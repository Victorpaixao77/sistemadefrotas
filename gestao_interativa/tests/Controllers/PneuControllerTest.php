<?php

namespace GestaoInterativa\Tests\Controllers;

use PHPUnit\Framework\TestCase;
use GestaoInterativa\Controllers\PneuController;
use GestaoInterativa\Repositories\PneuRepository;
use GestaoInterativa\Repositories\VeiculoRepository;
use GestaoInterativa\Repositories\StatusRepository;

class PneuControllerTest extends TestCase
{
    private $pneuController;
    private $pneuRepository;
    private $veiculoRepository;
    private $statusRepository;

    protected function setUp(): void
    {
        $this->pneuRepository = $this->createMock(PneuRepository::class);
        $this->veiculoRepository = $this->createMock(VeiculoRepository::class);
        $this->statusRepository = $this->createMock(StatusRepository::class);

        $this->pneuController = new PneuController(
            $this->pneuRepository,
            $this->veiculoRepository,
            $this->statusRepository
        );
    }

    public function testIndexReturnsView()
    {
        $this->pneuRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $this->veiculoRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $this->statusRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $result = $this->pneuController->index();

        $this->assertIsString($result);
        $this->assertStringContainsString('<!DOCTYPE html>', $result);
    }

    public function testShowReturnsView()
    {
        $id = 1;
        $pneu = [
            'id' => $id,
            'numero_serie' => '123456',
            'marca' => 'Teste',
            'modelo' => 'Teste',
            'status_id' => 1
        ];

        $this->pneuRepository->expects($this->once())
            ->method('findById')
            ->with($id)
            ->willReturn($pneu);

        $result = $this->pneuController->show($id);

        $this->assertIsString($result);
        $this->assertStringContainsString('<!DOCTYPE html>', $result);
        $this->assertStringContainsString($pneu['numero_serie'], $result);
    }

    public function testCreateReturnsView()
    {
        $this->veiculoRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $this->statusRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $result = $this->pneuController->create();

        $this->assertIsString($result);
        $this->assertStringContainsString('<!DOCTYPE html>', $result);
        $this->assertStringContainsString('<form', $result);
    }

    public function testStoreValidatesAndSaves()
    {
        $data = [
            'numero_serie' => '123456',
            'marca' => 'Teste',
            'modelo' => 'Teste',
            'status_id' => 1
        ];

        $this->pneuRepository->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($pneu) use ($data) {
                return $pneu->getNumeroSerie() === $data['numero_serie']
                    && $pneu->getMarca() === $data['marca']
                    && $pneu->getModelo() === $data['modelo']
                    && $pneu->getStatusId() === $data['status_id'];
            }))
            ->willReturn(true);

        $result = $this->pneuController->store($data);

        $this->assertIsString($result);
        $this->assertStringContainsString('redirect', $result);
    }

    public function testEditReturnsView()
    {
        $id = 1;
        $pneu = [
            'id' => $id,
            'numero_serie' => '123456',
            'marca' => 'Teste',
            'modelo' => 'Teste',
            'status_id' => 1
        ];

        $this->pneuRepository->expects($this->once())
            ->method('findById')
            ->with($id)
            ->willReturn($pneu);

        $this->veiculoRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $this->statusRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $result = $this->pneuController->edit($id);

        $this->assertIsString($result);
        $this->assertStringContainsString('<!DOCTYPE html>', $result);
        $this->assertStringContainsString($pneu['numero_serie'], $result);
    }

    public function testUpdateValidatesAndSaves()
    {
        $id = 1;
        $data = [
            'numero_serie' => '123456',
            'marca' => 'Teste',
            'modelo' => 'Teste',
            'status_id' => 1
        ];

        $this->pneuRepository->expects($this->once())
            ->method('update')
            ->with($id, $this->callback(function ($pneu) use ($data) {
                return $pneu->getNumeroSerie() === $data['numero_serie']
                    && $pneu->getMarca() === $data['marca']
                    && $pneu->getModelo() === $data['modelo']
                    && $pneu->getStatusId() === $data['status_id'];
            }))
            ->willReturn(true);

        $result = $this->pneuController->update($id, $data);

        $this->assertIsString($result);
        $this->assertStringContainsString('redirect', $result);
    }

    public function testDestroyDeletesPneu()
    {
        $id = 1;

        $this->pneuRepository->expects($this->once())
            ->method('delete')
            ->with($id)
            ->willReturn(true);

        $result = $this->pneuController->destroy($id);

        $this->assertIsString($result);
        $this->assertStringContainsString('redirect', $result);
    }
} 