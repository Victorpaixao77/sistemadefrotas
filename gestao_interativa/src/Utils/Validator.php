<?php
namespace GestaoInterativa\Utils;

use GestaoInterativa\Exceptions\ValidationException;

class Validator {
    private $data;
    private $rules;
    private $errors = [];

    public function __construct(array $data, array $rules) {
        $this->data = $data;
        $this->rules = $rules;
    }

    public function validate() {
        foreach ($this->rules as $field => $rules) {
            $rules = explode('|', $rules);
            
            foreach ($rules as $rule) {
                if (strpos($rule, ':') !== false) {
                    list($rule, $parameter) = explode(':', $rule);
                } else {
                    $parameter = null;
                }
                
                $method = 'validate' . ucfirst($rule);
                if (method_exists($this, $method)) {
                    $this->$method($field, $parameter);
                }
            }
        }
        
        if (!empty($this->errors)) {
            throw new ValidationException('Erro de validação', $this->errors);
        }
        
        return true;
    }

    private function validateRequired($field) {
        if (!isset($this->data[$field]) || $this->data[$field] === '') {
            $this->addError($field, 'O campo é obrigatório');
        }
    }

    private function validateEmail($field) {
        if (isset($this->data[$field]) && $this->data[$field] !== '') {
            if (!filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
                $this->addError($field, 'E-mail inválido');
            }
        }
    }

    private function validateMin($field, $parameter) {
        if (isset($this->data[$field]) && $this->data[$field] !== '') {
            if (strlen($this->data[$field]) < $parameter) {
                $this->addError($field, "O campo deve ter no mínimo {$parameter} caracteres");
            }
        }
    }

    private function validateMax($field, $parameter) {
        if (isset($this->data[$field]) && $this->data[$field] !== '') {
            if (strlen($this->data[$field]) > $parameter) {
                $this->addError($field, "O campo deve ter no máximo {$parameter} caracteres");
            }
        }
    }

    private function validateNumeric($field) {
        if (isset($this->data[$field]) && $this->data[$field] !== '') {
            if (!is_numeric($this->data[$field])) {
                $this->addError($field, 'O campo deve ser numérico');
            }
        }
    }

    private function validateInteger($field) {
        if (isset($this->data[$field]) && $this->data[$field] !== '') {
            if (!filter_var($this->data[$field], FILTER_VALIDATE_INT)) {
                $this->addError($field, 'O campo deve ser um número inteiro');
            }
        }
    }

    private function validateFloat($field) {
        if (isset($this->data[$field]) && $this->data[$field] !== '') {
            if (!filter_var($this->data[$field], FILTER_VALIDATE_FLOAT)) {
                $this->addError($field, 'O campo deve ser um número decimal');
            }
        }
    }

    private function validateDate($field) {
        if (isset($this->data[$field]) && $this->data[$field] !== '') {
            $date = \DateTime::createFromFormat('Y-m-d', $this->data[$field]);
            if (!$date || $date->format('Y-m-d') !== $this->data[$field]) {
                $this->addError($field, 'Data inválida');
            }
        }
    }

    private function validateDatetime($field) {
        if (isset($this->data[$field]) && $this->data[$field] !== '') {
            $date = \DateTime::createFromFormat('Y-m-d H:i:s', $this->data[$field]);
            if (!$date || $date->format('Y-m-d H:i:s') !== $this->data[$field]) {
                $this->addError($field, 'Data e hora inválidas');
            }
        }
    }

    private function validateCpf($field) {
        if (isset($this->data[$field]) && $this->data[$field] !== '') {
            if (!Helpers::validateCpf($this->data[$field])) {
                $this->addError($field, 'CPF inválido');
            }
        }
    }

    private function validateCnpj($field) {
        if (isset($this->data[$field]) && $this->data[$field] !== '') {
            if (!Helpers::validateCnpj($this->data[$field])) {
                $this->addError($field, 'CNPJ inválido');
            }
        }
    }

    private function validatePlaca($field) {
        if (isset($this->data[$field]) && $this->data[$field] !== '') {
            if (!Helpers::validatePlaca($this->data[$field])) {
                $this->addError($field, 'Placa inválida');
            }
        }
    }

    private function validateIn($field, $parameter) {
        if (isset($this->data[$field]) && $this->data[$field] !== '') {
            $values = explode(',', $parameter);
            if (!in_array($this->data[$field], $values)) {
                $this->addError($field, 'Valor inválido');
            }
        }
    }

    private function validateNotIn($field, $parameter) {
        if (isset($this->data[$field]) && $this->data[$field] !== '') {
            $values = explode(',', $parameter);
            if (in_array($this->data[$field], $values)) {
                $this->addError($field, 'Valor inválido');
            }
        }
    }

    private function validateBetween($field, $parameter) {
        if (isset($this->data[$field]) && $this->data[$field] !== '') {
            list($min, $max) = explode(',', $parameter);
            if ($this->data[$field] < $min || $this->data[$field] > $max) {
                $this->addError($field, "O valor deve estar entre {$min} e {$max}");
            }
        }
    }

    private function validateSize($field, $parameter) {
        if (isset($this->data[$field]) && $this->data[$field] !== '') {
            if (strlen($this->data[$field]) != $parameter) {
                $this->addError($field, "O campo deve ter {$parameter} caracteres");
            }
        }
    }

    private function validateUrl($field) {
        if (isset($this->data[$field]) && $this->data[$field] !== '') {
            if (!filter_var($this->data[$field], FILTER_VALIDATE_URL)) {
                $this->addError($field, 'URL inválida');
            }
        }
    }

    private function validateAlpha($field) {
        if (isset($this->data[$field]) && $this->data[$field] !== '') {
            if (!ctype_alpha($this->data[$field])) {
                $this->addError($field, 'O campo deve conter apenas letras');
            }
        }
    }

    private function validateAlphaNum($field) {
        if (isset($this->data[$field]) && $this->data[$field] !== '') {
            if (!ctype_alnum($this->data[$field])) {
                $this->addError($field, 'O campo deve conter apenas letras e números');
            }
        }
    }

    private function validateAlphaDash($field) {
        if (isset($this->data[$field]) && $this->data[$field] !== '') {
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $this->data[$field])) {
                $this->addError($field, 'O campo deve conter apenas letras, números, traços e underscores');
            }
        }
    }

    private function validateRegex($field, $parameter) {
        if (isset($this->data[$field]) && $this->data[$field] !== '') {
            if (!preg_match($parameter, $this->data[$field])) {
                $this->addError($field, 'Formato inválido');
            }
        }
    }

    private function validateConfirmed($field) {
        if (isset($this->data[$field]) && $this->data[$field] !== '') {
            $confirmation = $field . '_confirmation';
            if (!isset($this->data[$confirmation]) || $this->data[$field] !== $this->data[$confirmation]) {
                $this->addError($field, 'A confirmação não corresponde');
            }
        }
    }

    private function validateDifferent($field, $parameter) {
        if (isset($this->data[$field]) && $this->data[$field] !== '') {
            if (!isset($this->data[$parameter]) || $this->data[$field] === $this->data[$parameter]) {
                $this->addError($field, 'O campo deve ser diferente de ' . $parameter);
            }
        }
    }

    private function validateSame($field, $parameter) {
        if (isset($this->data[$field]) && $this->data[$field] !== '') {
            if (!isset($this->data[$parameter]) || $this->data[$field] !== $this->data[$parameter]) {
                $this->addError($field, 'O campo deve ser igual a ' . $parameter);
            }
        }
    }

    private function addError($field, $message) {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    public function getErrors() {
        return $this->errors;
    }

    public function hasErrors() {
        return !empty($this->errors);
    }
} 