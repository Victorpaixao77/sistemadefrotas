<!DOCTYPE html>
<html>
<head>
    <title>Detalhes do Pneu</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <div class="container">
        <h1>Detalhes do Pneu</h1>
        
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Pneu #<?php echo $pneu->getId(); ?></h5>
                
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Marca:</strong> <?php echo htmlspecialchars($pneu->getMarca()); ?></p>
                        <p><strong>Modelo:</strong> <?php echo htmlspecialchars($pneu->getModelo()); ?></p>
                        <p><strong>Medida:</strong> <?php echo htmlspecialchars($pneu->getMedida()); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Status:</strong> <?php echo htmlspecialchars($pneu->getStatus()); ?></p>
                        <p><strong>Data de Criação:</strong> <?php echo $pneu->getCreatedAt(); ?></p>
                        <p><strong>Última Atualização:</strong> <?php echo $pneu->getUpdatedAt(); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-3">
            <a href="?action=edit&id=<?php echo $pneu->getId(); ?>" class="btn btn-warning">Editar</a>
            <a href="?action=index" class="btn btn-secondary">Voltar</a>
        </div>
    </div>
</body>
</html> 