<?php
/**
 * Insights de veículos (qualidade de cadastro + manutenção).
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/api_json.php';
require_once '../includes/sf_insights_cache.php';

configure_session();
session_start();
require_authentication();
if (function_exists('session_write_close')) { session_write_close(); }

$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
if (!$empresa_id) {
    api_json_error('Não autorizado.', 401);
}

const SF_KM_ALERTA_MANUTENCAO = 20000;

try {
    $conn = getConnection();

    sf_insights_json_cached($empresa_id, 'vehicle', 300, function () use ($conn, $empresa_id) {
        $stmt = $conn->prepare("SELECT id, placa, modelo, marca, ano, chassi, renavam, km_atual,
                                       foto_veiculo, documento, proprietario, status_id,
                                       km_ultima_manutencao, ultima_manutencao
                                FROM veiculos WHERE empresa_id = :e ORDER BY placa LIMIT 10000");
        $stmt->execute([':e' => $empresa_id]);
        $veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $anoAtual = (int) date('Y');
        $itens = [];
        $qErros = 0;
        $qAvisos = 0;
        $semFoto = 0;
        $semDoc = 0;
        $manutAtrasada = 0;

        foreach ($veiculos as $v) {
            $motivos = [];
            $temErro = false;
            $rotulo = trim((string) ($v['placa'] ?? '')) ?: ('#' . (int) $v['id']);
            if (trim((string) ($v['modelo'] ?? '')) !== '') {
                $rotulo .= ' · ' . trim((string) $v['modelo']);
            }

            if (trim((string) ($v['documento'] ?? '')) === '') {
                $motivos[] = ['sev' => 'aviso', 'msg' => 'Sem documento (CRLV) anexado.'];
                $semDoc++;
            }
            if (trim((string) ($v['foto_veiculo'] ?? '')) === '') {
                $motivos[] = ['sev' => 'aviso', 'msg' => 'Sem foto do veículo.'];
                $semFoto++;
            }
            $chassi = preg_replace('/\s+/', '', (string) ($v['chassi'] ?? ''));
            if ($chassi === '') {
                $motivos[] = ['sev' => 'aviso', 'msg' => 'Sem chassi cadastrado.'];
            } elseif (strlen($chassi) !== 17) {
                $motivos[] = ['sev' => 'aviso', 'msg' => 'Chassi com tamanho inválido (deve ter 17 caracteres).'];
            }
            $renavam = preg_replace('/\D/', '', (string) ($v['renavam'] ?? ''));
            if ($renavam === '') {
                $motivos[] = ['sev' => 'aviso', 'msg' => 'Sem RENAVAM cadastrado.'];
            }
            $ano = (int) ($v['ano'] ?? 0);
            if ($ano < 1950 || $ano > $anoAtual + 1) {
                $motivos[] = ['sev' => 'erro', 'msg' => 'Ano do veículo inválido (' . ($ano ?: 'vazio') . ').'];
                $temErro = true;
            }
            if (trim((string) ($v['proprietario'] ?? '')) === '') {
                $motivos[] = ['sev' => 'aviso', 'msg' => 'Sem proprietário informado.'];
            }
            $km = (float) ($v['km_atual'] ?? 0);
            if ($km <= 0) {
                $motivos[] = ['sev' => 'aviso', 'msg' => 'Hodômetro zerado (km_atual = 0).'];
            }
            $kmUltima = $v['km_ultima_manutencao'];
            if ($kmUltima !== null && (float) $kmUltima > 0 && $km > 0) {
                $rodado = $km - (float) $kmUltima;
                if ($rodado >= SF_KM_ALERTA_MANUTENCAO) {
                    $motivos[] = ['sev' => 'erro', 'msg' => 'Rodou ' . number_format($rodado, 0, ',', '.') . ' km desde a última manutenção (revisar).'];
                    $temErro = true;
                    $manutAtrasada++;
                }
            }

            if (!$motivos) {
                continue;
            }
            if ($temErro) {
                $qErros++;
            } else {
                $qAvisos++;
            }
            $itens[] = [
                'id' => (int) $v['id'],
                'nome' => $rotulo,
                'severidade' => $temErro ? 'erro' : 'aviso',
                'motivos' => $motivos,
            ];
        }

        usort($itens, function ($a, $b) {
            if ($a['severidade'] === $b['severidade']) {
                return strcmp($a['nome'], $b['nome']);
            }
            return $a['severidade'] === 'erro' ? -1 : 1;
        });

        return [
            'success' => true,
            'total_veiculos' => count($veiculos),
            'qualidade' => $itens,
            'q_erros' => $qErros,
            'q_avisos' => $qAvisos,
            'resumo' => [
                'sem_foto' => $semFoto,
                'sem_documento' => $semDoc,
                'manutencao_atrasada' => $manutAtrasada,
            ],
        ];
    });
} catch (Throwable $e) {
    error_log('api/vehicle_insights.php: ' . $e->getMessage());
    api_json_error('Erro ao gerar insights de veículos.', 500);
}
