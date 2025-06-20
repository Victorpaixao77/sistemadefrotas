<?php
namespace GestaoInterativa\Exceptions;

class AuthException extends AppException {
    public function __construct($message = "Erro de autenticação", $data = []) {
        parent::__construct($message, 401, $data);
    }
} 