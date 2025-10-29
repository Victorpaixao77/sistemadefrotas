<?php
/**
 * SISTEMA DE CACHE SIMPLES
 * Armazena resultados de consultas frequentes por 5 minutos
 */

class SistemaCache {
    private $cacheDir;
    private $ttl;
    
    /**
     * Construtor
     * @param int $ttl Tempo de vida do cache em segundos (padrão: 300 = 5 minutos)
     */
    public function __construct($ttl = 300) {
        $this->cacheDir = __DIR__;
        $this->ttl = $ttl;
        
        // Criar diretório de cache se não existir
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * Obter valor do cache
     * @param string $key Chave do cache
     * @return mixed|null Retorna o valor ou null se expirado/não existe
     */
    public function get($key) {
        $file = $this->getCacheFile($key);
        
        if (!file_exists($file)) {
            return null;
        }
        
        $data = json_decode(file_get_contents($file), true);
        
        // Verificar se expirou
        if (time() - $data['timestamp'] > $this->ttl) {
            unlink($file);
            return null;
        }
        
        return $data['value'];
    }
    
    /**
     * Salvar valor no cache
     * @param string $key Chave do cache
     * @param mixed $value Valor a ser armazenado
     */
    public function set($key, $value) {
        $file = $this->getCacheFile($key);
        
        $data = [
            'timestamp' => time(),
            'key' => $key,
            'value' => $value
        ];
        
        file_put_contents($file, json_encode($data));
    }
    
    /**
     * Remover item do cache
     * @param string $key Chave do cache
     */
    public function delete($key) {
        $file = $this->getCacheFile($key);
        if (file_exists($file)) {
            unlink($file);
        }
    }
    
    /**
     * Limpar todo o cache
     */
    public function clear() {
        $files = glob($this->cacheDir . '/*.cache');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
    
    /**
     * Limpar cache expirado
     */
    public function clearExpired() {
        $files = glob($this->cacheDir . '/*.cache');
        $limpos = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $data = json_decode(file_get_contents($file), true);
                if (time() - $data['timestamp'] > $this->ttl) {
                    unlink($file);
                    $limpos++;
                }
            }
        }
        
        return $limpos;
    }
    
    /**
     * Obter estatísticas do cache
     */
    public function getStats() {
        $files = glob($this->cacheDir . '/*.cache');
        $total = count($files);
        $tamanho = 0;
        $validos = 0;
        $expirados = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $tamanho += filesize($file);
                $data = json_decode(file_get_contents($file), true);
                
                if (time() - $data['timestamp'] > $this->ttl) {
                    $expirados++;
                } else {
                    $validos++;
                }
            }
        }
        
        return [
            'total' => $total,
            'validos' => $validos,
            'expirados' => $expirados,
            'tamanho_mb' => round($tamanho / 1024 / 1024, 2),
            'tamanho_kb' => round($tamanho / 1024, 2)
        ];
    }
    
    /**
     * Obter caminho do arquivo de cache
     */
    private function getCacheFile($key) {
        $hash = md5($key);
        return $this->cacheDir . '/' . $hash . '.cache';
    }
}

/**
 * Exemplo de uso:
 * 
 * $cache = new SistemaCache(300); // 5 minutos
 * 
 * $key = "dashboard_stats_empresa_1";
 * $stats = $cache->get($key);
 * 
 * if (!$stats) {
 *     $stats = calcularEstatisticas(); // Query pesada
 *     $cache->set($key, $stats);
 * }
 * 
 * echo json_encode($stats);
 */
?>

