<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

class Notificacoes {
    private $conn;
    private $empresa_id;

    public function __construct($empresa_id) {
        $this->conn = getConnection();
        $this->empresa_id = $empresa_id;
    }

    /**
     * Registra uma nova notificação
     */
    public function registrarNotificacao($tipo, $mensagem, $prioridade = 'media', $dados = []) {
        try {
            $query = "INSERT INTO notificacoes (
                        empresa_id,
                        tipo,
                        mensagem,
                        prioridade,
                        dados,
                        data_criacao,
                        status
                    ) VALUES (
                        :empresa_id,
                        :tipo,
                        :mensagem,
                        :prioridade,
                        :dados,
                        NOW(),
                        'pendente'
                    )";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':empresa_id', $this->empresa_id);
            $stmt->bindParam(':tipo', $tipo);
            $stmt->bindParam(':mensagem', $mensagem);
            $stmt->bindParam(':prioridade', $prioridade);
            $stmt->bindParam(':dados', json_encode($dados));
            $stmt->execute();

            return $this->conn->lastInsertId();
        } catch (PDOException $e) {
            error_log("Erro ao registrar notificação: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtém notificações pendentes
     */
    public function obterNotificacoesPendentes($limite = 10) {
        try {
            $query = "SELECT 
                        id,
                        tipo,
                        mensagem,
                        prioridade,
                        dados,
                        data_criacao
                    FROM notificacoes
                    WHERE empresa_id = :empresa_id
                    AND status = 'pendente'
                    ORDER BY 
                        CASE prioridade
                            WHEN 'alta' THEN 1
                            WHEN 'media' THEN 2
                            WHEN 'baixa' THEN 3
                        END,
                        data_criacao DESC
                    LIMIT :limite";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':empresa_id', $this->empresa_id);
            $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();

            $notificacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Decodifica os dados JSON
            foreach ($notificacoes as &$notificacao) {
                $notificacao['dados'] = json_decode($notificacao['dados'], true);
            }
            
            return $notificacoes;
        } catch (PDOException $e) {
            error_log("Erro ao obter notificações pendentes: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Marca uma notificação como lida
     */
    public function marcarComoLida($notificacao_id) {
        try {
            $query = "UPDATE notificacoes
                    SET status = 'lida',
                        data_leitura = NOW()
                    WHERE id = :id
                    AND empresa_id = :empresa_id";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $notificacao_id);
            $stmt->bindParam(':empresa_id', $this->empresa_id);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao marcar notificação como lida: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Marca todas as notificações como lidas
     */
    public function marcarTodasComoLidas() {
        try {
            $query = "UPDATE notificacoes
                    SET status = 'lida',
                        data_leitura = NOW()
                    WHERE empresa_id = :empresa_id
                    AND status = 'pendente'";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':empresa_id', $this->empresa_id);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao marcar todas as notificações como lidas: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtém estatísticas de notificações
     */
    public function obterEstatisticas() {
        try {
            $query = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
                        SUM(CASE WHEN status = 'lida' THEN 1 ELSE 0 END) as lidas,
                        SUM(CASE WHEN prioridade = 'alta' AND status = 'pendente' THEN 1 ELSE 0 END) as alta_prioridade,
                        SUM(CASE WHEN prioridade = 'media' AND status = 'pendente' THEN 1 ELSE 0 END) as media_prioridade,
                        SUM(CASE WHEN prioridade = 'baixa' AND status = 'pendente' THEN 1 ELSE 0 END) as baixa_prioridade
                    FROM notificacoes
                    WHERE empresa_id = :empresa_id
                    AND data_criacao >= DATE_SUB(NOW(), INTERVAL 30 DAY)";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':empresa_id', $this->empresa_id);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao obter estatísticas de notificações: " . $e->getMessage());
            return [
                'total' => 0,
                'pendentes' => 0,
                'lidas' => 0,
                'alta_prioridade' => 0,
                'media_prioridade' => 0,
                'baixa_prioridade' => 0
            ];
        }
    }

    /**
     * Limpa notificações antigas
     */
    public function limparNotificacoesAntigas($dias = 90) {
        try {
            $query = "DELETE FROM notificacoes
                    WHERE empresa_id = :empresa_id
                    AND data_criacao < DATE_SUB(NOW(), INTERVAL :dias DAY)
                    AND status = 'lida'";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':empresa_id', $this->empresa_id);
            $stmt->bindParam(':dias', $dias, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao limpar notificações antigas: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtém histórico de notificações
     */
    public function obterHistorico($pagina = 1, $por_pagina = 20) {
        try {
            $offset = ($pagina - 1) * $por_pagina;
            
            $query = "SELECT 
                        id,
                        tipo,
                        mensagem,
                        prioridade,
                        dados,
                        data_criacao,
                        status,
                        data_leitura
                    FROM notificacoes
                    WHERE empresa_id = :empresa_id
                    ORDER BY data_criacao DESC
                    LIMIT :offset, :por_pagina";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':empresa_id', $this->empresa_id);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindParam(':por_pagina', $por_pagina, PDO::PARAM_INT);
            $stmt->execute();

            $notificacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Decodifica os dados JSON
            foreach ($notificacoes as &$notificacao) {
                $notificacao['dados'] = json_decode($notificacao['dados'], true);
            }
            
            return $notificacoes;
        } catch (PDOException $e) {
            error_log("Erro ao obter histórico de notificações: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtém total de páginas do histórico
     */
    public function obterTotalPaginas($por_pagina = 20) {
        try {
            $query = "SELECT COUNT(*) as total
                    FROM notificacoes
                    WHERE empresa_id = :empresa_id";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':empresa_id', $this->empresa_id);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return ceil($resultado['total'] / $por_pagina);
        } catch (PDOException $e) {
            error_log("Erro ao obter total de páginas: " . $e->getMessage());
            return 1;
        }
    }
} 