<?php
namespace GestaoInterativa\Middleware;

use GestaoInterativa\Exceptions\AuthException;

class AuthMiddleware {
    public static function check() {
        session_start();
        if (empty($_SESSION['user_id'])) {
            throw new AuthException('Usuário não autenticado');
        }
    }
} 