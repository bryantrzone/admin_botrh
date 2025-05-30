<?php
require_once 'db.php';
require_once 'vendor/autoload.php'; // Composer autoload

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

$mensaje = '';
$tipo_mensaje = '';
$resultados = [];

function validarColumnas($hoja) {
    $columnasRequeridas = ['nombre', 'zona'];
    $columnasOpcionales = ['direccion', 'activa'];
    $todasLasColumnas = array_merge($columnasRequeridas, $columnasOpcionales);
    
    $primeraFila = $hoja->rangeToArray('A1:Z1', null, true, false)[0];
    $columnasEncontradas = array_filter($primeraFila);
    
    // Normalizar nombres de columnas (minúsculas, sin espacios)
    $columnasNormalizadas = array_map(function($col) {
        return strtolower(trim($col));
    }, $columnasEncontradas);
    
    $faltantes = [];
    foreach ($columnasRequeridas as $requerida) {
        if (!in_array(strtolower($requerida), $columnasNormalizadas)) {
            $faltantes[] = $requerida;
        }
    }
    
    return [
        'validas' => empty($faltantes),
        'faltantes' => $faltantes,
        'encontradas' => $columnasNormalizadas
    ];
}

function obtenerIndiceColumna($columnas, $nombre) {
    $nombre = strtolower($nombre);
    foreach ($columnas as $indice => $columna) {
        if (strtolower(trim($columna)) === $nombre) {
            return $indice;
        }
    }
    return -1;
}

function procesarValorZona($valor) {
    if (is_null($valor) || $valor === '') {
        return 'Centro'; // Por defecto Centro
    }
    
    $valor = strtolower(trim($valor));
    
    // Valores que se consideran como "Norte"
    $valoresNorte = ['norte', 'north', 'n'];
    // Valores que se consideran como "Centro"
    $valoresCentro = ['centro', 'center', 'c'];
    
    if (in_array($valor, $valoresNorte)) {
        return 'Norte';
    } elseif (in_array($valor, $valoresCentro)) {
        return 'Centro';
    }
    
    return 'Centro'; // Por defecto Centro si no se reconoce el valor
}

function procesarValorActiva($valor) {
    if (is_null($valor) || $valor === '') {
        return 1; // Por defecto activa
    }
    
    $valor = strtolower(trim($valor));
    
    // Valores que se consideran como "activa"
    $valoresActiva = ['1', 'si', 'sí', 'yes', 'true', 'activa', 'activo'];
    // Valores que se consideran como "inactiva"
    $valoresInactiva = ['0', 'no', 'false', 'inactiva', 'inactivo'];
    
    if (in_array($valor, $valoresActiva)) {
        return 1;
    } elseif (in_array($valor, $valoresInactiva)) {
        return 0;
    }
    
    return 1; // Por defecto activa si no se reconoce el valor
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['archivo_excel'])) {
    $archivoTemporal = $_FILES['archivo_excel']['tmp_name'];
    $nombreArchivo = $_FILES['archivo_excel']['name'];
    $extension = pathinfo($nombreArchivo, PATHINFO_EXTENSION);
    
    // Validar extensión
    if (!in_array(strtolower($extension), ['xlsx', 'xls', 'csv'])) {
        $mensaje = 'Solo se permiten archivos Excel (.xlsx, .xls) o CSV (.csv)';
        $tipo_mensaje = 'error';
    } else {
        try {
            // Cargar el archivo
            $spreadsheet = IOFactory::load($archivoTemporal);
            $hoja = $spreadsheet->getActiveSheet();
            
            // Validar columnas
            $validacion = validarColumnas($hoja);
            
            if (!$validacion['validas']) {
                $mensaje = 'El archivo no tiene las columnas requeridas. Faltan: ' . implode(', ', $validacion['faltantes']);
                $tipo_mensaje = 'error';
            } else {
                // Obtener datos
                $datos = $hoja->toArray();
                $encabezados = array_shift($datos); // Remover la primera fila (encabezados)
                
                // Obtener índices de columnas
                $indiceNombre = obtenerIndiceColumna($encabezados, 'nombre');
                $indiceDireccion = obtenerIndiceColumna($encabezados, 'direccion');
                $indiceZona = obtenerIndiceColumna($encabezados, 'zona');
                $indiceActiva = obtenerIndiceColumna($encabezados, 'activa');
                
                $procesados = 0;
                $errores = 0;
                $resultados = [];
                
                foreach ($datos as $fila => $columnas) {
                    $numeroFila = $fila + 2; // +2 porque array_shift removió encabezados y las filas empiezan en 1
                    
                    // Extraer valores
                    $nombre = isset($columnas[$indiceNombre]) ? trim($columnas[$indiceNombre]) : '';
                    $direccion = $indiceDireccion >= 0 && isset($columnas[$indiceDireccion]) ? trim($columnas[$indiceDireccion]) : '';
                    $zona = $indiceZona >= 0 && isset($columnas[$indiceZona]) ? procesarValorZona($columnas[$indiceZona]) : 'Centro';
                    $activa = $indiceActiva >= 0 && isset($columnas[$indiceActiva]) ? procesarValorActiva($columnas[$indiceActiva]) : 1;
                    
                    // Validar datos obligatorios
                    if (empty($nombre)) {
                        $resultados[] = [
                            'fila' => $numeroFila,
                            'status' => 'error',
                            'mensaje' => 'Nombre vacío'
                        ];
                        $errores++;
                        continue;
                    }
                    
                    // Validar zona
                    if (!in_array($zona, ['Norte', 'Centro'])) {
                        $resultados[] = [
                            'fila' => $numeroFila,
                            'status' => 'error',
                            'mensaje' => "Zona inválida: '{$zona}'. Solo se permite 'Norte' o 'Centro'"
                        ];
                        $errores++;
                        continue;
                    }
                    
                    // Verificar si ya existe
                    $sqlVerificar = "SELECT id FROM sucursals WHERE nombre = ?";
                    $stmtVerificar = $conn->prepare($sqlVerificar);
                    $stmtVerificar->bind_param("s", $nombre);
                    $stmtVerificar->execute();
                    $existe = $stmtVerificar->get_result()->fetch_assoc();
                    
                    if ($existe) {
                        $resultados[] = [
                            'fila' => $numeroFila,
                            'status' => 'advertencia',
                            'mensaje' => "Sucursal '{$nombre}' ya existe - omitida"
                        ];
                        continue;
                    }
                    
                    // Insertar nueva sucursal
                    $sqlInsertar = "INSERT INTO sucursals (nombre, direccion, zona, activa, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())";
                    $stmtInsertar = $conn->prepare($sqlInsertar);
                    $stmtInsertar->bind_param("sssi", $nombre, $direccion, $zona, $activa);
                    
                    if ($stmtInsertar->execute()) {
                        $resultados[] = [
                            'fila' => $numeroFila,
                            'status' => 'exito',
                            'mensaje' => "Sucursal '{$nombre}' (Zona: {$zona}) importada correctamente"
                        ];
                        $procesados++;
                    } else {
                        $resultados[] = [
                            'fila' => $numeroFila,
                            'status' => 'error',
                            'mensaje' => "Error al insertar '{$nombre}': " . $conn->error
                        ];
                        $errores++;
                    }
                }
                
                if ($procesados > 0) {
                    $mensaje = "Importación completada: {$procesados} sucursales procesadas, {$errores} errores";
                    $tipo_mensaje = $errores > 0 ? 'warning' : 'success';
                } else {
                    $mensaje = "No se importaron sucursales";
                    $tipo_mensaje = 'error';
                }
            }
            
        } catch (Exception $e) {
            $mensaje = 'Error al procesar el archivo: ' . $e->getMessage();
            $tipo_mensaje = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Sucursales desde Excel</title>
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
            max-width: 1000px;
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
        
        .mensaje.warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .instrucciones {
            background: #e7f3ff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid #007bff;
        }
        
        .instrucciones h3 {
            color: #0056b3;
            margin-bottom: 15px;
        }
        
        .instrucciones ul {
            margin-left: 20px;
        }
        
        .instrucciones li {
            margin-bottom: 8px;
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
        
        input[type="file"] {
            width: 100%;
            padding: 12px;
            border: 2px dashed #007bff;
            border-radius: 5px;
            background: white;
            cursor: pointer;
        }
        
        input[type="file"]:hover {
            border-color: #0056b3;
            background-color: #f8f9fa;
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
        
        .resultados {
            margin-top: 30px;
        }
        
        .resultado-item {
            padding: 10px;
            margin-bottom: 8px;
            border-radius: 5px;
            border-left: 4px solid;
        }
        
        .resultado-item.exito {
            background-color: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        
        .resultado-item.error {
            background-color: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        
        .resultado-item.advertencia {
            background-color: #fff3cd;
            border-color: #ffc107;
            color: #856404;
        }
        
        .ejemplo-tabla {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        
        .ejemplo-tabla th, .ejemplo-tabla td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        .ejemplo-tabla th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        
        .descargar-plantilla {
            text-align: center;
            margin: 20px 0;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Importar Sucursales desde Excel</h1>
        
        <div class="navegacion">
            <a href="sucursales.php" class="btn btn-secondary">← Volver a Sucursales</a>
        </div>
        
        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo_mensaje; ?>">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>
        
        <div class="instrucciones">
            <h3>📋 Instrucciones para importar</h3>
            <ul>
                <li><strong>Formato requerido:</strong> Excel (.xlsx, .xls) o CSV (.csv)</li>
                <li><strong>Columnas obligatorias:</strong> <code>nombre</code>, <code>zona</code></li>
                <li><strong>Columnas opcionales:</strong> <code>direccion</code>, <code>activa</code></li>
                <li><strong>Primera fila:</strong> Debe contener los nombres de las columnas</li>
                <li><strong>Campo "zona":</strong> Solo acepta "Norte" o "Centro" (también acepta "North/N" o "Center/C")</li>
                <li><strong>Campo "activa":</strong> Acepta: 1/0, Si/No, True/False, Activa/Inactiva</li>
                <li><strong>Duplicados:</strong> Se omiten sucursales con nombres que ya existen</li>
            </ul>
            
            <h4>Ejemplo de estructura:</h4>
            <table class="ejemplo-tabla">
                <thead>
                    <tr>
                        <th>nombre</th>
                        <th>direccion</th>
                        <th>zona</th>
                        <th>activa</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Sucursal Centro</td>
                        <td>Av. Principal 123</td>
                        <td>Centro</td>
                        <td>Si</td>
                    </tr>
                    <tr>
                        <td>Sucursal Norte</td>
                        <td>Calle Norte 456</td>
                        <td>Norte</td>
                        <td>1</td>
                    </tr>
                    <tr>
                        <td>Sucursal Plaza</td>
                        <td></td>
                        <td>Norte</td>
                        <td>No</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="descargar-plantilla">
            <a href="plantilla_sucursales.php" class="btn btn-success">📥 Descargar Plantilla Excel</a>
        </div>
        
        <div class="formulario">
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="archivo_excel">Seleccionar archivo Excel/CSV</label>
                    <input type="file" id="archivo_excel" name="archivo_excel" 
                           accept=".xlsx,.xls,.csv" required>
                </div>
                
                <button type="submit" class="btn">📤 Importar Sucursales</button>
            </form>
        </div>
        
        <?php if (!empty($resultados)): ?>
            <div class="resultados">
                <h3>Resultados de la importación:</h3>
                <?php foreach ($resultados as $resultado): ?>
                    <div class="resultado-item <?php echo $resultado['status']; ?>">
                        <strong>Fila <?php echo $resultado['fila']; ?>:</strong> 
                        <?php echo $resultado['mensaje']; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>