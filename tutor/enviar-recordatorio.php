<?php
$ruta_base = "../";
require_once "../includes/conexion.php";
require_once "../includes/funciones.php";

verificarAcceso(['docente']);

$mensaje = "";
$error = "";

// Cargar cursos
$sql_cursos = "SELECT id_curso, grado, paralelo FROM cursos";
$res_cursos = mysqli_query($conexion, $sql_cursos);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id_docente = $_SESSION['id_usuario'];
    $id_curso = (int)$_POST['id_curso'];
    $titulo = limpiarCadena($_POST['titulo']);
    $mensaje_txt = limpiarCadena($_POST['mensaje']);

    if (empty($id_curso) || empty($titulo) || empty($mensaje_txt)) {
        $error = "Por favor complete todos los campos del formulario.";
    } else {
        $sql = "INSERT INTO recordatorios (id_docente, id_curso, titulo, mensaje) VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "iiss", $id_docente, $id_curso, $titulo, $mensaje_txt);

        if (mysqli_stmt_execute($stmt)) {
            $mensaje = "Recordatorio publicado con éxito.";
        } else {
            $error = "Error al publicar el aviso: " . mysqli_error($conexion);
        }
    }
}

require_once "../includes/header_panel.php";
?>

<div style="max-width: 650px; margin: 0 auto;">
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h2 style="color: var(--primary-color);">Publicar Recordatorio / Aviso</h2>
            <a href="dashboard.php" style="text-decoration: none; color: var(--primary-color); font-weight: bold;">&larr; Volver</a>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert" style="background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($mensaje)): ?>
            <div class="alert" style="background-color: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <form action="enviar-recordatorio.php" method="POST">
            <div class="form-group">
                <label for="id_curso">Curso Destino *:</label>
                <select name="id_curso" id="id_curso" required>
                    <option value="">-- Seleccionar Curso --</option>
                    <?php while ($c = mysqli_fetch_assoc($res_cursos)): ?>
                        <option value="<?php echo $c['id_curso']; ?>"><?php echo $c['grado'] . " '" . $c['paralelo'] . "'"; ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="titulo">Título del Aviso *:</label>
                <input type="text" name="titulo" id="titulo" required placeholder="Ej. Recordatorio: Examen Parcial de Física">
            </div>

            <div class="form-group">
                <label for="mensaje">Contenido del Comunicado *:</label>
                <textarea name="mensaje" id="mensaje" rows="5" required placeholder="Escriba detalladamente el aviso para los estudiantes..."></textarea>
            </div>

            <button type="submit" class="btn-submit" style="width: 100%;">Publicar Comunicado</button>
        </form>
    </div>
</div>

<?php require_once "../includes/footer_panel.php"; ?>