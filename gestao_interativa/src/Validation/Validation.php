<?php

namespace GestaoInterativa\Validation;

use GestaoInterativa\Exceptions\ValidationException;

class Validation
{
    private $errors = [];
    private $data = [];
    private $rules = [];

    public function validate($data, $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->errors = [];

        foreach ($rules as $field => $ruleString) {
            $fieldRules = explode('|', $ruleString);
            
            foreach ($fieldRules as $rule) {
                $this->applyRule($field, $rule);
            }
        }

        if (!empty($this->errors)) {
            throw new ValidationException('Validation failed', 422, $this->errors);
        }

        return true;
    }

    private function applyRule($field, $rule)
    {
        $value = $this->getValue($field);
        $params = [];

        if (strpos($rule, ':') !== false) {
            list($rule, $paramString) = explode(':', $rule, 2);
            $params = explode(',', $paramString);
        }

        $method = 'validate' . ucfirst($rule);
        
        if (method_exists($this, $method)) {
            if (!$this->$method($value, $params)) {
                $this->addError($field, $rule, $params);
            }
        }
    }

    private function validateRequired($value, $params = [])
    {
        return !empty($value) || $value === '0';
    }

    private function validateEmail($value, $params = [])
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function validateMin($value, $params = [])
    {
        $min = isset($params[0]) ? (int)$params[0] : 0;
        return strlen($value) >= $min;
    }

    private function validateMax($value, $params = [])
    {
        $max = isset($params[0]) ? (int)$params[0] : 0;
        return strlen($value) <= $max;
    }

    private function validateNumeric($value, $params = [])
    {
        return is_numeric($value);
    }

    private function validateAlpha($value, $params = [])
    {
        return ctype_alpha($value);
    }

    private function validateAlphaNumeric($value, $params = [])
    {
        return ctype_alnum($value);
    }

    private function validateDate($value, $params = [])
    {
        $date = \DateTime::createFromFormat('Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value;
    }

    private function validateCpf($value, $params = [])
    {
        // Remove caracteres não numéricos
        $cpf = preg_replace('/[^0-9]/', '', $value);
        
        // Verifica se tem 11 dígitos
        if (strlen($cpf) != 11) {
            return false;
        }
        
        // Verifica se todos os dígitos são iguais
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }
        
        // Calcula os dígitos verificadores
        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }
        
        return true;
    }

    private function validateCnpj($value, $params = [])
    {
        // Remove caracteres não numéricos
        $cnpj = preg_replace('/[^0-9]/', '', $value);
        
        // Verifica se tem 14 dígitos
        if (strlen($cnpj) != 14) {
            return false;
        }
        
        // Verifica se todos os dígitos são iguais
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }
        
        // Calcula os dígitos verificadores
        for ($i = 0, $j = 5, $soma = 0; $i < 12; $i++) {
            $soma += $cnpj[$i] * $j;
            $j = ($j == 2) ? 9 : $j - 1;
        }
        
        $resto = $soma % 11;
        if ($cnpj[12] != ($resto < 2 ? 0 : 11 - $resto)) {
            return false;
        }
        
        for ($i = 0, $j = 6, $soma = 0; $i < 13; $i++) {
            $soma += $cnpj[$i] * $j;
            $j = ($j == 2) ? 9 : $j - 1;
        }
        
        $resto = $soma % 11;
        return $cnpj[13] == ($resto < 2 ? 0 : 11 - $resto);
    }

    private function validatePlaca($value, $params = [])
    {
        // Remove espaços e converte para maiúsculas
        $placa = strtoupper(trim($value));
        
        // Padrão Mercosul (ABC1D23) ou padrão antigo (ABC1234)
        return preg_match('/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/', $placa) || 
               preg_match('/^[A-Z]{3}[0-9]{4}$/', $placa);
    }

    private function validateIn($value, $params = [])
    {
        return in_array($value, $params);
    }

    private function validateNotIn($value, $params = [])
    {
        return !in_array($value, $params);
    }

    private function validateBetween($value, $params = [])
    {
        if (count($params) < 2) {
            return false;
        }
        
        $min = (int)$params[0];
        $max = (int)$params[1];
        
        return $value >= $min && $value <= $max;
    }

    private function validateSize($value, $params = [])
    {
        $size = isset($params[0]) ? (int)$params[0] : 0;
        return strlen($value) == $size;
    }

    private function validateUrl($value, $params = [])
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    private function validateIp($value, $params = [])
    {
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    private function validateJson($value, $params = [])
    {
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    private function getValue($field)
    {
        return isset($this->data[$field]) ? $this->data[$field] : null;
    }

    private function addError($field, $rule, $params = [])
    {
        $message = $this->getErrorMessage($field, $rule, $params);
        $this->errors[$field] = $message;
    }

    private function getErrorMessage($field, $rule, $params = [])
    {
        $messages = [
            'required' => "The {$field} field is required.",
            'email' => "The {$field} field must be a valid email address.",
            'min' => "The {$field} field must be at least {$params[0]} characters.",
            'max' => "The {$field} field may not be greater than {$params[0]} characters.",
            'numeric' => "The {$field} field must be a number.",
            'alpha' => "The {$field} field may only contain letters.",
            'alpha_numeric' => "The {$field} field may only contain letters and numbers.",
            'date' => "The {$field} field is not a valid date.",
            'cpf' => "The {$field} field is not a valid CPF.",
            'cnpj' => "The {$field} field is not a valid CNPJ.",
            'placa' => "The {$field} field is not a valid vehicle plate.",
            'in' => "The selected {$field} is invalid.",
            'not_in' => "The selected {$field} is invalid.",
            'between' => "The {$field} field must be between {$params[0]} and {$params[1]}.",
            'size' => "The {$field} field must be {$params[0]} characters.",
            'url' => "The {$field} field format is invalid.",
            'ip' => "The {$field} field must be a valid IP address.",
            'json' => "The {$field} field must be a valid JSON string."
        ];

        return isset($messages[$rule]) ? $messages[$rule] : "The {$field} field failed validation.";
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getFirstError()
    {
        return reset($this->errors);
    }

    public function hasErrors()
    {
        return !empty($this->errors);
    }

    public function clearErrors()
    {
        $this->errors = [];
    }
} 