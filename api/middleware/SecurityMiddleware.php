<?php
// api/middleware/SecurityMiddleware.php

/**
 * Security Middleware Class
 * Provides SSL enforcement, security headers, and request validation
 */
class SecurityMiddleware
{
    private $environment;

    public function __construct()
    {
        $this->environment = getenv('APP_ENV') ?: 'production';
    }

    /**
     * Handle security middleware
     */
    public function handle()
    {
        // Force HTTPS in production
        if (!$this->enforceHTTPS()) {
            return false;
        }

        // Set security headers
        $this->setSecurityHeaders();

        // Validate and sanitize request
        if (!$this->validateRequest()) {
            return false;
        }

        // Check for common attack patterns
        if (!$this->checkAttackPatterns()) {
            return false;
        }

        return true; // Continue processing
    }

    /**
     * Force HTTPS in production
     */
    private function enforceHTTPS()
    {
        $sslEnforce = getenv('SSL_ENFORCE') !== 'false'; // Default to true

        if ($sslEnforce && $this->environment === 'production') {
            // Check if request is not secure
            if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
                // Check for load balancer headers
                $proto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
                $scheme = $_SERVER['HTTP_X_FORWARDED_SCHEME'] ?? '';

                if ($proto !== 'https' && $scheme !== 'https') {
                    // Redirect to HTTPS
                    $httpsUrl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                    header('Location: ' . $httpsUrl, true, 301);
                    exit;
                }
            }
        }

        return true;
    }

    /**
     * Set security headers
     */
    private function setSecurityHeaders()
    {
        // Content Security Policy
        if ($this->environment === 'production') {
            $csp = "default-src 'self'; " .
                   "script-src 'self' 'unsafe-inline'; " .
                   "style-src 'self' 'unsafe-inline'; " .
                   "img-src 'self' data: blob:; " .
                   "font-src 'self'; " .
                   "connect-src 'self'; " .
                   "frame-ancestors 'none'; " .
                   "base-uri 'self'; " .
                   "form-action 'self'";
            header("Content-Security-Policy: $csp");
        } else {
            // More permissive for development
            $csp = "default-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
                   "img-src 'self' data: blob: http: https:; " .
                   "connect-src 'self' ws: wss:";
            header("Content-Security-Policy: $csp");
        }

        // X-Frame-Options
        header('X-Frame-Options: DENY');

        // X-Content-Type-Options
        header('X-Content-Type-Options: nosniff');

        // X-XSS-Protection
        header('X-XSS-Protection: 1; mode=block');

        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Permissions Policy
        $permissionsPolicy = 'camera=(), microphone=(), geolocation=(), ' .
                           'payment=(), usb=(), magnetometer=(), gyroscope=()';
        header("Permissions-Policy: $permissionsPolicy");

        // Strict Transport Security (HTTPS only)
        if ($this->environment === 'production' &&
            (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')) {
            $hsts = 'max-age=31536000; includeSubDomains; preload';
            header('Strict-Transport-Security: ' . $hsts);
        }

        // Remove server information
        header('Server: S3-Compatible Storage');

        // Content-Type Options (prevent MIME sniffing)
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
        }
    }

    /**
     * Validate and sanitize request
     */
    private function validateRequest()
    {
        // Validate request method
        $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'HEAD'];
        if (!in_array($_SERVER['REQUEST_METHOD'], $allowedMethods)) {
            $this->sendErrorResponse('Method not allowed', 405);
            return false;
        }

        // Validate request URI length
        $uriLength = strlen($_SERVER['REQUEST_URI'] ?? '');
        if ($uriLength > 2048) {
            $this->sendErrorResponse('Request URI too long', 414);
            return false;
        }

        // Validate headers length
        $headersSize = $this->calculateHeadersSize();
        if ($headersSize > 8192) { // 8KB headers limit
            $this->sendErrorResponse('Headers too large', 431);
            return false;
        }

        // Sanitize input data
        $this->sanitizeInput();

        return true;
    }

    /**
     * Check for common attack patterns
     */
    private function checkAttackPatterns()
    {
        $requestData = '';

        // Collect request data for analysis
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $requestData = http_build_query($_GET);
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $requestData = http_build_query($_POST) . file_get_contents('php://input');
        }

        $requestData .= ' ' . ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $requestData .= ' ' . ($_SERVER['HTTP_REFERER'] ?? '');
        $requestData .= ' ' . $_SERVER['REQUEST_URI'];

        // Common attack patterns
        $attackPatterns = [
            // SQL Injection patterns
            '/(\b(union|select|insert|update|delete|drop|create|alter|exec|execute)\b)/i',
            '/(\b(and|or)\s+\d+\s*=\s*\d+)/i',
            '/(\b(and|or)\s+\'\w+\'\s*=\s*\'\w+\')/i',

            // XSS patterns
            '/<script[^>]*>.*?<\/script>/is',
            '/javascript:/i',
            '/on\w+\s*=/i',

            // Path traversal
            '/\.\.[\/\\]/',

            // Command injection
            '/[;&|`$(){}[\]]/',
            '/\b(system|exec|passthru|shell_exec|eval|assert)\b/i',

            // File inclusion
            '/\b(include|require|include_once|require_once)\b/i',
        ];

        foreach ($attackPatterns as $pattern) {
            if (preg_match($pattern, $requestData)) {
                $this->logSecurityViolation('Potential attack detected', $pattern, $requestData);
                $this->sendErrorResponse('Invalid request', 400);
                return false;
            }
        }

        // Check for suspicious user agents
        $this->checkSuspiciousUserAgent();

        return true;
    }

    /**
     * Check for suspicious user agents
     */
    private function checkSuspiciousUserAgent()
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Known malicious bot patterns
        $maliciousPatterns = [
            '/bot/i',
            '/crawler/i',
            '/scanner/i',
            '/scraper/i',
            '/spider/i',
            '/curl/i',
            '/wget/i',
            '/python/i',
            '/perl/i',
            '/java/i',
        ];

        // Only check in production for non-whitelisted bots
        if ($this->environment === 'production') {
            foreach ($maliciousPatterns as $pattern) {
                if (preg_match($pattern, $userAgent)) {
                    // Check if it's a legitimate search engine
                    $legitimateBots = ['googlebot', 'bingbot', 'slurp', 'duckduckbot'];
                    $isLegitimate = false;

                    foreach ($legitimateBots as $legitimateBot) {
                        if (stripos($userAgent, $legitimateBot) !== false) {
                            $isLegitimate = true;
                            break;
                        }
                    }

                    if (!$isLegitimate) {
                        $this->logSecurityViolation('Suspicious user agent', $userAgent);
                        // Don't block immediately, but log for monitoring
                    }
                }
            }
        }
    }

    /**
     * Sanitize input data
     */
    private function sanitizeInput()
    {
        // Sanitize GET parameters
        if (!empty($_GET)) {
            $_GET = $this->sanitizeArray($_GET);
        }

        // Sanitize POST data
        if (!empty($_POST)) {
            $_POST = $this->sanitizeArray($_POST);
        }

        // Sanitize cookies
        if (!empty($_COOKIE)) {
            $_COOKIE = $this->sanitizeArray($_COOKIE);
        }
    }

    /**
     * Recursively sanitize array values
     */
    private function sanitizeArray($array)
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->sanitizeArray($value);
            } else {
                $array[$key] = $this->sanitizeString($value);
            }
        }
        return $array;
    }

    /**
     * Sanitize string value
     */
    private function sanitizeString($string)
    {
        if (is_string($string)) {
            // Remove control characters
            $string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $string);

            // Normalize whitespace
            $string = preg_replace('/\s+/', ' ', $string);

            // Trim whitespace
            $string = trim($string);

            // Limit string length
            if (strlen($string) > 1000) {
                $string = substr($string, 0, 1000);
            }
        }

        return $string;
    }

    /**
     * Calculate total size of request headers
     */
    private function calculateHeadersSize()
    {
        $size = 0;
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headerName = str_replace('_', '-', substr($key, 5));
                $size += strlen($headerName) + strlen($value) + 4; // ": " + "\r\n"
            }
        }
        return $size;
    }

    /**
     * Log security violations
     */
    private function logSecurityViolation($type, $pattern, $data = '')
    {
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';

        $message = "Security Violation - Type: $type, IP: $clientIp, URI: $uri, Pattern: $pattern";
        if ($data) {
            $message .= ", Data: " . substr($data, 0, 200); // Limit data length
        }
        $message .= ", User-Agent: $userAgent";

        error_log($message);
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
     * Static method to initialize security middleware
     */
    public static function init()
    {
        $middleware = new self();
        return $middleware->handle();
    }

    /**
     * Validate input against SQL injection patterns
     */
    public static function validateSqlInput($input)
    {
        $sqlPatterns = [
            '/(\bUNION\b.*\bSELECT\b)/i',
            '/(\bSELECT\b.*\bFROM\b)/i',
            '/(\bINSERT\b.*\bINTO\b)/i',
            '/(\bUPDATE\b.*\bSET\b)/i',
            '/(\bDELETE\b.*\bFROM\b)/i',
            '/(\bDROP\b.*\bTABLE\b)/i',
            '/(\bCREATE\b.*\bTABLE\b)/i',
            '/(\bALTER\b.*\bTABLE\b)/i',
            '/(\bEXEC\b|\bEXECUTE\b)/i',
        ];

        foreach ($sqlPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate input against XSS patterns
     */
    public static function validateXssInput($input)
    {
        $xssPatterns = [
            '/<script[^>]*>.*?<\/script>/is',
            '/<iframe[^>]*>.*?<\/iframe>/is',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<.*?on\w+.*?=.*?>/i',
        ];

        foreach ($xssPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return false;
            }
        }

        return true;
    }
}