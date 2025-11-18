<?php
// api/config/config.php

// Maximum file size: 50MB for mobile app optimization
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50 MB

// Comprehensive MIME type support for modern applications
define('ALLOWED_MIME_TYPES', [
    // Images
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'image/svg+xml',
    'image/avif',

    // Videos
    'video/mp4',
    'video/webm',
    'video/quicktime',
    'video/x-msvideo',

    // Audio
    'audio/mpeg',
    'audio/wav',
    'audio/ogg',
    'audio/mp4',
    'audio/aac',

    // Documents
    'application/pdf',
    'text/plain',
    'text/csv',

    // Archives
    'application/zip',
    'application/x-rar-compressed',

    // JSON Data
    'application/json'
]);

// File type categories for validation
define('IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/avif']);
define('VIDEO_TYPES', ['video/mp4', 'video/webm', 'video/quicktime', 'video/x-msvideo']);
define('AUDIO_TYPES', ['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp4', 'audio/aac']);
define('DOCUMENT_TYPES', ['application/pdf', 'text/plain', 'text/csv']);
define('ARCHIVE_TYPES', ['application/zip', 'application/x-rar-compressed']);
define('DATA_TYPES', ['application/json']);

// Dangerous file extensions blacklist (security)
define('DANGEROUS_EXTENSIONS', [
    'php', 'php3', 'php4', 'php5', 'phtml',
    'exe', 'bat', 'cmd', 'com', 'scr',
    'js', 'vbs', 'ps1', 'sh', 'py',
    'pl', 'rb', 'asp', 'aspx', 'jsp'
]);

// Image validation limits
define('MAX_IMAGE_WIDTH', 4096);
define('MAX_IMAGE_HEIGHT', 4096);
define('MAX_VIDEO_DURATION', 600); // 10 minutes in seconds
