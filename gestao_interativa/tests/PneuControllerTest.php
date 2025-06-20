<?php
use PHPUnit\Framework\TestCase;
use GestaoInterativa\Controllers\PneuController;
use GestaoInterativa\Repositories\PneuRepository;

class PneuControllerTest extends TestCase {
    public function testIndexReturnsView() {
        $repo = $this->createMock(PneuRepository::class);
        $repo->method('getAll')->willReturn([]);
        $controller = new PneuController($repo);
        ob_start();
        $controller->index();
        $output = ob_get_clean();
        $this->assertStringContainsString('Listagem de Pneus', $output);
    }
} 