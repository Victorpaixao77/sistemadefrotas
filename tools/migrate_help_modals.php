<?php
/**
 * Substitui modais de ajuda inline pelo bundle unificado (helpModal + sf-help-modal-content).
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/sf_page_help.php';

$map = [
    'pages/vehicles.php' => 'vehicles',
    'pages/motorists.php' => 'motorists',
    'pages/fornecedores_moderno.php' => 'fornecedores',
    'pages/routes.php' => 'routes',
    'pages/abastecimentos.php' => 'abastecimentos',
    'pages/manutencoes.php' => 'manutencoes',
    'pages/contas_pagar.php' => 'contas_pagar',
    'pages/despesas_fixas.php' => 'despesas_fixas',
    'pages/financiamento.php' => 'financiamento',
    'pages/multas.php' => 'multas',
    'pages/comissoes.php' => 'comissoes',
    'pages/lucratividade.php' => 'lucratividade',
    'pages/gestao_motoristas.php' => 'gestao_motoristas',
];

$bundleLine = function (string $key): string {
    return "    <?php \$sf_help_key = '$key'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>\n";
};

foreach ($map as $rel => $key) {
    $path = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (!is_file($path)) {
        echo "SKIP missing: $rel\n";
        continue;
    }
    $content = file_get_contents($path);
    if ($content === false) {
        continue;
    }
    $original = $content;

    // Já migrado (só bundle, sem modal-body manual)
    if (preg_match('/sf_page_help_bundle\.php.*\n.*sf_help_key = \'' . preg_quote($key, '/') . '\'/', $content)
        || preg_match('/\$sf_help_key = \'' . preg_quote($key, '/') . '\';\s*include.*sf_page_help_bundle/', $content)) {
        if (!preg_match('/sf_echo_page_help\(\'' . preg_quote($key, '/') . '\'\)/', $content)) {
            echo "OK already: $rel\n";
            continue;
        }
    }

  // Remove bloco modal que contém sf_echo_page_help para esta key
    $pattern = '/\s*<!--[^>]*(?:Modal|Help)[^>]*Ajuda[^>]*-->\s*'
        . '<div[^>]*id="help[^"]*"[^>]*>.*?'
        . '<\?php sf_echo_page_help\(\'' . preg_quote($key, '/') . '\'\); \?>.*?'
        . '<\/div>\s*<\/div>\s*<\/div>/s';

    $pattern2 = '/\s*<!--[^>]*(?:Modal|Help)[^>]*-->\s*'
        . '<div[^>]*class="modal[^"]*"[^>]*id="help[^"]*"[^>]*>.*?'
        . '<\?php sf_echo_page_help\(\'' . preg_quote($key, '/') . '\'\); \?>.*?'
        . '<\/div>\s*<\/div>\s*<\/div>/s';

    $replaced = preg_replace($pattern, "\n" . $bundleLine($key), $content, 1, $c1);
    if ($c1 < 1) {
        $replaced = preg_replace($pattern2, "\n" . $bundleLine($key), $content, 1, $c2);
    } else {
        $c2 = $c1;
    }

    if ($c2 < 1) {
        echo "WARN no match: $rel\n";
        continue;
    }

    // Garantir CSS/JS de ajuda
    if (strpos($replaced, 'sf-page-help.css') === false && preg_match('/<\/head>/', $replaced)) {
        $replaced = preg_replace('/<\/head>/', "    <?php sf_page_help_css('..'); ?>\n</head>", $replaced, 1);
    }
    if (strpos($replaced, 'sf_page_help.js') === false && preg_match('/<\/body>/', $replaced)) {
        $replaced = preg_replace('/<\/body>/', "    <?php sf_page_help_js('..'); ?>\n</body>", $replaced, 1);
    }

    if (!strpos($replaced, 'sf_page_help.php') && preg_match("/require_once.*config\.php/", $replaced)) {
        $replaced = preg_replace(
            "/(require_once\s+['\"]\.\.\/includes\/config\.php['\"];)/",
            "$1\nrequire_once __DIR__ . '/../includes/sf_page_help.php';",
            $replaced,
            1
        );
    }

    file_put_contents($path, $replaced);
    echo "Migrated: $rel\n";
}

echo "Done.\n";
