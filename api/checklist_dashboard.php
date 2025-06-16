<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Configura sessão e autenticação
configure_session();
session_start();
require_authentication();

header('Content-Type: application/json');

$action = isset($_GET['action']) ? $_GET['action'] : '';
$empresa_id = $_SESSION['empresa_id'];

try {
    $conn = getConnection();
    switch ($action) {
        case 'conformidade_motorista':
            // Percentual de OKs por motorista
            $sql = "SELECT 
                        m.nome as motorista_nome,
                        COUNT(*) AS total_checklists,
                        SUM(
                            o.oleo_motor + o.agua_radiador + o.fluido_freio + o.fluido_direcao + o.combustivel +
                            o.pneus + o.estepe + o.luzes + o.buzina + o.limpador_para_brisa + o.agua_limpador + o.freios + o.vazamentos + o.rastreador +
                            o.triangulo + o.extintor + o.chave_macaco + o.cintas + o.primeiros_socorros +
                            o.doc_veiculo + o.cnh + o.licenciamento + o.seguro + o.manifesto_carga + o.doc_empresa +
                            o.carga_amarrada + o.peso_correto + o.motorista_descansado + o.motorista_sobrio + o.celular_carregado + o.epi
                        ) AS total_oks
                    FROM checklist_viagem o
                    LEFT JOIN motoristas m ON o.motorista_id = m.id
                    WHERE o.empresa_id = :empresa_id
                    GROUP BY o.motorista_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':empresa_id', $empresa_id);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $data]);
            break;
        case 'itens_negligenciados':
            // Itens mais negligenciados (mais '0')
            $itens = [
                'freios', 'pneus', 'oleo_motor', 'agua_radiador', 'fluido_freio', 'fluido_direcao', 'combustivel',
                'estepe', 'luzes', 'buzina', 'limpador_para_brisa', 'agua_limpador', 'vazamentos', 'rastreador',
                'triangulo', 'extintor', 'chave_macaco', 'cintas', 'primeiros_socorros',
                'doc_veiculo', 'cnh', 'licenciamento', 'seguro', 'manifesto_carga', 'doc_empresa',
                'carga_amarrada', 'peso_correto', 'motorista_descansado', 'motorista_sobrio', 'celular_carregado', 'epi'
            ];
            $unions = [];
            $params = [];
            foreach ($itens as $idx => $item) {
                $unions[] = "SELECT '$item' AS item, COUNT(*) - SUM($item) AS vezes_com_problema FROM checklist_viagem WHERE empresa_id = :empresa_id$idx";
                $params[":empresa_id$idx"] = $empresa_id;
            }
            $sql = implode(' UNION ALL ', $unions) . ' ORDER BY vezes_com_problema DESC';
            $stmt = $conn->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $data]);
            break;
        case 'historico_diario':
            // Histórico diário de checklists
            $sql = "SELECT DATE(data_checklist) AS data, COUNT(*) AS total_checklists
                    FROM checklist_viagem
                    WHERE empresa_id = :empresa_id
                    GROUP BY DATE(data_checklist)
                    ORDER BY data";
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':empresa_id', $empresa_id);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $data]);
            break;
        case 'risco_alto':
            // Checklists com risco alto (score_seguranca < 8)
            $sql = "SELECT o.*, m.nome as motorista_nome, v.placa as veiculo_placa,
                        (
                          o.oleo_motor + o.agua_radiador + o.fluido_freio + o.fluido_direcao + o.combustivel +
                          o.pneus + o.freios + o.luzes + o.buzina + o.triangulo + o.extintor
                        ) AS score_seguranca
                    FROM checklist_viagem o
                    LEFT JOIN motoristas m ON o.motorista_id = m.id
                    LEFT JOIN veiculos v ON o.veiculo_id = v.id
                    WHERE o.empresa_id = :empresa_id
                    HAVING score_seguranca < 8
                    ORDER BY o.data_checklist DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':empresa_id', $empresa_id);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $data]);
            break;
        case 'ranking_veiculos':
            // Ranking de veículos com mais checklists incompletos
            $sql = "SELECT v.placa as veiculo_placa, COUNT(*) AS total_checklists,
                        SUM(
                          CASE WHEN o.freios = 0 OR o.pneus = 0 OR o.luzes = 0 THEN 1 ELSE 0 END
                        ) AS possiveis_riscos
                    FROM checklist_viagem o
                    LEFT JOIN veiculos v ON o.veiculo_id = v.id
                    WHERE o.empresa_id = :empresa_id
                    GROUP BY o.veiculo_id
                    ORDER BY possiveis_riscos DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':empresa_id', $empresa_id);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $data]);
            break;
        default:
            echo json_encode(['success' => false, 'error' => 'Ação inválida']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 