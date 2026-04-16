<?php
/**
 * Configuração do WSDenatran (consulta de infrações/multas no DETRAN).
 * O uso do serviço exige cadastro de certificado junto ao Denatran/SERPRO.
 * Configure em: Configurações do Sistema > Consulta de Multas (DETRAN).
 *
 * Endpoints:
 * - Desenvolvimento: https://wsdenatrandes-des07116.apps.dev.serpro/
 * - Homologação: https://wsrenavam.hom.denatran.serpro.gov.br/
 * - Produção: https://renavam.denatran.serpro.gov.br/
 */

// Bloquear acesso direto: configurar em pages/configuracoes.php
if (count(get_included_files()) === 1 && php_sapi_name() !== 'cli') {
    require_once __DIR__ . '/sf_paths.php';
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Acesso restrito</title></head><body>';
    echo '<p><strong>Acesso direto não permitido.</strong></p>';
    echo '<p>Para configurar a consulta de multas no DETRAN, acesse: ';
    echo '<a href="' . htmlspecialchars((isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . sf_app_url('pages/configuracoes.php'), ENT_QUOTES, 'UTF-8') . '">Configurações do Sistema</a> ';
    echo 'e use a seção <strong>Consulta de Multas (DETRAN)</strong>.</p>';
    echo '</body></html>';
    exit;
}

// Base URL do WSDenatran (altere conforme ambiente: dev, homolog ou prod)
if (!defined('DENATRAN_BASE_URL')) {
    define('DENATRAN_BASE_URL', 'https://wsdenatrandes-des07116.apps.dev.serpro');
}

// CPF do usuário autorizado a fazer consultas (obrigatório no header x-cpf-usuario)
// Deixe vazio para usar o CPF informado na própria consulta quando aplicável
if (!defined('DENATRAN_CPF_USUARIO')) {
    define('DENATRAN_CPF_USUARIO', '');
}

// Caminho do certificado digital (.pem ou .crt) - necessário para autenticação
// Deixe vazio se ainda não tiver certificado cadastrado
if (!defined('DENATRAN_CERT_PATH')) {
    define('DENATRAN_CERT_PATH', '');
}

// Caminho da chave privada do certificado (.key)
if (!defined('DENATRAN_KEY_PATH')) {
    define('DENATRAN_KEY_PATH', '');
}

// Senha da chave privada (se houver)
if (!defined('DENATRAN_KEY_PASS')) {
    define('DENATRAN_KEY_PASS', '');
}

// Habilitar integração (false = apenas mensagem de que é necessário configurar)
if (!defined('DENATRAN_HABILITADO')) {
    define('DENATRAN_HABILITADO', false);
}
