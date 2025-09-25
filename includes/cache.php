<?php
/**
 * Sistema de Cache para melhorar performance
 * Implementa cache em arquivo para dados frequentemente acessados
 */

class CacheManager {
    private $cache_dir;
    private $default_ttl = 3600; // 1 hora em segundos
    
    public function __construct($cache_dir = '../cache/') {
        $this->cache_dir = $cache_dir;
        
        // Criar diretório de cache se não existir
        if (!is_dir($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }
    }
    
    /**
     * Armazenar dados no cache
     */
    public function set($key, $data, $ttl = null) {
        $ttl = $ttl ?? $this->default_ttl;
        $cache_file = $this->getCacheFile($key);
        
        $cache_data = [
            'data' => $data,
            'expires' => time() + $ttl,
            'created' => time()
        ];
        
        return file_put_contents($cache_file, serialize($cache_data)) !== false;
    }
    
    /**
     * Recuperar dados do cache
     */
    public function get($key) {
        $cache_file = $this->getCacheFile($key);
        
        if (!file_exists($cache_file)) {
            return null;
        }
        
        $cache_data = unserialize(file_get_contents($cache_file));
        
        // Verificar se o cache expirou
        if ($cache_data['expires'] < time()) {
            $this->delete($key);
            return null;
        }
        
        return $cache_data['data'];
    }
    
    /**
     * Verificar se existe cache válido
     */
    public function has($key) {
        $cache_file = $this->getCacheFile($key);
        
        if (!file_exists($cache_file)) {
            return false;
        }
        
        $cache_data = unserialize(file_get_contents($cache_file));
        return $cache_data['expires'] >= time();
    }
    
    /**
     * Deletar cache
     */
    public function delete($key) {
        $cache_file = $this->getCacheFile($key);
        if (file_exists($cache_file)) {
            return unlink($cache_file);
        }
        return true;
    }
    
    /**
     * Limpar todo o cache
     */
    public function clear() {
        $files = glob($this->cache_dir . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
        return true;
    }
    
    /**
     * Obter estatísticas do cache
     */
    public function getStats() {
        $files = glob($this->cache_dir . '*.cache');
        $total_files = count($files);
        $total_size = 0;
        $expired_files = 0;
        
        foreach ($files as $file) {
            $total_size += filesize($file);
            
            $cache_data = unserialize(file_get_contents($file));
            if ($cache_data['expires'] < time()) {
                $expired_files++;
            }
        }
        
        return [
            'total_files' => $total_files,
            'total_size' => $total_size,
            'expired_files' => $expired_files,
            'valid_files' => $total_files - $expired_files
        ];
    }
    
    /**
     * Obter arquivo de cache
     */
    private function getCacheFile($key) {
        return $this->cache_dir . md5($key) . '.cache';
    }
}

// Função helper para usar o cache facilmente
function getCachedData($key, $callback, $ttl = 3600) {
    $cache = new CacheManager();
    
    // Tentar obter do cache primeiro
    $cached_data = $cache->get($key);
    if ($cached_data !== null) {
        return $cached_data;
    }
    
    // Se não estiver no cache, executar callback e armazenar
    $data = $callback();
    $cache->set($key, $data, $ttl);
    
    return $data;
}

// Função para invalidar cache específico
function invalidateCache($key) {
    $cache = new CacheManager();
    return $cache->delete($key);
}

// Função para limpar todo o cache
function clearAllCache() {
    $cache = new CacheManager();
    return $cache->clear();
}
?>
