<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'db.php';

$vacante_id = intval($_POST['vacante_id']);
$cuestionario_id = intval($_POST['cuestionario_id']);

var_dump($vacante_id);
var_dump($vacante_id);

// Asignar el cuestionario a la vacante
$conn->query("UPDATE cuestionarios SET vacante_id = $vacante_id WHERE id = $cuestionario_id");

header("Location: index.php");
exit;
