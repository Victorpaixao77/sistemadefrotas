<?php

namespace GestaoInterativa\Routing;

use GestaoInterativa\Http\Request;
use GestaoInterativa\Http\Response;

class Router
{
    private $routes = [];
    private $middleware = [];
    private $prefix = '';
    private $namespace = '';

    public function addRoute($method, $path, $handler, $middleware = [])
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $this->prefix . $path,
            'handler' => $handler,
            'middleware' => $middleware,
            'name' => null,
            'namespace' => $this->namespace
        ];
    }

    public function get($path, $handler, $middleware = [])
    {
        $this->addRoute('GET', $path, $handler, $middleware);
        return $this;
    }

    public function post($path, $handler, $middleware = [])
    {
        $this->addRoute('POST', $path, $handler, $middleware);
        return $this;
    }

    public function put($path, $handler, $middleware = [])
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
        return $this;
    }

    public function delete($path, $handler, $middleware = [])
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
        return $this;
    }

    public function patch($path, $handler, $middleware = [])
    {
        $this->addRoute('PATCH', $path, $handler, $middleware);
        return $this;
    }

    public function options($path, $handler, $middleware = [])
    {
        $this->addRoute('OPTIONS', $path, $handler, $middleware);
        return $this;
    }

    public function any($path, $handler, $middleware = [])
    {
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];
        foreach ($methods as $method) {
            $this->addRoute($method, $path, $handler, $middleware);
        }
        return $this;
    }

    public function match(array $methods, $path, $handler, $middleware = [])
    {
        foreach ($methods as $method) {
            $this->addRoute($method, $path, $handler, $middleware);
        }
        return $this;
    }

    public function group($prefix, callable $callback, $middleware = [], $groupPrefix = '', $groupNamespace = '')
    {
        $originalPrefix = $this->prefix;
        $originalNamespace = $this->namespace;
        
        $this->prefix .= $prefix;
        if ($groupPrefix) {
            $this->prefix .= '/' . $groupPrefix;
        }
        if ($groupNamespace) {
            $this->namespace = $groupNamespace;
        }
        
        $callback($this);
        
        $this->prefix = $originalPrefix;
        $this->namespace = $originalNamespace;
        
        return $this;
    }

    public function middleware($middleware)
    {
        if (is_array($middleware)) {
            $this->middleware = array_merge($this->middleware, $middleware);
        } else {
            $this->middleware[] = $middleware;
        }
        return $this;
    }

    public function name($name)
    {
        if (!empty($this->routes)) {
            $this->routes[count($this->routes) - 1]['name'] = $name;
        }
        return $this;
    }

    public function prefix($prefix)
    {
        $this->prefix = $prefix;
        return $this;
    }

    public function namespace($namespace)
    {
        $this->namespace = $namespace;
        return $this;
    }

    public function hasRoute($method, $path)
    {
        foreach ($this->routes as $route) {
            if ($route['method'] === strtoupper($method) && $this->matchPath($route['path'], $path)) {
                return true;
            }
        }
        return false;
    }

    public function getRoute($method, $path)
    {
        foreach ($this->routes as $route) {
            if ($route['method'] === strtoupper($method)) {
                $params = $this->matchPath($route['path'], $path);
                if ($params !== false) {
                    return array_merge($route, ['parameters' => $params]);
                }
            }
        }
        return null;
    }

    private function matchPath($routePath, $requestPath)
    {
        $routeSegments = explode('/', trim($routePath, '/'));
        $requestSegments = explode('/', trim($requestPath, '/'));
        
        if (count($routeSegments) !== count($requestSegments)) {
            return false;
        }
        
        $params = [];
        
        for ($i = 0; $i < count($routeSegments); $i++) {
            $routeSegment = $routeSegments[$i];
            $requestSegment = $requestSegments[$i];
            
            if (preg_match('/^\{([^}]+)\}$/', $routeSegment, $matches)) {
                $paramName = $matches[1];
                
                // Verificar se é um parâmetro opcional
                if (strpos($paramName, '?') !== false) {
                    $paramName = rtrim($paramName, '?');
                    if (empty($requestSegment)) {
                        continue;
                    }
                }
                
                $params[$paramName] = $requestSegment;
            } elseif ($routeSegment !== $requestSegment) {
                return false;
            }
        }
        
        return $params;
    }

    public function dispatch(Request $request)
    {
        $method = $request->getMethod();
        $path = $request->getPath();
        
        $route = $this->getRoute($method, $path);
        
        if (!$route) {
            // Verificar se existe rota para outros métodos
            $allowedMethods = [];
            foreach ($this->routes as $r) {
                if ($this->matchPath($r['path'], $path)) {
                    $allowedMethods[] = $r['method'];
                }
            }
            
            if (!empty($allowedMethods)) {
                $response = new Response();
                $response->setStatusCode(405);
                $response->setAllow($allowedMethods);
                return $response;
            }
            
            $response = new Response();
            return $response->notFound();
        }
        
        // Executar middleware
        foreach ($route['middleware'] as $middleware) {
            if (is_callable($middleware)) {
                $result = $middleware($request);
                if ($result instanceof Response) {
                    return $result;
                }
            } elseif (is_string($middleware) && class_exists($middleware)) {
                $instance = new $middleware();
                if (method_exists($instance, 'handle')) {
                    $result = $instance->handle($request);
                    if ($result instanceof Response) {
                        return $result;
                    }
                }
            }
        }
        
        // Executar handler
        $handler = $route['handler'];
        
        if (is_callable($handler)) {
            $params = $route['parameters'] ?? [];
            $result = call_user_func_array($handler, $params);
        } elseif (is_string($handler)) {
            $result = $this->executeController($handler, $route['parameters'] ?? []);
        } else {
            $result = new Response();
            return $result->internalServerError('Invalid handler');
        }
        
        if ($result instanceof Response) {
            return $result;
        } else {
            $response = new Response();
            $response->setContent($result);
            return $response;
        }
    }

    private function executeController($handler, $params = [])
    {
        if (strpos($handler, '@') === false) {
            return new Response();
        }
        
        list($controller, $method) = explode('@', $handler);
        
        if (!empty($this->namespace)) {
            $controller = $this->namespace . '\\' . $controller;
        }
        
        if (!class_exists($controller)) {
            return new Response();
        }
        
        $instance = new $controller();
        
        if (!method_exists($instance, $method)) {
            return new Response();
        }
        
        return call_user_func_array([$instance, $method], $params);
    }

    public function getRoutes()
    {
        return $this->routes;
    }

    public function clearRoutes()
    {
        $this->routes = [];
        return $this;
    }
} 