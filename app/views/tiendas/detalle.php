<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($tienda['nombre']) ?> - Inventario 123</title>
<?php include ROOT_PATH . '/app/views/components/favicon.php'; ?>
</head>
<body>
<?php include ROOT_PATH . '/app/views/components/navbar.php'; ?>
<div class="container-fluid px-3 px-lg-4 py-4">
  <a href="index.php?controller=tienda" class="text-decoration-none small mb-2 d-inline-block"><i class="fas fa-arrow-left me-1"></i>Tiendas</a>
  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h4 class="fw-bold mb-0"><?= htmlspecialchars($tienda['nombre']) ?></h4>
      <div class="text-muted small"><?= htmlspecialchars($tienda['cr_tienda'] ?? '') ?> · <?= htmlspecialchars($tienda['plaza_nombre'] ?? '') ?></div>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary" href="index.php?controller=export&action=modulo&modulo=tiendas&tienda_id=<?= (int) $tienda['id'] ?>"><i class="fas fa-file-excel me-1"></i>Excel</a>
      <button class="btn btn-outline-secondary" id="btnInventario"><i class="fas fa-clipboard-check me-1"></i>Inventario</button>
      <button class="btn btn-primary" id="btnRegistrar"><i class="fas fa-plus me-1"></i>Registrar activo</button>
    </div>
  </div>
  <div id="ultimoInv" class="mb-2"></div>
  <div id="buscador"></div>
  <div class="card card-soft">
    <div class="table-responsive"><table class="table align-middle mb-0">
      <thead class="table-light"><tr><th>Equipo</th><th>Estado</th><th>Fecha instalación</th><th></th></tr></thead>
      <tbody id="tbody"></tbody>
    </table></div>
    <div class="p-3 text-muted small" id="vacio" style="display:none">Sin activos instalados.</div>
  </div>
</div>

<!-- Modal: Registrar activo (3 pestañas) -->
<div class="modal fade" id="modalRegistrar" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="tituloRegistrar">Registrar activo</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <ul class="nav nav-tabs mb-3" id="tabsRegistrar">
      <li class="nav-item"><button class="nav-link active" data-tab="instalacion">Instalación</button></li>
      <li class="nav-item"><button class="nav-link" data-tab="retiro">Retiro</button></li>
      <li class="nav-item"><button class="nav-link" data-tab="reemplazo">Reemplazo</button></li>
    </ul>

    <div id="paneInstalacion">
      <h6 class="text-primary">Equipo que entra</h6>
      <div class="row">
        <div class="col-6 mb-2"><label class="form-label small">Tipo</label><select class="form-select" id="insDispositivo"></select></div>
        <div class="col-6 mb-2"><label class="form-label small">Modelo</label><select class="form-select" id="insModelo"></select></div>
      </div>
      <div class="row">
        <div class="col-6 mb-2"><label class="form-label small">Serie</label><input class="form-control font-monospace" id="insSerie"></div>
        <div class="col-6 mb-2"><label class="form-label small">Código de barras (opcional)</label><input class="form-control font-monospace" id="insCodigo"></div>
      </div>
    </div>

    <div id="paneRetiro" class="d-none">
      <h6 class="text-danger">Equipo que sale</h6>
      <div class="mb-2"><label class="form-label small">Activo instalado a retirar</label><select class="form-select" id="retActivo"></select></div>
      <div class="row">
        <div class="col-6 mb-2"><label class="form-label small">Motivo</label>
          <select class="form-select" id="retMotivo">
            <option>Falla</option><option>Obsolescencia</option><option>Fin de contrato</option><option>Cierre de tienda</option><option>Otro</option>
          </select></div>
        <div class="col-6 mb-2"><label class="form-label small">Destino</label>
          <select class="form-select" id="retDestino"><option value="en_bodega">A bodega</option><option value="baja">Baja</option><option value="garantia">Garantía</option></select></div>
      </div>
    </div>

    <div id="paneReemplazo" class="d-none">
      <h6 class="text-danger">Retiro</h6>
      <div class="form-text mb-1">Si el nuevo equipo sustituye a varias unidades viejas, agrégalas todas.</div>
      <div id="remFilas"></div>
      <button type="button" class="btn btn-sm btn-outline-primary mb-2" id="btnRemMas"><i class="fas fa-plus me-1"></i>Agregar otro equipo retirado</button>
      <div class="mb-3"><label class="form-label small">Destino de los retirados</label>
        <select class="form-select" id="remDestino"><option value="en_bodega">A bodega</option><option value="baja">Baja</option><option value="garantia">Garantía</option></select></div>
      <h6 class="text-success">Instalación</h6>
      <div class="row">
        <div class="col-6 mb-2"><label class="form-label small">Tipo</label><select class="form-select" id="remDispositivo"></select></div>
        <div class="col-6 mb-2"><label class="form-label small">Modelo</label><select class="form-select" id="remModelo"></select></div>
      </div>
      <div class="row">
        <div class="col-6 mb-2"><label class="form-label small">Serie</label><input class="form-control font-monospace" id="remSerie"></div>
        <div class="col-6 mb-2"><label class="form-label small">Código de barras (opcional)</label><input class="form-control font-monospace" id="remCodigo"></div>
      </div>
    </div>
  </div>
  <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
    <button class="btn btn-primary" id="btnGuardarRegistro">Guardar</button></div>
</div></div></div>

<!-- Modal: Inventario (checklist) -->
<div class="modal fade" id="modalInventario" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Inventario de <?= htmlspecialchars($tienda['nombre']) ?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div id="invResumen" class="mb-2 text-muted small"></div>
    <div id="invLista" class="list-group"></div>
    <div class="input-group mt-3">
      <input class="form-control font-monospace" id="invEscaneo" placeholder="Escribe serie o código y Enter…">
      <button class="btn btn-outline-primary" id="btnInvEscanear"><i class="fas fa-magnifying-glass"></i></button>
    </div>
  </div>
  <div class="modal-footer">
    <button class="btn btn-outline-danger" id="btnInvCancelar">Cancelar inventario</button>
    <button class="btn btn-success" id="btnInvCerrar">Finalizar</button>
  </div>
</div></div></div>

<script>
const TIENDA_ID = <?= (int) $tienda['id'] ?>;
let catalogos = null, busqueda = '', inventarioId = null, inventarioDetalle = [];

function llenarModelos(selDisp, selMod) {
  const dispId = parseInt(selDisp.value || 0);
  selMod.innerHTML = catalogos.modelos.filter(m => m.dispositivo_id === dispId)
    .map(m => `<option value="${m.id}">${UI.esc(m.marca_nombre)} ${UI.esc(m.nombre)}</option>`).join('');
}

async function cargar() {
  const params = { modulo: 'tiendas', tienda_id: TIENDA_ID, por_pagina: 300 };
  if (busqueda) params.busqueda = busqueda;
  const r = await Api.get('listarActivos', params);
  const activos = r.activos || [];
  document.getElementById('tbody').innerHTML = activos.map(a => `
    <tr><td><div class="fw-semibold">${UI.esc([a.dispositivo_nombre,a.marca_nombre,a.modelo_nombre].filter(Boolean).join(' · '))}</div>
      <div class="text-muted small font-monospace">${UI.esc(a.serie||'')}${a.codigo_barras ? ' · CB:'+UI.esc(a.codigo_barras):''}</div></td>
      <td>${UI.statusBadge(a.status)}</td>
      <td class="text-muted small">${UI.esc((a.fecha_alta||'').split(' ')[0]||'')}</td>
      <td class="text-end">${a.puedeEliminar ? `<button class="btn btn-sm btn-outline-danger btn-del" data-id="${a.id}"><i class="fas fa-trash"></i></button>` : ''}</td>
    </tr>`).join('');
  document.getElementById('vacio').style.display = activos.length ? 'none' : 'block';
  document.querySelectorAll('.btn-del').forEach(b => b.addEventListener('click', async () => {
    if (!await UI.confirmar('¿Eliminar este activo?')) return;
    await Api.post('eliminarActivo', { id: b.dataset.id }); cargar();
  }));
}

document.getElementById('btnRegistrar').addEventListener('click', () => abrirRegistrar());
async function abrirRegistrar() {
  ['insDispositivo','remDispositivo'].forEach(id => {
    const sel = document.getElementById(id);
    sel.innerHTML = catalogos.dispositivos.map(d => `<option value="${d.id}">${UI.esc(d.nombre)}</option>`).join('');
  });
  llenarModelos(document.getElementById('insDispositivo'), document.getElementById('insModelo'));
  llenarModelos(document.getElementById('remDispositivo'), document.getElementById('remModelo'));
  document.getElementById('insDispositivo').onchange = () => llenarModelos(document.getElementById('insDispositivo'), document.getElementById('insModelo'));
  document.getElementById('remDispositivo').onchange = () => llenarModelos(document.getElementById('remDispositivo'), document.getElementById('remModelo'));

  const instalados = (await Api.get('listarActivos', { modulo: 'tiendas', tienda_id: TIENDA_ID, status: 'en_uso', por_pagina: 300 })).activos || [];
  const opts = instalados.map(a => `<option value="${a.id}">${UI.esc([a.dispositivo_nombre,a.modelo_nombre].filter(Boolean).join(' · '))} - ${UI.esc(a.serie||'')}</option>`).join('') || '<option value="">(sin equipo instalado)</option>';
  document.getElementById('retActivo').innerHTML = opts;
  remOpts = opts;
  document.getElementById('remFilas').innerHTML = '';
  agregarFilaRetiro('remFilas', remOpts);

  document.querySelectorAll('#tabsRegistrar .nav-link').forEach(b => b.classList.remove('active'));
  document.querySelector('#tabsRegistrar [data-tab=instalacion]').classList.add('active');
  document.getElementById('paneInstalacion').classList.remove('d-none');
  document.getElementById('paneRetiro').classList.add('d-none');
  document.getElementById('paneReemplazo').classList.add('d-none');
  new bootstrap.Modal(document.getElementById('modalRegistrar')).show();
}
document.querySelectorAll('#tabsRegistrar .nav-link').forEach(btn => btn.addEventListener('click', () => {
  document.querySelectorAll('#tabsRegistrar .nav-link').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  ['instalacion','retiro','reemplazo'].forEach(t => document.getElementById('pane' + t.charAt(0).toUpperCase() + t.slice(1)).classList.toggle('d-none', t !== btn.dataset.tab));
}));

document.getElementById('btnGuardarRegistro').addEventListener('click', async () => {
  const tab = document.querySelector('#tabsRegistrar .nav-link.active').dataset.tab;
  if (tab === 'instalacion') {
    const serie = document.getElementById('insSerie').value.trim();
    if (!serie) { UI.toast('Falta la serie.', 'error'); return; }
    await guardarInstalacion(serie, document.getElementById('insCodigo').value.trim(), document.getElementById('insModelo').value, false);
  } else if (tab === 'retiro') {
    const id = document.getElementById('retActivo').value;
    if (!id) { UI.toast('Elige el activo a retirar.', 'error'); return; }
    const r = await Api.post('actualizarActivo', { id, status: document.getElementById('retDestino').value, motivo: document.getElementById('retMotivo').value });
    if (r.success) { UI.toast('Retirado.'); bootstrap.Modal.getInstance(document.getElementById('modalRegistrar')).hide(); cargar(); }
    else UI.toast(r.message || 'No se pudo retirar.', 'error');
  } else {
    let retIds = Array.from(document.querySelectorAll('#remFilas .fila-retiro select')).map(s => s.value).filter(v => v);
    retIds = [...new Set(retIds)];
    const serie = document.getElementById('remSerie').value.trim();
    if (!retIds.length) { UI.toast('Elige al menos un activo a retirar.', 'error'); return; }
    if (!serie) { UI.toast('Falta la serie del equipo que entra.', 'error'); return; }
    const destino = document.getElementById('remDestino').value;
    const r = await Api.post('guardarActivo', {
      serie, codigo_barras: document.getElementById('remCodigo').value.trim() || null,
      modelo_id: document.getElementById('remModelo').value, status: 'en_uso', tienda_uso_id: TIENDA_ID,
      reemplaza_activo_id: retIds[0], salida_destino: destino,
    });
    if (!r.success) { UI.toast(r.message || 'No se pudo registrar.', 'error'); return; }
    for (const extraId of retIds.slice(1)) {
      await Api.post('actualizarActivo', {
        id: extraId, status: destino, tienda_uso_id: TIENDA_ID,
        motivo: 'Retiro adicional junto con el reemplazo de serie ' + serie,
      });
    }
    UI.toast('Reemplazo registrado.'); bootstrap.Modal.getInstance(document.getElementById('modalRegistrar')).hide(); cargar();
  }
});

let remOpts = '';
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
document.getElementById('btnRemMas').addEventListener('click', () => agregarFilaRetiro('remFilas', remOpts));

async function guardarInstalacion(serie, codigo, modeloId, moverExistente) {
  const r = await Api.post('guardarActivo', {
    serie, codigo_barras: codigo || null, modelo_id: modeloId, status: 'en_uso',
    tienda_uso_id: TIENDA_ID, mover_existente: moverExistente ? 1 : 0,
  });
  if (r.success) {
    UI.toast('Activo instalado.'); bootstrap.Modal.getInstance(document.getElementById('modalRegistrar')).hide(); cargar();
  } else if (r.ya_existe) {
    if (await UI.confirmar(r.message)) await guardarInstalacion(serie, codigo, modeloId, true);
  } else {
    UI.toast(r.message || 'No se pudo instalar.', 'error');
  }
}

// ── Inventario (checklist) ──────────────────────────────────────────────
document.getElementById('btnInventario').addEventListener('click', async () => {
  let r0 = await Api.get('inventarioTiendaAbierto', { tienda_id: TIENDA_ID });
  let abierto = r0 ? r0.abierto : null;
  if (!abierto || !abierto.id) {
    const r = await Api.post('inventarioTiendaIniciar', { tienda_id: TIENDA_ID });
    if (!r.success) { UI.toast(r.message || 'No se pudo iniciar el inventario.', 'error'); return; }
    inventarioId = r.inventario.id;
  } else {
    inventarioId = abierto.id;
  }
  await cargarInventario();
  new bootstrap.Modal(document.getElementById('modalInventario')).show();
});
async function cargarInventario() {
  const d = await Api.get('inventarioTiendaDetalle', { id: inventarioId });
  inventarioDetalle = d.detalle || [];
  const enc = inventarioDetalle.filter(x => x.encontrado).length;
  document.getElementById('invResumen').textContent = `${enc} encontrados de ${inventarioDetalle.length}`;
  document.getElementById('invLista').innerHTML = inventarioDetalle.map(x => `
    <div class="list-group-item d-flex align-items-center gap-2" data-detalle="${x.detalle_id}">
      <i class="fas ${x.encontrado ? 'fa-check-circle text-success' : 'fa-circle text-secondary'}" style="cursor:pointer" data-marcar="${x.detalle_id}" data-enc="${x.encontrado?0:1}"></i>
      <div class="flex-grow-1">
        <div>${UI.esc([x.dispositivo_nombre,x.modelo_nombre].filter(Boolean).join(' · '))}</div>
        <div class="text-muted small font-monospace">${UI.esc(x.serie||'')}</div>
      </div>
    </div>`).join('');
  document.querySelectorAll('[data-marcar]').forEach(el => el.addEventListener('click', async () => {
    await Api.post('inventarioTiendaMarcar', { detalle_id: el.dataset.marcar, encontrado: el.dataset.enc });
    cargarInventario();
  }));
}
document.getElementById('btnInvEscanear').addEventListener('click', async () => {
  const codigo = document.getElementById('invEscaneo').value.trim();
  if (!codigo) return;
  const r = await Api.post('inventarioTiendaEscanear', { id: inventarioId, codigo });
  if (r.success) { document.getElementById('invEscaneo').value = ''; cargarInventario(); }
  else UI.toast(r.message || 'Ese código no pertenece a esta tienda.', 'error');
});
document.getElementById('invEscaneo').addEventListener('keydown', (e) => { if (e.key === 'Enter') document.getElementById('btnInvEscanear').click(); });
document.getElementById('btnInvCerrar').addEventListener('click', async () => {
  const faltan = inventarioDetalle.filter(x => !x.encontrado).length;
  if (faltan > 0 && !await UI.confirmar(`Faltan ${faltan} por revisar. ¿Finalizar de todos modos?`)) return;
  const r = await Api.post('inventarioTiendaCerrar', { id: inventarioId });
  if (r.success) { UI.toast('Inventario cerrado.'); bootstrap.Modal.getInstance(document.getElementById('modalInventario')).hide(); cargarUltimoInventario(); }
  else UI.toast(r.message || 'No se pudo cerrar.', 'error');
});
document.getElementById('btnInvCancelar').addEventListener('click', async () => {
  if (!await UI.confirmar('¿Cancelar este inventario? Se descarta todo el progreso.')) return;
  await Api.post('inventarioTiendaCancelar', { id: inventarioId });
  bootstrap.Modal.getInstance(document.getElementById('modalInventario')).hide();
});
async function cargarUltimoInventario() {
  // El resumen "último inventario" se muestra sólo si existe uno cerrado reciente.
}

document.addEventListener('DOMContentLoaded', async () => {
  catalogos = await Api.get('obtenerCatalogos');
  UI.buscadorPill(document.getElementById('buscador'), 'Buscar por serie, código o modelo…', (v) => { busqueda = v; cargar(); });
  await cargar();
});
</script>
</body>
</html>
