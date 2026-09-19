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

// Crear un nuevo aviso
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear') {
    $titulo = trim($_POST['titulo'] ?? '');
    $texto = trim($_POST['mensaje'] ?? '');
    $destinatario = $_POST['destinatario'] ?? 'todos';
    $alcance = $_POST['alcance'] ?? 'general';
    $paralelos_sel = $_POST['paralelos'] ?? [];

    if (empty($titulo) || empty($texto)) {
        $errores[] = "El título y el mensaje son obligatorios.";
    }
    if (!in_array($destinatario, ['docentes', 'estudiantes', 'todos'])) {
        $errores[] = "Destinatario no válido.";
    }
    if ($alcance === 'especifico' && empty($paralelos_sel)) {
        $errores[] = "Selecciona al menos un paralelo para un aviso específico.";
    }

    if (empty($errores)) {
        $stmt = mysqli_prepare($conexion, "INSERT INTO avisos (id_admin, titulo, mensaje, destinatario, alcance) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "issss", $_SESSION['id_usuario'], $titulo, $texto, $destinatario, $alcance);
        mysqli_stmt_execute($stmt);
        $id_aviso_nuevo = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);

        if ($alcance === 'especifico') {
            $stmt_p = mysqli_prepare($conexion, "INSERT INTO avisos_paralelos (id_aviso, id_paralelo) VALUES (?, ?)");
            foreach ($paralelos_sel as $id_par) {
                $id_par = intval($id_par);
                mysqli_stmt_bind_param($stmt_p, "ii", $id_aviso_nuevo, $id_par);
                mysqli_stmt_execute($stmt_p);
            }
            mysqli_stmt_close($stmt_p);
        }
        $mensaje = "Aviso publicado correctamente.";
    }
}

// Eliminar un aviso
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar') {
    $id_aviso = intval($_POST['id_aviso'] ?? 0);
    if ($id_aviso > 0) {
        $stmt = mysqli_prepare($conexion, "DELETE FROM avisos WHERE id_aviso = ?");
        mysqli_stmt_bind_param($stmt, "i", $id_aviso);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $mensaje = "Aviso eliminado.";
    }
}

$paralelos = mysqli_fetch_all(mysqli_query($conexion, "SELECT id_paralelo, grado, letra FROM paralelos ORDER BY grado, letra"), MYSQLI_ASSOC);

$sql_avisos = "SELECT a.*, GROUP_CONCAT(CONCAT(p.grado, '\"', p.letra, '\"') SEPARATOR ', ') AS paralelos_nombres
               FROM avisos a
               LEFT JOIN avisos_paralelos ap ON a.id_aviso = ap.id_aviso
               LEFT JOIN paralelos p ON ap.id_paralelo = p.id_paralelo
               GROUP BY a.id_aviso
               ORDER BY a.fecha_publicacion DESC";
$avisos = mysqli_fetch_all(mysqli_query($conexion, $sql_avisos), MYSQLI_ASSOC);

require_once "../includes/header_panel.php";
?>

<div class="card hero-section" style="margin-bottom: 25px;">
    <h2>📢 Avisos Institucionales</h2>
    <p>Publica comunicados generales o dirigidos a paralelos específicos.</p>
</div>

<?php if ($mensaje): ?>
    <div class="card" style="background-color: #d1e7dd; color: #0f5132; margin-bottom: 20px;"><?php echo htmlspecialchars($mensaje); ?></div>
<?php endif; ?>
<?php if (!empty($errores)): ?>
    <div class="card" style="background-color: #f8d7da; color: #842029; margin-bottom: 20px;">
        <ul style="margin-left: 18px;"><?php foreach ($errores as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?></ul>
    </div>
<?php endif; ?>

<div class="card" style="margin-bottom: 25px;">
    <h3 style="color: var(--primary); margin-bottom: 15px;">Publicar nuevo aviso</h3>
    <form action="avisos.php" method="POST" id="formAviso">
        <input type="hidden" name="accion" value="crear">
        <div class="form-group">
            <label>Título *</label>
            <input type="text" name="titulo" required>
        </div>
        <div class="form-group">
            <label>Mensaje *</label>
            <textarea name="mensaje" rows="3" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:6px;"></textarea>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <div class="form-group">
                <label>Destinatarios *</label>
                <select name="destinatario" required>
                    <option value="todos">Docentes y Estudiantes</option>
                    <option value="docentes">Solo Docentes</option>
                    <option value="estudiantes">Solo Estudiantes</option>
                </select>
            </div>
            <div class="form-group">
                <label>Alcance *</label>
                <select name="alcance" id="alcance" onchange="document.getElementById('bloqueParalelos').style.display = this.value === 'especifico' ? 'block' : 'none';">
                    <option value="general">General (todo el colegio)</option>
                    <option value="especifico">Específico (paralelos seleccionados)</option>
                </select>
            </div>
        </div>
        <div id="bloqueParalelos" style="display:none; background:#f8f9fa; padding:12px; border-radius:6px; margin-bottom: 15px;">
            <label style="font-weight:600; margin-bottom:8px; display:block;">Selecciona los paralelos:</label>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(90px, 1fr)); gap: 6px;">
                <?php foreach ($paralelos as $p): ?>
                    <label style="font-weight: normal; display:flex; align-items:center; gap:5px;">
                        <input type="checkbox" name="paralelos[]" value="<?php echo $p['id_paralelo']; ?>" style="width:auto;">
                        <?php echo $p['grado']; ?>° "<?php echo $p['letra']; ?>"
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <button type="submit" class="btn-submit" style="width:auto; padding:10px 24px;">Publicar Aviso</button>
    </form>
</div>

<div class="card">
    <h3 style="color: var(--primary); margin-bottom: 15px;">Avisos publicados</h3>
    <?php if (!empty($avisos)): ?>
        <?php foreach ($avisos as $a): ?>
            <div style="border: 1px solid var(--border); border-left: 4px solid var(--primary); border-radius: 6px; padding: 12px; margin-bottom: 12px;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <h4 style="margin:0; color: var(--primary);"><?php echo htmlspecialchars($a['titulo']); ?></h4>
                    <form action="avisos.php" method="POST" onsubmit="return confirm('¿Eliminar este aviso?');">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id_aviso" value="<?php echo $a['id_aviso']; ?>">
                        <button type="submit" style="background:#dc2626;color:white;border:none;padding:3px 10px;border-radius:4px;cursor:pointer;font-size:0.78rem;">Eliminar</button>
                    </form>
                </div>
                <p style="margin: 8px 0; color: var(--text-main);"><?php echo nl2br(htmlspecialchars($a['mensaje'])); ?></p>
                <small style="color: var(--text-muted);">
                    Para: <?php echo ucfirst($a['destinatario']); ?> |
                    Alcance: <?php echo $a['alcance'] === 'general' ? 'General' : 'Paralelos: ' . htmlspecialchars($a['paralelos_nombres']); ?> |
                    <?php echo date('d/m/Y H:i', strtotime($a['fecha_publicacion'])); ?>
                </small>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p style="text-align: center; color: var(--text-muted); padding: 15px 0;">No hay avisos publicados todavía.</p>
    <?php endif; ?>
</div>

<?php require_once "../includes/footer_panel.php"; ?>
