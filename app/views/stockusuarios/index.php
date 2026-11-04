<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Stock usuarios - Inventario 123</title>
<?php include ROOT_PATH . '/app/views/components/favicon.php'; ?>
<style>
.usr-card { cursor: pointer; }
.usr-avatar { width:38px; height:38px; border-radius:50%; background:#667eea; color:#fff;
  display:flex; align-items:center; justify-content:center; font-weight:700; flex:0 0 38px; }
</style>
</head>
<body>
<?php include ROOT_PATH . '/app/views/components/navbar.php'; ?>
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="fw-bold mb-0"><i class="fas fa-people-group me-2 text-primary"></i>Stock usuarios <span class="text-muted fs-6" id="contador"></span></h4>
    <a id="btnExcel" class="btn btn-outline-secondary"><i class="fas fa-file-excel me-1"></i>Excel</a>
  </div>
  <div id="tabsPlaza"></div>
  <div id="lista"></div>
</div>

<script>
let catalogos = null, plazaActual = null;

function tipoLabel(t) { return ({admin:'Admin',coordinador:'Coordinador',pfs:'PFS',ati:'ATI'})[t] || t; }

async function cargar() {
  if (!plazaActual) return;
  const usuarios = await Api.get('stockUsuarios', { plaza_id: plazaActual });
  document.getElementById('lista').innerHTML = (usuarios || []).map(u => `
    <div class="card card-soft mb-2">
      <div class="card-body py-2 d-flex align-items-center gap-3 usr-card" data-id="${u.id}">
        <div class="usr-avatar">${UI.esc((u.nombre||'?').substring(0,1).toUpperCase())}</div>
        <div class="flex-grow-1">
          <div class="fw-semibold">${UI.esc(u.nombre)}</div>
          <div class="text-muted small text-uppercase">${tipoLabel(u.tipo)}</div>
        </div>
        <span class="badge bg-secondary">${u.activos_count} activo(s)</span>
        <i class="fas fa-chevron-down text-muted"></i>
      </div>
      <div class="card-body pt-0 d-none stock-detalle">
        ${(u.activos||[]).length ? `<table class="table table-sm mb-0"><tbody>
          ${u.activos.map(a => `<tr style="cursor:pointer" onclick="UI.verDetalleActivo(${a.id})"><td>${UI.esc([a.tipo,a.modelo].filter(Boolean).join(' · '))}</td>
            <td class="text-muted small font-monospace">${UI.esc(a.serie||'')}${a.codigo_barras ? ' · CB:'+UI.esc(a.codigo_barras) : ''}</td>
            <td class="text-end"><i class="fas fa-circle-info text-muted"></i></td></tr>`).join('')}
        </tbody></table>` : '<div class="text-muted small">Sin activos.</div>'}
      </div>
    </div>`).join('') || '<div class="text-muted">Sin usuarios en esta plaza.</div>';

  document.querySelectorAll('.usr-card').forEach(c => c.addEventListener('click', () => {
    c.parentElement.querySelector('.stock-detalle').classList.toggle('d-none');
  }));
  const totalActivos = (usuarios || []).reduce((acc, u) => acc + (u.activos_count || 0), 0);
  document.getElementById('contador').textContent = `(${(usuarios||[]).length} usuarios · ${totalActivos} activos)`;
  document.getElementById('btnExcel').href = UI.excelHref('stock_usuarios', { plaza_id: plazaActual });
}

document.addEventListener('DOMContentLoaded', async () => {
  catalogos = await Api.get('obtenerCatalogos');
  UI.tabsPlaza(document.getElementById('tabsPlaza'), catalogos.plazas, (id) => { plazaActual = id; cargar(); }, false);
  plazaActual = catalogos.plazas[0]?.id || null;
  cargar();
});
</script>
</body>
</html>
