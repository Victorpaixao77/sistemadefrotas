<?php
/**
 * IA híbrida — orquestra banco → docs RAG → ajuda sistema → fallback.
 */
declare(strict_types=1);

require_once __DIR__ . '/sf_ia_help.php';

/**
 * Tenta responder pergunta não classificada (intent ajuda) em camadas.
 * @return array{answer:string,rows:array,fontes:array,camadas:array,intent_sugerido:?string}
 */
function sf_ia_hybrid_resolve(PDO $conn, int $empresaId, string $pergunta): array
{
    $camadas = [];
    $rows = [];
    $fontes = [];

    // Camada 1: documentos enviados pela empresa (RAG)
    if (function_exists('sf_ia_rag_search')) {
        $chunks = sf_ia_rag_search($conn, $empresaId, $pergunta, 4);
        if ($chunks !== []) {
            $camadas[] = 'documentos_empresa';
            $fontes[] = 'Documentos enviados';
            $lines = ['Encontrei nos seus documentos:'];
            foreach ($chunks as $i => $c) {
                $snippet = mb_substr((string) ($c['chunk_text'] ?? ''), 0, 200);
                $lines[] = ($i + 1) . '. ' . ($c['nome_arquivo'] ?? 'arquivo') . ' — ' . $snippet . '…';
                $rows[] = ['Fonte' => $c['nome_arquivo'] ?? '—', 'Trecho' => $snippet . '…'];
            }
            return [
                'answer' => implode("\n", $lines),
                'rows' => $rows,
                'fontes' => $fontes,
                'camadas' => $camadas,
                'intent_sugerido' => 'documentos',
            ];
        }
    }

    // Camada 2: documentação interna do sistema
    $help = sf_ia_help_answer($pergunta);
    if (($help['rows'] ?? []) !== []) {
        $camadas[] = 'docs_sistema';
        $fontes[] = 'Documentação';
        return [
            'answer' => $help['answer'],
            'rows' => $help['rows'],
            'fontes' => $fontes,
            'camadas' => $camadas,
            'intent_sugerido' => 'ajuda_sistema',
        ];
    }

    // Camada 3: tópicos rápidos
    foreach (sf_ia_help_topics() as $titulo => $query) {
        if (mb_stripos($pergunta, explode(' ', $titulo)[0]) !== false) {
            $h2 = sf_ia_help_answer($query);
            if (($h2['rows'] ?? []) !== []) {
                $camadas[] = 'topico_ajuda';
                return [
                    'answer' => $h2['answer'],
                    'rows' => $h2['rows'],
                    'fontes' => ['Manual do sistema'],
                    'camadas' => $camadas,
                    'intent_sugerido' => 'ajuda_sistema',
                ];
            }
        }
    }

    return [
        'answer' => '',
        'rows' => [],
        'fontes' => [],
        'camadas' => [],
        'intent_sugerido' => null,
    ];
}
