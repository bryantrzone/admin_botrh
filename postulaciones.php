<?php
require_once 'auth.php';
include 'db.php';

// Función para obtener estadísticas
function obtenerEstadisticas($conn) {
    $stats = [];
    
    // Total de postulaciones
    $result = $conn->query("SELECT COUNT(*) as total FROM postulacions");
    $stats['total'] = $result->fetch_assoc()['total'];
    
    // Postulaciones de hoy
    $result = $conn->query("SELECT COUNT(*) as hoy FROM postulacions WHERE DATE(created_at) = CURDATE()");
    $stats['hoy'] = $result->fetch_assoc()['hoy'];
    
    // Postulaciones de esta semana
    $result = $conn->query("SELECT COUNT(*) as semana FROM postulacions WHERE WEEK(created_at) = WEEK(NOW()) AND YEAR(created_at) = YEAR(NOW())");
    $stats['semana'] = $result->fetch_assoc()['semana'];
    
    // Vacante más popular (con más postulaciones)
    $result = $conn->query("
        SELECT v.titulo, COUNT(*) as cantidad 
        FROM postulacions p 
        JOIN vacantes v ON v.id = p.vacante_id 
        GROUP BY p.vacante_id 
        ORDER BY cantidad DESC 
        LIMIT 1
    ");
    $popular = $result->fetch_assoc();
    $stats['vacante_popular'] = $popular ? $popular['titulo'] : 'N/A';
    $stats['popular_count'] = $popular ? $popular['cantidad'] : 0;
    
    return $stats;
}

// Función para obtener áreas
function obtenerAreas($conn) {
    $sql = "SELECT DISTINCT a.id, a.nombre 
            FROM areas a 
            INNER JOIN vacantes v ON a.id = v.area_id 
            INNER JOIN postulacions p ON v.id = p.vacante_id 
            ORDER BY a.nombre";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Función para obtener sucursales
function obtenerSucursales($conn) {
    $sql = "SELECT DISTINCT s.id, s.nombre 
            FROM sucursals s 
            INNER JOIN vacantes v ON s.id = v.sucursal_id 
            INNER JOIN postulacions p ON v.id = p.vacante_id 
            ORDER BY s.nombre";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Filtros
$filtro_vacante = $_GET['vacante'] ?? '';
$filtro_fecha = $_GET['fecha'] ?? '';
$filtro_busqueda = $_GET['busqueda'] ?? '';
$filtro_area = $_GET['area'] ?? '';
$filtro_sucursal = $_GET['sucursal'] ?? '';

// Construir consulta con filtros
$sql = "
SELECT p.id AS post_id, 
       p.created_at,
       c.id AS candidato_id,
       c.nombre, c.apellido, c.email, c.telefono,
       v.id AS vacante_id,
       v.titulo AS vacante,
       s.nombre AS sucursal,
       s.id AS sucursal_id,
       a.nombre AS area,
       a.id AS area_id
FROM postulacions p
JOIN candidatos c ON c.id = p.candidato_id
JOIN vacantes v ON v.id = p.vacante_id
LEFT JOIN sucursals s ON s.id = v.sucursal_id
LEFT JOIN areas a ON a.id = v.area_id
WHERE 1=1
";

$params = [];
$types = "";

if (!empty($filtro_vacante)) {
    $sql .= " AND v.id = ?";
    $params[] = $filtro_vacante;
    $types .= "i";
}

if (!empty($filtro_fecha)) {
    $sql .= " AND DATE(p.created_at) = ?";
    $params[] = $filtro_fecha;
    $types .= "s";
}

if (!empty($filtro_busqueda)) {
    $sql .= " AND (c.nombre LIKE ? OR c.apellido LIKE ? OR c.email LIKE ?)";
    $busqueda = "%" . $filtro_busqueda . "%";
    $params[] = $busqueda;
    $params[] = $busqueda;
    $params[] = $busqueda;
    $types .= "sss";
}

if (!empty($filtro_area)) {
    $sql .= " AND a.id = ?";
    $params[] = $filtro_area;
    $types .= "i";
}

if (!empty($filtro_sucursal)) {
    $sql .= " AND s.id = ?";
    $params[] = $filtro_sucursal;
    $types .= "i";
}

$sql .= " ORDER BY p.created_at DESC LIMIT 100";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

// Obtener lista de vacantes para el filtro
$vacantes_result = $conn->query("SELECT id, titulo FROM vacantes WHERE activa = 1 ORDER BY titulo");

// Obtener áreas y sucursales para filtros
$areas = obtenerAreas($conn);
$sucursales = obtenerSucursales($conn);

// Obtener estadísticas
$stats = obtenerEstadisticas($conn);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Postulaciones - Sistema RH</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <style>
    :root {
        --navbar-height: 76px;
    }
    
    body {
        background-color: #f8f9fa;
        padding-top: calc(var(--navbar-height) + 1rem);
    }
    
    .custom-card {
        border: none;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        transition: all 0.3s ease;
    }
    
    .custom-card:hover {
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        transform: translateY(-2px);
    }
    
    .stat-card {
        background: linear-gradient(135deg, #ed9a0c 0%, #ff6b35 100%);
        color: white;
        border-radius: 1rem;
        transition: transform 0.3s ease;
    }
    
    .stat-card:hover {
        transform: scale(1.05);
    }
    
    .stat-number {
        font-size: 2.5rem;
        font-weight: 700;
    }
    
    .filter-card {
        background: #e7f3ff;
        border-left: 4px solid #ed9a0c;
    }
    
    .postulacion-item {
        transition: all 0.3s ease;
    }
    
    .postulacion-item:hover {
        background-color: #f8f9fa;
    }
    
    .badge-vacante {
        background-color: #6f42c1;
    }
    
    .badge-sucursal {
        background-color: #17a2b8;
    }
    
    .badge-area {
        background-color: #28a745;
    }
    
    .filters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        align-items: end;
    }
    
    .filter-actions {
        display: flex;
        gap: 0.5rem;
        flex-direction: column;
    }
    
    .active-filters {
        background: #fff3cd;
        border-left: 4px solid #ffc107;
        padding: 1rem;
        margin-bottom: 1rem;
        border-radius: 0.5rem;
    }
    
    .filter-tag {
        display: inline-block;
        background: #ed9a0c;
        color: white;
        padding: 0.25rem 0.75rem;
        border-radius: 1rem;
        font-size: 0.875rem;
        margin: 0.25rem 0.25rem 0.25rem 0;
        text-decoration: none;
    }
    
    .filter-tag:hover {
        background: #d48806;
        color: white;
        text-decoration: none;
    }
    
    .filter-tag .remove {
        margin-left: 0.5rem;
        font-weight: bold;
    }
    
    @media (max-width: 768px) {
        .stat-number {
            font-size: 2rem;
        }
        
        .table-responsive {
            font-size: 0.875rem;
        }
        
        .filters-grid {
            grid-template-columns: 1fr;
        }
        
        .filter-actions {
            flex-direction: row;
        }
    }
  </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container-fluid mt-4">
    
    <!-- Filtros Activos -->
    <?php 
    $filtros_activos = [];
    if (!empty($filtro_vacante)) {
        $vacante_nombre = $conn->query("SELECT titulo FROM vacantes WHERE id = $filtro_vacante")->fetch_assoc();
        $filtros_activos[] = ['tipo' => 'vacante', 'valor' => $filtro_vacante, 'texto' => 'Vacante: ' . $vacante_nombre['titulo']];
    }
    if (!empty($filtro_area)) {
        $area_nombre = $conn->query("SELECT nombre FROM areas WHERE id = $filtro_area")->fetch_assoc();
        $filtros_activos[] = ['tipo' => 'area', 'valor' => $filtro_area, 'texto' => 'Área: ' . $area_nombre['nombre']];
    }
    if (!empty($filtro_sucursal)) {
        $sucursal_nombre = $conn->query("SELECT nombre FROM sucursals WHERE id = $filtro_sucursal")->fetch_assoc();
        $filtros_activos[] = ['tipo' => 'sucursal', 'valor' => $filtro_sucursal, 'texto' => 'Sucursal: ' . $sucursal_nombre['nombre']];
    }
    if (!empty($filtro_fecha)) {
        $filtros_activos[] = ['tipo' => 'fecha', 'valor' => $filtro_fecha, 'texto' => 'Fecha: ' . date('d/m/Y', strtotime($filtro_fecha))];
    }
    if (!empty($filtro_busqueda)) {
        $filtros_activos[] = ['tipo' => 'busqueda', 'valor' => $filtro_busqueda, 'texto' => 'Búsqueda: ' . $filtro_busqueda];
    }
    
    if (!empty($filtros_activos)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="active-filters">
                    <h6 class="mb-2">🔍 Filtros Activos:</h6>
                    <div>
                        <?php foreach ($filtros_activos as $filtro): ?>
                            <?php
                            $url_params = $_GET;
                            unset($url_params[$filtro['tipo']]);
                            $remove_url = '?' . http_build_query($url_params);
                            if ($remove_url === '?') $remove_url = 'postulaciones.php';
                            ?>
                            <a href="<?php echo $remove_url; ?>" class="filter-tag">
                                <?php echo htmlspecialchars($filtro['texto']); ?>
                                <span class="remove">×</span>
                            </a>
                        <?php endforeach; ?>
                        <a href="postulaciones.php" class="filter-tag" style="background: #dc3545;">
                            Limpiar todos ×
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Filtros -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card filter-card">
                <div class="card-body">
                    <h5 class="card-title mb-3">🔍 Filtros de Búsqueda</h5>
                    <form method="GET">
                        <div class="filters-grid">
                            <div>
                                <label for="area" class="form-label">Área</label>
                                <select class="form-select" id="area" name="area">
                                    <option value="">Todas las áreas</option>
                                    <?php foreach ($areas as $area): ?>
                                        <option value="<?php echo $area['id']; ?>" 
                                                <?php echo ($filtro_area == $area['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($area['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label for="sucursal" class="form-label">Sucursal</label>
                                <select class="form-select" id="sucursal" name="sucursal">
                                    <option value="">Todas las sucursales</option>
                                    <?php foreach ($sucursales as $sucursal): ?>
                                        <option value="<?php echo $sucursal['id']; ?>" 
                                                <?php echo ($filtro_sucursal == $sucursal['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($sucursal['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label for="vacante" class="form-label">Vacante</label>
                                <select class="form-select" id="vacante" name="vacante">
                                    <option value="">Todas las vacantes</option>
                                    <?php while ($vacante = $vacantes_result->fetch_assoc()): ?>
                                        <option value="<?php echo $vacante['id']; ?>" 
                                                <?php echo ($filtro_vacante == $vacante['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($vacante['titulo']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label for="fecha" class="form-label">Fecha</label>
                                <input type="date" class="form-control" id="fecha" name="fecha" 
                                       value="<?php echo htmlspecialchars($filtro_fecha); ?>">
                            </div>
                            
                            <div>
                                <label for="busqueda" class="form-label">Buscar Candidato</label>
                                <input type="text" class="form-control" id="busqueda" name="busqueda" 
                                       value="<?php echo htmlspecialchars($filtro_busqueda); ?>"
                                       placeholder="Nombre, apellido o email">
                            </div>
                            
                            <div class="filter-actions">
                                <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
                                <a href="postulaciones.php" class="btn btn-outline-secondary btn-sm">Limpiar</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Lista de Postulaciones -->
    <div class="row">
        <div class="col-12">
            <div class="card custom-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">📋 Postulaciones Recientes</h5>
                    <span class="badge bg-primary fs-6">
                        <?php echo $result->num_rows; ?> resultados
                    </span>
                </div>
                <div class="card-body p-0">
                    <?php if ($result->num_rows == 0): ?>
                        <div class="text-center py-5">
                            <div class="mb-3">
                                <i style="font-size: 4rem;">📄</i>
                            </div>
                            <h4 class="text-muted">No hay postulaciones</h4>
                            <p class="text-muted">
                                <?php if (!empty($filtros_activos)): ?>
                                    No se encontraron postulaciones con los filtros aplicados.
                                    <br><a href="postulaciones.php" class="btn btn-outline-primary mt-2">Ver todas</a>
                                <?php else: ?>
                                    Aún no hay candidatos que se hayan postulado a las vacantes.
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Candidato</th>
                                        <th>Contacto</th>
                                        <th>Vacante</th>
                                        <th>Ubicación</th>
                                        <th>Fecha Postulación</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr class="postulacion-item">
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($row['nombre'] . ' ' . $row['apellido']); ?></strong>
                                                    <br>
                                                    <small class="text-muted">ID: <?php echo $row['candidato_id']; ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <span class="d-block">📧 <?php echo htmlspecialchars($row['email']); ?></span>
                                                    <?php if ($row['telefono']): ?>
                                                        <span class="d-block text-muted">📞 <?php echo htmlspecialchars($row['telefono']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-vacante text-white">
                                                    <?php echo htmlspecialchars($row['vacante']); ?>
                                                </span>
                                                <br>
                                                <small class="text-muted">ID: <?php echo $row['vacante_id']; ?></small>
                                            </td>
                                            <td>
                                                <?php if ($row['sucursal']): ?>
                                                    <span class="badge badge-sucursal text-white d-block mb-1">
                                                        🏪 <?php echo htmlspecialchars($row['sucursal']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($row['area']): ?>
                                                    <span class="badge badge-area text-white d-block">
                                                        📁 <?php echo htmlspecialchars($row['area']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="fw-semibold">
                                                    <?php echo date('d/m/Y', strtotime($row['created_at'])); ?>
                                                </span>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo date('H:i', strtotime($row['created_at'])); ?>
                                                </small>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group-vertical btn-group-sm" role="group">
                                                    <a href="ver_respuestas.php?id=<?php echo $row['post_id']; ?>" 
                                                       class="btn btn-info btn-sm"
                                                       data-bs-toggle="tooltip" 
                                                       title="Ver respuestas del cuestionario">
                                                        📝 Ver Respuestas
                                                    </a>                                                    
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($result->num_rows > 0): ?>
                    <div class="card-footer text-muted">
                        <small>
                            Mostrando <?php echo $result->num_rows; ?> postulaciones. 
                            <?php if ($result->num_rows >= 100): ?>
                                (Limitado a 100 resultados. Usa filtros para refinar la búsqueda.)
                            <?php endif; ?>
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    
    
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Auto-submit del formulario cuando se cambia un filtro
        const filterSelects = document.querySelectorAll('#area, #sucursal, #vacante, #fecha');
        filterSelects.forEach(select => {
            select.addEventListener('change', function() {
                if (this.value !== '' || hasOtherFilters(this)) {
                    this.closest('form').submit();
                }
            });
        });
        
        // Función para verificar si hay otros filtros activos
        function hasOtherFilters(currentElement) {
            const form = currentElement.closest('form');
            const inputs = form.querySelectorAll('input, select');
            
            for (let input of inputs) {
                if (input !== currentElement && input.value !== '') {
                    return true;
                }
            }
            return false;
        }
        
        // Highlight de búsqueda
        const busquedaInput = document.getElementById('busqueda');
        const termino = busquedaInput.value.toLowerCase();
        
        if (termino) {
            document.querySelectorAll('td').forEach(td => {
                if (td.textContent.toLowerCase().includes(termino)) {
                    td.innerHTML = td.innerHTML.replace(
                        new RegExp(`(${termino})`, 'gi'),
                        '<mark>$1</mark>'
                    );
                }
            });
        }
        
        // Confirmación para acciones masivas
        document.querySelectorAll('a[href*="exportar"]').forEach(link => {
            link.addEventListener('click', function(e) {
                if (!confirm('¿Deseas exportar las postulaciones? Esta acción puede tomar unos momentos.')) {
                    e.preventDefault();
                }
            });
        });
        
        // Limpiar filtros individualmente
        document.querySelectorAll('.filter-tag').forEach(tag => {
            tag.addEventListener('click', function(e) {
                e.preventDefault();
                window.location.href = this.href;
            });
        });
    });
</script>

</body>
</html>