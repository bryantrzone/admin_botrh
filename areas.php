<?php
require_once 'db.php';
require_once 'auth.php';

// Función para obtener todas las áreas
function obtenerAreas($conn) {
    $sql = "SELECT * FROM areas ORDER BY created_at DESC";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Función para obtener un área por ID
function obtenerArea($conn, $id) {
    $sql = "SELECT * FROM areas WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Función para crear área
function crearArea($conn, $nombre, $descripcion) {
    $sql = "INSERT INTO areas (nombre, descripcion, created_at, updated_at) VALUES (?, ?, NOW(), NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $nombre, $descripcion);
    return $stmt->execute();
}

// Función para actualizar área
function actualizarArea($conn, $id, $nombre, $descripcion) {
    $sql = "UPDATE areas SET nombre = ?, descripcion = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $nombre, $descripcion, $id);
    return $stmt->execute();
}

// Función para eliminar área
function eliminarArea($conn, $id) {
    $sql = "DELETE FROM areas WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}

// Función para verificar si el nombre ya existe
function nombreAreaExiste($conn, $nombre, $id_excluir = null) {
    if ($id_excluir) {
        $sql = "SELECT id FROM areas WHERE nombre = ? AND id != ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $nombre, $id_excluir);
    } else {
        $sql = "SELECT id FROM areas WHERE nombre = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $nombre);
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
            $descripcion = trim($_POST['descripcion']);
            
            if (!empty($nombre)) {
                if (!nombreAreaExiste($conn, $nombre)) {
                    if (crearArea($conn, $nombre, $descripcion)) {
                        $mensaje = 'Área creada exitosamente';
                        $tipo_mensaje = 'success';
                    } else {
                        $mensaje = 'Error al crear el área';
                        $tipo_mensaje = 'error';
                    }
                } else {
                    $mensaje = 'Ya existe un área con ese nombre';
                    $tipo_mensaje = 'error';
                }
            } else {
                $mensaje = 'El nombre es obligatorio';
                $tipo_mensaje = 'error';
            }
            break;
            
        case 'actualizar':
            $id = $_POST['id'];
            $nombre = trim($_POST['nombre']);
            $descripcion = trim($_POST['descripcion']);
            
            if (!empty($nombre)) {
                if (!nombreAreaExiste($conn, $nombre, $id)) {
                    if (actualizarArea($conn, $id, $nombre, $descripcion)) {
                        $mensaje = 'Área actualizada exitosamente';
                        $tipo_mensaje = 'success';
                    } else {
                        $mensaje = 'Error al actualizar el área';
                        $tipo_mensaje = 'error';
                    }
                } else {
                    $mensaje = 'Ya existe un área con ese nombre';
                    $tipo_mensaje = 'error';
                }
            } else {
                $mensaje = 'El nombre es obligatorio';
                $tipo_mensaje = 'error';
            }
            break;
            
        case 'eliminar':
            $id = $_POST['id'];
            if (eliminarArea($conn, $id)) {
                $mensaje = 'Área eliminada exitosamente';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'Error al eliminar el área';
                $tipo_mensaje = 'error';
            }
            break;
    }
}

// Obtener datos para edición si se solicita
$area_editar = null;
if (isset($_GET['editar'])) {
    $area_editar = obtenerArea($conn, $_GET['editar']);
}

// Obtener todas las áreas
$areas = obtenerAreas($conn);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administración de Áreas</title>
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
        
        .navegacion {
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
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        
        input[type="text"], textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        textarea {
            height: 100px;
            resize: vertical;
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
        
        .btn-success {
            background-color: #28a745;
        }
        
        .btn-success:hover {
            background-color: #218838;
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
        
        .acciones {
            display: flex;
            gap: 5px;
        }
        
        .btn-small {
            padding: 8px 15px;
            font-size: 14px;
        }
        
        .descripcion {
            max-width: 300px;
            word-wrap: break-word;
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
        
        @media (max-width: 768px) {
            .container {
                padding: 15px;
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
    
    <div class="container-fluid mt-4">
        
        
        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo_mensaje; ?>">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>
        
        <!-- Estadísticas -->
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($areas); ?></div>
                <div class="stat-label">Total de Áreas</div>
            </div>
        </div>
        
        <!-- Formulario para crear/editar -->
        <div class="formulario">
            <h2><?php echo $area_editar ? 'Editar Área' : 'Nueva Área'; ?></h2>
            <form method="POST">
                <input type="hidden" name="accion" value="<?php echo $area_editar ? 'actualizar' : 'crear'; ?>">
                <?php if ($area_editar): ?>
                    <input type="hidden" name="id" value="<?php echo $area_editar['id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="nombre">Nombre del Área *</label>
                    <input type="text" id="nombre" name="nombre" required 
                           value="<?php echo $area_editar ? htmlspecialchars($area_editar['nombre']) : ''; ?>"
                           placeholder="Ej: Ventas, Marketing, Recursos Humanos">
                </div>
                
                <div class="form-group">
                    <label for="descripcion">Descripción</label>
                    <textarea id="descripcion" name="descripcion" 
                              placeholder="Descripción detallada del área y sus funciones"><?php echo $area_editar ? htmlspecialchars($area_editar['descripcion']) : ''; ?></textarea>
                </div>
                
                <button type="submit" class="btn">
                    <?php echo $area_editar ? '✏️ Actualizar Área' : '➕ Crear Área'; ?>
                </button>
                
                <?php if ($area_editar): ?>
                    <a href="areas.php" class="btn btn-secondary">❌ Cancelar</a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Lista de áreas -->
        <h2>Lista de Áreas</h2>
        <?php if (empty($areas)): ?>
            <div style="text-align: center; padding: 50px; color: #666;">
                <h3>📁 No hay áreas registradas</h3>
                <p>Crea tu primera área usando el formulario de arriba</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Creada</th>
                            <th>Actualizada</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($areas as $area): ?>
                            <tr>
                                <td><?php echo $area['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($area['nombre']); ?></strong>
                                </td>
                                <td class="descripcion">
                                    <?php 
                                    $descripcion = htmlspecialchars($area['descripcion']);
                                    echo !empty($descripcion) ? $descripcion : '<em style="color: #999;">Sin descripción</em>';
                                    ?>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($area['created_at'])); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($area['updated_at'])); ?></td>
                                <td>
                                    <div class="acciones">
                                        <a href="?editar=<?php echo $area['id']; ?>" class="btn btn-warning btn-small">✏️ Editar</a>
                                        <form method="POST" style="display: inline;" 
                                            onsubmit="return confirm('¿Estás seguro de eliminar el área \'<?php echo htmlspecialchars($area['nombre']); ?>\'?\n\nEsta acción no se puede deshacer.')">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id" value="<?php echo $area['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-small">🗑️ Eliminar</button>
                                        </form>
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
        // Auto-focus en el campo nombre cuando se carga la página
        document.addEventListener('DOMContentLoaded', function() {
            const nombreInput = document.getElementById('nombre');
            if (nombreInput && !nombreInput.value) {
                nombreInput.focus();
            }
        });

        // Confirmación personalizada para eliminar
        function confirmarEliminacion(nombre) {
            return confirm(`¿Estás seguro de eliminar el área "${nombre}"?\n\nEsta acción no se puede deshacer.`);
        }

        // Validación del formulario
        document.querySelector('form').addEventListener('submit', function(e) {
            const nombre = document.getElementById('nombre').value.trim();
            
            if (!nombre) {
                e.preventDefault();
                alert('El nombre del área es obligatorio');
                document.getElementById('nombre').focus();
                return false;
            }
            
            if (nombre.length < 2) {
                e.preventDefault();
                alert('El nombre del área debe tener al menos 2 caracteres');
                document.getElementById('nombre').focus();
                return false;
            }
        });
    </script>
</body>
</html>