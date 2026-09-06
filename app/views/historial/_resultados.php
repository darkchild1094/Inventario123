<?php
/**
 * Parcial: tabla de movimientos + paginación. Se usa en la carga normal y en
 * las respuestas AJAX de filtrado en vivo de la pestaña Historial.
 * Variables: $movimientos, $paginacion, $eventos
 */
use App\Helpers\HistorialVista as HV;

$colorEvt = [
    'alta' => 'success', 'cambio_status' => 'primary', 'cambio_stock' => 'info',
    'reemplazo_entra' => 'success', 'reemplazo_sale' => 'warning text-dark',
    'edicion' => 'secondary', 'baja' => 'danger', 'eliminacion' => 'dark',
];
?>
<div class="table-responsive">
    <table class="table table-hover align-middle small">
        <thead class="table-light">
            <tr>
                <th>Fecha</th>
                <th>Evento</th>
                <th>Activo</th>
                <th>Estatus</th>
                <th>Stock</th>
                <th>Tienda</th>
                <th>Por</th>
                <th>Nota</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($movimientos)): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">
                <i class="fas fa-clock-rotate-left fa-2x d-block mb-2 opacity-50"></i>
                No hay movimientos con los filtros aplicados.
            </td></tr>
        <?php else: foreach ($movimientos as $m): ?>
            <tr>
                <td class="text-nowrap"><?= htmlspecialchars($m['creado_en'] ?? '') ?></td>
                <td>
                    <span class="badge bg-<?= $colorEvt[$m['evento']] ?? 'secondary' ?>">
                        <?= htmlspecialchars($eventos[$m['evento']] ?? $m['evento']) ?>
                    </span>
                </td>
                <td>
                    <div class="fw-bold"><?= htmlspecialchars(HV::titulo($m)) ?></div>
                    <div class="text-muted font-monospace" style="font-size:.8em"><?= htmlspecialchars(HV::identificadores($m)) ?></div>
                    <?php if (HV::tieneRelacionado($m)): ?>
                        <div class="mt-1 ps-2 border-start border-2">
                            <div class="text-muted"><i class="fas fa-link me-1"></i><?= htmlspecialchars(HV::relLabel($m['evento'])) ?>: <strong><?= htmlspecialchars(HV::titulo($m, 'rel_')) ?></strong></div>
                            <div class="text-muted font-monospace" style="font-size:.8em"><?= htmlspecialchars(HV::identificadores($m, 'rel_')) ?></div>
                        </div>
                    <?php endif; ?>
                </td>
                <td class="text-nowrap">
                    <?php if (!empty($m['status_anterior']) || !empty($m['status_nuevo'])): ?>
                        <?= htmlspecialchars($m['status_anterior'] ?? '—') ?>
                        &rarr; <strong><?= htmlspecialchars($m['status_nuevo'] ?? '—') ?></strong>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td>
                    <?php if (($m['stock_ant_nombre'] ?? null) !== ($m['stock_new_nombre'] ?? null) && !empty($m['stock_new_nombre'])): ?>
                        <span class="text-muted"><?= htmlspecialchars($m['stock_ant_nombre'] ?? '—') ?></span>
                        &rarr; <strong><?= htmlspecialchars($m['stock_new_nombre']) ?></strong>
                    <?php else: ?>
                        <?= htmlspecialchars($m['stock_new_nombre'] ?? $m['stock_ant_nombre'] ?? '—') ?>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($m['tienda_nombre'] ?? '—') ?></td>
                <td><?= htmlspecialchars($m['actor_nombre'] ?? 'Sistema') ?></td>
                <td class="text-muted"><?= htmlspecialchars($m['nota'] ?? '') ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php if (isset($paginacion) && $paginacion['total_paginas'] > 1): ?>
    <nav class="mt-3">
        <ul class="pagination pagination-sm justify-content-center">
            <?php
            $actual = $paginacion['pagina_actual'];
            $total  = $paginacion['total_paginas'];
            $inicio = max(1, $actual - 3);
            $fin    = min($total, $actual + 3);
            ?>
            <li class="page-item <?= $actual <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="index.php?<?= http_build_query(array_merge($_GET, ['controller' => 'historial', 'action' => 'index', 'pagina' => $actual - 1])) ?>">&laquo;</a>
            </li>
            <?php for ($i = $inicio; $i <= $fin; $i++): ?>
                <li class="page-item <?= $i === $actual ? 'active' : '' ?>">
                    <a class="page-link" href="index.php?<?= http_build_query(array_merge($_GET, ['controller' => 'historial', 'action' => 'index', 'pagina' => $i])) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= $actual >= $total ? 'disabled' : '' ?>">
                <a class="page-link" href="index.php?<?= http_build_query(array_merge($_GET, ['controller' => 'historial', 'action' => 'index', 'pagina' => $actual + 1])) ?>">&raquo;</a>
            </li>
        </ul>
        <p class="text-center text-muted small">
            Página <?= $actual ?> de <?= $total ?> ·
            <?= number_format($paginacion['total_resultados']) ?> movimiento<?= $paginacion['total_resultados'] !== 1 ? 's' : '' ?>
        </p>
    </nav>
<?php endif; ?>
