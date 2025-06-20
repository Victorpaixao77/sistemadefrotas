<?php

namespace GestaoInterativa\Tests\Repositories;

use PHPUnit\Framework\TestCase;
use GestaoInterativa\Repositories\VeiculoRepository;
use GestaoInterativa\Models\Veiculo;
use PDO;
use PDOStatement;

class VeiculoRepositoryTest extends TestCase
{
    private $pdo;
    private $veiculoRepository;

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(PDO::class);
        $this->veiculoRepository = new VeiculoRepository($this->pdo);
    }

    public function testFindAllReturnsArrayOfVeiculos()
    {
        $expectedVeiculos = [
            [
                'id' => 1,
                'placa' => 'ABC1234',
                'marca' => 'Teste',
                'modelo' => 'Teste',
                'ano' => 2020,
                'empresa_id' => 1
            ],
            [
                'id' => 2,
                'placa' => 'DEF5678',
                'marca' => 'Teste2',
                'modelo' => 'Teste2',
                'ano' => 2021,
                'empresa_id' => 1
            ]
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn($expectedVeiculos);

        $this->pdo->expects($this->once())
            ->method('query')
            ->with('SELECT * FROM veiculos')
            ->willReturn($stmt);

        $result = $this->veiculoRepository->findAll();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(Veiculo::class, $result[0]);
        $this->assertInstanceOf(Veiculo::class, $result[1]);
    }

    public function testFindByIdReturnsVeiculo()
    {
        $id = 1;
        $expectedVeiculo = [
            'id' => $id,
            'placa' => 'ABC1234',
            'marca' => 'Teste',
            'modelo' => 'Teste',
            'ano' => 2020,
            'empresa_id' => 1
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('fetch')
            ->willReturn($expectedVeiculo);

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with('SELECT * FROM veiculos WHERE id = ?')
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with([$id]);

        $result = $this->veiculoRepository->findById($id);

        $this->assertInstanceOf(Veiculo::class, $result);
        $this->assertEquals($expectedVeiculo['placa'], $result->getPlaca());
    }

    public function testCreateSavesVeiculo()
    {
        $veiculo = new Veiculo();
        $veiculo->setPlaca('ABC1234');
        $veiculo->setMarca('Teste');
        $veiculo->setModelo('Teste');
        $veiculo->setAno(2020);
        $veiculo->setEmpresaId(1);

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with([
                $veiculo->getPlaca(),
                $veiculo->getMarca(),
                $veiculo->getModelo(),
                $veiculo->getAno(),
                $veiculo->getEmpresaId()
            ]);

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with('INSERT INTO veiculos (placa, marca, modelo, ano, empresa_id) VALUES (?, ?, ?, ?, ?)')
            ->willReturn($stmt);

        $this->pdo->expects($this->once())
            ->method('lastInsertId')
            ->willReturn(1);

        $result = $this->veiculoRepository->create($veiculo);

        $this->assertTrue($result);
    }

    public function testUpdateSavesVeiculo()
    {
        $id = 1;
        $veiculo = new Veiculo();
        $veiculo->setId($id);
        $veiculo->setPlaca('ABC1234');
        $veiculo->setMarca('Teste');
        $veiculo->setModelo('Teste');
        $veiculo->setAno(2020);
        $veiculo->setEmpresaId(1);

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with([
                $veiculo->getPlaca(),
                $veiculo->getMarca(),
                $veiculo->getModelo(),
                $veiculo->getAno(),
                $veiculo->getEmpresaId(),
                $id
            ]);

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with('UPDATE veiculos SET placa = ?, marca = ?, modelo = ?, ano = ?, empresa_id = ? WHERE id = ?')
            ->willReturn($stmt);

        $result = $this->veiculoRepository->update($id, $veiculo);

        $this->assertTrue($result);
    }

    public function testDeleteRemovesVeiculo()
    {
        $id = 1;

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with([$id]);

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with('DELETE FROM veiculos WHERE id = ?')
            ->willReturn($stmt);

        $result = $this->veiculoRepository->delete($id);

        $this->assertTrue($result);
    }

    public function testFindByEmpresaIdReturnsArrayOfVeiculos()
    {
        $empresaId = 1;
        $expectedVeiculos = [
            [
                'id' => 1,
                'placa' => 'ABC1234',
                'marca' => 'Teste',
                'modelo' => 'Teste',
                'ano' => 2020,
                'empresa_id' => $empresaId
            ]
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn($expectedVeiculos);

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with('SELECT * FROM veiculos WHERE empresa_id = ?')
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with([$empresaId]);

        $result = $this->veiculoRepository->findByEmpresaId($empresaId);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(Veiculo::class, $result[0]);
    }

    public function testFindByPlacaReturnsVeiculo()
    {
        $placa = 'ABC1234';
        $expectedVeiculo = [
            'id' => 1,
            'placa' => $placa,
            'marca' => 'Teste',
            'modelo' => 'Teste',
            'ano' => 2020,
            'empresa_id' => 1
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('fetch')
            ->willReturn($expectedVeiculo);

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with('SELECT * FROM veiculos WHERE placa = ?')
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with([$placa]);

        $result = $this->veiculoRepository->findByPlaca($placa);

        $this->assertInstanceOf(Veiculo::class, $result);
        $this->assertEquals($expectedVeiculo['placa'], $result->getPlaca());
    }
} 