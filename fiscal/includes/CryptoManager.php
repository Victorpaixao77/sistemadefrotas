<?php
/**
 * Gerenciador de Criptografia para Sistema Fiscal
 * Sistema de Frotas
 */

class CryptoManager {
    private $algorithm = 'AES-256-CBC';
    private $key;
    private $iv;
    
    public function __construct($key = null) {
        if ($key) {
            $this->key = hash('sha256', $key, true);
        } else {
            // Chave padrão para desenvolvimento
            $this->key = hash('sha256', 'chave_padrao_sistema_fiscal_2025', true);
        }
        
        // Gerar IV único
        $this->iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->algorithm));
    }
    
    /**
     * Criptografa uma string
     */
    public function encrypt($data) {
        if (empty($data)) {
            return '';
        }
        
        try {
            $encrypted = openssl_encrypt($data, $this->algorithm, $this->key, 0, $this->iv);
            if ($encrypted === false) {
                throw new Exception('Falha na criptografia');
            }
            
            // Retorna IV + dados criptografados em base64
            return base64_encode($this->iv . $encrypted);
        } catch (Exception $e) {
            error_log("Erro na criptografia: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Descriptografa uma string
     */
    public function decrypt($encryptedData) {
        if (empty($encryptedData)) {
            return '';
        }
        
        try {
            $data = base64_decode($encryptedData);
            $ivLength = openssl_cipher_iv_length($this->algorithm);
            $iv = substr($data, 0, $ivLength);
            $encrypted = substr($data, $ivLength);
            
            $decrypted = openssl_decrypt($encrypted, $this->algorithm, $this->key, 0, $iv);
            if ($decrypted === false) {
                throw new Exception('Falha na descriptografia');
            }
            
            return $decrypted;
        } catch (Exception $e) {
            error_log("Erro na descriptografia: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Gera hash seguro para senhas
     */
    public function hashPassword($password, $salt = null) {
        if (!$salt) {
            $salt = bin2hex(random_bytes(32));
        }
        
        $hash = hash_pbkdf2('sha256', $password, $salt, 10000, 64);
        return $salt . ':' . $hash;
    }
    
    /**
     * Verifica se uma senha está correta
     */
    public function verifyPassword($password, $storedHash) {
        $parts = explode(':', $storedHash);
        if (count($parts) !== 2) {
            return false;
        }
        
        $salt = $parts[0];
        $hash = $parts[1];
        
        $newHash = hash_pbkdf2('sha256', $password, $salt, 10000, 64);
        return hash_equals($hash, $newHash);
    }
    
    /**
     * Gera hash para assinatura digital
     */
    public function generateSignatureHash($data) {
        return hash('sha256', $data);
    }
    
    /**
     * Verifica integridade de um arquivo
     */
    public function verifyFileIntegrity($filePath, $expectedHash) {
        if (!file_exists($filePath)) {
            return false;
        }
        
        $actualHash = hash_file('sha256', $filePath);
        return hash_equals($expectedHash, $actualHash);
    }
    
    /**
     * Criptografa um arquivo
     */
    public function encryptFile($sourcePath, $destPath) {
        try {
            if (!file_exists($sourcePath)) {
                throw new Exception('Arquivo fonte não encontrado');
            }
            
            $sourceData = file_get_contents($sourcePath);
            $encryptedData = $this->encrypt($sourceData);
            
            if ($encryptedData === false) {
                throw new Exception('Falha na criptografia do arquivo');
            }
            
            if (file_put_contents($destPath, $encryptedData) === false) {
                throw new Exception('Falha ao salvar arquivo criptografado');
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Erro na criptografia de arquivo: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Descriptografa um arquivo
     */
    public function decryptFile($sourcePath, $destPath) {
        try {
            if (!file_exists($sourcePath)) {
                throw new Exception('Arquivo criptografado não encontrado');
            }
            
            $encryptedData = file_get_contents($sourcePath);
            $decryptedData = $this->decrypt($encryptedData);
            
            if ($decryptedData === false) {
                throw new Exception('Falha na descriptografia do arquivo');
            }
            
            if (file_put_contents($destPath, $decryptedData) === false) {
                throw new Exception('Falha ao salvar arquivo descriptografado');
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Erro na descriptografia de arquivo: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Gera chave de criptografia aleatória
     */
    public static function generateRandomKey($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Gera salt aleatório
     */
    public static function generateSalt($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Criptografa dados sensíveis da empresa
     */
    public function encryptCompanyData($data) {
        if (is_array($data)) {
            $encrypted = [];
            foreach ($data as $key => $value) {
                if (in_array($key, ['senha_certificado', 'chave_criptografia', 'csc_producao', 'csc_homologacao'])) {
                    $encrypted[$key] = $this->encrypt($value);
                } else {
                    $encrypted[$key] = $value;
                }
            }
            return $encrypted;
        }
        
        return $data;
    }
    
    /**
     * Descriptografa dados sensíveis da empresa
     */
    public function decryptCompanyData($data) {
        if (is_array($data)) {
            $decrypted = [];
            foreach ($data as $key => $value) {
                if (in_array($key, ['senha_certificado', 'chave_criptografia', 'csc_producao', 'csc_homologacao'])) {
                    $decrypted[$key] = $this->decrypt($value);
                } else {
                    $decrypted[$key] = $value;
                }
            }
            return $decrypted;
        }
        
        return $data;
    }
}
