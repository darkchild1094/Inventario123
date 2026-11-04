<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bodega - Inventario 123</title>
<?php include ROOT_PATH . '/app/views/components/favicon.php'; ?>
</head>
<body>
<?php include ROOT_PATH . '/app/views/components/navbar.php'; ?>
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="fw-bold mb-0"><i class="fas fa-warehouse me-2 text-primary"></i>Bodega <span class="text-muted fs-6" id="contador"></span></h4>
    <div class="d-flex gap-2">
      <a id="btnExcel" class="btn btn-outline-secondary"><i class="fas fa-file-excel me-1"></i>Excel</a>
      <button class="btn btn-primary" id="btnAgregar"><i class="fas fa-plus me-1"></i>Agregar activo</button>
    </div>
  </div>
  <div id="tabsPlaza"></div>
  <div class="d-flex align-items-end gap-2 mb-3 flex-wrap">
    <div id="buscador" class="mb-0"></div>
    <div style="max-width:280px">
      <label class="form-label small text-muted mb-1">Filtrar por proyecto RENTEC</label>
      <select class="form-select" id="filtroRentec"><option value="">Todos</option></select>
    </div>
  </div>
  <div id="barraSel"></div>
  <div class="card card-soft">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="table-light"><tr>
          <th style="width:2.5rem"></th><th>Equipo</th><th>Estado</th><th>Procedencia</th><th>Plaza</th><th></th>
        </tr></thead>
        <tbody id="tbody"></tbody>
      </table>
    </div>
    <div class="p-3 text-muted small" id="vacio" style="display:none">Sin activos en bodega.</div>
  </div>
</div>

<div class="modal fade" id="modalAgregar" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Agregar activo a bodega</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="mb-2"><label class="form-label small">Tipo de equipo</label>
      <select class="form-select" id="fDispositivo"></select></div>
    <div class="mb-2"><label class="form-label small">Modelo</label>
      <select class="form-select" id="fModelo"></select></div>
    <div class="mb-2"><label class="form-label small">Serie</label>
      <input class="form-control font-monospace" id="fSerie"></div>
    <div class="mb-2"><label class="form-label small">Código de barras (opcional)</label>
      <input class="form-control font-monospace" id="fCodigo"></div>
  </div>
  <div class="modal-footer">
    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
    <button class="btn btn-primary" id="btnGuardar">Guardar</button>
  </div>
</div></div></div>

<script>
const MODULO = 'bodega';
let plazaActual = null, busqueda = '', catalogos = null, seleccion = new Set(), proyectoRentecActual = '';

async function cargarCatalogos() {
  catalogos = await Api.get('obtenerCatalogos');
  UI.tabsPlaza(document.getElementById('tabsPlaza'), catalogos.plazas, (id) => { plazaActual = id; cargar(); }, true);
  const selD = document.getElementById('fDispositivo');
  selD.innerHTML = catalogos.dispositivos.map(d => `<option value="${d.id}">${UI.esc(d.nombre)}</option>`).join('');
  selD.addEventListener('change', llenarModelos);
  llenarModelos();

  const proyectos = await Api.get('rentecListar');
  const selR = document.getElementById('filtroRentec');
  selR.innerHTML = '<option value="">Todos</option>' + (proyectos || [])
    .map(p => `<option value="${p.id}">${UI.esc(p.folio)} · ${UI.esc(p.nombre)}</option>`).join('');
  selR.addEventListener('change', () => { proyectoRentecActual = selR.value; cargar(); });
}
function llenarModelos() {
  const dispId = parseInt(document.getElementById('fDispositivo').value || 0);
  const opts = catalogos.modelos.filter(m => m.dispositivo_id === dispId);
  document.getElementById('fModelo').innerHTML = opts.map(m => `<option value="${m.id}">${UI.esc(m.marca_nombre)} ${UI.esc(m.nombre)}</option>`).join('');
}

async function cargar() {
  const params = { modulo: MODULO, por_pagina: 200 };
  if (plazaActual) params.plaza_id = plazaActual;
  if (busqueda) params.busqueda = busqueda;
  if (proyectoRentecActual) params.proyecto_rentec_id = proyectoRentecActual;
  const r = await Api.get('listarActivos', params);
  const activos = r.activos || [];
  document.getElementById('tbody').innerHTML = activos.map(a => UI.filaActivo(a, { seleccionable: true })).join('');
  document.getElementById('vacio').style.display = activos.length ? 'none' : 'block';
  document.getElementById('contador').textContent = `(${r.paginacion?.total_resultados ?? activos.length})`;
  seleccion.clear();
  actualizarSeleccion();
  document.querySelectorAll('.sel-chk').forEach(chk => chk.addEventListener('change', (e) => {
    const id = parseInt(e.target.closest('tr').dataset.id);
    if (e.target.checked) seleccion.add(id); else seleccion.delete(id);
    actualizarSeleccion();
  }));
  document.getElementById('btnExcel').href = UI.excelHref(MODULO, plazaActual ? { plaza_id: plazaActual } : {});
}

let barraApi;
function actualizarSeleccion() { barraApi.actualizar(seleccion.size); }

document.getElementById('btnAgregar').addEventListener('click', () => {
  new bootstrap.Modal(document.getElementById('modalAgregar')).show();
});
document.getElementById('btnGuardar').addEventListener('click', async () => {
  const serie = document.getElementById('fSerie').value.trim();
  if (!serie) { UI.toast('Falta la serie.', 'error'); return; }
  const r = await Api.post('guardarActivo', {
    serie, codigo_barras: document.getElementById('fCodigo').value.trim() || null,
    modelo_id: document.getElementById('fModelo').value, status: 'en_bodega',
    plaza_id: plazaActual || catalogos.plazas[0]?.id,
  });
  if (r.success) {
    UI.toast('Activo agregado.');
    bootstrap.Modal.getInstance(document.getElementById('modalAgregar')).hide();
    document.getElementById('fSerie').value = ''; document.getElementById('fCodigo').value = '';
    cargar();
  } else if (r.ya_existe) {
    UI.toast(r.message, 'error');
  } else {
    UI.toast(r.message || 'No se pudo guardar.', 'error');
  }
});

document.addEventListener('DOMContentLoaded', async () => {
  barraApi = UI.barraSeleccion(document.getElementById('barraSel'), {
    onEnviar: abrirEnviar,
    onEliminar: eliminarSeleccion,
    onCancelar: () => { seleccion.clear(); document.querySelectorAll('.sel-chk').forEach(c => c.checked = false); actualizarSeleccion(); },
  });
  UI.buscadorPill(document.getElementById('buscador'), 'Buscar por serie, código o modelo…', (v) => { busqueda = v; cargar(); });
  await cargarCatalogos();
  await cargar();
});

async function eliminarSeleccion() {
  if (!await UI.confirmar(`¿Eliminar ${seleccion.size} activo(s)? Esta acción no se puede deshacer.`)) return;
  for (const id of seleccion) await Api.post('eliminarActivo', { id });
  UI.toast('Eliminado(s).');
  cargar();
}

function abrirEnviar() {
  const plazasOpt = catalogos.plazas.filter(p => p.id !== plazaActual);
  const id = 'modalEnviar';
  document.getElementById(id)?.remove();
  const div = document.createElement('div');
  div.innerHTML = `<div class="modal fade" id="${id}" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Enviar ${seleccion.size} activo(s)</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="form-check mb-2"><input class="form-check-input" type="radio" name="destino" value="bodega" id="rdBodega" checked>
        <label class="form-check-label" for="rdBodega">A bodega de otra plaza</label></div>
      <select class="form-select mb-3" id="selPlazaDestino">${plazasOpt.map(p => `<option value="${p.id}">${UI.esc(p.nombre)}</option>`).join('')}</select>
      <div class="form-check mb-2"><input class="form-check-input" type="radio" name="destino" value="usuario" id="rdUsuario">
        <label class="form-check-label" for="rdUsuario">A un usuario</label></div>
      <select class="form-select" id="selUsuarioDestino" disabled>${(catalogos.usuarios||[]).map(u => `<option value="${u.id}">${UI.esc(u.nombre)}</option>`).join('')}</select>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      <button class="btn btn-primary" id="btnEnviarOk">Enviar</button></div>
  </div></div></div>`;
  document.body.appendChild(div);
  const modalEl = div.querySelector('.modal');
  div.querySelectorAll('input[name=destino]').forEach(r => r.addEventListener('change', () => {
    document.getElementById('selPlazaDestino').disabled = r.value !== 'bodega' || !r.checked;
    document.getElementById('selUsuarioDestino').disabled = r.value !== 'usuario' || !r.checked;
  }));
  modalEl.querySelector('#btnEnviarOk').addEventListener('click', async () => {
    const aBodega = div.querySelector('#rdBodega').checked;
    const data = { activos: Array.from(seleccion), tipo: aBodega ? 'bodega' : 'usuario' };
    if (aBodega) data.plaza_id = document.getElementById('selPlazaDestino').value;
    else data.usuario_id = document.getElementById('selUsuarioDestino').value;
    const r = await Api.post('enviarActivos', data);
    if (r.success) { UI.toast(r.message || 'Enviado(s).'); bootstrap.Modal.getInstance(modalEl).hide(); cargar(); }
    else UI.toast(r.message || 'No se pudo enviar.', 'error');
  });
  modalEl.addEventListener('hidden.bs.modal', () => div.remove());
  new bootstrap.Modal(modalEl).show();
}
</script>
</body>
</html>
