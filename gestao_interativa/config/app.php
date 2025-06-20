<?php
return [
    'name' => 'Sistema de Gestão de Frotas',
    'version' => '1.0.0',
    'debug' => true,
    'timezone' => 'America/Sao_Paulo',
    'locale' => 'pt_BR',
    'url' => 'http://localhost/sistema-frotas',
    'key' => 'base64:'.base64_encode(random_bytes(32)),
    'cipher' => 'AES-256-CBC',
    'session' => [
        'driver' => 'file',
        'lifetime' => 120,
        'expire_on_close' => false,
        'encrypt' => false,
        'files' => __DIR__ . '/../storage/sessions',
        'connection' => null,
        'table' => 'sessions',
        'lottery' => [2, 100],
        'cookie' => 'sistema_frotas_session',
        'path' => '/',
        'domain' => null,
        'secure' => false,
        'http_only' => true,
        'same_site' => 'lax',
    ],
    'logging' => [
        'enabled' => true,
        'path' => __DIR__ . '/../logs',
        'level' => 'debug',
    ],
    'cache' => [
        'driver' => 'file',
        'path' => __DIR__ . '/../storage/cache',
        'lifetime' => 60,
    ],
    'security' => [
        'password_hash_algo' => PASSWORD_BCRYPT,
        'password_hash_options' => [
            'cost' => 12,
        ],
        'token_lifetime' => 3600,
    ],
    'pagination' => [
        'per_page' => 10,
        'max_pages' => 5,
    ],
    'upload' => [
        'max_size' => 5242880, // 5MB
        'allowed_types' => ['jpg', 'jpeg', 'png', 'pdf'],
        'path' => __DIR__ . '/../uploads',
    ],
    'mail' => [
        'driver' => 'smtp',
        'host' => 'smtp.mailtrap.io',
        'port' => 2525,
        'username' => null,
        'password' => null,
        'encryption' => 'tls',
        'from' => [
            'address' => 'noreply@sistema-frotas.com',
            'name' => 'Sistema de Gestão de Frotas',
        ],
    ],
    'notifications' => [
        'channels' => ['mail', 'database'],
        'default_channel' => 'database',
    ],
    'api' => [
        'rate_limit' => 60,
        'rate_limit_window' => 60,
        'token_lifetime' => 3600,
    ],
]; 