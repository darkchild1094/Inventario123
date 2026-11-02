<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RENTEC - Inventario 123</title>
<?php include ROOT_PATH . '/app/views/components/favicon.php'; ?>
</head>
<body>
<?php include ROOT_PATH . '/app/views/components/navbar.php'; ?>
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="fw-bold mb-0"><i class="fas fa-arrows-rotate me-2 text-primary"></i>RENTEC</h4>
    <button class="btn btn-primary d-none" id="btnNuevo"><i class="fas fa-plus me-1"></i>Agregar proyecto</button>
  </div>
  <div id="lista"></div>
</div>

<div class="modal fade" id="modalNuevo" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Nuevo proyecto RENTEC</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="mb-2"><label class="form-label small">Nombre</label><input class="form-control" id="fNombre"></div>
    <div class="mb-2"><label class="form-label small">Plaza</label><select class="form-select" id="fPlaza"></select></div>
    <div class="row">
      <div class="col mb-2"><label class="form-label small">Inicio</label><input type="date" class="form-control" id="fInicio"></div>
      <div class="col mb-2"><label class="form-label small">Fin</label><input type="date" class="form-control" id="fFin"></div>
    </div>
  </div>
  <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
    <button class="btn btn-primary" id="btnGuardar">Crear</button></div>
</div></div></div>

<script>
let catalogos = null;
async function cargar() {
  const r = await Api.get('rentecListar');
  const items = r.proyectos || r || [];
  document.getElementById('lista').innerHTML = (items || []).map(p => {
    const total = (parseInt(p.instalados)||0) + (parseInt(p.recibidos)||0);
    const pct = total ? Math.round((p.instalados / total) * 100) : 0;
    return `<a href="index.php?controller=rentec&action=detalle&id=${p.id}" class="card card-soft mb-2 text-decoration-none text-dark d-block">
      <div class="card-body">
        <div class="d-flex justify-content-between">
          <div><span class="fw-semibold">${UI.esc(p.folio)}</span> · ${UI.esc(p.nombre)}</div>
          <span class="badge bg-${p.estado === 'abierto' ? 'primary' : 'secondary'}">${p.estado === 'abierto' ? 'Abierto' : 'Finalizado'}</span>
        </div>
        <div class="text-muted small">${UI.esc(p.plaza_nombre || '')} · ${UI.esc(p.fecha_inicio || '')} – ${UI.esc(p.fecha_fin || '')}</div>
        <div class="d-flex align-items-center gap-2 mt-2">
          <div class="bar-track flex-grow-1"><div class="bar-fill" style="width:${pct}%"></div></div>
          <small class="text-muted">${p.instalados}/${total} instalados (${pct}%)</small>
        </div>
      </div>
    </a>`;
  }).join('') || '<div class="text-muted">Sin proyectos RENTEC.</div>';
}

document.addEventListener('DOMContentLoaded', async () => {
  const perfil = await Api.get('obtenerPerfil');
  if (perfil.puedeGestionarRentec) document.getElementById('btnNuevo').classList.remove('d-none');
  catalogos = await Api.get('obtenerCatalogos');
  document.getElementById('fPlaza').innerHTML = catalogos.plazas.map(p => `<option value="${p.id}">${UI.esc(p.nombre)}</option>`).join('');
  cargar();
});
document.getElementById('btnNuevo').addEventListener('click', () => new bootstrap.Modal(document.getElementById('modalNuevo')).show());
document.getElementById('btnGuardar').addEventListener('click', async () => {
  const nombre = document.getElementById('fNombre').value.trim();
  if (!nombre) { UI.toast('Falta el nombre.', 'error'); return; }
  const r = await Api.postJson('rentecCrear', {
    nombre, plaza_id: document.getElementById('fPlaza').value,
    fecha_inicio: document.getElementById('fInicio').value || null,
    fecha_fin: document.getElementById('fFin').value || null,
  });
  if (r.success) { UI.toast('Proyecto creado: ' + r.folio); bootstrap.Modal.getInstance(document.getElementById('modalNuevo')).hide(); cargar(); }
  else UI.toast(r.message || 'No se pudo crear.', 'error');
});
</script>
<style>.bar-track{background:#e9ecef;border-radius:6px;height:8px;overflow:hidden}.bar-fill{height:100%;background:#0d6efd}</style>
</body>
</html>
