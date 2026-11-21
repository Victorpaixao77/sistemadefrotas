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

    // Catálogo de combustíveis — quase estático. Cacheado (APCu/arquivo) por 1h.
    require_once __DIR__ . '/../includes/sf_cache.php';
    $combustiveis = sf_cache_remember('catalogo:tipos_combustivel', 3600, function () use ($conn) {
        $list = $conn->query("SELECT id, nome FROM tipos_combustivel ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
        if (empty($list)) {
            // Semeia os tipos padrão na primeira execução.
            $ins = $conn->prepare("INSERT INTO tipos_combustivel (nome) VALUES (:nome)");
            foreach (['Gasolina', 'Diesel', 'Etanol', 'GNV', 'Elétrico'] as $nome) {
                $ins->execute([':nome' => $nome]);
            }
            $list = $conn->query("SELECT id, nome FROM tipos_combustivel ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
        }
        return $list;
    });

    echo json_encode(['success' => true, 'data' => $combustiveis]);

} catch (PDOException $e) {
    error_log("Error in combustiveis.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao buscar tipos de combustível.']);
} catch (Exception $e) {
    error_log("General error in combustiveis.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno do servidor']);
} 
