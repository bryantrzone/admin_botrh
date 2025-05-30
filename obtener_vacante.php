<?php
header('Content-Type: application/json');
include 'db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'ID inválido']);
    exit;
}

$id = $_GET['id'];

try {
    $sql = "SELECT * FROM vacantes WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($vacante = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'vacante' => $vacante
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Vacante no encontrada'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener datos: ' . $e->getMessage()
    ]);
}

$conn->close();
?>