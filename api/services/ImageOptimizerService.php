<?php
// api/services/ImageOptimizerService.php

require_once __DIR__ . '/../config/config.php';

class ImageOptimizerService
{
    private $qualityJpeg = 85;
    private $qualityWebP = 80;
    private $thumbnailSizes = [
        'xs' => 100,
        'sm' => 300,
        'md' => 600,
        'lg' => 1200
    ];

    public function __construct()
    {
        // Set quality from config constants if available
        if (defined('JPEG_QUALITY')) {
            $this->qualityJpeg = JPEG_QUALITY;
        }
        if (defined('WEBP_QUALITY')) {
            $this->qualityWebP = WEBP_QUALITY;
        }
        if (defined('THUMBNAIL_SIZES')) {
            $sizes = explode(',', THUMBNAIL_SIZES);
            if (count($sizes) === 4) {
                $this->thumbnailSizes = [
                    'xs' => (int)$sizes[0],
                    'sm' => (int)$sizes[1],
                    'md' => (int)$sizes[2],
                    'lg' => (int)$sizes[3]
                ];
            }
        }
    }

    /**
     * Generate all thumbnail sizes for an image
     */
    public function generateThumbnails($originalPath, $outputDir, $objectId)
    {
        if (!file_exists($originalPath)) {
            throw new Exception("Original image file not found");
        }

        // Create output directory if it doesn't exist
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $thumbnails = [];
        $imageInfo = getimagesize($originalPath);
        if ($imageInfo === false) {
            throw new Exception("Cannot read image for thumbnail generation");
        }

        list($originalWidth, $originalHeight, $type) = $imageInfo;

        // Generate each thumbnail size
        foreach ($this->thumbnailSizes as $sizeName => $maxDimension) {
            $thumbnailPath = $outputDir . "/{$objectId}_{$sizeName}.jpg";

            try {
                $this->createThumbnail($originalPath, $thumbnailPath, $maxDimension, $originalWidth, $originalHeight, $type);

                $thumbnails[$sizeName] = [
                    'path' => $thumbnailPath,
                    'size' => filesize($thumbnailPath),
                    'dimensions' => $this->calculateThumbnailDimensions($originalWidth, $originalHeight, $maxDimension)
                ];
            } catch (Exception $e) {
                error_log("Failed to create {$sizeName} thumbnail for object {$objectId}: " . $e->getMessage());
                // Continue with other sizes even if one fails
            }
        }

        // Generate WebP versions if GD supports it
        if (function_exists('imagewebp')) {
            foreach ($this->thumbnailSizes as $sizeName => $maxDimension) {
                $webpPath = $outputDir . "/{$objectId}_{$sizeName}.webp";

                try {
                    $this->createThumbnail($originalPath, $webpPath, $maxDimension, $originalWidth, $originalHeight, $type, true);

                    if (!isset($thumbnails[$sizeName])) {
                        $thumbnails[$sizeName] = [];
                    }
                    $thumbnails[$sizeName]['webp_path'] = $webpPath;
                    $thumbnails[$sizeName]['webp_size'] = filesize($webpPath);
                } catch (Exception $e) {
                    error_log("Failed to create {$sizeName} WebP thumbnail for object {$objectId}: " . $e->getMessage());
                }
            }
        }

        return $thumbnails;
    }

    /**
     * Create a single thumbnail
     */
    private function createThumbnail($sourcePath, $destPath, $maxDimension, $originalWidth, $originalHeight, $imageType, $isWebP = false)
    {
        // Create image resource based on type
        $sourceImage = $this->createImageResource($sourcePath, $imageType);
        if (!$sourceImage) {
            throw new Exception("Failed to create image resource from source");
        }

        // Calculate thumbnail dimensions
        $thumbDimensions = $this->calculateThumbnailDimensions($originalWidth, $originalHeight, $maxDimension);
        $thumbWidth = $thumbDimensions['width'];
        $thumbHeight = $thumbDimensions['height'];

        // Create true color thumbnail canvas
        $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);
        if (!$thumbnail) {
            imagedestroy($sourceImage);
            throw new Exception("Failed to create thumbnail canvas");
        }

        // Handle transparency for PNG and GIF
        if ($imageType === IMAGETYPE_PNG || $imageType === IMAGETYPE_GIF) {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
            $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
            imagefilledrectangle($thumbnail, 0, 0, $thumbWidth, $thumbHeight, $transparent);
        }

        // Resample and resize
        if (!imagecopyresampled($thumbnail, $sourceImage, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $originalWidth, $originalHeight)) {
            imagedestroy($sourceImage);
            imagedestroy($thumbnail);
            throw new Exception("Failed to resample image");
        }

        // Remove EXIF data and optimize the original
        $this->removeEXIF($thumbnail);

        // Save thumbnail
        $success = false;
        if ($isWebP && function_exists('imagewebp')) {
            $success = imagewebp($thumbnail, $destPath, $this->qualityWebP);
        } else {
            switch ($imageType) {
                case IMAGETYPE_JPEG:
                    $success = imagejpeg($thumbnail, $destPath, $this->qualityJpeg);
                    break;
                case IMAGETYPE_PNG:
                    $success = imagepng($thumbnail, $destPath, 9); // Maximum compression
                    break;
                case IMAGETYPE_GIF:
                    $success = imagegif($thumbnail, $destPath);
                    break;
                default:
                    // Default to JPEG for other types
                    $success = imagejpeg($thumbnail, $destPath, $this->qualityJpeg);
                    break;
            }
        }

        imagedestroy($sourceImage);
        imagedestroy($thumbnail);

        if (!$success) {
            throw new Exception("Failed to save thumbnail");
        }

        // Optimize file size
        $this->optimizeFile($destPath);
    }

    /**
     * Create image resource from file based on type
     */
    private function createImageResource($filePath, $imageType)
    {
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg($filePath);
            case IMAGETYPE_PNG:
                return imagecreatefrompng($filePath);
            case IMAGETYPE_GIF:
                return imagecreatefromgif($filePath);
            case IMAGETYPE_WEBP:
                return imagecreatefromwebp($filePath);
            default:
                throw new Exception("Unsupported image type for processing");
        }
    }

    /**
     * Calculate thumbnail dimensions maintaining aspect ratio
     */
    private function calculateThumbnailDimensions($originalWidth, $originalHeight, $maxDimension)
    {
        if ($originalWidth <= $maxDimension && $originalHeight <= $maxDimension) {
            return ['width' => $originalWidth, 'height' => $originalHeight];
        }

        $ratio = min($maxDimension / $originalWidth, $maxDimension / $originalHeight);
        return [
            'width' => (int)round($originalWidth * $ratio),
            'height' => (int)round($originalHeight * $ratio)
        ];
    }

    /**
     * Remove EXIF data from image
     */
    private function removeEXIF($image)
    {
        // For GD library, EXIF is already stripped during processing
        // This is a placeholder for additional EXIF removal if needed
        return true;
    }

    /**
     * Optimize original image file
     */
    public function optimizeOriginal($filePath)
    {
        if (!file_exists($filePath)) {
            throw new Exception("Original file not found for optimization");
        }

        $imageInfo = getimagesize($filePath);
        if ($imageInfo === false) {
            return false; // Not an image, skip optimization
        }

        list($width, $height, $type) = $imageInfo;

        // Skip optimization for very large images that might cause memory issues
        if ($width > MAX_IMAGE_WIDTH || $height > MAX_IMAGE_HEIGHT) {
            return false;
        }

        // Only optimize JPEG and PNG images
        if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG])) {
            return false;
        }

        $tempPath = $filePath . '.temp';
        $sourceImage = $this->createImageResource($filePath, $type);

        if (!$sourceImage) {
            return false;
        }

        $success = false;
        if ($type === IMAGETYPE_JPEG) {
            $success = imagejpeg($sourceImage, $tempPath, $this->qualityJpeg);
        } elseif ($type === IMAGETYPE_PNG) {
            $success = imagepng($sourceImage, $tempPath, 9);
        }

        imagedestroy($sourceImage);

        if ($success && file_exists($tempPath)) {
            $originalSize = filesize($filePath);
            $optimizedSize = filesize($tempPath);

            // Only replace if optimized version is smaller
            if ($optimizedSize < $originalSize) {
                if (!rename($tempPath, $filePath)) {
                    unlink($tempPath);
                    return false;
                }
                return true;
            } else {
                unlink($tempPath);
                return false;
            }
        }

        if (file_exists($tempPath)) {
            unlink($tempPath);
        }

        return false;
    }

    /**
     * Convert image to WebP format
     */
    public function convertToWebP($filePath)
    {
        if (!function_exists('imagewebp')) {
            return false; // WebP not supported
        }

        $imageInfo = getimagesize($filePath);
        if ($imageInfo === false) {
            return false;
        }

        $webpPath = $filePath . '.webp';
        list($width, $height, $type) = $imageInfo;

        $sourceImage = $this->createImageResource($filePath, $type);
        if (!$sourceImage) {
            return false;
        }

        $success = imagewebp($sourceImage, $webpPath, $this->qualityWebP);
        imagedestroy($sourceImage);

        return $success && file_exists($webpPath) ? $webpPath : false;
    }

    /**
     * Get thumbnail URL for an object
     */
    public static function getThumbnailUrl($objectId, $size, $format = 'jpg')
    {
        return "/api/thumbnails/{$objectId}/{$size}.{$format}";
    }

    /**
     * Optimize file using available tools (placeholder for future implementation)
     */
    private function optimizeFile($filePath)
    {
        // This is a placeholder for file optimization
        // In a production environment, you might integrate with tools like:
        // - jpegoptim for JPEG files
        // - optipng for PNG files
        // - gifsicle for GIF files

        return true;
    }

    /**
     * Get image metadata
     */
    public function getImageMetadata($filePath)
    {
        $imageInfo = getimagesize($filePath);
        if ($imageInfo === false) {
            return null;
        }

        $metadata = [
            'width' => $imageInfo[0],
            'height' => $imageInfo[1],
            'type' => $imageInfo[2],
            'mime' => image_type_to_mime_type($imageInfo[2]),
            'size' => filesize($filePath),
            'created_at' => date('Y-m-d H:i:s', filemtime($filePath))
        ];

        // Additional EXIF data for JPEG files
        if ($imageInfo[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($filePath);
            if ($exif !== false) {
                $metadata['exif'] = $exif;
            }
        }

        return $metadata;
    }
}