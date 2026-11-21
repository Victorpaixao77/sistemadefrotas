<?php
/**
 * IA conhece o sistema — RAG em documentação interna.
 */
declare(strict_types=1);

function sf_ia_help_search(string $pergunta, int $limit = 5): array
{
    $q = mb_strtolower(trim($pergunta), 'UTF-8');
    if ($q === '') {
        return [];
    }
    $terms = array_values(array_filter(preg_split('/\s+/u', $q) ?: [], static fn($t) => mb_strlen($t) >= 3));
    if ($terms === []) {
        return [];
    }

    $docsDir = dirname(__DIR__) . '/docs';
    $files = array_merge(
        glob($docsDir . '/*.md') ?: [],
        glob($docsDir . '/14-telas/*.md') ?: [],
        [dirname(__DIR__) . '/CLAUDE.md']
    );

    $hits = [];
    foreach ($files as $path) {
        if (!is_readable($path)) {
            continue;
        }
        $content = file_get_contents($path);
        if ($content === false) {
            continue;
        }
        $lower = mb_strtolower($content, 'UTF-8');
        $score = 0;
        foreach ($terms as $t) {
            if (strpos($lower, $t) !== false) {
                $score++;
            }
        }
        if ($score < 1) {
            continue;
        }
        $snippet = sf_ia_help_snippet($content, $terms[0]);
        $hits[] = [
            'arquivo' => basename($path),
            'path' => str_replace(dirname(__DIR__) . DIRECTORY_SEPARATOR, '', $path),
            'score' => $score,
            'trecho' => $snippet,
        ];
    }

    usort($hits, static fn($a, $b) => $b['score'] <=> $a['score']);
    return array_slice($hits, 0, $limit);
}

function sf_ia_help_snippet(string $content, string $term, int $len = 280): string
{
    $pos = mb_stripos($content, $term);
    if ($pos === false) {
        return mb_substr(trim($content), 0, $len) . '…';
    }
    $start = max(0, $pos - 80);
    return '…' . trim(mb_substr($content, $start, $len)) . '…';
}

function sf_ia_help_answer(string $pergunta): array
{
    $hits = sf_ia_help_search($pergunta, 4);
    if ($hits === []) {
        return [
            'answer' => 'Não encontrei na documentação interna. Consulte o menu de ajuda (?) da tela ou o manual em docs/.',
            'rows' => [],
        ];
    }
    $lines = ['Encontrei na documentação do sistema:'];
    $rows = [];
    foreach ($hits as $i => $h) {
        $lines[] = ($i + 1) . '. **' . $h['arquivo'] . '** — ' . $h['trecho'];
        $rows[] = ['Documento' => $h['arquivo'], 'Trecho' => $h['trecho']];
    }
    return [
        'answer' => implode("\n", $lines),
        'rows' => $rows,
        'fontes_doc' => array_column($hits, 'path'),
    ];
}

/** Tópicos rápidos de ajuda. */
function sf_ia_help_topics(): array
{
    return [
        'Como cadastrar motorista?' => 'motorista cadastro pages',
        'Como emitir MDF-e?' => 'fiscal MDF-e',
        'Como gerar CIOT?' => 'CIOT',
        'Onde altero placa do veículo?' => 'veículo placa',
        'Como funciona o Centro de Inteligência?' => 'assistente_ia copiloto',
    ];
}
