<?php
session_start();

// Si ya está logueado, redirigir
if (isset($_SESSION['usuario_id'])) {
    if ($_SESSION['rol'] == 'administrador') {
        header('Location: index.php');
    } else {
        header('Location: postulaciones.php');
    }
    exit;
}

require_once 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = trim($_POST['usuario']);
    $contraseña = trim($_POST['contraseña']);
    
    if (!empty($usuario) && !empty($contraseña)) {
        // Buscar usuario en la base de datos
        $sql = "SELECT id, nombre, usuario, contraseña, rol FROM usuarios WHERE usuario = ? AND activo = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $usuario);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            // Verificar contraseña (en texto plano como solicitas)
            if ($contraseña === $user['contraseña']) {
                // Login exitoso
                $_SESSION['usuario_id'] = $user['id'];
                $_SESSION['usuario_nombre'] = $user['nombre'];
                $_SESSION['usuario_usuario'] = $user['usuario'];
                $_SESSION['rol'] = $user['rol'];
                
                // Redirigir según el rol
                if ($user['rol'] == 'administrador') {
                    header('Location: index.php');
                } else {
                    header('Location: postulaciones.php');
                }
                exit;
            } else {
                $error = 'Usuario o contraseña incorrectos';
            }
        } else {
            $error = 'Usuario o contraseña incorrectos';
        }
    } else {
        $error = 'Por favor ingrese usuario y contraseña';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Sistema RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            /* background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); */
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Arial, sans-serif;
        }
        
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
            margin: 20px;
        }
        
        .login-header {
            /* background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); */
            background: linear-gradient(135deg, #b6b6b6 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .login-header h2 {
            margin: 0;
            font-weight: 600;
        }
        
        .login-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }
        
        .login-body {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .form-control {
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-login {
            /* background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); */
            background: linear-gradient(135deg, #b6b6b6 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .demo-info {
            background: #e7f3ff;
            border: 1px solid #b3d7ff;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            font-size: 14px;
        }
        
        .demo-info h6 {
            color: #0066cc;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .demo-user {
            background: white;
            padding: 8px;
            border-radius: 5px;
            margin: 5px 0;
            border-left: 3px solid #0066cc;
        }
        
        .icon {
            font-size: 3rem;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            
            <p>Iniciar Sesión</p>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert-error">
                    ❌ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="usuario" class="form-label">Usuario</label>
                    <input type="text" 
                           id="usuario" 
                           name="usuario" 
                           class="form-control" 
                           placeholder="Ingrese su usuario"
                           value="<?php echo isset($_POST['usuario']) ? htmlspecialchars($_POST['usuario']) : ''; ?>"
                           required>
                </div>
                
                <div class="form-group">
                    <label for="contraseña" class="form-label">Contraseña</label>
                    <input type="password" 
                           id="contraseña" 
                           name="contraseña" 
                           class="form-control" 
                           placeholder="Ingrese su contraseña"
                           required>
                </div>
                
                <button type="submit" class="btn btn-login">
                    🔐 Iniciar Sesión
                </button>
            </form>            
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-focus en el campo usuario
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('usuario').focus();
        });
        
        // Efecto visual en el formulario
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        });
    </script>
</body>
</html>