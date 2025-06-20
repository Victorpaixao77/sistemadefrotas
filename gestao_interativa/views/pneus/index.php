<!DOCTYPE html>
<html>
<head>
    <title>Lista de Pneus</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <div class="container">
        <h1>Lista de Pneus</h1>
        
        <a href="?action=create" class="btn btn-primary">Novo Pneu</a>
        
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Marca</th>
                    <th>Modelo</th>
                    <th>Medida</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pneus as $pneu): ?>
                <tr>
                    <td><?php echo $pneu->getId(); ?></td>
                    <td><?php echo htmlspecialchars($pneu->getMarca()); ?></td>
                    <td><?php echo htmlspecialchars($pneu->getModelo()); ?></td>
                    <td><?php echo htmlspecialchars($pneu->getMedida()); ?></td>
                    <td><?php echo htmlspecialchars($pneu->getStatus()); ?></td>
                    <td>
                        <a href="?action=show&id=<?php echo $pneu->getId(); ?>" class="btn btn-sm btn-info">Ver</a>
                        <a href="?action=edit&id=<?php echo $pneu->getId(); ?>" class="btn btn-sm btn-warning">Editar</a>
                        <a href="?action=delete&id=<?php echo $pneu->getId(); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza?')">Excluir</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html> 