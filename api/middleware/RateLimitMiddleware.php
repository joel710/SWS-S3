<?php
// api/middleware/RateLimitMiddleware.php

require_once __DIR__ . '/../config/database.php';

/**
 * Rate Limiting Middleware Class
 * Provides API rate limiting and abuse prevention
 */
class RateLimitMiddleware
{
    private $pdo;
    private $rateLimit;
    private $windowSize;
    private $storage = [];

    public function __construct()
    {
        global $pdo;
        $this->pdo = $pdo;
        $this->rateLimit = (int)(getenv('RATE_LIMIT') ?: 1000); // requests per hour
        $this->windowSize = 3600; // 1 hour window
    }

    /**
     * Handle rate limiting for incoming requests
     */
    public function handle()
    {
        $clientIp = $this->getClientIp();
        $apiKey = $this->getApiKey();

        // Check IP-based blocking first
        if ($this->isIpBlocked($clientIp)) {
            $this->sendErrorResponse('IP address blocked due to abuse', 429);
            return false;
        }

        // Check rate limits
        if (!$this->checkRateLimit($clientIp, $apiKey)) {
            $this->sendErrorResponse('Rate limit exceeded', 429);
            return false;
        }

        // Validate request size
        if (!$this->validateRequestSize()) {
            $this->sendErrorResponse('Request size too large', 413);
            return false;
        }

        // Check concurrent requests
        if (!$this->checkConcurrentRequests($clientIp)) {
            $this->sendErrorResponse('Too many concurrent requests', 429);
            return false;
        }

        return true; // Continue processing
    }

    /**
     * Check rate limits for client
     */
    private function checkRateLimit($clientIp, $apiKey = null)
    {
        $current_time = time();
        $window_start = $current_time - $this->windowSize;

        // Clean old entries
        $this->cleanupOldEntries($window_start);

        // Different rate limits for different contexts
        if ($apiKey) {
            // Higher limit for authenticated API users
            $limit = $this->rateLimit;
            $key = "api_key:$apiKey";
        } else {
            // Lower limit for unauthenticated requests
            $limit = 100; // 100 requests per hour for unauthenticated
            $key = "ip:$clientIp";
        }

        // Get current request count
        $current_count = $this->getRequestCount($key, $window_start);

        // Check if limit exceeded
        if ($current_count >= $limit) {
            $this->logRateLimitExceeded($clientIp, $apiKey, $current_count, $limit);
            return false;
        }

        // Record this request
        $this->recordRequest($key, $current_time);

        // Set rate limit headers
        $this->setRateLimitHeaders($current_count, $limit);

        return true;
    }

    /**
     * Check if IP is blocked
     */
    private function isIpBlocked($clientIp)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, block_reason, blocked_until
                FROM blocked_ips
                WHERE ip_address = ? AND (blocked_until IS NULL OR blocked_until > NOW())
                LIMIT 1
            ");
            $stmt->execute([$clientIp]);
            $blocked = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($blocked) {
                // Log blocked attempt
                $this->logBlockedAttempt($clientIp, $blocked['block_reason']);
                return true;
            }
        } catch (Exception $e) {
            error_log("Rate limit check error: " . $e->getMessage());
        }

        return false;
    }

    /**
     * Get current request count for identifier
     */
    private function getRequestCount($key, $windowStart)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM rate_limit_requests
                WHERE identifier = ? AND request_time > ?
            ");
            $stmt->execute([$key, date('Y-m-d H:i:s', $windowStart)]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return (int)$result['count'];
        } catch (Exception $e) {
            error_log("Request count error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Record a request in the database
     */
    private function recordRequest($key, $timestamp)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO rate_limit_requests (identifier, request_time)
                VALUES (?, ?)
            ");
            $stmt->execute([$key, date('Y-m-d H:i:s', $timestamp)]);
        } catch (Exception $e) {
            error_log("Request recording error: " . $e->getMessage());
        }
    }

    /**
     * Clean up old rate limit entries
     */
    private function cleanupOldEntries($windowStart)
    {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM rate_limit_requests
                WHERE request_time < ?
            ");
            $stmt->execute([date('Y-m-d H:i:s', $windowStart)]);
        } catch (Exception $e) {
            error_log("Cleanup error: " . $e->getMessage());
        }
    }

    /**
     * Check concurrent requests limit
     */
    private function checkConcurrentRequests($clientIp)
    {
        $maxConcurrent = 10; // Maximum concurrent requests per IP
        $timeout = 30; // Consider requests older than 30 seconds as stuck

        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM active_requests
                WHERE ip_address = ? AND start_time > DATE_SUB(NOW(), INTERVAL ? SECOND)
            ");
            $stmt->execute([$clientIp, $timeout]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $currentConcurrent = (int)$result['count'];

            if ($currentConcurrent >= $maxConcurrent) {
                return false;
            }

            // Record this active request
            $requestId = uniqid();
            $stmt = $this->pdo->prepare("
                INSERT INTO active_requests (request_id, ip_address, start_time)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$requestId, $clientIp]);

            // Register cleanup function
            register_shutdown_function(function() use ($requestId) {
                $this->cleanupActiveRequest($requestId);
            });

            return true;
        } catch (Exception $e) {
            error_log("Concurrent request check error: " . $e->getMessage());
            return true; // Allow on error
        }
    }

    /**
     * Clean up active request on completion
     */
    private function cleanupActiveRequest($requestId)
    {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM active_requests
                WHERE request_id = ?
            ");
            $stmt->execute([$requestId]);
        } catch (Exception $e) {
            error_log("Active request cleanup error: " . $e->getMessage());
        }
    }

    /**
     * Validate request size
     */
    private function validateRequestSize()
    {
        $maxRequestSize = 50 * 1024 * 1024; // 50MB

        // Check POST content length
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $contentLength = $_SERVER['CONTENT_LENGTH'] ?? 0;
            if ($contentLength > $maxRequestSize) {
                return false;
            }
        }

        // Check file uploads
        if (!empty($_FILES)) {
            $totalUploadSize = 0;
            foreach ($_FILES as $file) {
                if (is_array($file['size'])) {
                    $totalUploadSize += array_sum($file['size']);
                } else {
                    $totalUploadSize += $file['size'];
                }
            }

            if ($totalUploadSize > $maxRequestSize) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get client IP address
     */
    private function getClientIp()
    {
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);

                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Get API key from request
     */
    private function getApiKey()
    {
        // Check Authorization header
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (strpos($authHeader, 'Bearer ') === 0) {
            return substr($authHeader, 7);
        }

        // Check X-API-Key header
        return $_SERVER['HTTP_X_API_KEY'] ?? null;
    }

    /**
     * Set rate limit headers
     */
    private function setRateLimitHeaders($current, $limit)
    {
        $remaining = max(0, $limit - $current);
        $reset = time() + $this->windowSize;

        header("X-RateLimit-Limit: $limit");
        header("X-RateLimit-Remaining: $remaining");
        header("X-RateLimit-Reset: $reset");
    }

    /**
     * Log rate limit exceeded
     */
    private function logRateLimitExceeded($clientIp, $apiKey, $current, $limit)
    {
        $message = "Rate limit exceeded - IP: $clientIp, API: " . ($apiKey ? 'YES' : 'NO') .
                   ", Current: $current, Limit: $limit";
        error_log($message);

        // Consider blocking if this is repeated abuse
        $this->considerBlocking($clientIp);
    }

    /**
     * Log blocked attempt
     */
    private function logBlockedAttempt($clientIp, $reason)
    {
        $message = "Blocked request attempt - IP: $clientIp, Reason: $reason";
        error_log($message);
    }

    /**
     * Consider blocking an IP for repeated abuse
     */
    private function considerBlocking($clientIp)
    {
        // Check if this IP has exceeded rate limits multiple times recently
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as violations
                FROM rate_limit_violations
                WHERE ip_address = ? AND violation_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute([$clientIp]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $violations = (int)$result['violations'];

            // Record this violation
            $stmt = $this->pdo->prepare("
                INSERT INTO rate_limit_violations (ip_address, violation_time)
                VALUES (?, NOW())
            ");
            $stmt->execute([$clientIp]);

            // Block if too many violations
            if ($violations >= 5) { // 5 violations in 1 hour
                $stmt = $this->pdo->prepare("
                    INSERT INTO blocked_ips (ip_address, block_reason, blocked_until)
                    VALUES (?, 'Automated block due to rate limit violations', DATE_ADD(NOW(), INTERVAL 1 DAY))
                ");
                $stmt->execute([$clientIp]);

                error_log("IP $clientIp automatically blocked for 1 day due to repeated rate limit violations");
            }
        } catch (Exception $e) {
            error_log("Consider blocking error: " . $e->getMessage());
        }
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
            'status_code' => $statusCode,
            'retry_after' => $this->windowSize
        ]);
    }

    /**
     * Static method to initialize rate limiting
     */
    public static function init()
    {
        $middleware = new self();
        return $middleware->handle();
    }
}