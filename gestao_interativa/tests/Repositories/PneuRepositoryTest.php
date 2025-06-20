<?php

namespace GestaoInterativa\Tests\Repositories;

use PHPUnit\Framework\TestCase;
use GestaoInterativa\Repositories\PneuRepository;
use GestaoInterativa\Models\Pneu;
use PDO;
use PDOStatement;

class PneuRepositoryTest extends TestCase
{
    private $pdo;
    private $pneuRepository;

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(PDO::class);
        $this->pneuRepository = new PneuRepository($this->pdo);
    }

    public function testFindAllReturnsArrayOfPneus()
    {
        $expectedPneus = [
            [
                'id' => 1,
                'numero_serie' => '123456',
                'marca' => 'Teste',
                'modelo' => 'Teste',
                'status_id' => 1
            ],
            [
                'id' => 2,
                'numero_serie' => '789012',
                'marca' => 'Teste2',
                'modelo' => 'Teste2',
                'status_id' => 2
            ]
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn($expectedPneus);

        $this->pdo->expects($this->once())
            ->method('query')
            ->with('SELECT * FROM pneus')
            ->willReturn($stmt);

        $result = $this->pneuRepository->findAll();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(Pneu::class, $result[0]);
        $this->assertInstanceOf(Pneu::class, $result[1]);
    }

    public function testFindByIdReturnsPneu()
    {
        $id = 1;
        $expectedPneu = [
            'id' => $id,
            'numero_serie' => '123456',
            'marca' => 'Teste',
            'modelo' => 'Teste',
            'status_id' => 1
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('fetch')
            ->willReturn($expectedPneu);

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with('SELECT * FROM pneus WHERE id = ?')
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with([$id]);

        $result = $this->pneuRepository->findById($id);

        $this->assertInstanceOf(Pneu::class, $result);
        $this->assertEquals($expectedPneu['numero_serie'], $result->getNumeroSerie());
    }

    public function testCreateSavesPneu()
    {
        $pneu = new Pneu();
        $pneu->setNumeroSerie('123456');
        $pneu->setMarca('Teste');
        $pneu->setModelo('Teste');
        $pneu->setStatusId(1);

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with([
                $pneu->getNumeroSerie(),
                $pneu->getMarca(),
                $pneu->getModelo(),
                $pneu->getStatusId()
            ]);

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with('INSERT INTO pneus (numero_serie, marca, modelo, status_id) VALUES (?, ?, ?, ?)')
            ->willReturn($stmt);

        $this->pdo->expects($this->once())
            ->method('lastInsertId')
            ->willReturn(1);

        $result = $this->pneuRepository->create($pneu);

        $this->assertTrue($result);
    }

    public function testUpdateSavesPneu()
    {
        $id = 1;
        $pneu = new Pneu();
        $pneu->setId($id);
        $pneu->setNumeroSerie('123456');
        $pneu->setMarca('Teste');
        $pneu->setModelo('Teste');
        $pneu->setStatusId(1);

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with([
                $pneu->getNumeroSerie(),
                $pneu->getMarca(),
                $pneu->getModelo(),
                $pneu->getStatusId(),
                $id
            ]);

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with('UPDATE pneus SET numero_serie = ?, marca = ?, modelo = ?, status_id = ? WHERE id = ?')
            ->willReturn($stmt);

        $result = $this->pneuRepository->update($id, $pneu);

        $this->assertTrue($result);
    }

    public function testDeleteRemovesPneu()
    {
        $id = 1;

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with([$id]);

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with('DELETE FROM pneus WHERE id = ?')
            ->willReturn($stmt);

        $result = $this->pneuRepository->delete($id);

        $this->assertTrue($result);
    }

    public function testFindByVeiculoIdReturnsArrayOfPneus()
    {
        $veiculoId = 1;
        $expectedPneus = [
            [
                'id' => 1,
                'numero_serie' => '123456',
                'marca' => 'Teste',
                'modelo' => 'Teste',
                'status_id' => 1,
                'veiculo_id' => $veiculoId
            ]
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn($expectedPneus);

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with('SELECT * FROM pneus WHERE veiculo_id = ?')
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with([$veiculoId]);

        $result = $this->pneuRepository->findByVeiculoId($veiculoId);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(Pneu::class, $result[0]);
    }

    public function testFindByStatusIdReturnsArrayOfPneus()
    {
        $statusId = 1;
        $expectedPneus = [
            [
                'id' => 1,
                'numero_serie' => '123456',
                'marca' => 'Teste',
                'modelo' => 'Teste',
                'status_id' => $statusId
            ]
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn($expectedPneus);

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with('SELECT * FROM pneus WHERE status_id = ?')
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with([$statusId]);

        $result = $this->pneuRepository->findByStatusId($statusId);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(Pneu::class, $result[0]);
    }
} 