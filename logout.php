<?php
session_start();
session_destroy();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cerrando Sesión...</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Arial, sans-serif;
        }
        
        .logout-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 40px;
            text-align: center;
            max-width: 400px;
            width: 100%;
            margin: 20px;
        }
        
        .icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }
        
        .spinner-border {
            width: 3rem;
            height: 3rem;
            margin: 20px 0;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-size: 16px;
            font-weight: 600;
            color: white;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="icon">👋</div>
        <h3>Sesión Cerrada</h3>
        <p class="text-muted">Has cerrado sesión exitosamente del Sistema RH</p>
        
        <div class="spinner-border text-primary" role="status" id="spinner">
            <span class="visually-hidden">Redirigiendo...</span>
        </div>
        
        <p><small class="text-muted">Redirigiendo al login en <span id="countdown">3</span> segundos...</small></p>
        
        <div style="margin-top: 20px;">
            <a href="login.php" class="btn-login">
                🔐 Iniciar Sesión Nuevamente
            </a>
        </div>
    </div>

    <script>
        // Countdown y redirección automática
        let countdown = 3;
        const countdownElement = document.getElementById('countdown');
        const spinner = document.getElementById('spinner');
        
        const timer = setInterval(() => {
            countdown--;
            countdownElement.textContent = countdown;
            
            if (countdown <= 0) {
                clearInterval(timer);
                spinner.style.display = 'none';
                window.location.href = 'login.php';
            }
        }, 1000);
    </script>
</body>
</html>