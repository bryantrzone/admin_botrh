<?php
require_once 'db.php';

// Verificar que se recibió el ID de la pregunta
if (!isset($_GET['pregunta_id'])) {
    header('Location: cuestionarios.php');
    exit;
}

$pregunta_id = $_GET['pregunta_id'];

// Función para obtener pregunta con cuestionario
function obtenerPreguntaConCuestionario($conn, $id) {
    $sql = "SELECT p.*, c.titulo as cuestionario_titulo, c.id as cuestionario_id, tp.tipo
            FROM pregunta p 
            INNER JOIN cuestionarios c ON p.cuestionario_id = c.id
            INNER JOIN tipo_pregunta tp ON p.tipo_preguntum_id = tp.id
            WHERE p.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Función para obtener opciones de respuesta
function obtenerOpciones($conn, $pregunta_id) {
    $sql = "SELECT * FROM opcion_respuesta WHERE preguntum_id = ? ORDER BY orden ASC, id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $pregunta_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Función para obtener el siguiente orden
function obtenerSiguienteOrdenOpcion($conn, $pregunta_id) {
    $sql = "SELECT COALESCE(MAX(orden), 0) + 1 as siguiente_orden FROM opcion_respuesta WHERE preguntum_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $pregunta_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['siguiente_orden'];
}

// Función para crear opción
function crearOpcion($conn, $texto, $orden, $es_negativa, $pregunta_id) {
    $sql = "INSERT INTO opcion_respuesta (texto, orden, es_negativa, preguntum_id, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("siii", $texto, $orden, $es_negativa, $pregunta_id);
    return $stmt->execute() ? $conn->insert_id : false;
}

// Función para actualizar opción
function actualizarOpcion($conn, $id, $texto, $orden, $es_negativa) {
    $sql = "UPDATE opcion_respuesta SET texto = ?, orden = ?, es_negativa = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("siii", $texto, $orden, $es_negativa, $id);
    return $stmt->execute();
}

// Función para eliminar opción
function eliminarOpcion($conn, $id) {
    $sql = "DELETE FROM opcion_respuesta WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}

// Función para reordenar opciones
function reordenarOpciones($conn, $pregunta_id, $orden_opciones) {
    foreach ($orden_opciones as $orden => $opcion_id) {
        $sql = "UPDATE opcion_respuesta SET orden = ?, updated_at = NOW() WHERE id = ? AND preguntum_id = ?";
        $stmt = $conn->prepare($sql);
        $nuevo_orden = $orden + 1; // Los órdenes empiezan en 1
        $stmt->bind_param("iii", $nuevo_orden, $opcion_id, $pregunta_id);
        $stmt->execute();
    }
    return true;
}

// Función para obtener plantillas de opciones
function obtenerPlantillasOpciones($conn) {
    $sql = "SELECT * FROM plantillas_opciones WHERE activa = 1 ORDER BY categoria, nombre";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Función para crear opciones desde plantilla
function crearOpcionesDesdePlantilla($conn, $pregunta_id, $plantilla_id) {
    // Obtener la plantilla
    $sql = "SELECT opciones FROM plantillas_opciones WHERE id = ? AND activa = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $plantilla_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if (!$result) return false;
    
    $opciones = json_decode($result['opciones'], true);
    if (!$opciones) return false;
    
    $orden_actual = obtenerSiguienteOrdenOpcion($conn, $pregunta_id);
    $creadas = 0;
    
    foreach ($opciones as $opcion) {
        if (crearOpcion($conn, $opcion['texto'], $orden_actual, $opcion['es_negativa'], $pregunta_id)) {
            $creadas++;
            $orden_actual++;
        }
    }
    
    return $creadas;
}

// Función para guardar opciones actuales como plantilla
function guardarComoPlantilla($conn, $pregunta_id, $nombre, $descripcion, $categoria) {
    // Obtener opciones existentes
    $sql = "SELECT texto, es_negativa FROM opcion_respuesta WHERE preguntum_id = ? ORDER BY orden";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $pregunta_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $opciones = [];
    while ($row = $result->fetch_assoc()) {
        $opciones[] = [
            'texto' => $row['texto'],
            'es_negativa' => (int)$row['es_negativa']
        ];
    }
    
    if (empty($opciones)) return false;
    
    // Guardar como plantilla
    $sql = "INSERT INTO plantillas_opciones (nombre, descripcion, opciones, categoria) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $opciones_json = json_encode($opciones);
    $stmt->bind_param("ssss", $nombre, $descripcion, $opciones_json, $categoria);
    
    return $stmt->execute();
}

// Obtener datos de la pregunta
$pregunta = obtenerPreguntaConCuestionario($conn, $pregunta_id);
if (!$pregunta) {
    header('Location: cuestionarios.php');
    exit;
}

// Verificar que es una pregunta de opciones
if ($pregunta['tipo'] !== 'opciones') {
    header('Location: preguntas.php?cuestionario_id=' . $pregunta['cuestionario_id']);
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
            $orden = $_POST['orden'] ?: obtenerSiguienteOrdenOpcion($conn, $pregunta_id);
            $es_negativa = isset($_POST['es_negativa']) ? 1 : 0;
            
            if (!empty($texto)) {
                $opcion_id = crearOpcion($conn, $texto, $orden, $es_negativa, $pregunta_id);
                if ($opcion_id) {
                    $mensaje = 'Opción creada exitosamente';
                    $tipo_mensaje = 'success';
                } else {
                    $mensaje = 'Error al crear la opción';
                    $tipo_mensaje = 'error';
                }
            } else {
                $mensaje = 'El texto de la opción es obligatorio';
                $tipo_mensaje = 'error';
            }
            break;
            
        case 'actualizar':
            $id = $_POST['id'];
            $texto = trim($_POST['texto']);
            $orden = $_POST['orden'];
            $es_negativa = isset($_POST['es_negativa']) ? 1 : 0;
            
            if (!empty($texto)) {
                if (actualizarOpcion($conn, $id, $texto, $orden, $es_negativa)) {
                    $mensaje = 'Opción actualizada exitosamente';
                    $tipo_mensaje = 'success';
                } else {
                    $mensaje = 'Error al actualizar la opción';
                    $tipo_mensaje = 'error';
                }
            } else {
                $mensaje = 'El texto de la opción es obligatorio';
                $tipo_mensaje = 'error';
            }
            break;
            
        case 'eliminar':
            $id = $_POST['id'];
            if (eliminarOpcion($conn, $id)) {
                $mensaje = 'Opción eliminada exitosamente';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'Error al eliminar la opción';
                $tipo_mensaje = 'error';
            }
            break;
            
        case 'crear_multiple':
            $opciones_texto = trim($_POST['opciones_multiple']);
            if (!empty($opciones_texto)) {
                // Separar por comas o saltos de línea
                $opciones_array = [];
                
                // Primero intentar separar por comas
                if (strpos($opciones_texto, ',') !== false) {
                    $opciones_array = explode(',', $opciones_texto);
                } else {
                    // Si no hay comas, separar por saltos de línea
                    $opciones_array = explode("\n", $opciones_texto);
                }
                
                $creadas = 0;
                $errores = 0;
                $orden_actual = obtenerSiguienteOrdenOpcion($conn, $pregunta_id);
                
                foreach ($opciones_array as $linea) {
                    $linea = trim($linea);
                    if (!empty($linea)) {
                        // Separar texto y configuración si está en formato "texto|negativa"
                        $partes = explode('|', $linea, 2);
                        $texto = trim($partes[0]);
                        $es_negativa = 0;
                        
                        // Verificar si se especificó que es negativa
                        if (isset($partes[1])) {
                            $config = strtolower(trim($partes[1]));
                            $es_negativa = in_array($config, ['1', 'negativa', 'neg', 'si', 'sí', 'true']) ? 1 : 0;
                        }
                        
                        if (crearOpcion($conn, $texto, $orden_actual, $es_negativa, $pregunta_id)) {
                            $creadas++;
                            $orden_actual++;
                        } else {
                            $errores++;
                        }
                    }
                }
                
                if ($creadas > 0) {
                    $mensaje = "Se crearon $creadas opciones exitosamente";
                    if ($errores > 0) {
                        $mensaje .= " (hubo $errores errores)";
                    }
                    $tipo_mensaje = 'success';
                } else {
                    $mensaje = 'No se pudieron crear las opciones';
                    $tipo_mensaje = 'error';
                }
            } else {
                $mensaje = 'Debe ingresar al menos una opción';
                $tipo_mensaje = 'error';
            }
            break;
            
        case 'reordenar':
            $orden_opciones = json_decode($_POST['orden_opciones'], true);
            if (reordenarOpciones($conn, $pregunta_id, $orden_opciones)) {
                $mensaje = 'Opciones reordenadas exitosamente';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'Error al reordenar las opciones';
                $tipo_mensaje = 'error';
            }
            break;
            
        case 'usar_plantilla':
            $plantilla_id = $_POST['plantilla_id'];
            $creadas = crearOpcionesDesdePlantilla($conn, $pregunta_id, $plantilla_id);
            if ($creadas > 0) {
                $mensaje = "Se crearon $creadas opciones desde la plantilla";
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'Error al aplicar la plantilla';
                $tipo_mensaje = 'error';
            }
            break;
            
        case 'guardar_plantilla':
            $nombre = trim($_POST['plantilla_nombre']);
            $descripcion = trim($_POST['plantilla_descripcion']);
            $categoria = $_POST['plantilla_categoria'];
            
            if (!empty($nombre)) {
                if (guardarComoPlantilla($conn, $pregunta_id, $nombre, $descripcion, $categoria)) {
                    $mensaje = 'Plantilla guardada exitosamente';
                    $tipo_mensaje = 'success';
                } else {
                    $mensaje = 'Error al guardar la plantilla';
                    $tipo_mensaje = 'error';
                }
            } else {
                $mensaje = 'El nombre de la plantilla es obligatorio';
                $tipo_mensaje = 'error';
            }
            break;
    }
}

// Obtener datos para edición si se solicita
$opcion_editar = null;
if (isset($_GET['editar'])) {
    $sql = "SELECT * FROM opcion_respuesta WHERE id = ? AND preguntum_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $_GET['editar'], $pregunta_id);
    $stmt->execute();
    $opcion_editar = $stmt->get_result()->fetch_assoc();
}

// Obtener plantillas disponibles
$plantillas = obtenerPlantillasOpciones($conn);

// Obtener opciones
$opciones = obtenerOpciones($conn, $pregunta_id);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Opciones - <?php echo htmlspecialchars($pregunta['texto']); ?></title>
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
            margin-bottom: 10px;
        }
        
        .pregunta-info {
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
        
        .formularios-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .formulario {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
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
        
        .btn-small {
            padding: 8px 15px;
            font-size: 14px;
        }
        
        .opciones-container {
            margin-top: 30px;
        }
        
        .opcion-item {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 10px;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .opcion-item:hover {
            background-color: #f8f9fa;
        }
        
        .opcion-item.opcion-negativa {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
        }
        
        .opcion-contenido {
            flex: 1;
        }
        
        .opcion-texto {
            font-size: 16px;
            margin-bottom: 5px;
            color: #333;
        }
        
        .opcion-acciones {
            display: flex;
            gap: 5px;
        }
        
        .instrucciones {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
        }
        
        .instrucciones h4 {
            color: #856404;
            margin-bottom: 10px;
        }
        
        .instrucciones ul {
            margin-left: 20px;
            color: #856404;
        }
        
        .ejemplo {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            font-family: monospace;
            margin-top: 10px;
            border-left: 3px solid #007bff;
        }
        
        .badge-negativa {
            background-color: #dc3545;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .badge-orden {
            background-color: #007bff;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            cursor: grab;
        }
        
        .badge-orden:active {
            cursor: grabbing;
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
        
        .acciones-rapidas {
            margin-top: 30px;
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        /* Estilos para validación de caracteres */
        .char-count {
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .char-count.warning {
            color: #ffc107;
        }
        
        .char-count.danger {
            color: #dc3545;
        }
        
        .char-count.success {
            color: #28a745;
        }
        
        .opcion-valida {
            color: #28a745;
            font-size: 0.875rem;
        }
        
        .opcion-invalida {
            color: #dc3545;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .opcion-warning {
            color: #ffc107;
            font-size: 0.875rem;
        }
        
        .input-invalid {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
        }
        
        .input-warning {
            border-color: #ffc107 !important;
            box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25) !important;
        }
        
        .input-valid {
            border-color: #28a745 !important;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25) !important;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .formularios-container {
                grid-template-columns: 1fr;
            }
            
            .opcion-item {
                flex-direction: column;
                gap: 10px;
            }
            
            .opcion-acciones {
                justify-content: center;
            }
            
            .acciones-rapidas form {
                display: block;
                margin: 10px 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>⚙️ Opciones de Respuesta</h1>
        
        <div class="pregunta-info">
            <h3>Pregunta: <?php echo htmlspecialchars($pregunta['texto']); ?></h3>
            <p><strong>Cuestionario:</strong> <?php echo htmlspecialchars($pregunta['cuestionario_titulo']); ?></p>
            <small>
                Pregunta ID: <?php echo $pregunta['id']; ?> | 
                Tipo: <?php echo ucfirst($pregunta['tipo']); ?> | 
                Requerida: <?php echo $pregunta['requerida'] ? 'Sí' : 'No'; ?> |
                Total opciones: <?php echo count($opciones); ?>
            </small>
        </div>
        
        <div class="navegacion">
            <a href="preguntas.php?cuestionario_id=<?php echo $pregunta['cuestionario_id']; ?>" class="btn btn-secondary">← Volver a Preguntas</a>
        </div>
        
        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo_mensaje; ?>">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>
        
        <!-- Formularios para crear opciones -->
        <div class="formularios-container">
            <!-- Formulario individual -->
            <div class="formulario">
                <h3><?php echo $opcion_editar ? '✏️ Editar Opción' : '➕ Nueva Opción'; ?></h3>
                <form method="POST">
                    <input type="hidden" name="accion" value="<?php echo $opcion_editar ? 'actualizar' : 'crear'; ?>">
                    <?php if ($opcion_editar): ?>
                        <input type="hidden" name="id" value="<?php echo $opcion_editar['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="texto">Texto de la Opción *</label>
                        <input type="text" class="form-control" id="texto" name="texto" required 
                               
                               value="<?php echo $opcion_editar ? htmlspecialchars($opcion_editar['texto']) : ''; ?>"
                               placeholder="Ej: Muy satisfecho">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 5px;">
                            <small style="color: #666;">
                                <strong>⚠️ Límite WhatsApp:</strong> Máximo 24 caracteres para listas
                            </small>
                            <small id="contador-texto">
                                <span id="chars-count">0</span>/24 caracteres
                            </small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="orden">Orden</label>
                        <input type="number" id="orden" name="orden" min="1"
                               value="<?php echo $opcion_editar ? $opcion_editar['orden'] : obtenerSiguienteOrdenOpcion($conn, $pregunta_id); ?>"
                               placeholder="Orden de la opción">
                        <small style="color: #666;">Define el orden de aparición de la opción</small>
                    </div>
                    
                    <div class="form-group">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <input type="checkbox" id="es_negativa" name="es_negativa" 
                                   <?php echo ($opcion_editar && $opcion_editar['es_negativa']) ? 'checked' : ''; ?>>
                            <label for="es_negativa" style="margin: 0;">Es opción negativa</label>
                        </div>
                        <small style="color: #666;">Marca si esta opción representa una respuesta negativa o desfavorable</small>
                    </div>
                    
                    <button type="submit" class="btn">
                        <?php echo $opcion_editar ? '✏️ Actualizar' : '➕ Crear Opción'; ?>
                    </button>
                    
                    <?php if ($opcion_editar): ?>
                        <a href="opciones.php?pregunta_id=<?php echo $pregunta_id; ?>" class="btn btn-secondary">❌ Cancelar</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Plantillas y creación múltiple -->
            <div class="formulario">
                <h3>🎯 Usar Plantillas</h3>
                
                <?php if (!empty($plantillas)): ?>
                    <div style="margin-bottom: 30px;">
                        <h5>📋 Plantillas Disponibles</h5>
                        <form method="POST">
                            <input type="hidden" name="accion" value="usar_plantilla">
                            <div class="form-group">
                                <label for="plantilla_id">Seleccionar Plantilla:</label>
                                <select id="plantilla_id" name="plantilla_id" required>
                                    <option value="">-- Selecciona una plantilla --</option>
                                    <?php 
                                    $categoria_actual = '';
                                    foreach ($plantillas as $plantilla): 
                                        if ($categoria_actual !== $plantilla['categoria']):
                                            if ($categoria_actual !== '') echo '</optgroup>';
                                            echo '<optgroup label="' . ucfirst($plantilla['categoria']) . '">';
                                            $categoria_actual = $plantilla['categoria'];
                                        endif;
                                    ?>
                                        <option value="<?php echo $plantilla['id']; ?>" 
                                                data-opciones='<?php echo htmlspecialchars($plantilla['opciones']); ?>'>
                                            <?php echo htmlspecialchars($plantilla['nombre']); ?>
                                            <?php if ($plantilla['descripcion']): ?>
                                                - <?php echo htmlspecialchars($plantilla['descripcion']); ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <?php if ($categoria_actual !== '') echo '</optgroup>'; ?>
                                </select>
                            </div>
                            <div id="preview-plantilla" style="display: none; margin-bottom: 15px;">
                                <strong>Vista previa:</strong>
                                <div id="preview-opciones" style="margin-top: 10px;"></div>
                            </div>
                            <button type="submit" class="btn btn-success">🎯 Aplicar Plantilla</button>
                        </form>
                    </div>
                    <hr>
                <?php endif; ?>
                
                <h5>📝 Crear Múltiples Opciones</h5>
                
                <div class="instrucciones">
                    <h4>💡 Instrucciones:</h4>
                    <ul>
                        <li><strong>Separar por comas:</strong> <code>Opción 1, Opción 2, Opción 3</code></li>
                        <li><strong>O una por línea:</strong> <code>Una opción por línea</code></li>
                        <li><strong>Para marcar como negativa:</strong> <code>Texto|negativa</code></li>
                        <li><strong>⚠️ Límite WhatsApp:</strong> Máximo 24 caracteres por opción</li>
                        <li><strong>El orden se asigna automáticamente</strong></li>
                    </ul>
                    
                    <div class="ejemplo">
                        <strong>✅ Ejemplo válido (con comas):</strong><br>
                        Excelente, Muy bueno, Bueno, Regular|negativa, Malo|negativa<br><br>

                        <strong>✅ Ejemplo válido (por líneas):</strong><br>
                        Muy satisfecho<br>
                        Satisfecho<br>
                        Neutral<br>
                        Insatisfecho|negativa<br>
                        Muy insatisfecho|negativa<br><br>

                        <strong>❌ Evitar textos largos:</strong><br>
                        <span style="color: #dc3545;">Extremadamente satisfecho con el servicio recibido</span> (53 chars - muy largo)
                    </div>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="accion" value="crear_multiple">
                    
                    <div class="form-group">
                        <label for="opciones_multiple">Opciones</label>
                        <textarea id="opciones_multiple" name="opciones_multiple" rows="6" 
                                  placeholder="Separadas por comas: Excelente, Bueno, Regular, Malo&#10;&#10;O una por línea:&#10;Excelente&#10;Bueno&#10;Regular&#10;Malo"></textarea>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 5px;">
                            <small style="color: #666;">
                                Puedes separar las opciones por <strong>comas</strong> o escribir <strong>una por línea</strong>. 
                                <strong style="color: #ffc107;">⚠️ Máximo 24 caracteres por opción (WhatsApp)</strong>
                            </small>
                            <small id="contador-opciones-multiple">
                                0 opciones detectadas
                            </small>
                        </div>
                        <div id="validacion-opciones" style="margin-top: 10px;"></div>
                    </div>
                    
                    <button type="submit" class="btn btn-success">📝 Crear Todas las Opciones</button>
                </form>
            </div>
        </div>
        
        <!-- Controles de reordenamiento -->
        <?php if (count($opciones) > 1): ?>
        <div class="reorder-controls">
            <p><strong>💡 Tip:</strong> Arrastra las opciones por el número de orden para reordenarlas</p>
            <button type="button" id="guardar-orden" class="btn btn-success" style="display: none;">💾 Guardar Nuevo Orden</button>
        </div>
        <?php endif; ?>
        
        <!-- Lista de opciones -->
        <div class="opciones-container">
            <h2>📋 Opciones Configuradas (<?php echo count($opciones); ?>)</h2>
            
            <?php if (empty($opciones)): ?>
                <div style="text-align: center; padding: 50px; color: #666;">
                    <h3>⚙️ No hay opciones configuradas</h3>
                    <p>Crea opciones usando los formularios de arriba</p>
                </div>
            <?php else: ?>
                <div id="opciones-list" class="sortable">
                    <?php foreach ($opciones as $opcion): ?>
                        <div class="opcion-item <?php echo $opcion['es_negativa'] ? 'opcion-negativa' : ''; ?>" data-id="<?php echo $opcion['id']; ?>">
                            <div class="opcion-contenido">
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                                    <span class="badge-orden" title="Arrastra para reordenar">
                                        #<?php echo $opcion['orden']; ?>
                                    </span>
                                    <?php if ($opcion['es_negativa']): ?>
                                        <span class="badge-negativa">Negativa</span>
                                    <?php endif; ?>
                                </div>
                                <div class="opcion-texto">
                                    <?php echo htmlspecialchars($opcion['texto']); ?>
                                </div>
                            </div>
                            
                            <div class="opcion-acciones">
                                <a href="?pregunta_id=<?php echo $pregunta_id; ?>&editar=<?php echo $opcion['id']; ?>" 
                                   class="btn btn-warning btn-small">✏️ Editar</a>
                                <form method="POST" style="display: inline;" 
                                      onsubmit="return confirm('¿Estás seguro de eliminar esta opción?\n\nEsta acción no se puede deshacer.')">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id" value="<?php echo $opcion['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-small">🗑️ Eliminar</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Acciones adicionales -->
                <div class="acciones-rapidas">
                    <h4>🎯 Acciones Rápidas</h4>
                    <p>Opciones predefinidas comunes:</p>
                    
                    <form method="POST">
                        <input type="hidden" name="accion" value="crear_multiple">
                        <input type="hidden" name="opciones_multiple" value="Sí, No|negativa">
                        <button type="submit" class="btn btn-success btn-small">✅ Agregar Sí/No</button>
                    </form>
                    
                    <form method="POST">
                        <input type="hidden" name="accion" value="crear_multiple">
                        <input type="hidden" name="opciones_multiple" value="Excelente, Muy bueno, Bueno, Regular|negativa, Malo|negativa">
                        <button type="submit" class="btn btn-success btn-small">⭐ Escala 1-5</button>
                    </form>
                    
                    <form method="POST">
                        <input type="hidden" name="accion" value="crear_multiple">
                        <input type="hidden" name="opciones_multiple" value="Muy de acuerdo, De acuerdo, Neutral, En desacuerdo|negativa, Muy en desacuerdo|negativa">
                        <button type="submit" class="btn btn-success btn-small">📊 Escala Likert</button>
                    </form>
                    
                    <form method="POST">
                        <input type="hidden" name="accion" value="crear_multiple">
                        <input type="hidden" name="opciones_multiple" value="Siempre, Frecuentemente, A veces, Nunca|negativa">
                        <button type="submit" class="btn btn-success btn-small">⏰ Frecuencia</button>
                    </form>
                    
                    <form method="POST">
                        <input type="hidden" name="accion" value="crear_multiple">
                        <input type="hidden" name="opciones_multiple" value="Primaria, Secundaria, Preparatoria, Universidad, Posgrado">
                        <button type="submit" class="btn btn-success btn-small">🎓 Nivel Educativo</button>
                    </form>
                    
                    <form method="POST">
                        <input type="hidden" name="accion" value="crear_multiple">
                        <input type="hidden" name="opciones_multiple" value="Totalmente satisfecho, Satisfecho, Neutral, Insatisfecho|negativa, Totalmente insatisfecho|negativa">
                        <button type="submit" class="btn btn-success btn-small">😊 Satisfacción</button>
                    </form>
                </div>
                
                <!-- Guardar como plantilla -->
                <?php if (count($opciones) > 0): ?>
                <div style="margin-top: 30px; padding: 20px; background: #fff3cd; border-radius: 8px; border-left: 4px solid #ffc107;">
                    <h5 style="color: #856404;">💾 Guardar como Plantilla</h5>
                    <p style="margin-bottom: 15px;">Tienes <?php echo count($opciones); ?> opciones configuradas. ¿Quieres guardarlas como plantilla para reutilizar en otras preguntas?</p>
                    
                    <form method="POST" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; align-items: end;">
                        <input type="hidden" name="accion" value="guardar_plantilla">
                        <div>
                            <input type="text" name="plantilla_nombre" placeholder="Nombre de la plantilla" required style="width: 100%; padding: 8px;">
                        </div>
                        <div>
                            <select name="plantilla_categoria" required style="width: 100%; padding: 8px;">
                                <option value="">-- Categoría --</option>
                                <option value="satisfaccion">Satisfacción</option>
                                <option value="frecuencia">Frecuencia</option>
                                <option value="calificacion">Calificación</option>
                                <option value="si_no">Sí/No</option>
                                <option value="educacion">Educación</option>
                                <option value="custom">Personalizada</option>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-warning btn-small" style="width: 100%;">💾 Guardar Plantilla</button>
                        </div>
                        <div style="grid-column: 1 / -1;">
                            <input type="text" name="plantilla_descripcion" placeholder="Descripción opcional de la plantilla" style="width: 100%; padding: 8px;">
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <script>
        // Preview de plantilla
        document.getElementById('plantilla_id').addEventListener('change', function() {
            const preview = document.getElementById('preview-plantilla');
            const previewOpciones = document.getElementById('preview-opciones');
            
            if (this.value) {
                try {
                    const opciones = JSON.parse(this.selectedOptions[0].dataset.opciones || '[]');
                    preview.style.display = 'block';
                    
                    let html = '';
                    opciones.forEach((opcion, index) => {
                        const negativeClass = opcion.es_negativa ? 'color: #dc3545;' : 'color: #28a745;';
                        const icon = opcion.es_negativa ? '❌' : '✅';
                        html += `<span style="${negativeClass} margin-right: 10px;">${icon} ${opcion.texto}</span>`;
                    });
                    previewOpciones.innerHTML = html;
                } catch (e) {
                    preview.style.display = 'none';
                }
            } else {
                preview.style.display = 'none';
            }
        });

        // Inicializar Sortable para reordenar opciones
        <?php if (count($opciones) > 1): ?>
        const sortable = Sortable.create(document.getElementById('opciones-list'), {
            handle: '.badge-orden',
            animation: 150,
            ghostClass: 'sortable-ghost',
            onEnd: function(evt) {
                document.getElementById('guardar-orden').style.display = 'inline-block';
            }
        });

        // Guardar nuevo orden
        document.getElementById('guardar-orden').addEventListener('click', function() {
            const opciones = Array.from(document.querySelectorAll('.opcion-item'));
            const orden = opciones.map(item => item.dataset.id);
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const accionInput = document.createElement('input');
            accionInput.type = 'hidden';
            accionInput.name = 'accion';
            accionInput.value = 'reordenar';
            
            const ordenInput = document.createElement('input');
            ordenInput.type = 'hidden';
            ordenInput.name = 'orden_opciones';
            ordenInput.value = JSON.stringify(orden);
            
            form.appendChild(accionInput);
            form.appendChild(ordenInput);
            document.body.appendChild(form);
            form.submit();
        });
        <?php endif; ?>

        // Auto-focus y validación de caracteres
        document.addEventListener('DOMContentLoaded', function() {
            const textoInput = document.getElementById('texto');
            const charsCount = document.getElementById('chars-count');
            const contadorTexto = document.getElementById('contador-texto');
            
            // Auto-focus en el campo de texto
            if (textoInput && !textoInput.value) {
                textoInput.focus();
            }
            
            // Actualizar contador de caracteres para campo individual
            function actualizarContadorIndividual() {
                const length = textoInput.value.length;
                charsCount.textContent = length;
                
                // Remover clases previas
                contadorTexto.style.color = '';
                textoInput.classList.remove('input-valid', 'input-warning', 'input-invalid');
                
                if (length === 0) {
                    contadorTexto.style.color = '#666';
                } else if (length <= 20) {
                    contadorTexto.style.color = '#28a745';
                    textoInput.classList.add('input-valid');
                } else if (length <= 24) {
                    contadorTexto.style.color = '#ffc107';
                    textoInput.classList.add('input-warning');
                } else {
                    contadorTexto.style.color = '#dc3545';
                    textoInput.classList.add('input-invalid');
                }
            }
            
            textoInput.addEventListener('input', actualizarContadorIndividual);
            actualizarContadorIndividual(); // Inicializar
            
            // Validación en tiempo real para textarea múltiple
            const textarea = document.getElementById('opciones_multiple');
            const contadorMultiple = document.getElementById('contador-opciones-multiple');
            const validacionDiv = document.getElementById('validacion-opciones');
            
            function validarOpcionesMultiples() {
                let opciones = [];
                if (textarea.value.includes(',')) {
                    opciones = textarea.value.split(',').map(opt => opt.trim()).filter(opt => opt);
                } else {
                    opciones = textarea.value.split('\n').map(opt => opt.trim()).filter(opt => opt);
                }
                
                // Actualizar contador
                contadorMultiple.textContent = `${opciones.length} opciones detectadas`;
                
                // Validar cada opción
                let validaciones = [];
                let hayErrores = false;
                let hayWarnings = false;
                
                opciones.forEach((opcion, index) => {
                    // Separar texto de configuración negativa
                    const partes = opcion.split('|');
                    const texto = partes[0].trim();
                    const length = texto.length;
                    
                    if (length === 0) {
                        validaciones.push(`<div class="opcion-invalida">• Opción ${index + 1}: Vacía</div>`);
                        hayErrores = true;
                    } else if (length > 24) {
                        validaciones.push(`<div class="opcion-invalida">• Opción ${index + 1}: "${texto}" (${length} chars - Excede límite WhatsApp)</div>`);
                        hayErrores = true;
                    } else if (length > 20) {
                        validaciones.push(`<div class="opcion-warning">• Opción ${index + 1}: "${texto}" (${length} chars - Cerca del límite)</div>`);
                        hayWarnings = true;
                    } else {
                        validaciones.push(`<div class="opcion-valida">• Opción ${index + 1}: "${texto}" (${length} chars - ✓)</div>`);
                    }
                });
                
                // Mostrar validaciones
                if (opciones.length > 0) {
                    let headerClass = hayErrores ? 'color: #dc3545;' : (hayWarnings ? 'color: #ffc107;' : 'color: #28a745;');
                    let headerText = hayErrores ? '❌ Hay opciones con errores:' : (hayWarnings ? '⚠️ Algunas opciones están cerca del límite:' : '✅ Todas las opciones son válidas:');
                    
                    validacionDiv.innerHTML = `
                        <div style="${headerClass} font-weight: bold; margin-bottom: 10px;">${headerText}</div>
                        ${validaciones.join('')}
                    `;
                    
                    // Cambiar estilo del textarea
                    textarea.classList.remove('input-valid', 'input-warning', 'input-invalid');
                    if (hayErrores) {
                        textarea.classList.add('input-invalid');
                    } else if (hayWarnings) {
                        textarea.classList.add('input-warning');
                    } else {
                        textarea.classList.add('input-valid');
                    }
                } else {
                    validacionDiv.innerHTML = '';
                    textarea.classList.remove('input-valid', 'input-warning', 'input-invalid');
                }
                
                // Actualizar contador múltiple
                contadorMultiple.style.color = '';
                if (opciones.length === 0) {
                    contadorMultiple.style.color = '#666';
                } else if (hayErrores) {
                    contadorMultiple.style.color = '#dc3545';
                } else if (hayWarnings) {
                    contadorMultiple.style.color = '#ffc107';
                } else {
                    contadorMultiple.style.color = '#28a745';
                }
            }
            
            textarea.addEventListener('input', validarOpcionesMultiples);
            validarOpcionesMultiples(); // Inicializar
        });

        // Validación del formulario múltiple
        document.querySelector('textarea[name="opciones_multiple"]').closest('form').addEventListener('submit', function(e) {
            const textarea = this.querySelector('textarea[name="opciones_multiple"]');
            let opciones = [];
            
            // Detectar si se usaron comas o saltos de línea
            if (textarea.value.includes(',')) {
                opciones = textarea.value.split(',').map(opt => opt.trim()).filter(opt => opt);
            } else {
                opciones = textarea.value.split('\n').map(opt => opt.trim()).filter(opt => opt);
            }
            
            if (opciones.length === 0) {
                e.preventDefault();
                alert('Debe ingresar al menos una opción');
                textarea.focus();
                return false;
            }
            
            // Validar límites de WhatsApp
            let opcionesInvalidas = [];
            opciones.forEach((opcion, index) => {
                const texto = opcion.split('|')[0].trim();
                if (texto.length > 24) {
                    opcionesInvalidas.push(`Opción ${index + 1}: "${texto}" (${texto.length} caracteres)`);
                }
            });
            
            if (opcionesInvalidas.length > 0) {
                e.preventDefault();
                alert(`❌ Las siguientes opciones exceden el límite de 24 caracteres para WhatsApp:\n\n${opcionesInvalidas.join('\n')}\n\nPor favor, acórtalas antes de continuar.`);
                textarea.focus();
                return false;
            }
            
            if (opciones.length > 10) {
                e.preventDefault();
                alert('❌ WhatsApp permite máximo 10 opciones por lista. Tienes ' + opciones.length + ' opciones.');
                textarea.focus();
                return false;
            }
            
            if (opciones.length > 5) {
                if (!confirm(`⚠️ Tienes ${opciones.length} opciones. WhatsApp recomienda máximo 10.\n\n¿Continuar?`)) {
                    e.preventDefault();
                    return false;
                }
            }
            
            // Mostrar preview de las opciones a crear
            const preview = opciones.slice(0, 3).map(opt => opt.split('|')[0].trim()).join(', ') + 
                          (opciones.length > 3 ? `, y ${opciones.length - 3} más...` : '');
            if (!confirm(`✅ Se crearán ${opciones.length} opciones válidas para WhatsApp:\n\n${preview}\n\n¿Continuar?`)) {
                e.preventDefault();
                return false;
            }
        });

        // Confirmar antes de usar plantillas si ya hay opciones
        <?php if (count($opciones) > 0): ?>
        document.querySelectorAll('.acciones-rapidas form').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!confirm('Ya tienes <?php echo count($opciones); ?> opciones configuradas.\n\n¿Estás seguro de agregar más opciones?')) {
                    e.preventDefault();
                    return false;
                }
            });
        });
        
        document.querySelector('form input[name="accion"][value="usar_plantilla"]').closest('form').addEventListener('submit', function(e) {
            if (!confirm('Ya tienes <?php echo count($opciones); ?> opciones configuradas.\n\n¿Estás seguro de agregar más opciones desde la plantilla?')) {
                e.preventDefault();
                return false;
            }
        });
        <?php endif; ?>

        // Validación del formulario individual
        document.querySelector('form[method="POST"]').addEventListener('submit', function(e) {
            // Solo validar si es el formulario individual
            if (this.querySelector('input[name="accion"]').value === 'crear' || this.querySelector('input[name="accion"]').value === 'actualizar') {
                const texto = document.getElementById('texto').value.trim();
                const orden = document.getElementById('orden').value;
                
                if (!texto) {
                    e.preventDefault();
                    alert('El texto de la opción es obligatorio');
                    document.getElementById('texto').focus();
                    return false;
                }
                
                // if (texto.length > 24) {
                //     e.preventDefault();
                //     alert(`❌ El texto excede el límite de WhatsApp (${texto.length}/24 caracteres).\n\nPor favor, acórtalo para que sea compatible con WhatsApp.`);
                //     document.getElementById('texto').focus();
                //     return false;
                // }
                
                if (orden && (orden < 1 || orden > 100)) {
                    e.preventDefault();
                    alert('El orden debe estar entre 1 y 100');
                    document.getElementById('orden').focus();
                    return false;
                }
                
                if (texto.length > 20) {
                    if (!confirm(`⚠️ El texto tiene ${texto.length} caracteres, está cerca del límite de WhatsApp (24).\n\n¿Continuar?`)) {
                        e.preventDefault();
                        return false;
                    }
                }
            }
        });
    </script>
</body>
</html>