<?php

namespace Core;

class Router {
    protected array $routes = [];

    public function add(string $method, string $route, string $controllerAction) {
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $route);
        $pattern = '#^'.$pattern.'$#';

        $this->routes[strtoupper($method)][$pattern] = $controllerAction;
    }

    public function dispatch(string $url, string $method): void {
        $method = strtoupper($method);
        $url = parse_url($url, PHP_URL_PATH);

        if (strpos($url, 'index.php') !== false) {
            $url = explode('index.php', $url)[1] ?? '/';
        }

        $url = '/'.trim($url, '/');

        if(!isset($this->routes[$method])) {
            $this->abort(405, "Method not Allowed!!");
        }

        foreach($this->routes[$method] as $pattern => $action) {
            if (preg_match($pattern, $url, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                [$controllerClass, $methodName] = explode('@', $action);
                $fullControllerNamespace = "App\\Controllers\\".$controllerClass;

                if (class_exists($fullControllerNamespace)) {
                    $controller = new $fullControllerNamespace();
                    if (method_exists($controller, $methodName)) {
                        call_user_func_array([$controller, $methodName], $params);
                        return;
                    }
                }
                $this->abort(500, 'Controller or Method not found!');
            }
        }
        $this->abort(404, "Page not found!");
    }

    public function abort(int $code, string $message) {
        http_response_code($code);
        echo "<h1>{$code}</h1><p>{$message}</p>";
        exit;
    }
}