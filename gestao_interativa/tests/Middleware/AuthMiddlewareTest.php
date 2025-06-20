<?php

namespace GestaoInterativa\Tests\Middleware;

use PHPUnit\Framework\TestCase;
use GestaoInterativa\Middleware\AuthMiddleware;
use GestaoInterativa\Exceptions\AuthException;

class AuthMiddlewareTest extends TestCase
{
    private $middleware;

    protected function setUp(): void
    {
        $this->middleware = new AuthMiddleware();
    }

    public function testMiddlewareCanBeCreated()
    {
        $this->assertInstanceOf(AuthMiddleware::class, $this->middleware);
    }

    public function testMiddlewareThrowsExceptionWhenUserNotLoggedIn()
    {
        $_SESSION = [];

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Usuário não autenticado');

        $this->middleware->handle();
    }

    public function testMiddlewarePassesWhenUserIsLoggedIn()
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['empresa_id'] = 1;

        $this->assertTrue($this->middleware->handle());
    }

    public function testMiddlewareThrowsExceptionWhenEmpresaIdNotSet()
    {
        $_SESSION['user_id'] = 1;
        unset($_SESSION['empresa_id']);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Empresa não definida');

        $this->middleware->handle();
    }

    public function testMiddlewareThrowsExceptionWhenUserIdNotSet()
    {
        $_SESSION['empresa_id'] = 1;
        unset($_SESSION['user_id']);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Usuário não autenticado');

        $this->middleware->handle();
    }

    public function testMiddlewareThrowsExceptionWhenSessionNotStarted()
    {
        session_destroy();

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Sessão não iniciada');

        $this->middleware->handle();
    }
} 