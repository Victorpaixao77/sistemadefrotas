<?php

namespace GestaoInterativa\Tests\Models;

use PHPUnit\Framework\TestCase;
use GestaoInterativa\Models\Veiculo;

class VeiculoTest extends TestCase
{
    private $veiculo;

    protected function setUp(): void
    {
        $this->veiculo = new Veiculo();
    }

    public function testVeiculoCanBeCreated()
    {
        $this->assertInstanceOf(Veiculo::class, $this->veiculo);
    }

    public function testVeiculoCanSetAndGetId()
    {
        $id = 1;
        $this->veiculo->setId($id);
        $this->assertEquals($id, $this->veiculo->getId());
    }

    public function testVeiculoCanSetAndGetPlaca()
    {
        $placa = 'ABC1234';
        $this->veiculo->setPlaca($placa);
        $this->assertEquals($placa, $this->veiculo->getPlaca());
    }

    public function testVeiculoCanSetAndGetMarca()
    {
        $marca = 'Teste';
        $this->veiculo->setMarca($marca);
        $this->assertEquals($marca, $this->veiculo->getMarca());
    }

    public function testVeiculoCanSetAndGetModelo()
    {
        $modelo = 'Teste';
        $this->veiculo->setModelo($modelo);
        $this->assertEquals($modelo, $this->veiculo->getModelo());
    }

    public function testVeiculoCanSetAndGetAno()
    {
        $ano = 2020;
        $this->veiculo->setAno($ano);
        $this->assertEquals($ano, $this->veiculo->getAno());
    }

    public function testVeiculoCanSetAndGetEmpresaId()
    {
        $empresaId = 1;
        $this->veiculo->setEmpresaId($empresaId);
        $this->assertEquals($empresaId, $this->veiculo->getEmpresaId());
    }

    public function testVeiculoCanSetAndGetChassi()
    {
        $chassi = '12345678901234567';
        $this->veiculo->setChassi($chassi);
        $this->assertEquals($chassi, $this->veiculo->getChassi());
    }

    public function testVeiculoCanSetAndGetRenavam()
    {
        $renavam = '12345678901';
        $this->veiculo->setRenavam($renavam);
        $this->assertEquals($renavam, $this->veiculo->getRenavam());
    }

    public function testVeiculoCanSetAndGetCor()
    {
        $cor = 'Preto';
        $this->veiculo->setCor($cor);
        $this->assertEquals($cor, $this->veiculo->getCor());
    }

    public function testVeiculoCanSetAndGetTipo()
    {
        $tipo = 'Caminhão';
        $this->veiculo->setTipo($tipo);
        $this->assertEquals($tipo, $this->veiculo->getTipo());
    }

    public function testVeiculoCanSetAndGetStatus()
    {
        $status = 'Ativo';
        $this->veiculo->setStatus($status);
        $this->assertEquals($status, $this->veiculo->getStatus());
    }

    public function testVeiculoCanSetAndGetObservacoes()
    {
        $observacoes = 'Teste de observações';
        $this->veiculo->setObservacoes($observacoes);
        $this->assertEquals($observacoes, $this->veiculo->getObservacoes());
    }

    public function testVeiculoCanBeHydrated()
    {
        $data = [
            'id' => 1,
            'placa' => 'ABC1234',
            'marca' => 'Teste',
            'modelo' => 'Teste',
            'ano' => 2020,
            'empresa_id' => 1,
            'chassi' => '12345678901234567',
            'renavam' => '12345678901',
            'cor' => 'Preto',
            'tipo' => 'Caminhão',
            'status' => 'Ativo',
            'observacoes' => 'Teste de observações'
        ];

        $this->veiculo->hydrate($data);

        $this->assertEquals($data['id'], $this->veiculo->getId());
        $this->assertEquals($data['placa'], $this->veiculo->getPlaca());
        $this->assertEquals($data['marca'], $this->veiculo->getMarca());
        $this->assertEquals($data['modelo'], $this->veiculo->getModelo());
        $this->assertEquals($data['ano'], $this->veiculo->getAno());
        $this->assertEquals($data['empresa_id'], $this->veiculo->getEmpresaId());
        $this->assertEquals($data['chassi'], $this->veiculo->getChassi());
        $this->assertEquals($data['renavam'], $this->veiculo->getRenavam());
        $this->assertEquals($data['cor'], $this->veiculo->getCor());
        $this->assertEquals($data['tipo'], $this->veiculo->getTipo());
        $this->assertEquals($data['status'], $this->veiculo->getStatus());
        $this->assertEquals($data['observacoes'], $this->veiculo->getObservacoes());
    }

    public function testVeiculoCanBeConvertedToArray()
    {
        $data = [
            'id' => 1,
            'placa' => 'ABC1234',
            'marca' => 'Teste',
            'modelo' => 'Teste',
            'ano' => 2020,
            'empresa_id' => 1,
            'chassi' => '12345678901234567',
            'renavam' => '12345678901',
            'cor' => 'Preto',
            'tipo' => 'Caminhão',
            'status' => 'Ativo',
            'observacoes' => 'Teste de observações'
        ];

        $this->veiculo->hydrate($data);
        $result = $this->veiculo->toArray();

        $this->assertEquals($data, $result);
    }
} 