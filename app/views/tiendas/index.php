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

<?php $puedeAsignarAti = $puedeAsignarAti ?? false; ?>

<div class="container py-4">
    <h2 class="h3 fw-bold mb-1"><i class="fas fa-store text-primary me-2"></i>Tiendas</h2>
    <p class="text-muted">Entra a una tienda para ver y administrar sus activos.
        <?php if ($puedeAsignarAti): ?>
            El <strong>ATI responsable</strong> recibe en su stock los activos que pasan a garantía o baja.
        <?php endif; ?>
    </p>

    <form method="GET" action="index.php" class="row g-2 align-items-end mb-3">
        <input type="hidden" name="controller" value="tienda">
        <input type="hidden" name="action" value="index">
        <div class="col-md-4">
            <label class="form-label small fw-bold text-muted mb-1">Plaza</label>
            <select name="plaza_id" class="form-select" onchange="this.form.submit()">
                <option value="0"<?= (int) $plazaId === 0 ? ' selected' : '' ?>><?= \App\Helpers\Permisos::esAdmin() ? 'Todas' : 'Todas mis plazas' ?></option>
                <?php foreach ($plazas as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= (int) $p['id'] === (int) $plazaId ? 'selected' : '' ?>>
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
                <tr>
                    <th>CR</th><th>Tienda</th><th>Plaza</th>
                    <th class="text-end">Activos</th>
                    <?php if ($puedeAsignarAti): ?><th style="width:280px;">ATI responsable</th><?php endif; ?>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($tiendas)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Sin tiendas para el filtro.</td></tr>
            <?php else: foreach ($tiendas as $t): ?>
                <tr>
                    <td class="text-muted"><?= htmlspecialchars($t['cr_tienda'] ?? '') ?></td>
                    <td class="fw-bold">
                        <a href="index.php?modulo=tiendas&tienda_id=<?= (int) $t['id'] ?>" class="text-decoration-none">
                            <?= htmlspecialchars($t['nombre']) ?>
                        </a>
                    </td>
                    <td class="small text-muted"><?= htmlspecialchars($t['plaza_nombre'] ?? '') ?></td>
                    <td class="text-end"><span class="badge bg-light text-dark border"><?= number_format((int) ($t['activos_count'] ?? 0)) ?></span></td>
                    <?php if ($puedeAsignarAti): ?>
                    <td>
                        <select class="form-select form-select-sm js-ati" data-tienda-id="<?= $t['id'] ?>"
                                <?= empty($atis) ? 'disabled' : '' ?>>
                            <option value="">— Sin asignar —</option>
                            <?php foreach ($atis as $a): ?>
                                <option value="<?= $a['id'] ?>" <?= (int) ($t['ati_usuario_id'] ?? 0) === (int) $a['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($a['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($atis)): ?>
                            <div class="form-text small"><?= htmlspecialchars($t['ati_nombre'] ?? 'Elige una plaza para asignar') ?></div>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td class="text-end">
                        <a href="index.php?modulo=tiendas&tienda_id=<?= (int) $t['id'] ?>" class="btn btn-sm btn-outline-primary">
                            Ver activos <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
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
