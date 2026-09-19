<?php
$ruta_base = "../";
require_once "../includes/conexion.php";
require_once "../includes/funciones.php";

$mensaje = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = limpiarCadena($_POST['nombre_solicitante']);
    $relacion = limpiarCadena($_POST['relacion']);
    $telefono = limpiarCadena($_POST['telefono']);
    $motivo = limpiarCadena($_POST['motivo']);

    if (empty($nombre) || empty($relacion) || empty($telefono) || empty($motivo)) {
        $error = "Por favor, complete todos los campos del formulario.";
    } else {
        $sql = "INSERT INTO consultas_psicologicas (nombre_solicitante, relacion, telefono, motivo) 
                VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "ssss", $nombre, $relacion, $telefono, $motivo);

        if (mysqli_stmt_execute($stmt)) {
            $mensaje = "Su solicitud fue enviada exitosamente. El gabinete psicopedagógico se pondrá en contacto a la brevedad.";
        } else {
            $error = "Ocurrió un error al registrar su solicitud: " . mysqli_error($conexion);
        }
    }
}

require_once "../includes/header.php";
?>

<div style="max-width: 650px; margin: 0 auto;">
    <div class="card">
        <h2 style="color: var(--primary-color); margin-bottom: 15px;">Gabinete Psicopedagógico</h2>
        <p style="margin-bottom: 20px; font-size: 0.95rem; line-height: 1.5;">
            Espacio confidencial orientado al apoyo emocional, psicopedagógico y adaptación escolar de nuestros estudiantes. Rellene el siguiente formulario para solicitar una atención.
        </p>

        <?php if (!empty($error)): ?>
            <div class="alert" style="background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 15px;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($mensaje)): ?>
            <div class="alert" style="background-color: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 15px;">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <form action="gabinete-psicologico.php" method="POST">
            <div class="form-group">
                <label for="nombre_solicitante">Nombre completo del Solicitante *:</label>
                <input type="text" name="nombre_solicitante" id="nombre_solicitante" required placeholder="Ej. Juan Pérez">
            </div>

            <div class="form-group">
                <label for="relacion">Relación con la Institución *:</label>
                <select name="relacion" id="relacion" required>
                    <option value="">-- Seleccionar --</option>
                    <option value="Estudiante">Estudiante</option>
                    <option value="Tutor / Padre de familia">Tutor / Padre de familia</option>
                </select>
            </div>

            <div class="form-group">
                <label for="telefono">Teléfono / Celular de Contacto *:</label>
                <input type="text" name="telefono" id="telefono" required placeholder="Ej. 71234567">
            </div>

            <div class="form-group">
                <label for="motivo">Motivo de la Consulta / Breve detalle *:</label>
                <textarea name="motivo" id="motivo" rows="4" required placeholder="Escriba brevemente el motivo de su requerimiento..."></textarea>
            </div>

            <button type="submit" class="btn-submit" style="width: 100%;">Enviar Solicitud</button>
        </form>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>