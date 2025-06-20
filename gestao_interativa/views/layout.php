<!DOCTYPE html>
<html>
<head>
    <title><?php echo isset($title) ? $title : 'Gestão Interativa'; ?></title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <h1>Layout Content</h1>
    </header>
    
    <main>
        <?php echo $content; ?>
    </main>
    
    <footer>
        <p>&copy; 2024 Sistema de Frotas</p>
    </footer>
</body>
</html> 