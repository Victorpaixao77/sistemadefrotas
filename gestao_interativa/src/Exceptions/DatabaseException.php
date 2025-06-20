<?php
namespace GestaoInterativa\Exceptions;

class DatabaseException extends AppException {
    public function __construct($message = "Erro no banco de dados", $data = []) {
        parent::__construct($message, 500, $data);
    }
} 