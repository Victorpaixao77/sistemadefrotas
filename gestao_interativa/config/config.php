<?php
return [
    'app' => [
        'name' => 'Sistema de Gestão de Frotas',
        'version' => '1.0.0',
        'debug' => true,
        'timezone' => 'America/Sao_Paulo',
        'locale' => 'pt_BR',
        'url' => 'http://localhost/sistema-frotas'
    ],
    
    'auth' => [
        'session_name' => 'sistema_frotas_session',
        'session_lifetime' => 7200, // 2 horas
        'remember_lifetime' => 604800, // 7 dias
        'password_hash_algo' => PASSWORD_BCRYPT,
        'password_hash_options' => [
            'cost' => 12
        ]
    ],
    
    'pagination' => [
        'per_page' => 10,
        'max_pages' => 5
    ],
    
    'upload' => [
        'max_size' => 5242880, // 5MB
        'allowed_types' => [
            'image/jpeg',
            'image/png',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ],
        'path' => '../uploads'
    ],
    
    'notifications' => [
        'email' => [
            'enabled' => true,
            'from' => 'noreply@sistema-frotas.com',
            'from_name' => 'Sistema de Gestão de Frotas'
        ],
        'sms' => [
            'enabled' => false
        ]
    ],
    
    'maintenance' => [
        'pneus' => [
            'dias_aviso_manutencao' => 30,
            'km_aviso_revisao' => 1000
        ],
        'veiculos' => [
            'dias_aviso_documentacao' => 30,
            'km_aviso_revisao' => 1000
        ]
    ],
    
    'logging' => [
        'enabled' => true,
        'path' => '../logs',
        'level' => 'debug', // debug, info, warning, error, critical
        'max_files' => 5,
        'max_size' => 5242880 // 5MB
    ]
]; 