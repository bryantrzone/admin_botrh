<?php
require_once 'auth.php';
include 'db.php';

$postulacion_id = intval($_GET['id']);

// Función para obtener información completa de la postulación
function obtenerPostulacionCompleta($conn, $postulacion_id) {
    $sql = "
    SELECT 
        p.id as postulacion_id,
        p.created_at as fecha_postulacion,
        c.id as candidato_id,
        c.nombre,
        c.apellido,
        c.email,
        c.telefono,
        c.created_at as fecha_registro_candidato,
        v.id as vacante_id,
        v.titulo as vacante_titulo,
        v.descripcion as vacante_descripcion,
        v.salario_min,
        v.salario_max,
        s.nombre as sucursal,
        s.direccion as sucursal_direccion,
        s.zona as sucursal_zona,
        a.nombre as area,
        cu.titulo as cuestionario_titulo,
        cu.descripcion as cuestionario_descripcion
    FROM postulacions p
    JOIN candidatos c ON c.id = p.candidato_id
    JOIN vacantes v ON v.id = p.vacante_id
    LEFT JOIN sucursals s ON s.id = v.sucursal_id
    LEFT JOIN areas a ON a.id = v.area_id
    LEFT JOIN cuestionarios cu ON cu.vacante_id = v.id
    WHERE p.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $postulacion_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Función para obtener respuestas del candidato
function obtenerRespuestas($conn, $postulacion_id) {
    $sql = "
    SELECT 
        p.texto AS pregunta,
        p.requerida,
        p.orden,
        r.respuesta_texto,
        tp.tipo as tipo_pregunta,
        tp.descripcion as tipo_descripcion
    FROM respuesta r
    JOIN pregunta p ON p.id = r.preguntum_id
    JOIN tipo_pregunta tp ON p.tipo_preguntum_id = tp.id
    WHERE r.postulacion_id = ?
    ORDER BY p.orden ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $postulacion_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Función para obtener estadísticas de respuestas
function obtenerEstadisticasRespuestas($respuestas) {
    $stats = [
        'total_preguntas' => count($respuestas),
        'preguntas_respondidas' => 0,
        'preguntas_vacias' => 0,
        'respuestas_largas' => 0,
        'respuestas_cortas' => 0
    ];
    
    foreach ($respuestas as $respuesta) {
        if (!empty(trim($respuesta['respuesta_texto']))) {
            $stats['preguntas_respondidas']++;
            $longitud = strlen(trim($respuesta['respuesta_texto']));
            if ($longitud > 100) {
                $stats['respuestas_largas']++;
            } else {
                $stats['respuestas_cortas']++;
            }
        } else {
            $stats['preguntas_vacias']++;
        }
    }
    
    return $stats;
}

// Obtener datos
$postulacion = obtenerPostulacionCompleta($conn, $postulacion_id);

if (!$postulacion) {
    header('Location: postulaciones.php');
    exit;
}

$respuestas = obtenerRespuestas($conn, $postulacion_id);
$stats = obtenerEstadisticasRespuestas($respuestas);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Respuestas - <?php echo htmlspecialchars($postulacion['nombre'] . ' ' . $postulacion['apellido']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --navbar-height: 70px;
        }
        
        body {
            background-color: #f8f9fa;
            padding-top: calc(var(--navbar-height) + 1rem);
        }
        
        .header-card {
            background: linear-gradient(135deg, #6c6c6c 0%, #141414 100%);
            color: white;
            border-radius: 15px;
            margin-bottom: 2rem;            
        }
        
        .candidate-avatar {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            color: white;
        }
        
        .info-card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border-radius: 15px;
            transition: all 0.3s ease;
        }
        
        .info-card:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }
        
        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            margin-bottom: 1rem;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            font-size: 0.8rem;
            opacity: 0.9;
        }
        
        .question-card {
            border: none;
            border-radius: 12px;
            margin-bottom: 1rem;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            scroll-margin-top: calc(var(--navbar-height) + 6rem);
        }
        
        .question-card:hover {
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
        }
        
        .question-card:target {
            animation: highlightQuestion 2s ease-in-out;
        }
        
        @keyframes highlightQuestion {
            0% { box-shadow: 0 0 0 4px rgba(237, 154, 12, 0.3); }
            100% { box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08); }
        }
        
        .question-header {
            background: #f8f9fa;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #dee2e6;
        }
        
        .question-number {
            background: #ed9a0c;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
            margin-right: 0.75rem;
        }
        
        .question-text {
            font-weight: 600;
            color: #333;
            margin: 0;
        }
        
        .question-type {
            background: #17a2b8;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 15px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }
        
        .required-badge {
            background: #dc3545;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 15px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }
        
        .answer-content {
            padding: 1.5rem;
        }
        
        .answer-text {
            background: #ffffff;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            min-height: 60px;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        
        .answer-text.empty {
            background: #f8f9fa;
            color: #6c757d;
            font-style: italic;
            border-style: dashed;
        }
        
        .answer-text.long {
            border-left: 4px solid #28a745;
        }
        
        .answer-text.short {
            border-left: 4px solid #ffc107;
        }
        
        .back-button {
            position: fixed;
            top: calc(var(--navbar-height) + 1.5rem);
            left: 1rem;
            z-index: 1000;
            border-radius: 50px;
            padding: 0.5rem 1rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .questions-navigation {
            position: sticky;
            top: calc(var(--navbar-height) + 1rem);
            z-index: 999;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }
        
        .nav-pills .nav-link {
            border-radius: 20px;
            margin: 0 0.25rem;
            font-size: 0.8rem;
            padding: 0.5rem 0.75rem;
            transition: all 0.3s ease;
        }
        
        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, #ed9a0c 0%, #ff6b35 100%);
            border: none;
        }
        
        .nav-pills .nav-link:not(.active) {
            background: #f8f9fa;
            color: #6c757d;
            border: 1px solid #dee2e6;
        }
        
        .nav-pills .nav-link:not(.active):hover {
            background: #e9ecef;
            color: #495057;
        }
        
        .questions-container {
            max-height: 70vh;
            overflow-y: auto;
            scroll-behavior: smooth;
        }
        
        .questions-container::-webkit-scrollbar {
            width: 8px;
        }
        
        .questions-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .questions-container::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #ed9a0c 0%, #ff6b35 100%);
            border-radius: 10px;
        }
        
        .progress-bar-custom {
            height: 8px;
            border-radius: 4px;
            background: linear-gradient(90deg, #ed9a0c 0%, #ff6b35 100%);
        }
        
        .timeline-item {
            position: relative;
            padding-left: 2rem;
            margin-bottom: 1rem;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: 0.5rem;
            top: 0;
            bottom: 0;
            width: 3px;
            background: linear-gradient(180deg, #ed9a0c 0%, #ff6b35 100%);
        }
        
        /* .timeline-item::after {
            content: '';
            position: absolute;
            left: 0;
            top: 0.5rem;
            width: 1rem;
            height: 1rem;
            background: #ed9a0c;
            border-radius: 50%;
            border: 3px solid white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        } */
        
        .timeline-item:last-child::before {
            display: none;
        }
        
        @media (max-width: 768px) {
            .back-button {
                position: relative;
                top: auto;
                left: auto;
                margin-bottom: 1rem;
            }
            
            .candidate-avatar {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }
            
            .stat-box {
                margin-bottom: 0.5rem;
            }
            
            .questions-navigation {
                position: relative;
                top: auto;
            }
            
            .nav-pills .nav-link {
                font-size: 0.75rem;
                padding: 0.4rem 0.6rem;
            }
            
            .questions-container {
                max-height: none;
            }
            
            body {
                padding-top: calc(var(--navbar-height) + 1rem);
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container-fluid px-4 mt-4">
        
        <!-- Header con información del candidato -->
        <div class="card header-card">
            <div class="card-body p-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center">
                            <div class="candidate-avatar me-4">
                                <?php 
                                $initials = strtoupper(substr($postulacion['nombre'], 0, 1) . substr($postulacion['apellido'], 0, 1));
                                echo $initials;
                                ?>
                            </div>
                            <div>
                                <h2 class="mb-1"><?php echo htmlspecialchars($postulacion['nombre'] . ' ' . $postulacion['apellido']); ?></h2>
                                <p class="mb-2 opacity-90">
                                    📧 <?php echo htmlspecialchars($postulacion['email']); ?>
                                    <?php if ($postulacion['telefono']): ?>
                                        | 📞 <?php echo htmlspecialchars($postulacion['telefono']); ?>
                                    <?php endif; ?>
                                </p>
                                <p class="mb-0 opacity-75">
                                    Postulado a: <strong><?php echo htmlspecialchars($postulacion['vacante_titulo']); ?></strong>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <div class="timeline-item">
                            <strong>Registro:</strong><br>
                            <small><?php echo date('d/m/Y H:i', strtotime($postulacion['fecha_registro_candidato'])); ?></small>
                        </div>
                        <div class="timeline-item">
                            <strong>Postulación:</strong><br>
                            <small><?php echo date('d/m/Y H:i', strtotime($postulacion['fecha_postulacion'])); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Panel izquierdo - Información adicional -->
            <div class="col-lg-4 mb-4">
                
                <!-- Estadísticas de respuestas -->
                <div class="card info-card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">📊 Estadísticas de Respuestas</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <div class="stat-box">
                                    <div class="stat-number"><?php echo $stats['total_preguntas']; ?></div>
                                    <div class="stat-label">Total Preguntas</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stat-box">
                                    <div class="stat-number"><?php echo $stats['preguntas_respondidas']; ?></div>
                                    <div class="stat-label">Respondidas</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Barra de progreso -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">                                
                                <span><?php echo $stats['total_preguntas'] > 0 ? round(($stats['preguntas_respondidas'] / $stats['total_preguntas']) * 100) : 0; ?>%</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar progress-bar-custom" style="width: <?php echo $stats['total_preguntas'] > 0 ? ($stats['preguntas_respondidas'] / $stats['total_preguntas']) * 100 : 0; ?>%"></div>
                            </div>
                        </div>                        
                    </div>
                </div>
                
                <!-- Información de la vacante -->
                <div class="card info-card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">💼 Información de la Vacante</h5>
                    </div>
                    <div class="card-body">
                        <h6 class="fw-bold mb-2"><?php echo htmlspecialchars($postulacion['vacante_titulo']); ?></h6>
                        
                        <?php if ($postulacion['vacante_descripcion']): ?>
                            <p class="text-muted mb-3"><?php echo htmlspecialchars($postulacion['vacante_descripcion']); ?></p>
                        <?php endif; ?>
                        
                        <div class="mb-2">
                            <strong>🏪 Sucursal:</strong> <?php echo htmlspecialchars($postulacion['sucursal'] ?? 'No especificada'); ?>
                            <?php if ($postulacion['sucursal_zona']): ?>
                                <span class="badge bg-info ms-1"><?php echo htmlspecialchars($postulacion['sucursal_zona']); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-2">
                            <strong>📁 Área:</strong> <?php echo htmlspecialchars($postulacion['area'] ?? 'No especificada'); ?>
                        </div>
                        
                        
                        
                        <?php if ($postulacion['sucursal_direccion']): ?>
                            <div class="mb-2">
                                <strong>📍 Dirección:</strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($postulacion['sucursal_direccion']); ?></small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Información del cuestionario -->
                <?php if ($postulacion['cuestionario_titulo']): ?>
                <div class="card info-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">📋 Cuestionario Aplicado</h5>
                    </div>
                    <div class="card-body">
                        <h6 class="fw-bold"><?php echo htmlspecialchars($postulacion['cuestionario_titulo']); ?></h6>
                        <?php if ($postulacion['cuestionario_descripcion']): ?>
                            <p class="text-muted mb-0"><?php echo htmlspecialchars($postulacion['cuestionario_descripcion']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Panel derecho - Respuestas -->
            <div class="col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3>Respuestas del Candidato</h3>
                    <span class="badge bg-primary fs-6"><?php echo count($respuestas); ?> preguntas</span>
                </div>
                
                <?php if (empty($respuestas)): ?>
                    <div class="card info-card">
                        <div class="card-body text-center py-5">
                            <div class="mb-3">
                                <i style="font-size: 4rem;">📝</i>
                            </div>
                            <h4 class="text-muted">No hay respuestas registradas</h4>
                            <p class="text-muted">El candidato aún no ha completado el cuestionario o no hay cuestionario asignado a esta vacante.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($respuestas as $index => $respuesta): ?>
                        <div class="question-card">
                            <div class="question-header">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <span class="question-number"><?php echo $index + 1; ?></span>
                                        <p class="question-text"><?php echo htmlspecialchars($respuesta['pregunta']); ?></p>
                                    </div>
                                    <div>
                                        <span class="question-type"><?php echo ucfirst($respuesta['tipo_pregunta']); ?></span>                                        
                                    </div>
                                </div>
                            </div>
                            <div class="answer-content">
                                <?php 
                                $answer_text = trim($respuesta['respuesta_texto']);
                                $is_empty = empty($answer_text);
                                $is_long = strlen($answer_text) > 100;
                                $answer_class = $is_empty ? 'empty' : ($is_long ? 'long' : 'short');
                                ?>
                                <div class="answer-text <?php echo $answer_class; ?>">
                                    <?php if ($is_empty): ?>
                                        <em>Sin respuesta</em>
                                    <?php else: ?>
                                        <?php echo nl2br(htmlspecialchars($answer_text)); ?>
                                    <?php endif; ?>
                                </div>
                                
                               
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para exportar PDF (placeholder)
        function exportarPDF() {
            alert('Funcionalidad de exportar PDF en desarrollo.\n\nPor ahora puedes usar "Imprimir" y seleccionar "Guardar como PDF".');
        }
        
        // Smooth scroll para navegación
        document.addEventListener('DOMContentLoaded', function() {
            // Agregar números de pregunta al scroll
            const questions = document.querySelectorAll('.question-card');
            questions.forEach((question, index) => {
                question.setAttribute('data-question', index + 1);
            });
            
            // Efecto hover en las tarjetas de preguntas
            questions.forEach(question => {
                question.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateX(5px)';
                });
                
                question.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateX(0)';
                });
            });
        });
        
        // Estilos de impresión
        const printStyles = `
            <style>
                @media print {
                    .back-button, .navbar, .btn { display: none !important; }
                    .container-fluid { margin: 0; padding: 0; }
                    .question-card { break-inside: avoid; margin-bottom: 1rem; }
                    .header-card { background: #f8f9fa !important; color: #333 !important; }
                }
            </style>
        `;
        document.head.insertAdjacentHTML('beforeend', printStyles);
    </script>
</body>
</html>