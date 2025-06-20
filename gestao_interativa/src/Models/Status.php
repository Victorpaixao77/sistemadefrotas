<?php
namespace GestaoInterativa\Models;

class Status {
    private $id;
    private $nome;
    private $descricao;
    private $tipo; // 'veiculo' ou 'pneu'
    private $cor;
    private $empresa_id;

    public function __construct(array $data = []) {
        $this->hydrate($data);
    }

    private function hydrate(array $data) {
        foreach ($data as $key => $value) {
            $method = 'set' . str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));
            if (method_exists($this, $method)) {
                $this->$method($value);
            }
        }
    }

    // Getters
    public function getId() { return $this->id; }
    public function getNome() { return $this->nome; }
    public function getDescricao() { return $this->descricao; }
    public function getTipo() { return $this->tipo; }
    public function getCor() { return $this->cor; }
    public function getEmpresaId() { return $this->empresa_id; }

    // Setters
    public function setId($id) { $this->id = $id; }
    public function setNome($nome) { $this->nome = $nome; }
    public function setDescricao($descricao) { $this->descricao = $descricao; }
    public function setTipo($tipo) { $this->tipo = $tipo; }
    public function setCor($cor) { $this->cor = $cor; }
    public function setEmpresaId($empresa_id) { $this->empresa_id = $empresa_id; }

    public function toArray() {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'descricao' => $this->descricao,
            'tipo' => $this->tipo,
            'cor' => $this->cor,
            'empresa_id' => $this->empresa_id
        ];
    }
} 