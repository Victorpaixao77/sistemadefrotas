<?php
namespace GestaoInterativa\Repositories;

use GestaoInterativa\Database\Database;
use GestaoInterativa\Models\Pneu;

class PneuRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function findAll($empresaId) {
        $sql = "SELECT * FROM pneus WHERE empresa_id = ? ORDER BY data_entrada DESC";
        $result = $this->db->fetchAll($sql, [$empresaId]);
        
        return array_map(function($data) {
            return new Pneu($data);
        }, $result);
    }

    public function findById($id, $empresaId) {
        $sql = "SELECT * FROM pneus WHERE id = ? AND empresa_id = ?";
        $result = $this->db->fetch($sql, [$id, $empresaId]);
        
        return $result ? new Pneu($result) : null;
    }

    public function findByStatus($statusId, $empresaId) {
        $sql = "SELECT * FROM pneus WHERE status_id = ? AND empresa_id = ? ORDER BY data_entrada DESC";
        $result = $this->db->fetchAll($sql, [$statusId, $empresaId]);
        
        return array_map(function($data) {
            return new Pneu($data);
        }, $result);
    }

    public function findByVeiculo($veiculoId, $empresaId) {
        $sql = "SELECT p.* FROM pneus p 
                INNER JOIN pneus_veiculos pv ON p.id = pv.pneu_id 
                WHERE pv.veiculo_id = ? AND p.empresa_id = ? 
                ORDER BY pv.data_instalacao DESC";
        $result = $this->db->fetchAll($sql, [$veiculoId, $empresaId]);
        
        return array_map(function($data) {
            return new Pneu($data);
        }, $result);
    }

    public function save(Pneu $pneu) {
        if ($pneu->getId()) {
            return $this->update($pneu);
        }
        return $this->insert($pneu);
    }

    private function insert(Pneu $pneu) {
        $data = $pneu->toArray();
        unset($data['id']);
        
        return $this->db->insert('pneus', $data);
    }

    private function update(Pneu $pneu) {
        $data = $pneu->toArray();
        $id = $data['id'];
        unset($data['id']);
        
        return $this->db->update('pneus', $data, 'id = ?', [$id]);
    }

    public function delete($id, $empresaId) {
        return $this->db->delete('pneus', 'id = ? AND empresa_id = ?', [$id, $empresaId]);
    }

    public function getPneusProximosManutencao($empresaId, $diasAviso = 30) {
        $sql = "SELECT p.*, 
                DATEDIFF(DATE_ADD(p.data_ultima_recapagem, INTERVAL p.vida_util_km DAY), CURDATE()) as dias_restantes
                FROM pneus p
                WHERE p.empresa_id = ? 
                AND p.status_id = 1
                AND DATEDIFF(DATE_ADD(p.data_ultima_recapagem, INTERVAL p.vida_util_km DAY), CURDATE()) <= ?
                ORDER BY dias_restantes ASC";
        
        $result = $this->db->fetchAll($sql, [$empresaId, $diasAviso]);
        
        return array_map(function($data) {
            return new Pneu($data);
        }, $result);
    }

    public function getPneusVencidos($empresaId) {
        $sql = "SELECT p.*, 
                DATEDIFF(DATE_ADD(p.data_ultima_recapagem, INTERVAL p.vida_util_km DAY), CURDATE()) as dias_restantes
                FROM pneus p
                WHERE p.empresa_id = ? 
                AND p.status_id = 1
                AND DATEDIFF(DATE_ADD(p.data_ultima_recapagem, INTERVAL p.vida_util_km DAY), CURDATE()) <= 0
                ORDER BY dias_restantes ASC";
        
        $result = $this->db->fetchAll($sql, [$empresaId]);
        
        return array_map(function($data) {
            return new Pneu($data);
        }, $result);
    }
} 