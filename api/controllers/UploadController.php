<?php
// api/controllers/UploadController.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../services/UploadService.php';
require_once __DIR__ . '/../services/ValidationService.php';
require_once __DIR__ . '/../services/ImageOptimizerService.php';

class UploadController {
    private $pdo;
    private $authService;
    private $uploadService;
    private $imageOptimizer;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->authService = new AuthService();
        $this->uploadService = new UploadService();
        $this->imageOptimizer = new ImageOptimizerService();
    }

    public function upload() {
        try {
            // Authenticate request
            $project = $this->authService->authenticate();
            if (!$project) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                return;
            }

            // Validate required parameters
            if (!isset($_FILES['file']) || !isset($_POST['bucket'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing file or bucket parameter']);
                return;
            }

            $project_id = $project['id'];
            $bucket_name = $_POST['bucket'];
            $file = $_FILES['file'];

            // Enhanced validation using ValidationService
            $validationResult = $this->validateFile($file);
            if (!$validationResult['valid']) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $validationResult['error']]);
                return;
            }

            // Check bucket exists and belongs to project
            $bucket = $this->getBucket($project_id, $bucket_name);
            if (!$bucket) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Bucket not found']);
                return;
            }

            // Perform upload with enhanced metadata
            $uploadResult = $this->performUpload($bucket, $file, $validationResult);
            if (!$uploadResult['success']) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $uploadResult['error']]);
                return;
            }

            // Queue optimization for images if enabled
            $optimizationResult = $this->queueOptimization($uploadResult['object_id'], $file);

            // Return success response with enhanced data
            echo json_encode([
                'success' => true,
                'data' => [
                    'object_id' => $uploadResult['object_id'],
                    'filename' => $uploadResult['filename'],
                    'size' => $uploadResult['size'],
                    'mime_type' => $uploadResult['mime_type'],
                    'url' => $uploadResult['url'],
                    'signed_url' => $uploadResult['signed_url'],
                    'metadata' => $uploadResult['metadata'],
                    'optimization_queued' => $optimizationResult['queued'],
                    'file_hash' => $uploadResult['file_hash'],
                    'uploaded_at' => date('c')
                ]
            ]);

        } catch (Exception $e) {
            error_log("Upload error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Upload failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Validate file using ValidationService
     */
    private function validateFile($file) {
        try {
            // Check for upload errors
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errorMessages = [
                    UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
                    UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
                    UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                    UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                    UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
                ];

                $errorMessage = $errorMessages[$file['error']] ?? 'Unknown upload error';
                return ['valid' => false, 'error' => $errorMessage];
            }

            // Basic file checks
            if ($file['size'] > MAX_FILE_SIZE) {
                return ['valid' => false, 'error' => 'File size exceeds maximum limit'];
            }

            // MIME type validation
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detectedMimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($detectedMimeType, ALLOWED_MIME_TYPES)) {
                return ['valid' => false, 'error' => 'File type not allowed'];
            }

            // Use ValidationService for advanced validation
            $validationResult = ValidationService::validateFile(
                $file['tmp_name'],
                $detectedMimeType,
                $file['name']
            );

            if (is_array($validationResult)) {
                // Validation passed, return metadata
                return [
                    'valid' => true,
                    'mime_type' => $detectedMimeType,
                    'metadata' => $validationResult
                ];
            }

            return ['valid' => true, 'mime_type' => $detectedMimeType, 'metadata' => null];

        } catch (Exception $e) {
            error_log("File validation error: " . $e->getMessage());
            return ['valid' => false, 'error' => 'File validation failed'];
        }
    }

    /**
     * Get bucket information
     */
    private function getBucket($projectId, $bucketName) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM buckets
                WHERE project_id = ? AND name = ?
                LIMIT 1
            ");
            $stmt->execute([$projectId, $bucketName]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Bucket lookup error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Perform the actual file upload
     */
    private function performUpload($bucket, $file, $validationResult) {
        try {
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $uniqueFilename = uniqid() . '.' . $extension;

            // Create storage directory
            $storageDir = STORAGE_PATH . "/{$bucket['project_id']}/{$bucket['name']}";
            if (!is_dir($storageDir)) {
                mkdir($storageDir, 0755, true);
            }

            $filePath = $storageDir . '/' . $uniqueFilename;

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                return ['success' => false, 'error' => 'Failed to move uploaded file'];
            }

            // Generate file hash
            $fileHash = hash_file('sha256', $filePath);

            // Prepare metadata
            $metadata = null;
            if ($validationResult['metadata']) {
                $metadata = json_encode($validationResult['metadata']);
            }

            // Insert into database
            $stmt = $this->pdo->prepare("
                INSERT INTO objects (bucket_id, filename, path, mime_type, size, file_hash, metadata)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $bucket['id'],
                $file['name'],
                $filePath,
                $validationResult['mime_type'],
                $file['size'],
                $fileHash,
                $metadata
            ]);

            $objectId = $this->pdo->lastInsertId();

            // Generate signed URL
            $signedUrl = $this->generateSignedUrl($objectId);

            return [
                'success' => true,
                'object_id' => $objectId,
                'filename' => $file['name'],
                'size' => $file['size'],
                'mime_type' => $validationResult['mime_type'],
                'url' => "/api/get-file?object_id={$objectId}",
                'signed_url' => $signedUrl,
                'metadata' => $metadata ? json_decode($metadata, true) : null,
                'file_hash' => $fileHash
            ];

        } catch (Exception $e) {
            error_log("Upload performance error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Upload processing failed'];
        }
    }

    /**
     * Generate signed URL for the uploaded object
     */
    private function generateSignedUrl($objectId) {
        try {
            require_once __DIR__ . '/../services/UrlSigningService.php';
            $urlService = new UrlSigningService();
            return $urlService->generateUrl($objectId, 3600); // 1 hour expiry
        } catch (Exception $e) {
            error_log("Signed URL generation error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Queue optimization for uploaded file if it's an image
     */
    private function queueOptimization($objectId, $file) {
        try {
            // Only optimize images and if optimization is enabled
            if (!ENABLE_OPTIMIZATION) {
                return ['queued' => false, 'reason' => 'Optimization disabled'];
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, IMAGE_TYPES)) {
                return ['queued' => false, 'reason' => 'Not an image file'];
            }

            // Create optimization job
            $stmt = $this->pdo->prepare("
                INSERT INTO optimization_jobs (object_id, job_type, status, priority)
                VALUES (?, ?, ?, ?)
            ");

            // Queue multiple jobs for comprehensive optimization
            $jobs = [
                ['thumbnail_generation', 10], // High priority
                ['image_optimization', 5],    // Medium priority
                ['webp_conversion', 3]        // Lower priority
            ];

            foreach ($jobs as $job) {
                $stmt->execute([$objectId, $job[0], 'pending', $job[1]]);
            }

            return ['queued' => true, 'jobs_count' => count($jobs)];

        } catch (Exception $e) {
            error_log("Optimization queue error: " . $e->getMessage());
            return ['queued' => false, 'reason' => 'Failed to queue optimization'];
        }
    }
}
