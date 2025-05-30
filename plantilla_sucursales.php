<?php
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Crear nueva hoja de cálculo
$spreadsheet = new Spreadsheet();
$hoja = $spreadsheet->getActiveSheet();

// Configurar propiedades del documento
$spreadsheet->getProperties()
    ->setCreator("Sistema de Sucursales")
    ->setTitle("Plantilla para Importar Sucursales")
    ->setSubject("Plantilla de importación")
    ->setDescription("Plantilla para importar sucursales al sistema");

// Configurar nombre de la hoja
$hoja->setTitle('Sucursales');

// Definir encabezados
$encabezados = [
    'A1' => 'nombre',
    'B1' => 'direccion', 
    'C1' => 'activa'
];

// Insertar encabezados
foreach ($encabezados as $celda => $valor) {
    $hoja->setCellValue($celda, $valor);
}

// Estilo para encabezados
$estiloEncabezado = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 12
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '4472C4']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ]
];

// Aplicar estilo a encabezados
$hoja->getStyle('A1:C1')->applyFromArray($estiloEncabezado);

// Datos de ejemplo
$ejemplos = [
    ['Sucursal Centro', 'Av. Juárez 123, Centro', 'Si'],
    ['Sucursal Norte', 'Blvd. Norte 456, Col. Moderna', '1'],
    ['Sucursal Sur', 'Calle Sur 789, Col. Industrial', 'Activa'],
    ['Sucursal Este', '', 'No'],
    ['Sucursal Oeste', 'Periférico Oeste 321, Zona Comercial', 'True']
];

// Insertar datos de ejemplo
$fila = 2;
foreach ($ejemplos as $ejemplo) {
    $hoja->setCellValue('A' . $fila, $ejemplo[0]);
    $hoja->setCellValue('B' . $fila, $ejemplo[1]);
    $hoja->setCellValue('C' . $fila, $ejemplo[2]);
    $fila++;
}

// Estilo para datos de ejemplo
$estiloDatos = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'CCCCCC']
        ]
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'F8F9FA']
    ]
];

// Aplicar estilo a datos de ejemplo
$hoja->getStyle('A2:C6')->applyFromArray($estiloDatos);

// Ajustar ancho de columnas
$hoja->getColumnDimension('A')->setWidth(25); // nombre
$hoja->getColumnDimension('B')->setWidth(40); // direccion
$hoja->getColumnDimension('C')->setWidth(15); // activa

// Agregar comentarios a las celdas de encabezado
$hoja->getComment('A1')->getText()->createTextRun('Nombre de la sucursal (OBLIGATORIO)');
$hoja->getComment('B1')->getText()->createTextRun('Dirección completa de la sucursal (OPCIONAL)');
$hoja->getComment('C1')->getText()->createTextRun('Estado de la sucursal. Acepta: Si/No, 1/0, True/False, Activa/Inactiva (OPCIONAL - Por defecto: Activa)');

// Crear una segunda hoja con instrucciones
$hojaInstrucciones = $spreadsheet->createSheet();
$hojaInstrucciones->setTitle('Instrucciones');

$instrucciones = [
    ['INSTRUCCIONES PARA IMPORTAR SUCURSALES', ''],
    ['', ''],
    ['1. COLUMNAS REQUERIDAS:', ''],
    ['   • nombre', 'Obligatorio - Nombre único de la sucursal'],
    ['   • direccion', 'Opcional - Dirección completa'],
    ['   • activa', 'Opcional - Estado (por defecto: activa)'],
    ['', ''],
    ['2. FORMATO DEL CAMPO "ACTIVA":', ''],
    ['   Valores aceptados para ACTIVA:', 'Si, 1, True, Activa'],
    ['   Valores aceptados para INACTIVA:', 'No, 0, False, Inactiva'],
    ['', ''],
    ['3. REGLAS IMPORTANTES:', ''],
    ['   • La primera fila debe contener los encabezados', ''],
    ['   • No se importarán sucursales con nombres duplicados', ''],
    ['   • Si el campo "activa" está vacío, se asignará como "activa"', ''],
    ['   • El campo "direccion" puede estar vacío', ''],
    ['', ''],
    ['4. FORMATOS SOPORTADOS:', ''],
    ['   • Excel (.xlsx, .xls)', ''],
    ['   • CSV (.csv)', ''],
    ['', ''],
    ['5. PROCESO DE IMPORTACIÓN:', ''],
    ['   1. Complete la hoja "Sucursales" con sus datos', ''],
    ['   2. Guarde el archivo', ''],
    ['   3. Use la opción "Importar" en el sistema', ''],
    ['   4. Revise los resultados de la importación', '']
];

// Insertar instrucciones
$filaInst = 1;
foreach ($instrucciones as $instruccion) {
    $hojaInstrucciones->setCellValue('A' . $filaInst, $instruccion[0]);
    $hojaInstrucciones->setCellValue('B' . $filaInst, $instruccion[1]);
    $filaInst++;
}

// Estilo para título de instrucciones
$hojaInstrucciones->getStyle('A1')->applyFromArray([
    'font' => [
        'bold' => true,
        'size' => 16,
        'color' => ['rgb' => '2E75B6']
    ]
]);

// Estilo para subtítulos
$hojaInstrucciones->getStyle('A3')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
$hojaInstrucciones->getStyle('A8')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
$hojaInstrucciones->getStyle('A12')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
$hojaInstrucciones->getStyle('A18')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
$hojaInstrucciones->getStyle('A21')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);

// Ajustar ancho de columnas en instrucciones
$hojaInstrucciones->getColumnDimension('A')->setWidth(50);
$hojaInstrucciones->getColumnDimension('B')->setWidth(30);

// Volver a la primera hoja
$spreadsheet->setActiveSheetIndex(0);

// Configurar headers para descarga
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="plantilla_sucursales.xlsx"');
header('Cache-Control: max-age=0');

// Crear writer y enviar archivo
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

// Limpiar memoria
$spreadsheet->disconnectWorksheets();
unset($spreadsheet);
exit;