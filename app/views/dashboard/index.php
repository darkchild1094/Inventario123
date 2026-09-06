<?php
use App\Models\Movimiento;

$nombre  = $_SESSION['usuario']['nombre'] ?? 'Usuario';
$plaza   = $_SESSION['usuario']['plaza_nombre'] ?? '';
$vistaBase = in_array($tipo, ['admin', 'coordinador'], true) ? 'todos' : 'mi_stock';

$kpis = [
    ['k' => 'total',     'lbl' => 'Total activos', 'ico' => 'fa-box-open',        'cls' => 'dark',    'val' => $resumen['total']],
    ['k' => 'en_uso',    'lbl' => 'En uso',        'ico' => 'fa-plug-circle-check','cls' => 'secondary','val' => $resumen['por_status']['en_uso']],
    ['k' => 'en_bodega', 'lbl' => 'En bodega',     'ico' => 'fa-warehouse',       'cls' => 'success', 'val' => $resumen['por_status']['en_bodega']],
    ['k' => 'asignado',  'lbl' => 'Asignado',      'ico' => 'fa-user-tag',        'cls' => 'primary', 'val' => $resumen['por_status']['asignado']],
    ['k' => 'garantia',  'lbl' => 'Garantía',      'ico' => 'fa-screwdriver-wrench','cls' => 'warning','val' => $resumen['por_status']['garantia']],
    ['k' => 'baja',      'lbl' => 'Baja',          'ico' => 'fa-ban',             'cls' => 'danger',  'val' => $resumen['por_status']['baja']],
];

$colorEvt = [
    'alta' => 'success', 'cambio_status' => 'primary', 'cambio_stock' => 'info',
    'reemplazo_entra' => 'success', 'reemplazo_sale' => 'warning text-dark',
    'edicion' => 'secondary', 'baja' => 'danger', 'eliminacion' => 'dark',
];
$eventos   = Movimiento::EVENTOS;
$maxDisp   = max(1, ...array_map(fn($d) => $d['n'], $resumen['por_dispositivo'] ?: [['n' => 1]]));
$maxPlaza  = max(1, ...array_map(fn($d) => $d['n'], $resumen['por_plaza'] ?: [['n' => 1]]));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio - Inventario 123</title>
    <?php include ROOT_PATH . '/app/views/components/favicon.php'; ?>
    <style>
        body { background: #f4f7f6; }
        .kpi { border: 0; border-radius: 14px; overflow: hidden; transition: transform .12s, box-shadow .12s; }
        .kpi:hover { transform: translateY(-2px); box-shadow: 0 .5rem 1.2rem rgba(0,0,0,.1); }
        .kpi .num { font-size: 1.9rem; font-weight: 700; line-height: 1; }
        .kpi .lbl { font-size: .8rem; opacity: .85; }
        .kpi .ico { font-size: 1.6rem; opacity: .35; }
        .bar-track { background: #e9ecef; border-radius: 6px; height: 10px; overflow: hidden; }
        .bar-fill { height: 100%; border-radius: 6px; background: #0d6efd; }
        .card-soft { border: 0; border-radius: 14px; box-shadow: 0 .25rem .75rem rgba(0,0,0,.05); }
        .mov-row { border-bottom: 1px solid #f0f0f0; padding: .55rem 0; font-size: .87rem; }
        .mov-row:last-child { border-bottom: 0; }
    </style>
</head>
<body>
<?php include ROOT_PATH . '/app/views/components/navbar.php'; ?>

<div class="container-fluid px-3 px-lg-4 py-4">

    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
        <div>
            <h4 class="fw-bold mb-0">Hola, <?= htmlspecialchars(explode(' ', $nombre)[0]) ?> 👋</h4>
            <div class="text-muted small">
                <span class="text-uppercase"><?= htmlspecialchars($tipo) ?></span>
                <?= $plaza !== '' ? ' · ' . htmlspecialchars($plaza) : '' ?>
            </div>
        </div>
        <?php if (in_array($tipo, ['admin', 'coordinador', 'fs', 'ati'], true)): ?>
            <a href="index.php?action=crear" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Nuevo activo
            </a>
        <?php endif; ?>
    </div>

    <!-- KPIs -->
    <div class="row g-3 mb-4">
        <?php foreach ($kpis as $c): ?>
            <div class="col-6 col-md-4 col-xl-2">
                <a href="index.php?vista=<?= $vistaBase ?><?= $c['k'] !== 'total' ? '&status=' . $c['k'] : '' ?>"
                   class="text-decoration-none">
                    <div class="card kpi bg-<?= $c['cls'] ?> <?= $c['cls'] === 'warning' ? 'text-dark' : 'text-white' ?>">
                        <div class="card-body d-flex align-items-center justify-content-between">
                            <div>
                                <div class="num"><?= number_format($c['val']) ?></div>
                                <div class="lbl"><?= $c['lbl'] ?></div>
                            </div>
                            <i class="fas <?= $c['ico'] ?> ico"></i>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-3">

        <!-- Por tipo de equipo -->
        <div class="col-lg-6">
            <div class="card card-soft h-100">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="fas fa-microchip me-2 text-primary"></i>Por tipo de equipo</h6>
                    <?php if (empty($resumen['por_dispositivo'])): ?>
                        <p class="text-muted small mb-0">Sin datos.</p>
                    <?php else: foreach ($resumen['por_dispositivo'] as $d): ?>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small">
                                <span><?= htmlspecialchars($d['nombre']) ?></span>
                                <strong><?= number_format($d['n']) ?></strong>
                            </div>
                            <div class="bar-track mt-1">
                                <div class="bar-fill" style="width: <?= round($d['n'] / $maxDisp * 100) ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>

        <!-- Por plaza (admin/coordinador) o Traslados/atajos -->
        <div class="col-lg-6">
            <div class="card card-soft h-100">
                <div class="card-body">
                    <?php if (in_array($tipo, ['admin', 'coordinador'], true) && !empty($resumen['por_plaza'])): ?>
                        <h6 class="fw-bold mb-3"><i class="fas fa-location-dot me-2 text-primary"></i>Por plaza</h6>
                        <?php foreach ($resumen['por_plaza'] as $d): ?>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between small">
                                    <span><?= htmlspecialchars($d['nombre']) ?></span>
                                    <strong><?= number_format($d['n']) ?></strong>
                                </div>
                                <div class="bar-track mt-1">
                                    <div class="bar-fill" style="width: <?= round($d['n'] / $maxPlaza * 100) ?>%; background:#198754"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <h6 class="fw-bold mb-3"><i class="fas fa-bolt me-2 text-primary"></i>Accesos rápidos</h6>
                        <div class="d-grid gap-2">
                            <a href="index.php?vista=<?= $vistaBase ?>" class="btn btn-outline-secondary text-start"><i class="fas fa-list me-2"></i>Ver inventario</a>
                            <a href="index.php?controller=historial&action=index" class="btn btn-outline-secondary text-start"><i class="fas fa-clock-rotate-left me-2"></i>Historial de movimientos</a>
                            <a href="index.php?controller=export&action=inventario" class="btn btn-outline-secondary text-start"><i class="fas fa-file-excel me-2 text-success"></i>Exportar a Excel</a>
                        </div>
                    <?php endif; ?>

                    <?php if (in_array($tipo, ['coordinador', 'admin'], true)): ?>
                        <a href="index.php?controller=solicitud&action=index"
                           class="d-flex align-items-center justify-content-between mt-3 p-2 rounded text-decoration-none
                                  <?= $pendTraslados > 0 ? 'bg-warning-subtle text-dark' : 'bg-light text-muted' ?>">
                            <span><i class="fas fa-right-left me-2"></i>Traslados pendientes de aprobar</span>
                            <span class="badge rounded-pill bg-<?= $pendTraslados > 0 ? 'warning text-dark' : 'secondary' ?>">
                                <?= (int) $pendTraslados ?>
                            </span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Movimientos recientes -->
        <div class="col-lg-<?= $tipo === 'admin' ? '8' : '12' ?>">
            <div class="card card-soft h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold mb-0"><i class="fas fa-wave-square me-2 text-primary"></i>Movimientos recientes</h6>
                        <a href="index.php?controller=historial&action=index" class="small text-decoration-none">Ver todo</a>
                    </div>
                    <?php if (empty($movs)): ?>
                        <p class="text-muted small mb-0">Aún no hay movimientos.</p>
                    <?php else: foreach ($movs as $m):
                        $eq = trim(($m['eq_dispositivo'] ?? '') . ' · ' . trim(($m['eq_marca'] ?? '') . ' ' . ($m['eq_modelo'] ?? '')), ' ·');
                        $idn = $m['eq_serie'] ?: ($m['eq_codigo_barras'] ?: ($m['eq_num_activo'] ?: '—'));
                    ?>
                        <div class="mov-row d-flex align-items-center gap-2">
                            <span class="badge bg-<?= $colorEvt[$m['evento']] ?? 'secondary' ?>" style="min-width:110px">
                                <?= htmlspecialchars($eventos[$m['evento']] ?? $m['evento']) ?>
                            </span>
                            <span class="flex-grow-1 text-truncate">
                                <?= htmlspecialchars($eq ?: 'Equipo') ?>
                                <span class="text-muted">· <?= htmlspecialchars($idn) ?></span>
                            </span>
                            <span class="text-muted small text-nowrap"><?= htmlspecialchars(substr((string) $m['creado_en'], 0, 16)) ?></span>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>

        <!-- Catálogo (solo admin) -->
        <?php if ($tipo === 'admin' && !empty($extras)): ?>
            <div class="col-lg-4">
                <div class="card card-soft h-100">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="fas fa-database me-2 text-primary"></i>Catálogo</h6>
                        <a href="index.php?controller=tienda&action=index" class="d-flex justify-content-between py-2 border-bottom text-decoration-none text-body">
                            <span><i class="fas fa-store me-2 text-muted"></i>Tiendas</span><strong><?= number_format($extras['tiendas']) ?></strong>
                        </a>
                        <a href="index.php?controller=usuario&action=index" class="d-flex justify-content-between py-2 border-bottom text-decoration-none text-body">
                            <span><i class="fas fa-users me-2 text-muted"></i>Usuarios</span><strong><?= number_format($extras['usuarios']) ?></strong>
                        </a>
                        <a href="index.php?controller=modelo&action=index" class="d-flex justify-content-between py-2 text-decoration-none text-body">
                            <span><i class="fas fa-tags me-2 text-muted"></i>Modelos</span><strong><?= number_format($extras['modelos']) ?></strong>
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>
</body>
</html>
