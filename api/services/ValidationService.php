<?php
// api/services/ValidationService.php

class ValidationService
{
    /**
     * Validate file signature matches MIME type to prevent extension spoofing
     */
    public static function validateFileSignature($filePath, $mimeType)
    {
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            throw new Exception("Cannot read file for validation");
        }

        $header = fread($handle, 16);
        fclose($handle);

        $signatures = [
            'image/jpeg' => ["\xFF\xD8\xFF"],
            'image/png' => ["\x89\x50\x4E\x47\x0D\x0A\x1A\x0A"],
            'image/gif' => ["\x47\x49\x46\x38"],
            'image/webp' => ["\x52\x49\x46\x46\x??\x??\x57\x45\x42\x50"],
            'image/svg+xml' => ["<svg", "<?xml"],
            'video/mp4' => ["\x66\x74\x79\x70\x4D\x53\x4E\x56", "\x66\x74\x79\x70\x69\x73\x6F\x6D"],
            'video/webm' => ["\x1A\x45\xDF\xA3"],
            'audio/mpeg' => ["\x49\x44\x33", "\xFF\xFB", "\xFF\xF3"],
            'audio/wav' => ["\x52\x49\x46\x46\x??\x??\x57\x41\x56\x45"],
            'application/pdf' => ["%PDF-"],
            'application/zip' => ["\x50\x4B\x03\x04", "\x50\x4B\x05\x06", "\x50\x4B\x07\x08"],
            'application/json' => ["{", "["],
            'text/plain' => ["", ""], // Text files can start with anything
        ];

        if (!isset($signatures[$mimeType])) {
            return true; // Skip validation for unknown MIME types
        }

        foreach ($signatures[$mimeType] as $signature) {
            if ($signature === "" || strpos($header, $signature) === 0) {
                return true;
            }
            // Handle wildcard patterns
            if (strpos($signature, '??') !== false) {
                $pattern = str_replace('??', '..', $signature);
                if (preg_match('/^' . $pattern . '/', $header)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Scan file for potentially malicious content patterns
     */
    public static function scanForMaliciousContent($filePath)
    {
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            throw new Exception("Cannot read file for security scan");
        }

        $content = fread($handle, min(8192, filesize($filePath))); // Read first 8KB
        fclose($handle);

        // Check for dangerous patterns
        $dangerousPatterns = [
            '/<\?php/i',
            '/<script/i',
            '/<iframe/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload=/i',
            '/onerror=/i',
            '/eval\(/i',
            '/exec\(/i',
            '/system\(/i',
            '/shell_exec\(/i',
            '/base64_decode\(/i',
            '/\$_GET/i',
            '/\$_POST/i',
            '/\$_REQUEST/i',
            '/\$_COOKIE/i',
            '/\$_FILES/i',
            '/\$_SERVER/i',
            '/\$_ENV/i',
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return false; // Malicious content detected
            }
        }

        // Check for suspicious executable signatures
        $executableSignatures = [
            'MZ',                    // Windows PE
            "\x7FELF",              // Linux ELF
            "\xCA\xFE\xBA\xBE",     // Java class
            "\xFE\xED\xFA\xCE",     // Mach-O binary (macOS)
            "\xFE\xED\xFA\xCF",     // Mach-O binary (macOS)
        ];

        foreach ($executableSignatures as $signature) {
            if (strpos($content, $signature) === 0) {
                return false; // Executable file detected
            }
        }

        return true; // File appears safe
    }

    /**
     * Validate image dimensions and properties
     */
    public static function validateImageDimensions($filePath, $maxWidth = 4096, $maxHeight = 4096)
    {
        $imageInfo = getimagesize($filePath);
        if ($imageInfo === false) {
            throw new Exception("Invalid image file");
        }

        list($width, $height, $type) = $imageInfo;

        if ($width > $maxWidth || $height > $maxHeight) {
            throw new Exception("Image dimensions exceed maximum allowed size ({$maxWidth}x{$maxHeight})");
        }

        if ($width <= 0 || $height <= 0) {
            throw new Exception("Invalid image dimensions");
        }

        // Additional validation for image types
        $allowedTypes = [
            IMAGETYPE_JPEG,
            IMAGETYPE_PNG,
            IMAGETYPE_GIF,
            IMAGETYPE_WEBP,
        ];

        if (!in_array($type, $allowedTypes) && $type !== IMAGETYPE_UNKNOWN) {
            throw new Exception("Unsupported image type");
        }

        return [
            'width' => $width,
            'height' => $height,
            'type' => $type,
            'mime' => image_type_to_mime_type($type)
        ];
    }

    /**
     * Validate video/audio metadata
     */
    public static function validateMediaMetadata($filePath)
    {
        // Basic file size check (must be reasonable for media files)
        $fileSize = filesize($filePath);
        if ($fileSize === false || $fileSize > MAX_FILE_SIZE) {
            throw new Exception("File size exceeds maximum limit");
        }

        $mimeType = mime_content_type($filePath);

        // For video files, we can do additional checks if the environment supports it
        if (in_array($mimeType, VIDEO_TYPES)) {
            // Basic video duration check (placeholder - would need ffmpeg for real validation)
            // For now, we'll just check file size as a proxy for duration
            if ($fileSize > 100 * 1024 * 1024) { // 100MB is likely too long
                throw new Exception("Video file appears too large");
            }
        }

        return [
            'size' => $fileSize,
            'mime_type' => $mimeType,
            'validated' => true
        ];
    }

    /**
     * Validate archive file integrity
     */
    public static function validateArchiveIntegrity($filePath, $mimeType)
    {
        if ($mimeType === 'application/zip') {
            $handle = fopen($filePath, 'rb');
            if (!$handle) {
                throw new Exception("Cannot read archive file");
            }

            // Check ZIP file signature
            $header = fread($handle, 4);
            fclose($handle);

            if ($header !== "PK\x03\x04" && $header !== "PK\x05\x06" && $header !== "PK\x07\x08") {
                throw new Exception("Invalid ZIP file signature");
            }

            // Additional check for encrypted archives
            $handle = fopen($filePath, 'rb');
            fseek($handle, 6);
            $flags = fread($handle, 2);
            fclose($handle);

            $flagValue = ord($flags[0]) | (ord($flags[1]) << 8);
            if ($flagValue & 0x01) {
                throw new Exception("Password-protected archives are not allowed");
            }
        }

        return true;
    }

    /**
     * Comprehensive file validation
     */
    public static function validateFile($filePath, $mimeType, $originalName)
    {
        // Check file extension against dangerous extensions list
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (in_array($extension, DANGEROUS_EXTENSIONS)) {
            throw new Exception("File extension '$extension' is not allowed");
        }

        // Validate file signature
        if (!self::validateFileSignature($filePath, $mimeType)) {
            throw new Exception("File signature does not match declared MIME type");
        }

        // Scan for malicious content
        if (!self::scanForMaliciousContent($filePath)) {
            throw new Exception("File contains potentially malicious content");
        }

        // Type-specific validations
        if (in_array($mimeType, IMAGE_TYPES)) {
            return self::validateImageDimensions($filePath);
        }

        if (in_array($mimeType, VIDEO_TYPES) || in_array($mimeType, AUDIO_TYPES)) {
            return self::validateMediaMetadata($filePath);
        }

        if (in_array($mimeType, ARCHIVE_TYPES)) {
            self::validateArchiveIntegrity($filePath, $mimeType);
        }

        // Basic validation for documents and other files
        $fileSize = filesize($filePath);
        if ($fileSize === false || $fileSize > MAX_FILE_SIZE) {
            throw new Exception("File size exceeds maximum limit");
        }

        return [
            'validated' => true,
            'mime_type' => $mimeType,
            'size' => $fileSize
        ];
    }
}