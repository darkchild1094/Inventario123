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
  <a href="index.php?controller=rentec" class="text-decoration-none small mb-2 d-inline-block"><i class="fas fa-arrow-left me-1"></i>RENTEC</a>
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div id="cabecera"></div>
    <div class="d-flex gap-2">
      <a id="btnExcel" class="btn btn-outline-secondary"><i class="fas fa-file-excel me-1"></i>Excel</a>
      <button class="btn btn-primary" id="btnAgregar"><i class="fas fa-plus me-1"></i>Añadir activo</button>
      <button class="btn btn-outline-danger d-none" id="btnBorrar">Borrar proyecto</button>
      <button class="btn btn-outline-secondary d-none" id="btnFinalizar">Finalizar</button>
    </div>
  </div>
  <ul class="nav nav-tabs mb-3" id="tabsRentecDetalle">
    <li class="nav-item"><button class="nav-link active" data-pane="pendientes">Pendientes <span class="badge bg-warning text-dark" id="cntPendientes">0</span></button></li>
    <li class="nav-item"><button class="nav-link" data-pane="instalados">Instalados <span class="badge bg-success" id="cntInstalados">0</span></button></li>
    <li class="nav-item"><button class="nav-link" data-pane="retirados">Retirados <span class="badge bg-secondary" id="cntRetirados">0</span></button></li>
  </ul>
  <div id="pendientes"></div>
  <div id="instalados" class="d-none"></div>
  <div id="retirados" class="d-none"></div>
</div>

<div class="modal fade" id="modalAgregar" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Añadir activo al proyecto</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="mb-2"><label class="form-label small">Tipo de equipo</label><select class="form-select" id="fDispositivo"></select></div>
    <div class="mb-2"><label class="form-label small">Modelo</label><select class="form-select" id="fModelo"></select></div>
    <div class="mb-2"><label class="form-label small">Serie</label><input class="form-control font-monospace" id="fSerie"></div>
    <div class="mb-2"><label class="form-label small">Código de barras (opcional)</label><input class="form-control font-monospace" id="fCodigo"></div>
  </div>
  <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
    <button class="btn btn-primary" id="btnGuardar">Guardar</button></div>
</div></div></div>

<div class="modal fade" id="modalInstalar" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Instalar</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="mb-2"><label class="form-label small">Tienda</label><select class="form-select" id="iTienda"></select></div>
    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="iRetiraChk">
      <label class="form-check-label" for="iRetiraChk">Retirar equipo existente de la tienda</label></div>
    <div id="iRetiraBox" class="d-none">
      <div class="form-text mb-1">Si el nuevo equipo sustituye a varias unidades viejas (ej. un UPS nuevo reemplaza a 3 chicos), agrégalas todas aquí.</div>
      <div id="iRetiraFilas"></div>
      <button type="button" class="btn btn-sm btn-outline-primary mb-2" id="btnIRetiraMas"><i class="fas fa-plus me-1"></i>Agregar otro equipo retirado</button>
      <label class="form-label small d-block">Destino de los equipos retirados</label>
      <select class="form-select" id="iRetiraDestino">
        <option value="en_bodega">A bodega</option><option value="baja">Baja</option><option value="garantia">Garantía</option>
      </select>
    </div>
  </div>
  <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
    <button class="btn btn-primary" id="btnInstalarOk">Instalar</button></div>
</div></div></div>

<script>
const ID = <?= (int) $id ?>;
let catalogos = null, proyecto = null, pendienteActual = null;

async function cargar() {
  const d = await Api.get('rentecDetalle', { id: ID });
  proyecto = d;
  document.getElementById('cabecera').innerHTML = `
    <h4 class="fw-bold mb-0">${UI.esc(d.folio)} · ${UI.esc(d.nombre)}</h4>
    <div class="text-muted small">${UI.esc(d.plaza_nombre || '')} · ${UI.esc(d.fecha_inicio||'')} – ${UI.esc(d.fecha_fin||'')} ·
      <span class="badge bg-${d.estado === 'abierto' ? 'primary' : 'secondary'}">${d.estado === 'abierto' ? 'Abierto' : 'Finalizado'}</span></div>`;
  document.getElementById('pendientes').innerHTML = (d.detalle_recibidos || []).map(a => `
    <div class="card mb-1 border-warning" style="cursor:pointer" onclick="abrirInstalar(${a.activo_id})">
      <div class="card-body py-2">
        <span class="badge bg-warning text-dark me-2">Pendiente</span>
        ${UI.esc([a.dispositivo_nombre, a.marca_nombre, a.modelo_nombre].filter(Boolean).join(' · '))}
        <span class="text-muted small font-monospace ms-2">${UI.esc(a.serie||'')}${a.codigo_barras ? ' · CB:'+UI.esc(a.codigo_barras):''}</span>
        <button class="btn btn-sm btn-link text-muted float-end p-0" onclick="event.stopPropagation(); UI.verDetalleActivo(${a.activo_id})" title="Ver detalle"><i class="fas fa-circle-info"></i></button>
      </div>
    </div>`).join('') || '<div class="text-muted small">Sin pendientes.</div>';
  document.getElementById('instalados').innerHTML = (d.detalle_instalados || []).map(m => `
    <div class="card mb-1 border-success" style="cursor:pointer" onclick="UI.verDetalleActivo(${m.activo_id})"><div class="card-body py-2">
      <span class="badge bg-success me-2">Instalado</span>
      ${UI.esc([m.dispositivo_nombre, m.marca_nombre, m.modelo_nombre].filter(Boolean).join(' · '))}
      <span class="text-muted small font-monospace ms-2">${UI.esc(m.serie_entra||'')}</span>
      <div class="text-muted small">${UI.esc(m.tienda_nombre || '')}${m.serie_sale ? ' · sustituyó a ' + UI.esc(m.serie_sale) : ''}</div>
    </div></div>`).join('') || '<div class="text-muted small">Sin instalados.</div>';

  // Retirados = el lado "sale" de cada reemplazo (ya pareado con su
  // instalación) + los que salieron sin que entrara nada en su lugar.
  const retiradosPareados = (d.detalle_instalados || []).filter(m => m.serie_sale).map(m => ({
    activo_id: m.activo_relacionado_id,
    equipo: [m.dispositivo_sale_nombre, m.marca_sale_nombre, m.modelo_sale_nombre].filter(Boolean).join(' · '),
    serie: m.serie_sale, codigo: m.codigo_sale, tienda: m.tienda_nombre,
    nota: 'sustituido por ' + (m.serie_entra || ''),
  }));
  const retiradosSolos = (d.detalle_retirados_sin_reemplazo || []).map(r => ({
    activo_id: r.activo_id,
    equipo: [r.dispositivo_nombre, r.marca_nombre, r.modelo_nombre].filter(Boolean).join(' · '),
    serie: r.serie, codigo: r.codigo_barras, tienda: r.tienda_nombre,
    nota: 'sin reemplazo directo',
  }));
  const retirados = [...retiradosPareados, ...retiradosSolos];
  document.getElementById('retirados').innerHTML = retirados.map(r => `
    <div class="card mb-1 border-secondary" style="cursor:pointer" onclick="UI.verDetalleActivo(${r.activo_id})"><div class="card-body py-2">
      <span class="badge bg-secondary me-2">Retirado</span>
      ${UI.esc(r.equipo || '—')}
      <span class="text-muted small font-monospace ms-2">${UI.esc(r.serie||'')}${r.codigo ? ' · CB:'+UI.esc(r.codigo):''}</span>
      <div class="text-muted small">${UI.esc(r.tienda || '—')} · ${UI.esc(r.nota)}</div>
    </div></div>`).join('') || '<div class="text-muted small">Sin retirados.</div>';

  document.getElementById('cntPendientes').textContent = (d.detalle_recibidos || []).length;
  document.getElementById('cntInstalados').textContent = (d.detalle_instalados || []).length;
  document.getElementById('cntRetirados').textContent = retirados.length;

  document.getElementById('btnExcel').href = 'index.php?controller=export&action=rentec&id=' + ID;
}

document.querySelectorAll('#tabsRentecDetalle .nav-link').forEach(btn => btn.addEventListener('click', () => {
  document.querySelectorAll('#tabsRentecDetalle .nav-link').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  ['pendientes', 'instalados', 'retirados'].forEach(p => document.getElementById(p).classList.toggle('d-none', p !== btn.dataset.pane));
}));

function llenarModelos() {
  const dispId = parseInt(document.getElementById('fDispositivo').value || 0);
  document.getElementById('fModelo').innerHTML = catalogos.modelos.filter(m => m.dispositivo_id === dispId)
    .map(m => `<option value="${m.id}">${UI.esc(m.marca_nombre)} ${UI.esc(m.nombre)}</option>`).join('');
}

document.getElementById('btnAgregar').addEventListener('click', () => new bootstrap.Modal(document.getElementById('modalAgregar')).show());
document.getElementById('btnGuardar').addEventListener('click', async () => {
  const serie = document.getElementById('fSerie').value.trim();
  if (!serie) { UI.toast('Falta la serie.', 'error'); return; }
  const r = await Api.post('guardarActivo', {
    serie, codigo_barras: document.getElementById('fCodigo').value.trim() || null,
    modelo_id: document.getElementById('fModelo').value, status: 'en_bodega',
    plaza_id: proyecto.plaza_id, proyecto_rentec_id: ID,
  });
  if (r.success) {
    UI.toast('Activo agregado.'); bootstrap.Modal.getInstance(document.getElementById('modalAgregar')).hide();
    document.getElementById('fSerie').value=''; document.getElementById('fCodigo').value=''; cargar();
  } else UI.toast(r.message || 'No se pudo guardar.', 'error');
});

async function abrirInstalar(activoId) {
  pendienteActual = activoId;
  const tiendas = catalogos.tiendas.filter(t => t.plaza_id === proyecto.plaza_id);
  document.getElementById('iTienda').innerHTML = tiendas.map(t => `<option value="${t.id}">${UI.esc(t.nombre)}</option>`).join('');
  document.getElementById('iRetiraChk').checked = false;
  document.getElementById('iRetiraBox').classList.add('d-none');
  await cargarRetirables();
  document.getElementById('iTienda').onchange = cargarRetirables;
  new bootstrap.Modal(document.getElementById('modalInstalar')).show();
}
let iRetiraOpts = '';
async function cargarRetirables() {
  const tiendaId = document.getElementById('iTienda').value;
  const r = await Api.get('listarActivos', { modulo: 'tiendas', tienda_id: tiendaId, status: 'en_uso', por_pagina: 200 });
  iRetiraOpts = (r.activos || []).map(a =>
    `<option value="${a.id}">${UI.esc([a.dispositivo_nombre, a.modelo_nombre].filter(Boolean).join(' · '))} - ${UI.esc(a.serie||'')}</option>`).join('')
    || '<option value="">(sin equipo instalado en esta tienda)</option>';
  document.getElementById('iRetiraFilas').innerHTML = '';
  agregarFilaRetiro('iRetiraFilas', iRetiraOpts);
}
function agregarFilaRetiro(contenedorId, opts) {
  const cont = document.getElementById(contenedorId);
  const fila = document.createElement('div');
  fila.className = 'input-group mb-2 fila-retiro';
  fila.innerHTML = `<select class="form-select">${opts}</select>
    <button type="button" class="btn btn-outline-danger btn-quitar-fila" tabindex="-1"><i class="fas fa-xmark"></i></button>`;
  fila.querySelector('.btn-quitar-fila').addEventListener('click', () => {
    if (cont.querySelectorAll('.fila-retiro').length > 1) fila.remove();
  });
  cont.appendChild(fila);
}
document.getElementById('btnIRetiraMas').addEventListener('click', () => agregarFilaRetiro('iRetiraFilas', iRetiraOpts));
document.getElementById('iRetiraChk').addEventListener('change', (e) => {
  document.getElementById('iRetiraBox').classList.toggle('d-none', !e.target.checked);
});
document.getElementById('btnInstalarOk').addEventListener('click', async () => {
  const data = {
    id: pendienteActual, status: 'en_uso',
    tienda_uso_id: document.getElementById('iTienda').value,
    proyecto_rentec_id: ID,
  };
  let retirarIds = [];
  if (document.getElementById('iRetiraChk').checked) {
    retirarIds = Array.from(document.querySelectorAll('#iRetiraFilas .fila-retiro select'))
      .map(s => s.value).filter(v => v);
    retirarIds = [...new Set(retirarIds)];
  }
  if (retirarIds.length) {
    data.reemplaza_activo_id = retirarIds[0];
    data.salida_destino = document.getElementById('iRetiraDestino').value;
  }
  const r = await Api.post('actualizarActivo', data);
  if (!r.success) { UI.toast(r.message || 'No se pudo instalar.', 'error'); return; }
  // Equipos retirados adicionales (más allá del primero, que ya quedó
  // ligado como reemplazo): se retiran igual pero sin el enlace 1 a 1,
  // porque la bitácora solo soporta un activo_relacionado_id por entrada.
  for (const extraId of retirarIds.slice(1)) {
    await Api.post('actualizarActivo', {
      id: extraId, status: document.getElementById('iRetiraDestino').value,
      tienda_uso_id: document.getElementById('iTienda').value, proyecto_rentec_id: ID,
      motivo: 'Retiro adicional junto con la instalación de activo ' + pendienteActual,
    });
  }
  UI.toast('Instalado.'); bootstrap.Modal.getInstance(document.getElementById('modalInstalar')).hide(); cargar();
});

document.getElementById('btnFinalizar').addEventListener('click', async () => {
  if (!await UI.confirmar('¿Finalizar este proyecto RENTEC?')) return;
  const r = await Api.post('rentecCerrar', { id: ID });
  if (r.success) { UI.toast('Proyecto finalizado.'); cargar(); } else UI.toast(r.message, 'error');
});
document.getElementById('btnBorrar').addEventListener('click', async () => {
  if (!await UI.confirmar('¿Borrar este proyecto RENTEC?')) return;
  const r = await Api.postJson('rentecEliminar', { id: ID });
  if (r.success) { UI.toast('Proyecto borrado.'); location.href = 'index.php?controller=rentec'; }
  else UI.toast(r.message, 'error');
});

document.addEventListener('DOMContentLoaded', async () => {
  const perfil = await Api.get('obtenerPerfil');
  catalogos = await Api.get('obtenerCatalogos');
  const selD = document.getElementById('fDispositivo');
  selD.innerHTML = catalogos.dispositivos.map(d => `<option value="${d.id}">${UI.esc(d.nombre)}</option>`).join('');
  selD.addEventListener('change', llenarModelos);
  llenarModelos();
  await cargar();
  if (perfil.puedeGestionarRentec) {
    document.getElementById('btnBorrar').classList.remove('d-none');
    document.getElementById('btnFinalizar').classList.remove('d-none');
  }
});
</script>
</body>
</html>
