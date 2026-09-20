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
$tab = ($_GET['tab'] ?? 'noticias') === 'eventos' ? 'eventos' : 'noticias';

/**
 * Valida y guarda la imagen subida para una noticia.
 * Devuelve [ruta_relativa_o_null, error_o_null].
 * Si no se subió ningún archivo, devuelve [null, null] (no es un error).
 */
function subirImagenNoticia($archivo) {
    if (!isset($archivo) || $archivo['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        return [null, "Error al subir la imagen (código {$archivo['error']}). Revisa el tamaño máximo permitido por el servidor."];
    }

    $permitidas = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];
    $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if (!array_key_exists($ext, $permitidas)) {
        return [null, "Formato de imagen no permitido. Usa JPG, PNG, WEBP o GIF."];
    }

    if ($archivo['size'] > 2 * 1024 * 1024) {
        return [null, "La imagen supera el tamaño máximo permitido (2 MB)."];
    }

    // Verifica el tipo real del archivo, no solo la extensión del nombre
    if (function_exists('mime_content_type')) {
        $tipo_real = mime_content_type($archivo['tmp_name']);
        if ($tipo_real !== $permitidas[$ext]) {
            return [null, "El archivo no parece ser una imagen válida."];
        }
    }

    $carpeta_destino = "../assets/img/noticias/";
    if (!is_dir($carpeta_destino)) {
        mkdir($carpeta_destino, 0755, true);
    }

    $nombre_nuevo = "noticia_" . uniqid() . "." . $ext;
    if (!move_uploaded_file($archivo['tmp_name'], $carpeta_destino . $nombre_nuevo)) {
        return [null, "No se pudo guardar la imagen en el servidor. Verifica los permisos de la carpeta assets/img/noticias."];
    }

    return ["assets/img/noticias/" . $nombre_nuevo, null];
}

// ---------------- NOTICIAS ----------------

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear_noticia') {
    $titulo = trim($_POST['titulo'] ?? '');
    $resumen = trim($_POST['resumen'] ?? '');
    $fecha = $_POST['fecha_publicacion'] ?? '';

    if (empty($titulo) || empty($resumen) || empty($fecha)) {
        $errores[] = "Título, resumen y fecha de publicación son obligatorios.";
    }

    [$imagen_subida, $error_imagen] = subirImagenNoticia($_FILES['imagen'] ?? null);
    if ($error_imagen) $errores[] = $error_imagen;

    if (empty($errores)) {
        $stmt = mysqli_prepare($conexion, "INSERT INTO noticias (id_admin, titulo, resumen, imagen, fecha_publicacion) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "issss", $_SESSION['id_usuario'], $titulo, $resumen, $imagen_subida, $fecha);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $mensaje = "Noticia publicada correctamente.";
    }
    $tab = 'noticias';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'editar_noticia') {
    $id = intval($_POST['id_noticia'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $resumen = trim($_POST['resumen'] ?? '');
    $fecha = $_POST['fecha_publicacion'] ?? '';
    $imagen_actual = $_POST['imagen_actual'] ?? null;

    if (empty($titulo) || empty($resumen) || empty($fecha)) {
        $errores[] = "Título, resumen y fecha de publicación son obligatorios.";
    }

    [$imagen_subida, $error_imagen] = subirImagenNoticia($_FILES['imagen'] ?? null);
    if ($error_imagen) $errores[] = $error_imagen;

    if ($id > 0 && empty($errores)) {
        $imagen_final = $imagen_subida ?: $imagen_actual;
        $stmt = mysqli_prepare($conexion, "UPDATE noticias SET titulo = ?, resumen = ?, imagen = ?, fecha_publicacion = ? WHERE id_noticia = ?");
        mysqli_stmt_bind_param($stmt, "ssssi", $titulo, $resumen, $imagen_final, $fecha, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $mensaje = "Noticia actualizada correctamente.";

        // Si se reemplazó la imagen, borra la anterior del servidor
        if ($imagen_subida && $imagen_actual && $imagen_actual !== $imagen_subida && file_exists("../" . $imagen_actual)) {
            @unlink("../" . $imagen_actual);
        }
    }
    $tab = 'noticias';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar_noticia') {
    $id = intval($_POST['id_noticia'] ?? 0);
    if ($id > 0) {
        $stmt = mysqli_prepare($conexion, "SELECT imagen FROM noticias WHERE id_noticia = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $fila_img = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        $stmt2 = mysqli_prepare($conexion, "DELETE FROM noticias WHERE id_noticia = ?");
        mysqli_stmt_bind_param($stmt2, "i", $id);
        mysqli_stmt_execute($stmt2);
        mysqli_stmt_close($stmt2);

        if (!empty($fila_img['imagen']) && file_exists("../" . $fila_img['imagen'])) {
            @unlink("../" . $fila_img['imagen']);
        }
        $mensaje = "Noticia eliminada.";
    }
    $tab = 'noticias';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'estado_noticia') {
    $id = intval($_POST['id_noticia'] ?? 0);
    $nuevo_estado = intval($_POST['nuevo_estado'] ?? 1);
    if ($id > 0) {
        $stmt = mysqli_prepare($conexion, "UPDATE noticias SET estado = ? WHERE id_noticia = ?");
        mysqli_stmt_bind_param($stmt, "ii", $nuevo_estado, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $mensaje = $nuevo_estado ? "Noticia publicada en el sitio." : "Noticia oculta del sitio público.";
    }
    $tab = 'noticias';
}

// ---------------- EVENTOS ----------------

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear_evento') {
    $titulo = trim($_POST['titulo'] ?? '');
    $lugar_hora = trim($_POST['lugar_hora'] ?? '');
    $fecha = $_POST['fecha_evento'] ?? '';

    if (empty($titulo) || empty($fecha)) {
        $errores[] = "Título y fecha del evento son obligatorios.";
    }

    if (empty($errores)) {
        $stmt = mysqli_prepare($conexion, "INSERT INTO eventos (id_admin, titulo, lugar_hora, fecha_evento) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "isss", $_SESSION['id_usuario'], $titulo, $lugar_hora, $fecha);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $mensaje = "Evento agregado correctamente.";
    }
    $tab = 'eventos';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'editar_evento') {
    $id = intval($_POST['id_evento'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $lugar_hora = trim($_POST['lugar_hora'] ?? '');
    $fecha = $_POST['fecha_evento'] ?? '';

    if (empty($titulo) || empty($fecha)) {
        $errores[] = "Título y fecha del evento son obligatorios.";
    }

    if ($id > 0 && empty($errores)) {
        $stmt = mysqli_prepare($conexion, "UPDATE eventos SET titulo = ?, lugar_hora = ?, fecha_evento = ? WHERE id_evento = ?");
        mysqli_stmt_bind_param($stmt, "sssi", $titulo, $lugar_hora, $fecha, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $mensaje = "Evento actualizado correctamente.";
    }
    $tab = 'eventos';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar_evento') {
    $id = intval($_POST['id_evento'] ?? 0);
    if ($id > 0) {
        $stmt = mysqli_prepare($conexion, "DELETE FROM eventos WHERE id_evento = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $mensaje = "Evento eliminado.";
    }
    $tab = 'eventos';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'estado_evento') {
    $id = intval($_POST['id_evento'] ?? 0);
    $nuevo_estado = intval($_POST['nuevo_estado'] ?? 1);
    if ($id > 0) {
        $stmt = mysqli_prepare($conexion, "UPDATE eventos SET estado = ? WHERE id_evento = ?");
        mysqli_stmt_bind_param($stmt, "ii", $nuevo_estado, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $mensaje = $nuevo_estado ? "Evento visible en el sitio público." : "Evento oculto del sitio público.";
    }
    $tab = 'eventos';
}

// ---------------- Cargar registro a editar (si viene por GET) ----------------

$noticia_editar = null;
if (isset($_GET['editar_noticia'])) {
    $id = intval($_GET['editar_noticia']);
    $stmt = mysqli_prepare($conexion, "SELECT * FROM noticias WHERE id_noticia = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $noticia_editar = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    $tab = 'noticias';
}

$evento_editar = null;
if (isset($_GET['editar_evento'])) {
    $id = intval($_GET['editar_evento']);
    $stmt = mysqli_prepare($conexion, "SELECT * FROM eventos WHERE id_evento = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $evento_editar = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    $tab = 'eventos';
}

// ---------------- Listados ----------------

$noticias = mysqli_fetch_all(mysqli_query($conexion, "SELECT * FROM noticias ORDER BY fecha_publicacion DESC, id_noticia DESC"), MYSQLI_ASSOC);
$eventos = mysqli_fetch_all(mysqli_query($conexion, "SELECT * FROM eventos ORDER BY fecha_evento ASC, id_evento DESC"), MYSQLI_ASSOC);

require_once "../includes/header_panel.php";
?>

<style>
.tab-filters-cw { display: flex; gap: 6px; background: #f5f5f5; padding: 4px; border-radius: 8px; width: fit-content; margin-bottom: 20px; }
.tab-btn-cw { text-decoration: none; padding: 8px 18px; font-size: 0.85rem; color: #555; border-radius: 6px; font-weight: 500; }
.tab-btn-cw.active { background: var(--primary); color: #fff; }
.status-badge-cw { padding: 3px 10px; border-radius: 12px; font-weight: 700; font-size: 0.78rem; }
.status-on { background: #dcfce7; color: #15803d; }
.status-off { background: #f1f5f9; color: #64748b; }
</style>

<div class="card hero-section" style="margin-bottom: 25px;">
    <h2>🌐 Contenido de la Página Web</h2>
    <p>Gestiona las "Últimas Noticias" y "Próximos Eventos" que se muestran en la portada del sitio.</p>
</div>

<?php if ($mensaje): ?>
    <div class="card" style="background-color: #d1e7dd; color: #0f5132; margin-bottom: 20px;"><?php echo htmlspecialchars($mensaje); ?></div>
<?php endif; ?>
<?php if (!empty($errores)): ?>
    <div class="card" style="background-color: #f8d7da; color: #842029; margin-bottom: 20px;">
        <ul style="margin-left: 18px;"><?php foreach ($errores as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?></ul>
    </div>
<?php endif; ?>

<div class="tab-filters-cw">
    <a href="contenido_web.php?tab=noticias" class="tab-btn-cw <?php echo $tab === 'noticias' ? 'active' : ''; ?>">📰 Noticias</a>
    <a href="contenido_web.php?tab=eventos" class="tab-btn-cw <?php echo $tab === 'eventos' ? 'active' : ''; ?>">📅 Próximos Eventos</a>
</div>

<?php if ($tab === 'noticias'): ?>

    <div class="card" style="margin-bottom: 25px;">
        <h3 style="color: var(--primary); margin-bottom: 15px;">
            <?php echo $noticia_editar ? 'Editar noticia' : 'Publicar nueva noticia'; ?>
        </h3>
        <form action="contenido_web.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="accion" value="<?php echo $noticia_editar ? 'editar_noticia' : 'crear_noticia'; ?>">
            <?php if ($noticia_editar): ?>
                <input type="hidden" name="id_noticia" value="<?php echo $noticia_editar['id_noticia']; ?>">
                <input type="hidden" name="imagen_actual" value="<?php echo htmlspecialchars($noticia_editar['imagen'] ?? ''); ?>">
            <?php endif; ?>
            <div class="form-group">
                <label>Título *</label>
                <input type="text" name="titulo" required value="<?php echo htmlspecialchars($noticia_editar['titulo'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Resumen *</label>
                <textarea name="resumen" rows="3" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:6px;"><?php echo htmlspecialchars($noticia_editar['resumen'] ?? ''); ?></textarea>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Fecha de publicación *</label>
                    <input type="date" name="fecha_publicacion" required value="<?php echo htmlspecialchars($noticia_editar['fecha_publicacion'] ?? date('Y-m-d')); ?>">
                </div>
                <div class="form-group">
                    <label>Imagen (JPG, PNG, WEBP o GIF, máx. 2MB)</label>
                    <?php if (!empty($noticia_editar['imagen'])): ?>
                        <div style="margin-bottom:6px; display:flex; align-items:center; gap:8px;">
                            <img src="../<?php echo htmlspecialchars($noticia_editar['imagen']); ?>" style="height:45px;width:45px;border-radius:6px;object-fit:cover;">
                            <small style="color:var(--text-muted);">Imagen actual — subí otra para reemplazarla</small>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="imagen" accept=".jpg,.jpeg,.png,.webp,.gif">
                </div>
            </div>
            <button type="submit" class="btn-submit" style="width:auto; padding:10px 24px;">
                <?php echo $noticia_editar ? 'Guardar cambios' : 'Publicar Noticia'; ?>
            </button>
            <?php if ($noticia_editar): ?>
                <a href="contenido_web.php?tab=noticias" style="margin-left:10px; font-size:0.85rem; color: var(--text-muted);">Cancelar edición</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <h3 style="color: var(--primary); margin-bottom: 15px;">Noticias registradas</h3>
        <?php if (!empty($noticias)): ?>
            <?php foreach ($noticias as $n): ?>
                <div style="border: 1px solid var(--border); border-left: 4px solid var(--primary); border-radius: 6px; padding: 12px; margin-bottom: 12px;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px; flex-wrap:wrap;">
                        <div style="display:flex; gap:10px; align-items:flex-start;">
                            <?php if (!empty($n['imagen'])): ?>
                                <img src="../<?php echo htmlspecialchars($n['imagen']); ?>" style="width:45px;height:45px;border-radius:6px;object-fit:cover;flex-shrink:0;">
                            <?php endif; ?>
                            <div>
                                <h4 style="margin:0; color: var(--primary);"><?php echo htmlspecialchars($n['titulo']); ?></h4>
                                <small style="color: var(--text-muted);"><?php echo date('d/m/Y', strtotime($n['fecha_publicacion'])); ?></small>
                                <span class="status-badge-cw <?php echo $n['estado'] ? 'status-on' : 'status-off'; ?>" style="margin-left:8px;">
                                    <?php echo $n['estado'] ? 'Publicada' : 'Oculta'; ?>
                                </span>
                            </div>
                        </div>
                        <div style="display:flex; gap:6px;">
                            <form action="contenido_web.php" method="POST">
                                <input type="hidden" name="accion" value="estado_noticia">
                                <input type="hidden" name="id_noticia" value="<?php echo $n['id_noticia']; ?>">
                                <input type="hidden" name="nuevo_estado" value="<?php echo $n['estado'] ? 0 : 1; ?>">
                                <button type="submit" style="background:<?php echo $n['estado'] ? '#64748b' : '#16a34a'; ?>;color:white;border:none;padding:5px 10px;border-radius:4px;cursor:pointer;font-size:0.78rem;">
                                    <?php echo $n['estado'] ? 'Ocultar' : 'Publicar'; ?>
                                </button>
                            </form>
                            <a href="contenido_web.php?tab=noticias&editar_noticia=<?php echo $n['id_noticia']; ?>" style="background:#0d6efd;color:white;border:none;padding:5px 10px;border-radius:4px;font-size:0.78rem;text-decoration:none;">Editar</a>
                            <form action="contenido_web.php" method="POST" onsubmit="return confirm('¿Eliminar esta noticia?');">
                                <input type="hidden" name="accion" value="eliminar_noticia">
                                <input type="hidden" name="id_noticia" value="<?php echo $n['id_noticia']; ?>">
                                <button type="submit" style="background:#dc2626;color:white;border:none;padding:5px 10px;border-radius:4px;cursor:pointer;font-size:0.78rem;">Eliminar</button>
                            </form>
                        </div>
                    </div>
                    <p style="margin: 8px 0 0; color: var(--text-main);"><?php echo htmlspecialchars($n['resumen']); ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="text-align: center; color: var(--text-muted); padding: 15px 0;">No hay noticias registradas todavía.</p>
        <?php endif; ?>
    </div>

<?php else: ?>

    <div class="card" style="margin-bottom: 25px;">
        <h3 style="color: var(--primary); margin-bottom: 15px;">
            <?php echo $evento_editar ? 'Editar evento' : 'Agregar nuevo evento'; ?>
        </h3>
        <form action="contenido_web.php" method="POST">
            <input type="hidden" name="accion" value="<?php echo $evento_editar ? 'editar_evento' : 'crear_evento'; ?>">
            <?php if ($evento_editar): ?>
                <input type="hidden" name="id_evento" value="<?php echo $evento_editar['id_evento']; ?>">
            <?php endif; ?>
            <div class="form-group">
                <label>Título *</label>
                <input type="text" name="titulo" required value="<?php echo htmlspecialchars($evento_editar['titulo'] ?? ''); ?>">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Fecha del evento *</label>
                    <input type="date" name="fecha_evento" required value="<?php echo htmlspecialchars($evento_editar['fecha_evento'] ?? date('Y-m-d')); ?>">
                </div>
                <div class="form-group">
                    <label>Lugar / Hora (opcional)</label>
                    <input type="text" name="lugar_hora" placeholder="Patio principal - 08:00 a.m." value="<?php echo htmlspecialchars($evento_editar['lugar_hora'] ?? ''); ?>">
                </div>
            </div>
            <button type="submit" class="btn-submit" style="width:auto; padding:10px 24px;">
                <?php echo $evento_editar ? 'Guardar cambios' : 'Agregar Evento'; ?>
            </button>
            <?php if ($evento_editar): ?>
                <a href="contenido_web.php?tab=eventos" style="margin-left:10px; font-size:0.85rem; color: var(--text-muted);">Cancelar edición</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <h3 style="color: var(--primary); margin-bottom: 15px;">Eventos registrados</h3>
        <?php if (!empty($eventos)): ?>
            <?php foreach ($eventos as $ev): ?>
                <div style="border: 1px solid var(--border); border-left: 4px solid var(--secondary); border-radius: 6px; padding: 12px; margin-bottom: 12px;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px; flex-wrap:wrap;">
                        <div>
                            <h4 style="margin:0; color: var(--primary);"><?php echo htmlspecialchars($ev['titulo']); ?></h4>
                            <small style="color: var(--text-muted);">
                                <?php echo date('d/m/Y', strtotime($ev['fecha_evento'])); ?>
                                <?php if (!empty($ev['lugar_hora'])): ?> — <?php echo htmlspecialchars($ev['lugar_hora']); ?><?php endif; ?>
                            </small>
                            <span class="status-badge-cw <?php echo $ev['estado'] ? 'status-on' : 'status-off'; ?>" style="margin-left:8px;">
                                <?php echo $ev['estado'] ? 'Visible' : 'Oculto'; ?>
                            </span>
                        </div>
                        <div style="display:flex; gap:6px;">
                            <form action="contenido_web.php" method="POST">
                                <input type="hidden" name="accion" value="estado_evento">
                                <input type="hidden" name="id_evento" value="<?php echo $ev['id_evento']; ?>">
                                <input type="hidden" name="nuevo_estado" value="<?php echo $ev['estado'] ? 0 : 1; ?>">
                                <button type="submit" style="background:<?php echo $ev['estado'] ? '#64748b' : '#16a34a'; ?>;color:white;border:none;padding:5px 10px;border-radius:4px;cursor:pointer;font-size:0.78rem;">
                                    <?php echo $ev['estado'] ? 'Ocultar' : 'Publicar'; ?>
                                </button>
                            </form>
                            <a href="contenido_web.php?tab=eventos&editar_evento=<?php echo $ev['id_evento']; ?>" style="background:#0d6efd;color:white;border:none;padding:5px 10px;border-radius:4px;font-size:0.78rem;text-decoration:none;">Editar</a>
                            <form action="contenido_web.php" method="POST" onsubmit="return confirm('¿Eliminar este evento?');">
                                <input type="hidden" name="accion" value="eliminar_evento">
                                <input type="hidden" name="id_evento" value="<?php echo $ev['id_evento']; ?>">
                                <button type="submit" style="background:#dc2626;color:white;border:none;padding:5px 10px;border-radius:4px;cursor:pointer;font-size:0.78rem;">Eliminar</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="text-align: center; color: var(--text-muted); padding: 15px 0;">No hay eventos registrados todavía.</p>
        <?php endif; ?>
    </div>

<?php endif; ?>

<?php require_once "../includes/footer_panel.php"; ?>