<?php
// Iniciar sesión global si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Sanitiza entradas de texto para evitar vulnerabilidades XSS
 */
function limpiarCadena($cadena) {
    return htmlspecialchars(trim($cadena), ENT_QUOTES, 'UTF-8');
}

/**
 * Verifica si el usuario ha iniciado sesión
 */
function estaAutenticado() {
    return isset($_SESSION['id_usuario']);
}

/**
 * Restringe el acceso según el rol del usuario logueado
 * $rolesPermitidos puede ser un arreglo, ej: ['administrador', 'docente']
 */
function verificarAcceso($rolesPermitidos) {
    if (!estaAutenticado()) {
        header("Location: ../auth/login.php");
        exit();
    }
    
    if (!in_array($_SESSION['rol'], $rolesPermitidos)) {
        header("Location: ../index.php?error=acceso_denegado");
        exit();
    }
}

/**
 * Redirige al dashboard correspondiente según el rol del usuario
 */
function redirigirSegunRol($rol) {
    switch ($rol) {
        case 'administrador':
            header("Location: ../admin/dashboard.php");
            break;
        case 'docente':
            header("Location: ../tutor/dashboard.php");
            break;
        case 'estudiante':
            header("Location: ../estudiante/dashboard.php");
            break;
        case 'padre':
            header("Location: ../padres/dashboard.php");
            break;
        default:
            header("Location: ../index.php");
            break;
    }
    exit();
}
/**
 * Calcula el centralizador de notas (Ser/Saber/Hacer/Decidir/Asistencia/Autoevaluación)
 * de todos los estudiantes de un paralelo, para una configuración de dimensiones dada.
 * Se usa tanto en la vista del docente como en la exportación a Excel/CSV.
 */
function calcularCentralizadorTrimestral($conexion, $id_paralelo, $config_actual) {
    $centralizador_datos = [];
    $estudiantes_curso = [];

    if (!$id_paralelo || !$config_actual) {
        return $centralizador_datos;
    }

    // Lista de estudiantes del paralelo
    $sql_e_par = "SELECT u.id_usuario, u.nombres, u.apellidos, u.ci
                  FROM estudiante_paralelo ep
                  INNER JOIN usuarios u ON ep.id_estudiante = u.id_usuario
                  WHERE ep.id_paralelo = ?
                  ORDER BY u.apellidos ASC, u.nombres ASC";
    $stmt_ep = mysqli_prepare($conexion, $sql_e_par);
    mysqli_stmt_bind_param($stmt_ep, "i", $id_paralelo);
    mysqli_stmt_execute($stmt_ep);
    $res_ep = mysqli_stmt_get_result($stmt_ep);
    while ($row = mysqli_fetch_assoc($res_ep)) {
        $estudiantes_curso[$row['id_usuario']] = $row;
    }
    mysqli_stmt_close($stmt_ep);

    // Autoevaluaciones registradas
    $autoevaluaciones_registradas = [];
    $sql_get_auto = "SELECT id_estudiante, nota FROM autoevaluaciones WHERE id_config = ?";
    $stmt_ga = mysqli_prepare($conexion, $sql_get_auto);
    mysqli_stmt_bind_param($stmt_ga, "i", $config_actual['id_config']);
    mysqli_stmt_execute($stmt_ga);
    $res_ga = mysqli_stmt_get_result($stmt_ga);
    while ($row = mysqli_fetch_assoc($res_ga)) {
        $autoevaluaciones_registradas[$row['id_estudiante']] = $row['nota'];
    }
    mysqli_stmt_close($stmt_ga);

    // Asistencia ponderada
    $asistencias_estudiantes = [];
    $max_asistencia_val = floatval($config_actual['max_asistencia'] ?? 5.00);
    $sql_get_asis = "SELECT id_estudiante,
                            COUNT(CASE WHEN estado = 'presente' THEN 1 END) as presentes,
                            COUNT(*) as total
                     FROM asistencia_detalle
                     WHERE id_estudiante IN (SELECT id_estudiante FROM estudiante_paralelo WHERE id_paralelo = ?)
                     GROUP BY id_estudiante";
    $stmt_asis = mysqli_prepare($conexion, $sql_get_asis);
    mysqli_stmt_bind_param($stmt_asis, "i", $id_paralelo);
    mysqli_stmt_execute($stmt_asis);
    $res_asis = mysqli_stmt_get_result($stmt_asis);
    while ($row = mysqli_fetch_assoc($res_asis)) {
        $pct = ($row['total'] > 0) ? ($row['presentes'] / $row['total']) : 1;
        $asistencias_estudiantes[$row['id_estudiante']] = $pct * $max_asistencia_val;
    }
    mysqli_stmt_close($stmt_asis);

    // Promedios por dimensión
    $promedios_dimensiones = [];
    $sql_prom = "SELECT n.id_estudiante, a.dimension,
                        SUM(n.nota) as obtenido,
                        SUM(a.puntaje_maximo) as maximo
                 FROM notas_actividades n
                 INNER JOIN actividades_dimension a ON n.id_actividad = a.id_actividad
                 WHERE a.id_config = ?
                 GROUP BY n.id_estudiante, a.dimension";
    $stmt_p = mysqli_prepare($conexion, $sql_prom);
    mysqli_stmt_bind_param($stmt_p, "i", $config_actual['id_config']);
    mysqli_stmt_execute($stmt_p);
    $res_p = mysqli_stmt_get_result($stmt_p);
    while ($row = mysqli_fetch_assoc($res_p)) {
        $e_id = $row['id_estudiante'];
        $dim = $row['dimension'];
        $obtenido = floatval($row['obtenido']);
        $maximo = floatval($row['maximo']);
        $max_dim = floatval($config_actual['max_' . strtolower($dim)] ?? 10.00);
        $promedios_dimensiones[$e_id][$dim] = ($maximo > 0) ? ($obtenido / $maximo) * $max_dim : 0;
    }
    mysqli_stmt_close($stmt_p);

    // Consolidado final por estudiante
    foreach ($estudiantes_curso as $e_id => $est) {
        $ser = $promedios_dimensiones[$e_id]['Ser'] ?? 0;
        $saber = $promedios_dimensiones[$e_id]['Saber'] ?? 0;
        $hacer = $promedios_dimensiones[$e_id]['Hacer'] ?? 0;
        $decidir = $promedios_dimensiones[$e_id]['Decidir'] ?? 0;
        $asistencia = $asistencias_estudiantes[$e_id] ?? $max_asistencia_val;
        $auto = $autoevaluaciones_registradas[$e_id] ?? 0;
        $total_final = $ser + $saber + $hacer + $decidir + $asistencia + $auto;

        $centralizador_datos[] = [
            'id_usuario' => $e_id,
            'nombre_completo' => $est['apellidos'] . " " . $est['nombres'],
            'ci' => $est['ci'],
            'ser' => $ser,
            'saber' => $saber,
            'hacer' => $hacer,
            'decidir' => $decidir,
            'asistencia' => $asistencia,
            'autoevaluacion' => $auto,
            'total' => $total_final,
            'estado' => ($total_final >= 51) ? 'Aprobado' : 'Riesgo / Reprobado'
        ];
    }

    return $centralizador_datos;
}
?>