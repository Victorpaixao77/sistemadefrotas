<?php
/**
 * 🎯 GERENCIADOR DE EVENTOS FISCAIS
 * 📋 SISTEMA DE FROTAS - MÓDULO FISCAL
 * 
 * Esta classe gerencia todos os eventos fiscais oficiais:
 * - Cancelamento de documentos
 * - Encerramento de MDF-e
 * - Carta de Correção Eletrônica (CCE)
 * - Inutilização de números
 * 
 * 📅 Data: Agosto 2025
 * 🔧 Versão: 2.0.0
 * 🏷️  Prefixo: fiscal_ (para organização do banco)
 */

class FiscalEventManager {
    private $conn;
    private $empresa_id;
    private $cryptoManager;
    
    public function __construct($conn, $empresa_id) {
        $this->conn = $conn;
        $this->empresa_id = $empresa_id;
        $this->cryptoManager = new CryptoManager();
    }
    
    /**
     * 📝 Registrar um novo evento fiscal
     */
    public function registrarEvento($tipoEvento, $documentoTipo, $documentoId, $justificativa = '', $xmlEvento = '', $usuarioId = null) {
        try {
            $sql = "INSERT INTO fiscal_eventos_fiscais (
                empresa_id, tipo_evento, documento_tipo, documento_id, 
                justificativa, xml_evento, usuario_id
            ) VALUES (
                :empresa_id, :tipo_evento, :documento_tipo, :documento_id,
                :justificativa, :xml_evento, :usuario_id
            )";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                'empresa_id' => $this->empresa_id,
                'tipo_evento' => $tipoEvento,
                'documento_tipo' => $documentoTipo,
                'documento_id' => $documentoId,
                'justificativa' => $justificativa,
                'xml_evento' => $xmlEvento,
                'usuario_id' => $usuarioId
            ]);
            
            $eventoId = $this->conn->lastInsertId();
            
            // Registrar log
            $this->registrarLog($documentoTipo, $documentoId, 'evento_registrado', 'sucesso', "Evento {$tipoEvento} registrado com ID: {$eventoId}", $usuarioId);
            
            return [
                'success' => true,
                'evento_id' => $eventoId,
                'message' => "Evento {$tipoEvento} registrado com sucesso"
            ];
            
        } catch (Exception $e) {
            $this->registrarLog($documentoTipo, $documentoId, 'evento_registrado', 'erro', "Erro ao registrar evento: " . $e->getMessage(), $usuarioId);
            return [
                'success' => false,
                'message' => "Erro ao registrar evento: " . $e->getMessage()
            ];
        }
    }
    
    /**
     * 🔄 Processar um evento fiscal (enviar para SEFAZ)
     */
    public function processarEvento($eventoId) {
        try {
            // Buscar evento
            $sql = "SELECT * FROM fiscal_eventos_fiscais WHERE id = :evento_id AND empresa_id = :empresa_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute(['evento_id' => $eventoId, 'empresa_id' => $this->empresa_id]);
            $evento = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$evento) {
                throw new Exception("Evento não encontrado");
            }
            
            // Enviar para SEFAZ (nesta fase a integração ainda não está implementada)
            $resultadoSefaz = $this->enviarParaSefaz($evento);
            
            // Atualizar status do evento
            $this->atualizarStatusEvento($eventoId, $resultadoSefaz['status'], $resultadoSefaz['protocolo'], $resultadoSefaz['xml_retorno']);
            
            // Atualizar status do documento principal
            $this->atualizarStatusDocumento($evento['documento_tipo'], $evento['documento_id'], $resultadoSefaz['status']);
            
            // Registrar log
            $this->registrarLog(
                $evento['documento_tipo'],
                $evento['documento_id'],
                'evento_processado',
                ($resultadoSefaz['status'] === 'aceito') ? 'sucesso' : 'pendente',
                "Evento processado com status: {$resultadoSefaz['status']}",
                $evento['usuario_id']
            );

            $aceito = ($resultadoSefaz['status'] === 'aceito');
            return [
                'success' => $aceito,
                'status' => $resultadoSefaz['status'],
                'protocolo' => $resultadoSefaz['protocolo'],
                'message' => $aceito ? "Evento processado com sucesso" : "Integração SEFAZ não implementada; evento ficou pendente.",
                'erro' => $aceito ? null : ($resultadoSefaz['erro'] ?? null),
            ];
            
        } catch (Exception $e) {
            $this->registrarLog($evento['documento_tipo'] ?? 'desconhecido', $evento['documento_id'] ?? 0, 'evento_processado', 'erro', "Erro ao processar evento: " . $e->getMessage(), $evento['usuario_id'] ?? null);
            return [
                'success' => false,
                'message' => "Erro ao processar evento: " . $e->getMessage()
            ];
        }
    }
    
    /**
     * ❌ Cancelar um documento fiscal
     */
    public function cancelarDocumento($documentoTipo, $documentoId, $justificativa, $usuarioId = null) {
        try {
            // Verificar se pode ser cancelado
            if (!$this->podeSerCancelado($documentoTipo, $documentoId)) {
                throw new Exception("Documento não pode ser cancelado");
            }
            
            // Registrar evento de cancelamento
            $resultado = $this->registrarEvento('cancelamento', $documentoTipo, $documentoId, $justificativa, '', $usuarioId);
            
            if (!$resultado['success']) {
                throw new Exception($resultado['message']);
            }
            
            // Processar evento
            return $this->processarEvento($resultado['evento_id']);
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => "Erro ao cancelar documento: " . $e->getMessage()
            ];
        }
    }
    
    /**
     * 🔒 Encerrar um MDF-e
     */
    public function encerrarMDFe($mdfeId, $usuarioId = null) {
        try {
            // Verificar se pode ser encerrado
            if (!$this->podeSerEncerrado($mdfeId)) {
                throw new Exception("MDF-e não pode ser encerrado");
            }
            
            // Registrar evento de encerramento
            $resultado = $this->registrarEvento('encerramento', 'mdfe', $mdfeId, 'Encerramento automático após viagem', '', $usuarioId);
            
            if (!$resultado['success']) {
                throw new Exception($resultado['message']);
            }
            
            // Processar evento
            return $this->processarEvento($resultado['evento_id']);
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => "Erro ao encerrar MDF-e: " . $e->getMessage()
            ];
        }
    }
    
    /**
     * 📝 Emitir Carta de Correção Eletrônica
     */
    public function emitirCCE($documentoTipo, $documentoId, $correcoes, $usuarioId = null) {
        try {
            // Verificar se pode receber CCE
            if (!$this->podeReceberCCE($documentoTipo, $documentoId)) {
                throw new Exception("Documento não pode receber CCE");
            }
            
            // Gerar XML da CCE
            $xmlCCE = $this->gerarXMLCCE($documentoTipo, $documentoId, $correcoes);
            
            // Registrar evento CCE
            $resultado = $this->registrarEvento('cce', $documentoTipo, $documentoId, 'Carta de Correção Eletrônica', $xmlCCE, $usuarioId);
            
            if (!$resultado['success']) {
                throw new Exception($resultado['message']);
            }
            
            // Processar evento
            return $this->processarEvento($resultado['evento_id']);
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => "Erro ao emitir CCE: " . $e->getMessage()
            ];
        }
    }
    
    /**
     * 🔍 Verificar se documento pode ser cancelado
     */
    private function podeSerCancelado($documentoTipo, $documentoId) {
        $sql = "SELECT status FROM fiscal_{$documentoTipo} WHERE id = :documento_id AND empresa_id = :empresa_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['documento_id' => $documentoId, 'empresa_id' => $this->empresa_id]);
        $documento = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$documento) {
            return false;
        }
        
        // Só pode cancelar se estiver autorizado
        return in_array($documento['status'], ['autorizado', 'pendente']);
    }
    
    /**
     * 🔍 Verificar se MDF-e pode ser encerrado
     */
    private function podeSerEncerrado($mdfeId) {
        $sql = "SELECT status FROM fiscal_mdfe WHERE id = :mdfe_id AND empresa_id = :empresa_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['mdfe_id' => $mdfeId, 'empresa_id' => $this->empresa_id]);
        $mdfe = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$mdfe) {
            return false;
        }
        
        // Só pode encerrar se estiver autorizado
        return $mdfe['status'] === 'autorizado';
    }
    
    /**
     * 🔍 Verificar se documento pode receber CCE
     */
    private function podeReceberCCE($documentoTipo, $documentoId) {
        $sql = "SELECT status FROM fiscal_{$documentoTipo} WHERE id = :documento_id AND empresa_id = :empresa_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['documento_id' => $documentoId, 'empresa_id' => $this->empresa_id]);
        $documento = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$documento) {
            return false;
        }
        
        // Só pode receber CCE se estiver autorizado
        return $documento['status'] === 'autorizado';
    }
    
    /**
     * 🌐 Envio para SEFAZ (sem mocks)
     * Observação: esta classe hoje não executa a integração real de eventos.
     */
    private function enviarParaSefaz($evento) {
        return [
            'status' => 'pendente',
            'protocolo' => null,
            'xml_retorno' => null,
            'erro' => 'Integração SEFAZ de eventos fiscais não implementada no modo atual.'
        ];
    }
    
    /**
     * 🔄 Atualizar status do evento
     */
    private function atualizarStatusEvento($eventoId, $status, $protocolo = '', $xmlRetorno = '') {
        $sql = "UPDATE fiscal_eventos_fiscais SET 
                status = :status, 
                protocolo_evento = :protocolo,
                xml_retorno = :xml_retorno,
                data_processamento = NOW()
                WHERE id = :evento_id";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            'status' => $status,
            'protocolo' => $protocolo,
            'xml_retorno' => $xmlRetorno,
            'evento_id' => $eventoId
        ]);
    }
    
    /**
     * 🔄 Atualizar status do documento principal
     */
    private function atualizarStatusDocumento($documentoTipo, $documentoId, $statusEvento) {
        $novoStatus = 'pendente';
        
        if ($statusEvento === 'aceito') {
            switch ($documentoTipo) {
                case 'mdfe':
                    $novoStatus = 'encerrado';
                    break;
                case 'nfe':
                case 'cte':
                    $novoStatus = 'cancelado';
                    break;
            }
        }
        
        $sql = "UPDATE fiscal_{$documentoTipo} SET status = :novo_status WHERE id = :documento_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            'novo_status' => $novoStatus,
            'documento_id' => $documentoId
        ]);
        
        // Registrar histórico de status
        $this->registrarHistoricoStatus($documentoTipo, $documentoId, 'pendente', $novoStatus, "Status alterado por evento fiscal", null);
    }
    
    /**
     * 📊 Registrar histórico de mudança de status
     */
    private function registrarHistoricoStatus($documentoTipo, $documentoId, $statusAnterior, $statusNovo, $motivo, $usuarioId) {
        $sql = "INSERT INTO fiscal_status_historico (
            empresa_id, documento_tipo, documento_id, status_anterior, 
            status_novo, motivo_mudanca, usuario_id, ip_usuario
        ) VALUES (
            :empresa_id, :documento_tipo, :documento_id, :status_anterior,
            :status_novo, :motivo, :usuario_id, :ip_usuario
        )";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            'empresa_id' => $this->empresa_id,
            'documento_tipo' => $documentoTipo,
            'documento_id' => $documentoId,
            'status_anterior' => $statusAnterior,
            'status_novo' => $statusNovo,
            'motivo' => $motivo,
            'usuario_id' => $usuarioId,
            'ip_usuario' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);
    }
    
    /**
     * 📝 Registrar log de operação
     */
    private function registrarLog($documentoTipo, $documentoId, $acao, $status, $mensagem, $usuarioId) {
        $sql = "INSERT INTO fiscal_logs (
            empresa_id, documento_tipo, documento_id, acao, 
            status, mensagem, usuario_id, ip_usuario
        ) VALUES (
            :empresa_id, :documento_tipo, :documento_id, :acao,
            :status, :mensagem, :usuario_id, :ip_usuario
        )";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            'empresa_id' => $this->empresa_id,
            'documento_tipo' => $documentoTipo,
            'documento_id' => $documentoId,
            'acao' => $acao,
            'status' => $status,
            'mensagem' => $mensagem,
            'usuario_id' => $usuarioId,
            'ip_usuario' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);
    }
    
    /**
     * 📄 Gerar XML da CCE
     */
    private function gerarXMLCCE($documentoTipo, $documentoId, $correcoes) {
        // Simulação de geração de XML CCE
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<cce>';
        $xml .= '<documento_tipo>' . $documentoTipo . '</documento_tipo>';
        $xml .= '<documento_id>' . $documentoId . '</documento_id>';
        $xml .= '<correcoes>';
        
        foreach ($correcoes as $correcao) {
            $xml .= '<correcao>' . htmlspecialchars($correcao) . '</correcao>';
        }
        
        $xml .= '</correcoes>';
        $xml .= '</cce>';
        
        return $xml;
    }
    
    /**
     * 📋 Listar eventos fiscais
     */
    public function listarEventos($filtros = []) {
        $sql = "SELECT ef.*, 
                       CASE 
                           WHEN ef.documento_tipo = 'nfe' THEN n.numero_nfe
                           WHEN ef.documento_tipo = 'cte' THEN c.numero_cte
                           WHEN ef.documento_tipo = 'mdfe' THEN m.numero_mdfe
                       END as numero_documento
                FROM fiscal_eventos_fiscais ef
                LEFT JOIN fiscal_nfe_clientes n ON ef.documento_tipo = 'nfe' AND ef.documento_id = n.id
                LEFT JOIN fiscal_cte c ON ef.documento_tipo = 'cte' AND ef.documento_id = c.id
                LEFT JOIN fiscal_mdfe m ON ef.documento_tipo = 'mdfe' AND ef.documento_id = m.id
                WHERE ef.empresa_id = :empresa_id";
        
        $params = ['empresa_id' => $this->empresa_id];
        
        if (!empty($filtros['tipo_evento'])) {
            $sql .= " AND ef.tipo_evento = :tipo_evento";
            $params['tipo_evento'] = $filtros['tipo_evento'];
        }
        
        if (!empty($filtros['status'])) {
            $sql .= " AND ef.status = :status";
            $params['status'] = $filtros['status'];
        }
        
        if (!empty($filtros['data_inicio'])) {
            $sql .= " AND ef.data_evento >= :data_inicio";
            $params['data_inicio'] = $filtros['data_inicio'];
        }
        
        if (!empty($filtros['data_fim'])) {
            $sql .= " AND ef.data_evento <= :data_fim";
            $params['data_fim'] = $filtros['data_fim'];
        }
        
        $sql .= " ORDER BY ef.data_evento DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 📊 Obter estatísticas dos eventos
     */
    public function getEstatisticasEventos() {
        $sql = "SELECT 
                    tipo_evento,
                    status,
                    COUNT(*) as total
                FROM fiscal_eventos_fiscais 
                WHERE empresa_id = :empresa_id
                GROUP BY tipo_evento, status";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['empresa_id' => $this->empresa_id]);
        
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $estatisticas = [];
        foreach ($resultados as $row) {
            $estatisticas[$row['tipo_evento']][$row['status']] = $row['total'];
        }
        
        return $estatisticas;
    }
}
?>
