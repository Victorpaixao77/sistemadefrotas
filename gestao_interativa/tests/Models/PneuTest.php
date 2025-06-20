<?php

namespace GestaoInterativa\Tests\Models;

use PHPUnit\Framework\TestCase;
use GestaoInterativa\Models\Pneu;

class PneuTest extends TestCase
{
    private $pneu;

    protected function setUp(): void
    {
        $this->pneu = new Pneu();
    }

    public function testPneuCanBeCreated()
    {
        $this->assertInstanceOf(Pneu::class, $this->pneu);
    }

    public function testPneuCanSetAndGetId()
    {
        $id = 1;
        $this->pneu->setId($id);
        $this->assertEquals($id, $this->pneu->getId());
    }

    public function testPneuCanSetAndGetNumeroSerie()
    {
        $numeroSerie = '123456';
        $this->pneu->setNumeroSerie($numeroSerie);
        $this->assertEquals($numeroSerie, $this->pneu->getNumeroSerie());
    }

    public function testPneuCanSetAndGetMarca()
    {
        $marca = 'Teste';
        $this->pneu->setMarca($marca);
        $this->assertEquals($marca, $this->pneu->getMarca());
    }

    public function testPneuCanSetAndGetModelo()
    {
        $modelo = 'Teste';
        $this->pneu->setModelo($modelo);
        $this->assertEquals($modelo, $this->pneu->getModelo());
    }

    public function testPneuCanSetAndGetStatusId()
    {
        $statusId = 1;
        $this->pneu->setStatusId($statusId);
        $this->assertEquals($statusId, $this->pneu->getStatusId());
    }

    public function testPneuCanSetAndGetVeiculoId()
    {
        $veiculoId = 1;
        $this->pneu->setVeiculoId($veiculoId);
        $this->assertEquals($veiculoId, $this->pneu->getVeiculoId());
    }

    public function testPneuCanSetAndGetPosicaoId()
    {
        $posicaoId = 1;
        $this->pneu->setPosicaoId($posicaoId);
        $this->assertEquals($posicaoId, $this->pneu->getPosicaoId());
    }

    public function testPneuCanSetAndGetDataAlocacao()
    {
        $dataAlocacao = '2024-01-01';
        $this->pneu->setDataAlocacao($dataAlocacao);
        $this->assertEquals($dataAlocacao, $this->pneu->getDataAlocacao());
    }

    public function testPneuCanSetAndGetDataDesalocacao()
    {
        $dataDesalocacao = '2024-01-01';
        $this->pneu->setDataDesalocacao($dataDesalocacao);
        $this->assertEquals($dataDesalocacao, $this->pneu->getDataDesalocacao());
    }

    public function testPneuCanSetAndGetKmAlocacao()
    {
        $kmAlocacao = 1000;
        $this->pneu->setKmAlocacao($kmAlocacao);
        $this->assertEquals($kmAlocacao, $this->pneu->getKmAlocacao());
    }

    public function testPneuCanSetAndGetKmDesalocacao()
    {
        $kmDesalocacao = 2000;
        $this->pneu->setKmDesalocacao($kmDesalocacao);
        $this->assertEquals($kmDesalocacao, $this->pneu->getKmDesalocacao());
    }

    public function testPneuCanSetAndGetObservacoes()
    {
        $observacoes = 'Teste de observações';
        $this->pneu->setObservacoes($observacoes);
        $this->assertEquals($observacoes, $this->pneu->getObservacoes());
    }

    public function testPneuCanBeHydrated()
    {
        $data = [
            'id' => 1,
            'numero_serie' => '123456',
            'marca' => 'Teste',
            'modelo' => 'Teste',
            'status_id' => 1,
            'veiculo_id' => 1,
            'posicao_id' => 1,
            'data_alocacao' => '2024-01-01',
            'data_desalocacao' => '2024-01-01',
            'km_alocacao' => 1000,
            'km_desalocacao' => 2000,
            'observacoes' => 'Teste de observações'
        ];

        $this->pneu->hydrate($data);

        $this->assertEquals($data['id'], $this->pneu->getId());
        $this->assertEquals($data['numero_serie'], $this->pneu->getNumeroSerie());
        $this->assertEquals($data['marca'], $this->pneu->getMarca());
        $this->assertEquals($data['modelo'], $this->pneu->getModelo());
        $this->assertEquals($data['status_id'], $this->pneu->getStatusId());
        $this->assertEquals($data['veiculo_id'], $this->pneu->getVeiculoId());
        $this->assertEquals($data['posicao_id'], $this->pneu->getPosicaoId());
        $this->assertEquals($data['data_alocacao'], $this->pneu->getDataAlocacao());
        $this->assertEquals($data['data_desalocacao'], $this->pneu->getDataDesalocacao());
        $this->assertEquals($data['km_alocacao'], $this->pneu->getKmAlocacao());
        $this->assertEquals($data['km_desalocacao'], $this->pneu->getKmDesalocacao());
        $this->assertEquals($data['observacoes'], $this->pneu->getObservacoes());
    }

    public function testPneuCanBeConvertedToArray()
    {
        $data = [
            'id' => 1,
            'numero_serie' => '123456',
            'marca' => 'Teste',
            'modelo' => 'Teste',
            'status_id' => 1,
            'veiculo_id' => 1,
            'posicao_id' => 1,
            'data_alocacao' => '2024-01-01',
            'data_desalocacao' => '2024-01-01',
            'km_alocacao' => 1000,
            'km_desalocacao' => 2000,
            'observacoes' => 'Teste de observações'
        ];

        $this->pneu->hydrate($data);
        $result = $this->pneu->toArray();

        $this->assertEquals($data, $result);
    }
} 