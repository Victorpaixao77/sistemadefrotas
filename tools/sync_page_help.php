<?php
/**
 * Sincroniza modais de ajuda das páginas com includes/page_help/*.php
 * Uso: php tools/sync_page_help.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/sf_page_help.php';

$map = [
    'pages/vehicles.php' => ['key' => 'vehicles', 'modal_id' => 'helpVehiclesModal'],
    'pages/motorists.php' => ['key' => 'motorists', 'modal_id' => 'helpMotoristsModal'],
    'pages/fornecedores_moderno.php' => ['key' => 'fornecedores', 'modal_id' => 'helpFornecedorModal'],
    'pages/routes.php' => ['key' => 'routes', 'modal_id' => 'helpRouteModal'],
    'pages/abastecimentos.php' => ['key' => 'abastecimentos', 'modal_id' => 'helpModal'],
    'pages/manutencoes.php' => ['key' => 'manutencoes', 'modal_id' => 'helpMaintenanceModal'],
    'pages/contas_pagar.php' => ['key' => 'contas_pagar', 'modal_id' => 'helpModal'],
    'pages/despesas_fixas.php' => ['key' => 'despesas_fixas', 'modal_id' => 'helpModal'],
    'pages/financiamento.php' => ['key' => 'financiamento', 'modal_id' => 'helpModal'],
    'pages/multas.php' => ['key' => 'multas', 'modal_id' => 'helpModal'],
    'pages/comissoes.php' => ['key' => 'comissoes', 'modal_id' => 'helpModal'],
    'pages/lucratividade.php' => ['key' => 'lucratividade', 'modal_id' => 'helpLucratividadeModal'],
    'pages/pneus.php' => ['key' => 'pneus', 'modal_id' => 'helpPneusModal'],
    'pages/estoque_pneus.php' => ['key' => 'estoque_pneus', 'modal_id' => 'helpModal'],
    'pages/gestao_interativa.php' => ['key' => 'gestao_interativa', 'modal_id' => 'helpModal'],
    'pages/manutencao_pneus.php' => ['key' => 'manutencao_pneus', 'modal_id' => 'helpModal'],
    'pages/gestao_motoristas.php' => ['key' => 'gestao_motoristas', 'modal_id' => 'helpModal'],
];

$requireSnippet = "require_once __DIR__ . '/../includes/sf_page_help.php';";
$cssSnippet = "<?php sf_page_help_css('..'); ?>";
$jsSnippet = "<?php sf_page_help_js('..'); ?>";

foreach ($map as $rel => $cfg) {
    $path = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (!is_file($path)) {
        echo "SKIP (missing): $rel\n";
        continue;
    }
    $content = file_get_contents($path);
    if ($content === false) {
        echo "ERR read: $rel\n";
        continue;
    }

    if (strpos($content, 'sf_page_help.php') === false) {
        if (preg_match("/require_once\s+['\"]\.\.\/includes\/config\.php['\"];/", $content)) {
            $content = preg_replace(
                "/(require_once\s+['\"]\.\.\/includes\/config\.php['\"];)/",
                "$1\n" . $requireSnippet,
                $content,
                1
            );
        }
    }

    $modalId = preg_quote($cfg['modal_id'], '/');
    $key = $cfg['key'];
    $replacement = '<div class="modal-body sf-help-modal-body">' . "\n"
        . "                <?php sf_echo_page_help('$key'); ?>\n"
        . '            </div>';

    $pattern = '/<div class="modal-body[^"]*">.*?<\/div>\s*(?=<div class="modal-footer">)/s';
    $newContent = preg_replace_callback(
        '/(<div class="modal[^"]*" id="' . $modalId . '"[^>]*>.*?<div class="modal-header">.*?<\/div>\s*)<div class="modal-body[^"]*">.*?<\/div>/s',
        function ($m) use ($key) {
            return $m[1] . '<div class="modal-body sf-help-modal-body">' . "\n"
                . "                <?php sf_echo_page_help('$key'); ?>\n"
                . '            </div>';
        },
        $content,
        1,
        $count
    );

    if ($count < 1) {
        echo "WARN no modal-body replaced: $rel\n";
        continue;
    }
    $content = $newContent;

    if (strpos($content, 'sf-page-help.css') === false && preg_match('/<\/head>/', $content)) {
        $content = preg_replace('/<\/head>/', "    $cssSnippet\n</head>", $content, 1);
    }

    if (strpos($content, 'sf_page_help.js') === false && preg_match('/<\/body>/', $content)) {
        $content = preg_replace('/<\/body>/', "    $jsSnippet\n</body>", $content, 1);
    }

    file_put_contents($path, $content);
    echo "OK: $rel\n";
}

echo "Done.\n";
