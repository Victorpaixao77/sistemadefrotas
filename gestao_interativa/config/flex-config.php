<?php
/**
 * Configuração do Modo Flexível
 * Configurações específicas para as funcionalidades do modo flexível
 */

// Configurações gerais
define('FLEX_MODE_ENABLED', true);
define('FLEX_AUTO_REFRESH', true);
define('FLEX_REFRESH_INTERVAL', 30000); // 30 segundos
define('FLEX_ENABLE_NOTIFICATIONS', true);
define('FLEX_ENABLE_SHORTCUTS', true);
define('FLEX_ENABLE_CONTEXT_MENU', true);
define('FLEX_ENABLE_DRAG_DROP', true);
define('FLEX_ENABLE_TOOLTIPS', true);

// Configurações de API
define('FLEX_API_TIMEOUT', 10); // segundos
define('FLEX_API_RETRY_ATTEMPTS', 3);
define('FLEX_API_CACHE_DURATION', 300); // 5 minutos

// Configurações de estatísticas
define('FLEX_CRITICAL_DEPTH', 1.6); // mm
define('FLEX_ALERT_DEPTH', 2.5); // mm
define('FLEX_MIN_PRESSURE', 25); // PSI
define('FLEX_MAX_KM', 80000); // km

// Configurações de filtros
define('FLEX_FILTERS_PER_PAGE', 50);
define('FLEX_MAX_FILTER_RESULTS', 1000);
define('FLEX_FILTER_DEBOUNCE', 300); // ms

// Configurações de exportação
define('FLEX_EXPORT_FORMATS', ['xlsx', 'csv', 'pdf']);
define('FLEX_EXPORT_MAX_RECORDS', 10000);

// Configurações de notificações
define('FLEX_NOTIFICATION_DURATION', 5000); // ms
define('FLEX_CRITICAL_NOTIFICATION_SOUND', true);
define('FLEX_ALERT_NOTIFICATION_SOUND', true);

// Configurações de tema
define('FLEX_DEFAULT_THEME', 'light');
define('FLEX_AVAILABLE_THEMES', ['light', 'dark', 'auto']);

// Configurações de performance
define('FLEX_LAZY_LOADING', true);
define('FLEX_VIRTUAL_SCROLLING', true);
define('FLEX_DEBOUNCE_DELAY', 250); // ms

// Configurações de segurança
define('FLEX_CSRF_PROTECTION', true);
define('FLEX_RATE_LIMITING', true);
define('FLEX_MAX_REQUESTS_PER_MINUTE', 60);

// Configurações de logs
define('FLEX_LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR
define('FLEX_LOG_FILE', 'logs/flex_mode.log');
define('FLEX_LOG_MAX_SIZE', 10485760); // 10MB

// Configurações de cache
define('FLEX_CACHE_ENABLED', true);
define('FLEX_CACHE_DURATION', 300); // 5 minutos
define('FLEX_CACHE_DIR', 'cache/flex/');

// Configurações de desenvolvimento
define('FLEX_DEBUG_MODE', false);
define('FLEX_MOCK_DATA', false);
define('FLEX_DEV_TOOLS', false);

// Configurações de integração
define('FLEX_INTEGRATION_WEBHOOKS', []);
define('FLEX_EXTERNAL_APIS', []);
define('FLEX_THIRD_PARTY_SERVICES', []);

/**
 * Classe de configuração do modo flexível
 */
class FlexConfig {
    private static $instance = null;
    private $config = [];
    
    private function __construct() {
        $this->loadConfig();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function loadConfig() {
        $this->config = [
            'general' => [
                'enabled' => FLEX_MODE_ENABLED,
                'auto_refresh' => FLEX_AUTO_REFRESH,
                'refresh_interval' => FLEX_REFRESH_INTERVAL,
                'enable_notifications' => FLEX_ENABLE_NOTIFICATIONS,
                'enable_shortcuts' => FLEX_ENABLE_SHORTCUTS,
                'enable_context_menu' => FLEX_ENABLE_CONTEXT_MENU,
                'enable_drag_drop' => FLEX_ENABLE_DRAG_DROP,
                'enable_tooltips' => FLEX_ENABLE_TOOLTIPS
            ],
            'api' => [
                'timeout' => FLEX_API_TIMEOUT,
                'retry_attempts' => FLEX_API_RETRY_ATTEMPTS,
                'cache_duration' => FLEX_API_CACHE_DURATION
            ],
            'statistics' => [
                'critical_depth' => FLEX_CRITICAL_DEPTH,
                'alert_depth' => FLEX_ALERT_DEPTH,
                'min_pressure' => FLEX_MIN_PRESSURE,
                'max_km' => FLEX_MAX_KM
            ],
            'filters' => [
                'per_page' => FLEX_FILTERS_PER_PAGE,
                'max_results' => FLEX_MAX_FILTER_RESULTS,
                'debounce' => FLEX_FILTER_DEBOUNCE
            ],
            'export' => [
                'formats' => FLEX_EXPORT_FORMATS,
                'max_records' => FLEX_EXPORT_MAX_RECORDS
            ],
            'notifications' => [
                'duration' => FLEX_NOTIFICATION_DURATION,
                'critical_sound' => FLEX_CRITICAL_NOTIFICATION_SOUND,
                'alert_sound' => FLEX_ALERT_NOTIFICATION_SOUND
            ],
            'theme' => [
                'default' => FLEX_DEFAULT_THEME,
                'available' => FLEX_AVAILABLE_THEMES
            ],
            'performance' => [
                'lazy_loading' => FLEX_LAZY_LOADING,
                'virtual_scrolling' => FLEX_VIRTUAL_SCROLLING,
                'debounce_delay' => FLEX_DEBOUNCE_DELAY
            ],
            'security' => [
                'csrf_protection' => FLEX_CSRF_PROTECTION,
                'rate_limiting' => FLEX_RATE_LIMITING,
                'max_requests_per_minute' => FLEX_MAX_REQUESTS_PER_MINUTE
            ],
            'logs' => [
                'level' => FLEX_LOG_LEVEL,
                'file' => FLEX_LOG_FILE,
                'max_size' => FLEX_LOG_MAX_SIZE
            ],
            'cache' => [
                'enabled' => FLEX_CACHE_ENABLED,
                'duration' => FLEX_CACHE_DURATION,
                'dir' => FLEX_CACHE_DIR
            ],
            'development' => [
                'debug_mode' => FLEX_DEBUG_MODE,
                'mock_data' => FLEX_MOCK_DATA,
                'dev_tools' => FLEX_DEV_TOOLS
            ],
            'integration' => [
                'webhooks' => FLEX_INTEGRATION_WEBHOOKS,
                'external_apis' => FLEX_EXTERNAL_APIS,
                'third_party_services' => FLEX_THIRD_PARTY_SERVICES
            ]
        ];
    }
    
    public function get($key, $default = null) {
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }
        
        return $value;
    }
    
    public function set($key, $value) {
        $keys = explode('.', $key);
        $config = &$this->config;
        
        foreach ($keys as $k) {
            if (!isset($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }
        
        $config = $value;
    }
    
    public function getAll() {
        return $this->config;
    }
    
    public function isEnabled($feature) {
        return $this->get("general.enable_$feature", false);
    }
    
    public function getTheme() {
        return $_COOKIE['flex_theme'] ?? $this->get('theme.default', 'light');
    }
    
    public function setTheme($theme) {
        if (in_array($theme, $this->get('theme.available', []))) {
            setcookie('flex_theme', $theme, time() + (86400 * 30), '/');
            return true;
        }
        return false;
    }
    
    public function isDebugMode() {
        return $this->get('development.debug_mode', false);
    }
    
    public function useMockData() {
        return $this->get('development.mock_data', false);
    }
    
    public function log($message, $level = 'INFO') {
        if (!$this->get('logs.file')) {
            return;
        }
        
        $logFile = $this->get('logs.file');
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    public function getJavaScriptConfig() {
        return [
            'autoRefresh' => $this->get('general.auto_refresh'),
            'refreshInterval' => $this->get('general.refresh_interval'),
            'enableNotifications' => $this->get('general.enable_notifications'),
            'enableShortcuts' => $this->get('general.enable_shortcuts'),
            'enableContextMenu' => $this->get('general.enable_context_menu'),
            'enableDragDrop' => $this->get('general.enable_drag_drop'),
            'enableTooltips' => $this->get('general.enable_tooltips'),
            'criticalDepth' => $this->get('statistics.critical_depth'),
            'alertDepth' => $this->get('statistics.alert_depth'),
            'minPressure' => $this->get('statistics.min_pressure'),
            'maxKm' => $this->get('statistics.max_km'),
            'filtersPerPage' => $this->get('filters.per_page'),
            'filterDebounce' => $this->get('filters.debounce'),
            'notificationDuration' => $this->get('notifications.duration'),
            'theme' => $this->getTheme(),
            'debugMode' => $this->isDebugMode(),
            'mockData' => $this->useMockData()
        ];
    }
}

// Função helper para acessar configurações
function flex_config($key = null, $default = null) {
    $config = FlexConfig::getInstance();
    
    if ($key === null) {
        return $config->getAll();
    }
    
    return $config->get($key, $default);
}

// Função helper para logging
function flex_log($message, $level = 'INFO') {
    FlexConfig::getInstance()->log($message, $level);
}

// Função helper para verificar se feature está habilitada
function flex_enabled($feature) {
    return FlexConfig::getInstance()->isEnabled($feature);
}

// Inicializar configuração
$flexConfig = FlexConfig::getInstance();

// Log de inicialização
if ($flexConfig->isDebugMode()) {
    flex_log('Modo Flexível inicializado', 'DEBUG');
}
?> 