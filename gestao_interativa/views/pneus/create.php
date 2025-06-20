<!DOCTYPE html>
<html>
<head>
    <title>Novo Pneu</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <div class="container">
        <h1>Novo Pneu</h1>
        
        <form method="POST" action="?action=store">
            <div class="form-group">
                <label for="marca">Marca:</label>
                <input type="text" id="marca" name="marca" required class="form-control">
            </div>
            
            <div class="form-group">
                <label for="modelo">Modelo:</label>
                <input type="text" id="modelo" name="modelo" required class="form-control">
            </div>
            
            <div class="form-group">
                <label for="medida">Medida:</label>
                <input type="text" id="medida" name="medida" required class="form-control">
            </div>
            
            <div class="form-group">
                <label for="status_id">Status:</label>
                <select id="status_id" name="status_id" required class="form-control">
                    <?php foreach ($statuses as $status): ?>
                    <option value="<?php echo $status->getId(); ?>">
                        <?php echo htmlspecialchars($status->getNome()); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary">Salvar</button>
            <a href="?action=index" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</body>
</html> 