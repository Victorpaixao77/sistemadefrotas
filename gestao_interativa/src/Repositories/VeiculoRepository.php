<?php
namespace GestaoInterativa\Repositories;

use GestaoInterativa\Database\Database;
use GestaoInterativa\Models\Veiculo;

class VeiculoRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function findAll($empresaId) {
        $sql = "SELECT * FROM veiculos WHERE empresa_id = ? ORDER BY placa ASC";
        $result = $this->db->fetchAll($sql, [$empresaId]);
        
        return array_map(function($data) {
            return new Veiculo($data);
        }, $result);
    }

    public function findById($id, $empresaId) {
        $sql = "SELECT * FROM veiculos WHERE id = ? AND empresa_id = ?";
        $result = $this->db->fetch($sql, [$id, $empresaId]);
        
        return $result ? new Veiculo($result) : null;
    }

    public function findByStatus($statusId, $empresaId) {
        $sql = "SELECT * FROM veiculos WHERE status_id = ? AND empresa_id = ? ORDER BY placa ASC";
        $result = $this->db->fetchAll($sql, [$statusId, $empresaId]);
        
        return array_map(function($data) {
            return new Veiculo($data);
        }, $result);
    }

    public function findByPlaca($placa, $empresaId) {
        $sql = "SELECT * FROM veiculos WHERE placa = ? AND empresa_id = ?";
        $result = $this->db->fetch($sql, [$placa, $empresaId]);
        
        return $result ? new Veiculo($result) : null;
    }

    public function save(Veiculo $veiculo) {
        if ($veiculo->getId()) {
            return $this->update($veiculo);
        }
        return $this->insert($veiculo);
    }

    private function insert(Veiculo $veiculo) {
        $data = $veiculo->toArray();
        unset($data['id']);
        
        return $this->db->insert('veiculos', $data);
    }

    private function update(Veiculo $veiculo) {
        $data = $veiculo->toArray();
        $id = $data['id'];
        unset($data['id']);
        
        return $this->db->update('veiculos', $data, 'id = ?', [$id]);
    }

    public function delete($id, $empresaId) {
        return $this->db->delete('veiculos', 'id = ? AND empresa_id = ?', [$id, $empresaId]);
    }

    public function getVeiculosEmManutencao($empresaId) {
        $sql = "SELECT v.* FROM veiculos v 
                INNER JOIN manutencoes m ON v.id = m.veiculo_id 
                WHERE v.empresa_id = ? 
                AND m.status = 'em_andamento'
                ORDER BY m.data_inicio DESC";
        
        $result = $this->db->fetchAll($sql, [$empresaId]);
        
        return array_map(function($data) {
            return new Veiculo($data);
        }, $result);
    }

    public function getVeiculosProximosRevisao($empresaId, $kmAviso = 1000) {
        $sql = "SELECT v.*, 
                (v.km_atual - v.km_ultima_revisao) as km_percorrido
                FROM veiculos v
                WHERE v.empresa_id = ? 
                AND v.status_id = 1
                AND (v.km_atual - v.km_ultima_revisao) >= (v.km_proxima_revisao - ?)
                ORDER BY km_percorrido DESC";
        
        $result = $this->db->fetchAll($sql, [$empresaId, $kmAviso]);
        
        return array_map(function($data) {
            return new Veiculo($data);
        }, $result);
    }

    public function getVeiculosEmViagem($empresaId) {
        $sql = "SELECT v.* FROM veiculos v 
                INNER JOIN rotas r ON v.id = r.veiculo_id 
                WHERE v.empresa_id = ? 
                AND r.status = 'em_andamento'
                ORDER BY r.data_saida DESC";
        
        $result = $this->db->fetchAll($sql, [$empresaId]);
        
        return array_map(function($data) {
            return new Veiculo($data);
        }, $result);
    }

    public function getVeiculosDisponiveis($empresaId) {
        $sql = "SELECT v.* FROM veiculos v 
                WHERE v.empresa_id = ? 
                AND v.status_id = 1
                AND v.id NOT IN (
                    SELECT veiculo_id FROM rotas 
                    WHERE status = 'em_andamento'
                )
                AND v.id NOT IN (
                    SELECT veiculo_id FROM manutencoes 
                    WHERE status = 'em_andamento'
                )
                ORDER BY v.placa ASC";
        
        $result = $this->db->fetchAll($sql, [$empresaId]);
        
        return array_map(function($data) {
            return new Veiculo($data);
        }, $result);
    }
} 