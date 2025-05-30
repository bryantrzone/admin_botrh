<?php
require_once 'db.php';
require_once 'auth.php';

// Función para obtener todas las sucursales
function obtenerSucursales($conn) {
    $sql = "SELECT * FROM sucursals ORDER BY created_at DESC";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Función para obtener una sucursal por ID
function obtenerSucursal($conn, $id) {
    $sql = "SELECT * FROM sucursals WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Función para crear sucursal
function crearSucursal($conn, $nombre, $direccion, $zona, $activa) {
    $sql = "INSERT INTO sucursals (nombre, direccion, zona, activa, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssi", $nombre, $direccion, $zona, $activa);
    return $stmt->execute();
}

// Función para actualizar sucursal
function actualizarSucursal($conn, $id, $nombre, $direccion, $zona, $activa) {
    $sql = "UPDATE sucursals SET nombre = ?, direccion = ?, zona = ?, activa = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssii", $nombre, $direccion, $zona, $activa, $id);
    return $stmt->execute();
}

// Función para eliminar sucursal
function eliminarSucursal($conn, $id) {
    $sql = "DELETE FROM sucursals WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}

// Procesar formularios
$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    switch ($accion) {
        case 'crear':
            $nombre = trim($_POST['nombre']);
            $direccion = trim($_POST['direccion']);
            $zona = $_POST['zona'];
            $activa = isset($_POST['activa']) ? 1 : 0;
            
            if (!empty($nombre)) {
                if (crearSucursal($conn, $nombre, $direccion, $zona, $activa)) {
                    $mensaje = 'Sucursal creada exitosamente';
                    $tipo_mensaje = 'success';
                } else {
                    $mensaje = 'Error al crear la sucursal';
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
            $direccion = trim($_POST['direccion']);
            $zona = $_POST['zona'];
            $activa = isset($_POST['activa']) ? 1 : 0;
            
            if (!empty($nombre)) {
                if (actualizarSucursal($conn, $id, $nombre, $direccion, $zona, $activa)) {
                    $mensaje = 'Sucursal actualizada exitosamente';
                    $tipo_mensaje = 'success';
                } else {
                    $mensaje = 'Error al actualizar la sucursal';
                    $tipo_mensaje = 'error';
                }
            } else {
                $mensaje = 'El nombre es obligatorio';
                $tipo_mensaje = 'error';
            }
            break;
            
        case 'eliminar':
            $id = $_POST['id'];
            if (eliminarSucursal($conn, $id)) {
                $mensaje = 'Sucursal eliminada exitosamente';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'Error al eliminar la sucursal';
                $tipo_mensaje = 'error';
            }
            break;
    }
}

// Obtener datos para edición si se solicita
$sucursal_editar = null;
if (isset($_GET['editar'])) {
    $sucursal_editar = obtenerSucursal($conn, $_GET['editar']);
}

// Obtener todas las sucursales
$sucursales = obtenerSucursales($conn);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administración de Sucursales</title>
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
        
        .estado.activa {
            background-color: #28a745;
        }
        
        .estado.inactiva {
            background-color: #dc3545;
        }
        
        .zona {
            padding: 5px 10px;
            border-radius: 15px;
            color: white;
            font-size: 12px;
            font-weight: bold;
        }
        
        .zona.zona-norte {
            background-color: #17a2b8;
        }
        
        .zona.zona-centro {
            background-color: #6f42c1;
        }
        
        .acciones {
            display: flex;
            gap: 5px;
        }
        
        .btn-small {
            padding: 8px 15px;
            font-size: 14px;
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
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        
        <div style="margin-bottom: 20px; text-align: center;">
            <a href="importar.php" class="btn" style="background-color: #28a745;">📤 Importar desde Excel</a>        
        </div>
        
        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo_mensaje; ?>">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>
        
        <!-- Formulario para crear/editar -->
        <div class="formulario">
            <h2><?php echo $sucursal_editar ? 'Editar Sucursal' : 'Nueva Sucursal'; ?></h2>
            <form method="POST">
                <input type="hidden" name="accion" value="<?php echo $sucursal_editar ? 'actualizar' : 'crear'; ?>">
                <?php if ($sucursal_editar): ?>
                    <input type="hidden" name="id" value="<?php echo $sucursal_editar['id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="nombre">Nombre de la Sucursal *</label>
                    <input type="text" id="nombre" name="nombre" required 
                           value="<?php echo $sucursal_editar ? htmlspecialchars($sucursal_editar['nombre']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="direccion">Dirección</label>
                    <textarea id="direccion" name="direccion" 
                              placeholder="Ingrese la dirección de la sucursal"><?php echo $sucursal_editar ? htmlspecialchars($sucursal_editar['direccion']) : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="zona">Zona *</label>
                    <select id="zona" name="zona" required>
                        <option value="">Seleccione una zona</option>
                        <option value="Norte" <?php echo ($sucursal_editar && $sucursal_editar['zona'] == 'Norte') ? 'selected' : ''; ?>>Norte</option>
                        <option value="Centro" <?php echo (!$sucursal_editar || $sucursal_editar['zona'] == 'Centro') ? 'selected' : ''; ?>>Centro</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="activa" name="activa" 
                               <?php echo (!$sucursal_editar || $sucursal_editar['activa']) ? 'checked' : ''; ?>>
                        <label for="activa">Sucursal activa</label>
                    </div>
                </div>
                
                <button type="submit" class="btn">
                    <?php echo $sucursal_editar ? 'Actualizar Sucursal' : 'Crear Sucursal'; ?>
                </button>
                
                <?php if ($sucursal_editar): ?>
                    <a href="index.php" class="btn btn-secondary">Cancelar</a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Lista de sucursales -->
        <h2>Lista de Sucursales</h2>
        <?php if (empty($sucursales)): ?>
            <p>No hay sucursales registradas.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Dirección</th>
                            <th>Zona</th>
                            <th>Estado</th>
                            <th>Creada</th>
                            <th>Actualizada</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sucursales as $sucursal): ?>
                            <tr>
                                <td><?php echo $sucursal['id']; ?></td>
                                <td><?php echo htmlspecialchars($sucursal['nombre']); ?></td>
                                <td><?php echo htmlspecialchars($sucursal['direccion']); ?></td>
                                <td>
                                    <span class="zona zona-<?php echo strtolower($sucursal['zona']); ?>">
                                        <?php echo $sucursal['zona']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="estado <?php echo $sucursal['activa'] ? 'activa' : 'inactiva'; ?>">
                                        <?php echo $sucursal['activa'] ? 'Activa' : 'Inactiva'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($sucursal['created_at'])); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($sucursal['updated_at'])); ?></td>
                                <td>
                                    <div class="acciones">
                                        <a href="?editar=<?php echo $sucursal['id']; ?>" class="btn btn-warning btn-small">Editar</a>
                                        <form method="POST" style="display: inline;" 
                                            onsubmit="return confirm('¿Estás seguro de eliminar esta sucursal?')">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id" value="<?php echo $sucursal['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-small">Eliminar</button>
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
</body>
</html>