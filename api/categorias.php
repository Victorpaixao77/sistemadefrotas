<?php
// Initialize session and include necessary files
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/api_json.php';

// Configure session before starting it
configure_session();

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON
header('Content-Type: application/json');

// Ensure the request is authenticated
require_authentication();
if (function_exists('session_write_close')) { session_write_close(); }

// Protege mutações (POST/PUT/PATCH/DELETE) com CSRF.
api_require_csrf_json();

try {
    $conn = getConnection();

    // Catálogo de categorias — quase estático. Cacheado (APCu/arquivo) por 1h.
    require_once __DIR__ . '/../includes/sf_cache.php';
    $categorias = sf_cache_remember('catalogo:categorias_veiculos', 3600, function () use ($conn) {
        $list = $conn->query("SELECT id, nome FROM categorias_veiculos ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
        if (empty($list)) {
            $ins = $conn->prepare("INSERT INTO categorias_veiculos (nome) VALUES (:nome)");
            foreach (['Leve', 'Pesado', 'Passeio', 'Escolar', 'Caminhão', 'Utilitário', 'Especial'] as $nome) {
                $ins->execute([':nome' => $nome]);
            }
            $list = $conn->query("SELECT id, nome FROM categorias_veiculos ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
        }
        return $list;
    });

    echo json_encode(['success' => true, 'data' => $categorias]);

} catch (PDOException $e) {
    error_log("Error in categorias.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao buscar categorias: ']);
} catch (Exception $e) {
    error_log("General error in categorias.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno do servidor']);
} 
