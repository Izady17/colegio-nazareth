<?php
$ruta_base = "../";
require_once "../includes/conexion.php";
require_once "../includes/funciones.php";
require_once "../includes/header.php";

// Función auxiliar para formatear la fecha
function fechaFormateadaEs($fecha) {
    if (!$fecha) return '';
    return date('d/m/Y', strtotime($fecha));
}

// Obtener todas las actividades activas
$sql_actividades = "SELECT * FROM actividades WHERE estado = 1 ORDER BY fecha DESC";
$res_actividades = mysqli_query($conexion, $sql_actividades);

$actividades_array = [];
$contadores = [
    'Todas' => 0,
    'Desfiles' => 0,
    'Actos Cívicos' => 0,
    'Eventos Culturales' => 0,
    'Otras Actividades' => 0
];

if ($res_actividades && mysqli_num_rows($res_actividades) > 0) {
    while ($act = mysqli_fetch_assoc($res_actividades)) {
        $actividades_array[] = $act;
        $contadores['Todas']++;
        
        $cat = $act['categoria'] ?? 'Otras Actividades';
        if (isset($contadores[$cat])) {
            $contadores[$cat]++;
        } else {
            $contadores['Otras Actividades']++;
        }
    }
}

// Filtrar las próximas actividades para la barra lateral (3 más cercanas/pendientes)
$proximas_actividades = array_filter($actividades_array, function($item) {
    $est = $item['estado_proceso'] ?? 'Pendiente';
    return $est === 'Pendiente' || $est === 'En proceso';
});
// Ordenar de más cercana a más lejana
usort($proximas_actividades, function($a, $b) {
    return strtotime($a['fecha']) - strtotime($b['fecha']);
});
$proximas_actividades = array_slice($proximas_actividades, 0, 3);
?>

<style>
:root {
    --color-primary-dark: #7f1d1d;
    --color-bg-light: #f8fafc;
    --border-color-soft: #e2e8f0;
}

/* Banner Superior */
.hero-actividades {
    background: #7f1d1d;
    color: white;
    padding: 30px;
    border-radius: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.hero-actividades-title {
    display: flex;
    align-items: center;
    gap: 15px;
}
.hero-icon-box {
    background: rgba(255,255,255,0.15);
    padding: 12px 16px;
    border-radius: 10px;
    font-size: 1.8rem;
}
.hero-quote {
    text-align: right;
    max-width: 320px;
    font-size: 0.85rem;
    font-style: italic;
    opacity: 0.9;
    border-left: 2px solid rgba(255,255,255,0.3);
    padding-left: 12px;
}

/* Pestañas de Categoría */
.tabs-categorias {
    display: flex;
    gap: 10px;
    overflow-x: auto;
    margin-bottom: 20px;
    padding-bottom: 5px;
}
.tab-cat-btn {
    background: #fff;
    border: 1px solid var(--border-color-soft);
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 0.88rem;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
    transition: all 0.2s;
}
.tab-cat-btn:hover { background: #f1f5f9; }
.tab-cat-btn.active {
    background: #7f1d1d;
    color: white;
    border-color: #7f1d1d;
}
.tab-badge {
    background: #f1f5f9;
    color: #475569;
    padding: 2px 7px;
    border-radius: 10px;
    font-size: 0.75rem;
}
.tab-cat-btn.active .tab-badge {
    background: rgba(255,255,255,0.2);
    color: white;
}

/* Barra de Filtros y Búsqueda */
.bar-filtros {
    display: flex;
    gap: 15px;
    margin-bottom: 25px;
    flex-wrap: wrap;
}
.search-input-wrapper {
    flex: 1;
    min-width: 280px;
    position: relative;
}
.search-input-wrapper input {
    width: 100%;
    padding: 10px 15px 10px 38px;
    border: 1px solid var(--border-color-soft);
    border-radius: 8px;
    font-size: 0.9rem;
    background: #fff;
}
.search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
}
.select-filtro {
    padding: 10px 15px;
    border: 1px solid var(--border-color-soft);
    border-radius: 8px;
    background: #fff;
    font-size: 0.88rem;
    color: #334155;
    cursor: pointer;
}

/* Layout Principal */
.layout-actividades {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 25px;
}
@media (max-width: 900px) {
    .layout-actividades { grid-template-columns: 1fr; }
}

/* Tarjeta de Actividad */
.tarjeta-actividad {
    background: #fff;
    border: 1px solid var(--border-color-soft);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 16px;
    display: flex;
    gap: 18px;
    align-items: center;
    position: relative;
    transition: box-shadow 0.2s, transform 0.2s;
}
.tarjeta-actividad:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    transform: translateY(-2px);
}
.actividad-img {
    width: 130px;
    height: 95px;
    object-fit: cover;
    border-radius: 8px;
    flex-shrink: 0;
}
.actividad-body {
    flex: 1;
}
.actividad-titulo {
    font-size: 1.05rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 6px 0;
}
.actividad-desc {
    font-size: 0.85rem;
    color: #64748b;
    margin: 0 0 10px 0;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.actividad-tags {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.tag-item {
    font-size: 0.75rem;
    padding: 3px 8px;
    border-radius: 6px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.tag-desfile { background: #fee2e2; color: #991b1b; }
.tag-civico { background: #e0e7ff; color: #3730a3; }
.tag-cultural { background: #fef3c7; color: #92400e; }
.tag-academico { background: #dbeafe; color: #1e40af; }
.tag-deportivo { background: #dcfce7; color: #166534; }
.tag-general { background: #f1f5f9; color: #475569; }

.actividad-meta {
    text-align: right;
    min-width: 140px;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 6px;
}
.meta-info {
    font-size: 0.8rem;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 4px;
}

/* Badges de Estado */
.status-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.status-realizado { background: #dcfce7; color: #15803d; }
.status-proceso { background: #e0f2fe; color: #0369a1; }
.status-pendiente { background: #fef3c7; color: #b45309; }

.arrow-link {
    color: #94a3b8;
    font-size: 1.1rem;
    margin-left: 10px;
}

/* Sidebar Widgets */
.sidebar-widget {
    background: #fff;
    border: 1px solid var(--border-color-soft);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
}
.widget-title {
    font-size: 0.98rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Calendario */
.mini-calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    font-weight: 700;
    font-size: 0.9rem;
    color: #1e293b;
}
.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
    text-align: center;
    font-size: 0.78rem;
}
.cal-day-head {
    font-weight: 600;
    color: #94a3b8;
    padding: 4px 0;
}
.cal-day {
    padding: 6px 0;
    border-radius: 50%;
    color: #334155;
}
.cal-day.has-event-desfile { background: #ef4444; color: white; font-weight: bold; }
.cal-day.has-event-civico { background: #3b82f6; color: white; font-weight: bold; }
.cal-day.has-event-cultural { background: #f59e0b; color: white; font-weight: bold; }
.cal-day.has-event-deportivo { background: #10b981; color: white; font-weight: bold; }

.calendar-legend {
    margin-top: 15px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    font-size: 0.72rem;
    color: #64748b;
}
.legend-item {
    display: flex;
    align-items: center;
    gap: 6px;
}
.legend-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

/* Próximas Actividades List */
.proximas-item {
    display: flex;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px border-subtle #f1f5f9;
    align-items: center;
}
.proximas-item:last-child { border-bottom: none; }
.date-box {
    background: #eff6ff;
    color: #1d4ed8;
    border-radius: 8px;
    padding: 6px 10px;
    text-align: center;
    min-width: 50px;
}
.date-box-day { font-size: 1.1rem; font-weight: 800; line-height: 1; }
.date-box-month { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; }
</style>

<!-- Banner Superior -->
<div class="hero-actividades">
    <div class="hero-actividades-title">
        <div class="hero-icon-box">📅</div>
        <div>
            <h1 style="font-size: 1.6rem; margin: 0; color: white;">Actividades Académicas</h1>
            <p style="margin: 4px 0 0 0; font-size: 0.9rem; opacity: 0.9;">Eventos, desfiles, actos cívicos y actividades que fortalecen nuestra formación integral.</p>
        </div>
    </div>
    <div class="hero-quote">
        "La educación no solo se vive en las aulas, también en cada actividad que nos une como comunidad."
        <br><strong style="font-size: 0.75rem;">U.E. Jesús de Nazareth</strong>
    </div>
</div>

<!-- Pestañas de Categorías -->
<div class="tabs-categorias">
    <button class="tab-cat-btn active" onclick="filtrarTab('Todas', this)">
        📅 Todas <span class="tab-badge"><?php echo $contadores['Todas']; ?></span>
    </button>
    <button class="tab-cat-btn" onclick="filtrarTab('Desfiles', this)">
        👥 Desfiles <span class="tab-badge"><?php echo $contadores['Desfiles']; ?></span>
    </button>
    <button class="tab-cat-btn" onclick="filtrarTab('Actos Cívicos', this)">
        🎓 Actos Cívicos <span class="tab-badge"><?php echo $contadores['Actos Cívicos']; ?></span>
    </button>
    <button class="tab-cat-btn" onclick="filtrarTab('Eventos Culturales', this)">
        🎭 Eventos Culturales <span class="tab-badge"><?php echo $contadores['Eventos Culturales']; ?></span>
    </button>
    <button class="tab-cat-btn" onclick="filtrarTab('Otras Actividades', this)">
        🏢 Otras Actividades <span class="tab-badge"><?php echo $contadores['Otras Actividades']; ?></span>
    </button>
</div>

<!-- Barra de Búsqueda y Filtros -->
<div class="bar-filtros">
    <div class="search-input-wrapper">
        <span class="search-icon">🔍</span>
        <input type="text" id="inputBuscar" placeholder="Buscar por nombre de la actividad, fecha o descripción..." onkeyup="aplicarFiltros()">
    </div>
    <select id="selectMes" class="select-filtro" onchange="aplicarFiltros()">
        <option value="">Todos los meses</option>
        <option value="01">Enero</option>
        <option value="02">Febrero</option>
        <option value="03">Marzo</option>
        <option value="04">Abril</option>
        <option value="05">Mayo</option>
        <option value="06">Junio</option>
        <option value="07">Julio</option>
        <option value="08">Agosto</option>
        <option value="09">Septiembre</option>
        <option value="10">Octubre</option>
        <option value="11">Noviembre</option>
        <option value="12">Diciembre</option>
    </select>
    <select id="selectCategoria" class="select-filtro" onchange="aplicarFiltros()">
        <option value="">Todas las categorías</option>
        <option value="Desfiles">Desfiles</option>
        <option value="Actos Cívicos">Actos Cívicos</option>
        <option value="Eventos Culturales">Eventos Culturales</option>
        <option value="Otras Actividades">Otras Actividades</option>
    </select>
</div>

<!-- Layout Principal -->
<div class="layout-actividades">
<!-- Lista de Actividades (Izquierda) -->
<div>
    <div id="contenedorActividades">
        <?php if (!empty($actividades_array)): ?>
            <?php foreach ($actividades_array as $act): ?>
                <?php 
                    $categoria = $act['categoria'] ?? 'Otras Actividades';
                    $estado_proceso = $act['estado_proceso'] ?? 'Realizado';
                    $lugar = $act['lugar'] ?? 'Unidad Educativa';
                    
                    // Definir base de ruta relativa para el HTML (navegador)
                    $base_path = isset($ruta_base) ? $ruta_base : '../';
                    
                    // Comprobar la existencia del archivo en el sistema de archivos del servidor
                    $ruta_fisica = __DIR__ . '/../assets/img/uploads/actividades/' . ($act['imagen'] ?? '');

                    if (!empty($act['imagen']) && file_exists($ruta_fisica)) {
                        $img_src = $base_path . 'assets/img/uploads/actividades/' . $act['imagen'];
                    } else {
                        // Imagen por defecto si no existe la foto subida
                        $img_src = $base_path . 'assets/img/frontis.jpg'; 
                    }

                    $mes_num = !empty($act['fecha']) ? date('m', strtotime($act['fecha'])) : '01';
                    
                    // Determinar clase de badge de estado
                    $badge_class = 'status-realizado';
                    $status_icon = '✔';
                    if ($estado_proceso === 'En proceso') {
                        $badge_class = 'status-proceso';
                        $status_icon = '🔄';
                    } elseif ($estado_proceso === 'Pendiente') {
                        $badge_class = 'status-pendiente';
                        $status_icon = '🕒';
                    }

                    // Tags
                    $tags_str = $act['tags'] ?? $categoria;
                    $tags_list = explode(',', $tags_str);
                ?>
                <article class="tarjeta-actividad item-actividad" 
                         data-categoria="<?php echo htmlspecialchars($categoria); ?>"
                         data-mes="<?php echo htmlspecialchars($mes_num); ?>"
                         data-texto="<?php echo htmlspecialchars(strtolower(($act['titulo'] ?? '') . ' ' . ($act['descripcion'] ?? ''))); ?>">
                    
                    <img src="<?php echo htmlspecialchars($img_src); ?>" alt="Actividad" class="actividad-img" onerror="this.src='https://via.placeholder.com/130x95?text=Actividad'">
                    
                    <div class="actividad-body">
                        <h3 class="actividad-titulo"><?php echo htmlspecialchars($act['titulo'] ?? ''); ?></h3>
                        <p class="actividad-desc"><?php echo htmlspecialchars($act['descripcion'] ?? ''); ?></p>
                        <div class="actividad-tags">
                            <?php foreach ($tags_list as $tag): ?>
                                <?php 
                                    $tag_clean = trim($tag);
                                    $tag_class = 'tag-general';
                                    if (stripos($tag_clean, 'desfile') !== false) $tag_class = 'tag-desfile';
                                    elseif (stripos($tag_clean, 'cívico') !== false || stripos($tag_clean, 'civico') !== false) $tag_class = 'tag-civico';
                                    elseif (stripos($tag_clean, 'cultural') !== false || stripos($tag_clean, 'folklore') !== false) $tag_class = 'tag-cultural';
                                    elseif (stripos($tag_clean, 'académico') !== false || stripos($tag_clean, 'academico') !== false) $tag_class = 'tag-academico';
                                    elseif (stripos($tag_clean, 'deport') !== false) $tag_class = 'tag-deportivo';
                                ?>
                                <span class="tag-item <?php echo $tag_class; ?>">👤 <?php echo htmlspecialchars($tag_clean); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="actividad-meta">
                        <div class="meta-info">📅 <?php echo isset($act['fecha']) && function_exists('fechaFormateadaEs') ? fechaFormateadaEs($act['fecha']) : ($act['fecha'] ?? ''); ?></div>
                        <div class="meta-info">📍 <?php echo htmlspecialchars($lugar); ?></div>
                        <span class="status-badge <?php echo $badge_class; ?>">
                            <?php echo $status_icon . ' ' . htmlspecialchars($estado_proceso); ?>
                        </span>
                    </div>

                    <span class="arrow-link">❯</span>
                </article>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 40px; background: #fff; border-radius: 12px; color: #64748b;">
                <p>No hay actividades registradas en este momento.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
    <!-- Sidebar Lateral (Derecha) -->
    <div>
        <!-- Widget 1: Calendario de Actividades -->
        <div class="sidebar-widget">
            <div class="widget-title">📅 Calendario de Actividades</div>
            
            <div class="mini-calendar-header">
                <span>&lt;</span>
                <span>Septiembre 2025</span>
                <span>&gt;</span>
            </div>

            <div class="calendar-grid">
                <div class="cal-day-head">L</div>
                <div class="cal-day-head">M</div>
                <div class="cal-day-head">M</div>
                <div class="cal-day-head">J</div>
                <div class="cal-day-head">V</div>
                <div class="cal-day-head">S</div>
                <div class="cal-day-head">D</div>

                <div class="cal-day">1</div>
                <div class="cal-day">2</div>
                <div class="cal-day">3</div>
                <div class="cal-day">4</div>
                <div class="cal-day has-event-cultural">5</div>
                <div class="cal-day">6</div>
                <div class="cal-day">7</div>

                <div class="cal-day">8</div>
                <div class="cal-day">9</div>
                <div class="cal-day">10</div>
                <div class="cal-day">11</div>
                <div class="cal-day">12</div>
                <div class="cal-day">13</div>
                <div class="cal-day">14</div>

                <div class="cal-day">15</div>
                <div class="cal-day has-event-desfile">16</div>
                <div class="cal-day">17</div>
                <div class="cal-day">18</div>
                <div class="cal-day">19</div>
                <div class="cal-day">20</div>
                <div class="cal-day">21</div>

                <div class="cal-day has-event-deportivo">22</div>
                <div class="cal-day">23</div>
                <div class="cal-day">24</div>
                <div class="cal-day">25</div>
                <div class="cal-day">26</div>
                <div class="cal-day">27</div>
                <div class="cal-day">28</div>

                <div class="cal-day">29</div>
                <div class="cal-day">30</div>
            </div>

            <div class="calendar-legend">
                <div class="legend-item"><span class="legend-dot" style="background:#ef4444;"></span> Desfile</div>
                <div class="legend-item"><span class="legend-dot" style="background:#3b82f6;"></span> Acto Cívico</div>
                <div class="legend-item"><span class="legend-dot" style="background:#f59e0b;"></span> Evento Cultural</div>
                <div class="legend-item"><span class="legend-dot" style="background:#10b981;"></span> Deportivo</div>
                <div class="legend-item" style="grid-column: span 2;"><span class="legend-dot" style="background:#6b7280;"></span> Otro</div>
            </div>
        </div>

        <!-- Widget 2: Próximas Actividades -->
        <div class="sidebar-widget">
            <div class="widget-title">📢 Próximas actividades</div>
            
            <?php if (!empty($proximas_actividades)): ?>
                <?php foreach ($proximas_actividades as $p_act): ?>
                    <?php 
                        $d_num = date('d', strtotime($p_act['fecha']));
                        $m_nom = substr(date('F', strtotime($p_act['fecha'])), 0, 3);
                        
                        // Traducción manual simple de mes
                        $meses_es = ['Jan'=>'ENE', 'Feb'=>'FEB', 'Mar'=>'MAR', 'Apr'=>'ABR', 'May'=>'MAY', 'Jun'=>'JUN', 'Jul'=>'JUL', 'Aug'=>'AGO', 'Sep'=>'SEP', 'Oct'=>'OCT', 'Nov'=>'NOV', 'Dec'=>'DIC'];
                        $m_nom_es = $meses_es[$m_nom] ?? $m_nom;
                    ?>
                    <div class="proximas-item">
                        <div class="date-box">
                            <div class="date-box-day"><?php echo $d_num; ?></div>
                            <div class="date-box-month"><?php echo $m_nom_es; ?></div>
                        </div>
                        <div style="flex:1;">
                            <h4 style="margin:0 0 4px 0; font-size:0.85rem; color:#1e293b;"><?php echo htmlspecialchars($p_act['titulo']); ?></h4>
                            <span class="tag-item tag-general" style="font-size:0.7rem;">👤 <?php echo htmlspecialchars($p_act['categoria'] ?? 'General'); ?></span>
                        </div>
                        <span style="color:#cbd5e1;">❯</span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="font-size: 0.82rem; color: #94a3b8; margin: 0;">No hay próximas actividades pendientes.</p>
            <?php endif; ?>

            <a href="#" style="display: block; margin-top: 15px; font-size: 0.82rem; color: #1d4ed8; font-weight: 600; text-decoration: none;">Ver todas las actividades ➔</a>
        </div>
    </div>

</div>

<script>
let categoriaSeleccionadaTab = 'Todas';

function filtrarTab(categoria, btn) {
    categoriaSeleccionadaTab = categoria;
    
    // Cambiar estado activo de las pestañas
    document.querySelectorAll('.tab-cat-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    aplicarFiltros();
}

function aplicarFiltros() {
    const textoBuscar = document.getElementById('inputBuscar').value.toLowerCase().trim();
    const mesFiltro = document.getElementById('selectMes').value;
    const catFiltroSelect = document.getElementById('selectCategoria').value;

    const tarjetas = document.querySelectorAll('.item-actividad');

    tarjetas.forEach(tarjeta => {
        const catTarjeta = tarjeta.dataset.categoria;
        const mesTarjeta = tarjeta.dataset.mes;
        const textoTarjeta = tarjeta.dataset.texto;

        // Validar filtro de pestaña
        let cumpleTab = (categoriaSeleccionadaTab === 'Todas') || (catTarjeta === categoriaSeleccionadaTab);
        
        // Validar filtro de buscador
        let cumpleTexto = (textoBuscar === '') || textoTarjeta.includes(textoBuscar);

        // Validar filtro de mes
        let cumpleMes = (mesFiltro === '') || (mesTarjeta === mesFiltro);

        // Validar filtro de select de categoría
        let cumpleCatSelect = (catFiltroSelect === '') || (catTarjeta === catFiltroSelect);

        if (cumpleTab && cumpleTexto && cumpleMes && cumpleCatSelect) {
            tarjeta.style.display = 'flex';
        } else {
            tarjeta.style.display = 'none';
        }
    });
}
</script>

<?php require_once "../includes/footer.php"; ?>