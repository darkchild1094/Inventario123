<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Traslados - Inventario 123</title>
    <?php include ROOT_PATH . '/app/views/components/favicon.php'; ?>
    <style> body { background:#f4f7f6; } </style>
</head>
<body>
<?php include ROOT_PATH . '/app/views/components/navbar.php'; ?>
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h4 class="fw-bold mb-0">
            <i class="fas fa-right-left me-2"></i>Solicitudes de movimiento
            <?php if (!empty($pendientes)): ?>
                <span class="badge rounded-pill bg-warning text-dark ms-1"><?= (int) $pendientes ?> por firmar</span>
            <?php endif; ?>
        </h4>
        <a href="index.php?controller=solicitud&action=crear" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i>Nueva solicitud
        </a>
    </div>

    <form method="GET" class="mb-3">
        <input type="hidden" name="controller" value="solicitud">
        <input type="hidden" name="action" value="index">
        <div class="d-inline-flex align-items-center gap-2">
            <label class="text-muted small mb-0">Estado</label>
            <select name="estado" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                <option value="">Todos</option>
                <?php foreach ($estados as $k => $lbl): ?>
                    <option value="<?= $k ?>" <?= ($fEstado === $k) ? 'selected' : '' ?>><?= $lbl ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th><th>Fecha</th><th>Movimiento</th><th>Origen</th><th>Plaza</th>
                        <th class="text-center">Activos</th><th>Estado</th><th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($solicitudes)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Sin solicitudes.</td></tr>
                <?php else: foreach ($solicitudes as $s):
                    $badge = match ($s['estado']) {
                        'pendiente' => 'warning text-dark', 'aprobada' => 'success',
                        'rechazada' => 'danger', 'cancelada' => 'secondary', default => 'light text-dark',
                    };
                    $porFirmar = in_array($s['id'], $porFirmarIds ?? [], true);
                    $origen = $s['origen_nombre'] ?: ($s['origen_tienda_nombre'] ?: '—');
                    $destTxt = \App\Models\SolicitudTraslado::DESTINOS[$s['destino']] ?? $s['destino'];
                    if ($s['destino'] === 'asignado' && $s['destino_usuario_nombre']) $destTxt .= ' → ' . $s['destino_usuario_nombre'];
                    if ($s['destino'] === 'en_bodega' && $s['bodega_nombre']) $destTxt .= ' → ' . $s['bodega_nombre'];
                ?>
                    <tr class="<?= $porFirmar ? 'table-warning' : '' ?>">
                        <td>#<?= (int) $s['id'] ?></td>
                        <td class="text-nowrap"><?= htmlspecialchars(substr((string) $s['creado_en'], 0, 16)) ?></td>
                        <td><?= htmlspecialchars($destTxt) ?></td>
                        <td><?= htmlspecialchars($origen) ?></td>
                        <td><?= htmlspecialchars($s['plaza_nombre'] ?? '—') ?></td>
                        <td class="text-center"><?= (int) ($s['activos_count'] ?? 0) ?></td>
                        <td><span class="badge bg-<?= $badge ?> text-uppercase"><?= $estados[$s['estado']] ?? $s['estado'] ?></span></td>
                        <td class="text-end">
                            <a href="index.php?controller=solicitud&action=ver&id=<?= (int) $s['id'] ?>"
                               class="btn btn-sm btn-outline-primary">
                                <?= $porFirmar ? 'Firmar' : 'Ver' ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
