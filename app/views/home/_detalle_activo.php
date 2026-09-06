<?php
/**
 * Parcial: detalle completo de un activo, renderizado dentro del modal
 * que se abre al hacer clic en una tarjeta (sin recargar la página).
 * Variable esperada: $activo (resultado de Activo::obtenerPorId()).
 */
use App\Helpers\HistorialVista as HV;

$statusClase = match($activo['status'] ?? '') {
    'en_bodega' => 'secondary',
    'en_uso'    => 'success',
    'baja'      => 'danger',
    'garantia'  => 'warning text-dark',
    'asignado'  => 'primary',
    default     => 'dark',
};
$statusLabel = match($activo['status'] ?? '') {
    'en_bodega' => 'En Bodega',
    'en_uso'    => 'En Uso',
    'baja'      => 'Baja',
    'garantia'  => 'Garantía',
    'asignado'  => 'Asignado',
    default     => 'Desconocido',
};
?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h5 class="mb-0"><?= htmlspecialchars($activo['dispositivo_nombre'] ?? 'Equipo') ?></h5>
    <span class="badge rounded-pill bg-<?= $statusClase ?>"><?= $statusLabel ?></span>
</div>

<div class="row g-3">
    <div class="col-6">
        <small class="text-muted d-block">Serie</small>
        <strong><?= htmlspecialchars($activo['serie'] ?? '—') ?></strong>
    </div>
    <div class="col-6">
        <small class="text-muted d-block">Código de barras</small>
        <strong><?= htmlspecialchars($activo['codigo_barras'] ?? '—') ?></strong>
    </div>

    <div class="col-6">
        <small class="text-muted d-block">N° de activo</small>
        <strong><?= htmlspecialchars($activo['num_activo'] ?? '—') ?></strong>
    </div>

    <div class="col-4">
        <small class="text-muted d-block">Dispositivo</small>
        <strong><?= htmlspecialchars($activo['dispositivo_nombre'] ?? '—') ?></strong>
    </div>
    <div class="col-4">
        <small class="text-muted d-block">Marca</small>
        <strong><?= htmlspecialchars($activo['marca_nombre'] ?? '—') ?></strong>
    </div>
    <div class="col-4">
        <small class="text-muted d-block">Modelo</small>
        <strong><?= htmlspecialchars($activo['modelo_nombre'] ?? '—') ?></strong>
    </div>

    <div class="col-4">
        <small class="text-muted d-block">Negocio</small>
        <strong><?= htmlspecialchars($activo['negocio_nombre'] ?? '—') ?></strong>
    </div>
    <div class="col-4">
        <small class="text-muted d-block">Región</small>
        <strong><?= htmlspecialchars($activo['region_nombre'] ?? '—') ?></strong>
    </div>
    <div class="col-4">
        <small class="text-muted d-block">Plaza</small>
        <strong><?= htmlspecialchars($activo['plaza_nombre'] ?? '—') ?></strong>
    </div>

    <div class="col-12">
        <hr class="my-1">
    </div>

    <?php
    [$stockLbl, $stockVal] = match ($activo['stock_tipo'] ?? '') {
        'usuario' => ['Asignado a', $activo['usuario_nombre'] ?? '—'],
        'tienda'  => ['Stock de tienda', $activo['tienda_stock_nombre'] ?? '—'],
        default   => ['Bodega', $activo['bodega_nombre'] ?? '—'],
    };
    ?>
    <div class="col-6">
        <small class="text-muted d-block"><?= $stockLbl ?></small>
        <strong><?= htmlspecialchars($stockVal) ?></strong>
    </div>
    <?php if (!empty($activo['tienda_uso_nombre'])): ?>
        <div class="col-6">
            <small class="text-muted d-block">En uso en</small>
            <strong class="text-success"><?= htmlspecialchars($activo['tienda_uso_nombre']) ?></strong>
        </div>
    <?php endif; ?>
    <?php if (!empty($activo['procedencia_nombre'])): ?>
        <div class="col-6">
            <small class="text-muted d-block">Procedencia</small>
            <strong><?= htmlspecialchars($activo['procedencia_nombre']) ?></strong>
        </div>
    <?php endif; ?>

    <div class="col-12">
        <hr class="my-1">
    </div>

    <div class="col-6">
        <small class="text-muted d-block">Fecha de alta</small>
        <strong><?= htmlspecialchars($activo['fecha_alta'] ?? '—') ?></strong>
    </div>
    <div class="col-6">
        <small class="text-muted d-block">Última modificación</small>
        <strong><?= htmlspecialchars($activo['fecha_modificacion'] ?? '—') ?></strong>
    </div>

    <div class="col-12">
        <small class="text-muted d-block">ID interno</small>
        <strong>#<?= str_pad($activo['id'], 4, '0', STR_PAD_LEFT) ?></strong>
    </div>
</div>

<?php
$fotosActivo = [
    'foto_equipo' => 'Equipo',
    'foto_serie'  => 'Serie',
    'foto_activo' => 'Código de barras',
];
$hayFotos = array_filter($fotosActivo, fn($_, $c) => !empty($activo[$c]), ARRAY_FILTER_USE_BOTH);
?>
<?php if (!empty($hayFotos)): ?>
    <hr class="my-3">
    <h6 class="mb-2"><i class="fas fa-camera me-2 text-primary"></i>Fotografías</h6>
    <div class="row g-2">
        <?php foreach ($hayFotos as $campo => $label): ?>
            <div class="col-4">
                <a href="uploads/<?= htmlspecialchars($activo[$campo]) ?>" target="_blank">
                    <img src="uploads/thumbs/<?= htmlspecialchars($activo[$campo]) ?>"
                         onerror="this.src='uploads/<?= htmlspecialchars($activo[$campo]) ?>'"
                         alt="<?= $label ?>" class="rounded border w-100" style="height:90px;object-fit:cover;">
                </a>
                <small class="text-muted d-block text-center"><?= $label ?></small>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($movimientos)): ?>
    <hr class="my-3">
    <h6 class="mb-2"><i class="fas fa-clock-rotate-left me-2 text-primary"></i>Línea de tiempo</h6>
    <?php
    $etiquetas = \App\Models\Movimiento::EVENTOS;
    $colorEvt = [
        'alta' => 'success', 'cambio_status' => 'primary', 'cambio_stock' => 'info',
        'reemplazo_entra' => 'success', 'reemplazo_sale' => 'warning text-dark',
        'edicion' => 'secondary', 'baja' => 'danger', 'eliminacion' => 'dark',
    ];
    ?>
    <ul class="list-group list-group-flush small">
        <?php foreach ($movimientos as $m): ?>
            <li class="list-group-item px-0 py-2">
                <div class="d-flex justify-content-between">
                    <span class="badge bg-<?= $colorEvt[$m['evento']] ?? 'secondary' ?>">
                        <?= htmlspecialchars($etiquetas[$m['evento']] ?? $m['evento']) ?>
                    </span>
                    <span class="text-muted"><?= htmlspecialchars($m['creado_en'] ?? '') ?></span>
                </div>
                <div class="mt-1">
                    <div><?= htmlspecialchars(HV::titulo($m)) ?></div>
                    <div class="text-muted font-monospace" style="font-size:.85em"><?= htmlspecialchars(HV::identificadores($m)) ?></div>
                    <?php if (!empty($m['status_anterior']) || !empty($m['status_nuevo'])): ?>
                        <div>Estatus:
                            <strong><?= htmlspecialchars($m['status_anterior'] ?? '—') ?></strong>
                            &rarr; <strong><?= htmlspecialchars($m['status_nuevo'] ?? '—') ?></strong>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($m['stock_new_nombre']) && ($m['stock_ant_nombre'] ?? null) !== ($m['stock_new_nombre'] ?? null)): ?>
                        <div>Stock:
                            <strong><?= htmlspecialchars($m['stock_ant_nombre'] ?? '—') ?></strong>
                            &rarr; <strong><?= htmlspecialchars($m['stock_new_nombre']) ?></strong>
                        </div>
                    <?php endif; ?>
                    <?php if (HV::tieneRelacionado($m)): ?>
                        <div class="ps-2 border-start border-2 mt-1">
                            <div><i class="fas fa-link me-1"></i><?= htmlspecialchars(HV::relLabel($m['evento'])) ?>: <strong><?= htmlspecialchars(HV::titulo($m, 'rel_')) ?></strong></div>
                            <div class="text-muted font-monospace" style="font-size:.85em"><?= htmlspecialchars(HV::identificadores($m, 'rel_')) ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($m['tienda_nombre'])): ?>
                        <div>Tienda: <strong><?= htmlspecialchars($m['tienda_nombre']) ?></strong></div>
                    <?php endif; ?>
                    <?php if (!empty($m['nota'])): ?>
                        <div class="text-muted fst-italic"><?= htmlspecialchars($m['nota']) ?></div>
                    <?php endif; ?>
                    <div class="text-muted">Por: <?= htmlspecialchars($m['actor_nombre'] ?? 'Sistema') ?></div>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>