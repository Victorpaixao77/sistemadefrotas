<?php

namespace GestaoInterativa\Tests\Exceptions;

use PHPUnit\Framework\TestCase;
use GestaoInterativa\Exceptions\ErrorHandler;
use GestaoInterativa\Exceptions\ValidationException;
use GestaoInterativa\Exceptions\NotFoundException;
use GestaoInterativa\Exceptions\AuthException;
use GestaoInterativa\Exceptions\DatabaseException;
use Exception;

class ErrorHandlerTest extends TestCase
{
    private $errorHandler;
    private $config;

    protected function setUp(): void
    {
        $this->config = [
            'debug' => true,
            'logging' => [
                'enabled' => true,
                'path' => __DIR__ . '/../../logs',
                'level' => 'debug'
            ]
        ];

        $this->errorHandler = new ErrorHandler($this->config);
    }

    public function testErrorHandlerCanBeCreated()
    {
        $this->assertInstanceOf(ErrorHandler::class, $this->errorHandler);
    }

    public function testHandleErrorConvertsErrorToException()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Test error');

        $this->errorHandler->handleError(E_USER_ERROR, 'Test error', 'test.php', 1);
    }

    public function testHandleExceptionLogsException()
    {
        $exception = new Exception('Test exception');
        
        ob_start();
        $this->errorHandler->handleException($exception);
        $output = ob_get_clean();

        $this->assertStringContainsString('Test exception', $output);
    }

    public function testHandleShutdownHandlesFatalError()
    {
        $this->errorHandler->handleShutdown();

        $this->assertTrue(true); // Se chegou aqui, não houve erro fatal
    }

    public function testIsFatalErrorReturnsTrueForFatalErrors()
    {
        $this->assertTrue($this->errorHandler->isFatalError(E_ERROR));
        $this->assertTrue($this->errorHandler->isFatalError(E_PARSE));
        $this->assertTrue($this->errorHandler->isFatalError(E_CORE_ERROR));
        $this->assertTrue($this->errorHandler->isFatalError(E_COMPILE_ERROR));
    }

    public function testIsFatalErrorReturnsFalseForNonFatalErrors()
    {
        $this->assertFalse($this->errorHandler->isFatalError(E_WARNING));
        $this->assertFalse($this->errorHandler->isFatalError(E_NOTICE));
        $this->assertFalse($this->errorHandler->isFatalError(E_USER_WARNING));
        $this->assertFalse($this->errorHandler->isFatalError(E_USER_NOTICE));
    }

    public function testLogExceptionCreatesLogFile()
    {
        $exception = new Exception('Test exception');
        
        $this->errorHandler->logException($exception);

        $logFile = $this->config['logging']['path'] . '/error.log';
        $this->assertFileExists($logFile);
    }

    public function testRenderExceptionRendersHtmlInWebContext()
    {
        $exception = new Exception('Test exception');
        
        ob_start();
        $this->errorHandler->renderException($exception);
        $output = ob_get_clean();

        $this->assertStringContainsString('<!DOCTYPE html>', $output);
        $this->assertStringContainsString('Test exception', $output);
    }

    public function testRenderCliExceptionRendersTextInCliContext()
    {
        $exception = new Exception('Test exception');
        
        ob_start();
        $this->errorHandler->renderCliException($exception);
        $output = ob_get_clean();

        $this->assertStringContainsString('Test exception', $output);
    }

    public function testRenderHtmlExceptionRendersHtml()
    {
        $exception = new Exception('Test exception');
        
        ob_start();
        $this->errorHandler->renderHtmlException($exception);
        $output = ob_get_clean();

        $this->assertStringContainsString('<!DOCTYPE html>', $output);
        $this->assertStringContainsString('Test exception', $output);
    }

    public function testRenderErrorPageRendersGenericErrorPage()
    {
        ob_start();
        $this->errorHandler->renderErrorPage();
        $output = ob_get_clean();

        $this->assertStringContainsString('<!DOCTYPE html>', $output);
        $this->assertStringContainsString('Error', $output);
    }

    public function testHandleValidationException()
    {
        $exception = new ValidationException('Validation error');
        
        ob_start();
        $this->errorHandler->handleException($exception);
        $output = ob_get_clean();

        $this->assertStringContainsString('Validation error', $output);
    }

    public function testHandleNotFoundException()
    {
        $exception = new NotFoundException('Resource not found');
        
        ob_start();
        $this->errorHandler->handleException($exception);
        $output = ob_get_clean();

        $this->assertStringContainsString('Resource not found', $output);
    }

    public function testHandleAuthException()
    {
        $exception = new AuthException('Authentication error');
        
        ob_start();
        $this->errorHandler->handleException($exception);
        $output = ob_get_clean();

        $this->assertStringContainsString('Authentication error', $output);
    }

    public function testHandleDatabaseException()
    {
        $exception = new DatabaseException('Database error');
        
        ob_start();
        $this->errorHandler->handleException($exception);
        $output = ob_get_clean();

        $this->assertStringContainsString('Database error', $output);
    }
} 