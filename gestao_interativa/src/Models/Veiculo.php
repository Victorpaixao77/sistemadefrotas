<?php
namespace GestaoInterativa\Models;

class Veiculo {
    private $id;
    private $placa;
    private $marca;
    private $modelo;
    private $ano_fabricacao;
    private $ano_modelo;
    private $chassi;
    private $renavam;
    private $cor;
    private $km_atual;
    private $capacidade_tanque;
    private $tipo_combustivel;
    private $numero_eixos;
    private $tipo_veiculo;
    private $status_id;
    private $empresa_id;
    private $data_cadastro;
    private $observacoes;

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
    public function getPlaca() { return $this->placa; }
    public function getMarca() { return $this->marca; }
    public function getModelo() { return $this->modelo; }
    public function getAnoFabricacao() { return $this->ano_fabricacao; }
    public function getAnoModelo() { return $this->ano_modelo; }
    public function getChassi() { return $this->chassi; }
    public function getRenavam() { return $this->renavam; }
    public function getCor() { return $this->cor; }
    public function getKmAtual() { return $this->km_atual; }
    public function getCapacidadeTanque() { return $this->capacidade_tanque; }
    public function getTipoCombustivel() { return $this->tipo_combustivel; }
    public function getNumeroEixos() { return $this->numero_eixos; }
    public function getTipoVeiculo() { return $this->tipo_veiculo; }
    public function getStatusId() { return $this->status_id; }
    public function getEmpresaId() { return $this->empresa_id; }
    public function getDataCadastro() { return $this->data_cadastro; }
    public function getObservacoes() { return $this->observacoes; }

    // Setters
    public function setId($id) { $this->id = $id; }
    public function setPlaca($placa) { $this->placa = $placa; }
    public function setMarca($marca) { $this->marca = $marca; }
    public function setModelo($modelo) { $this->modelo = $modelo; }
    public function setAnoFabricacao($ano_fabricacao) { $this->ano_fabricacao = $ano_fabricacao; }
    public function setAnoModelo($ano_modelo) { $this->ano_modelo = $ano_modelo; }
    public function setChassi($chassi) { $this->chassi = $chassi; }
    public function setRenavam($renavam) { $this->renavam = $renavam; }
    public function setCor($cor) { $this->cor = $cor; }
    public function setKmAtual($km_atual) { $this->km_atual = $km_atual; }
    public function setCapacidadeTanque($capacidade_tanque) { $this->capacidade_tanque = $capacidade_tanque; }
    public function setTipoCombustivel($tipo_combustivel) { $this->tipo_combustivel = $tipo_combustivel; }
    public function setNumeroEixos($numero_eixos) { $this->numero_eixos = $numero_eixos; }
    public function setTipoVeiculo($tipo_veiculo) { $this->tipo_veiculo = $tipo_veiculo; }
    public function setStatusId($status_id) { $this->status_id = $status_id; }
    public function setEmpresaId($empresa_id) { $this->empresa_id = $empresa_id; }
    public function setDataCadastro($data_cadastro) { $this->data_cadastro = $data_cadastro; }
    public function setObservacoes($observacoes) { $this->observacoes = $observacoes; }

    public function toArray() {
        return [
            'id' => $this->id,
            'placa' => $this->placa,
            'marca' => $this->marca,
            'modelo' => $this->modelo,
            'ano_fabricacao' => $this->ano_fabricacao,
            'ano_modelo' => $this->ano_modelo,
            'chassi' => $this->chassi,
            'renavam' => $this->renavam,
            'cor' => $this->cor,
            'km_atual' => $this->km_atual,
            'capacidade_tanque' => $this->capacidade_tanque,
            'tipo_combustivel' => $this->tipo_combustivel,
            'numero_eixos' => $this->numero_eixos,
            'tipo_veiculo' => $this->tipo_veiculo,
            'status_id' => $this->status_id,
            'empresa_id' => $this->empresa_id,
            'data_cadastro' => $this->data_cadastro,
            'observacoes' => $this->observacoes
        ];
    }
} 