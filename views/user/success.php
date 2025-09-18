<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registracija uspešna</title>
    <style>

    </style>
</head>
<body>
    <div class="success-container">
        <h2>Registracija uspešna!</h2>
        <p>Bićete preusmereni na stranicu za login za 5 sekundi...</p>
    </div>

    <script>
        // Dinamički uzmi bazni URL iz .env
        const baseUrl = '<?php echo isset($_ENV['APP_URL']) ? $_ENV['APP_URL'] : 'http://testcitrus'; ?>';
        setTimeout(() => {
            window.location.href = baseUrl + '/'; 
        }, 4000);
    </script>
</body>
</html>