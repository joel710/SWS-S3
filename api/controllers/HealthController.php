<?php
// api/controllers/HealthController.php

/**
 * Health Check Controller
 * Provides system health status and monitoring endpoints
 */
class HealthController
{
    /**
     * Basic health check endpoint
     * GET /api/health
     */
    public function check()
    {
        try {
            $health = [
                'status' => 'healthy',
                'timestamp' => date('c'),
                'version' => '1.0.0',
                'environment' => getenv('APP_ENV') ?: 'unknown',
                'services' => $this->checkServices(),
                'metrics' => $this->getSystemMetrics(),
                'uptime' => $this->getUptime()
            ];

            // Check if any service is unhealthy
            foreach ($health['services'] as $service => $status) {
                if (!$status['healthy']) {
                    $health['status'] = 'degraded';
                    break;
                }
            }

            $statusCode = $health['status'] === 'healthy' ? 200 : 503;

            http_response_code($statusCode);
            echo json_encode(['success' => true, 'data' => $health]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Health check failed',
                'timestamp' => date('c')
            ]);
        }
    }

    /**
     * Check status of essential services
     */
    private function checkServices()
    {
        $services = [];

        // Database connection check
        try {
            global $pdo;
            $stmt = $pdo->query("SELECT 1");
            $services['database'] = [
                'healthy' => true,
                'response_time' => $this->measureDatabaseResponseTime()
            ];
        } catch (Exception $e) {
            $services['database'] = [
                'healthy' => false,
                'error' => $e->getMessage()
            ];
        }

        // Storage directory check
        $storagePath = __DIR__ . '/../../storage';
        $services['storage'] = [
            'healthy' => is_dir($storagePath) && is_writable($storagePath),
            'path' => $storagePath,
            'free_space' => disk_free_space($storagePath)
        ];

        // Image processing check
        $services['image_processing'] = [
            'healthy' => function_exists('gd_info') && extension_loaded('gd'),
            'gd_version' => function_exists('gd_info') ? gd_info()['GD Version'] : 'Not available'
        ];

        // Memory usage check
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        $services['memory'] = [
            'healthy' => $memoryUsage < ($memoryLimit * 0.9), // Alert if using > 90%
            'usage' => $memoryUsage,
            'limit' => $memoryLimit,
            'usage_percent' => round(($memoryUsage / $memoryLimit) * 100, 2)
        ];

        return $services;
    }

    /**
     * Get system metrics
     */
    private function getSystemMetrics()
    {
        return [
            'php_version' => PHP_VERSION,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'cpu_load' => $this->getCpuLoad(),
            'disk_usage' => $this->getDiskUsage(),
            'active_connections' => $this->getActiveConnections()
        ];
    }

    /**
     * Get system uptime
     */
    private function getUptime()
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return [
                'load_1min' => $load[0],
                'load_5min' => $load[1] ?? null,
                'load_15min' => $load[2] ?? null
            ];
        }

        return null;
    }

    /**
     * Measure database response time
     */
    private function measureDatabaseResponseTime()
    {
        try {
            global $pdo;
            $start = microtime(true);
            $stmt = $pdo->query("SELECT 1");
            $end = microtime(true);
            return round(($end - $start) * 1000, 2); // milliseconds
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Parse PHP memory limit string
     */
    private function parseMemoryLimit($limit)
    {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $value = (int)$limit;

        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    /**
     * Get CPU load
     */
    private function getCpuLoad()
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return [
                '1min' => $load[0],
                '5min' => $load[1] ?? null,
                '15min' => $load[2] ?? null
            ];
        }

        return null;
    }

    /**
     * Get disk usage information
     */
    private function getDiskUsage()
    {
        $storagePath = __DIR__ . '/../../storage';
        if (!is_dir($storagePath)) {
            return null;
        }

        $total = disk_total_space($storagePath);
        $free = disk_free_space($storagePath);
        $used = $total - $free;

        return [
            'total' => $total,
            'used' => $used,
            'free' => $free,
            'usage_percent' => round(($used / $total) * 100, 2)
        ];
    }

    /**
     * Get active database connections (simplified)
     */
    private function getActiveConnections()
    {
        try {
            global $pdo;
            $stmt = $pdo->query("SHOW STATUS LIKE 'Threads_connected'");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)$result['Value'];
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Detailed health check with service-specific information
     * GET /api/health/detailed
     */
    public function detailed()
    {
        try {
            $detailed = [
                'status' => 'healthy',
                'timestamp' => date('c'),
                'version' => '1.0.0',
                'environment' => getenv('APP_ENV') ?: 'unknown',
                'services' => $this->checkServices(),
                'metrics' => $this->getSystemMetrics(),
                'configuration' => $this->getConfiguration(),
                'recent_errors' => $this->getRecentErrors(),
                'performance' => $this->getPerformanceMetrics()
            ];

            echo json_encode(['success' => true, 'data' => $detailed]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Detailed health check failed'
            ]);
        }
    }

    /**
     * Get configuration information
     */
    private function getConfiguration()
    {
        return [
            'max_file_size' => MAX_FILE_SIZE,
            'allowed_mime_types' => count(ALLOWED_MIME_TYPES),
            'rate_limit' => getenv('RATE_LIMIT') ?: 1000,
            'ssl_enforced' => getenv('SSL_ENFORCE') !== 'false',
            'optimization_enabled' => getenv('ENABLE_OPTIMIZATION') !== 'false'
        ];
    }

    /**
     * Get recent errors from log
     */
    private function getRecentErrors()
    {
        // This is a placeholder - in production you'd parse actual log files
        return [
            'count_last_hour' => 0,
            'count_last_24h' => 0,
            'recent_errors' => []
        ];
    }

    /**
     * Get performance metrics
     */
    private function getPerformanceMetrics()
    {
        return [
            'average_response_time' => 0, // Would need to track this
            'requests_per_minute' => 0,   // Would need to track this
            'error_rate' => 0,           // Would need to track this
            'optimization_queue_size' => $this->getOptimizationQueueSize()
        ];
    }

    /**
     * Get optimization queue size
     */
    private function getOptimizationQueueSize()
    {
        try {
            global $pdo;
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM optimization_jobs WHERE status = 'pending'");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)$result['count'];
        } catch (Exception $e) {
            return 0;
        }
    }
}