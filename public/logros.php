<?php
$ruta_base = "../";
require_once "../includes/conexion.php";
require_once "../includes/header.php";

// Consulta para obtener todos los logros visibles
$sql_logros = "SELECT * FROM logros WHERE destacado = 1 ORDER BY fecha DESC";
$res_logros = mysqli_query($conexion, $sql_logros);

$logros_array = [];
$contadores = [
    'Todos' => 0,
    'Deportivo' => 0,
    'Académico' => 0,
    'Artístico' => 0,
    'Ciencia y Tecnología' => 0,
    'Disciplina y Valores' => 0
];

if ($res_logros && mysqli_num_rows($res_logros) > 0) {
    while ($row = mysqli_fetch_assoc($res_logros)) {
        $logros_array[] = $row;
        $contadores['Todos']++;
        if (isset($contadores[$row['categoria']])) {
            $contadores[$row['categoria']]++;
        }
    }
}
?>

<style>
/* Estilos para el Menú de Filtros */
.filtros-logros {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: center;
    margin-bottom: 30px;
    background: #fff;
    padding: 15px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}
.btn-filtro {
    background: transparent;
    border: 1px solid transparent;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 600;
    color: #4b5563;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
}
.btn-filtro:hover {
    background: #f3f4f6;
}
.btn-filtro.active {
    background: #7f1d1d; /* Color rojo oscuro del tema */
    color: white;
}
.contador-badge {
    background: rgba(0,0,0,0.1);
    padding: 2px 6px;
    border-radius: 12px;
    font-size: 0.75rem;
}
.btn-filtro.active .contador-badge {
    background: rgba(255,255,255,0.2);
}

/* Estilos de las Tarjetas */
.grid-logros {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}
.tarjeta-logro {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    border: 1px solid #e5e7eb;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    position: relative;
    overflow: hidden;
    transition: transform 0.2s;
}
.tarjeta-logro:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 15px rgba(0,0,0,0.1);
}
.logro-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}
.logro-badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 5px;
}
.logro-contenido {
    display: flex;
    gap: 15px;
    flex-grow: 1;
}
.logro-texto {
    flex: 1;
    z-index: 2;
}
.logro-imagen {
    width: 100px;
    height: 120px;
    object-fit: cover;
    border-radius: 8px;
    box-shadow: -4px 4px 10px rgba(0,0,0,0.1);
    transform: rotate(3deg);
}
.btn-ver-mas {
    display: inline-block;
    margin-top: 15px;
    padding: 6px 14px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.85rem;
    color: #4b5563;
    text-decoration: none;
    font-weight: 500;
    transition: 0.2s;
}
.btn-ver-mas:hover {
    background: #f3f4f6;
    color: #111827;
}

/* Tarjeta CTA Especial */
.tarjeta-cta {
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
}
.btn-cta {
    display: block;
    width: 100%;
    text-align: center;
    background: #475569;
    color: white;
    padding: 10px;
    border-radius: 8px;
    text-decoration: none;
    margin-top: 15px;
    font-weight: 600;
}
.btn-cta:hover { background: #334155; }
</style>

<div class="card hero-section" style="text-align: center; margin-bottom: 30px; background: #7f1d1d; color: white;">
    <h1 style="color: white; margin-bottom: 10px;">🏆 Logros y Talento Estudiantil</h1>
    <p style="opacity: 0.9;">Reconocimiento al esfuerzo, dedicación y excelencia de nuestros estudiantes en distintas disciplinas.</p>
</div>

<!-- Barra de Filtros -->
<div class="filtros-logros">
    <button class="btn-filtro active" onclick="filtrarLogros('Todos')">
        🗂️ Todos <span class="contador-badge"><?php echo $contadores['Todos']; ?></span>
    </button>
    <button class="btn-filtro" onclick="filtrarLogros('Deportivo')">
        🏆 Deportivos <span class="contador-badge"><?php echo $contadores['Deportivo']; ?></span>
    </button>
    <button class="btn-filtro" onclick="filtrarLogros('Académico')">
        📖 Académicos <span class="contador-badge"><?php echo $contadores['Académico']; ?></span>
    </button>
    <button class="btn-filtro" onclick="filtrarLogros('Artístico')">
        🎨 Artísticos <span class="contador-badge"><?php echo $contadores['Artístico']; ?></span>
    </button>
    <button class="btn-filtro" onclick="filtrarLogros('Ciencia y Tecnología')">
        ⚙️ Ciencia y Tecnología <span class="contador-badge"><?php echo $contadores['Ciencia y Tecnología']; ?></span>
    </button>
    <button class="btn-filtro" onclick="filtrarLogros('Disciplina y Valores')">
        ⭐ Disciplina y Valores <span class="contador-badge"><?php echo $contadores['Disciplina y Valores']; ?></span>
    </button>
</div>

<!-- Rejilla de Tarjetas -->
<div class="grid-logros" id="contenedor-logros">
    <?php if (!empty($logros_array)): ?>
        <?php foreach ($logros_array as $logro): ?>
            <?php
                $badge_bg = '#f3f4f6'; $badge_color = '#374151'; $icono = '🏆';
                if ($logro['categoria'] === 'Deportivo') { $badge_bg = '#fee2e2'; $badge_color = '#991b1b'; $icono = '🏆'; } 
                elseif ($logro['categoria'] === 'Académico') { $badge_bg = '#e0f2fe'; $badge_color = '#075985'; $icono = '📖'; } 
                elseif ($logro['categoria'] === 'Artístico') { $badge_bg = '#f3e8ff'; $badge_color = '#6b21a8'; $icono = '🎨'; }
                elseif ($logro['categoria'] === 'Ciencia y Tecnología') { $badge_bg = '#dcfce7'; $badge_color = '#166534'; $icono = '⚙️'; }
                elseif ($logro['categoria'] === 'Disciplina y Valores') { $badge_bg = '#fef3c7'; $badge_color = '#b45309'; $icono = '⭐'; }
            ?>
            <div class="tarjeta-logro item-logro" data-categoria="<?php echo htmlspecialchars($logro['categoria']); ?>">
                <div class="logro-header">
                    <span class="logro-badge" style="background-color: <?php echo $badge_bg; ?>; color: <?php echo $badge_color; ?>;">
                        <?php echo $icono . ' ' . htmlspecialchars($logro['categoria']); ?>
                    </span>
                    <span style="font-size: 0.8rem; color: #6b7280;">📅 <?php echo date("d/m/Y", strtotime($logro['fecha'])); ?></span>
                </div>
                
                <div class="logro-contenido">
                    <div class="logro-texto">
                        <h3 style="color: #111827; font-size: 1.1rem; margin-bottom: 8px; line-height: 1.3;">
                            <?php echo htmlspecialchars($logro['titulo']); ?>
                        </h3>
                        <p style="color: #4b5563; font-size: 0.9rem; line-height: 1.4;">
                            <?php echo htmlspecialchars($logro['descripcion']); ?>
                        </p>
                        <a href="#" class="btn-ver-mas">📝 Ver más ></a>
                    </div>
                    <?php if (!empty($logro['imagen'])): ?>
                        <div>
                            <!-- Se agregó $ruta_base para asegurar la ruta del servidor -->
                            <img src="<?php echo $ruta_base; ?>assets/img/uploads/logros/<?php echo htmlspecialchars($logro['imagen']); ?>" alt="Logro" class="logro-imagen">
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div style="grid-column: 1 / -1; text-align: center; color: #6b7280; padding: 40px;">
            <p>No hay logros registrados en este momento.</p>
        </div>
    <?php endif; ?>

    <!-- Tarjeta Estática CTA -->
    <div class="tarjeta-logro tarjeta-cta item-logro" data-categoria="Todos">
        <h3 style="color: #334155; font-size: 1.2rem; margin-bottom: 10px; display:flex; align-items:center; gap:8px;">
            📈 ¡Tu esfuerzo también cuenta!
        </h3>
        <p style="color: #64748b; font-size: 0.95rem; line-height: 1.5; flex-grow:1;">
            Si conoces algún logro o talento de un estudiante, puedes comunicarlo al Departamento de Gestión Académica.
        </p>
        <a href="contacto.php" class="btn-cta">✉️ Enviar propuesta</a>
    </div>
</div>

<script>
function filtrarLogros(categoria) {
    // Actualizar estado activo de los botones
    document.querySelectorAll('.btn-filtro').forEach(btn => btn.classList.remove('active'));
    event.currentTarget.classList.add('active');

    // Filtrar tarjetas
    const tarjetas = document.querySelectorAll('.item-logro');
    tarjetas.forEach(tarjeta => {
        // La tarjeta CTA siempre se muestra en "Todos", si quieres que aparezca siempre quita la condición.
        if (categoria === 'Todos' || tarjeta.dataset.categoria === categoria || tarjeta.classList.contains('tarjeta-cta')) {
            tarjeta.style.display = 'flex';
        } else {
            tarjeta.style.display = 'none';
        }
    });
}
</script>

<?php require_once "../includes/footer.php"; ?>