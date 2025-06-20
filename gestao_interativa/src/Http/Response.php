<?php

namespace GestaoInterativa\Http;

class Response
{
    private $content;
    private $statusCode;
    private $headers;
    private $cookies;

    public function __construct($content = '', $statusCode = 200, $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->cookies = [];
    }

    public function setContent($content)
    {
        $this->content = $content;
        return $this;
    }

    public function getContent()
    {
        return $this->content;
    }

    public function setStatusCode($statusCode)
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function setHeader($key, $value)
    {
        $this->headers[$key] = $value;
        return $this;
    }

    public function getHeader($key)
    {
        return isset($this->headers[$key]) ? $this->headers[$key] : null;
    }

    public function setHeaders(array $headers)
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function removeHeader($key)
    {
        unset($this->headers[$key]);
        return $this;
    }

    public function clearHeaders()
    {
        $this->headers = [];
        return $this;
    }

    public function setCookie($name, $value, $expire = 0, $path = '/', $domain = '', $secure = false, $httponly = false)
    {
        $this->cookies[$name] = [
            'value' => $value,
            'expire' => $expire,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly
        ];
        return $this;
    }

    public function getCookies()
    {
        return $this->cookies;
    }

    public function setCookies(array $cookies)
    {
        $this->cookies = array_merge($this->cookies, $cookies);
        return $this;
    }

    public function removeCookie($name)
    {
        unset($this->cookies[$name]);
        return $this;
    }

    public function clearCookies()
    {
        $this->cookies = [];
        return $this;
    }

    public function setLastModified(\DateTime $date)
    {
        $this->setHeader('Last-Modified', $date->format('D, d M Y H:i:s') . ' GMT');
        return $this;
    }

    public function setETag($etag)
    {
        $this->setHeader('ETag', '"' . $etag . '"');
        return $this;
    }

    public function setCache($maxAge)
    {
        $this->setHeader('Cache-Control', 'max-age=' . $maxAge);
        return $this;
    }

    public function setNoCache()
    {
        $this->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        return $this;
    }

    public function setPrivate()
    {
        $this->setHeader('Cache-Control', 'private');
        return $this;
    }

    public function setPublic()
    {
        $this->setHeader('Cache-Control', 'public');
        return $this;
    }

    public function setExpires(\DateTime $date)
    {
        $this->setHeader('Expires', $date->format('D, d M Y H:i:s') . ' GMT');
        return $this;
    }

    public function setVary($value)
    {
        $this->setHeader('Vary', $value);
        return $this;
    }

    public function setContentType($type)
    {
        $this->setHeader('Content-Type', $type);
        return $this;
    }

    public function setContentLength($length)
    {
        $this->setHeader('Content-Length', $length);
        return $this;
    }

    public function setContentDisposition($disposition, $filename = null)
    {
        $header = $disposition;
        if ($filename) {
            $header .= '; filename="' . $filename . '"';
        }
        $this->setHeader('Content-Disposition', $header);
        return $this;
    }

    public function setLocation($url)
    {
        $this->setHeader('Location', $url);
        return $this;
    }

    public function setRefresh($seconds, $url = null)
    {
        $header = $seconds;
        if ($url) {
            $header .= ';url=' . $url;
        }
        $this->setHeader('Refresh', $header);
        return $this;
    }

    public function setAllow(array $methods)
    {
        $this->setHeader('Allow', implode(', ', $methods));
        return $this;
    }

    public function setContentLanguage($language)
    {
        $this->setHeader('Content-Language', $language);
        return $this;
    }

    public function setContentEncoding($encoding)
    {
        $this->setHeader('Content-Encoding', $encoding);
        return $this;
    }

    public function setXFrameOptions($value)
    {
        $this->setHeader('X-Frame-Options', $value);
        return $this;
    }

    public function setXContentTypeOptions($value)
    {
        $this->setHeader('X-Content-Type-Options', $value);
        return $this;
    }

    public function setXSSProtection($enabled, $mode = 'block')
    {
        if ($enabled) {
            $this->setHeader('X-XSS-Protection', '1; mode=' . $mode);
        } else {
            $this->setHeader('X-XSS-Protection', '0');
        }
        return $this;
    }

    public function setStrictTransportSecurity($maxAge, $includeSubDomains = false, $preload = false)
    {
        $header = 'max-age=' . $maxAge;
        if ($includeSubDomains) {
            $header .= '; includeSubDomains';
        }
        if ($preload) {
            $header .= '; preload';
        }
        $this->setHeader('Strict-Transport-Security', $header);
        return $this;
    }

    public function setContentSecurityPolicy($policy)
    {
        $this->setHeader('Content-Security-Policy', $policy);
        return $this;
    }

    public function setReferrerPolicy($policy)
    {
        $this->setHeader('Referrer-Policy', $policy);
        return $this;
    }

    public function setPermissionsPolicy($policy)
    {
        $this->setHeader('Permissions-Policy', $policy);
        return $this;
    }

    public function setCrossOriginEmbedderPolicy($policy)
    {
        $this->setHeader('Cross-Origin-Embedder-Policy', $policy);
        return $this;
    }

    public function setCrossOriginOpenerPolicy($policy)
    {
        $this->setHeader('Cross-Origin-Opener-Policy', $policy);
        return $this;
    }

    public function setCrossOriginResourcePolicy($policy)
    {
        $this->setHeader('Cross-Origin-Resource-Policy', $policy);
        return $this;
    }

    public function setAccessControlAllowOrigin($origin)
    {
        $this->setHeader('Access-Control-Allow-Origin', $origin);
        return $this;
    }

    public function setAccessControlAllowMethods(array $methods)
    {
        $this->setHeader('Access-Control-Allow-Methods', implode(', ', $methods));
        return $this;
    }

    public function setAccessControlAllowHeaders(array $headers)
    {
        $this->setHeader('Access-Control-Allow-Headers', implode(', ', $headers));
        return $this;
    }

    public function setAccessControlAllowCredentials($allow)
    {
        $this->setHeader('Access-Control-Allow-Credentials', $allow ? 'true' : 'false');
        return $this;
    }

    public function setAccessControlMaxAge($maxAge)
    {
        $this->setHeader('Access-Control-Max-Age', $maxAge);
        return $this;
    }

    public function setAccessControlExposeHeaders(array $headers)
    {
        $this->setHeader('Access-Control-Expose-Headers', implode(', ', $headers));
        return $this;
    }

    public function send()
    {
        // Enviar status code
        http_response_code($this->statusCode);

        // Enviar headers
        foreach ($this->headers as $key => $value) {
            header("{$key}: {$value}");
        }

        // Enviar cookies
        foreach ($this->cookies as $name => $cookie) {
            setcookie(
                $name,
                $cookie['value'],
                $cookie['expire'],
                $cookie['path'],
                $cookie['domain'],
                $cookie['secure'],
                $cookie['httponly']
            );
        }

        // Enviar conteúdo
        echo $this->content;
    }

    public function json($data, $statusCode = 200)
    {
        $this->setContentType('application/json');
        $this->setStatusCode($statusCode);
        $this->setContent(json_encode($data));
        return $this;
    }

    public function redirect($url, $statusCode = 302)
    {
        $this->setLocation($url);
        $this->setStatusCode($statusCode);
        return $this;
    }

    public function notFound($message = 'Not Found')
    {
        $this->setStatusCode(404);
        $this->setContent($message);
        return $this;
    }

    public function forbidden($message = 'Forbidden')
    {
        $this->setStatusCode(403);
        $this->setContent($message);
        return $this;
    }

    public function unauthorized($message = 'Unauthorized')
    {
        $this->setStatusCode(401);
        $this->setContent($message);
        return $this;
    }

    public function methodNotAllowed($message = 'Method Not Allowed')
    {
        $this->setStatusCode(405);
        $this->setContent($message);
        return $this;
    }

    public function internalServerError($message = 'Internal Server Error')
    {
        $this->setStatusCode(500);
        $this->setContent($message);
        return $this;
    }
} 