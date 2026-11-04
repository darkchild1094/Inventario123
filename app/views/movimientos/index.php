<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Movimientos - Inventario 123</title>
<?php include ROOT_PATH . '/app/views/components/favicon.php'; ?>
</head>
<body>
<?php include ROOT_PATH . '/app/views/components/navbar.php'; ?>
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <h4 class="fw-bold mb-0"><i class="fas fa-clock-rotate-left me-2 text-primary"></i>Movimientos <span class="text-muted fs-6" id="contador"></span></h4>
    <a id="btnExcel" class="btn btn-outline-secondary"><i class="fas fa-file-excel me-1"></i>Excel de esta pestaña</a>
  </div>
  <ul class="nav nav-pills gap-2 mb-3" id="tabsLugar">
    <li class="nav-item"><button class="nav-link active" data-v="">Todos</button></li>
    <li class="nav-item"><button class="nav-link" data-v="bodega">Bodega</button></li>
    <li class="nav-item"><button class="nav-link" data-v="usuario">Usuario</button></li>
    <li class="nav-item"><button class="nav-link" data-v="tienda">Tienda</button></li>
  </ul>
  <div id="buscador"></div>
  <div id="timeline"></div>
</div>

<script>
const EVENTO_LBL = {alta:'Alta',cambio_status:'Cambio de estatus',cambio_stock:'Cambio de stock',
  reemplazo_entra:'Instalación',reemplazo_sale:'Retiro',edicion:'Edición',baja:'Baja',eliminacion:'Eliminación'};
let lugarTipo = '', busqueda = '';

function fechaGrupo(iso) {
  const d = new Date(iso.replace(' ', 'T'));
  const hoy = new Date(); const ayer = new Date(); ayer.setDate(hoy.getDate() - 1);
  const mismaFecha = (a,b) => a.toDateString() === b.toDateString();
  if (mismaFecha(d, hoy)) return 'Hoy';
  if (mismaFecha(d, ayer)) return 'Ayer';
  return d.toLocaleDateString('es-MX', { day:'2-digit', month:'long', year:'numeric' });
}

async function cargar() {
  const params = { por_pagina: 100 };
  if (lugarTipo) params.lugar_tipo = lugarTipo;
  if (busqueda) params.serie = busqueda;
  const r = await Api.get('listarHistorial', params);
  const movs = r.movimientos || [];
  const grupos = {};
  movs.forEach(m => { const g = fechaGrupo(m.creado_en); (grupos[g] = grupos[g] || []).push(m); });
  const html = Object.entries(grupos).map(([g, items]) => `
    <div class="text-muted small text-uppercase fw-semibold mt-3 mb-2">${UI.esc(g)}</div>
    <div class="card card-soft mb-1"><div class="list-group list-group-flush">
      ${items.map(m => {
        const equipo = [m.eq_dispositivo, m.eq_marca, m.eq_modelo].filter(Boolean).join(' · ');
        const serie = m.eq_serie || m.eq_codigo_barras || '';
        const hora = (m.creado_en.split(' ')[1] || '').substring(0,5);
        return `<div class="list-group-item" style="cursor:pointer" onclick="UI.verDetalleActivo(${m.activo_id})">
          <div class="d-flex justify-content-between">
            <span class="badge bg-primary">${UI.esc(EVENTO_LBL[m.evento] || m.evento)}</span>
            <span class="text-muted small">${hora}</span>
          </div>
          <div class="fw-semibold mt-1">${UI.esc(equipo || '—')}</div>
          <div class="text-muted small font-monospace">${UI.esc(serie)}</div>
          <div class="small text-muted">${UI.esc(m.stock_ant_nombre || '—')} → ${UI.esc(m.stock_new_nombre || '—')}</div>
          <div class="small text-muted">Por ${UI.esc(m.actor_nombre || '—')}${m.nota ? ' · ' + UI.esc(m.nota) : ''}</div>
        </div>`;
      }).join('')}
    </div></div>`).join('');
  document.getElementById('timeline').innerHTML = html || '<div class="text-muted mt-3">Sin movimientos.</div>';
  document.getElementById('contador').textContent = `(${movs.length})`;
  const p = new URLSearchParams({ controller: 'export', action: 'movimientos' });
  if (lugarTipo) p.set('lugar_tipo', lugarTipo);
  if (busqueda) p.set('serie', busqueda);
  document.getElementById('btnExcel').href = 'index.php?' + p.toString();
}

document.querySelectorAll('#tabsLugar .nav-link').forEach(b => b.addEventListener('click', () => {
  document.querySelectorAll('#tabsLugar .nav-link').forEach(x => x.classList.remove('active'));
  b.classList.add('active'); lugarTipo = b.dataset.v; cargar();
}));
UI.buscadorPill(document.getElementById('buscador'), 'Buscar por activo, serie o código…', (v) => { busqueda = v; cargar(); });
cargar();
</script>
</body>
</html>
