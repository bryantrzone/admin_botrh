<?php
include 'db.php';

$titulo = $_POST['titulo'];
$descripcion = $_POST['descripcion'];
$requisitos = $_POST['requisitos'];
$salario_min = $_POST['salario_min'] ?: 0;
$salario_max = $_POST['salario_max'] ?: 0;
$activa = isset($_POST['activa']) ? 1 : 0;
$fecha_publicacion = $_POST['fecha_publicacion'];
$sucursal_id = $_POST['sucursal_id'];
$area_id = $_POST['area_id'];

$fecha = date('Y-m-d H:i:s');

$sql = "INSERT INTO vacantes (titulo, descripcion, requisitos, salario_min, salario_max, activa, fecha_publicacion, fecha_cierre, created_at, updated_at, sucursal_id, area_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sssiiisssii", $titulo, $descripcion, $requisitos, $salario_min, $salario_max, $activa, $fecha_publicacion, $fecha, $fecha, $sucursal_id, $area_id);
$stmt->execute();

header("Location: index.php");
exit;
