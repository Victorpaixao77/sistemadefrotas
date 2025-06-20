<?php

namespace GestaoInterativa\Http;

class Request
{
    private $method;
    private $uri;
    private $query;
    private $post;
    private $files;
    private $headers;
    private $rawBody;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->uri = $_SERVER['REQUEST_URI'] ?? '/';
        $this->query = $_GET;
        $this->post = $_POST;
        $this->files = $_FILES;
        $this->headers = $this->getHeaders();
        $this->rawBody = file_get_contents('php://input');
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function isMethod($method)
    {
        return strtoupper($this->method) === strtoupper($method);
    }

    public function getUri()
    {
        return $this->uri;
    }

    public function getPath()
    {
        $path = parse_url($this->uri, PHP_URL_PATH);
        return $path ?: '/';
    }

    public function getQuery()
    {
        return $this->query;
    }

    public function getQueryParam($key, $default = null)
    {
        return isset($this->query[$key]) ? $this->query[$key] : $default;
    }

    public function hasQueryParam($key)
    {
        return isset($this->query[$key]);
    }

    public function getPost()
    {
        return $this->post;
    }

    public function getPostParam($key, $default = null)
    {
        return isset($this->post[$key]) ? $this->post[$key] : $default;
    }

    public function hasPostParam($key)
    {
        return isset($this->post[$key]);
    }

    public function getFiles()
    {
        return $this->files;
    }

    public function getFile($key)
    {
        return isset($this->files[$key]) ? $this->files[$key] : null;
    }

    public function hasFile($key)
    {
        return isset($this->files[$key]);
    }

    public function getHeaders()
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[ucwords($header, '-')] = $value;
            }
        }
        return $headers;
    }

    public function getHeader($key, $default = null)
    {
        return isset($this->headers[$key]) ? $this->headers[$key] : $default;
    }

    public function hasHeader($key)
    {
        return isset($this->headers[$key]);
    }

    public function getClientIp()
    {
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    public function getUserAgent()
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? null;
    }

    public function getReferer()
    {
        return $_SERVER['HTTP_REFERER'] ?? null;
    }

    public function isAjax()
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    public function isSecure()
    {
        return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    }

    public function getHost()
    {
        return $_SERVER['HTTP_HOST'] ?? null;
    }

    public function getPort()
    {
        return $_SERVER['SERVER_PORT'] ?? null;
    }

    public function getScheme()
    {
        return $this->isSecure() ? 'https' : 'http';
    }

    public function getBaseUrl()
    {
        $scheme = $this->getScheme();
        $host = $this->getHost();
        $port = $this->getPort();
        
        $url = "{$scheme}://{$host}";
        
        if (($scheme === 'http' && $port !== '80') || ($scheme === 'https' && $port !== '443')) {
            $url .= ":{$port}";
        }
        
        return $url;
    }

    public function getUrl()
    {
        return $this->getBaseUrl() . $this->getUri();
    }

    public function getContentType()
    {
        return $_SERVER['CONTENT_TYPE'] ?? null;
    }

    public function getContentLength()
    {
        return isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : null;
    }

    public function getRawBody()
    {
        return $this->rawBody;
    }

    public function setRawBody($body)
    {
        $this->rawBody = $body;
    }

    public function getJson()
    {
        $content = $this->getRawBody();
        $data = json_decode($content, true);
        return json_last_error() === JSON_ERROR_NONE ? $data : null;
    }

    public function isJson()
    {
        $contentType = $this->getContentType();
        return $contentType && strpos($contentType, 'application/json') === 0;
    }

    public function isXml()
    {
        $contentType = $this->getContentType();
        return $contentType && strpos($contentType, 'application/xml') === 0;
    }

    public function isForm()
    {
        $contentType = $this->getContentType();
        return $contentType && strpos($contentType, 'application/x-www-form-urlencoded') === 0;
    }

    public function isMultipart()
    {
        $contentType = $this->getContentType();
        return $contentType && strpos($contentType, 'multipart/form-data') === 0;
    }
} 