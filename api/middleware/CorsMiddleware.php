<?php
// api/middleware/CorsMiddleware.php

require_once __DIR__ . '/../config/cors.php';

/**
 * CORS Middleware Class
 * Handles Cross-Origin Resource Sharing for API requests
 */
class CorsMiddleware
{
    private $config;

    public function __construct()
    {
        // Load CORS configuration
        $this->config = $this->loadConfig();
    }

    /**
     * Handle incoming CORS request
     */
    public function handle()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;

        // Handle preflight OPTIONS requests
        if ($method === 'OPTIONS') {
            return $this->handlePreflight($origin);
        }

        // Set CORS headers for regular requests
        $this->setCORSHeaders($origin);

        return true; // Continue processing the request
    }

    /**
     * Handle preflight OPTIONS requests
     */
    private function handlePreflight($origin)
    {
        // Validate origin if provided
        if ($origin && !$this->isOriginAllowed($origin)) {
            $this->sendErrorResponse('Origin not allowed', 403);
            return false;
        }

        // Get requested method from headers
        $requestedMethod = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] ?? null;
        $requestedHeaders = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? null;

        // Validate requested method
        if ($requestedMethod && !$this->isMethodAllowed($requestedMethod)) {
            $this->sendErrorResponse('Method not allowed', 405);
            return false;
        }

        // Validate requested headers
        if ($requestedHeaders && !$this->areHeadersAllowed($requestedHeaders)) {
            $this->sendErrorResponse('Headers not allowed', 400);
            return false;
        }

        // Set preflight response headers
        $this->setPreflightHeaders($origin);

        // Send 204 No Content response
        http_response_code(204);
        exit;
    }

    /**
     * Set CORS headers for regular requests
     */
    private function setCORSHeaders($origin)
    {
        if ($origin && $this->isOriginAllowed($origin)) {
            header("Access-Control-Allow-Origin: $origin");
            header("Vary: Origin");
        } elseif (in_array('*', $this->config['allowed_origins'])) {
            header("Access-Control-Allow-Origin: *");
        }

        header("Access-Control-Allow-Methods: " . implode(', ', $this->config['allowed_methods']));
        header("Access-Control-Allow-Headers: " . implode(', ', $this->config['allowed_headers']));
        header("Access-Control-Max-Age: " . $this->config['max_age']);

        if ($this->config['allow_credentials']) {
            header("Access-Control-Allow-Credentials: true");
        }

        // Set security headers
        $this->setSecurityHeaders();
    }

    /**
     * Set preflight response headers
     */
    private function setPreflightHeaders($origin)
    {
        if ($origin && $this->isOriginAllowed($origin)) {
            header("Access-Control-Allow-Origin: $origin");
            header("Vary: Origin");
        } elseif (in_array('*', $this->config['allowed_origins'])) {
            header("Access-Control-Allow-Origin: *");
        }

        $requestedMethod = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] ?? 'GET';
        header("Access-Control-Allow-Methods: $requestedMethod");

        $requestedHeaders = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? '';
        if ($requestedHeaders) {
            header("Access-Control-Allow-Headers: $requestedHeaders");
        }

        header("Access-Control-Max-Age: " . $this->config['max_age']);

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
        $securityHeaders = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin'
        ];

        $environment = getenv('APP_ENV') ?: 'production';
        if ($environment === 'production') {
            $securityHeaders = array_merge($securityHeaders, [
                'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
                'Content-Security-Policy' => "default-src 'self'"
            ]);
        }

        foreach ($securityHeaders as $header => $value) {
            header("$header: $value");
        }
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
        return in_array($method, $this->config['allowed_methods']);
    }

    /**
     * Check if requested headers are allowed
     */
    private function areHeadersAllowed($requestedHeaders)
    {
        $requestedHeaderArray = array_map('trim', explode(',', $requestedHeaders));
        $allowedHeaders = $this->config['allowed_headers'];

        // If all headers are allowed, return true
        if (in_array('*', $allowedHeaders)) {
            return true;
        }

        // Check each requested header
        foreach ($requestedHeaderArray as $header) {
            if (!in_array($header, $allowedHeaders) && !in_array(strtolower($header), array_map('strtolower', $allowedHeaders))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Send error response
     */
    private function sendErrorResponse($message, $statusCode)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $message,
            'status_code' => $statusCode
        ]);
    }

    /**
     * Load CORS configuration
     */
    private function loadConfig()
    {
        $environment = getenv('APP_ENV') ?: 'production';

        $configs = [
            'development' => [
                'allowed_origins' => ['*'],
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
                    'https://staging-app.example.com'
                ],
                'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
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
                'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
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
     * Static method to initialize CORS handling
     */
    public static function init()
    {
        $middleware = new self();
        return $middleware->handle();
    }
}