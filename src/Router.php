<?php
namespace Router;

class Router
{
    private static ?Router $instance = null;
    private array $routes = [];
    private array $routeNames = [];
    private string $groupPrefix = '';

    private function __construct() {}

    public static function getInstance(): Router
    {
        if (self::$instance === null) {
            self::$instance = new self();
            register_shutdown_function([self::$instance, 'dispatch']);
        }
        return self::$instance;
    }

    public static function get($uri, $action): Router
    {
        $instance = self::getInstance();
        $instance->addRoute('GET', $instance->getPrefixedUri($uri), $action);
        return $instance;
    }

    public static function post($uri, $action): Router
    {
        $instance = self::getInstance();
        $instance->addRoute('POST', $instance->getPrefixedUri($uri), $action);
        return $instance;
    }

    public static function put($uri, $action): Router
    {
        $instance = self::getInstance();
        $instance->addRoute('PUT', $instance->getPrefixedUri($uri), $action);
        return $instance;
    }

    public static function patch($uri, $action): Router
    {
        $instance = self::getInstance();
        $instance->addRoute('PATCH', $instance->getPrefixedUri($uri), $action);
        return $instance;
    }

    public static function delete($uri, $action): Router
    {
        $instance = self::getInstance();
        $instance->addRoute('DELETE', $instance->getPrefixedUri($uri), $action);
        return $instance;
    }

    public static function group(array $options, callable $callback): void
    {
        $instance = self::getInstance();
        $originalPrefix = $instance->groupPrefix;

        if (isset($options['prefix'])) {
            $instance->groupPrefix .= '/' . trim($options['prefix'], '/');
        }

        call_user_func($callback);

        // Restaura o prefixo original após o grupo
        $instance->groupPrefix = $originalPrefix;
    }

    private static function addRoute($method, $uri, $action):Router
    {
        $instance = self::getInstance();
        $fullUri = $instance->getPrefixedUri($uri);
        $instance->routes[$method][$fullUri] = $action;
        return $instance;
    }

    public function name($name): Router
    {
        $lastRoute = end($this->routes);
        if ($lastRoute) {
            $uri = key($lastRoute);
            $this->routeNames[$name] = $uri;
        }
        return $this;
    }

    private function getPrefixedUri($uri): string
    {
        return trim($this->groupPrefix . '/' . trim($uri, '/'), '/');
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

        if (!isset($this->routes[$method])) {
            http_response_code(404);
            echo "404 Not Found - Method not found";
            return;
        }

        foreach ($this->routes[$method] as $route => $action) {
            $pattern = preg_replace('/\{[^\}]+\}/', '([^/]+)', $route);
            $pattern = "#^" . $pattern . "$#";

            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches);

                if (is_callable($action)) {
                    call_user_func_array($action, $matches);
                    return;
                }

                if (is_array($action) && count($action) === 2) {
                    $controller = new $action[0]();
                    $method = $action[1];

                    if (method_exists($controller, $method)) {
                        call_user_func_array([$controller, $method], $matches);
                        return;
                    }
                }
            }
        }

        http_response_code(404);
        echo "404 Not Found - Route not found";
    }
}
