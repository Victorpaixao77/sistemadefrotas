<?php
/**
 * Ajuda contextual padronizada por página do Sistema de Frotas.
 * Seções: Visão Geral, Funcionalidades, Como Usar, KPIs, Gráficos, Dicas, Legenda (opcional).
 */
declare(strict_types=1);

if (!function_exists('sf_page_help_sections')) {
    function sf_page_help_sections(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $dir = __DIR__ . '/page_help';
        $files = glob($dir . '/*.php') ?: [];
        sort($files);
        $cache = [];
        foreach ($files as $file) {
            $chunk = require $file;
            if (is_array($chunk)) {
                $cache = array_merge($cache, $chunk);
            }
        }
        return $cache;
    }

    function sf_page_help_get(string $key): ?array
    {
        $all = sf_page_help_sections();
        return $all[$key] ?? null;
    }

  /** @param array<int, string|array{text:string,sub?:array<int,string>}> $items */
    function sf_hlp_ul(array $items): string
    {
        $html = '<ul>';
        foreach ($items as $item) {
            if (is_array($item)) {
                $html .= '<li><strong>' . $item['text'] . '</strong>';
                if (!empty($item['sub'])) {
                    $html .= '<ul>';
                    foreach ($item['sub'] as $sub) {
                        $html .= '<li>' . $sub . '</li>';
                    }
                    $html .= '</ul>';
                }
                $html .= '</li>';
            } else {
                $html .= '<li>' . $item . '</li>';
            }
        }
        return $html . '</ul>';
    }

  /** @param array<int, string|array{text:string,sub?:array<int,string>}> $items */
    function sf_hlp_ol(array $items): string
    {
        $html = '<ol>';
        foreach ($items as $item) {
            if (is_array($item)) {
                $html .= '<li><strong>' . $item['text'] . '</strong>';
                if (!empty($item['sub'])) {
                    $html .= '<ul>';
                    foreach ($item['sub'] as $sub) {
                        $html .= '<li>' . $sub . '</li>';
                    }
                    $html .= '</ul>';
                }
                $html .= '</li>';
            } else {
                $html .= '<li>' . $item . '</li>';
            }
        }
        return $html . '</ol>';
    }

  /** @param array<int, array{color:string,label:string,desc:string}> $items */
    function sf_hlp_legenda(array $items): string
    {
        $html = '<ul class="sf-help-legend">';
        foreach ($items as $row) {
            $html .= '<li><span class="sf-help-swatch" style="background:' . htmlspecialchars($row['color']) . ';"></span>'
                . '<strong>' . $row['label'] . ':</strong> ' . $row['desc'] . '</li>';
        }
        return $html . '</ul>';
    }

    function sf_help_render_section(string $title, string $body): string
    {
        if (trim($body) === '') {
            return '';
        }
        return '<div class="help-section sf-help-section"><h3>' . htmlspecialchars($title) . '</h3>' . $body . '</div>';
    }

    function sf_echo_page_help(string $key): void
    {
        $data = sf_page_help_get($key);
        if (!$data) {
            echo '<div class="help-section"><p>Conteúdo de ajuda em elaboração para esta página.</p></div>';
            return;
        }
        $map = [
            'visao_geral' => 'Visão Geral',
            'funcionalidades' => 'Funcionalidades Principais',
            'como_usar' => 'Como Usar',
            'kpis' => 'Indicadores (KPIs)',
            'graficos' => 'Gráficos e Análises',
            'dicas' => 'Dicas',
            'legenda' => 'Legenda de Cores',
        ];
        foreach ($map as $field => $label) {
            if (!empty($data[$field])) {
                echo sf_help_render_section($label, (string) $data[$field]);
            }
        }
    }

    /**
     * Modal completo de ajuda (id padrão sfHelpModal).
     *
     * @param array{modern?:bool,modal_id?:string,title?:string} $opts
     */
    function sf_render_page_help_modal(string $key, array $opts = []): void
    {
        $data = sf_page_help_get($key);
        $title = $opts['title'] ?? ($data['title'] ?? 'Ajuda');
        $modalId = $opts['modal_id'] ?? 'helpModal';
        echo '<div class="modal" id="' . htmlspecialchars($modalId) . '" data-sf-help-key="' . htmlspecialchars($key) . '">';
        echo '<div class="modal-content sf-help-modal-content">';
        echo '<div class="modal-header"><h2><i class="fas fa-question-circle" aria-hidden="true"></i> Ajuda — ' . htmlspecialchars($title) . '</h2>';
        echo '<span class="close-modal" title="Fechar" aria-label="Fechar">&times;</span></div>';
        echo '<div class="modal-body sf-help-modal-body">';
        sf_echo_page_help($key);
        echo '</div>';
        echo '<div class="modal-footer"><button type="button" class="btn-secondary sf-help-close">Fechar</button></div>';
        echo '</div></div>';
    }

    function sf_page_help_css(string $base = '..'): void
    {
        $v = function_exists('sf_asset_v') ? sf_asset_v() : '1';
        $prefix = ($base === '' || $base === '.') ? '' : rtrim($base, '/') . '/';
        echo '<link rel="stylesheet" href="' . htmlspecialchars($prefix) . 'css/sf-page-help.css?v=' . $v . '">' . "\n";
    }

    function sf_page_help_js(string $base = '..'): void
    {
        $v = function_exists('sf_asset_v') ? sf_asset_v() : '1';
        $prefix = ($base === '' || $base === '.') ? '' : rtrim($base, '/') . '/';
        echo '<script src="' . htmlspecialchars($prefix) . 'js/sf_page_help.js?v=' . $v . '"></script>' . "\n";
    }
}
