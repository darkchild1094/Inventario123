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
    <div class="d-flex align-items-center justify-content-between mb-1">
        <h2 class="h3 fw-bold mb-0"><i class="fas fa-store text-primary me-2"></i>Tiendas <span class="text-muted fs-6">(<?= count($tiendas) ?>)</span></h2>
        <div class="d-flex gap-2">
            <a id="btnExcel" class="btn btn-outline-secondary" href="index.php?controller=export&action=modulo&modulo=tiendas<?= $plazaId ? '&plaza_id=' . (int) $plazaId : '' ?>"><i class="fas fa-file-excel me-1"></i>Excel</a>
            <?php if ($puedeAsignarAti): ?>
                <button class="btn btn-primary" id="btnNuevaTienda"><i class="fas fa-plus me-1"></i>Nueva tienda</button>
            <?php endif; ?>
        </div>
    </div>
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
                        <a href="index.php?controller=tienda&action=detalle&id=<?= (int) $t['id'] ?>" class="text-decoration-none">
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
                        <?php if ($puedeAsignarAti): ?>
                        <button class="btn btn-sm btn-outline-secondary js-editar" data-id="<?= (int) $t['id'] ?>"
                            data-cr="<?= htmlspecialchars($t['cr_tienda'] ?? '') ?>" data-nombre="<?= htmlspecialchars($t['nombre']) ?>"
                            data-plaza="<?= (int) ($t['plaza_id'] ?? 0) ?>" data-ati="<?= (int) ($t['ati_usuario_id'] ?? 0) ?>">
                            <i class="fas fa-pen"></i>
                        </button>
                        <?php endif; ?>
                        <a href="index.php?controller=tienda&action=detalle&id=<?= (int) $t['id'] ?>" class="btn btn-sm btn-outline-primary">
                            Ver activos <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalTienda" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="modalTiendaTitulo">Tienda</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <input type="hidden" id="tId">
    <div class="mb-2"><label class="form-label small">CR (único)</label><input class="form-control" id="tCr"></div>
    <div class="mb-2"><label class="form-label small">Nombre</label><input class="form-control" id="tNombre"></div>
    <div class="mb-2"><label class="form-label small">Plaza</label>
      <select class="form-select" id="tPlaza"><?php foreach ($plazasTodas as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option><?php endforeach; ?></select></div>
  </div>
  <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
    <button class="btn btn-primary" id="btnGuardarTienda">Guardar</button></div>
</div></div></div>

<script>
document.getElementById('btnNuevaTienda')?.addEventListener('click', () => {
  document.getElementById('modalTiendaTitulo').textContent = 'Nueva tienda';
  document.getElementById('tId').value = ''; document.getElementById('tCr').value = ''; document.getElementById('tNombre').value = '';
  new bootstrap.Modal(document.getElementById('modalTienda')).show();
});
document.querySelectorAll('.js-editar').forEach(btn => btn.addEventListener('click', () => {
  document.getElementById('modalTiendaTitulo').textContent = 'Editar tienda';
  document.getElementById('tId').value = btn.dataset.id;
  document.getElementById('tCr').value = btn.dataset.cr;
  document.getElementById('tNombre').value = btn.dataset.nombre;
  document.getElementById('tPlaza').value = btn.dataset.plaza;
  new bootstrap.Modal(document.getElementById('modalTienda')).show();
}));
document.getElementById('btnGuardarTienda').addEventListener('click', async () => {
  const id = document.getElementById('tId').value;
  const data = {
    cr_tienda: document.getElementById('tCr').value.trim(),
    nombre: document.getElementById('tNombre').value.trim(),
    plaza_id: document.getElementById('tPlaza').value,
  };
  if (!data.cr_tienda || !data.nombre) { UI.toast('Faltan datos.', 'error'); return; }
  const r = id ? await Api.post('tiendaActualizar', { id, ...data }) : await Api.post('tiendaCrear', data);
  if (r.success) { UI.toast(r.message || 'Guardado.'); location.reload(); } else UI.toast(r.message || 'No se pudo guardar.', 'error');
});

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
