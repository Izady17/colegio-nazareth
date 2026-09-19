<?php
$ruta_base = "../";
require_once "../includes/conexion.php";
require_once "../includes/funciones.php";

// Restringir acceso solo a estudiantes
verificarAcceso(['estudiante']);

$id_estudiante = $_SESSION['id_usuario'];

// Obtener información del curso del estudiante
$sql_curso = "SELECT p.id_paralelo, p.grado, p.letra 
              FROM estudiante_paralelo ep 
              INNER JOIN paralelos p ON ep.id_paralelo = p.id_paralelo 
              WHERE ep.id_estudiante = ? 
              LIMIT 1";
$stmt = mysqli_prepare($conexion, $sql_curso);
mysqli_stmt_bind_param($stmt, "i", $id_estudiante);
mysqli_stmt_execute($stmt);
$res_curso = mysqli_stmt_get_result($stmt);
$curso_info = mysqli_fetch_assoc($res_curso);
$id_paralelo_estudiante = $curso_info['id_paralelo'] ?? 0;

// Obtener avisos vigentes para este estudiante (generales, o específicos de su paralelo)
$sql_avisos = "SELECT DISTINCT a.id_aviso, a.titulo, a.mensaje, a.fecha_publicacion
               FROM avisos a
               LEFT JOIN avisos_paralelos ap ON a.id_aviso = ap.id_aviso
               WHERE a.destinatario IN ('estudiantes', 'todos')
                 AND (a.alcance = 'general' OR ap.id_paralelo = ?)
               ORDER BY a.fecha_publicacion DESC
               LIMIT 10";
$stmt_av = mysqli_prepare($conexion, $sql_avisos);
mysqli_stmt_bind_param($stmt_av, "i", $id_paralelo_estudiante);
mysqli_stmt_execute($stmt_av);
$avisos_vigentes = mysqli_fetch_all(mysqli_stmt_get_result($stmt_av), MYSQLI_ASSOC);

// Verificar si el estudiante tiene citaciones a entrevista pendientes (en riesgo)
$sql_entrevistas = "SELECT b.motivo_entrevista, b.fecha_entrevista_citada, b.promedio_proyectado,
                           m.nombre_materia, u.nombres AS docente_nombres, u.apellidos AS docente_apellidos, cd.trimestre
                    FROM boletin_trimestral b
                    INNER JOIN configuracion_dimensiones cd ON b.id_config = cd.id_config
                    INNER JOIN materias m ON cd.id_materia = m.id_materia
                    INNER JOIN usuarios u ON cd.id_docente = u.id_usuario
                    WHERE b.id_estudiante = ? AND b.entrevista_citada = 1
                    ORDER BY b.fecha_entrevista_citada DESC";
$stmt_ent = mysqli_prepare($conexion, $sql_entrevistas);
mysqli_stmt_bind_param($stmt_ent, "i", $id_estudiante);
mysqli_stmt_execute($stmt_ent);
$res_entrevistas = mysqli_stmt_get_result($stmt_ent);
$entrevistas_pendientes = mysqli_fetch_all($res_entrevistas, MYSQLI_ASSOC);

// Resumen académico real: para cada materia, se toma su configuración del trimestre más reciente
$resumen_materias = [];
if ($id_paralelo_estudiante) {
    $sql_configs = "SELECT cd.*, m.nombre_materia
                    FROM configuracion_dimensiones cd
                    INNER JOIN materias m ON cd.id_materia = m.id_materia
                    WHERE cd.id_paralelo = ?
                    AND cd.trimestre = (
                        SELECT MAX(cd2.trimestre) FROM configuracion_dimensiones cd2
                        WHERE cd2.id_paralelo = cd.id_paralelo AND cd2.id_materia = cd.id_materia
                    )
                    ORDER BY m.nombre_materia ASC";
    $stmt_cfg = mysqli_prepare($conexion, $sql_configs);
    mysqli_stmt_bind_param($stmt_cfg, "i", $id_paralelo_estudiante);
    mysqli_stmt_execute($stmt_cfg);
    $configs_materias = mysqli_fetch_all(mysqli_stmt_get_result($stmt_cfg), MYSQLI_ASSOC);

    foreach ($configs_materias as $cfg) {
        $datos = calcularCentralizadorTrimestral($conexion, $id_paralelo_estudiante, $cfg);
        foreach ($datos as $d) {
            if ($d['id_usuario'] == $id_estudiante) {
                $resumen_materias[] = [
                    'materia' => $cfg['nombre_materia'],
                    'total' => round($d['total'], 1)
                ];
                break;
            }
        }
    }
}
$promedio_general = !empty($resumen_materias)
    ? round(array_sum(array_column($resumen_materias, 'total')) / count($resumen_materias))
    : null;

require_once "../includes/header_panel.php";

$nombre_estudiante = $_SESSION['nombre_completo'] ?? 'Estudiante';
$primer_nombre = explode(' ', trim($nombre_estudiante))[0];
?>

<style>
  :root {
    --bg-main: #f3f6fc;
    --card-bg: #ffffff;
    --text-dark: #1e293b;
    --text-muted: #64748b;
    --border-color: #e2e8f0;
    --blue-primary: #2563eb;
    --green-primary: #10b981;
    --purple-primary: #8b5cf6;
    --orange-primary: #f97316;
    --red-primary: #ef4444;
    --brand-red: #800020;
  }

  /* Grid Principal (Izquierda + Lateral Derecho) */
  .estudiante-layout {
    display: grid;
    grid-template-columns: 1fr 310px;
    gap: 20px;
    align-items: start;
  }

  @media (max-width: 1024px) {
    .estudiante-layout {
      grid-template-columns: 1fr;
    }
  }

  /* Banner de Bienvenida Estilo Estudiante con Marca de Agua */
  .welcome-banner-estudiante {
    background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 55%, #c7d2fe 100%);
    border-radius: 18px;
    padding: 24px 28px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.03);
  }

  .welcome-banner-estudiante .motto-overlay {
    position: absolute;
    right: 25px;
    bottom: 15px;
    font-family: 'Georgia', serif;
    font-style: italic;
    font-size: 1.6rem;
    color: rgba(255, 255, 255, 0.45);
    pointer-events: none;
    font-weight: 700;
  }

  .welcome-banner-estudiante h2 {
    margin: 0 0 6px 0;
    color: #0f172a;
    font-size: 1.6rem;
    font-weight: 700;
  }

  .welcome-banner-estudiante p {
    margin: 0 0 14px 0;
    color: #475569;
    font-size: 0.92rem;
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
    font-weight: 600;
    box-shadow: 0 2px 4px rgba(0,0,0,0.04);
    display: flex;
    align-items: center;
    gap: 6px;
  }

  /* Grid de Tarjetas de Acceso Rápido */
  .grid-estudiante-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
  }

  .estudiante-card {
    background: var(--card-bg);
    border-radius: 16px;
    padding: 18px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    border: 1px solid var(--border-color);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    position: relative;
  }

  .estudiante-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.06);
  }

  .est-icon-box {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: #fff;
    margin-bottom: 12px;
  }

  .estudiante-card h3 {
    font-size: 0.95rem;
    margin: 0 0 4px 0;
    color: var(--text-dark);
    font-weight: 700;
  }

  .estudiante-card p {
    font-size: 0.78rem;
    color: var(--text-muted);
    margin: 0 0 14px 0;
    line-height: 1.35;
  }

  .btn-est-link {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    text-decoration: none;
    font-size: 0.8rem;
    margin-top: auto;
    align-self: flex-end;
  }

  /* Bloque Inferior Izquierda (Resumen Académico) */
  .academic-summary-card {
    background: var(--card-bg);
    border-radius: 16px;
    padding: 20px;
    border: 1px solid var(--border-color);
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    margin-bottom: 20px;
  }

  .academic-summary-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 18px;
  }

  .academic-summary-header h3 {
    margin: 0;
    font-size: 1rem;
    color: var(--text-dark);
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .summary-content-grid {
    display: grid;
    grid-template-columns: 180px 1fr;
    gap: 20px;
    align-items: center;
  }

  @media (max-width: 640px) {
    .summary-content-grid {
      grid-template-columns: 1fr;
    }
  }

  /* Donut Chart / Indicador Promedio */
  .avg-circle-box {
    text-align: center;
    padding: 10px;
  }

  .circle-chart {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: conic-gradient(var(--green-primary) calc(var(--percentage) * 1%), #e2e8f0 0);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    position: relative;
  }

  .circle-chart-inner {
    width: 92px;
    height: 92px;
    border-radius: 50%;
    background: #fff;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
  }

  .circle-chart-inner .lbl {
    font-size: 0.68rem;
    color: var(--text-muted);
    font-weight: 600;
  }

  .circle-chart-inner .val {
    font-size: 1.4rem;
    font-weight: 800;
    color: var(--text-dark);
  }

  /* Barras de materias */
  .subject-bars-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .subject-bar-item {
    display: flex;
    flex-direction: column;
    gap: 3px;
  }

  .subject-bar-meta {
    display: flex;
    justify-content: space-between;
    font-size: 0.82rem;
    color: var(--text-dark);
  }

  .subject-bar-track {
    background: #f1f5f9;
    border-radius: 6px;
    height: 7px;
    overflow: hidden;
  }

  .subject-bar-fill {
    height: 100%;
    border-radius: 6px;
  }

  /* Banner Inferior Sugerencia */
  .footer-suggestion-estudiante {
    background: #eff6ff;
    border: 1px solid #dbeafe;
    border-radius: 14px;
    padding: 14px 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.85rem;
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
    background: #1e293b;
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
    font-size: 0.98rem;
    color: var(--text-dark);
  }

  .user-profile-box p {
    margin: 0 0 10px 0;
    font-size: 0.8rem;
    color: var(--text-muted);
  }

  /* Elementos de Avisos/Eventos en Barra Lateral */
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
    margin: 3px 0;
  }

  .notice-date-side {
    font-size: 0.72rem;
    color: #94a3b8;
  }

  /* Alerta Citaciones */
  .alert-citacion-box {
    background-color: #fef2f2;
    border: 1px solid #fca5a5;
    border-radius: 14px;
    padding: 16px;
    margin-bottom: 20px;
  }

  .alert-citacion-box h3 {
    color: #b91c1c;
    margin: 0 0 10px 0;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 8px;
  }
</style>

<!-- ALERTA DE CITACIÓN A ENTREVISTA (SI EXISTE) -->
<?php if (!empty($entrevistas_pendientes)): ?>
    <div class="alert-citacion-box">
        <h3><i class="fa-solid fa-triangle-exclamation"></i> Citación a Entrevista Pendiente</h3>
        <?php foreach ($entrevistas_pendientes as $ent): ?>
            <div style="padding: 8px 0; border-top: 1px dashed #fca5a5;">
                <p style="margin: 0; color: #7f1d1d; font-size: 0.85rem;">
                    <strong><?php echo htmlspecialchars($ent['nombre_materia']); ?></strong> (Trimestre <?php echo (int)$ent['trimestre']; ?>) —
                    Docente: <?php echo htmlspecialchars($ent['docente_apellidos'] . " " . $ent['docente_nombres']); ?>
                </p>
                <?php if (!empty($ent['motivo_entrevista'])): ?>
                    <p style="margin: 3px 0 0; color: #991b1b; font-size: 0.8rem;"><em>Motivo: <?php echo htmlspecialchars($ent['motivo_entrevista']); ?></em></p>
                <?php endif; ?>
                <?php if (!empty($ent['fecha_entrevista_citada'])): ?>
                    <p style="margin: 3px 0 0; font-size: 0.75rem; color: #b91c1c;">
                        Fecha de registro: <?php echo date('d/m/Y', strtotime($ent['fecha_entrevista_citada'])); ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <p style="margin: 10px 0 0 0; font-weight: 600; color: #7f1d1d; font-size: 0.82rem;">
            Por favor, acércate a Dirección/Regencia o conversa con tu docente para coordinar.
        </p>
    </div>
<?php endif; ?>

<div class="estudiante-layout">

    <!-- COLUMNA PRINCIPAL -->
    <div class="estudiante-main-content">

        <!-- Banner de Bienvenida -->
        <div class="welcome-banner-estudiante">
            <div style="z-index: 2;">
                <h2>¡Hola, <?php echo htmlspecialchars($primer_nombre); ?>!</h2>
                <p>Bienvenido al portal académico de la U.E. Jesús de Nazareth.</p>
                <div class="welcome-tags">
                    <span class="tag-pill"><i class="fa-regular fa-calendar" style="color: var(--blue-primary);"></i> Hoy es <?php echo date('d/m/Y'); ?></span>
                    <span class="tag-pill"><i class="fa-solid fa-graduation-cap" style="color: var(--purple-primary);"></i> Curso: <?php echo $curso_info ? $curso_info['grado'] . "° '" . $curso_info['letra'] . "'" : "No asignado"; ?></span>
                </div>
            </div>
            <div class="motto-overlay">
                Formando mejores personas
            </div>
        </div>

        <!-- Módulos de Acceso Rápido -->
        <div class="grid-estudiante-cards">
            <!-- Horario de clases -->
            <div class="estudiante-card">
                <div>
                    <div class="est-icon-box" style="background: var(--purple-primary);"><i class="fa-solid fa-calendar-days"></i></div>
                    <h3>Horario de Clases</h3>
                    <p>Consulta la distribución semanal de tus materias.</p>
                </div>
                <a href="horario.php" class="btn-est-link" style="background: var(--purple-primary);"><i class="fa-solid fa-arrow-right"></i></a>
            </div>

            <!-- Tareas Pendientes -->
            <div class="estudiante-card">
                <div>
                    <div class="est-icon-box" style="background: var(--green-primary);"><i class="fa-solid fa-list-check"></i></div>
                    <h3>Tareas Pendientes</h3>
                    <p>Revisa las actividades asignadas por tus docentes.</p>
                </div>
                <a href="tareas.php" class="btn-est-link" style="background: var(--green-primary);"><i class="fa-solid fa-arrow-right"></i></a>
            </div>

            <!-- Recordatorios / Avisos -->
            <div class="estudiante-card">
                <div>
                    <div class="est-icon-box" style="background: var(--red-primary);"><i class="fa-solid fa-bullhorn"></i></div>
                    <h3>Recordatorios</h3>
                    <p>Avisos y comunicados importantes de tu curso.</p>
                </div>
                <a href="recordatorios.php" class="btn-est-link" style="background: var(--red-primary);"><i class="fa-solid fa-arrow-right"></i></a>
            </div>

            <!-- Mi Asistencia -->
            <div class="estudiante-card">
                <div>
                    <div class="est-icon-box" style="background: var(--orange-primary);"><i class="fa-solid fa-user-check"></i></div>
                    <h3>Mi Asistencia</h3>
                    <p>Revisa tu registro mensual de asistencias y licencias.</p>
                </div>
                <a href="asistencia.php" class="btn-est-link" style="background: var(--orange-primary);"><i class="fa-solid fa-arrow-right"></i></a>
            </div>
        </div>

        <!-- Resumen Académico Real -->
        <?php if (!empty($resumen_materias)): ?>
            <div class="academic-summary-card">
                <div class="academic-summary-header">
                    <h3><i class="fa-solid fa-chart-line" style="color: var(--blue-primary);"></i> Resumen Académico</h3>
                    <a href="notas.php" style="font-size: 0.8rem; color: var(--blue-primary); text-decoration: none; font-weight: 600;">Ver boletín completo →</a>
                </div>

                <div class="summary-content-grid">
                    <!-- Gráfico circular / promedio general -->
                    <div class="avg-circle-box">
                        <div class="circle-chart" style="--percentage: <?php echo $promedio_general ?? 0; ?>;">
                            <div class="circle-chart-inner">
                                <span class="lbl">Promedio</span>
                                <span class="val"><?php echo $promedio_general ?? 0; ?>%</span>
                            </div>
                        </div>
                    </div>

                    <!-- Lista de barras por materias -->
                    <div class="subject-bars-list">
                        <?php foreach (array_slice($resumen_materias, 0, 5) as $rm): ?>
                            <div class="subject-bar-item">
                                <div class="subject-bar-meta">
                                    <span><?php echo htmlspecialchars($rm['materia']); ?></span>
                                    <strong><?php echo $rm['total']; ?>%</strong>
                                </div>
                                <div class="subject-bar-track">
                                    <div class="subject-bar-fill" style="width: <?php echo min(100, $rm['total']); ?>%; background: <?php echo $rm['total'] >= 51 ? 'var(--green-primary)' : 'var(--red-primary)'; ?>;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Sugerencia / Mensaje motivacional inferior -->
        <div class="footer-suggestion-estudiante">
            <div style="display: flex; align-items: center; gap: 10px; color: #1e40af;">
                <i class="fa-solid fa-lightbulb" style="font-size: 1.2rem;"></i>
                <span><strong>¡No olvides!</strong> Revisa tus tareas pendientes y mantén al día tu asistencia para un mejor rendimiento.</span>
            </div>
        </div>

    </div>

    <!-- COLUMNA LATERAL DERECHA -->
    <div class="estudiante-sidebar-right">

        <!-- Perfil Estudiante -->
        <div class="side-panel-card">
            <div class="user-profile-box">
                <div class="user-avatar-lg">
                    <?php echo mb_strtoupper(mb_substr(trim($nombre_estudiante), 0, 1)); ?>
                </div>
                <h4><?php echo htmlspecialchars($nombre_estudiante); ?></h4>
                <p>Estudiante | C.I. <?php echo htmlspecialchars($_SESSION['ci'] ?? ''); ?></p>
                <div style="background: #f1f5f9; padding: 6px 10px; border-radius: 8px; font-size: 0.78rem; color: #475569; font-weight: 600;">
                    Curso: <?php echo $curso_info ? $curso_info['grado'] . " '" . $curso_info['letra'] . "'" : "Sin Asignar"; ?>
                </div>
            </div>
        </div>

        <!-- Avisos del Colegio -->
        <div class="side-panel-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <h4 style="margin: 0; font-size: 0.92rem; color: var(--text-dark);"><i class="fa-solid fa-bell" style="color: var(--brand-red);"></i> Avisos y Comunicados</h4>
            </div>

            <?php if (!empty($avisos_vigentes)): ?>
                <?php foreach (array_slice($avisos_vigentes, 0, 5) as $av): ?>
                    <div class="notice-item-side">
                        <div class="notice-title-side"><?php echo htmlspecialchars($av['titulo']); ?></div>
                        <div class="notice-desc-side"><?php echo htmlspecialchars(substr($av['mensaje'], 0, 75)) . '...'; ?></div>
                        <div class="notice-date-side"><i class="fa-regular fa-clock"></i> <?php echo date('d/m/Y', strtotime($av['fecha_publicacion'])); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align: center; color: var(--text-muted); font-size: 0.8rem; margin: 10px 0;">No hay avisos publicados para tu curso.</p>
            <?php endif; ?>
        </div>

    </div>

</div>

<?php require_once "../includes/footer_panel.php"; ?>