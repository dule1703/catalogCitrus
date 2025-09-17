<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registracija uspešna</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background-color: #f0f0f0;
        }
        .success-container {
            text-align: center;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
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