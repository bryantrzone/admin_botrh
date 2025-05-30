<?php
require_once 'db.php';

// Función para duplicar cuestionario
function duplicarCuestionario($conn, $cuestionario_id_original, $nuevo_titulo, $nueva_descripcion = '', $nueva_vacante_id = null) {
    $conn->begin_transaction();
    
    try {
        // 1. Obtener cuestionario original
        $sql = "SELECT * FROM cuestionarios WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $cuestionario_id_original);
        $stmt->execute();
        $cuestionario_original = $stmt->get_result()->fetch_assoc();
        
        if (!$cuestionario_original) {
            throw new Exception('Cuestionario original no encontrado');
        }
        
        // 2. Crear nuevo cuestionario
        $sql = "INSERT INTO cuestionarios (titulo, descripcion, activo, vacante_id, created_at, updated_at) 
                VALUES (?, ?, ?, ?, NOW(), NOW())";
        $stmt = $conn->prepare($sql);
        $activo = 1; // Por defecto activo
        $stmt->bind_param("ssii", $nuevo_titulo, $nueva_descripcion, $activo, $nueva_vacante_id);
        $stmt->execute();
        $nuevo_cuestionario_id = $conn->insert_id;
        
        // 3. Obtener preguntas del cuestionario original
        $sql = "SELECT * FROM pregunta WHERE cuestionario_id = ? ORDER BY orden ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $cuestionario_id_original);
        $stmt->execute();
        $preguntas_originales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // 4. Duplicar cada pregunta
        foreach ($preguntas_originales as $pregunta_original) {
            // Crear nueva pregunta
            $sql = "INSERT INTO pregunta (texto, requerida, orden, metadatos, cuestionario_id, tipo_preguntum_id, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("siisii", 
                $pregunta_original['texto'],
                $pregunta_original['requerida'],
                $pregunta_original['orden'],
                $pregunta_original['metadatos'],
                $nuevo_cuestionario_id,
                $pregunta_original['tipo_preguntum_id']
            );
            $stmt->execute();
            $nueva_pregunta_id = $conn->insert_id;
            
            // 5. Obtener opciones de la pregunta original (si las tiene)
            $sql = "SELECT * FROM opcion_respuesta WHERE preguntum_id = ? ORDER BY orden ASC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $pregunta_original['id']);
            $stmt->execute();
            $opciones_originales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // 6. Duplicar opciones
            foreach ($opciones_originales as $opcion_original) {
                $sql = "INSERT INTO opcion_respuesta (texto, orden, es_negativa, preguntum_id, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, NOW(), NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("siii",
                    $opcion_original['texto'],
                    $opcion_original['orden'],
                    $opcion_original['es_negativa'],
                    $nueva_pregunta_id
                );
                $stmt->execute();
            }
        }
        
        $conn->commit();
        return $nuevo_cuestionario_id;
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error duplicando cuestionario: " . $e->getMessage());
        return false;
    }
}

// Función para obtener cuestionario por ID
function obtenerCuestionario($conn, $id) {
    $sql = "SELECT c.*, v.titulo as vacante_titulo 
            FROM cuestionarios c 
            LEFT JOIN vacantes v ON c.vacante_id = v.id 
            WHERE c.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Función para obtener vacantes activas
function obtenerVacantesActivas($conn) {
    $sql = "SELECT id, titulo FROM vacantes WHERE activa = 1 ORDER BY titulo";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

$mensaje = '';
$tipo_mensaje = '';
$cuestionario_duplicado_id = null;

// Procesar formulario de duplicación
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cuestionario_id_original = $_POST['cuestionario_id_original'];
    $nuevo_titulo = trim($_POST['nuevo_titulo']);
    $nueva_descripcion = trim($_POST['nueva_descripcion']);
    $nueva_vacante_id = !empty($_POST['nueva_vacante_id']) ? $_POST['nueva_vacante_id'] : null;
    
    if (!empty($nuevo_titulo)) {
        $cuestionario_duplicado_id = duplicarCuestionario($conn, $cuestionario_id_original, $nuevo_titulo, $nueva_descripcion, $nueva_vacante_id);
        
        if ($cuestionario_duplicado_id) {
            $mensaje = 'Cuestionario duplicado exitosamente con ID: ' . $cuestionario_duplicado_id;
            $tipo_mensaje = 'success';
        } else {
            $mensaje = 'Error al duplicar el cuestionario';
            $tipo_mensaje = 'error';
        }
    } else {
        $mensaje = 'El título del nuevo cuestionario es obligatorio';
        $tipo_mensaje = 'error';
    }
}

// Obtener cuestionario original si se especifica ID
$cuestionario_original = null;
if (isset($_GET['id'])) {
    $cuestionario_original = obtenerCuestionario($conn, $_GET['id']);
    if (!$cuestionario_original) {
        header('Location: cuestionarios.php');
        exit;
    }
}

// Obtener vacantes para el selector
$vacantes = obtenerVacantesActivas($conn);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Duplicar Cuestionario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .cuestionario-original {
            background: #e7f3ff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid #007bff;
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
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        
        input[type="text"], textarea, select {
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
        
        .informacion-duplicacion {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
        }
        
        .acciones-finales {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <h1>📋 Duplicar Cuestionario</h1>
        
        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo_mensaje; ?>">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($cuestionario_duplicado_id): ?>
            <div class="acciones-finales">
                <h4>✅ ¡Cuestionario duplicado exitosamente!</h4>
                <p>El cuestionario se ha duplicado con todas sus preguntas y opciones.</p>
                <div style="margin-top: 20px;">
                    <a href="preguntas.php?cuestionario_id=<?php echo $cuestionario_duplicado_id; ?>" class="btn btn-success">
                        📝 Ver Preguntas del Nuevo Cuestionario
                    </a>
                    <a href="cuestionarios.php" class="btn">
                        📋 Ver Todos los Cuestionarios
                    </a>
                    <a href="duplicar_cuestionario.php" class="btn btn-secondary">
                        🔄 Duplicar Otro Cuestionario
                    </a>
                </div>
            </div>
        <?php else: ?>
            
            <?php if ($cuestionario_original): ?>
                <!-- Mostrar información del cuestionario original -->
                <div class="cuestionario-original">
                    <h3>📄 Cuestionario Original</h3>
                    <p><strong>Título:</strong> <?php echo htmlspecialchars($cuestionario_original['titulo']); ?></p>
                    <p><strong>Descripción:</strong> <?php echo htmlspecialchars($cuestionario_original['descripcion'] ?: 'Sin descripción'); ?></p>
                    <p><strong>Vacante asociada:</strong> <?php echo $cuestionario_original['vacante_titulo'] ? htmlspecialchars($cuestionario_original['vacante_titulo']) : 'Ninguna'; ?></p>
                    <p><strong>Estado:</strong> <?php echo $cuestionario_original['activo'] ? 'Activo' : 'Inactivo'; ?></p>
                    <small>ID: <?php echo $cuestionario_original['id']; ?> | 
                           Creado: <?php echo date('d/m/Y H:i', strtotime($cuestionario_original['created_at'])); ?>
                    </small>
                </div>
            <?php endif; ?>
            
            <div class="informacion-duplicacion">
                <h5>ℹ️ Información sobre la duplicación</h5>
                <ul style="margin: 10px 0 0 20px;">
                    <li>Se duplicará el cuestionario completo con todas sus preguntas</li>
                    <li>Se copiarán todas las opciones de respuesta de las preguntas tipo "opciones"</li>
                    <li>Se mantendrá el orden original de preguntas y opciones</li>
                    <li>El nuevo cuestionario se creará como "Activo" por defecto</li>
                    <li>Puedes asignarlo a una vacante diferente o dejarlo sin asignar</li>
                </ul>
            </div>
            
            <!-- Formulario de duplicación -->
            <form method="POST">
                <?php if ($cuestionario_original): ?>
                    <input type="hidden" name="cuestionario_id_original" value="<?php echo $cuestionario_original['id']; ?>">
                <?php else: ?>
                    <div class="form-group">
                        <label for="cuestionario_id_original">Seleccionar Cuestionario a Duplicar *</label>
                        <select id="cuestionario_id_original" name="cuestionario_id_original" required>
                            <option value="">-- Selecciona un cuestionario --</option>
                            <?php
                            $sql = "SELECT c.id, c.titulo, c.descripcion, v.titulo as vacante_titulo 
                                    FROM cuestionarios c 
                                    LEFT JOIN vacantes v ON c.vacante_id = v.id 
                                    ORDER BY c.created_at DESC";
                            $result = $conn->query($sql);
                            while ($row = $result->fetch_assoc()):
                            ?>
                                <option value="<?php echo $row['id']; ?>">
                                    <?php echo htmlspecialchars($row['titulo']); ?>
                                    <?php if ($row['vacante_titulo']): ?>
                                        (Vacante: <?php echo htmlspecialchars($row['vacante_titulo']); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="nuevo_titulo">Título del Nuevo Cuestionario *</label>
                    <input type="text" id="nuevo_titulo" name="nuevo_titulo" required 
                           value="<?php echo $cuestionario_original ? 'Copia de ' . htmlspecialchars($cuestionario_original['titulo']) : ''; ?>"
                           placeholder="Ej: Copia de Evaluación - Ventas">
                </div>
                
                <div class="form-group">
                    <label for="nueva_descripcion">Descripción del Nuevo Cuestionario</label>
                    <textarea id="nueva_descripcion" name="nueva_descripcion" 
                              placeholder="Descripción del cuestionario duplicado"><?php echo $cuestionario_original ? htmlspecialchars($cuestionario_original['descripcion']) : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="nueva_vacante_id">Asignar a Vacante (opcional)</label>
                    <select id="nueva_vacante_id" name="nueva_vacante_id">
                        <option value="">-- Sin asignar a vacante --</option>
                        <?php foreach ($vacantes as $vacante): ?>
                            <option value="<?php echo $vacante['id']; ?>">
                                <?php echo htmlspecialchars($vacante['titulo']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #666; margin-top: 5px; display: block;">
                        Puedes asignar el cuestionario duplicado a una vacante diferente o dejarlo sin asignar
                    </small>
                </div>
                
                <div style="margin-top: 30px;">
                    <button type="submit" class="btn btn-success">
                        📋 Duplicar Cuestionario
                    </button>
                    <a href="cuestionarios.php" class="btn btn-secondary">❌ Cancelar</a>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-focus en el campo de título
        document.addEventListener('DOMContentLoaded', function() {
            const tituloInput = document.getElementById('nuevo_titulo');
            if (tituloInput) {
                tituloInput.focus();
                // Seleccionar el texto si es una copia predefinida
                if (tituloInput.value.startsWith('Copia de ')) {
                    tituloInput.select();
                }
            }
        });
        
        // Validación del formulario
        document.querySelector('form').addEventListener('submit', function(e) {
            const titulo = document.getElementById('nuevo_titulo').value.trim();
            const cuestionarioOriginal = document.querySelector('input[name="cuestionario_id_original"], select[name="cuestionario_id_original"]').value;
            
            if (!titulo) {
                e.preventDefault();
                alert('El título del nuevo cuestionario es obligatorio');
                document.getElementById('nuevo_titulo').focus();
                return false;
            }
            
            if (!cuestionarioOriginal) {
                e.preventDefault();
                alert('Debe seleccionar un cuestionario para duplicar');
                return false;
            }
            
            if (titulo.length < 3) {
                e.preventDefault();
                alert('El título debe tener al menos 3 caracteres');
                document.getElementById('nuevo_titulo').focus();
                return false;
            }
            
            // Confirmar duplicación
            if (!confirm(`¿Estás seguro de duplicar el cuestionario?\n\nSe creará: "${titulo}"\n\nEsta acción copiará todas las preguntas y opciones.`)) {
                e.preventDefault();
                return false;
            }
        });
        
        // Cambio en selector de cuestionario original (si no hay uno predefinido)
        const selectorCuestionario = document.getElementById('cuestionario_id_original');
        if (selectorCuestionario && selectorCuestionario.tagName === 'SELECT') {
            selectorCuestionario.addEventListener('change', function() {
                const tituloInput = document.getElementById('nuevo_titulo');
                const selectedOption = this.selectedOptions[0];
                
                if (selectedOption && selectedOption.value) {
                    const tituloOriginal = selectedOption.textContent.split('(')[0].trim();
                    tituloInput.value = 'Copia de ' + tituloOriginal;
                }
            });
        }
    </script>
</body>
</html>