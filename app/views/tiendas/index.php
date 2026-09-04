<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario 123 - Tiendas</title>
    <?php include ROOT_PATH . '/app/views/components/favicon.php'; ?>
</head>
<body>
<?php include ROOT_PATH . '/app/views/components/navbar.php'; ?>

<div class="container py-4">
    <h2 class="h3 fw-bold mb-1"><i class="fas fa-store text-primary me-2"></i>Tiendas · ATI responsable</h2>
    <p class="text-muted">El ATI responsable de una tienda recibe en su stock los activos que pasan a
        <strong>garantía</strong> o <strong>baja</strong>.</p>

    <form method="GET" action="index.php" class="row g-2 align-items-end mb-3">
        <input type="hidden" name="controller" value="tienda">
        <input type="hidden" name="action" value="index">
        <div class="col-md-4">
            <label class="form-label small fw-bold text-muted mb-1">Plaza</label>
            <select name="plaza_id" class="form-select" onchange="this.form.submit()">
                <?php foreach ($plazas as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= (int) $p['id'] === $plazaId ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-bold text-muted mb-1">Buscar</label>
            <input type="text" name="busqueda" class="form-control" value="<?= htmlspecialchars($busqueda) ?>"
                   placeholder="Nombre o CR de tienda...">
        </div>
        <div class="col-md-2">
            <button class="btn btn-outline-secondary w-100"><i class="fas fa-search me-1"></i> Filtrar</button>
        </div>
    </form>

    <div id="tiendaAlert"></div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr><th>CR</th><th>Tienda</th><th style="width:320px;">ATI responsable</th></tr>
            </thead>
            <tbody>
            <?php if (empty($tiendas)): ?>
                <tr><td colspan="3" class="text-center text-muted py-4">Sin tiendas para el filtro.</td></tr>
            <?php else: foreach ($tiendas as $t): ?>
                <tr>
                    <td class="text-muted"><?= htmlspecialchars($t['cr_tienda']) ?></td>
                    <td class="fw-bold"><?= htmlspecialchars($t['nombre']) ?></td>
                    <td>
                        <select class="form-select form-select-sm js-ati" data-tienda-id="<?= $t['id'] ?>">
                            <option value="">— Sin asignar —</option>
                            <?php foreach ($atis as $a): ?>
                                <option value="<?= $a['id'] ?>" <?= (int) ($t['ati_usuario_id'] ?? 0) === (int) $a['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($a['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($plazaId > 0 && empty($atis)): ?>
        <p class="text-warning small"><i class="fas fa-triangle-exclamation me-1"></i>
            Esta plaza no tiene usuarios tipo ATI. Crea al menos uno para poder asignarlo.</p>
    <?php endif; ?>
</div>

<script>
document.querySelectorAll('.js-ati').forEach(sel => {
    sel.addEventListener('change', async () => {
        const alertBox = document.getElementById('tiendaAlert');
        const fd = new FormData();
        fd.append('tienda_id', sel.dataset.tiendaId);
        fd.append('ati_usuario_id', sel.value);
        sel.disabled = true;
        try {
            const r = await fetch('index.php?controller=api&action=asignarAtiTienda', {
                method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const d = await r.json();
            alertBox.innerHTML = '<div class="alert alert-' + (d.success ? 'success' : 'danger') +
                ' alert-dismissible fade show py-2">' + (d.message || '') +
                '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        } catch (e) {
            alertBox.innerHTML = '<div class="alert alert-danger py-2">Error de conexión.</div>';
        } finally {
            sel.disabled = false;
        }
    });
});
</script>
</body>
</html>
