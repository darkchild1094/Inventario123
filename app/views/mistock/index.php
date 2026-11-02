<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mi Stock - Inventario 123</title>
<?php include ROOT_PATH . '/app/views/components/favicon.php'; ?>
</head>
<body>
<?php include ROOT_PATH . '/app/views/components/navbar.php'; ?>
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="fw-bold mb-0"><i class="fas fa-toolbox me-2 text-primary"></i>Mi Stock</h4>
    <div class="d-flex gap-2">
      <a id="btnExcel" class="btn btn-outline-secondary"><i class="fas fa-file-excel me-1"></i>Excel</a>
      <button class="btn btn-primary" id="btnAgregar"><i class="fas fa-plus me-1"></i>Agregar activo</button>
    </div>
  </div>
  <div id="buscador"></div>
  <div id="barraSel"></div>
  <div class="card card-soft">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="table-light"><tr>
          <th style="width:2.5rem"></th><th>Equipo</th><th>Estado</th><th>Procedencia</th><th>Plaza</th>
        </tr></thead>
        <tbody id="tbody"></tbody>
      </table>
    </div>
    <div class="p-3 text-muted small" id="vacio" style="display:none">No tienes activos en tu stock.</div>
  </div>
</div>

<div class="modal fade" id="modalAgregar" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Agregar activo a mi stock</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
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
const MODULO = 'mi_stock';
const MI_PLAZA = <?= (int) ($_SESSION['usuario']['plaza_id'] ?? 0) ?>;
let busqueda = '', catalogos = null, seleccion = new Set(), barraApi;

async function cargarCatalogos() {
  catalogos = await Api.get('obtenerCatalogos');
  const selD = document.getElementById('fDispositivo');
  selD.innerHTML = catalogos.dispositivos.map(d => `<option value="${d.id}">${UI.esc(d.nombre)}</option>`).join('');
  selD.addEventListener('change', llenarModelos);
  llenarModelos();
}
function llenarModelos() {
  const dispId = parseInt(document.getElementById('fDispositivo').value || 0);
  const opts = catalogos.modelos.filter(m => m.dispositivo_id === dispId);
  document.getElementById('fModelo').innerHTML = opts.map(m => `<option value="${m.id}">${UI.esc(m.marca_nombre)} ${UI.esc(m.nombre)}</option>`).join('');
}

async function cargar() {
  const params = { modulo: MODULO, por_pagina: 200 };
  if (busqueda) params.busqueda = busqueda;
  const r = await Api.get('listarActivos', params);
  const activos = r.activos || [];
  document.getElementById('tbody').innerHTML = activos.map(a => UI.filaActivo(a, { seleccionable: true })).join('');
  document.getElementById('vacio').style.display = activos.length ? 'none' : 'block';
  seleccion.clear(); actualizarSeleccion();
  document.querySelectorAll('.sel-chk').forEach(chk => chk.addEventListener('change', (e) => {
    const id = parseInt(e.target.closest('tr').dataset.id);
    if (e.target.checked) seleccion.add(id); else seleccion.delete(id);
    actualizarSeleccion();
  }));
  document.getElementById('btnExcel').href = UI.excelHref(MODULO);
}
function actualizarSeleccion() { barraApi.actualizar(seleccion.size); }

document.getElementById('btnAgregar').addEventListener('click', () => new bootstrap.Modal(document.getElementById('modalAgregar')).show());
document.getElementById('btnGuardar').addEventListener('click', async () => {
  const serie = document.getElementById('fSerie').value.trim();
  if (!serie) { UI.toast('Falta la serie.', 'error'); return; }
  const r = await Api.post('guardarActivo', {
    serie, codigo_barras: document.getElementById('fCodigo').value.trim() || null,
    modelo_id: document.getElementById('fModelo').value, status: 'asignado', plaza_id: MI_PLAZA,
  });
  if (r.success) {
    UI.toast('Activo agregado.');
    bootstrap.Modal.getInstance(document.getElementById('modalAgregar')).hide();
    document.getElementById('fSerie').value = ''; document.getElementById('fCodigo').value = '';
    cargar();
  } else UI.toast(r.message || 'No se pudo guardar.', 'error');
});

document.addEventListener('DOMContentLoaded', async () => {
  barraApi = UI.barraSeleccion(document.getElementById('barraSel'), {
    onEnviar: abrirEnviar, onEliminar: eliminarSeleccion,
    onCancelar: () => { seleccion.clear(); document.querySelectorAll('.sel-chk').forEach(c => c.checked = false); actualizarSeleccion(); },
  });
  UI.buscadorPill(document.getElementById('buscador'), 'Buscar por serie, código o modelo…', (v) => { busqueda = v; cargar(); });
  await cargarCatalogos();
  await cargar();
});

async function eliminarSeleccion() {
  if (!await UI.confirmar(`¿Eliminar ${seleccion.size} activo(s)? Esta acción no se puede deshacer.`)) return;
  for (const id of seleccion) await Api.post('eliminarActivo', { id });
  UI.toast('Eliminado(s).'); cargar();
}

function abrirEnviar() {
  const id = 'modalEnviar';
  document.getElementById(id)?.remove();
  const div = document.createElement('div');
  div.innerHTML = `<div class="modal fade" id="${id}" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Enviar ${seleccion.size} activo(s)</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="form-check mb-2"><input class="form-check-input" type="radio" name="destino" value="bodega" id="rdBodega" checked>
        <label class="form-check-label" for="rdBodega">A bodega</label></div>
      <select class="form-select mb-3" id="selPlazaDestino">${catalogos.plazas.map(p => `<option value="${p.id}">${UI.esc(p.nombre)}</option>`).join('')}</select>
      <div class="form-check mb-2"><input class="form-check-input" type="radio" name="destino" value="usuario" id="rdUsuario">
        <label class="form-check-label" for="rdUsuario">A otro usuario</label></div>
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
