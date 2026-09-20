<?php
session_start();
$ruta_base = "../";

// Control de acceso exclusivo para Docentes
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: " . $ruta_base . "auth/login.php");
    exit();
}

require_once "../includes/conexion.php";

$fecha_filtro = $_GET['fecha'] ?? date('Y-m-d');
$id_paralelo_filtro = intval($_GET['id_paralelo'] ?? 0);

// 1. Obtener la lista de paralelos para el filtro
$sql_par = "SELECT id_paralelo, grado, letra FROM paralelos ORDER BY grado ASC, letra ASC";
$res_par = mysqli_query($conexion, $sql_par);

// 2. Consultar únicamente las listas PUBLICADAS para la fecha y paralelo seleccionados
$sql_listas = "SELECT la.id_lista, la.publicada_en,
                      h.hora_inicio, h.hora_fin,
                      m.nombre_materia,
                      p.id_paralelo, p.grado, p.letra,
                      u.nombres AS docente_nombres, u.apellidos AS docente_apellidos
               FROM listas_asistencia la
               INNER JOIN horarios h ON la.id_horario = h.id_horario
               INNER JOIN materias m ON h.id_materia = m.id_materia
               INNER JOIN paralelos p ON h.id_paralelo = p.id_paralelo
               INNER JOIN usuarios u ON h.id_docente = u.id_usuario
               WHERE la.estado = 'Publicada' AND la.fecha = ?";

if ($id_paralelo_filtro > 0) {
    $sql_listas .= " AND p.id_paralelo = " . $id_paralelo_filtro;
}

$sql_listas .= " ORDER BY p.grado ASC, p.letra ASC, h.hora_inicio ASC";

$stmt_l = mysqli_prepare($conexion, $sql_listas);
mysqli_stmt_bind_param($stmt_l, "s", $fecha_filtro);
mysqli_stmt_execute($stmt_l);
$res_listas = mysqli_stmt_get_result($stmt_l);

$listas_publicadas = [];
while ($row = mysqli_fetch_assoc($res_listas)) {
    $listas_publicadas[] = $row;
}
mysqli_stmt_close($stmt_l);

// 3. Si se seleccionó una lista para ver su detalle
$id_lista_sel = intval($_GET['id_lista'] ?? 0);
$detalle_estudiantes = [];
$info_lista_sel = null;

if ($id_lista_sel > 0) {
    // Obtener información general del periodo seleccionado
    $sql_inf = "SELECT la.id_lista, la.fecha, la.publicada_en, h.hora_inicio, h.hora_fin,
                       m.nombre_materia, p.grado, p.letra, p.id_paralelo,
                       u.nombres AS docente_nombres, u.apellidos AS docente_apellidos
                FROM listas_asistencia la
                INNER JOIN horarios h ON la.id_horario = h.id_horario
                INNER JOIN materias m ON h.id_materia = m.id_materia
                INNER JOIN paralelos p ON h.id_paralelo = p.id_paralelo
                INNER JOIN usuarios u ON h.id_docente = u.id_usuario
                WHERE la.id_lista = ? AND la.estado = 'Publicada'";

    $stmt_i = mysqli_prepare($conexion, $sql_inf);
    mysqli_stmt_bind_param($stmt_i, "i", $id_lista_sel);
    mysqli_stmt_execute($stmt_i);
    $res_i = mysqli_stmt_get_result($stmt_i);
    $info_lista_sel = mysqli_fetch_assoc($res_i);
    mysqli_stmt_close($stmt_i);

    if ($info_lista_sel) {
        // Consultar la asistencia de cada estudiante
        $sql_det = "SELECT u.ci, u.apellidos, u.nombres,
                           COALESCE(ad.estado, 'Falta') AS estado_asistencia
                    FROM estudiante_paralelo ep
                    INNER JOIN usuarios u ON ep.id_estudiante = u.id_usuario
                    LEFT JOIN asistencia_detalle ad ON ad.id_lista = ? AND ad.id_estudiante = u.id_usuario
                    WHERE ep.id_paralelo = ? AND u.estado = 1
                    ORDER BY u.apellidos ASC, u.nombres ASC";

        $stmt_d = mysqli_prepare($conexion, $sql_det);
        mysqli_stmt_bind_param($stmt_d, "ii", $id_lista_sel, $info_lista_sel['id_paralelo']);
        mysqli_stmt_execute($stmt_d);
        $res_d = mysqli_stmt_get_result($stmt_d);

        while ($d = mysqli_fetch_assoc($res_d)) {
            $detalle_estudiantes[] = $d;
        }
        mysqli_stmt_close($stmt_d);
    }
}

require_once "../includes/header_panel.php";
?>

<div class="card hero-section" style="margin-bottom: 25px;">
    <h2>🔍 Consulta General de Asistencias Publicadas</h2>
    <p>Visualización de registros oficiales validados por regencia.</p>
</div>

<!-- Filtros de búsqueda -->
<div class="card" style="margin-bottom: 25px;">
    <form action="consulta_asistencia.php" method="GET" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
        <div class="form-group" style="margin: 0;">
            <label for="fecha">Fecha:</label>
            <input type="date" name="fecha" id="fecha" value="<?php echo htmlspecialchars($fecha_filtro); ?>" required>
        </div>

        <div class="form-group" style="margin: 0;">
            <label for="id_paralelo">Paralelo:</label>
            <select name="id_paralelo" id="id_paralelo">
                <option value="0">-- Todos los paralelos --</option>
                <?php while ($p = mysqli_fetch_assoc($res_par)): ?>
                    <option value="<?php echo $p['id_paralelo']; ?>" <?php echo ($id_paralelo_filtro === $p['id_paralelo']) ? 'selected' : ''; ?>>
                        <?php echo $p['grado']; ?>° "<?php echo $p['letra']; ?>"
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <button type="submit" class="btn-submit" style="width: auto; padding: 9px 20px;">Filtrar</button>
    </form>
</div>

<!-- Tabla de resultados de Listas Publicadas -->
<div class="card" style="margin-bottom: 25px;">
    <h3 style="color: var(--primary); margin-bottom: 15px;">Listas Oficiales del Día (<?php echo date('d/m/Y', strtotime($fecha_filtro)); ?>)</h3>

    <?php if (!empty($listas_publicadas)): ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border); text-align: left;">
                        <th style="padding: 10px 8px;">Paralelo</th>
                        <th style="padding: 10px 8px;">Materia / Horario</th>
                        <th style="padding: 10px 8px;">Docente a Cargo</th>
                        <th style="padding: 10px 8px; text-align: center;">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($listas_publicadas as $l): ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 10px 8px; font-weight: 600;">
                                <?php echo $l['grado']; ?>° "<?php echo $l['letra']; ?>"
                            </td>
                            <td style="padding: 10px 8px;">
                                <?php echo htmlspecialchars($l['nombre_materia']); ?><br>
                                <small style="color: var(--text-muted);"><?php echo substr($l['hora_inicio'], 0, 5) . " - " . substr($l['hora_fin'], 0, 5); ?></small>
                            </td>
                            <td style="padding: 10px 8px;">
                                <?php echo htmlspecialchars($l['docente_apellidos'] . " " . $l['docente_nombres']); ?>
                            </td>
                            <td style="padding: 10px 8px; text-align: center;">
                                <a href="consulta_asistencia.php?fecha=<?php echo $fecha_filtro; ?>&id_paralelo=<?php echo $id_paralelo_filtro; ?>&id_lista=<?php echo $l['id_lista']; ?>" 
                                   style="background: var(--primary); color: white; padding: 6px 14px; border-radius: 4px; text-decoration: none; font-size: 0.85rem; font-weight: 600;">
                                    👁️ Ver Nómina
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p style="text-align: center; color: var(--text-muted); padding: 20px 0;">
            No hay listas de asistencia publicadas para la fecha o paralelo seleccionado.
        </p>
    <?php endif; ?>
</div>

<!-- Modal o Vista del Detalle de Asistencia Seleccionada (Solo Lectura) -->
<?php if ($info_lista_sel && !empty($detalle_estudiantes)): ?>
    <div class="card" style="border: 2px solid var(--primary);">
        <div style="border-bottom: 2px solid var(--border); padding-bottom: 12px; margin-bottom: 20px;">
            <h3 style="color: var(--primary); margin: 0;">
                Nómina Oficial: <?php echo $info_lista_sel['grado']; ?>° "<?php echo $info_lista_sel['letra']; ?>" — <?php echo htmlspecialchars($info_lista_sel['nombre_materia']); ?>
            </h3>
            <small style="color: var(--text-muted); font-weight: 600;">
                Docente titular: <?php echo htmlspecialchars($info_lista_sel['docente_apellidos'] . " " . $info_lista_sel['docente_nombres']); ?> | Horario: <?php echo substr($info_lista_sel['hora_inicio'], 0, 5) . " - " . substr($info_lista_sel['hora_fin'], 0, 5); ?>
            </small>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border); text-align: left;">
                        <th style="padding: 10px 8px;">C.I.</th>
                        <th style="padding: 10px 8px;">Estudiante</th>
                        <th style="padding: 10px 8px; text-align: center;">Estado Registrado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detalle_estudiantes as $est): ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 10px 8px;"><?php echo htmlspecialchars($est['ci']); ?></td>
                            <td style="padding: 10px 8px; font-weight: 500;">
                                <?php echo htmlspecialchars($est['apellidos'] . " " . $est['nombres']); ?>
                            </td>
                            <td style="padding: 10px 8px; text-align: center;">
                                <?php
                                $badge_bg = '#e8f5e9'; $badge_c = '#2e7d32'; $label = 'Presente';
if ($a['estado_asistencia'] === 'Falta') {
    $badge_bg = '#ffebee'; $badge_c = '#c62828'; $label = 'Falta';
} elseif ($a['estado_asistencia'] === 'Atraso') {
    $badge_bg = '#fff3e0'; $badge_c = '#f57c00'; $label = 'Atraso';
} elseif ($a['estado_asistencia'] === 'Permiso') {
    $badge_bg = '#e1f5fe'; $badge_c = '#0288d1'; $label = 'Permiso / Licencia';
}
                                ?>
                                <span style="background: <?php echo $badge_bg; ?>; color: <?php echo $badge_c; ?>; padding: 4px 12px; border-radius: 12px; font-size: 0.82rem; font-weight: 700;">
                                    <?php echo $label; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once "../includes/footer_panel.php"; ?>