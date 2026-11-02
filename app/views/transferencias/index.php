<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Transferencias - Inventario 123</title>
<?php include ROOT_PATH . '/app/views/components/favicon.php'; ?>
</head>
<body>
<?php include ROOT_PATH . '/app/views/components/navbar.php'; ?>
<div class="container-fluid px-3 px-lg-4 py-4">
  <h4 class="fw-bold mb-3"><i class="fas fa-truck-ramp-box me-2 text-primary"></i>Transferencias</h4>
  <ul class="nav nav-pills gap-2 mb-3">
    <li class="nav-item"><button class="nav-link active" id="tabPend">Pendientes</button></li>
    <li class="nav-item"><button class="nav-link" id="tabComp">Completadas</button></li>
  </ul>
  <div class="card card-soft"><div class="list-group list-group-flush" id="lista"></div></div>
</div>
<script>
let completado = false;
async function cargar() {
  const r = await Api.get('transferenciasListar', { completado: completado ? 1 : 0 });
  const items = r.transferencias || [];
  document.getElementById('lista').innerHTML = items.map(t => `
    <div class="list-group-item d-flex justify-content-between align-items-center">
      <div>
        <div class="fw-semibold">${UI.esc([t.dispositivo_nombre, t.marca_nombre, t.modelo_nombre].filter(Boolean).join(' · '))}</div>
        <div class="text-muted small font-monospace">${UI.esc(t.serie || '')}${t.codigo_barras ? ' · CB:'+UI.esc(t.codigo_barras) : ''}</div>
        <div class="text-muted small">→ ${UI.esc(t.tienda_nombre)} (${UI.esc(t.cr_tienda)})</div>
      </div>
      ${completado
        ? `<span class="text-muted small">Completada por ${UI.esc(t.completado_por_nombre || '—')}</span>`
        : `<button class="btn btn-sm btn-primary" data-id="${t.id}">Marcar completada</button>`}
    </div>`).join('') || '<div class="list-group-item text-muted">Sin registros.</div>';

  document.querySelectorAll('#lista button[data-id]').forEach(b => b.addEventListener('click', async () => {
    const r2 = await Api.post('transferenciaCompletar', { id: b.dataset.id });
    if (r2.success) { UI.toast(r2.message); cargar(); } else UI.toast(r2.message, 'error');
  }));
}
document.getElementById('tabPend').addEventListener('click', (e) => { setTab(e.target, false); });
document.getElementById('tabComp').addEventListener('click', (e) => { setTab(e.target, true); });
function setTab(el, val) {
  document.querySelectorAll('.nav-link').forEach(x => x.classList.remove('active'));
  el.classList.add('active'); completado = val; cargar();
}
cargar();
</script>
</body>
</html>
