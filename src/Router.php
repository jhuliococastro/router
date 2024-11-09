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

    /**
     * @param $uri
     * @param $action
     * @return Router
     */
    public static function get($uri, $action):Router
    {
        return self::addRoute('GET', $uri, $action);
    }

    /**
     * @param $uri
     * @param $action
     * @return Router
     */
    public static function post($uri, $action):Router
    {
        return self::addRoute('POST', $uri, $action);
    }

    /**
     * @param $uri
     * @param $action
     * @return Router
     */
    public static function put($uri, $action):Router
    {
        return self::addRoute('PUT', $uri, $action);
    }

    /**
     * @param $uri
     * @param $action
     * @return Router
     */
    public static function patch($uri, $action):Router
    {
        return self::addRoute('PATCH', $uri, $action);
    }

    /**
     * @param $uri
     * @param $action
     * @return Router
     */
    public static function delete($uri, $action):Router
    {
        return self::addRoute('DELETE', $uri, $action);
    }

    /**
     * @param array $options
     * @param callable $callback
     * @return void
     */
    public static function group(array $options, callable $callback): void
    {
        $instance = self::getInstance();
        $originalPrefix = $instance->groupPrefix;

        if (isset($options['prefix'])) {
            $instance->groupPrefix .= '/' . trim($options['prefix'], '/');
        }

        call_user_func($callback);

        $instance->groupPrefix = $originalPrefix;
    }

    /**
     * @param $method
     * @param $uri
     * @param $action
     * @return Router
     */
    private static function addRoute($method, $uri, $action):Router
    {
        $instance = self::getInstance();
        $fullUri = $instance->getPrefixedUri($uri);
        $instance->routes[$method][$fullUri] = $action;
        return $instance;
    }

    /**
     * @param $name
     * @return $this
     */
    public function name($name): Router
    {
        $lastRoute = end($this->routes);
        if ($lastRoute) {
            $uri = key($lastRoute);
            $this->routeNames[$name] = $uri;
        }
        return $this;
    }

    /**
     * @param $uri
     * @return string
     */
    private function getPrefixedUri($uri): string
    {
        return trim($this->groupPrefix . '/' . trim($uri, '/'), '/');
    }

    /**
     * @return void
     */
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

    /**
     * @param string $name
     * @param array $params
     * @return string|null
     */
    public static function url(string $name, array $params = []): ?string
    {
        $instance = self::getInstance();
        if (!isset($instance->routeNames[$name])) {
            return null;
        }

        $uri = $instance->routeNames[$name];
        foreach ($params as $key => $value) {
            $uri = str_replace('{' . $key . '}', $value, $uri);
        }

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];

        return $protocol . $host . '/' . $uri;
    }
}
