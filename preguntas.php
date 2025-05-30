<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
require_once 'db.php';

// Verificar que se recibió el ID del cuestionario
if (!isset($_GET['cuestionario_id'])) {
    header('Location: cuestionarios.php');
    exit;
}

$cuestionario_id = $_GET['cuestionario_id'];

// Función para obtener cuestionario
function obtenerCuestionario($conn, $id) {
    $sql = "SELECT * FROM cuestionarios WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Función para obtener tipos de pregunta
function obtenerTiposPreguntas($conn) {
    $sql = "SELECT * FROM tipo_pregunta ORDER BY tipo";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Función para obtener preguntas del cuestionario
function obtenerPreguntas($conn, $cuestionario_id) {
    $sql = "SELECT p.*, tp.tipo, tp.descripcion as tipo_descripcion,
                   COUNT(op.id) as total_opciones
            FROM pregunta p 
            INNER JOIN tipo_pregunta tp ON p.tipo_preguntum_id = tp.id 
            LEFT JOIN opcion_respuesta op ON p.id = op.preguntum_id
            WHERE p.cuestionario_id = ? 
            GROUP BY p.id
            ORDER BY p.orden ASC, p.created_at ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $cuestionario_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Función para obtener el siguiente orden
function obtenerSiguienteOrden($conn, $cuestionario_id) {
    $sql = "SELECT COALESCE(MAX(orden), 0) + 1 as siguiente_orden FROM pregunta WHERE cuestionario_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $cuestionario_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['siguiente_orden'];
}

// Función para crear pregunta
function crearPregunta($conn, $texto, $requerida, $orden, $metadatos, $cuestionario_id, $tipo_pregunta_id) {
    $sql = "INSERT INTO pregunta (texto, requerida, orden, metadatos, cuestionario_id, tipo_preguntum_id, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("siisii", $texto, $requerida, $orden, $metadatos, $cuestionario_id, $tipo_pregunta_id);
    return $stmt->execute() ? $conn->insert_id : false;
}

// Función para actualizar pregunta
function actualizarPregunta($conn, $id, $texto, $requerida, $orden, $metadatos, $tipo_pregunta_id) {
    $sql = "UPDATE pregunta SET texto = ?, requerida = ?, orden = ?, metadatos = ?, tipo_preguntum_id = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("siisii", $texto, $requerida, $orden, $metadatos, $tipo_pregunta_id, $id);
    return $stmt->execute();
}

// Función para eliminar pregunta
function eliminarPregunta($conn, $id) {
    // Primero eliminar opciones de respuesta
    $sql1 = "DELETE FROM opcion_respuesta WHERE preguntum_id = ?";
    $stmt1 = $conn->prepare($sql1);
    $stmt1->bind_param("i", $id);
    $stmt1->execute();
    
    // Luego eliminar pregunta
    $sql2 = "DELETE FROM pregunta WHERE id = ?";
    $stmt2 = $conn->prepare($sql2);
    $stmt2->bind_param("i", $id);
    return $stmt2->execute();
}

// Función para reordenar preguntas
function reordenarPreguntas($conn, $cuestionario_id, $orden_preguntas) {
    foreach ($orden_preguntas as $orden => $pregunta_id) {
        $sql = "UPDATE pregunta SET orden = ?, updated_at = NOW() WHERE id = ? AND cuestionario_id = ?";
        $stmt = $conn->prepare($sql);
        $nuevo_orden = $orden + 1; // Los órdenes empiezan en 1
        $stmt->bind_param("iii", $nuevo_orden, $pregunta_id, $cuestionario_id);
        $stmt->execute();
    }
    return true;
}

// Obtener datos del cuestionario
$cuestionario = obtenerCuestionario($conn, $cuestionario_id);
if (!$cuestionario) {
    header('Location: cuestionarios.php');
    exit;
}

// Procesar formularios
$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    switch ($accion) {
        case 'crear':
            $texto = trim($_POST['texto']);
            $requerida = isset($_POST['requerida']) ? 1 : 0;
            $orden = $_POST['orden'] ?: obtenerSiguienteOrden($conn, $cuestionario_id);
            $metadatos = trim($_POST['metadatos']);
            $tipo_pregunta_id = $_POST['tipo_pregunta_id'];
            
            if (!empty($texto) && !empty($tipo_pregunta_id)) {
                $pregunta_id = crearPregunta($conn, $texto, $requerida, $orden, $metadatos, $cuestionario_id, $tipo_pregunta_id);
                if ($pregunta_id) {
                    $mensaje = 'Pregunta creada exitosamente';
                    $tipo_mensaje = 'success';
                } else {
                    $mensaje = 'Error al crear la pregunta';
                    $tipo_mensaje = 'error';
                }
            } else {
                $mensaje = 'El texto de la pregunta y el tipo son obligatorios';
                $tipo_mensaje = 'error';
            }
            break;
            
        case 'actualizar':
            $id = $_POST['id'];
            $texto = trim($_POST['texto']);
            $requerida = isset($_POST['requerida']) ? 1 : 0;
            $orden = $_POST['orden'];
            $metadatos = trim($_POST['metadatos']);
            $tipo_pregunta_id = $_POST['tipo_pregunta_id'];
            
            if (!empty($texto) && !empty($tipo_pregunta_id)) {
                if (actualizarPregunta($conn, $id, $texto, $requerida, $orden, $metadatos, $tipo_pregunta_id)) {
                    $mensaje = 'Pregunta actualizada exitosamente';
                    $tipo_mensaje = 'success';
                } else {
                    $mensaje = 'Error al actualizar la pregunta';
                    $tipo_mensaje = 'error';
                }
            } else {
                $mensaje = 'El texto de la pregunta y el tipo son obligatorios';
                $tipo_mensaje = 'error';
            }
            break;
            
        case 'eliminar':
            $id = $_POST['id'];
            if (eliminarPregunta($conn, $id)) {
                $mensaje = 'Pregunta eliminada exitosamente';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'Error al eliminar la pregunta';
                $tipo_mensaje = 'error';
            }
            break;
            
        case 'reordenar':
            $orden_preguntas = json_decode($_POST['orden_preguntas'], true);
            if (reordenarPreguntas($conn, $cuestionario_id, $orden_preguntas)) {
                $mensaje = 'Preguntas reordenadas exitosamente';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'Error al reordenar las preguntas';
                $tipo_mensaje = 'error';
            }
            break;
    }
}

// Obtener datos para edición si se solicita
$pregunta_editar = null;
if (isset($_GET['editar'])) {
    $sql = "SELECT * FROM pregunta WHERE id = ? AND cuestionario_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $_GET['editar'], $cuestionario_id);
    $stmt->execute();
    $pregunta_editar = $stmt->get_result()->fetch_assoc();
}

// Obtener tipos de preguntas y preguntas
$tipos_preguntas = obtenerTiposPreguntas($conn);
$preguntas = obtenerPreguntas($conn, $cuestionario_id);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preguntas - <?php echo htmlspecialchars($cuestionario['titulo']); ?></title>
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
            margin-bottom: 10px;
        }
        
        .cuestionario-info {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid #007bff;
        }
        
        .navegacion {
            margin-bottom: 30px;
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
        
        input[type="text"], input[type="number"], textarea, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        textarea {
            height: 80px;
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
        
        .btn-small {
            padding: 8px 15px;
            font-size: 14px;
        }
        
        .preguntas-container {
            margin-top: 30px;
        }
        
        .pregunta-item {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 15px;
            overflow: hidden;
        }
        
        .pregunta-header {
            background: #f8f9fa;
            padding: 15px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .pregunta-orden {
            background-color: #007bff;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            cursor: grab;
        }
        
        .pregunta-orden:active {
            cursor: grabbing;
        }
        
        .pregunta-tipo {
            background-color: #6c757d;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .pregunta-tipo.texto {
            background-color: #28a745;
        }
        
        .pregunta-tipo.opciones {
            background-color: #ffc107;
            color: #212529;
        }
        
        .pregunta-requerida {
            color: #dc3545;
            font-weight: bold;
        }
        
        .pregunta-contenido {
            padding: 15px;
        }
        
        .pregunta-texto {
            font-size: 16px;
            margin-bottom: 10px;
            color: #333;
        }
        
        .pregunta-metadatos {
            color: #666;
            font-size: 14px;
            font-style: italic;
            margin-bottom: 10px;
        }
        
        .pregunta-acciones {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .opciones-count {
            background-color: #17a2b8;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .sortable {
            min-height: 100px;
        }
        
        .sortable-ghost {
            opacity: 0.4;
        }
        
        .reorder-controls {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .pregunta-header {
                flex-direction: column;
                gap: 10px;
            }
            
            .pregunta-acciones {
                justify-content: center;
            }
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
</head>
<body>
    <div class="container">
        <h1>📝 Preguntas del Cuestionario</h1>
        
        <div class="cuestionario-info">
            <h3><?php echo htmlspecialchars($cuestionario['titulo']); ?></h3>
            <p><?php echo htmlspecialchars($cuestionario['descripcion']); ?></p>
            <small>ID: <?php echo $cuestionario['id']; ?> | 
                   Estado: <?php echo $cuestionario['activo'] ? 'Activo' : 'Inactivo'; ?> | 
                   Total preguntas: <?php echo count($preguntas); ?>
            </small>
        </div>
        
        <div class="navegacion">
            <a href="cuestionarios.php" class="btn btn-secondary">← Volver a Cuestionarios</a>
        </div>
        
        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo_mensaje; ?>">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>
        
        <!-- Formulario para crear/editar pregunta -->
        <div class="formulario">
            <h2><?php echo $pregunta_editar ? '✏️ Editar Pregunta' : '➕ Nueva Pregunta'; ?></h2>
            <form method="POST">
                <input type="hidden" name="accion" value="<?php echo $pregunta_editar ? 'actualizar' : 'crear'; ?>">
                <?php if ($pregunta_editar): ?>
                    <input type="hidden" name="id" value="<?php echo $pregunta_editar['id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="texto">Texto de la Pregunta *</label>
                    <textarea id="texto" name="texto" required 
                              placeholder="Escriba aquí el texto de la pregunta"><?php echo $pregunta_editar ? htmlspecialchars($pregunta_editar['texto']) : ''; ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="tipo_pregunta_id">Tipo de Pregunta *</label>
                        <select id="tipo_pregunta_id" name="tipo_pregunta_id" required>
                            <option value="">Seleccione un tipo</option>
                            <?php foreach ($tipos_preguntas as $tipo): ?>
                                <option value="<?php echo $tipo['id']; ?>" 
                                        <?php echo ($pregunta_editar && $pregunta_editar['tipo_preguntum_id'] == $tipo['id']) ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($tipo['tipo']); ?> - <?php echo $tipo['descripcion']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="orden">Orden</label>
                        <input type="number" id="orden" name="orden" min="1"
                               value="<?php echo $pregunta_editar ? $pregunta_editar['orden'] : obtenerSiguienteOrden($conn, $cuestionario_id); ?>"
                               placeholder="Orden de la pregunta">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="metadatos">Metadatos (opcional)</label>
                    <textarea id="metadatos" name="metadatos" 
                              placeholder="Información adicional, configuraciones JSON, etc."><?php echo $pregunta_editar ? htmlspecialchars($pregunta_editar['metadatos']) : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="requerida" name="requerida" 
                               <?php echo ($pregunta_editar && $pregunta_editar['requerida']) ? 'checked' : ''; ?>>
                        <label for="requerida">Pregunta requerida (obligatoria)</label>
                    </div>
                </div>
                
                <button type="submit" class="btn">
                    <?php echo $pregunta_editar ? '✏️ Actualizar Pregunta' : '➕ Crear Pregunta'; ?>
                </button>
                
                <?php if ($pregunta_editar): ?>
                    <a href="preguntas.php?cuestionario_id=<?php echo $cuestionario_id; ?>" class="btn btn-secondary">❌ Cancelar</a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Controles de reordenamiento -->
        <?php if (count($preguntas) > 1): ?>
        <div class="reorder-controls">
            <p><strong>💡 Tip:</strong> Arrastra las preguntas por el número de orden para reordenarlas</p>
            <button type="button" id="guardar-orden" class="btn btn-success" style="display: none;">💾 Guardar Nuevo Orden</button>
        </div>
        <?php endif; ?>
        
        <!-- Lista de preguntas -->
        <div class="preguntas-container">
            <h2>📋 Preguntas (<?php echo count($preguntas); ?>)</h2>
            
            <?php if (empty($preguntas)): ?>
                <div style="text-align: center; padding: 50px; color: #666;">
                    <h3>❓ No hay preguntas en este cuestionario</h3>
                    <p>Crea tu primera pregunta usando el formulario de arriba</p>
                </div>
            <?php else: ?>
                <div id="preguntas-list" class="sortable">
                    <?php foreach ($preguntas as $pregunta): ?>
                        <div class="pregunta-item" data-id="<?php echo $pregunta['id']; ?>">
                            <div class="pregunta-header">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span class="pregunta-orden" title="Arrastra para reordenar">
                                        #<?php echo $pregunta['orden']; ?>
                                    </span>
                                    <span class="pregunta-tipo <?php echo $pregunta['tipo']; ?>">
                                        <?php echo ucfirst($pregunta['tipo']); ?>
                                    </span>
                                    <?php if ($pregunta['requerida']): ?>
                                        <span class="pregunta-requerida" title="Pregunta obligatoria">*</span>
                                    <?php endif; ?>
                                    <?php if ($pregunta['tipo'] == 'opciones' && $pregunta['total_opciones'] > 0): ?>
                                        <span class="opciones-count"><?php echo $pregunta['total_opciones']; ?> opciones</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="pregunta-acciones">
                                    <?php if ($pregunta['tipo'] == 'opciones'): ?>
                                        <a href="opciones.php?pregunta_id=<?php echo $pregunta['id']; ?>" 
                                           class="btn btn-info btn-small">⚙️ Opciones</a>
                                    <?php endif; ?>
                                    <a href="?cuestionario_id=<?php echo $cuestionario_id; ?>&editar=<?php echo $pregunta['id']; ?>" 
                                       class="btn btn-warning btn-small">✏️ Editar</a>
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('¿Estás seguro de eliminar esta pregunta?\n\nSe eliminarán también todas sus opciones.\n\nEsta acción no se puede deshacer.')">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id" value="<?php echo $pregunta['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-small">🗑️ Eliminar</button>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="pregunta-contenido">
                                <div class="pregunta-texto">
                                    <?php echo htmlspecialchars($pregunta['texto']); ?>
                                </div>
                                
                                <?php if (!empty($pregunta['metadatos'])): ?>
                                    <div class="pregunta-metadatos">
                                        Metadatos: <?php echo htmlspecialchars($pregunta['metadatos']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Inicializar Sortable para reordenar preguntas
        <?php if (count($preguntas) > 1): ?>
        const sortable = Sortable.create(document.getElementById('preguntas-list'), {
            handle: '.pregunta-orden',
            animation: 150,
            ghostClass: 'sortable-ghost',
            onEnd: function(evt) {
                document.getElementById('guardar-orden').style.display = 'inline-block';
            }
        });

        // Guardar nuevo orden
        document.getElementById('guardar-orden').addEventListener('click', function() {
            const preguntas = Array.from(document.querySelectorAll('.pregunta-item'));
            const orden = preguntas.map(item => item.dataset.id);
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const accionInput = document.createElement('input');
            accionInput.type = 'hidden';
            accionInput.name = 'accion';
            accionInput.value = 'reordenar';
            
            const ordenInput = document.createElement('input');
            ordenInput.type = 'hidden';
            ordenInput.name = 'orden_preguntas';
            ordenInput.value = JSON.stringify(orden);
            
            form.appendChild(accionInput);
            form.appendChild(ordenInput);
            document.body.appendChild(form);
            form.submit();
        });
        <?php endif; ?>

        // Auto-focus en textarea
        document.addEventListener('DOMContentLoaded', function() {
            const textoTextarea = document.getElementById('texto');
            if (textoTextarea && !textoTextarea.value) {
                textoTextarea.focus();
            }
        });
    </script>
</body>
</html>