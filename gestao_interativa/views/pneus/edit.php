<!DOCTYPE html>
<html>
<head>
    <title>Editar Pneu</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <div class="container">
        <h1>Editar Pneu</h1>
        
        <form method="POST" action="?action=update&id=<?php echo $pneu->getId(); ?>">
            <div class="form-group">
                <label for="marca">Marca:</label>
                <input type="text" id="marca" name="marca" value="<?php echo htmlspecialchars($pneu->getMarca()); ?>" required class="form-control">
            </div>
            
            <div class="form-group">
                <label for="modelo">Modelo:</label>
                <input type="text" id="modelo" name="modelo" value="<?php echo htmlspecialchars($pneu->getModelo()); ?>" required class="form-control">
            </div>
            
            <div class="form-group">
                <label for="medida">Medida:</label>
                <input type="text" id="medida" name="medida" value="<?php echo htmlspecialchars($pneu->getMedida()); ?>" required class="form-control">
            </div>
            
            <div class="form-group">
                <label for="status_id">Status:</label>
                <select id="status_id" name="status_id" required class="form-control">
                    <?php foreach ($statuses as $status): ?>
                    <option value="<?php echo $status->getId(); ?>" <?php echo $status->getId() == $pneu->getStatusId() ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($status->getNome()); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary">Atualizar</button>
            <a href="?action=index" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</body>
</html> 