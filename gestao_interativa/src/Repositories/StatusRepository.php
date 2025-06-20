<?php
namespace GestaoInterativa\Repositories;

use GestaoInterativa\Database\Database;
use GestaoInterativa\Models\Status;

class StatusRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function findAll($empresaId) {
        $sql = "SELECT * FROM status WHERE empresa_id = ? ORDER BY nome ASC";
        $result = $this->db->fetchAll($sql, [$empresaId]);
        
        return array_map(function($data) {
            return new Status($data);
        }, $result);
    }

    public function findById($id, $empresaId) {
        $sql = "SELECT * FROM status WHERE id = ? AND empresa_id = ?";
        $result = $this->db->fetch($sql, [$id, $empresaId]);
        
        return $result ? new Status($result) : null;
    }

    public function findByTipo($tipo, $empresaId) {
        $sql = "SELECT * FROM status WHERE tipo = ? AND empresa_id = ? ORDER BY nome ASC";
        $result = $this->db->fetchAll($sql, [$tipo, $empresaId]);
        
        return array_map(function($data) {
            return new Status($data);
        }, $result);
    }

    public function save(Status $status) {
        if ($status->getId()) {
            return $this->update($status);
        }
        return $this->insert($status);
    }

    private function insert(Status $status) {
        $data = $status->toArray();
        unset($data['id']);
        
        return $this->db->insert('status', $data);
    }

    private function update(Status $status) {
        $data = $status->toArray();
        $id = $data['id'];
        unset($data['id']);
        
        return $this->db->update('status', $data, 'id = ?', [$id]);
    }

    public function delete($id, $empresaId) {
        return $this->db->delete('status', 'id = ? AND empresa_id = ?', [$id, $empresaId]);
    }

    public function getStatusVeiculos($empresaId) {
        return $this->findByTipo('veiculo', $empresaId);
    }

    public function getStatusPneus($empresaId) {
        return $this->findByTipo('pneu', $empresaId);
    }

    public function getStatusAtivo($tipo, $empresaId) {
        $sql = "SELECT * FROM status WHERE tipo = ? AND empresa_id = ? AND nome = 'Ativo' LIMIT 1";
        $result = $this->db->fetch($sql, [$tipo, $empresaId]);
        
        return $result ? new Status($result) : null;
    }

    public function getStatusInativo($tipo, $empresaId) {
        $sql = "SELECT * FROM status WHERE tipo = ? AND empresa_id = ? AND nome = 'Inativo' LIMIT 1";
        $result = $this->db->fetch($sql, [$tipo, $empresaId]);
        
        return $result ? new Status($result) : null;
    }

    public function getStatusManutencao($tipo, $empresaId) {
        $sql = "SELECT * FROM status WHERE tipo = ? AND empresa_id = ? AND nome = 'Em Manutenção' LIMIT 1";
        $result = $this->db->fetch($sql, [$tipo, $empresaId]);
        
        return $result ? new Status($result) : null;
    }
} 