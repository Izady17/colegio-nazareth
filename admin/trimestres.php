<?php
session_start();
$ruta_base = "../";

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrador') {
    header("Location: " . $ruta_base . "auth/login.php");
    exit();
}

require_once "../includes/conexion.php";

$mensaje = "";
$errores = [];
$hoy = date('Y-m-d');
$anio_actual = date('Y');

// ---------------- Crear gestión (si no existe una activa) ----------------

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear_gestion') {
    $anio = intval($_POST['anio'] ?? $anio_actual);

    $stmt_chk = mysqli_prepare($conexion, "SELECT id_gestion FROM gestiones WHERE anio = ?");
    mysqli_stmt_bind_param($stmt_chk, "i", $anio);
    mysqli_stmt_execute($stmt_chk);
    $existe = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_chk));
    mysqli_stmt_close($stmt_chk);

    if ($existe) {
        $errores[] = "Ya existe una gestión registrada para el año $anio.";
    } else {
        mysqli_begin_transaction($conexion);
        try {
            $stmt_g = mysqli_prepare($conexion, "INSERT INTO gestiones (anio, estado) VALUES (?, 'Activa')");
            mysqli_stmt_bind_param($stmt_g, "i", $anio);
            mysqli_stmt_execute($stmt_g);
            $id_gestion_nueva = mysqli_insert_id($conexion);
            mysqli_stmt_close($stmt_g);

            $stmt_t = mysqli_prepare($conexion, "INSERT INTO trimestres (id_gestion, numero) VALUES (?, ?)");
            for ($n = 1; $n <= 3; $n++) {
                mysqli_stmt_bind_param($stmt_t, "ii", $id_gestion_nueva, $n);
                mysqli_stmt_execute($stmt_t);
            }
            mysqli_stmt_close($stmt_t);

            mysqli_commit($conexion);
            $mensaje = "Gestión $anio creada. Ahora definí las fechas de cada trimestre.";
        } catch (Exception $e) {
            mysqli_rollback($conexion);
            $errores[] = "Error al crear la gestión: " . $e->getMessage();
        }
    }
}

// ---------------- Guardar fechas de trimestres ----------------

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar_trimestres') {
    $id_gestion = intval($_POST['id_gestion'] ?? 0);
    $fechas = $_POST['fecha'] ?? []; // [numero => ['inicio' => .., 'fin' => ..]]

    // Traer trimestres actuales para saber cuáles ya están bloqueados
    $stmt_tr = mysqli_prepare($conexion, "SELECT id_trimestre, numero, fecha_fin FROM trimestres WHERE id_gestion = ? ORDER BY numero ASC");
    mysqli_stmt_bind_param($stmt_tr, "i", $id_gestion);
    mysqli_stmt_execute($stmt_tr);
    $trimestres_actuales = mysqli_fetch_all(mysqli_stmt_get_result($stmt_tr), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_tr);

    $rangos_validos = [];
    foreach ($trimestres_actuales as $t) {
        $num = $t['numero'];
        $bloqueado = !empty($t['fecha_fin']) && $t['fecha_fin'] < $hoy;

        if ($bloqueado) {
            continue; // no se toca un trimestre ya finalizado
        }

        $ini = trim($fechas[$num]['inicio'] ?? '');
        $fin = trim($fechas[$num]['fin'] ?? '');

        if ($ini !== '' && $fin !== '') {
            if ($ini >= $fin) {
                $errores[] = "Trimestre $num: la fecha de inicio debe ser anterior a la fecha de fin.";
            } else {
                $rangos_validos[$num] = ['id' => $t['id_trimestre'], 'inicio' => $ini, 'fin' => $fin];
            }
        } elseif ($ini !== '' || $fin !== '') {
            $errores[] = "Trimestre $num: completá tanto la fecha de inicio como la de fin.";
        }
    }

    // Validar que no se superpongan entre sí (orden 1 -> 2 -> 3), comparando contra el set completo (bloqueados incluidos)
    if (empty($errores)) {
        $fechas_por_num = [];
        foreach ($trimestres_actuales as $t) {
            $fechas_por_num[$t['numero']] = $rangos_validos[$t['numero']] ?? ['inicio' => null, 'fin' => $t['fecha_fin']];
        }
        for ($n = 1; $n <= 2; $n++) {
            $fin_actual = $rangos_validos[$n]['fin'] ?? ($fechas_por_num[$n]['fin'] ?? null);
            $ini_siguiente = $rangos_validos[$n + 1]['inicio'] ?? null;
            if ($fin_actual && $ini_siguiente && $ini_siguiente <= $fin_actual) {
                $errores[] = "El Trimestre " . ($n + 1) . " debe comenzar después de que termine el Trimestre $n.";
            }
        }
    }

    if (empty($errores) && !empty($rangos_validos)) {
        $stmt_up = mysqli_prepare($conexion, "UPDATE trimestres SET fecha_inicio = ?, fecha_fin = ? WHERE id_trimestre = ?");
        foreach ($rangos_validos as $r) {
            mysqli_stmt_bind_param($stmt_up, "ssi", $r['inicio'], $r['fin'], $r['id']);
            mysqli_stmt_execute($stmt_up);
        }
        mysqli_stmt_close($stmt_up);
        $mensaje = "Fechas de trimestres actualizadas correctamente.";
    }
}

// ---------------- Cargar gestión activa ----------------

$gestion_activa = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT * FROM gestiones WHERE estado = 'Activa' ORDER BY anio DESC LIMIT 1"));

$trimestres = [];
if ($gestion_activa) {
    $stmt_t = mysqli_prepare($conexion, "SELECT * FROM trimestres WHERE id_gestion = ? ORDER BY numero ASC");
    mysqli_stmt_bind_param($stmt_t, "i", $gestion_activa['id_gestion']);
    mysqli_stmt_execute($stmt_t);
    $trimestres = mysqli_fetch_all(mysqli_stmt_get_result($stmt_t), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_t);
}

// Historial de gestiones anteriores (solo lectura)
$historial = mysqli_fetch_all(mysqli_query($conexion,
    "SELECT g.anio, t.numero, t.fecha_inicio, t.fecha_fin
     FROM gestiones g INNER JOIN trimestres t ON g.id_gestion = t.id_gestion
     WHERE g.estado = 'Cerrada' ORDER BY g.anio DESC, t.numero ASC"
), MYSQLI_ASSOC);

require_once "../includes/header_panel.php";
?>

<style>
.trimestre-row { border: 1px solid var(--border); border-radius: 6px; padding: 15px; margin-bottom: 12px; display: flex; align-items: center; gap: 15px; flex-wrap: wrap; }
.trimestre-row.bloqueado { background: #f1f5f9; opacity: 0.75; }
.badge-tri { padding: 4px 10px; border-radius: 12px; font-size: 0.78rem; font-weight: 700; }
.badge-actual { background: #d1e7dd; color: #0f5132; }
.badge-proximo { background: #fff3cd; color: #856404; }
.badge-finalizado { background: #e2e3e5; color: #41464b; }
</style>

<div class="card hero-section" style="margin-bottom: 25px;">
    <h2>📅 Gestión Académica</h2>
    <p>Definí las fechas de inicio y fin de cada trimestre. Es la base para ponderaciones, asistencia y proyección de notas.</p>
</div>

<?php if ($mensaje): ?>
    <div class="card" style="background-color: #d1e7dd; color: #0f5132; margin-bottom: 20px;"><?php echo htmlspecialchars($mensaje); ?></div>
<?php endif; ?>
<?php if (!empty($errores)): ?>
    <div class="card" style="background-color: #f8d7da; color: #842029; margin-bottom: 20px;">
        <ul style="margin-left: 18px;"><?php foreach ($errores as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?></ul>
    </div>
<?php endif; ?>

<?php if (!$gestion_activa): ?>

    <div class="card">
        <h3 style="color: var(--primary); margin-bottom: 15px;">No hay una gestión activa todavía</h3>
        <form action="trimestres.php" method="POST" style="display:flex; gap:15px; align-items:flex-end;">
            <input type="hidden" name="accion" value="crear_gestion">
            <div class="form-group" style="margin:0;">
                <label>Año de la gestión</label>
                <input type="number" name="anio" value="<?php echo $anio_actual; ?>" required>
            </div>
            <button type="submit" class="btn-submit" style="width:auto; padding:10px 24px;">Crear gestión</button>
        </form>
    </div>

<?php else: ?>

    <div class="card">
        <h3 style="color: var(--primary); margin-bottom: 15px;">Gestión <?php echo $gestion_activa['anio']; ?></h3>
        <form action="trimestres.php" method="POST">
            <input type="hidden" name="accion" value="guardar_trimestres">
            <input type="hidden" name="id_gestion" value="<?php echo $gestion_activa['id_gestion']; ?>">

            <?php foreach ($trimestres as $t):
                $num = $t['numero'];
                $bloqueado = !empty($t['fecha_fin']) && $t['fecha_fin'] < $hoy;
                $estado_label = 'badge-proximo'; $estado_texto = 'Próximo / sin definir';
                if ($bloqueado) {
                    $estado_label = 'badge-finalizado'; $estado_texto = 'Finalizado — notas ya registradas';
                } elseif (!empty($t['fecha_inicio']) && !empty($t['fecha_fin']) && $t['fecha_inicio'] <= $hoy && $t['fecha_fin'] >= $hoy) {
                    $estado_label = 'badge-actual'; $estado_texto = 'Trimestre actual';
                }
            ?>
                <div class="trimestre-row <?php echo $bloqueado ? 'bloqueado' : ''; ?>">
                    <div style="min-width: 140px;">
                        <strong>Trimestre <?php echo $num; ?></strong><br>
                        <span class="badge-tri <?php echo $estado_label; ?>"><?php echo $estado_texto; ?></span>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label style="font-size:0.8rem;">Fecha inicio</label>
                        <input type="date" name="fecha[<?php echo $num; ?>][inicio]" value="<?php echo htmlspecialchars($t['fecha_inicio'] ?? ''); ?>" <?php echo $bloqueado ? 'disabled' : ''; ?>>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label style="font-size:0.8rem;">Fecha fin</label>
                        <input type="date" name="fecha[<?php echo $num; ?>][fin]" value="<?php echo htmlspecialchars($t['fecha_fin'] ?? ''); ?>" <?php echo $bloqueado ? 'disabled' : ''; ?>>
                    </div>
                </div>
            <?php endforeach; ?>

            <button type="submit" class="btn-submit" style="width:auto; padding:10px 24px; margin-top:10px;">Guardar cambios</button>
        </form>
    </div>

<?php endif; ?>

<?php if (!empty($historial)): ?>
    <div class="card" style="margin-top: 25px;">
        <h3 style="color: var(--primary); margin-bottom: 15px;">Historial de gestiones cerradas</h3>
        <table style="width:100%; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom: 2px solid var(--border); text-align:left;">
                    <th style="padding:8px;">Año</th><th style="padding:8px;">Trimestre</th><th style="padding:8px;">Fechas</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($historial as $h): ?>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding:8px;"><?php echo $h['anio']; ?></td>
                        <td style="padding:8px;">Trimestre <?php echo $h['numero']; ?></td>
                        <td style="padding:8px;"><?php echo $h['fecha_inicio'] ? date('d/m/Y', strtotime($h['fecha_inicio'])) . ' — ' . date('d/m/Y', strtotime($h['fecha_fin'])) : 'Sin definir'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require_once "../includes/footer_panel.php"; ?>