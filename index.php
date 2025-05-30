<?php 
    // error_reporting(E_ALL);
    // ini_set('display_errors', 1);

    include 'db.php'; 
    
    require_once 'auth.php';
    
    // Función para obtener una vacante por ID
    function obtenerVacante($conn, $id) {
        $sql = "SELECT * FROM vacantes WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    
    // Función para actualizar vacante
    function actualizarVacante($conn, $id, $titulo, $descripcion, $requisitos, $fecha_publicacion, $salario_min, $salario_max, $sucursal_id, $area_id, $activa) {
        $sql = "UPDATE vacantes SET titulo = ?, descripcion = ?, requisitos = ?, fecha_publicacion = ?, salario_min = ?, salario_max = ?, sucursal_id = ?, area_id = ?, activa = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssiiiiiii", $titulo, $descripcion, $requisitos, $fecha_publicacion, $salario_min, $salario_max, $sucursal_id, $area_id, $activa, $id);
        return $stmt->execute();
    }
    
    // Función para eliminar vacante
    function eliminarVacante($conn, $id) {
        // Primero eliminar la relación en cuestionarios
        $sql1 = "UPDATE cuestionarios SET vacante_id = NULL WHERE vacante_id = ?";
        $stmt1 = $conn->prepare($sql1);
        $stmt1->bind_param("i", $id);
        $stmt1->execute();
        
        // Luego eliminar la vacante
        $sql2 = "DELETE FROM vacantes WHERE id = ?";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("i", $id);
        return $stmt2->execute();
    }
    
    // Procesar formularios
    $mensaje = '';
    $tipo_mensaje = '';
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $accion = $_POST['accion'] ?? '';
        
        switch ($accion) {
            case 'actualizar':
                $id = $_POST['id'];
                $titulo = trim($_POST['titulo']);
                $descripcion = trim($_POST['descripcion']);
                $requisitos = trim($_POST['requisitos']);
                $fecha_publicacion = $_POST['fecha_publicacion'];
                $salario_min = !empty($_POST['salario_min']) ? $_POST['salario_min'] : null;
                $salario_max = !empty($_POST['salario_max']) ? $_POST['salario_max'] : null;
                $sucursal_id = $_POST['sucursal_id'];
                $area_id = $_POST['area_id'];
                $activa = isset($_POST['activa']) ? 1 : 0;
                
                if (!empty($titulo) && !empty($fecha_publicacion) && !empty($sucursal_id) && !empty($area_id)) {
                    if (actualizarVacante($conn, $id, $titulo, $descripcion, $requisitos, $fecha_publicacion, $salario_min, $salario_max, $sucursal_id, $area_id, $activa)) {
                        $mensaje = 'Vacante actualizada exitosamente';
                        $tipo_mensaje = 'success';
                    } else {
                        $mensaje = 'Error al actualizar la vacante';
                        $tipo_mensaje = 'error';
                    }
                } else {
                    $mensaje = 'Todos los campos marcados con * son obligatorios';
                    $tipo_mensaje = 'error';
                }
                break;
                
            case 'eliminar':
                $id = $_POST['id'];
                if (eliminarVacante($conn, $id)) {
                    $mensaje = 'Vacante eliminada exitosamente';
                    $tipo_mensaje = 'success';
                } else {
                    $mensaje = 'Error al eliminar la vacante';
                    $tipo_mensaje = 'error';
                }
                break;
        }
    }
    
    // Obtener datos para edición si se solicita
    $vacante_editar = null;
    if (isset($_GET['editar'])) {
        $vacante_editar = obtenerVacante($conn, $_GET['editar']);
    }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Vacantes y Cuestionarios</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
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
        background: linear-gradient(135deg, #0d6efd 0%, #6610f2 100%);
        color: white;
        border-radius: 1rem;
    }
    
    .stat-number {
        font-size: 2.5rem;
        font-weight: 700;
    }
    
    .vacante-header {
        background: linear-gradient(135deg, #495057 0%, #6c757d 100%);
        color: white;
    }
    
    .salary-range {
        background-color: #e7f3ff;
        border-left: 4px solid #0d6efd;
        padding: 0.5rem;
        border-radius: 0.25rem;
    }
  </style>
</head>
<body>

<!-- Modal Asignar Cuestionario -->
<div class="modal fade" id="modalAsignarCuestionario" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="formAsignarCuestionario" class="modal-content" method="post" action="asignar_cuestionario.php">
      <div class="modal-header">
        <h5 class="modal-title" id="modalLabel">Asignar Cuestionario a Vacante</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="vacante_id" id="vacante_id_asignar">
        <div class="mb-3">
          <label for="cuestionario_id" class="form-label">Selecciona un cuestionario:</label>
          <select name="cuestionario_id" id="cuestionario_id" class="form-select" required>
            <option value="">-- Selecciona --</option>
            <?php
              $q = $conn->query("SELECT id, titulo FROM cuestionarios WHERE activo = 1");
              while ($c = $q->fetch_assoc()):
            ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['titulo']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Guardar</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Editar Vacante -->
<div class="modal fade" id="modalEditarVacante" tabindex="-1" aria-labelledby="modalEditarVacanteLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="post" id="formEditarVacante">
      <input type="hidden" name="accion" value="actualizar">
      <input type="hidden" name="id" id="vacante_id_editar">
      <div class="modal-header">
        <h5 class="modal-title" id="modalEditarVacanteLabel">Editar Vacante</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body row g-3">
        <div class="col-md-6">
          <label class="form-label">Título *</label>
          <input type="text" class="form-control" name="titulo" id="editar_titulo" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Fecha de Publicación *</label>
          <input type="date" class="form-control" name="fecha_publicacion" id="editar_fecha_publicacion" required>
        </div>
        <div class="col-12">
          <label class="form-label">Descripción</label>
          <textarea class="form-control" name="descripcion" id="editar_descripcion" rows="3"></textarea>
        </div>
        <div class="col-12">
          <label class="form-label">Requisitos</label>
          <textarea class="form-control" name="requisitos" id="editar_requisitos" rows="3"></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label">Salario Mínimo</label>
          <input type="number" class="form-control" name="salario_min" id="editar_salario_min" min="0" step="0.01">
        </div>
        <div class="col-md-6">
          <label class="form-label">Salario Máximo</label>
          <input type="number" class="form-control" name="salario_max" id="editar_salario_max" min="0" step="0.01">
        </div>
        <div class="col-md-6">
          <label class="form-label">Sucursal *</label>
          <select class="form-select" name="sucursal_id" id="editar_sucursal_id" required>
            <option value="">-- Selecciona --</option>
            <?php
            $sucursales = $conn->query("SELECT id, nombre FROM sucursals WHERE activa = 1 ORDER BY nombre");
            while ($s = $sucursales->fetch_assoc()):
            ?>
              <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nombre']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Área *</label>
          <select class="form-select" name="area_id" id="editar_area_id" required>
            <option value="">-- Selecciona --</option>
            <?php
            $areas = $conn->query("SELECT id, nombre FROM areas");
            while ($a = $areas->fetch_assoc()):
            ?>
              <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['nombre']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="col-12">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="activa" id="editar_activa" value="1">
            <label class="form-check-label" for="editar_activa">Vacante Activa</label>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Actualizar Vacante</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Nueva Vacante -->
<div class="modal fade" id="modalNuevaVacante" tabindex="-1" aria-labelledby="modalNuevaVacanteLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="post" action="crear_vacante.php">
      <div class="modal-header">
        <h5 class="modal-title">Agregar Nueva Vacante</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body row g-3">
        <div class="col-md-6">
          <label class="form-label">Título *</label>
          <input type="text" class="form-control" name="titulo" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Fecha de Publicación *</label>
          <input type="date" class="form-control" name="fecha_publicacion" required>
        </div>
        <div class="col-12">
          <label class="form-label">Descripción</label>
          <textarea class="form-control" name="descripcion" rows="3"></textarea>
        </div>
        <div class="col-12">
          <label class="form-label">Requisitos</label>
          <textarea class="form-control" name="requisitos" rows="3"></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label">Salario Mínimo</label>
          <input type="number" class="form-control" name="salario_min" min="0" step="0.01">
        </div>
        <div class="col-md-6">
          <label class="form-label">Salario Máximo</label>
          <input type="number" class="form-control" name="salario_max" min="0" step="0.01">
        </div>
        <div class="col-md-6">
          <label class="form-label">Sucursal *</label>
          <select class="form-select" name="sucursal_id" required>
            <option value="">-- Selecciona --</option>
            <?php
            $sucursales = $conn->query("SELECT id, nombre FROM sucursals WHERE activa = 1 ORDER BY nombre");
            while ($s = $sucursales->fetch_assoc()):
            ?>
              <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nombre']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Área *</label>
          <select class="form-select" name="area_id" required>
            <option value="">-- Selecciona --</option>
            <?php
            $areas = $conn->query("SELECT id, nombre FROM areas");
            while ($a = $areas->fetch_assoc()):
            ?>
              <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['nombre']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="col-12">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="activa" value="1" checked>
            <label class="form-check-label">Vacante Activa</label>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success">Guardar</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<?php include 'navbar.php'; ?>

<div class="container mt-4">
    
    
    <!-- Mensajes -->
    <?php if ($mensaje): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-<?php echo $tipo_mensaje === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                    <?php echo $mensaje; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Estadísticas -->
    <?php
    $total_vacantes = $conn->query("SELECT COUNT(*) as total FROM vacantes")->fetch_assoc()['total'];
    $vacantes_activas = $conn->query("SELECT COUNT(*) as total FROM vacantes WHERE activa = 1")->fetch_assoc()['total'];
    $con_cuestionarios = $conn->query("SELECT COUNT(DISTINCT v.id) as total FROM vacantes v INNER JOIN cuestionarios c ON c.vacante_id = v.id")->fetch_assoc()['total'];
    ?>
    
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stat-card">
                <div class="card-body text-center">
                    <div class="stat-number"><?php echo $total_vacantes; ?></div>
                    <div class="fw-semibold">Total Vacantes</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stat-card">
                <div class="card-body text-center">
                    <div class="stat-number"><?php echo $vacantes_activas; ?></div>
                    <div class="fw-semibold">Activas</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stat-card">
                <div class="card-body text-center">
                    <div class="stat-number"><?php echo $con_cuestionarios; ?></div>
                    <div class="fw-semibold">Con Cuestionarios</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stat-card">
                <div class="card-body text-center">
                    <div class="stat-number"><?php echo $total_vacantes - $con_cuestionarios; ?></div>
                    <div class="fw-semibold">Sin Cuestionarios</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Botón Nueva Vacante -->
    <div class="row mb-4">
        <div class="col-12 text-center">
            <button class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#modalNuevaVacante">
                ➕ Nueva Vacante
            </button>
        </div>
    </div>
    
    <?php
    $sql = "SELECT 
            v.id AS vacante_id,
            v.titulo AS vacante,
            v.descripcion,
            v.requisitos,
            v.fecha_publicacion,
            v.salario_min,
            v.salario_max,
            v.activa,
            v.created_at,
            v.updated_at,
            s.nombre AS sucursal,
            a.nombre AS area,
            c.id AS cuestionario_id,
            c.titulo AS cuestionario
        FROM vacantes v
        LEFT JOIN sucursals s ON s.id = v.sucursal_id
        LEFT JOIN areas a ON a.id = v.area_id
        LEFT JOIN cuestionarios c ON c.vacante_id = v.id
        ORDER BY v.created_at DESC";
    $result = $conn->query($sql);
    $agrupados = [];

    while ($row = $result->fetch_assoc()) {
        $vacante_id = $row['vacante_id'];
        if (!isset($agrupados[$vacante_id])) {
            $agrupados[$vacante_id] = [
                'vacante_id' => $vacante_id,
                'titulo' => $row['vacante'],
                'descripcion' => $row['descripcion'],
                'requisitos' => $row['requisitos'],
                'fecha_publicacion' => $row['fecha_publicacion'],
                'salario_min' => $row['salario_min'],
                'salario_max' => $row['salario_max'],
                'activa' => $row['activa'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
                'sucursal' => $row['sucursal'],
                'area' => $row['area'],
                'cuestionarios' => []
            ];
        }

        if ($row['cuestionario_id']) {
            $agrupados[$vacante_id]['cuestionarios'][] = [
                'cuestionario_id' => $row['cuestionario_id'],
                'cuestionario' => $row['cuestionario']
            ];
        }
    }
    ?>

    <!-- Lista de Vacantes -->
    <div class="row">
        <div class="col-12">
            <?php if (empty($agrupados)): ?>
                <div class="card custom-card">
                    <div class="card-body text-center py-5">
                        <div class="mb-3">
                            <i style="font-size: 4rem;">💼</i>
                        </div>
                        <h4 class="text-muted">No hay vacantes registradas</h4>
                        <p class="text-muted">Crea tu primera vacante usando el botón de arriba</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($agrupados as $vacante): ?>
                  <div class="card custom-card mb-4">
                    <div class="card-header vacante-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1"><?= htmlspecialchars($vacante['titulo']) ?></h5>
                            <small class="opacity-75">
                                Creada: <?= date('d/m/Y', strtotime($vacante['created_at'])) ?> | 
                                Publicación: <?= date('d/m/Y', strtotime($vacante['fecha_publicacion'])) ?>
                            </small>
                        </div>
                        <span class="badge <?= $vacante['activa'] ? 'bg-success' : 'bg-danger' ?> fs-6">
                            <?= $vacante['activa'] ? 'Activa' : 'Inactiva' ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">                                
                                <p class="mb-2"><strong>Sucursal:</strong> <?= htmlspecialchars($vacante['sucursal'] ?? 'No asignada') ?></p>
                                <p class="mb-2"><strong>Área:</strong> <?= htmlspecialchars($vacante['area'] ?? 'No asignada') ?></p>
                            </div>
                            <div class="col-md-4">
                                <?php if ($vacante['salario_min'] || $vacante['salario_max']): ?>
                                    <div class="salary-range mb-3">
                                        <h6 class="mb-1"><i class="me-1">💰</i>Rango Salarial</h6>
                                        <?php if ($vacante['salario_min'] && $vacante['salario_max']): ?>
                                            <span>$<?= number_format($vacante['salario_min'], 2) ?> - $<?= number_format($vacante['salario_max'], 2) ?></span>
                                        <?php elseif ($vacante['salario_min']): ?>
                                            <span>Desde $<?= number_format($vacante['salario_min'], 2) ?></span>
                                        <?php elseif ($vacante['salario_max']): ?>
                                            <span>Hasta $<?= number_format($vacante['salario_max'], 2) ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Cuestionarios asociados -->
                        <?php if (!empty($vacante['cuestionarios'])): ?>
                            <h6 class="mt-3 mb-2"><i class="me-1">📋</i>Cuestionarios Asociados:</h6>
                            <div class="list-group">
                                <?php foreach ($vacante['cuestionarios'] as $cuestionario): ?>
                                  <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><?= htmlspecialchars($cuestionario['cuestionario']) ?></span>
                                    <a href="ver_cuestionario.php?id=<?= $cuestionario['cuestionario_id'] ?>" 
                                       class="btn btn-sm btn-outline-info">📝 Ver cuestionario</a>
                                  </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning mt-3" role="alert">
                                <i class="me-1">⚠️</i>Esta vacante no tiene cuestionarios asociados
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            Actualizada: <?= date('d/m/Y H:i', strtotime($vacante['updated_at'])) ?>
                        </small>
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-outline-primary" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#modalAsignarCuestionario" 
                                    data-vacante-id="<?= $vacante['vacante_id'] ?>">
                                📋 Asignar Cuestionario
                            </button>
                            <button class="btn btn-sm btn-outline-warning" 
                                    onclick="cargarDatosEdicion(<?= htmlspecialchars(json_encode($vacante)) ?>)">
                                ✏️ Editar
                            </button>
                            <form method="POST" style="display: inline;" 
                                  onsubmit="return confirm('¿Estás seguro de eliminar la vacante \'<?= htmlspecialchars($vacante['titulo']) ?>\'?\n\nEsta acción no se puede deshacer.')">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= $vacante['vacante_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">🗑️ Eliminar</button>
                            </form>
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
  // Modal asignar cuestionario
  const modalAsignar = document.getElementById('modalAsignarCuestionario');
  modalAsignar.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    const vacanteId = button.getAttribute('data-vacante-id');
    document.getElementById('vacante_id_asignar').value = vacanteId;
  });
  
  // Función para cargar datos en modal de edición
  function cargarDatosEdicion(vacante) {
    // Cargar datos en los campos del modal
    document.getElementById('vacante_id_editar').value = vacante.vacante_id;
    document.getElementById('editar_titulo').value = vacante.titulo;
    document.getElementById('editar_descripcion').value = vacante.descripcion || '';
    document.getElementById('editar_requisitos').value = vacante.requisitos || '';
    document.getElementById('editar_fecha_publicacion').value = vacante.fecha_publicacion;
    document.getElementById('editar_salario_min').value = vacante.salario_min || '';
    document.getElementById('editar_salario_max').value = vacante.salario_max || '';
    document.getElementById('editar_activa').checked = vacante.activa == 1;
    
    // Hacer petición AJAX para obtener sucursal_id y area_id
    fetch(`obtener_vacante.php?id=${vacante.vacante_id}`)
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          document.getElementById('editar_sucursal_id').value = data.vacante.sucursal_id || '';
          document.getElementById('editar_area_id').value = data.vacante.area_id || '';
        }
      })
      .catch(error => {
        console.error('Error:', error);
      });
    
    // Mostrar el modal
    const modal = new bootstrap.Modal(document.getElementById('modalEditarVacante'));
    modal.show();
  }
  
  // Validación de salarios
  document.getElementById('editar_salario_min').addEventListener('input', function() {
    const salarioMax = document.getElementById('editar_salario_max');
    if (this.value && salarioMax.value && parseFloat(this.value) > parseFloat(salarioMax.value)) {
      salarioMax.setCustomValidity('El salario máximo debe ser mayor al mínimo');
    } else {
      salarioMax.setCustomValidity('');
    }
  });
  
  document.getElementById('editar_salario_max').addEventListener('input', function() {
    const salarioMin = document.getElementById('editar_salario_min');
    if (this.value && salarioMin.value && parseFloat(this.value) < parseFloat(salarioMin.value)) {
      this.setCustomValidity('El salario máximo debe ser mayor al mínimo');
    } else {
      this.setCustomValidity('');
    }
  });
  
  // Validación del formulario de edición
  document.getElementById('formEditarVacante').addEventListener('submit', function(e) {
    const titulo = document.getElementById('editar_titulo').value.trim();
    const fechaPublicacion = document.getElementById('editar_fecha_publicacion').value;
    const sucursalId = document.getElementById('editar_sucursal_id').value;
    const areaId = document.getElementById('editar_area_id').value;
    
    if (!titulo || !fechaPublicacion || !sucursalId || !areaId) {
      e.preventDefault();
      alert('Todos los campos marcados con * son obligatorios');
      return false;
    }
    
    if (titulo.length < 3) {
      e.preventDefault();
      alert('El título debe tener al menos 3 caracteres');
      document.getElementById('editar_titulo').focus();
      return false;
    }
  });
  
  // Inicializar tooltips
  document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl);
    });
  });
</script>

</body>
</html>