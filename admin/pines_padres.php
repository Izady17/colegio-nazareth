<?php
session_start();
$ruta_base = "../";

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrador') {
    header("Location: " . $ruta_base . "auth/login.php");
    exit();
}

require_once "../includes/conexion.php";

$mensaje = "";

// Regenerar el PIN de un estudiante
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'regenerar') {
    $id_estudiante = intval($_POST['id_usuario'] ?? 0);
    if ($id_estudiante > 0) {
        $nuevo_pin = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $nuevo_pin_hash = password_hash($nuevo_pin, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($conexion, "UPDATE usuarios SET password_padre = ?, pin_padre_plano = ?, pin_padre_entregado = 0 WHERE id_usuario = ?");
        mysqli_stmt_bind_param($stmt, "ssi", $nuevo_pin_hash, $nuevo_pin, $id_estudiante);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $mensaje = "PIN regenerado correctamente.";
    }
}

// Marcar un PIN como ya entregado a la familia
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'marcar_entregado') {
    $id_estudiante = intval($_POST['id_usuario'] ?? 0);
    if ($id_estudiante > 0) {
        $stmt = mysqli_prepare($conexion, "UPDATE usuarios SET pin_padre_entregado = 1 WHERE id_usuario = ?");
        mysqli_stmt_bind_param($stmt, "i", $id_estudiante);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $mensaje = "Marcado como entregado.";
    }
}

// Listar estudiantes con PIN pendiente de entrega
$sql = "SELECT u.id_usuario, u.ci, u.nombres, u.apellidos, u.pin_padre_plano, p.grado, p.letra
        FROM usuarios u
        LEFT JOIN estudiante_paralelo ep ON u.id_usuario = ep.id_estudiante
        LEFT JOIN paralelos p ON ep.id_paralelo = p.id_paralelo
        WHERE u.id_rol = 3 AND u.pin_padre_entregado = 0 AND u.password_padre IS NOT NULL
        ORDER BY u.creado_en DESC";
$res = mysqli_query($conexion, $sql);

// Listar también los ya entregados, por si hay que regenerar
$sql_entregados = "SELECT u.id_usuario, u.ci, u.nombres, u.apellidos, p.grado, p.letra
        FROM usuarios u
        LEFT JOIN estudiante_paralelo ep ON u.id_usuario = ep.id_estudiante
        LEFT JOIN paralelos p ON ep.id_paralelo = p.id_paralelo
        WHERE u.id_rol = 3 AND u.pin_padre_entregado = 1
        ORDER BY u.apellidos ASC";
$res_entregados = mysqli_query($conexion, $sql_entregados);

require_once "../includes/header_panel.php";
?>

<div class="card hero-section" style="margin-bottom: 25px;">
    <h2>🔑 PINs de Acceso para Padres de Familia</h2>
    <p>Entrega estos PIN en persona o en la reunión de padres. Una vez entregados, márcalos como tal.</p>
</div>

<?php if ($mensaje): ?>
    <div class="card" style="background-color: #d1e7dd; color: #0f5132; margin-bottom: 20px;"><?php echo htmlspecialchars($mensaje); ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom: 25px;">
    <h3 style="color: var(--primary); margin-bottom: 15px;">Pendientes de entrega</h3>
    <?php if (mysqli_num_rows($res) > 0): ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border); text-align: left;">
                        <th style="padding: 8px;">Estudiante</th>
                        <th style="padding: 8px;">CI</th>
                        <th style="padding: 8px;">Curso</th>
                        <th style="padding: 8px;">PIN</th>
                        <th style="padding: 8px; text-align: center;">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($e = mysqli_fetch_assoc($res)): ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 8px;"><?php echo htmlspecialchars($e['apellidos'] . " " . $e['nombres']); ?></td>
                            <td style="padding: 8px;"><?php echo htmlspecialchars($e['ci']); ?></td>
                            <td style="padding: 8px;"><?php echo $e['grado'] ? $e['grado'] . '° "' . $e['letra'] . '"' : '—'; ?></td>
                            <td style="padding: 8px; font-weight: 700; letter-spacing: 2px; color: var(--primary);"><?php echo htmlspecialchars($e['pin_padre_plano']); ?></td>
                            <td style="padding: 8px; text-align: center;">
                                <form action="pines_padres.php" method="POST" style="display: inline;">
                                    <input type="hidden" name="accion" value="marcar_entregado">
                                    <input type="hidden" name="id_usuario" value="<?php echo $e['id_usuario']; ?>">
                                    <button type="submit" style="background:#16a34a;color:white;border:none;padding:5px 10px;border-radius:4px;cursor:pointer;font-size:0.8rem;">Marcar entregado</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p style="text-align: center; color: var(--text-muted); padding: 15px 0;">No hay PINs pendientes de entrega.</p>
    <?php endif; ?>
</div>

<div class="card">
    <h3 style="color: var(--primary); margin-bottom: 15px;">Ya entregados (regenerar si se pierde)</h3>
    <?php if (mysqli_num_rows($res_entregados) > 0): ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border); text-align: left;">
                        <th style="padding: 8px;">Estudiante</th>
                        <th style="padding: 8px;">CI</th>
                        <th style="padding: 8px;">Curso</th>
                        <th style="padding: 8px; text-align: center;">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($e = mysqli_fetch_assoc($res_entregados)): ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 8px;"><?php echo htmlspecialchars($e['apellidos'] . " " . $e['nombres']); ?></td>
                            <td style="padding: 8px;"><?php echo htmlspecialchars($e['ci']); ?></td>
                            <td style="padding: 8px;"><?php echo $e['grado'] ? $e['grado'] . '° "' . $e['letra'] . '"' : '—'; ?></td>
                            <td style="padding: 8px; text-align: center;">
                                <form action="pines_padres.php" method="POST" style="display: inline;" onsubmit="return confirm('¿Generar un nuevo PIN? El anterior dejará de funcionar.');">
                                    <input type="hidden" name="accion" value="regenerar">
                                    <input type="hidden" name="id_usuario" value="<?php echo $e['id_usuario']; ?>">
                                    <button type="submit" style="background:#f59e0b;color:white;border:none;padding:5px 10px;border-radius:4px;cursor:pointer;font-size:0.8rem;">Regenerar PIN</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p style="text-align: center; color: var(--text-muted); padding: 15px 0;">Todavía no hay PINs entregados.</p>
    <?php endif; ?>
</div>

<?php require_once "../includes/footer_panel.php"; ?>
