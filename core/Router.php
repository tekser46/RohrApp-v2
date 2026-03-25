<?php
/**
 * Router — Simple API router
 * Routes: /api/{module}/{action} or /api/{module}/{action}/{id}
 * Webhook: /webhook/{type}
 */
class Router
{
    private array $routes = [];

    /**
     * Register a route
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $pattern Route pattern (e.g. "auth/login", "user/profile", "admin/users/:id")
     * @param array $handler [ControllerClass, methodName]
     * @param array $middleware Middleware class names to run before handler
     */
    public function add(string $method, string $pattern, array $handler, array $middleware = []): self
    {
        $this->routes[] = [
            'method'     => strtoupper($method),
            'pattern'    => $pattern,
            'handler'    => $handler,
            'middleware'  => $middleware,
        ];
        return $this;
    }

    // Convenience methods
    public function get(string $pattern, array $handler, array $middleware = []): self
    {
        return $this->add('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, array $handler, array $middleware = []): self
    {
        return $this->add('POST', $pattern, $handler, $middleware);
    }

    public function put(string $pattern, array $handler, array $middleware = []): self
    {
        return $this->add('PUT', $pattern, $handler, $middleware);
    }

    public function delete(string $pattern, array $handler, array $middleware = []): self
    {
        return $this->add('DELETE', $pattern, $handler, $middleware);
    }

    /**
     * Dispatch the current request
     */
    public function dispatch(string $requestMethod, string $requestPath): void
    {
        $requestMethod = strtoupper($requestMethod);

        foreach ($this->routes as $route) {
            if ($route['method'] !== $requestMethod) continue;

            $params = $this->matchPattern($route['pattern'], $requestPath);
            if ($params === false) continue;

            // Run middleware chain
            foreach ($route['middleware'] as $mw) {
                if (is_object($mw)) {
                    $mw->handle();
                } elseif (is_string($mw) && class_exists($mw)) {
                    (new $mw())->handle();
                }
            }

            // Instantiate controller and call method
            [$controllerClass, $method] = $route['handler'];
            if (!class_exists($controllerClass)) {
                Response::error("Controller {$controllerClass} nicht gefunden", 500, 'SERVER_ERROR');
            }

            $controller = new $controllerClass();
            if (!method_exists($controller, $method)) {
                Response::error("Methode {$method} nicht gefunden", 500, 'SERVER_ERROR');
            }

            // Call with extracted params (e.g. :id)
            $controller->$method($params);
            return;
        }

        Response::notFound('Route nicht gefunden: ' . $requestPath);
    }

    /**
     * Match a route pattern against a path, extract params
     * Pattern: "user/profile" matches "user/profile"
     * Pattern: "admin/users/:id" matches "admin/users/42" → ['id' => '42']
     * Returns false if no match, array of params if match
     */
    private function matchPattern(string $pattern, string $path): array|false
    {
        $patternParts = explode('/', trim($pattern, '/'));
        $pathParts    = explode('/', trim($path, '/'));

        if (count($patternParts) !== count($pathParts)) return false;

        $params = [];
        for ($i = 0; $i < count($patternParts); $i++) {
            if (str_starts_with($patternParts[$i], ':')) {
                // Dynamic segment
                $paramName = substr($patternParts[$i], 1);
                $params[$paramName] = $pathParts[$i];
            } elseif ($patternParts[$i] !== $pathParts[$i]) {
                return false;
            }
        }

        return $params;
    }
}
