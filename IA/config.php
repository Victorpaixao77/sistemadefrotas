<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

class ConfiguracoesIA {
    private $conn;
    private $empresa_id;

    public function __construct($empresa_id) {
        $this->conn = getConnection();
        $this->empresa_id = $empresa_id;
    }

    /**
     * Obtém configurações do painel
     */
    public function obterConfiguracoes() {
        try {
            $query = "SELECT 
                        config_key,
                        config_value
                    FROM configuracoes_ia
                    WHERE empresa_id = :empresa_id";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':empresa_id', $this->empresa_id);
            $stmt->execute();

            $configuracoes = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $configuracoes[$row['config_key']] = json_decode($row['config_value'], true);
            }

            return $configuracoes;
        } catch (PDOException $e) {
            error_log("Erro ao obter configurações: " . $e->getMessage());
            return $this->getConfiguracoesPadrao();
        }
    }

    /**
     * Salva configurações do painel
     */
    public function salvarConfiguracoes($configuracoes) {
        try {
            $this->conn->beginTransaction();

            // Remove configurações existentes
            $query = "DELETE FROM configuracoes_ia
                    WHERE empresa_id = :empresa_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':empresa_id', $this->empresa_id);
            $stmt->execute();

            // Insere novas configurações
            $query = "INSERT INTO configuracoes_ia (
                        empresa_id,
                        config_key,
                        config_value
                    ) VALUES (
                        :empresa_id,
                        :config_key,
                        :config_value
                    )";

            $stmt = $this->conn->prepare($query);
            
            foreach ($configuracoes as $key => $value) {
                $stmt->bindParam(':empresa_id', $this->empresa_id);
                $stmt->bindParam(':config_key', $key);
                $stmt->bindParam(':config_value', json_encode($value));
                $stmt->execute();
            }

            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Erro ao salvar configurações: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtém configurações padrão
     */
    private function getConfiguracoesPadrao() {
        return [
            'notificacoes' => [
                'email' => true,
                'sistema' => true,
                'prioridade_alta' => true,
                'prioridade_media' => true,
                'prioridade_baixa' => false
            ],
            'alertas' => [
                'manutencao' => [
                    'dias_antecedencia' => 30,
                    'km_antecedencia' => 1000
                ],
                'documentos' => [
                    'dias_antecedencia' => 30
                ],
                'consumo' => [
                    'variacao_percentual' => 10
                ],
                'rotas' => [
                    'tempo_maximo' => 120,
                    'min_veiculos' => 2
                ]
            ],
            'insights' => [
                'periodo_analise' => 90, // dias
                'variacao_consumo' => 10, // percentual
                'frequencia_manutencao' => 2, // vezes
                'variacao_custos' => 20 // percentual
            ],
            'interface' => [
                'tema' => 'claro',
                'graficos' => true,
                'estatisticas' => true,
                'recomendacoes' => true,
                'insights' => true
            ]
        ];
    }

    /**
     * Atualiza uma configuração específica
     */
    public function atualizarConfiguracao($key, $value) {
        try {
            $query = "INSERT INTO configuracoes_ia (
                        empresa_id,
                        config_key,
                        config_value
                    ) VALUES (
                        :empresa_id,
                        :config_key,
                        :config_value
                    ) ON DUPLICATE KEY UPDATE
                        config_value = :config_value";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':empresa_id', $this->empresa_id);
            $stmt->bindParam(':config_key', $key);
            $stmt->bindParam(':config_value', json_encode($value));
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao atualizar configuração: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtém uma configuração específica
     */
    public function obterConfiguracao($key) {
        try {
            $query = "SELECT config_value
                    FROM configuracoes_ia
                    WHERE empresa_id = :empresa_id
                    AND config_key = :config_key";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':empresa_id', $this->empresa_id);
            $stmt->bindParam(':config_key', $key);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($resultado) {
                return json_decode($resultado['config_value'], true);
            }
            
            // Se não encontrar, retorna o valor padrão
            $configuracoes_padrao = $this->getConfiguracoesPadrao();
            return $configuracoes_padrao[$key] ?? null;
        } catch (PDOException $e) {
            error_log("Erro ao obter configuração: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Remove uma configuração específica
     */
    public function removerConfiguracao($key) {
        try {
            $query = "DELETE FROM configuracoes_ia
                    WHERE empresa_id = :empresa_id
                    AND config_key = :config_key";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':empresa_id', $this->empresa_id);
            $stmt->bindParam(':config_key', $key);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao remover configuração: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reseta todas as configurações para os valores padrão
     */
    public function resetarConfiguracoes() {
        try {
            $this->conn->beginTransaction();

            // Remove todas as configurações
            $query = "DELETE FROM configuracoes_ia
                    WHERE empresa_id = :empresa_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':empresa_id', $this->empresa_id);
            $stmt->execute();

            // Insere configurações padrão
            $configuracoes_padrao = $this->getConfiguracoesPadrao();
            $query = "INSERT INTO configuracoes_ia (
                        empresa_id,
                        config_key,
                        config_value
                    ) VALUES (
                        :empresa_id,
                        :config_key,
                        :config_value
                    )";

            $stmt = $this->conn->prepare($query);
            
            foreach ($configuracoes_padrao as $key => $value) {
                $stmt->bindParam(':empresa_id', $this->empresa_id);
                $stmt->bindParam(':config_key', $key);
                $stmt->bindParam(':config_value', json_encode($value));
                $stmt->execute();
            }

            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Erro ao resetar configurações: " . $e->getMessage());
            return false;
        }
    }
} 