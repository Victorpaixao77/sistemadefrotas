<?php
namespace GestaoInterativa\Controllers;

use GestaoInterativa\Repositories\PneuRepository;
use GestaoInterativa\Utils\Validator;
use GestaoInterativa\Exceptions\ValidationException;

class PneuController {
    private $repository;

    public function __construct(PneuRepository $repository) {
        $this->repository = $repository;
    }

    public function index() {
        $pneus = $this->repository->getAll();
        require __DIR__ . '/../../views/pneus/index.php';
    }

    public function show($id) {
        $pneu = $this->repository->find($id);
        if (!$pneu) {
            throw new \GestaoInterativa\Exceptions\NotFoundException('Pneu não encontrado');
        }
        require __DIR__ . '/../../views/pneus/show.php';
    }

    public function create() {
        require __DIR__ . '/../../views/pneus/create.php';
    }

    public function store($data) {
        $validator = new Validator($data, [
            'numero_serie' => 'required',
            'marca' => 'required',
            'modelo' => 'required',
            'status_id' => 'required|integer',
        ]);
        $validator->validate();
        $this->repository->create($data);
        header('Location: /gestao_interativa/pneus');
        exit;
    }

    public function edit($id) {
        $pneu = $this->repository->find($id);
        if (!$pneu) {
            throw new \GestaoInterativa\Exceptions\NotFoundException('Pneu não encontrado');
        }
        require __DIR__ . '/../../views/pneus/edit.php';
    }

    public function update($id, $data) {
        $validator = new Validator($data, [
            'numero_serie' => 'required',
            'marca' => 'required',
            'modelo' => 'required',
            'status_id' => 'required|integer',
        ]);
        $validator->validate();
        $this->repository->update($id, $data);
        header('Location: /gestao_interativa/pneus');
        exit;
    }

    public function delete($id) {
        $this->repository->delete($id);
        header('Location: /gestao_interativa/pneus');
        exit;
    }
} 