<?php
session_start();
$ruta_base = "../";

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: " . $ruta_base . "auth/login.php");
    exit();
}

require_once "../includes/conexion.php";
require_once "../includes/header_panel.php";

$nombre_docente = $_SESSION['nombre_completo'] ?? $_SESSION['nombre'] ?? $_SESSION['nombres'] ?? 'Docente';
$id_docente = $_SESSION['id_usuario'];

// Avisos vigentes para este docente
$sql_avisos = "SELECT DISTINCT a.id_aviso, a.titulo, a.mensaje, a.fecha_publicacion
               FROM avisos a
               LEFT JOIN avisos_paralelos ap ON a.id_aviso = ap.id_aviso
               LEFT JOIN horarios h ON h.id_paralelo = ap.id_paralelo AND h.id_docente = ?
               WHERE a.destinatario IN ('docentes', 'todos')
                 AND (a.alcance = 'general' OR h.id_horario IS NOT NULL)
               ORDER BY a.fecha_publicacion DESC
               LIMIT 10";
$stmt_av = mysqli_prepare($conexion, $sql_avisos);
mysqli_stmt_bind_param($stmt_av, "i", $id_docente);
mysqli_stmt_execute($stmt_av);
$avisos_vigentes = mysqli_fetch_all(mysqli_stmt_get_result($stmt_av), MYSQLI_ASSOC);

// Detectar la jornada actual del docente (curso con el que tiene clase justo ahora)
$dias_map = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => null];
$hoy_dia = $dias_map[(int)date('N')];
$hora_actual = date('H:i:s');
$jornada_actual = null;

if ($hoy_dia) {
    $sql_actual = "SELECT h.id_paralelo, h.id_materia, p.grado, p.letra, m.nombre_materia
                   FROM horarios h
                   INNER JOIN paralelos p ON h.id_paralelo = p.id_paralelo
                   INNER JOIN materias m ON h.id_materia = m.id_materia
                   WHERE h.id_docente = ? AND h.dia = ? AND ? BETWEEN h.hora_inicio AND h.hora_fin
                   LIMIT 1";
    $stmt_act = mysqli_prepare($conexion, $sql_actual);
    mysqli_stmt_bind_param($stmt_act, "iss", $id_docente, $hoy_dia, $hora_actual);
    mysqli_stmt_execute($stmt_act);
    $jornada_actual = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_act));
    mysqli_stmt_close($stmt_act);
}

$id_paralelo = $jornada_actual['id_paralelo'] ?? 0;
$id_materia = $jornada_actual['id_materia'] ?? 0;

// Trimestre actual real, según las fechas definidas por admin (Gestión Académica)
$hoy_fecha = date('Y-m-d');
$sql_tri = "SELECT t.numero FROM trimestres t
            INNER JOIN gestiones g ON t.id_gestion = g.id_gestion
            WHERE g.estado = 'Activa' AND t.fecha_inicio <= ? AND t.fecha_fin >= ?
            LIMIT 1";
$stmt_tri = mysqli_prepare($conexion, $sql_tri);
mysqli_stmt_bind_param($stmt_tri, "ss", $hoy_fecha, $hoy_fecha);
mysqli_stmt_execute($stmt_tri);
$row_tri = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_tri));
mysqli_stmt_close($stmt_tri);
$trimestre = $row_tri['numero'] ?? null;

// Materias que imparte (referencia fija, sin importar el horario actual)
$sql_mat = "SELECT DISTINCT m.nombre_materia
            FROM horarios h INNER JOIN materias m ON h.id_materia = m.id_materia
            WHERE h.id_docente = ? ORDER BY m.nombre_materia ASC";
$stmt_mat = mysqli_prepare($conexion, $sql_mat);
mysqli_stmt_bind_param($stmt_mat, "i", $id_docente);
mysqli_stmt_execute($stmt_mat);
$materias_docente = array_column(mysqli_fetch_all(mysqli_stmt_get_result($stmt_mat), MYSQLI_ASSOC), 'nombre_materia');
mysqli_stmt_close($stmt_mat);
?>

<style>
  :root {
    --bg-main: #f3f6fc;
    --card-bg: #ffffff;
    --text-dark: #1e293b;
    --text-muted: #64748b;
    --border-color: #e2e8f0;
    --blue-primary: #1d4ed8;
    --green-primary: #10b981;
    --purple-primary: #8b5cf6;
    --orange-primary: #f97316;
    --red-primary: #ef4444;
    --brand-red: #800020;
  }

  /* Grid Principal (Izquierda + Lateral Derecho) */
  .docente-layout {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 20px;
    align-items: start;
  }

  @media (max-width: 1024px) {
    .docente-layout {
      grid-template-columns: 1fr;
    }
  }

  /* Banner de Bienvenida Estilo Docente */
  .welcome-banner-docente {
    background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 60%, #e0e7ff 100%);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.03);
  }

  .welcome-banner-docente h2 {
    margin: 0 0 6px 0;
    color: #0f172a;
    font-size: 1.5rem;
    font-weight: 700;
  }

  .welcome-banner-docente p {
    margin: 0 0 14px 0;
    color: #475569;
    font-size: 0.9rem;
  }

  .welcome-tags {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
  }

  .tag-pill {
    background: #ffffff;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.8rem;
    color: #334155;
    font-weight: 500;
    box-shadow: 0 2px 4px rgba(0,0,0,0.04);
    display: flex;
    align-items: center;
    gap: 6px;
  }

  /* Grid de Funciones / Módulos Docentes */
  .grid-docente-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
  }

  .docente-card {
    background: var(--card-bg);
    border-radius: 16px;
    padding: 18px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    border: 1px solid var(--border-color);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
  }

  .docente-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.06);
  }

  .doc-icon-box {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    color: #fff;
    margin-bottom: 12px;
  }

  .docente-card h3 {
    font-size: 0.98rem;
    margin: 0 0 4px 0;
    color: var(--text-dark);
  }

  .docente-card p {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin: 0 0 14px 0;
    line-height: 1.3;
  }

  .btn-doc-link {
    width: 100%;
    padding: 8px 12px;
    border-radius: 8px;
    border: none;
    color: #fff;
    font-weight: 600;
    font-size: 0.82rem;
    text-align: center;
    text-decoration: none;
    display: inline-block;
    box-sizing: border-box;
  }

  /* Bloque Horizontal Inferior (Resumen, Acceso Rápido, Actividad) */
  .bottom-info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 16px;
    margin-bottom: 20px;
  }

  @media (max-width: 900px) {
    .bottom-info-grid {
      grid-template-columns: 1fr;
    }
  }

  .sub-card {
    background: var(--card-bg);
    border-radius: 16px;
    padding: 16px;
    border: 1px solid var(--border-color);
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
  }

  .sub-card-title {
    font-size: 0.92rem;
    font-weight: 700;
    color: var(--text-dark);
    margin: 0 0 14px 0;
    display: flex;
    align-items: center;
    gap: 6px;
  }

  /* Accesos rápidos botones pequeños */
  .shortcuts-mini-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 8px;
  }

  .btn-shortcut-mini {
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    border-radius: 10px;
    padding: 10px 4px;
    text-align: center;
    text-decoration: none;
    color: var(--text-dark);
    font-size: 0.72rem;
    font-weight: 600;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
  }

  .btn-shortcut-mini:hover {
    background: #f1f5f9;
  }

  /* Lateral Derecho */
  .side-panel-card {
    background: var(--card-bg);
    border-radius: 16px;
    padding: 20px;
    border: 1px solid var(--border-color);
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    margin-bottom: 20px;
  }

  .user-profile-box {
    text-align: center;
  }

  .user-avatar-lg {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: var(--brand-red);
    color: #fff;
    font-size: 1.5rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 10px auto;
  }

  .user-profile-box h4 {
    margin: 0 0 2px 0;
    font-size: 1rem;
    color: var(--text-dark);
  }

  .user-profile-box p {
    margin: 0 0 10px 0;
    font-size: 0.8rem;
    color: var(--text-muted);
  }

  /* Lista de avisos/mensajes laterales */
  .notice-item-side {
    border-bottom: 1px solid var(--border-color);
    padding: 10px 0;
  }

  .notice-item-side:last-child {
    border-bottom: none;
  }

  .notice-title-side {
    font-weight: 600;
    font-size: 0.82rem;
    color: var(--text-dark);
  }

  .notice-desc-side {
    font-size: 0.78rem;
    color: var(--text-muted);
    margin: 2px 0;
  }

  .notice-date-side {
    font-size: 0.72rem;
    color: #94a3b8;
  }

  /* Banner Sugerencia */
  .footer-suggestion-docente {
    background: #eff6ff;
    border: 1px solid #dbeafe;
    border-radius: 14px;
    padding: 14px 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.85rem;
  }
</style>

<div class="docente-layout">

    <!-- COLUMNA PRINCIPAL -->
    <div class="docente-main-content">

        <!-- Banner de Bienvenida -->
        <div class="welcome-banner-docente">
            <div>
                <h2>¡Hola, <?php echo htmlspecialchars($nombre_docente); ?>!</h2>
                <p>Bienvenido al panel docente de la U.E. Jesús de Nazareth.</p>
                                <div class="welcome-tags">
                    <span class="tag-pill">📅 <?php echo $hoy_dia ?? 'Domingo'; ?>, <?php echo date('d/m/Y'); ?></span>

                    <span class="tag-pill" style="background: <?php echo $trimestre ? '#dbeafe' : '#f1f5f9'; ?>; color: <?php echo $trimestre ? '#1d4ed8' : '#64748b'; ?>;">
                        🗓️ <?php echo $trimestre ? "Trimestre $trimestre" : 'Sin trimestre activo'; ?>
                    </span>

                    <?php if ($jornada_actual): ?>
                        <span class="tag-pill" style="background: #fef3c7; color: #b45309;">
                            🟡 Tu curso ahora: <strong><?php echo $jornada_actual['grado']; ?>° "<?php echo $jornada_actual['letra']; ?>"</strong>
                        </span>
                    <?php else: ?>
                        <span class="tag-pill">⚪ Fuera de horario de clases</span>
                    <?php endif; ?>

                    <?php if (!empty($materias_docente)): ?>
                        <span class="tag-pill" title="Materias que impartís">
                            📚 <?php echo htmlspecialchars(implode(', ', $materias_docente)); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Opciones Rápidas (Tarjetas Principales) -->
        <div class="grid-docente-cards">
            <div class="docente-card">
                <div>
                    <div class="doc-icon-box" style="background: var(--blue-primary);"><i class="fa-solid fa-calendar-days"></i></div>
                    <h3>Horario de Clases</h3>
                    <p>Consulta tu horario semanal y próximas clases.</p>
                </div>
                <a href="horarios.php" class="btn-doc-link" style="background: var(--blue-primary);">Ver Horario →</a>
            </div>

            <div class="docente-card">
                <div>
                    <div class="doc-icon-box" style="background: var(--green-primary);"><i class="fa-solid fa-graduation-cap"></i></div>
                    <h3>Mis Cursos</h3>
                    <p>Accede a tus materias, contenidos y listas.</p>
                </div>
                <a href="actividades.php" class="btn-doc-link" style="background: var(--green-primary);">Ver Cursos →</a>
            </div>

            <div class="docente-card">
                <div>
                    <div class="doc-icon-box" style="background: var(--purple-primary);"><i class="fa-solid fa-pen-to-square"></i></div>
                    <h3>Registro de Notas</h3>
                    <p>Ingresa y gestiona las calificaciones de tus alumnos.</p>
                </div>
                <a href="actividades.php?id_paralelo=<?php echo $id_paralelo; ?>&id_materia=<?php echo $id_materia; ?>&trimestre=<?php echo $trimestre; ?>" class="btn-doc-link" style="background: var(--purple-primary);">Ingresar Notas →</a>
            </div>

            <div class="docente-card">
                <div>
                    <div class="doc-icon-box" style="background: var(--orange-primary);"><i class="fa-solid fa-clipboard-check"></i></div>
                    <h3>Asistencia</h3>
                    <p>Registra la asistencia diaria de tus estudiantes.</p>
                </div>
                <a href="asistencia.php" class="btn-doc-link" style="background: var(--orange-primary);">Registrar →</a>
            </div>

            <div class="docente-card">
                <div>
                    <div class="doc-icon-box" style="background: var(--red-primary);"><i class="fa-solid fa-sliders"></i></div>
                    <h3>Ponderaciones</h3>
                    <p>Define los porcentajes de evaluación y dimensiones.</p>
                </div>
                <a href="configurar_dimensiones.php" class="btn-doc-link" style="background: var(--red-primary);">Configurar →</a>
            </div>

            <div class="docente-card">
                <div>
                    <div class="doc-icon-box" style="background: var(--blue-primary);"><i class="fa-solid fa-paper-plane"></i></div>
                    <h3>Enviar Recordatorio</h3>
                    <p>Publica un aviso o comunicado para tus cursos.</p>
                </div>
                <a href="enviar-recordatorio.php" class="btn-doc-link" style="background: var(--blue-primary);">Publicar →</a>
            </div>
        </div>

        <!-- Secciones de Resumen, Accesos Rápidos y Actividad -->
        <div class="bottom-info-grid">
            <!-- Resumen de tu jornada -->
            <div class="sub-card">
                <div class="sub-card-title"><i class="fa-solid fa-chart-pie" style="color: var(--blue-primary);"></i> Resumen Jornada</div>
                <div style="text-align: center; padding: 10px 0;">
                    <div style="font-size: 1.4rem; font-weight: 700; color: var(--text-dark);">Clases hoy</div>
                    <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">
                        <?php echo $jornada_actual ? 'En desarrollo' : 'Sin clase activa ahora'; ?>
                    </div>
                </div>
            </div>

            <!-- Accesos rápidos -->
            <div class="sub-card">
                <div class="sub-card-title"><i class="fa-solid fa-bolt" style="color: var(--orange-primary);"></i> Accesos Rápidos</div>
                <div class="shortcuts-mini-grid">
                    <a href="actividades.php" class="btn-shortcut-mini">
                        <i class="fa-solid fa-list-check"></i>
                        <span>Actividades</span>
                    </a>
                    <a href="asistencia.php" class="btn-shortcut-mini">
                        <i class="fa-solid fa-user-check"></i>
                        <span>Asistencia</span>
                    </a>
                    <a href="horarios.php" class="btn-shortcut-mini">
                        <i class="fa-solid fa-calendar"></i>
                        <span>Horarios</span>
                    </a>
                </div>
            </div>

            <!-- Actividad Reciente -->
            <div class="sub-card">
                <div class="sub-card-title"><i class="fa-solid fa-clock-rotate-left" style="color: var(--purple-primary);"></i> Actividad Reciente</div>
                <div style="font-size: 0.8rem; color: var(--text-muted);">
                    <p style="margin: 4px 0;">• Registro de asistencia actualizado.</p>
                    <p style="margin: 4px 0;">• Consulta de horarios realizada.</p>
                </div>
            </div>
        </div>

        <!-- Sugerencia -->
        <div class="footer-suggestion-docente">
            <div style="display: flex; align-items: center; gap: 10px; color: #1e40af;">
                <i class="fa-solid fa-lightbulb" style="font-size: 1.2rem;"></i>
                <span><strong>¡Tu labor hace la diferencia!</strong> Gracias por ser parte del crecimiento de nuestros estudiantes.</span>
            </div>
        </div>

    </div>

    <!-- COLUMNA LATERAL DERECHA -->
    <div class="docente-sidebar-right">

        <!-- Tu Perfil -->
        <div class="side-panel-card">
            <div class="user-profile-box">
                <div class="user-avatar-lg">
                    <?php echo mb_strtoupper(mb_substr(trim($nombre_docente), 0, 1)); ?>
                </div>
                <h4><?php echo htmlspecialchars($nombre_docente); ?></h4>
                <p>Docente / Tutor</p>
                <a href="<?php echo $ruta_base; ?>index.php" class="btn-doc-link" style="background: #f1f5f9; color: #334155; font-weight: 600;">Ver Mi Perfil</a>
            </div>
        </div>

        <!-- Avisos y Recordatorios -->
        <div class="side-panel-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <h4 style="margin: 0; font-size: 0.95rem; color: var(--text-dark);"><i class="fa-solid fa-bell" style="color: var(--brand-red);"></i> Avisos Recientes</h4>
            </div>

            <?php if (!empty($avisos_vigentes)): ?>
                <?php foreach (array_slice($avisos_vigentes, 0, 4) as $av): ?>
                    <div class="notice-item-side">
                        <div class="notice-title-side"><?php echo htmlspecialchars($av['titulo']); ?></div>
                        <div class="notice-desc-side"><?php echo htmlspecialchars(substr($av['mensaje'], 0, 70)) . '...'; ?></div>
                        <div class="notice-date-side"><i class="fa-regular fa-clock"></i> <?php echo date('d/m/Y', strtotime($av['fecha_publicacion'])); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align: center; color: var(--text-muted); font-size: 0.8rem; margin: 10px 0;">No hay avisos pendientes.</p>
            <?php endif; ?>
        </div>

    </div>

</div>

<?php require_once "../includes/footer_panel.php"; ?>