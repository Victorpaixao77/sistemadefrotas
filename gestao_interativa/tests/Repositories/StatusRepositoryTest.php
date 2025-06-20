<?php

namespace GestaoInterativa\Tests\Repositories;

use PHPUnit\Framework\TestCase;
use GestaoInterativa\Repositories\StatusRepository;
use GestaoInterativa\Models\Status;
use PDO;
use PDOStatement;

class StatusRepositoryTest extends TestCase
{
    private $pdo;
    private $statusRepository;

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(PDO::class);
        $this->statusRepository = new StatusRepository($this->pdo);
    }

    public function testFindAllReturnsArrayOfStatus()
    {
        $expectedStatus = [
            [
                'id' => 1,
                'nome' => 'Bom',
                'descricao' => 'Pneu em bom estado'
            ],
            [
                'id' => 2,
                'nome' => 'Regular',
                'descricao' => 'Pneu em estado regular'
            ]
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn($expectedStatus);

        $this->pdo->expects($this->once())
            ->method('query')
            ->with('SELECT * FROM status')
            ->willReturn($stmt);

        $result = $this->statusRepository->findAll();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(Status::class, $result[0]);
        $this->assertInstanceOf(Status::class, $result[1]);
    }

    public function testFindByIdReturnsStatus()
    {
        $id = 1;
        $expectedStatus = [
            'id' => $id,
            'nome' => 'Bom',
            'descricao' => 'Pneu em bom estado'
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('fetch')
            ->willReturn($expectedStatus);

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with('SELECT * FROM status WHERE id = ?')
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with([$id]);

        $result = $this->statusRepository->findById($id);

        $this->assertInstanceOf(Status::class, $result);
        $this->assertEquals($expectedStatus['nome'], $result->getNome());
    }

    public function testCreateSavesStatus()
    {
        $status = new Status();
        $status->setNome('Bom');
        $status->setDescricao('Pneu em bom estado');

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with([
                $status->getNome(),
                $status->getDescricao()
            ]);

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with('INSERT INTO status (nome, descricao) VALUES (?, ?)')
            ->willReturn($stmt);

        $this->pdo->expects($this->once())
            ->method('lastInsertId')
            ->willReturn(1);

        $result = $this->statusRepository->create($status);

        $this->assertTrue($result);
    }

    public function testUpdateSavesStatus()
    {
        $id = 1;
        $status = new Status();
        $status->setId($id);
        $status->setNome('Bom');
        $status->setDescricao('Pneu em bom estado');

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with([
                $status->getNome(),
                $status->getDescricao(),
                $id
            ]);

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with('UPDATE status SET nome = ?, descricao = ? WHERE id = ?')
            ->willReturn($stmt);

        $result = $this->statusRepository->update($id, $status);

        $this->assertTrue($result);
    }

    public function testDeleteRemovesStatus()
    {
        $id = 1;

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with([$id]);

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with('DELETE FROM status WHERE id = ?')
            ->willReturn($stmt);

        $result = $this->statusRepository->delete($id);

        $this->assertTrue($result);
    }

    public function testFindByNomeReturnsStatus()
    {
        $nome = 'Bom';
        $expectedStatus = [
            'id' => 1,
            'nome' => $nome,
            'descricao' => 'Pneu em bom estado'
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('fetch')
            ->willReturn($expectedStatus);

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with('SELECT * FROM status WHERE nome = ?')
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with([$nome]);

        $result = $this->statusRepository->findByNome($nome);

        $this->assertInstanceOf(Status::class, $result);
        $this->assertEquals($expectedStatus['nome'], $result->getNome());
    }
} 