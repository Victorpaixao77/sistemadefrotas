<?php

namespace GestaoInterativa\View;

class View
{
    private $viewPath;
    private $data = [];
    private $sections = [];
    private $currentSection = null;
    private $layout = null;
    private $filters = [];
    private $helpers = [];
    private $shared = [];
    private $composers = [];
    private $creators = [];
    private $extensions = [];

    public function __construct($viewPath)
    {
        $this->viewPath = rtrim($viewPath, '/');
    }

    public function render($view, $data = [], $layout = null)
    {
        $this->data = array_merge($this->shared, $data);
        $this->layout = $layout;
        $this->sections = [];
        $this->currentSection = null;

        // Executar composers
        $this->runComposers($view);

        // Renderizar view
        $content = $this->renderView($view);

        // Aplicar layout se especificado
        if ($this->layout) {
            $content = $this->renderLayout($this->layout, $content);
        }

        return $content;
    }

    private function renderView($view)
    {
        $file = $this->viewPath . '/' . $view . '.php';
        
        if (!file_exists($file)) {
            throw new \Exception("View file not found: {$file}");
        }

        // Extrair dados para a view
        extract($this->data);

        // Capturar saída
        ob_start();
        include $file;
        $content = ob_get_clean();

        // Processar seções
        $content = $this->processSections($content);

        return $content;
    }

    private function renderLayout($layout, $content)
    {
        $this->data['content'] = $content;
        return $this->renderView($layout);
    }

    private function processSections($content)
    {
        // Processar seções aninhadas
        foreach ($this->sections as $name => $sectionContent) {
            $content = str_replace("@yield('{$name}')", $sectionContent, $content);
        }

        return $content;
    }

    public function section($name)
    {
        $this->currentSection = $name;
        ob_start();
    }

    public function endSection()
    {
        if ($this->currentSection) {
            $this->sections[$this->currentSection] = ob_get_clean();
            $this->currentSection = null;
        }
    }

    public function yield($name)
    {
        return isset($this->sections[$name]) ? $this->sections[$name] : '';
    }

    public function include($view, $data = [])
    {
        $data = array_merge($this->data, $data);
        return $this->renderView($view);
    }

    public function extend($layout)
    {
        $this->layout = $layout;
    }

    public function escape($value)
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    public function raw($value)
    {
        return $value;
    }

    public function if($condition, $content, $else = '')
    {
        return $condition ? $content : $else;
    }

    public function foreach($array, $callback)
    {
        $result = '';
        foreach ($array as $key => $value) {
            $result .= $callback($value, $key);
        }
        return $result;
    }

    public function addFilter($name, callable $filter)
    {
        $this->filters[$name] = $filter;
        return $this;
    }

    public function addHelper($name, callable $helper)
    {
        $this->helpers[$name] = $helper;
        return $this;
    }

    public function share($key, $value)
    {
        $this->shared[$key] = $value;
        return $this;
    }

    public function composer($views, callable $callback)
    {
        if (is_array($views)) {
            foreach ($views as $view) {
                $this->composers[$view][] = $callback;
            }
        } else {
            $this->composers[$views][] = $callback;
        }
        return $this;
    }

    public function creator($views, callable $callback)
    {
        if (is_array($views)) {
            foreach ($views as $view) {
                $this->creators[$view][] = $callback;
            }
        } else {
            $this->creators[$views][] = $callback;
        }
        return $this;
    }

    public function addExtension($views, callable $extension)
    {
        if (is_array($views)) {
            foreach ($views as $view) {
                $this->extensions[$view][] = $extension;
            }
        } else {
            $this->extensions[$views][] = $extension;
        }
        return $this;
    }

    private function runComposers($view)
    {
        // Executar creators
        if (isset($this->creators[$view])) {
            foreach ($this->creators[$view] as $callback) {
                $callback($this);
            }
        }

        // Executar composers
        if (isset($this->composers[$view])) {
            foreach ($this->composers[$view] as $callback) {
                $callback($this);
            }
        }

        // Executar extensions
        if (isset($this->extensions[$view])) {
            foreach ($this->extensions[$view] as $extension) {
                $extension($this);
            }
        }

        // Verificar padrões wildcard
        foreach ($this->composers as $pattern => $callbacks) {
            if ($this->matchesPattern($pattern, $view)) {
                foreach ($callbacks as $callback) {
                    $callback($this);
                }
            }
        }

        foreach ($this->creators as $pattern => $callbacks) {
            if ($this->matchesPattern($pattern, $view)) {
                foreach ($callbacks as $callback) {
                    $callback($this);
                }
            }
        }

        foreach ($this->extensions as $pattern => $extensions) {
            if ($this->matchesPattern($pattern, $view)) {
                foreach ($extensions as $extension) {
                    $extension($this);
                }
            }
        }
    }

    private function matchesPattern($pattern, $view)
    {
        if ($pattern === $view) {
            return true;
        }

        // Verificar padrões wildcard
        if (strpos($pattern, '*') !== false) {
            $pattern = str_replace('*', '.*', $pattern);
            return preg_match('/^' . $pattern . '$/', $view);
        }

        return false;
    }

    public function with($key, $value = null)
    {
        if (is_array($key)) {
            $this->data = array_merge($this->data, $key);
        } else {
            $this->data[$key] = $value;
        }
        return $this;
    }

    public function getData()
    {
        return $this->data;
    }

    public function getShared()
    {
        return $this->shared;
    }

    public function getViewPath()
    {
        return $this->viewPath;
    }

    public function setViewPath($path)
    {
        $this->viewPath = rtrim($path, '/');
        return $this;
    }

    // Métodos mágicos para helpers dinâmicos
    public function __call($name, $arguments)
    {
        if (isset($this->helpers[$name])) {
            return call_user_func_array($this->helpers[$name], $arguments);
        }

        throw new \Exception("Helper '{$name}' not found");
    }

    // Métodos para filtros
    public function filter($value, $filter, $params = [])
    {
        if (isset($this->filters[$filter])) {
            return call_user_func_array($this->filters[$filter], array_merge([$value], $params));
        }

        return $value;
    }

    // Métodos para formatação
    public function formatDate($date, $format = 'd/m/Y')
    {
        if (is_string($date)) {
            $date = new \DateTime($date);
        }
        return $date->format($format);
    }

    public function formatDateTime($date, $format = 'd/m/Y H:i:s')
    {
        if (is_string($date)) {
            $date = new \DateTime($date);
        }
        return $date->format($format);
    }

    public function formatCurrency($value, $currency = 'R$')
    {
        return $currency . ' ' . number_format($value, 2, ',', '.');
    }

    public function formatNumber($value, $decimals = 2)
    {
        return number_format($value, $decimals, ',', '.');
    }

    public function formatPhone($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) === 11) {
            return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 5) . '-' . substr($phone, 7);
        }
        return $phone;
    }

    public function formatCpf($cpf)
    {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        if (strlen($cpf) === 11) {
            return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9);
        }
        return $cpf;
    }

    public function formatCnpj($cnpj)
    {
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
        if (strlen($cnpj) === 14) {
            return substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.' . substr($cnpj, 5, 3) . '/' . substr($cnpj, 8, 4) . '-' . substr($cnpj, 12);
        }
        return $cnpj;
    }

    public function formatPlaca($placa)
    {
        $placa = strtoupper(trim($placa));
        if (strlen($placa) === 7) {
            return substr($placa, 0, 3) . '-' . substr($placa, 3);
        }
        return $placa;
    }

    // Métodos para validação
    public function validateEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function validateCpf($cpf)
    {
        // Implementação simplificada
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        return strlen($cpf) === 11;
    }

    public function validateCnpj($cnpj)
    {
        // Implementação simplificada
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
        return strlen($cnpj) === 14;
    }

    public function validatePlaca($placa)
    {
        $placa = strtoupper(trim($placa));
        return preg_match('/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/', $placa) || 
               preg_match('/^[A-Z]{3}[0-9]{4}$/', $placa);
    }

    // Métodos para utilitários
    public function generateRandomString($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $string = '';
        for ($i = 0; $i < $length; $i++) {
            $string .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $string;
    }

    public function encrypt($data, $key = null)
    {
        if (!$key) {
            $key = 'default_key';
        }
        return base64_encode(openssl_encrypt($data, 'AES-256-CBC', $key, 0, substr(hash('sha256', $key), 0, 16)));
    }

    public function decrypt($data, $key = null)
    {
        if (!$key) {
            $key = 'default_key';
        }
        return openssl_decrypt(base64_decode($data), 'AES-256-CBC', $key, 0, substr(hash('sha256', $key), 0, 16));
    }

    public function getClientIp()
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function getUserAgent()
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    public function isAjaxRequest()
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    public function redirect($url, $statusCode = 302)
    {
        header("Location: {$url}", true, $statusCode);
        exit;
    }

    public function getBaseUrl()
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $path = dirname($script);
        
        return "{$protocol}://{$host}{$path}";
    }

    public function getCurrentUrl()
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        
        return "{$protocol}://{$host}{$uri}";
    }

    // Métodos para arquivos
    public function getFileExtension($filename)
    {
        return pathinfo($filename, PATHINFO_EXTENSION);
    }

    public function getFileSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    public function getMimeType($filename)
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filename);
        finfo_close($finfo);
        
        return $mimeType;
    }

    public function isImage($filename)
    {
        $extension = strtolower($this->getFileExtension($filename));
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
        
        return in_array($extension, $imageExtensions);
    }

    public function isPdf($filename)
    {
        return strtolower($this->getFileExtension($filename)) === 'pdf';
    }

    public function getFileIcon($filename)
    {
        $extension = strtolower($this->getFileExtension($filename));
        
        $icons = [
            'pdf' => 'fas fa-file-pdf',
            'doc' => 'fas fa-file-word',
            'docx' => 'fas fa-file-word',
            'xls' => 'fas fa-file-excel',
            'xlsx' => 'fas fa-file-excel',
            'ppt' => 'fas fa-file-powerpoint',
            'pptx' => 'fas fa-file-powerpoint',
            'txt' => 'fas fa-file-alt',
            'jpg' => 'fas fa-file-image',
            'jpeg' => 'fas fa-file-image',
            'png' => 'fas fa-file-image',
            'gif' => 'fas fa-file-image',
            'zip' => 'fas fa-file-archive',
            'rar' => 'fas fa-file-archive',
            'mp3' => 'fas fa-file-audio',
            'mp4' => 'fas fa-file-video',
            'avi' => 'fas fa-file-video'
        ];
        
        return $icons[$extension] ?? 'fas fa-file';
    }
} 