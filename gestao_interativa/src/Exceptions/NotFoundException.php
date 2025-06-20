<?php
namespace GestaoInterativa\Exceptions;

class NotFoundException extends AppException {
    public function __construct($message = "Recurso não encontrado", $data = []) {
        parent::__construct($message, 404, $data);
    }
} 