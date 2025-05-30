<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once 'db.php';
require_once 'auth.php';

// Función para obtener todos los cuestionarios
function obtenerCuestionarios($conn) {
    $sql = "SELECT c.*, COUNT(p.id) as total_preguntas 
            FROM cuestionarios c 
            LEFT JOIN pregunta p ON c.id = p.cuestionario_id 
            GROUP BY c.id 
            ORDER BY c.created_at DESC";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Función para obtener un cuestionario por ID
function obtenerCuestionario($conn, $id) {
    $sql = "SELECT * FROM cuestionarios WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Función para crear cuestionario
function crearCuestionario($conn, $titulo, $descripcion, $activo, $vacante_id = null) {
    $sql = "INSERT INTO cuestionarios (titulo, descripcion, activo, vacante_id, created_at, updated_at) 
            VALUES (?, ?, ?, ?, NOW(), NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssii", $titulo, $descripcion, $activo, $vacante_id);
    return $stmt->execute() ? $conn->insert_id : false;
}

// Función para actualizar cuestionario
function actualizarCuestionario($conn, $id, $titulo, $descripcion, $activo, $vacante_id = null) {
    $sql = "UPDATE cuestionarios SET titulo = ?, descripcion = ?, activo = ?, vacante_id = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssiii", $titulo, $descripcion, $activo, $vacante_id, $id);
    return $stmt->execute();
}

// Función para eliminar cuestionario
function eliminarCuestionario($conn, $id) {
    
    // Luego eliminar preguntas
    $sql2 = "DELETE FROM pregunta WHERE cuestionario_id = ?";
    $stmt2 = $conn->prepare($sql2);
    $stmt2->bind_param("i", $id);
    $stmt2->execute();
    
    // Finalmente eliminar cuestionario
    $sql3 = "DELETE FROM cuestionarios WHERE id = ?";
    $stmt3 = $conn->prepare($sql3);
    $stmt3->bind_param("i", $id);
    return $stmt3->execute();
}

// Procesar formularios
$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    switch ($accion) {
        case 'crear':
            $titulo = trim($_POST['titulo']);
            $descripcion = trim($_POST['descripcion']);
            $activo = isset($_POST['activo']) ? 1 : 0;
            $vacante_id = !empty($_POST['vacante_id']) ? $_POST['vacante_id'] : null;
            
            if (!empty($titulo)) {
                $cuestionario_id = crearCuestionario($conn, $titulo, $descripcion, $activo, $vacante_id);
                if ($cuestionario_id) {
                    $mensaje = 'Cuestionario creado exitosamente';
                    $tipo_mensaje = 'success';
                } else {
                    $mensaje = 'Error al crear el cuestionario';
                    $tipo_mensaje = 'error';
                }
            } else {
                $mensaje = 'El título es obligatorio';
                $tipo_mensaje = 'error';
            }
            break;
            
        case 'actualizar':
            $id = $_POST['id'];
            $titulo = trim($_POST['titulo']);
            $descripcion = trim($_POST['descripcion']);
            $activo = isset($_POST['activo']) ? 1 : 0;
            $vacante_id = !empty($_POST['vacante_id']) ? $_POST['vacante_id'] : null;
            
            if (!empty($titulo)) {
                if (actualizarCuestionario($conn, $id, $titulo, $descripcion, $activo, $vacante_id)) {
                    $mensaje = 'Cuestionario actualizado exitosamente';
                    $tipo_mensaje = 'success';
                } else {
                    $mensaje = 'Error al actualizar el cuestionario';
                    $tipo_mensaje = 'error';
                }
            } else {
                $mensaje = 'El título es obligatorio';
                $tipo_mensaje = 'error';
            }
            break;
            
        case 'eliminar':
            $id = $_POST['id'];
            if (eliminarCuestionario($conn, $id)) {
                $mensaje = 'Cuestionario eliminado exitosamente';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'Error al eliminar el cuestionario';
                $tipo_mensaje = 'error';
            }
            break;
    }
}

// Obtener datos para edición si se solicita
$cuestionario_editar = null;
if (isset($_GET['editar'])) {
    $cuestionario_editar = obtenerCuestionario($conn, $_GET['editar']);
}

// Obtener todos los cuestionarios
$cuestionarios = obtenerCuestionarios($conn);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Cuestionarios</title>
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
            max-width: 1400px;
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
        
        input[type="text"], input[type="number"], textarea, select {
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
        
        .btn-success {
            background-color: #28a745;
        }
        
        .btn-success:hover {
            background-color: #218838;
        }
        
        .btn-info {
            background-color: #17a2b8;
        }
        
        .btn-info:hover {
            background-color: #138496;
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
        
        .btn-small {
            padding: 8px 15px;
            font-size: 14px;
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
        
        .pregunta-count {
            background-color: #007bff;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .acciones-rapidas {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            text-align: center;
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
                <div class="stat-number"><?php echo count($cuestionarios); ?></div>
                <div class="stat-label">Total Cuestionarios</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php echo count(array_filter($cuestionarios, function($c) { return $c['activo']; })); ?>
                </div>
                <div class="stat-label">Cuestionarios Activos</div>
            </div>
        </div>
        
        <!-- Acciones rápidas -->
        <div class="acciones-rapidas">
            <h4>🚀 Acciones Rápidas</h4>
            <div style="margin-top: 15px;">
                <a href="duplicar_cuestionario.php" class="btn btn-success">
                    📋 Duplicar Cuestionario
                </a>
                <span style="margin: 0 10px; color: #666;">|</span>
                <small style="color: #666;">
                    Duplica un cuestionario existente con todas sus preguntas y opciones
                </small>
            </div>
        </div>
        
        <!-- Formulario para crear/editar -->
        <div class="formulario">
            <h2><?php echo $cuestionario_editar ? '✏️ Editar Cuestionario' : '➕ Nuevo Cuestionario'; ?></h2>
            <form method="POST">
                <input type="hidden" name="accion" value="<?php echo $cuestionario_editar ? 'actualizar' : 'crear'; ?>">
                <?php if ($cuestionario_editar): ?>
                    <input type="hidden" name="id" value="<?php echo $cuestionario_editar['id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="titulo">Título del Cuestionario *</label>
                    <input type="text" id="titulo" name="titulo" required 
                           value="<?php echo $cuestionario_editar ? htmlspecialchars($cuestionario_editar['titulo']) : ''; ?>"
                           placeholder="Ej: Cuestionario de Evaluación - Ventas">
                </div>
                
                <div class="form-group">
                    <label for="descripcion">Descripción</label>
                    <textarea id="descripcion" name="descripcion" 
                              placeholder="Descripción detallada del cuestionario, su propósito y aplicación"><?php echo $cuestionario_editar ? htmlspecialchars($cuestionario_editar['descripcion']) : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="vacante_id">ID de Vacante (opcional)</label>
                    <input type="number" id="vacante_id" name="vacante_id" 
                           value="<?php echo $cuestionario_editar ? $cuestionario_editar['vacante_id'] : ''; ?>"
                           placeholder="Ingrese el ID de la vacante asociada">
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="activo" name="activo" 
                               <?php echo (!$cuestionario_editar || $cuestionario_editar['activo']) ? 'checked' : ''; ?>>
                        <label for="activo">Cuestionario activo</label>
                    </div>
                </div>
                
                <button type="submit" class="btn">
                    <?php echo $cuestionario_editar ? '✏️ Actualizar Cuestionario' : '➕ Crear Cuestionario'; ?>
                </button>
                
                <?php if ($cuestionario_editar): ?>
                    <a href="cuestionarios.php" class="btn btn-secondary">❌ Cancelar</a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Lista de cuestionarios -->
        <h2>📋 Lista de Cuestionarios</h2>
        <?php if (empty($cuestionarios)): ?>
            <div style="text-align: center; padding: 50px; color: #666;">
                <h3>📝 No hay cuestionarios registrados</h3>
                <p>Crea tu primer cuestionario usando el formulario de arriba</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Título</th>
                            <!-- <th>Descripción</th> -->
                            <th>Preguntas</th>
                            <th>Vacante ID</th>
                            <th>Estado</th>
                            <th>Creado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cuestionarios as $cuestionario): ?>
                            <tr>
                                <td><?php echo $cuestionario['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($cuestionario['titulo']); ?></strong>
                                </td>                            
                                <td>
                                    <span class="pregunta-count"><?php echo $cuestionario['total_preguntas']; ?> preguntas</span>
                                </td>
                                <td>
                                    <?php echo $cuestionario['vacante_id'] ? $cuestionario['vacante_id'] : '<em style="color: #999;">N/A</em>'; ?>
                                </td>
                                <td>
                                    <span class="estado <?php echo $cuestionario['activo'] ? 'activo' : 'inactivo'; ?>">
                                        <?php echo $cuestionario['activo'] ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($cuestionario['created_at'])); ?></td>
                                <td>
                                    <div class="acciones">
                                        <a href="preguntas.php?cuestionario_id=<?php echo $cuestionario['id']; ?>" 
                                        class="btn btn-info btn-small">📝 Preguntas</a>
                                        <a href="duplicar_cuestionario.php?id=<?php echo $cuestionario['id']; ?>" 
                                        class="btn btn-success btn-small" 
                                        title="Duplicar cuestionario con todas sus preguntas">📋 Duplicar</a>
                                        <a href="?editar=<?php echo $cuestionario['id']; ?>" 
                                        class="btn btn-warning btn-small">✏️ Editar</a>
                                        <form method="POST" style="display: inline;" 
                                            onsubmit="return confirm('¿Estás seguro de eliminar este cuestionario?\n\nSe eliminarán también todas sus preguntas y opciones.\n\nEsta acción no se puede deshacer.')">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id" value="<?php echo $cuestionario['id']; ?>">
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
        // Inicializar tooltips
        document.addEventListener('DOMContentLoaded', function() {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
        
        // Confirmación para duplicar
        document.querySelectorAll('a[href*="duplicar_cuestionario"]').forEach(link => {
            link.addEventListener('click', function(e) {
                const titulo = this.closest('tr').querySelector('strong').textContent;
                if (!confirm(`¿Deseas duplicar el cuestionario "${titulo}"?\n\nSe copiará con todas sus preguntas y opciones.`)) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>