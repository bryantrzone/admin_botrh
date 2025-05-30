<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'db.php';

$cuestionario_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Obtener título del cuestionario
$cuestionario = $conn->query("SELECT titulo FROM cuestionarios WHERE id = $cuestionario_id")->fetch_assoc();
if (!$cuestionario) {
    die("Cuestionario no encontrado.");
}

// Obtener preguntas con tipo, metadatos y sus respuestas
$sql = "
SELECT 
    p.id AS pregunta_id,
    p.texto AS pregunta_texto,
    p.metadatos,
    tp.tipo AS tipo_pregunta,
    o.id AS respuesta_id,
    o.texto AS respuesta_texto,
    o.es_negativa
FROM pregunta p
LEFT JOIN tipo_pregunta tp ON p.tipo_preguntum_id = tp.id
LEFT JOIN opcion_respuesta o ON o.preguntum_id = p.id
WHERE p.cuestionario_id = $cuestionario_id
ORDER BY p.orden ASC, o.orden ASC";

$resultado = $conn->query($sql);

// Agrupar por pregunta
$preguntas = [];
while ($row = $resultado->fetch_assoc()) {
    $pid = $row['pregunta_id'];
    if (!isset($preguntas[$pid])) {
        $metadatos = $row['metadatos'] ?? '';
        $lista_negativas = [];

        if (!empty($metadatos)) {
            $metadatos = trim((string) $metadatos);

            // Quitar comillas externas si existen
            if ($metadatos[0] === '"' && $metadatos[strlen($metadatos) - 1] === '"') {
                $metadatos = substr($metadatos, 1, -1);
            }

            // Quitar escapes dobles (\\\\ -> \\ y \" -> ")
            $metadatos = stripslashes($metadatos);
            $metadatos = stripslashes($metadatos); // se requieren dos pasadas en este caso

            $json = json_decode($metadatos, true);

            if (json_last_error() === JSON_ERROR_NONE && isset($json['palabrasNegativas']) && is_array($json['palabrasNegativas'])) {
                $lista_negativas = $json['palabrasNegativas'];
            } else {
                // Puedes habilitar este log temporal si sigue fallando:
                // echo "Error JSON: " . json_last_error_msg();
                // echo "<pre>$metadatos</pre>";
            }
        }

        $preguntas[$pid] = [
            'texto' => $row['pregunta_texto'],
            'tipo' => isset($row['tipo_pregunta']) ? $row['tipo_pregunta'] : 'Sin asignar',
            'palabras_bloqueo' => $lista_negativas,
            'respuestas_positivas' => [],
            'respuestas_negativas' => []
        ];
    }

    if ($row['respuesta_id']) {
        if ($row['es_negativa']) {
            $preguntas[$pid]['respuestas_negativas'][] = $row['respuesta_texto'];
        } else {
            $preguntas[$pid]['respuestas_positivas'][] = $row['respuesta_texto'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Visualizar Cuestionario</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">

  <?php include 'navbar.php'; ?>
  <div class="container">
    <h2 class="mb-4">Cuestionario: <?= htmlspecialchars($cuestionario['titulo']) ?></h2>

    <?php if (empty($preguntas)): ?>
      <div class="alert alert-warning">Este cuestionario no tiene preguntas.</div>
    <?php else: ?>
      <?php foreach ($preguntas as $pregunta): 
            // var_dump($pregunta['palabras_bloqueo']);
        ?>
        <div class="card mb-4">
          <div class="card-header fw-bold bg-light">
            <?= htmlspecialchars($pregunta['texto']) ?><br>
            <small class="text-muted">Tipo: <?= htmlspecialchars($pregunta['tipo']) ?></small>
          </div>
          <div class="card-body">            
            <?php if (!empty($pregunta['respuestas_positivas'])): ?>
              <h6 class="text-success">✔ Respuestas Positivas:</h6>
              <ul class="list-group mb-3">
                <?php foreach ($pregunta['respuestas_positivas'] as $respuesta): ?>
                  <li class="list-group-item"><?= htmlspecialchars($respuesta) ?></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>

            <?php if (!empty($pregunta['respuestas_negativas'])): ?>
              <h6 class="text-danger">✖ Respuestas Negativas:</h6>
              <ul class="list-group">
                <?php foreach ($pregunta['respuestas_negativas'] as $respuesta): ?>
                  <li class="list-group-item"><?= htmlspecialchars($respuesta) ?></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>

            <?php if (!empty($pregunta['palabras_bloqueo']) && $pregunta['tipo'] === 'texto'): ?>
                <div class="alert alert-danger mt-3">
                    🚫 <strong>Palabras que bloquean el flujo:</strong>
                    <ul class="mb-0 mt-2">
                    <?php foreach ($pregunta['palabras_bloqueo'] as $palabra): ?>
                        <li><?= htmlspecialchars($palabra) ?></li>
                    <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <a href="index.php" class="btn btn-secondary">⬅ Volver</a>
  </div>
</body>
</html>
