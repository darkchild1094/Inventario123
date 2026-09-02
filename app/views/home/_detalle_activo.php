<?php
/**
 * Parcial: detalle completo de un activo, renderizado dentro del modal
 * que se abre al hacer clic en una tarjeta (sin recargar la página).
 * Variable esperada: $activo (resultado de Activo::obtenerPorId()).
 */

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
        <small class="text-muted d-block">Placa / Activo Fijo</small>
        <strong><?= htmlspecialchars($activo['placa'] ?? '—') ?></strong>
    </div>

    <div class="col-6">
        <small class="text-muted d-block">Dispositivo</small>
        <strong><?= htmlspecialchars($activo['dispositivo_nombre'] ?? '—') ?></strong>
    </div>
    <div class="col-6">
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

    <div class="col-6">
        <small class="text-muted d-block">
            <?= $activo['stock_tipo'] === 'usuario' ? 'Asignado a' : 'Bodega' ?>
        </small>
        <strong>
            <?= htmlspecialchars(
                $activo['stock_tipo'] === 'usuario'
                    ? ($activo['usuario_nombre'] ?? '—')
                    : ($activo['bodega_nombre']  ?? '—')
            ) ?>
        </strong>
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