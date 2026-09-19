<?php
session_start();
$ruta_base = "../";

// Verificar acceso exclusivo para Administrador
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrador') {
    header("Location: " . $ruta_base . "auth/login.php");
    exit();
}

require_once "../includes/conexion.php";

$fecha_filtro = $_GET['fecha'] ?? date('Y-m-d');
$id_paralelo_filtro = intval($_GET['id_paralelo'] ?? 0);

$mensaje_exito = "";
$mensaje_error = "";

// --------------------------------------------------------------------------
// 1. PROCESAR PUBLICACIÓN O MODIFICACIÓN POR EL ADMIN/REGENTE (POST)
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $id_lista = intval($_POST['id_lista'] ?? 0);
    $accion = $_POST['accion'];
    $estados_estudiantes = $_POST['asistencia'] ?? []; // Array [id_estudiante => 'Presente'|'Falta'|'Permiso']

    if ($id_lista > 0) {
        mysqli_begin_transaction($conexion);
        try {
            // Actualizar el detalle de estados (incluyendo la opción de 'Permiso')
            if (!empty($estados_estudiantes)) {
                $sql_up_det = "INSERT INTO asistencia_detalle (id_lista, id_estudiante, estado) 
                               VALUES (?, ?, ?) 
                               ON DUPLICATE KEY UPDATE estado = VALUES(estado)";
                $stmt_det = mysqli_prepare($conexion, $sql_up_det);

                foreach ($estados_estudiantes as $id_est => $est_val) {
                    $est_clean = in_array($est_val, ['Presente', 'Falta', 'Permiso']) ? $est_val : 'Falta';
                    mysqli_stmt_bind_param($stmt_det, "iis", $id_lista, $id_est, $est_clean);
                    mysqli_stmt_execute($stmt_det);
                }
                mysqli_stmt_close($stmt_det);
            }

            // Cambiar estado de la lista según el botón presionado
            if ($accion === 'publicar') {
                $sql_pub = "UPDATE listas_asistencia SET estado = 'Publicada', publicada_en = CURRENT_TIMESTAMP WHERE id_lista = ?";
                $stmt_p = mysqli_prepare($conexion, $sql_pub);
                mysqli_stmt_bind_param($stmt_p, "i", $id_lista);
                mysqli_stmt_execute($stmt_p);
                mysqli_stmt_close($stmt_p);
                $mensaje_exito = "Lista publicada oficialmente. Ahora es visible para los estudiantes.";
            } elseif ($accion === 'guardar_borrador') {
                $mensaje_exito = "Cambios guardados correctamente en la lista.";
            }

            mysqli_commit($conexion);
        } catch (Exception $e) {
            mysqli_rollback($conexion);
            $mensaje_error = "Error al procesar la lista: " . $e->getMessage();
        }
    }
}

// --------------------------------------------------------------------------
// 2. CONSULTAS PARA LA VISTA
// --------------------------------------------------------------------------
// Obtener paralelos para el filtro
$sql_par = "SELECT id_paralelo, grado, letra FROM paralelos ORDER BY grado ASC, letra ASC";
$res_par = mysqli_query($conexion, $sql_par);

// Consultar horarios del día seleccionado que requieren asistencia
$sql_listas = "SELECT h.id_horario, h.hora_inicio, h.hora_fin, h.dia,
                      m.nombre_materia,
                      p.id_paralelo, p.grado, p.letra,
                      u_doc.nombres AS docente_nombres, u_doc.apellidos AS docente_apellidos,
                      la.id_lista, COALESCE(la.estado, 'Sin Registro') AS estado_lista, la.subida_en, la.publicada_en
               FROM horarios h
               INNER JOIN materias m ON h.id_materia = m.id_materia
               INNER JOIN paralelos p ON h.id_paralelo = p.id_paralelo
               INNER JOIN usuarios u_doc ON h.id_docente = u_doc.id_usuario
               LEFT JOIN listas_asistencia la ON la.id_horario = h.id_horario AND la.fecha = ?
               WHERE h.requiere_asistencia = 1 ";

if ($id_paralelo_filtro > 0) {
    $sql_listas .= " AND h.id_paralelo = " . $id_paralelo_filtro;
}
$sql_listas .= " ORDER BY p.grado ASC, p.letra ASC, h.hora_inicio ASC";

$stmt_l = mysqli_prepare($conexion, $sql_listas);
mysqli_stmt_bind_param($stmt_l, "s", $fecha_filtro);
mysqli_stmt_execute($stmt_l);
$res_listas = mysqli_stmt_get_result($stmt_l);

$listas_resumen = [];
while ($row = mysqli_fetch_assoc($res_listas)) {
    $listas_resumen[] = $row;
}
mysqli_stmt_close($stmt_l);

// Si se seleccionó una lista para revisar/editar
$id_lista_sel = intval($_GET['id_lista'] ?? 0);
$detalle_estudiantes = [];
$info_lista_sel = null;

if ($id_lista_sel > 0) {
    // Info general de la lista seleccionada
    $sql_inf = "SELECT la.id_lista, la.fecha, la.estado, h.hora_inicio, h.hora_fin,
                       m.nombre_materia, p.grado, p.letra,
                       u.nombres AS docente_nombres, u.apellidos AS docente_apellidos, p.id_paralelo
                FROM listas_asistencia la
                INNER JOIN horarios h ON la.id_horario = h.id_horario
                INNER JOIN materias m ON h.id_materia = m.id_materia
                INNER JOIN paralelos p ON h.id_paralelo = p.id_paralelo
                INNER JOIN usuarios u ON h.id_docente = u.id_usuario
                WHERE la.id_lista = ?";
    $stmt_i = mysqli_prepare($conexion, $sql_inf);
    mysqli_stmt_bind_param($stmt_i, "i", $id_lista_sel);
    mysqli_stmt_execute($stmt_i);
    $res_i = mysqli_stmt_get_result($stmt_i);
    $info_lista_sel = mysqli_fetch_assoc($res_i);
    mysqli_stmt_close($stmt_i);

    if ($info_lista_sel) {
        // Obtener la nomina de estudiantes con su estado actual
        $sql_det = "SELECT u.id_usuario, u.ci, u.apellidos, u.nombres,
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
    <h2>Control y Publicación de Asistencia (Regencia)</h2>
    <p>Verificación de listas enviadas por los docentes y gestión de permisos/licencias.</p>
</div>

<?php if ($mensaje_exito): ?>
    <div style="background-color: #d1e7dd; color: #0f5132; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
        <?php echo htmlspecialchars($mensaje_exito); ?>
    </div>
<?php endif; ?>

<?php if ($mensaje_error): ?>
    <div style="background-color: #f8d7da; color: #842029; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
        <?php echo htmlspecialchars($mensaje_error); ?>
    </div>
<?php endif; ?>

<!-- Barra de Filtros -->
<div class="card" style="margin-bottom: 25px;">
    <form action="asistencia.php" method="GET" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
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

<!-- Resumen de Listas del Día -->
<div class="card" style="margin-bottom: 25px;">
    <h3 style="color: var(--primary); margin-bottom: 15px;">Listas del Día (<?php echo date('d/m/Y', strtotime($fecha_filtro)); ?>)</h3>
    
    <?php if (!empty($listas_resumen)): ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border); text-align: left;">
                        <th style="padding: 10px 8px;">Paralelo</th>
                        <th style="padding: 10px 8px;">Materia / Horario</th>
                        <th style="padding: 10px 8px;">Docente</th>
                        <th style="padding: 10px 8px;">Estado</th>
                        <th style="padding: 10px 8px; text-align: center;">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($listas_resumen as $l): ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 10px 8px; font-weight: 600;">
                                <?php echo $l['grado']; ?>° "<?php echo $l['letra']; ?>"
                            </td>
                            <td style="padding: 10px 8px;">
                                <?php echo htmlspecialchars($l['nombre_materia']); ?><br>
                                <small style="color: var(--text-muted);"><?php echo substr($l['hora_inicio'], 0, 5) . " - " . substr($l['hora_fin'], 0, 5); ?></small>
                            </td>
                            <td style="padding: 10px 8px;"><?php echo htmlspecialchars($l['docente_apellidos'] . " " . $l['docente_nombres']); ?></td>
                            <td style="padding: 10px 8px;">
                                <?php
                                $badge_bg = '#f8d7da'; $badge_c = '#842029';
                                if ($l['estado_lista'] === 'Publicada') { $badge_bg = '#d1e7dd'; $badge_c = '#0f5132'; }
                                elseif ($l['estado_lista'] === 'Subida') { $badge_bg = '#fff3cd'; $badge_c = '#856404'; }
                                ?>
                                <span style="background: <?php echo $badge_bg; ?>; color: <?php echo $badge_c; ?>; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: 700;">
                                    <?php echo $l['estado_lista']; ?>
                                </span>
                            </td>
                            <td style="padding: 10px 8px; text-align: center;">
                                <?php if (!empty($l['id_lista'])): ?>
                                    <a href="asistencia.php?fecha=<?php echo $fecha_filtro; ?>&id_paralelo=<?php echo $id_paralelo_filtro; ?>&id_lista=<?php echo $l['id_lista']; ?>" 
                                       style="background: var(--primary); color: white; padding: 5px 12px; border-radius: 4px; text-decoration: none; font-size: 0.85rem;">
                                        Revisar / Publicar
                                    </a>
                                <?php else: ?>
                                    <small style="color: var(--text-muted);">Pendiente por docente</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p style="text-align: center; color: var(--text-muted); padding: 15px 0;">No se encontraron periodos configurados para la fecha o filtro seleccionado.</p>
    <?php endif; ?>
</div>

<!-- Formulario de Revisión y Edición de una Lista Seleccionada -->
<?php if ($info_lista_sel && !empty($detalle_estudiantes)): ?>
    <div class="card" style="border: 2px solid var(--primary);">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--border); padding-bottom: 12px; margin-bottom: 20px;">
            <div>
                <h3 style="color: var(--primary); margin: 0;">
                    Revisando: <?php echo $info_lista_sel['grado']; ?>° "<?php echo $info_lista_sel['letra']; ?>" — <?php echo htmlspecialchars($info_lista_sel['nombre_materia']); ?>
                </h3>
                <small style="color: var(--text-muted); font-weight: 600;">
                    Docente: <?php echo htmlspecialchars($info_lista_sel['docente_apellidos'] . " " . $info_lista_sel['docente_nombres']); ?> | Estado actual: <strong><?php echo $info_lista_sel['estado']; ?></strong>
                </small>
            </div>
        </div>

        <form action="asistencia.php?fecha=<?php echo $fecha_filtro; ?>&id_paralelo=<?php echo $id_paralelo_filtro; ?>&id_lista=<?php echo $id_lista_sel; ?>" method="POST">
            <input type="hidden" name="id_lista" value="<?php echo $id_lista_sel; ?>">

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border); text-align: left;">
                            <th style="padding: 10px 8px;">C.I.</th>
                            <th style="padding: 10px 8px;">Estudiante</th>
                            <th style="padding: 10px 8px; text-align: center;">Estado (Presente / Falta / Permiso)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detalle_estudiantes as $est): ?>
                            <tr style="border-bottom: 1px solid var(--border);">
                                <td style="padding: 10px 8px;"><?php echo htmlspecialchars($est['ci']); ?></td>
                                <td style="padding: 10px 8px; font-weight: 500;"><?php echo htmlspecialchars($est['apellidos'] . " " . $est['nombres']); ?></td>
                                <td style="padding: 10px 8px; text-align: center;">
                                    <div style="display: inline-flex; gap: 15px;">
                                        <label style="color: #2e7d32; font-weight: 600; cursor: pointer;">
                                            <input type="radio" name="asistencia[<?php echo $est['id_usuario']; ?>]" value="Presente" <?php echo ($est['estado_asistencia'] === 'Presente') ? 'checked' : ''; ?>> Presente
                                        </label>
                                        <label style="color: #c62828; font-weight: 600; cursor: pointer;">
                                            <input type="radio" name="asistencia[<?php echo $est['id_usuario']; ?>]" value="Falta" <?php echo ($est['estado_asistencia'] === 'Falta') ? 'checked' : ''; ?>> Falta
                                        </label>
                                        <label style="color: #0288d1; font-weight: 600; cursor: pointer;">
                                            <input type="radio" name="asistencia[<?php echo $est['id_usuario']; ?>]" value="Permiso" <?php echo ($est['estado_asistencia'] === 'Permiso') ? 'checked' : ''; ?>> Permiso
                                        </label>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="submit" name="accion" value="guardar_borrador" style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: 600;">
                    Guardar Cambios
                </button>
                <button type="submit" name="accion" value="publicar" class="btn-submit" style="width: auto; padding: 10px 24px;">
                    📢 Publicar Lista Oficial
                </button>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php require_once "../includes/footer_panel.php"; ?>