<?php
// api/config/cors.php

/**
 * CORS Configuration for S3-Compatible Object Storage
 * Provides environment-specific CORS policies for app integration
 */

// Get environment from environment variable or default to production
$environment = getenv('APP_ENV') ?: 'production';

// Define allowed origins based on environment
$corsConfig = [
    'development' => [
        'allowed_origins' => ['*'], // Allow all origins in development
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['*'],
        'max_age' => 3600,
        'allow_credentials' => true
    ],

    'staging' => [
        'allowed_origins' => [
            'http://localhost:3000',
            'http://localhost:8080',
            'http://127.0.0.1:3000',
            'http://127.0.0.1:8080',
            'https://staging-app.example.com',
            'https://staging-admin.example.com'
        ],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-Api-Key'],
        'max_age' => 3600,
        'allow_credentials' => true
    ],

    'production' => [
        'allowed_origins' => [
            // Default production origins - should be customized based on actual app domains
            'https://app.example.com',
            'https://admin.example.com',
            'https://cdn.example.com'
        ],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-Api-Key'],
        'max_age' => 86400, // 24 hours for production
        'allow_credentials' => true
    ]
];

// Load custom origins from environment if specified
$customOrigins = getenv('ALLOW_ORIGINS');
if ($customOrigins) {
    $originList = array_map('trim', explode(',', $customOrigins));

    // In production, replace with custom origins
    if ($environment === 'production') {
        $corsConfig[$environment]['allowed_origins'] = $originList;
    } else {
        // In non-production, add to existing origins
        $corsConfig[$environment]['allowed_origins'] = array_merge(
            $corsConfig[$environment]['allowed_origins'],
            $originList
        );
    }
}

// Get current environment configuration
$currentConfig = $corsConfig[$environment] ?? $corsConfig['production'];

// Additional security headers
$securityHeaders = [
    'X-Content-Type-Options' => 'nosniff',
    'X-Frame-Options' => 'DENY',
    'X-XSS-Protection' => '1; mode=block',
    'Referrer-Policy' => 'strict-origin-when-cross-origin'
];

// In production, add additional security headers
if ($environment === 'production') {
    $securityHeaders = array_merge($securityHeaders, [
        'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains; preload',
        'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'"
    ]);
}

/**
 * Function to set CORS headers based on request
 */
function setCORSHeaders($origin = null)
{
    global $currentConfig, $securityHeaders;

    // Handle Origin header
    if ($origin) {
        if (in_array('*', $currentConfig['allowed_origins']) || in_array($origin, $currentConfig['allowed_origins'])) {
            header("Access-Control-Allow-Origin: $origin");
        }
    } elseif (in_array('*', $currentConfig['allowed_origins'])) {
        header("Access-Control-Allow-Origin: *");
    }

    // Set other CORS headers
    header("Access-Control-Allow-Methods: " . implode(', ', $currentConfig['allowed_methods']));
    header("Access-Control-Allow-Headers: " . implode(', ', $currentConfig['allowed_headers']));
    header("Access-Control-Max-Age: " . $currentConfig['max_age']);

    // Handle credentials
    if ($currentConfig['allow_credentials']) {
        header("Access-Control-Allow-Credentials: true");
    }

    // Set security headers
    foreach ($securityHeaders as $header => $value) {
        header("$header: $value");
    }
}

/**
 * Function to validate if origin is allowed
 */
function isOriginAllowed($origin)
{
    global $currentConfig;

    if (in_array('*', $currentConfig['allowed_origins'])) {
        return true;
    }

    return in_array($origin, $currentConfig['allowed_origins']);
}

/**
 * Function to handle preflight OPTIONS requests
 */
function handlePreflightRequest($origin)
{
    global $currentConfig;

    if (!isOriginAllowed($origin)) {
        http_response_code(403);
        echo json_encode(['error' => 'Origin not allowed']);
        return false;
    }

    setCORSHeaders($origin);
    http_response_code(204); // No Content
    return true;
}

// Make functions globally available
if (!function_exists('setCORSHeaders')) {
    function setCORSHeaders($origin = null) {
        return \setCORSHeaders($origin);
    }
}

if (!function_exists('isOriginAllowed')) {
    function isOriginAllowed($origin) {
        return \isOriginAllowed($origin);
    }
}

if (!function_exists('handlePreflightRequest')) {
    function handlePreflightRequest($origin) {
        return \handlePreflightRequest($origin);
    }
}