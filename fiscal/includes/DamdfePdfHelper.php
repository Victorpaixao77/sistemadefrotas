<?php
/**
 * DAMDFE resumido (mPDF) quando sped-da não está disponível.
 *
 * @return array{html:string,numero:string}
 */
function montarDamdfeResumoHtml(string $xml_content, array $mdfe_row = [], ?string $logoPath = null): array
{
    $ns = 'http://www.portalfiscal.inf.br/mdfe';
    $xml = @simplexml_load_string($xml_content);
    $inf = null;
    if ($xml) {
        if (isset($xml->MDFe->infMDFe)) {
            $inf = $xml->MDFe->infMDFe;
        } elseif (isset($xml->children($ns)->MDFe->infMDFe)) {
            $inf = $xml->children($ns)->MDFe->infMDFe;
        } elseif (isset($xml->infMDFe)) {
            $inf = $xml->infMDFe;
        }
    }

    $numero = (string) ($mdfe_row['numero_mdfe'] ?? '');
    $serie = (string) ($mdfe_row['serie_mdfe'] ?? '1');
    $chave = preg_replace('/\D/', '', (string) ($mdfe_row['chave_acesso'] ?? ''));
    $protocolo = (string) ($mdfe_row['protocolo_autorizacao'] ?? '');
    $ufIni = (string) ($mdfe_row['uf_inicio'] ?? '');
    $ufFim = (string) ($mdfe_row['uf_fim'] ?? '');
    $placa = '';
    $condutor = '';
    $qCte = (string) ($mdfe_row['total_cte'] ?? '0');

    if ($inf) {
        $ide = $inf->ide ?? $inf->children($ns)->ide ?? null;
        if ($ide) {
            $numero = trim((string) ($ide->nMDF ?? '')) ?: $numero;
            $serie = trim((string) ($ide->serie ?? '')) ?: $serie;
            $ufIni = trim((string) ($ide->UFIni ?? '')) ?: $ufIni;
            $ufFim = trim((string) ($ide->UFFim ?? '')) ?: $ufFim;
        }
        $rodo = $inf->infModal->rodo ?? $inf->children($ns)->infModal->rodo ?? null;
        if ($rodo) {
            $veic = $rodo->veicTracao ?? $rodo->children($ns)->veicTracao ?? null;
            if ($veic) {
                $placa = trim((string) ($veic->placa ?? ''));
            }
            $cond = $rodo->condutor ?? $rodo->children($ns)->condutor ?? null;
            if ($cond) {
                $condutor = trim((string) ($cond->xNome ?? ''));
            }
        }
        $tot = $inf->tot ?? $inf->children($ns)->tot ?? null;
        if ($tot && isset($tot->qCTe)) {
            $qCte = (string) $tot->qCTe;
        }
    }

    $dataEmi = !empty($mdfe_row['data_emissao']) ? date('d/m/Y', strtotime((string) $mdfe_row['data_emissao'])) : date('d/m/Y');
    $chaveFmt = $chave !== '' ? chunk_split($chave, 4, ' ') : '-';

    $html = '<style>
        body{font-family:DejaVu Sans,sans-serif;font-size:10pt;color:#111}
        h1{font-size:14pt;margin:0 0 8px}
        table{border-collapse:collapse;width:100%;margin-top:10px}
        td,th{border:1px solid #333;padding:6px;text-align:left}
        th{background:#eee}
        .muted{color:#555;font-size:9pt}
    </style>';
    if ($logoPath !== null && $logoPath !== '' && is_file($logoPath)) {
        $html .= '<div style="margin-bottom:10px"><img src="' . htmlspecialchars($logoPath) . '" style="max-height:60px" alt="Logo"></div>';
    }
    $html .= '<h1>DAMDFE — Documento Auxiliar do Manifesto Eletrônico</h1>';
    $html .= '<p class="muted">Resumo gerado pelo sistema (layout simplificado). Para DAMDFE oficial, execute composer install (sped-da).</p>';
    $html .= '<table><tr><th>Campo</th><th>Valor</th></tr>';
    $html .= '<tr><td>Número / Série</td><td>' . htmlspecialchars($numero . ' / ' . $serie) . '</td></tr>';
    $html .= '<tr><td>Data emissão</td><td>' . htmlspecialchars($dataEmi) . '</td></tr>';
    $html .= '<tr><td>UF início → fim</td><td>' . htmlspecialchars($ufIni . ' → ' . $ufFim) . '</td></tr>';
    $html .= '<tr><td>Placa</td><td>' . htmlspecialchars($placa !== '' ? $placa : '-') . '</td></tr>';
    $html .= '<tr><td>Condutor</td><td>' . htmlspecialchars($condutor !== '' ? $condutor : '-') . '</td></tr>';
    $html .= '<tr><td>Qtd. CT-e</td><td>' . htmlspecialchars($qCte) . '</td></tr>';
    $html .= '<tr><td>Protocolo</td><td>' . htmlspecialchars($protocolo !== '' ? $protocolo : '-') . '</td></tr>';
    $html .= '<tr><td>Chave de acesso</td><td style="font-size:8pt">' . htmlspecialchars(trim($chaveFmt)) . '</td></tr>';
    $html .= '</table>';

    return ['html' => $html, 'numero' => $numero !== '' ? $numero : (string) ($mdfe_row['id'] ?? '')];
}
