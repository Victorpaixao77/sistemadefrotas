<?php
namespace GestaoInterativa\Exceptions;

class ValidationException extends AppException {
    public function __construct($message = "Erro de validação", $data = []) {
        parent::__construct($message, 422, $data);
    }
} 