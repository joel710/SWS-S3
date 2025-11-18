<?php
// api/controllers/CorsController.php

require_once __DIR__ . '/../config/cors.php';

/**
 * CORS Controller
 * Handles CORS preflight requests and headers
 */
class CorsController
{
    /**
     * Handle CORS preflight requests
     * OPTIONS /api/{path}
     */
    public function preflight()
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
        $requestMethod = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] ?? null;
        $requestHeaders = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? null;

        try {
            // Load CORS configuration
            $this->loadConfiguration();

            // Validate origin if provided
            if ($origin && !$this->isOriginAllowed($origin)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Origin not allowed']);
                return;
            }

            // Validate requested method if provided
            if ($requestMethod && !$this->isMethodAllowed($requestMethod)) {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
                return;
            }

            // Validate requested headers if provided
            if ($requestHeaders && !$this->areHeadersAllowed($requestHeaders)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Headers not allowed']);
                return;
            }

            // Set preflight response headers
            $this->setPreflightHeaders($origin, $requestMethod, $requestHeaders);

            // Send 204 No Content response for successful preflight
            http_response_code(204);

        } catch (Exception $e) {
            error_log("CORS preflight error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'CORS preflight failed']);
        }
    }

    /**
     * Load CORS configuration
     */
    private function loadConfiguration()
    {
        $this->config = $this->getCorsConfig();
    }

    /**
     * Get CORS configuration based on environment
     */
    private function getCorsConfig()
    {
        $environment = getenv('APP_ENV') ?: 'production';

        $configs = [
            'development' => [
                'allowed_origins' => ['*'],
                'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'HEAD'],
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
                'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'HEAD'],
                'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-Api-Key'],
                'max_age' => 3600,
                'allow_credentials' => true
            ],

            'production' => [
                'allowed_origins' => [
                    'https://app.example.com',
                    'https://admin.example.com',
                    'https://cdn.example.com'
                ],
                'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'HEAD'],
                'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-Api-Key'],
                'max_age' => 86400,
                'allow_credentials' => true
            ]
        ];

        // Load custom origins from environment
        $customOrigins = getenv('ALLOW_ORIGINS');
        if ($customOrigins) {
            $originList = array_map('trim', explode(',', $customOrigins));

            if ($environment === 'production') {
                $configs[$environment]['allowed_origins'] = $originList;
            } else {
                $configs[$environment]['allowed_origins'] = array_merge(
                    $configs[$environment]['allowed_origins'],
                    $originList
                );
            }
        }

        return $configs[$environment] ?? $configs['production'];
    }

    /**
     * Check if origin is allowed
     */
    private function isOriginAllowed($origin)
    {
        if (in_array('*', $this->config['allowed_origins'])) {
            return true;
        }

        return in_array($origin, $this->config['allowed_origins']);
    }

    /**
     * Check if HTTP method is allowed
     */
    private function isMethodAllowed($method)
    {
        return in_array(strtoupper($method), $this->config['allowed_methods']);
    }

    /**
     * Check if requested headers are allowed
     */
    private function areHeadersAllowed($requestedHeaders)
    {
        if (in_array('*', $this->config['allowed_headers'])) {
            return true;
        }

        $requestedHeaderArray = array_map('trim', explode(',', $requestedHeaders));
        $allowedHeaders = array_map('strtolower', $this->config['allowed_headers']);

        foreach ($requestedHeaderArray as $header) {
            if (!in_array(strtolower($header), $allowedHeaders)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Set preflight response headers
     */
    private function setPreflightHeaders($origin, $requestMethod, $requestHeaders)
    {
        // Set Access-Control-Allow-Origin
        if ($origin && $this->isOriginAllowed($origin)) {
            header("Access-Control-Allow-Origin: $origin");
            header("Vary: Origin");
        } elseif (in_array('*', $this->config['allowed_origins'])) {
            header("Access-Control-Allow-Origin: *");
        }

        // Set Access-Control-Allow-Methods
        if ($requestMethod) {
            header("Access-Control-Allow-Methods: $requestMethod");
        } else {
            header("Access-Control-Allow-Methods: " . implode(', ', $this->config['allowed_methods']));
        }

        // Set Access-Control-Allow-Headers
        if ($requestHeaders) {
            header("Access-Control-Allow-Headers: $requestHeaders");
        } else {
            header("Access-Control-Allow-Headers: " . implode(', ', $this->config['allowed_headers']));
        }

        // Set Access-Control-Max-Age
        header("Access-Control-Max-Age: " . $this->config['max_age']);

        // Set Access-Control-Allow-Credentials
        if ($this->config['allow_credentials']) {
            header("Access-Control-Allow-Credentials: true");
        }

        // Set security headers for preflight
        $this->setSecurityHeaders();
    }

    /**
     * Set security headers
     */
    private function setSecurityHeaders()
    {
        header("X-Content-Type-Options: nosniff");
        header("X-Frame-Options: DENY");
        header("X-XSS-Protection: 1; mode=block");
        header("Referrer-Policy: strict-origin-when-cross-origin");

        // Additional headers for production
        $environment = getenv('APP_ENV') ?: 'production';
        if ($environment === 'production') {
            header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
        }
    }

    /**
     * Set CORS headers for regular responses
     * This can be called by other controllers
     */
    public static function setCORSHeaders()
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;

        // Use the existing CORS configuration
        if (function_exists('setCORSHeaders')) {
            setCORSHeaders($origin);
            return;
        }

        // Fallback implementation
        $instance = new self();
        $instance->loadConfiguration();

        if ($origin && $instance->isOriginAllowed($origin)) {
            header("Access-Control-Allow-Origin: $origin");
            header("Vary: Origin");
        } elseif (in_array('*', $instance->config['allowed_origins'])) {
            header("Access-Control-Allow-Origin: *");
        }

        header("Access-Control-Allow-Methods: " . implode(', ', $instance->config['allowed_methods']));
        header("Access-Control-Allow-Headers: " . implode(', ', $instance->config['allowed_headers']));
        header("Access-Control-Max-Age: " . $instance->config['max_age']);

        if ($instance->config['allow_credentials']) {
            header("Access-Control-Allow-Credentials: true");
        }

        $instance->setSecurityHeaders();
    }
}