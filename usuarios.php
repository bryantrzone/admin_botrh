<?php
require_once 'auth.php';
require_once 'db.php';

// Solo administradores pueden acceder
verificarRol(['administrador']);

// Función para obtener todos los usuarios
function obtenerUsuarios($conn) {
    $sql = "SELECT * FROM usuarios ORDER BY created_at DESC";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Función para obtener un usuario por ID
function obtenerUsuario($conn, $id) {
    $sql = "SELECT * FROM usuarios WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Función para crear usuario
function crearUsuario($conn, $nombre, $usuario, $contraseña, $rol, $activo) {
    $sql = "INSERT INTO usuarios (nombre, usuario, contraseña, rol, activo, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssi", $nombre, $usuario, $contraseña, $rol, $activo);
    return $stmt->execute();
}

// Función para actualizar usuario
function actualizarUsuario($conn, $id, $nombre, $usuario, $contraseña, $rol, $activo) {
    if (!empty($contraseña)) {
        $sql = "UPDATE usuarios SET nombre = ?, usuario = ?, contraseña = ?, rol = ?, activo = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssii", $nombre, $usuario, $contraseña, $rol, $activo, $id);
    } else {
        $sql = "UPDATE usuarios SET nombre = ?, usuario = ?, rol = ?, activo = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssii", $nombre, $usuario, $rol, $activo, $id);
    }
    return $stmt->execute();
}

// Función para eliminar usuario
function eliminarUsuario($conn, $id) {
    $sql = "DELETE FROM usuarios WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}

// Función para verificar si el usuario ya existe
function usuarioExiste($conn, $usuario, $id_excluir = null) {
    if ($id_excluir) {
        $sql = "SELECT id FROM usuarios WHERE usuario = ? AND id != ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $usuario, $id_excluir);
    } else {
        $sql = "SELECT id FROM usuarios WHERE usuario = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $usuario);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() !== null;
}

// Procesar formularios
$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    switch ($accion) {
        case 'crear':
            $nombre = trim($_POST['nombre']);
            $usuario = trim($_POST['usuario']);
            $contraseña = trim($_POST['contraseña']);
            $rol = $_POST['rol'];
            $activo = isset($_POST['activo']) ? 1 : 0;
            
            if (!empty($nombre) && !empty($usuario) && !empty($contraseña)) {
                if (!usuarioExiste($conn, $usuario)) {
                    if (crearUsuario($conn, $nombre, $usuario, $contraseña, $rol, $activo)) {
                        $mensaje = 'Usuario creado exitosamente';
                        $tipo_mensaje = 'success';
                    } else {
                        $mensaje = 'Error al crear el usuario';
                        $tipo_mensaje = 'error';
                    }
                } else {
                    $mensaje = 'Ya existe un usuario con ese nombre de usuario';
                    $tipo_mensaje = 'error';
                }
            } else {
                $mensaje = 'Todos los campos son obligatorios';
                $tipo_mensaje = 'error';
            }
            break;
            
        case 'actualizar':
            $id = $_POST['id'];
            $nombre = trim($_POST['nombre']);
            $usuario = trim($_POST['usuario']);
            $contraseña = trim($_POST['contraseña']);
            $rol = $_POST['rol'];
            $activo = isset($_POST['activo']) ? 1 : 0;
            
            if (!empty($nombre) && !empty($usuario)) {
                if (!usuarioExiste($conn, $usuario, $id)) {
                    if (actualizarUsuario($conn, $id, $nombre, $usuario, $contraseña, $rol, $activo)) {
                        $mensaje = 'Usuario actualizado exitosamente';
                        $tipo_mensaje = 'success';
                    } else {
                        $mensaje = 'Error al actualizar el usuario';
                        $tipo_mensaje = 'error';
                    }
                } else {
                    $mensaje = 'Ya existe un usuario con ese nombre de usuario';
                    $tipo_mensaje = 'error';
                }
            } else {
                $mensaje = 'El nombre y usuario son obligatorios';
                $tipo_mensaje = 'error';
            }
            break;
            
        case 'eliminar':
            $id = $_POST['id'];
            $usuario_actual = obtenerUsuarioActual();
            
            // No permitir que se elimine a sí mismo
            if ($id == $usuario_actual['id']) {
                $mensaje = 'No puedes eliminar tu propio usuario';
                $tipo_mensaje = 'error';
            } else {
                if (eliminarUsuario($conn, $id)) {
                    $mensaje = 'Usuario eliminado exitosamente';
                    $tipo_mensaje = 'success';
                } else {
                    $mensaje = 'Error al eliminar el usuario';
                    $tipo_mensaje = 'error';
                }
            }
            break;
    }
}

// Obtener datos para edición si se solicita
$usuario_editar = null;
if (isset($_GET['editar'])) {
    $usuario_editar = obtenerUsuario($conn, $_GET['editar']);
}

// Obtener todos los usuarios
$usuarios = obtenerUsuarios($conn);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administración de Usuarios</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #333;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .mensaje {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            text-align: center;
        }
        
        .mensaje.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .mensaje.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .formulario {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        
        input[type="text"], input[type="password"], select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        input[type="checkbox"] {
            width: 20px;
            height: 20px;
        }
        
        .btn {
            background-color: #007bff;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            background-color: #0056b3;
        }
        
        .btn-danger {
            background-color: #dc3545;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .btn-warning {
            background-color: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover {
            background-color: #e0a800;
        }
        
        .btn-secondary {
            background-color: #6c757d;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .btn-small {
            padding: 8px 15px;
            font-size: 14px;
        }
        
        .tabla {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .tabla th, .tabla td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .tabla th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #333;
        }
        
        .tabla tr:hover {
            background-color: #f5f5f5;
        }
        
        .rol-badge {
            padding: 5px 10px;
            border-radius: 15px;
            color: white;
            font-size: 12px;
            font-weight: bold;
        }
        
        .rol-badge.administrador {
            background-color: #ffc107;
            color: #212529;
        }
        
        .rol-badge.rh {
            background-color: #17a2b8;
        }
        
        .estado {
            padding: 5px 10px;
            border-radius: 15px;
            color: white;
            font-size: 12px;
            font-weight: bold;
        }
        
        .estado.activo {
            background-color: #28a745;
        }
        
        .estado.inactivo {
            background-color: #dc3545;
        }
        
        .acciones {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9em;
            opacity: 0.9;
        }
        
        .usuario-actual {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .tabla {
                font-size: 14px;
            }
            
            .acciones {
                flex-direction: column;
            }
            
            .stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <h1>👥 Administración de Usuarios</h1>
        
        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo_mensaje; ?>">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>
        
        <!-- Estadísticas -->
        <?php
        $total_usuarios = count($usuarios);
        $usuarios_activos = count(array_filter($usuarios, function($u) { return $u['activo']; }));
        $administradores = count(array_filter($usuarios, function($u) { return $u['rol'] === 'administrador'; }));
        $usuarios_rh = count(array_filter($usuarios, function($u) { return $u['rol'] === 'rh'; }));
        ?>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_usuarios; ?></div>
                <div class="stat-label">Total Usuarios</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $usuarios_activos; ?></div>
                <div class="stat-label">Usuarios Activos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $administradores; ?></div>
                <div class="stat-label">Administradores</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $usuarios_rh; ?></div>
                <div class="stat-label">Personal RH</div>
            </div>
        </div>
        
        <!-- Formulario para crear/editar usuario -->
        <div class="formulario">
            <h2><?php echo $usuario_editar ? '✏️ Editar Usuario' : '➕ Nuevo Usuario'; ?></h2>
            <form method="POST">
                <input type="hidden" name="accion" value="<?php echo $usuario_editar ? 'actualizar' : 'crear'; ?>">
                <?php if ($usuario_editar): ?>
                    <input type="hidden" name="id" value="<?php echo $usuario_editar['id']; ?>">
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="nombre">Nombre Completo *</label>
                        <input type="text" id="nombre" name="nombre" required 
                               value="<?php echo $usuario_editar ? htmlspecialchars($usuario_editar['nombre']) : ''; ?>"
                               placeholder="Ej: María González">
                    </div>
                    
                    <div class="form-group">
                        <label for="usuario">Nombre de Usuario *</label>
                        <input type="text" id="usuario" name="usuario" required 
                               value="<?php echo $usuario_editar ? htmlspecialchars($usuario_editar['usuario']) : ''; ?>"
                               placeholder="Ej: maria.gonzalez">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="contraseña">Contraseña <?php echo $usuario_editar ? '(dejar vacío para no cambiar)' : '*'; ?></label>
                        <input type="password" id="contraseña" name="contraseña" 
                               <?php echo !$usuario_editar ? 'required' : ''; ?>
                               placeholder="<?php echo $usuario_editar ? 'Nueva contraseña' : 'Contraseña del usuario'; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="rol">Rol *</label>
                        <select id="rol" name="rol" required>
                            <option value="">Seleccione un rol</option>
                            <option value="administrador" <?php echo ($usuario_editar && $usuario_editar['rol'] == 'administrador') ? 'selected' : ''; ?>>
                                👑 Administrador
                            </option>
                            <option value="rh" <?php echo (!$usuario_editar || $usuario_editar['rol'] == 'rh') ? 'selected' : ''; ?>>
                                👥 Recursos Humanos
                            </option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="activo" name="activo" 
                               <?php echo (!$usuario_editar || $usuario_editar['activo']) ? 'checked' : ''; ?>>
                        <label for="activo">Usuario activo</label>
                    </div>
                </div>
                
                <button type="submit" class="btn">
                    <?php echo $usuario_editar ? '✏️ Actualizar Usuario' : '➕ Crear Usuario'; ?>
                </button>
                
                <?php if ($usuario_editar): ?>
                    <a href="usuarios.php" class="btn btn-secondary">❌ Cancelar</a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Lista de usuarios -->
        <h2>👤 Lista de Usuarios</h2>
        <?php if (empty($usuarios)): ?>
            <div style="text-align: center; padding: 50px; color: #666;">
                <h3>👥 No hay usuarios registrados</h3>
                <p>Crea tu primer usuario usando el formulario de arriba</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Creado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $usuario_actual = obtenerUsuarioActual();
                        foreach ($usuarios as $usuario): 
                            $es_usuario_actual = $usuario['id'] == $usuario_actual['id'];
                        ?>
                            <tr <?php echo $es_usuario_actual ? 'class="usuario-actual"' : ''; ?>>
                                <td>
                                    <?php echo $usuario['id']; ?>
                                    <?php if ($es_usuario_actual): ?>
                                        <small class="text-warning">👈 Tú</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($usuario['nombre']); ?></strong>
                                </td>
                                <td>
                                    <code><?php echo htmlspecialchars($usuario['usuario']); ?></code>
                                </td>
                                <td>
                                    <span class="rol-badge <?php echo $usuario['rol']; ?>">
                                        <?php if ($usuario['rol'] == 'administrador'): ?>
                                            👑 Admin
                                        <?php else: ?>
                                            👥 RH
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="estado <?php echo $usuario['activo'] ? 'activo' : 'inactivo'; ?>">
                                        <?php echo $usuario['activo'] ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($usuario['created_at'])); ?></td>
                                <td>
                                    <div class="acciones">
                                        <a href="?editar=<?php echo $usuario['id']; ?>" 
                                           class="btn btn-warning btn-small">✏️ Editar</a>
                                        
                                        <?php if (!$es_usuario_actual): ?>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('¿Estás seguro de eliminar al usuario \'<?php echo htmlspecialchars($usuario['nombre']); ?>\'?\n\nEsta acción no se puede deshacer.')">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id" value="<?php echo $usuario['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-small">🗑️ Eliminar</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="btn btn-secondary btn-small" style="opacity: 0.5;" title="No puedes eliminar tu propio usuario">
                                                🚫 No eliminar
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-focus en el campo nombre
        document.addEventListener('DOMContentLoaded', function() {
            const nombreInput = document.getElementById('nombre');
            if (nombreInput && !nombreInput.value) {
                nombreInput.focus();
            }
        });

        // Validación del formulario
        document.querySelector('form').addEventListener('submit', function(e) {
            const nombre = document.getElementById('nombre').value.trim();
            const usuario = document.getElementById('usuario').value.trim();
            const contraseña = document.getElementById('contraseña').value;
            const rol = document.getElementById('rol').value;
            const esEdicion = document.querySelector('input[name="accion"]').value === 'actualizar';
            
            if (!nombre || !usuario || !rol) {
                e.preventDefault();
                alert('Por favor complete todos los campos obligatorios');
                return false;
            }
            
            if (!esEdicion && !contraseña) {
                e.preventDefault();
                alert('La contraseña es obligatoria para nuevos usuarios');
                document.getElementById('contraseña').focus();
                return false;
            }
            
            if (nombre.length < 2) {
                e.preventDefault();
                alert('El nombre debe tener al menos 2 caracteres');
                document.getElementById('nombre').focus();
                return false;
            }
            
            if (usuario.length < 3) {
                e.preventDefault();
                alert('El nombre de usuario debe tener al menos 3 caracteres');
                document.getElementById('usuario').focus();
                return false;
            }
            
            if (contraseña && contraseña.length < 4) {
                e.preventDefault();
                alert('La contraseña debe tener al menos 4 caracteres');
                document.getElementById('contraseña').focus();
                return false;
            }
        });
        
        // Validación en tiempo real del nombre de usuario
        document.getElementById('usuario').addEventListener('input', function() {
            this.value = this.value.toLowerCase().replace(/[^a-z0-9._-]/g, '');
        });
    </script>
</body>
</html>