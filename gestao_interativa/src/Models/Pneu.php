<?php
namespace GestaoInterativa\Models;

class Pneu {
    private $id;
    private $numero_serie;
    private $marca;
    private $modelo;
    private $medida;
    private $sulco_inicial;
    private $dot;
    private $km_instalacao;
    private $data_instalacao;
    private $vida_util_km;
    private $numero_recapagens;
    private $data_ultima_recapagem;
    private $lote;
    private $data_entrada;
    private $observacoes;
    private $status_id;
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
    public function getNumeroSerie() { return $this->numero_serie; }
    public function getMarca() { return $this->marca; }
    public function getModelo() { return $this->modelo; }
    public function getMedida() { return $this->medida; }
    public function getSulcoInicial() { return $this->sulco_inicial; }
    public function getDot() { return $this->dot; }
    public function getKmInstalacao() { return $this->km_instalacao; }
    public function getDataInstalacao() { return $this->data_instalacao; }
    public function getVidaUtilKm() { return $this->vida_util_km; }
    public function getNumeroRecapagens() { return $this->numero_recapagens; }
    public function getDataUltimaRecapagem() { return $this->data_ultima_recapagem; }
    public function getLote() { return $this->lote; }
    public function getDataEntrada() { return $this->data_entrada; }
    public function getObservacoes() { return $this->observacoes; }
    public function getStatusId() { return $this->status_id; }
    public function getEmpresaId() { return $this->empresa_id; }

    // Setters
    public function setId($id) { $this->id = $id; }
    public function setNumeroSerie($numero_serie) { $this->numero_serie = $numero_serie; }
    public function setMarca($marca) { $this->marca = $marca; }
    public function setModelo($modelo) { $this->modelo = $modelo; }
    public function setMedida($medida) { $this->medida = $medida; }
    public function setSulcoInicial($sulco_inicial) { $this->sulco_inicial = $sulco_inicial; }
    public function setDot($dot) { $this->dot = $dot; }
    public function setKmInstalacao($km_instalacao) { $this->km_instalacao = $km_instalacao; }
    public function setDataInstalacao($data_instalacao) { $this->data_instalacao = $data_instalacao; }
    public function setVidaUtilKm($vida_util_km) { $this->vida_util_km = $vida_util_km; }
    public function setNumeroRecapagens($numero_recapagens) { $this->numero_recapagens = $numero_recapagens; }
    public function setDataUltimaRecapagem($data_ultima_recapagem) { $this->data_ultima_recapagem = $data_ultima_recapagem; }
    public function setLote($lote) { $this->lote = $lote; }
    public function setDataEntrada($data_entrada) { $this->data_entrada = $data_entrada; }
    public function setObservacoes($observacoes) { $this->observacoes = $observacoes; }
    public function setStatusId($status_id) { $this->status_id = $status_id; }
    public function setEmpresaId($empresa_id) { $this->empresa_id = $empresa_id; }

    public function toArray() {
        return [
            'id' => $this->id,
            'numero_serie' => $this->numero_serie,
            'marca' => $this->marca,
            'modelo' => $this->modelo,
            'medida' => $this->medida,
            'sulco_inicial' => $this->sulco_inicial,
            'dot' => $this->dot,
            'km_instalacao' => $this->km_instalacao,
            'data_instalacao' => $this->data_instalacao,
            'vida_util_km' => $this->vida_util_km,
            'numero_recapagens' => $this->numero_recapagens,
            'data_ultima_recapagem' => $this->data_ultima_recapagem,
            'lote' => $this->lote,
            'data_entrada' => $this->data_entrada,
            'observacoes' => $this->observacoes,
            'status_id' => $this->status_id,
            'empresa_id' => $this->empresa_id
        ];
    }
} 