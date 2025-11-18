<?php
// api/controllers/OptimizationController.php

require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../services/ImageOptimizerService.php';
require_once __DIR__ . '/../config/database.php';

class OptimizationController
{
    private $pdo;
    private $imageOptimizer;

    public function __construct()
    {
        global $pdo;
        $this->pdo = $pdo;
        $this->imageOptimizer = new ImageOptimizerService();
    }

    /**
     * Request image optimization for an object
     * POST /api/optimize
     */
    public function optimize()
    {
        header("Content-Type: application/json");

        try {
            // Authenticate request
            $authService = new AuthService();
            $project = $authService->authenticate();
            if (!$project) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                return;
            }

            // Get request data
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input || !isset($input['object_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Object ID required']);
                return;
            }

            $objectId = (int)$input['object_id'];
            $optimizeOriginal = isset($input['optimize_original']) ? (bool)$input['optimize_original'] : true;
            $generateThumbnails = isset($input['generate_thumbnails']) ? (bool)$input['generate_thumbnails'] : true;
            $convertToWebP = isset($input['convert_webp']) ? (bool)$input['convert_webp'] : false;

            // Verify object exists and belongs to project
            $stmt = $this->pdo->prepare("
                SELECT o.*, b.project_id
                FROM objects o
                JOIN buckets b ON o.bucket_id = b.id
                WHERE o.id = ? AND b.project_id = ?
            ");
            $stmt->execute([$objectId, $project['id']]);
            $object = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$object) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Object not found']);
                return;
            }

            // Check if file is an image
            if (!in_array($object['mime_type'], IMAGE_TYPES)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Object is not an image']);
                return;
            }

            // Check if file exists
            if (!file_exists($object['path'])) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Original file not found']);
                return;
            }

            $results = [];
            $thumbnailPaths = [];
            $originalOptimized = false;

            try {
                // Optimize original image
                if ($optimizeOriginal) {
                    $originalOptimized = $this->imageOptimizer->optimizeOriginal($object['path']);
                    $results['original_optimized'] = $originalOptimized;
                }

                // Generate thumbnails
                if ($generateThumbnails) {
                    $thumbnailDir = dirname($object['path']) . '/thumbnails';
                    $thumbnails = $this->imageOptimizer->generateThumbnails($object['path'], $thumbnailDir, $objectId);
                    $thumbnailPaths = $thumbnails;
                    $results['thumbnails_generated'] = !empty($thumbnails);
                }

                // Convert to WebP
                if ($convertToWebP) {
                    $webpPath = $this->imageOptimizer->convertToWebP($object['path']);
                    $results['webp_converted'] = $webpPath ? true : false;
                    if ($webpPath) {
                        $results['webp_path'] = $webpPath;
                    }
                }

                // Update database with optimization status
                $stmt = $this->pdo->prepare("
                    UPDATE objects
                    SET optimized_at = NOW(), thumbnails_available = ?
                    WHERE id = ?
                ");
                $stmt->execute([!empty($thumbnailPaths), $objectId]);

                // Store thumbnail paths in metadata
                if (!empty($thumbnailPaths)) {
                    $metadata = $object['metadata'] ? json_decode($object['metadata'], true) : [];
                    $metadata['thumbnails'] = $thumbnailPaths;
                    $metadataJson = json_encode($metadata);

                    $stmt = $this->pdo->prepare("
                        UPDATE objects
                        SET metadata = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$metadataJson, $objectId]);
                }

                echo json_encode([
                    'success' => true,
                    'data' => [
                        'object_id' => $objectId,
                        'optimization_results' => $results,
                        'thumbnail_urls' => $this->generateThumbnailUrls($objectId),
                        'optimized_at' => date('c')
                    ]
                ]);

            } catch (Exception $e) {
                error_log("Optimization failed for object {$objectId}: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Optimization failed: ' . $e->getMessage()]);
            }

        } catch (Exception $e) {
            error_log("Optimization controller error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Internal server error']);
        }
    }

    /**
     * Serve thumbnail images
     * GET /api/thumbnails/{id}/{size}.{format}
     */
    public function serveThumbnail()
    {
        try {
            // Parse request URI
            $requestUri = $_SERVER['REQUEST_URI'];
            $pattern = '/^\/api\/thumbnails\/(\d+)\/([a-z]+)\.(jpg|webp)$/';
            if (!preg_match($pattern, $requestUri, $matches)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid thumbnail request']);
                return;
            }

            $objectId = (int)$matches[1];
            $size = $matches[2];
            $format = $matches[3];

            // Validate size
            $validSizes = ['xs', 'sm', 'md', 'lg'];
            if (!in_array($size, $validSizes)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid thumbnail size']);
                return;
            }

            // Get object information
            $stmt = $this->pdo->prepare("
                SELECT o.*, b.project_id, b.is_public
                FROM objects o
                JOIN buckets b ON o.bucket_id = b.id
                WHERE o.id = ?
            ");
            $stmt->execute([$objectId]);
            $object = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$object) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Object not found']);
                return;
            }

            // Check authentication for private buckets
            if (!$object['is_public']) {
                $authService = new AuthService();
                $project = $authService->authenticate();
                if (!$project || $project['id'] != $object['project_id']) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Access denied']);
                    return;
                }
            }

            // Build thumbnail path
            $thumbnailDir = dirname($object['path']) . '/thumbnails';
            $thumbnailPath = $thumbnailDir . "/{$objectId}_{$size}.{$format}";

            // Fallback to JPG if WebP not found
            if ($format === 'webp' && !file_exists($thumbnailPath)) {
                $thumbnailPath = $thumbnailDir . "/{$objectId}_{$size}.jpg";
            }

            if (!file_exists($thumbnailPath)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Thumbnail not found']);
                return;
            }

            // Set appropriate headers
            $this->setThumbnailHeaders($thumbnailPath, $format);

            // Serve file
            readfile($thumbnailPath);

        } catch (Exception $e) {
            error_log("Thumbnail serving error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Internal server error']);
        }
    }

    /**
     * Batch optimize multiple objects
     * POST /api/optimize/batch
     */
    public function batchOptimize()
    {
        header("Content-Type: application/json");

        try {
            // Authenticate request
            $authService = new AuthService();
            $project = $authService->authenticate();
            if (!$project) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input || !isset($input['object_ids']) || !is_array($input['object_ids'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Object IDs array required']);
                return;
            }

            $objectIds = array_map('intval', $input['object_ids']);
            $results = [];
            $processed = 0;
            $failed = 0;

            foreach ($objectIds as $objectId) {
                try {
                    // Verify object exists and belongs to project
                    $stmt = $this->pdo->prepare("
                        SELECT o.*, b.project_id
                        FROM objects o
                        JOIN buckets b ON o.bucket_id = b.id
                        WHERE o.id = ? AND b.project_id = ?
                    ");
                    $stmt->execute([$objectId, $project['id']]);
                    $object = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$object || !in_array($object['mime_type'], IMAGE_TYPES)) {
                        $results[] = ['object_id' => $objectId, 'success' => false, 'error' => 'Invalid object'];
                        $failed++;
                        continue;
                    }

                    // Perform optimization (simplified for batch)
                    if (file_exists($object['path'])) {
                        $thumbnailDir = dirname($object['path']) . '/thumbnails';
                        $thumbnails = $this->imageOptimizer->generateThumbnails($object['path'], $thumbnailDir, $objectId);

                        $results[] = ['object_id' => $objectId, 'success' => true, 'thumbnails' => count($thumbnails)];
                        $processed++;
                    } else {
                        $results[] = ['object_id' => $objectId, 'success' => false, 'error' => 'File not found'];
                        $failed++;
                    }

                } catch (Exception $e) {
                    $results[] = ['object_id' => $objectId, 'success' => false, 'error' => $e->getMessage()];
                    $failed++;
                }
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'total_objects' => count($objectIds),
                    'processed' => $processed,
                    'failed' => $failed,
                    'results' => $results
                ]
            ]);

        } catch (Exception $e) {
            error_log("Batch optimization error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Internal server error']);
        }
    }

    /**
     * Generate thumbnail URLs for an object
     */
    private function generateThumbnailUrls($objectId)
    {
        $urls = [];
        $sizes = ['xs', 'sm', 'md', 'lg'];

        foreach ($sizes as $size) {
            $urls[$size] = [
                'jpg' => "/api/thumbnails/{$objectId}/{$size}.jpg",
                'webp' => "/api/thumbnails/{$objectId}/{$size}.webp"
            ];
        }

        return $urls;
    }

    /**
     * Set appropriate headers for thumbnail serving
     */
    private function setThumbnailHeaders($thumbnailPath, $format)
    {
        $mimeType = $format === 'webp' ? 'image/webp' : 'image/jpeg';
        $fileSize = filesize($thumbnailPath);
        $lastModified = filemtime($thumbnailPath);

        header("Content-Type: $mimeType");
        header("Content-Length: $fileSize");
        header("Last-Modified: " . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
        header("Cache-Control: public, max-age=31536000"); // 1 year cache
        header("Expires: " . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');

        // ETag support
        $etag = md5($lastModified . $fileSize);
        header("ETag: \"$etag\"");

        // Conditional requests
        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $lastModified) {
            http_response_code(304);
            exit;
        }
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) == "\"$etag\"") {
            http_response_code(304);
            exit;
        }
    }
}