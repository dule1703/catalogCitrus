<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registracija - CitrusApp</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="/css/style.css" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="/images/Fresh_citrus.svg">
   
</head>
<body>
    <div class="header">
        <h1 class="text-2xl font-bold">CitrusApp</h1>
    </div>

    <main class="flex items-center justify-center min-h-screen p-4">
        <?php require $viewPath; ?>
    </main>

    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> CitrusApp</p>
    </div>
     <script src="/js/app.js"></script>
</body>
</html>