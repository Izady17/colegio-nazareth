<?php
session_start();
$ruta_base = "../";

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    exit("Acceso denegado.");
}

require_once "../includes/conexion.php";
require_once "../includes/funciones.php";

$id_docente = $_SESSION['id_usuario'];
$id_paralelo = intval($_GET['id_paralelo'] ?? 0);
$id_materia = intval($_GET['id_materia'] ?? 0);
$trimestre = intval($_GET['trimestre'] ?? 1);

if ($id_paralelo === 0 || $id_materia === 0) {
    exit("Parámetros insuficientes.");
}

// 1. Obtener datos de la asignatura y curso
$sql_info = "SELECT p.grado, p.letra, m.nombre_materia 
            FROM paralelos p, materias m 
            WHERE p.id_paralelo = ? AND m.id_materia = ? LIMIT 1";
$stmt_i = mysqli_prepare($conexion, $sql_info);
mysqli_stmt_bind_param($stmt_i, "ii", $id_paralelo, $id_materia);
mysqli_stmt_execute($stmt_i);
$info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_i));
mysqli_stmt_close($stmt_i);

// 2. Obtener configuración de dimensiones (necesaria para calcular las notas)
$sql_cd = "SELECT * FROM configuracion_dimensiones 
           WHERE id_paralelo = ? AND id_materia = ? AND id_docente = ? AND trimestre = ? LIMIT 1";
$stmt_cd = mysqli_prepare($conexion, $sql_cd);
mysqli_stmt_bind_param($stmt_cd, "iiii", $id_paralelo, $id_materia, $id_docente, $trimestre);
mysqli_stmt_execute($stmt_cd);
$res_cd = mysqli_stmt_get_result($stmt_cd);
$config = mysqli_fetch_assoc($res_cd);
mysqli_stmt_close($stmt_cd);

if (!$config) {
    exit("No hay datos registrados para exportar.");
}

// 3. Calcular las notas reales (misma función que usa la pantalla del Centralizador)
$centralizador_datos = calcularCentralizadorTrimestral($conexion, $id_paralelo, $config);

$filename = "Boletin_T" . $trimestre . "_" . str_replace(' ', '_', $info['nombre_materia']) . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

// Apertura de flujo de salida con BOM UTF-8
$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Encabezados
fputcsv($output, ['U.E. Jesús de Nazareth - Reporte de Calificaciones']);
fputcsv($output, ['Materia:', $info['nombre_materia'], 'Curso:', $info['grado'] . ' "' . $info['letra'] . '"', 'Trimestre:', $trimestre]);
fputcsv($output, []);
fputcsv($output, ['CI', 'Apellidos y Nombres', 'SER', 'SABER', 'HACER', 'DECIDIR', 'ASISTENCIA', 'AUTOEVALUACIÓN', 'TOTAL', 'ESTADO']);

foreach ($centralizador_datos as $row) {
    fputcsv($output, [
        $row['ci'],
        $row['nombre_completo'],
        number_format($row['ser'], 2),
        number_format($row['saber'], 2),
        number_format($row['hacer'], 2),
        number_format($row['decidir'], 2),
        number_format($row['asistencia'], 2),
        number_format($row['autoevaluacion'], 2),
        number_format($row['total'], 2),
        $row['estado']
    ]);
}

fclose($output);
exit();