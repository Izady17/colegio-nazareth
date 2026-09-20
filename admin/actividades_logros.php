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
$tab = ($_GET['tab'] ?? 'actividades') === 'logros' ? 'logros' : 'actividades';

// ---------------- ACTIVIDADES ----------------

$categorias_actividad = ['Desfiles', 'Actos Cívicos', 'Eventos Culturales', 'Otras Actividades'];
$estados_proceso = ['Realizado', 'En proceso', 'Pendiente'];

// CREAR ACTIVIDAD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear_actividad') {
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $categoria = $_POST['categoria'] ?? 'Otras Actividades';
    $lugar = trim($_POST['lugar'] ?? 'Unidad Educativa');
    $tags = trim($_POST['tags'] ?? '');
    $estado_proceso = $_POST['estado_proceso'] ?? 'Pendiente';
    $fecha = $_POST['fecha'] ?? '';
    $id_admin = $_SESSION['id_admin'] ?? 1;
    $imagen_nombre = null;

    if (empty($titulo) || empty($descripcion) || empty($fecha)) {
        $errores[] = "Título, descripción y fecha son obligatorios.";
    }

    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $directorio = "../assets/img/uploads/actividades/";
        if (!is_dir($directorio)) mkdir($directorio, 0777, true);
        $imagen_nombre = time() . "_" . basename($_FILES['imagen']['name']);
        move_uploaded_file($_FILES['imagen']['tmp_name'], $directorio . $imagen_nombre);
    }

    if (empty($errores)) {
        $stmt = mysqli_prepare($conexion, "INSERT INTO actividades (id_admin, titulo, descripcion, categoria, lugar, tags, estado_proceso, imagen, fecha, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
        mysqli_stmt_bind_param($stmt, "issssssss", $id_admin, $titulo, $descripcion, $categoria, $lugar, $tags, $estado_proceso, $imagen_nombre, $fecha);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $mensaje = "Actividad creada correctamente.";
    }
    $tab = 'actividades';
}

// EDITAR ACTIVIDAD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'editar_actividad') {
    $id = intval($_POST['id_actividad'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $categoria = $_POST['categoria'] ?? 'Otras Actividades';
    $lugar = trim($_POST['lugar'] ?? 'Unidad Educativa');
    $tags = trim($_POST['tags'] ?? '');
    $estado_proceso = $_POST['estado_proceso'] ?? 'Pendiente';
    $fecha = $_POST['fecha'] ?? '';

    if (empty($titulo) || empty($descripcion) || empty($fecha)) {
        $errores[] = "Título, descripción y fecha son obligatorios.";
    }

    $imagen_sql = "";
    $params = [$titulo, $descripcion, $categoria, $lugar, $tags, $estado_proceso, $fecha];
    $tipos = "sssssss";

    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $directorio = "../assets/img/uploads/actividades/";
        if (!is_dir($directorio)) mkdir($directorio, 0777, true);
        $imagen_nombre = time() . "_" . basename($_FILES['imagen']['name']);
        if (move_uploaded_file($_FILES['imagen']['tmp_name'], $directorio . $imagen_nombre)) {
            $imagen_sql = ", imagen = ?";
            $params[] = $imagen_nombre;
            $tipos .= "s";
        }
    }

    $params[] = $id;
    $tipos .= "i";

    if ($id > 0 && empty($errores)) {
        $stmt = mysqli_prepare($conexion, "UPDATE actividades SET titulo = ?, descripcion = ?, categoria = ?, lugar = ?, tags = ?, estado_proceso = ?, fecha = ? $imagen_sql WHERE id_actividad = ?");
        mysqli_stmt_bind_param($stmt, $tipos, ...$params);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $mensaje = "Actividad actualizada correctamente.";
    }
    $tab = 'actividades';
}

// CAMBIAR ESTADO DE ACTIVIDAD (PUBLICAR/OCULTAR)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'estado_actividad') {
    $id = intval($_POST['id_actividad'] ?? 0);
    $nuevo_estado = intval($_POST['nuevo_estado'] ?? 0);
    if ($id > 0) {
        $stmt = mysqli_prepare($conexion, "UPDATE actividades SET estado = ? WHERE id_actividad = ?");
        mysqli_stmt_bind_param($stmt, "ii", $nuevo_estado, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $mensaje = "Estado de la actividad actualizado.";
    }
    $tab = 'actividades';
}

// ELIMINAR ACTIVIDAD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar_actividad') {
    $id = intval($_POST['id_actividad'] ?? 0);
    if ($id > 0) {
        // Borrar imagen física si existe
        $res = mysqli_query($conexion, "SELECT imagen FROM actividades WHERE id_actividad = $id");
        if ($reg = mysqli_fetch_assoc($res)) {
            if (!empty($reg['imagen']) && file_exists("assets/img/uploads/actividades/" . $reg['imagen'])) {
                unlink("assets/img/uploads/actividades/" . $reg['imagen']);
            }
        }
        $stmt = mysqli_prepare($conexion, "DELETE FROM actividades WHERE id_actividad = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $mensaje = "Actividad eliminada correctamente.";
    }
    $tab = 'actividades';
}

// ---------------- LOGROS ----------------

$categorias_logro = ['Deportivo', 'Académico', 'Artístico', 'Ciencia y Tecnología', 'Disciplina y Valores'];

// CREAR LOGRO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear_logro') {
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $categoria = $_POST['categoria'] ?? '';
    $fecha = $_POST['fecha'] ?? '';
    $imagen_nombre = null;

    if (empty($titulo) || empty($descripcion) || empty($fecha) || !in_array($categoria, $categorias_logro)) {
        $errores[] = "Título, descripción, categoría y fecha son obligatorios.";
    }

    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $directorio = "../assets/img/uploads/logros/";
        if (!is_dir($directorio)) mkdir($directorio, 0777, true);
        $imagen_nombre = time() . "_" . basename($_FILES['imagen']['name']);
        move_uploaded_file($_FILES['imagen']['tmp_name'], $directorio . $imagen_nombre);
    }

    if (empty($errores)) {
        $stmt = mysqli_prepare($conexion, "INSERT INTO logros (titulo, descripcion, categoria, imagen, fecha, destacado) VALUES (?, ?, ?, ?, ?, 1)");
        mysqli_stmt_bind_param($stmt, "sssss", $titulo, $descripcion, $categoria, $imagen_nombre, $fecha);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $mensaje = "Logro publicado correctamente.";
    }
    $tab = 'logros';
}

// EDITAR LOGRO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'editar_logro') {
    $id = intval($_POST['id_logro'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $categoria = $_POST['categoria'] ?? '';
    $fecha = $_POST['fecha'] ?? '';

    if (empty($titulo) || empty($descripcion) || empty($fecha) || !in_array($categoria, $categorias_logro)) {
        $errores[] = "Título, descripción, categoría y fecha son obligatorios.";
    }

    $imagen_sql = "";
    $params = [$titulo, $descripcion, $categoria, $fecha];
    $tipos = "ssss";

    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $directorio = "../assets/img/uploads/logros/";
        if (!is_dir($directorio)) mkdir($directorio, 0777, true);
        $imagen_nombre = time() . "_" . basename($_FILES['imagen']['name']);
        if (move_uploaded_file($_FILES['imagen']['tmp_name'], $directorio . $imagen_nombre)) {
            $imagen_sql = ", imagen = ?";
            $params[] = $imagen_nombre;
            $tipos .= "s";
        }
    }

    $params[] = $id;
    $tipos .= "i";

    if ($id > 0 && empty($errores)) {
        $stmt = mysqli_prepare($conexion, "UPDATE logros SET titulo = ?, descripcion = ?, categoria = ?, fecha = ? $imagen_sql WHERE id_logro = ?");
        mysqli_stmt_bind_param($stmt, $tipos, ...$params);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $mensaje = "Logro actualizado correctamente.";
    }
    $tab = 'logros';
}

// CAMBIAR DESTACADO/VISIBILIDAD DE LOGRO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'destacado_logro') {
    $id = intval($_POST['id_logro'] ?? 0);
    $nuevo_destacado = intval($_POST['nuevo_destacado'] ?? 0);
    if ($id > 0) {
        $stmt = mysqli_prepare($conexion, "UPDATE logros SET destacado = ? WHERE id_logro = ?");
        mysqli_stmt_bind_param($stmt, "ii", $nuevo_destacado, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $mensaje = "Visibilidad del logro actualizada.";
    }
    $tab = 'logros';
}

// ELIMINAR LOGRO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar_logro') {
    $id = intval($_POST['id_logro'] ?? 0);
    if ($id > 0) {
        // Borrar imagen física si existe
        $res = mysqli_query($conexion, "SELECT imagen FROM logros WHERE id_logro = $id");
        if ($reg = mysqli_fetch_assoc($res)) {
            if (!empty($reg['imagen']) && file_exists("assets/img/uploads/logros/" . $reg['imagen'])) {
                unlink("assets/img/uploads/logros/" . $reg['imagen']);
            }
        }
        $stmt = mysqli_prepare($conexion, "DELETE FROM logros WHERE id_logro = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $mensaje = "Logro eliminado correctamente.";
    }
    $tab = 'logros';
}

// ---------------- Cargar registro a editar (si viene por GET) ----------------

$act_editar = null;
if (isset($_GET['editar_actividad'])) {
    $id = intval($_GET['editar_actividad']);
    $stmt = mysqli_prepare($conexion, "SELECT * FROM actividades WHERE id_actividad = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $act_editar = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    $tab = 'actividades';
}

$logro_editar = null;
if (isset($_GET['editar_logro'])) {
    $id = intval($_GET['editar_logro']);
    $stmt = mysqli_prepare($conexion, "SELECT * FROM logros WHERE id_logro = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $logro_editar = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    $tab = 'logros';
}

// ---------------- Listados ----------------

$actividades = mysqli_fetch_all(mysqli_query($conexion, "SELECT * FROM actividades ORDER BY fecha DESC, id_actividad DESC"), MYSQLI_ASSOC);
$logros = mysqli_fetch_all(mysqli_query($conexion, "SELECT * FROM logros ORDER BY fecha DESC, id_logro DESC"), MYSQLI_ASSOC);

require_once "../includes/header_panel.php";
?>

<style>
.tab-filters-al { display: flex; gap: 6px; background: #f5f5f5; padding: 4px; border-radius: 8px; width: fit-content; margin-bottom: 20px; }
.tab-btn-al { text-decoration: none; padding: 8px 18px; font-size: 0.85rem; color: #555; border-radius: 6px; font-weight: 500; }
.tab-btn-al.active { background: var(--primary); color: #fff; }
.status-badge-al { padding: 3px 10px; border-radius: 12px; font-weight: 700; font-size: 0.78rem; }
.status-on { background: #dcfce7; color: #15803d; }
.status-off { background: #f1f5f9; color: #64748b; }
</style>

<div class="card hero-section" style="margin-bottom: 25px;">
    <h2>🏅 Actividades y Logros</h2>
    <p>Gestiona las "Actividades Institucionales" y los "Logros" que se muestran en las páginas públicas del sitio.</p>
</div>

<?php if ($mensaje): ?>
    <div class="card" style="background-color: #d1e7dd; color: #0f5132; margin-bottom: 20px;"><?php echo htmlspecialchars($mensaje); ?></div>
<?php endif; ?>
<?php if (!empty($errores)): ?>
    <div class="card" style="background-color: #f8d7da; color: #842029; margin-bottom: 20px;">
        <ul style="margin-left: 18px;"><?php foreach ($errores as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?></ul>
    </div>
<?php endif; ?>

<div class="tab-filters-al">
    <a href="actividades_logros.php?tab=actividades" class="tab-btn-al <?php echo $tab === 'actividades' ? 'active' : ''; ?>">🎉 Actividades</a>
    <a href="actividades_logros.php?tab=logros" class="tab-btn-al <?php echo $tab === 'logros' ? 'active' : ''; ?>">🏆 Logros</a>
</div>

<?php if ($tab === 'actividades'): ?>

    <div class="card" style="margin-bottom: 25px;">
        <h3 style="color: var(--primary); margin-bottom: 15px;">
            <?php echo $act_editar ? 'Editar actividad' : 'Nueva actividad'; ?>
        </h3>
        <form action="actividades_logros.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="accion" value="<?php echo $act_editar ? 'editar_actividad' : 'crear_actividad'; ?>">
            <?php if ($act_editar): ?>
                <input type="hidden" name="id_actividad" value="<?php echo $act_editar['id_actividad']; ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Título *</label>
                <input type="text" name="titulo" required value="<?php echo htmlspecialchars($act_editar['titulo'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Descripción *</label>
                <textarea name="descripcion" rows="3" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:6px;"><?php echo htmlspecialchars($act_editar['descripcion'] ?? ''); ?></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div class="form-group">
                    <label>Categoría *</label>
                    <select name="categoria" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:6px;">
                        <?php foreach ($categorias_actividad as $cat): ?>
                            <option value="<?php echo $cat; ?>" <?php echo (($act_editar['categoria'] ?? '') === $cat) ? 'selected' : ''; ?>><?php echo $cat; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Estado del Proceso *</label>
                    <select name="estado_proceso" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:6px;">
                        <?php foreach ($estados_proceso as $est): ?>
                            <option value="<?php echo $est; ?>" <?php echo (($act_editar['estado_proceso'] ?? '') === $est) ? 'selected' : ''; ?>><?php echo $est; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Lugar</label>
                    <input type="text" name="lugar" placeholder="Ej. Plaza 10 de Febrero" value="<?php echo htmlspecialchars($act_editar['lugar'] ?? 'Unidad Educativa'); ?>">
                </div>

                <div class="form-group">
                    <label>Etiquetas / Tags (separados por coma)</label>
                    <input type="text" name="tags" placeholder="Ej. Desfile, Cívico" value="<?php echo htmlspecialchars($act_editar['tags'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Fecha *</label>
                    <input type="date" name="fecha" required value="<?php echo htmlspecialchars($act_editar['fecha'] ?? date('Y-m-d')); ?>">
                </div>
            </div>

            <div class="form-group" style="margin-top: 15px;">
                <label>Imagen de la actividad (Opcional)</label>
                <input type="file" name="imagen" accept="image/*" style="width:100%; padding:7px; border:1px solid var(--border); border-radius:6px;">
            </div>

            <button type="submit" class="btn-submit" style="width:auto; padding:10px 24px; margin-top:15px;">
                <?php echo $act_editar ? 'Guardar cambios' : 'Publicar Actividad'; ?>
            </button>

            <?php if ($act_editar): ?>
                <a href="actividades_logros.php?tab=actividades" style="margin-left:10px; font-size:0.85rem; color: var(--text-muted);">Cancelar edición</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <h3 style="color: var(--primary); margin-bottom: 15px;">Actividades registradas</h3>
        <?php if (!empty($actividades)): ?>
            <?php foreach ($actividades as $a): ?>
                <div style="border: 1px solid var(--border); border-left: 4px solid var(--primary); border-radius: 6px; padding: 12px; margin-bottom: 12px;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px; flex-wrap:wrap;">
                        <div>
                            <h4 style="margin:0; color: var(--primary);"><?php echo htmlspecialchars($a['icono'] ?? '📌'); ?> <?php echo htmlspecialchars($a['titulo']); ?></h4>
                            <small style="color: var(--text-muted);"><?php echo date('d/m/Y', strtotime($a['fecha'])); ?></small>
                            <span class="status-badge-al <?php echo $a['estado'] ? 'status-on' : 'status-off'; ?>" style="margin-left:8px;">
                                <?php echo $a['estado'] ? 'Publicada' : 'Oculta'; ?>
                            </span>
                        </div>
                        <div style="display:flex; gap:6px;">
                            <form action="actividades_logros.php" method="POST">
                                <input type="hidden" name="accion" value="estado_actividad">
                                <input type="hidden" name="id_actividad" value="<?php echo $a['id_actividad']; ?>">
                                <input type="hidden" name="nuevo_estado" value="<?php echo $a['estado'] ? 0 : 1; ?>">
                                <button type="submit" style="background:<?php echo $a['estado'] ? '#64748b' : '#16a34a'; ?>;color:white;border:none;padding:5px 10px;border-radius:4px;cursor:pointer;font-size:0.78rem;">
                                    <?php echo $a['estado'] ? 'Ocultar' : 'Publicar'; ?>
                                </button>
                            </form>
                            <a href="actividades_logros.php?tab=actividades&editar_actividad=<?php echo $a['id_actividad']; ?>" style="background:#0d6efd;color:white;border:none;padding:5px 10px;border-radius:4px;font-size:0.78rem;text-decoration:none;">Editar</a>
                            <form action="actividades_logros.php" method="POST" onsubmit="return confirm('¿Eliminar esta actividad?');">
                                <input type="hidden" name="accion" value="eliminar_actividad">
                                <input type="hidden" name="id_actividad" value="<?php echo $a['id_actividad']; ?>">
                                <button type="submit" style="background:#dc2626;color:white;border:none;padding:5px 10px;border-radius:4px;cursor:pointer;font-size:0.78rem;">Eliminar</button>
                            </form>
                        </div>
                    </div>
                    <p style="margin: 8px 0 0; color: var(--text-main);"><?php echo htmlspecialchars($a['descripcion']); ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="text-align: center; color: var(--text-muted); padding: 15px 0;">No hay actividades registradas todavía.</p>
        <?php endif; ?>
    </div>

<?php else: ?>

    <div class="card" style="margin-bottom: 25px;">
        <h3 style="color: var(--primary); margin-bottom: 15px;">
            <?php echo $logro_editar ? 'Editar logro' : 'Publicar nuevo logro'; ?>
        </h3>
        <form action="actividades_logros.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="accion" value="<?php echo $logro_editar ? 'editar_logro' : 'crear_logro'; ?>">
            <?php if ($logro_editar): ?>
                <input type="hidden" name="id_logro" value="<?php echo $logro_editar['id_logro']; ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label>Título *</label>
                <input type="text" name="titulo" required value="<?php echo htmlspecialchars($logro_editar['titulo'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label>Descripción *</label>
                <textarea name="descripcion" rows="3" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:6px;"><?php echo htmlspecialchars($logro_editar['descripcion'] ?? ''); ?></textarea>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Categoría *</label>
                    <select name="categoria" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:6px;">
                        <?php foreach ($categorias_logro as $cat): ?>
                            <option value="<?php echo $cat; ?>" <?php echo (($logro_editar['categoria'] ?? '') === $cat) ? 'selected' : ''; ?>><?php echo $cat; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Fecha *</label>
                    <input type="date" name="fecha" required value="<?php echo htmlspecialchars($logro_editar['fecha'] ?? date('Y-m-d')); ?>">
                </div>
                
                <div class="form-group">
                    <label>Imagen (Opcional)</label>
                    <input type="file" name="imagen" accept="image/*" style="width:100%; padding:7px; border:1px solid var(--border); border-radius:6px;">
                </div>
            </div>
            
            <button type="submit" class="btn-submit" style="width:auto; padding:10px 24px; margin-top:15px;">
                <?php echo $logro_editar ? 'Guardar cambios' : 'Publicar Logro'; ?>
            </button>
            
            <?php if ($logro_editar): ?>
                <a href="actividades_logros.php?tab=logros" style="margin-left:10px; font-size:0.85rem; color: var(--text-muted);">Cancelar edición</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <h3 style="color: var(--primary); margin-bottom: 15px;">Logros registrados</h3>
        <?php if (!empty($logros)): ?>
            <?php foreach ($logros as $l): ?>
                <div style="border: 1px solid var(--border); border-left: 4px solid var(--secondary); border-radius: 6px; padding: 12px; margin-bottom: 12px;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px; flex-wrap:wrap;">
                        <div>
                            <h4 style="margin:0; color: var(--primary);"><?php echo htmlspecialchars($l['titulo']); ?></h4>
                            <small style="color: var(--text-muted);"><?php echo htmlspecialchars($l['categoria']); ?> — <?php echo date('d/m/Y', strtotime($l['fecha'])); ?></small>
                            <span class="status-badge-al <?php echo $l['destacado'] ? 'status-on' : 'status-off'; ?>" style="margin-left:8px;">
                                <?php echo $l['destacado'] ? 'Visible' : 'Oculto'; ?>
                            </span>
                        </div>
                        <div style="display:flex; gap:6px;">
                            <form action="actividades_logros.php" method="POST">
                                <input type="hidden" name="accion" value="destacado_logro">
                                <input type="hidden" name="id_logro" value="<?php echo $l['id_logro']; ?>">
                                <input type="hidden" name="nuevo_destacado" value="<?php echo $l['destacado'] ? 0 : 1; ?>">
                                <button type="submit" style="background:<?php echo $l['destacado'] ? '#64748b' : '#16a34a'; ?>;color:white;border:none;padding:5px 10px;border-radius:4px;cursor:pointer;font-size:0.78rem;">
                                    <?php echo $l['destacado'] ? 'Ocultar' : 'Publicar'; ?>
                                </button>
                            </form>
                            <a href="actividades_logros.php?tab=logros&editar_logro=<?php echo $l['id_logro']; ?>" style="background:#0d6efd;color:white;border:none;padding:5px 10px;border-radius:4px;font-size:0.78rem;text-decoration:none;">Editar</a>
                            <form action="actividades_logros.php" method="POST" onsubmit="return confirm('¿Eliminar este logro?');">
                                <input type="hidden" name="accion" value="eliminar_logro">
                                <input type="hidden" name="id_logro" value="<?php echo $l['id_logro']; ?>">
                                <button type="submit" style="background:#dc2626;color:white;border:none;padding:5px 10px;border-radius:4px;cursor:pointer;font-size:0.78rem;">Eliminar</button>
                            </form>
                        </div>
                    </div>
                    <p style="margin: 8px 0 0; color: var(--text-main);"><?php echo htmlspecialchars($l['descripcion']); ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="text-align: center; color: var(--text-muted); padding: 15px 0;">No hay logros registrados todavía.</p>
        <?php endif; ?>
    </div>

<?php endif; ?>

<?php require_once "../includes/footer_panel.php"; ?>