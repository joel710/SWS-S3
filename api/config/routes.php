<?php
// api/config/routes.php

/**
 * Route Definitions for S3-Compatible Object Storage API
 * Organized route definitions with middleware support
 */

class Route
{
    private static $routes = [];
    private static $middleware = [];

    /**
     * Register a GET route
     */
    public static function get($path, $handler, $middleware = [])
    {
        self::addRoute('GET', $path, $handler, $middleware);
    }

    /**
     * Register a POST route
     */
    public static function post($path, $handler, $middleware = [])
    {
        self::addRoute('POST', $path, $handler, $middleware);
    }

    /**
     * Register a PUT route
     */
    public static function put($path, $handler, $middleware = [])
    {
        self::addRoute('PUT', $path, $handler, $middleware);
    }

    /**
     * Register a DELETE route
     */
    public static function delete($path, $handler, $middleware = [])
    {
        self::addRoute('DELETE', $path, $handler, $middleware);
    }

    /**
     * Register an OPTIONS route
     */
    public static function options($path, $handler, $middleware = [])
    {
        self::addRoute('OPTIONS', $path, $handler, $middleware);
    }

    /**
     * Add a route to the registry
     */
    private static function addRoute($method, $path, $handler, $middleware = [])
    {
        $path = trim($path, '/');

        if (!isset(self::$routes[$method])) {
            self::$routes[$method] = [];
        }

        self::$routes[$method][$path] = [
            'handler' => $handler,
            'middleware' => $middleware
        ];
    }

    /**
     * Register middleware for all routes
     */
    public static function middleware($middleware)
    {
        self::$middleware[] = $middleware;
    }

    /**
     * Get all registered routes
     */
    public static function getRoutes()
    {
        return self::$routes;
    }

    /**
     * Get global middleware
     */
    public static function getGlobalMiddleware()
    {
        return self::$middleware;
    }

    /**
     * Initialize all API routes
     */
    public static function initialize()
    {
        // Global middleware for all routes
        self::middleware(['CorsMiddleware', 'SecurityMiddleware', 'RateLimitMiddleware']);

        // Health check endpoint
        self::get('/api/health', 'HealthController@check');

        // File operations
        self::post('/api/upload', 'UploadController@upload');
        self::get('/api/object', 'ObjectController@get');
        self::delete('/api/object', 'ObjectController@delete');
        self::get('/api/list', 'ObjectController@list');

        // URL signing
        self::post('/api/generate-signed-url', 'UrlSigningController@generate');
        self::get('/api/get-file', 'FileController@serve');

        // Image optimization endpoints
        self::post('/api/optimize', 'OptimizationController@optimize');
        self::post('/api/optimize/batch', 'OptimizationController@batchOptimize');
        self::get('/api/thumbnails/{id}/{size}.{format}', 'OptimizationController@serveThumbnail');

        // CORS preflight for all endpoints
        self::options('/api/{path}', 'CorsController@preflight');

        // Version 2 API (future use)
        self::get('/api/v2/buckets', 'BucketController@index');
        self::post('/api/v2/buckets', 'BucketController@create');
        self::get('/api/v2/buckets/{id}', 'BucketController@show');
        self::put('/api/v2/buckets/{id}', 'BucketController@update');
        self::delete('/api/v2/buckets/{id}', 'BucketController@delete');

        // Admin API endpoints
        self::get('/api/admin/stats', 'AdminController@stats');
        self::get('/api/admin/projects', 'AdminController@projects');
        self::get('/api/admin/jobs', 'AdminController@optimizationJobs');
    }

    /**
     * Parse route parameters from path
     */
    public static function parseParameters($routePath, $requestPath)
    {
        $routeSegments = explode('/', trim($routePath, '/'));
        $requestSegments = explode('/', trim($requestPath, '/'));

        $parameters = [];

        for ($i = 0; $i < count($routeSegments); $i++) {
            $segment = $routeSegments[$i];

            if (strpos($segment, '{') === 0 && strpos($segment, '}') === strlen($segment) - 1) {
                $paramName = trim($segment, '{}');
                if (isset($requestSegments[$i])) {
                    $parameters[$paramName] = $requestSegments[$i];
                }
            }
        }

        return $parameters;
    }

    /**
     * Match request path to registered route
     */
    public static function match($method, $requestPath)
    {
        $method = strtoupper($method);
        $requestPath = trim($requestPath, '/');

        if (!isset(self::$routes[$method])) {
            return null;
        }

        // Direct match
        if (isset(self::$routes[$method][$requestPath])) {
            return [
                'route' => self::$routes[$method][$requestPath],
                'parameters' => []
            ];
        }

        // Parameterized route matching
        foreach (self::$routes[$method] as $routePath => $route) {
            if (self::matchesPattern($routePath, $requestPath)) {
                return [
                    'route' => $route,
                    'parameters' => self::parseParameters($routePath, $requestPath),
                    'path' => $routePath
                ];
            }
        }

        return null;
    }

    /**
     * Check if request path matches route pattern
     */
    private static function matchesPattern($routePath, $requestPath)
    {
        $routeSegments = explode('/', $routePath);
        $requestSegments = explode('/', $requestPath);

        if (count($routeSegments) !== count($requestSegments)) {
            return false;
        }

        for ($i = 0; $i < count($routeSegments); $i++) {
            $routeSegment = $routeSegments[$i];
            $requestSegment = $requestSegments[$i];

            // If it's a parameter, it matches anything
            if (strpos($routeSegment, '{') === 0 && strpos($routeSegment, '}') === strlen($routeSegment) - 1) {
                continue;
            }

            // If it's a wildcard pattern
            if ($routeSegment === '{path}') {
                return true;
            }

            // Exact match required
            if ($routeSegment !== $requestSegment) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get route handler information
     */
    public static function getHandlerInfo($handler)
    {
        if (is_string($handler) && strpos($handler, '@') !== false) {
            list($controller, $method) = explode('@', $handler);
            return [
                'controller' => $controller,
                'method' => $method,
                'file' => __DIR__ . "/../controllers/{$controller}.php"
            ];
        }

        return ['handler' => $handler];
    }
}

/**
 * Route Dispatcher Class
 */
class RouteDispatcher
{
    private $routes;

    public function __construct()
    {
        $this->routes = Route::getRoutes();
    }

    /**
     * Dispatch the current request
     */
    public function dispatch($method, $uri)
    {
        $path = parse_url($uri, PHP_URL_PATH);
        $path = str_replace('/api', '', $path);

        // Apply global middleware first
        $this->applyGlobalMiddleware();

        // Find matching route
        $match = Route::match($method, $path);

        if (!$match) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint not found']);
            return;
        }

        $route = $match['route'];
        $parameters = $match['parameters'];

        // Apply route-specific middleware
        $this->applyRouteMiddleware($route['middleware']);

        // Execute the handler
        $this->executeHandler($route['handler'], $parameters);
    }

    /**
     * Apply global middleware
     */
    private function applyGlobalMiddleware()
    {
        $globalMiddleware = Route::getGlobalMiddleware();

        foreach ($globalMiddleware as $middleware) {
            if (!$this->executeMiddleware($middleware)) {
                exit; // Middleware blocked the request
            }
        }
    }

    /**
     * Apply route-specific middleware
     */
    private function applyRouteMiddleware($middleware)
    {
        foreach ($middleware as $middlewareClass) {
            if (!$this->executeMiddleware($middlewareClass)) {
                exit; // Middleware blocked the request
            }
        }
    }

    /**
     * Execute middleware
     */
    private function executeMiddleware($middleware)
    {
        try {
            $middlewareFile = __DIR__ . "/../middleware/{$middleware}.php";
            if (file_exists($middlewareFile)) {
                require_once $middlewareFile;

                if (class_exists($middleware)) {
                    $instance = new $middleware();
                    return $instance->handle();
                }
            }
        } catch (Exception $e) {
            error_log("Middleware error: " . $e->getMessage());
            return false;
        }

        return true; // Allow request if middleware doesn't exist
    }

    /**
     * Execute route handler
     */
    private function executeHandler($handler, $parameters = [])
    {
        try {
            $handlerInfo = Route::getHandlerInfo($handler);

            if (isset($handlerInfo['controller'])) {
                // Include controller file
                if (file_exists($handlerInfo['file'])) {
                    require_once $handlerInfo['file'];

                    if (class_exists($handlerInfo['controller'])) {
                        $controller = new $handlerInfo['controller']();

                        // Set parameters as global for backward compatibility
                        foreach ($parameters as $key => $value) {
                            $_GET[$key] = $value;
                        }

                        // Call the method
                        if (method_exists($controller, $handlerInfo['method'])) {
                            $controller->{$handlerInfo['method']}();
                        } else {
                            throw new Exception("Method {$handlerInfo['method']} not found in {$handlerInfo['controller']}");
                        }
                    } else {
                        throw new Exception("Controller class {$handlerInfo['controller']} not found");
                    }
                } else {
                    throw new Exception("Controller file not found: {$handlerInfo['file']}");
                }
            } else {
                // Direct function call
                if (is_callable($handler)) {
                    call_user_func($handler, $parameters);
                } else {
                    throw new Exception("Invalid handler");
                }
            }
        } catch (Exception $e) {
            error_log("Handler execution error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Internal server error']);
        }
    }
}