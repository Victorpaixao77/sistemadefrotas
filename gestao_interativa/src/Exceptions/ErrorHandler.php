<?php
namespace GestaoInterativa\Exceptions;

class ErrorHandler {
    private $config;

    public function __construct($config) {
        $this->config = $config;
    }

    public function register() {
        error_reporting(E_ALL);
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    public function handleError($level, $message, $file = '', $line = 0) {
        if (error_reporting() & $level) {
            throw new \ErrorException($message, 0, $level, $file, $line);
        }
        return true;
    }

    public function handleException($exception) {
        $this->logException($exception);
        
        if ($this->config['app']['debug']) {
            $this->renderException($exception);
        } else {
            $this->renderErrorPage($exception);
        }
    }

    public function handleShutdown() {
        $error = error_get_last();
        if ($error !== null && $this->isFatalError($error['type'])) {
            $this->handleException(new \ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            ));
        }
    }

    private function isFatalError($type) {
        return in_array($type, [
            E_ERROR,
            E_PARSE,
            E_CORE_ERROR,
            E_CORE_WARNING,
            E_COMPILE_ERROR,
            E_COMPILE_WARNING
        ]);
    }

    private function logException($exception) {
        if (!$this->config['logging']['enabled']) {
            return;
        }

        $logFile = $this->config['logging']['path'] . '/exceptions.log';
        $message = sprintf(
            "[%s] %s: %s in %s:%d\nStack trace:\n%s\n",
            date('Y-m-d H:i:s'),
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );

        file_put_contents($logFile, $message, FILE_APPEND);
    }

    private function renderException($exception) {
        if (php_sapi_name() === 'cli') {
            $this->renderCliException($exception);
        } else {
            $this->renderHtmlException($exception);
        }
    }

    private function renderCliException($exception) {
        echo "\n";
        echo "Exception: " . get_class($exception) . "\n";
        echo "Message: " . $exception->getMessage() . "\n";
        echo "File: " . $exception->getFile() . "\n";
        echo "Line: " . $exception->getLine() . "\n";
        echo "Stack trace:\n" . $exception->getTraceAsString() . "\n";
    }

    private function renderHtmlException($exception) {
        $statusCode = $exception instanceof AppException ? $exception->getCode() : 500;
        http_response_code($statusCode);
        
        echo '<!DOCTYPE html>';
        echo '<html>';
        echo '<head>';
        echo '<title>Error</title>';
        echo '<style>';
        echo 'body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }';
        echo '.error-container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }';
        echo '.error-title { color: #e74c3c; margin: 0 0 20px; }';
        echo '.error-message { background: #f8f9fa; padding: 15px; border-radius: 3px; margin-bottom: 20px; }';
        echo '.error-file { color: #666; font-size: 0.9em; margin-bottom: 20px; }';
        echo '.error-trace { background: #f8f9fa; padding: 15px; border-radius: 3px; font-family: monospace; white-space: pre-wrap; }';
        echo '</style>';
        echo '</head>';
        echo '<body>';
        echo '<div class="error-container">';
        echo '<h1 class="error-title">' . get_class($exception) . '</h1>';
        echo '<div class="error-message">' . $exception->getMessage() . '</div>';
        echo '<div class="error-file">';
        echo 'File: ' . $exception->getFile() . '<br>';
        echo 'Line: ' . $exception->getLine();
        echo '</div>';
        echo '<div class="error-trace">' . $exception->getTraceAsString() . '</div>';
        echo '</div>';
        echo '</body>';
        echo '</html>';
    }

    private function renderErrorPage($exception) {
        $statusCode = $exception instanceof AppException ? $exception->getCode() : 500;
        http_response_code($statusCode);
        
        echo '<!DOCTYPE html>';
        echo '<html>';
        echo '<head>';
        echo '<title>Error</title>';
        echo '<style>';
        echo 'body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }';
        echo '.error-container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; }';
        echo '.error-title { color: #e74c3c; margin: 0 0 20px; }';
        echo '.error-message { color: #666; }';
        echo '</style>';
        echo '</head>';
        echo '<body>';
        echo '<div class="error-container">';
        echo '<h1 class="error-title">Oops! Algo deu errado</h1>';
        echo '<div class="error-message">';
        echo 'Desculpe, ocorreu um erro inesperado. Por favor, tente novamente mais tarde.';
        echo '</div>';
        echo '</div>';
        echo '</body>';
        echo '</html>';
    }
} 