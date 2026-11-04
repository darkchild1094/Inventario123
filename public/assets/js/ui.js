// Helpers de UI compartidos por los módulos nuevos del panel web, para que
// se comporten igual que sus pantallas equivalentes en la app Android
// (handoff en /data/Inventario123-handoff).
const UI = (() => {
  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
  }

  const STATUS_LABEL = {
    en_bodega: 'En bodega', en_uso: 'En uso', asignado: 'Asignado',
    garantia: 'Garantía', baja: 'Baja',
  };
  const STATUS_CLASS = {
    en_bodega: 'success', en_uso: 'primary', asignado: 'secondary',
    garantia: 'warning text-dark', baja: 'danger',
  };
  function statusBadge(status) {
    const cls = STATUS_CLASS[status] || 'secondary';
    const lbl = STATUS_LABEL[status] || status || '—';
    return `<span class="badge bg-${cls}">${esc(lbl)}</span>`;
  }

  function toast(msg, tipo = 'success') {
    let host = document.getElementById('uiToastHost');
    if (!host) {
      host = document.createElement('div');
      host.id = 'uiToastHost';
      host.style.position = 'fixed';
      host.style.bottom = '1rem';
      host.style.right = '1rem';
      host.style.zIndex = 2000;
      document.body.appendChild(host);
    }
    const el = document.createElement('div');
    el.className = `toast align-items-center text-white bg-${tipo === 'error' ? 'danger' : tipo} border-0 show mb-2`;
    el.innerHTML = `<div class="d-flex"><div class="toast-body">${esc(msg)}</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="this.closest('.toast').remove()"></button></div>`;
    host.appendChild(el);
    setTimeout(() => el.remove(), 4000);
  }

  function confirmar(msg) {
    return new Promise((resolve) => {
      const id = 'uiConfirmModal';
      document.getElementById(id)?.remove();
      const div = document.createElement('div');
      div.innerHTML = `<div class="modal fade" id="${id}" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
        <div class="modal-body py-4">${esc(msg)}</div>
        <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-danger" id="${id}Ok">Confirmar</button></div>
      </div></div></div>`;
      document.body.appendChild(div);
      const modalEl = div.querySelector('.modal');
      const modal = new bootstrap.Modal(modalEl);
      let decidido = false;
      modalEl.querySelector(`#${id}Ok`).addEventListener('click', () => { decidido = true; resolve(true); modal.hide(); });
      modalEl.addEventListener('hidden.bs.modal', () => { div.remove(); if (!decidido) resolve(false); });
      modal.show();
    });
  }

  /** Pestañas por plaza. onChange(plazaIdOrNull) */
  function tabsPlaza(container, plazas, onChange, incluirTodas = true) {
    container.innerHTML = '';
    container.className = 'nav nav-pills gap-2 mb-3 flex-wrap';
    const mk = (id, label, activo) => {
      const a = document.createElement('button');
      a.type = 'button';
      a.className = `nav-link ${activo ? 'active' : ''}`;
      a.textContent = label;
      a.dataset.plazaId = id ?? '';
      a.addEventListener('click', () => {
        container.querySelectorAll('.nav-link').forEach((x) => x.classList.remove('active'));
        a.classList.add('active');
        onChange(id);
      });
      return a;
    };
    if (incluirTodas) container.appendChild(mk(null, 'Todas', true));
    plazas.forEach((p, i) => container.appendChild(mk(p.id, p.nombre, !incluirTodas && i === 0)));
  }

  /** Barra de búsqueda tipo pill. onInput(texto) con debounce. */
  function buscadorPill(container, placeholder, onInput) {
    container.innerHTML = `<div class="input-group mb-3" style="max-width:420px">
      <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
      <input type="text" class="form-control border-start-0 ps-0" placeholder="${esc(placeholder)}">
    </div>`;
    const input = container.querySelector('input');
    let t;
    input.addEventListener('input', () => {
      clearTimeout(t);
      t = setTimeout(() => onInput(input.value.trim()), 250);
    });
    return input;
  }

  /** Barra contextual de selección múltiple. Devuelve API para controlarla. */
  function barraSeleccion(container, { onEliminar, onEnviar, onCancelar }) {
    container.innerHTML = '';
    container.className = 'alert alert-primary d-none d-flex align-items-center justify-content-between mb-3';
    container.innerHTML = `
      <div><strong class="sel-count">0</strong> seleccionado(s)</div>
      <div class="d-flex gap-2">
        ${onEnviar ? '<button class="btn btn-sm btn-primary btn-enviar"><i class="fas fa-paper-plane me-1"></i>Enviar</button>' : ''}
        ${onEliminar ? '<button class="btn btn-sm btn-danger btn-eliminar"><i class="fas fa-trash me-1"></i>Eliminar</button>' : ''}
        <button class="btn btn-sm btn-outline-secondary btn-cancelar"><i class="fas fa-xmark me-1"></i>Cancelar</button>
      </div>`;
    if (onEnviar) container.querySelector('.btn-enviar').addEventListener('click', onEnviar);
    if (onEliminar) container.querySelector('.btn-eliminar').addEventListener('click', onEliminar);
    container.querySelector('.btn-cancelar').addEventListener('click', onCancelar);
    return {
      actualizar(n) {
        container.classList.toggle('d-none', n === 0);
        container.querySelector('.sel-count').textContent = n;
      },
    };
  }

  function excelHref(modulo, extra = {}) {
    const p = new URLSearchParams({ controller: 'export', action: 'modulo', modulo, ...extra });
    return `index.php?${p.toString()}`;
  }

  /** Fila de un activo para listas tipo Bodega/Mi Stock/Tienda. La fila NO
   *  muestra num_activo (no es algo que se busque a simple vista en una
   *  lista); para verlo hay que abrir el detalle — ver verDetalleActivo(). */
  function filaActivo(a, { seleccionable = false } = {}) {
    const equipo = [a.dispositivo_nombre, a.marca_nombre, a.modelo_nombre].filter(Boolean).join(' · ');
    return `
      <tr data-id="${a.id}">
        ${seleccionable ? `<td style="width:2.5rem"><input type="checkbox" class="form-check-input sel-chk"></td>` : ''}
        <td style="cursor:pointer" onclick="UI.verDetalleActivo(${a.id})">
          <div class="fw-semibold">${esc(equipo || '—')}</div>
          <div class="text-muted small font-monospace">${esc(a.serie || '')}${a.codigo_barras ? ' · CB:' + esc(a.codigo_barras) : ''}</div>
        </td>
        <td>${statusBadge(a.status)}</td>
        <td class="text-muted small">${esc(a.tienda_uso_nombre || a.bodega_nombre || a.usuario_nombre || a.procedencia_nombre || '—')}</td>
        <td class="text-muted small">${esc(a.plaza_nombre || '')}</td>
        <td class="text-end"><button class="btn btn-sm btn-link text-muted p-0" onclick="UI.verDetalleActivo(${a.id})" title="Ver detalle"><i class="fas fa-circle-info"></i></button></td>
      </tr>`;
  }

  /** Modal de solo-lectura con el detalle completo de un activo (incluye
   *  N° de activo — ver memoria inventario123_num_activo_oculto: visible
   *  aquí, pero nunca editable en ningún formulario). */
  async function verDetalleActivo(id) {
    const id2 = 'uiDetalleActivoModal';
    document.getElementById(id2)?.remove();
    const div = document.createElement('div');
    div.innerHTML = `<div class="modal fade" id="${id2}" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Detalle del activo</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" id="${id2}Body"><div class="text-center py-4"><div class="spinner-border text-primary"></div></div></div>
    </div></div></div>`;
    document.body.appendChild(div);
    const modalEl = div.querySelector('.modal');
    modalEl.addEventListener('hidden.bs.modal', () => div.remove());
    new bootstrap.Modal(modalEl).show();

    const a = await Api.get('obtenerActivo', { id });
    if (a.success === false) {
      document.getElementById(`${id2}Body`).innerHTML = `<div class="alert alert-danger mb-0">${esc(a.message || 'No se pudo cargar.')}</div>`;
      return;
    }
    const filas = [
      ['Tipo', [a.dispositivo_nombre, a.marca_nombre, a.modelo_nombre].filter(Boolean).join(' · ')],
      ['Serie', a.serie || '—'],
      ['Código de barras', a.codigo_barras || '—'],
      ['N° de activo', a.num_activo || '—'],
      ['Estado', STATUS_LABEL[a.status] || a.status],
      ['Ubicación', a.tienda_uso_nombre || a.bodega_nombre || a.usuario_nombre || a.procedencia_nombre || '—'],
      ['Plaza', a.plaza_nombre || '—'],
      ['Procedencia', a.procedencia_nombre || '—'],
      ['Fecha de alta', (a.fecha_alta || '').replace('T', ' ') || '—'],
    ];
    document.getElementById(`${id2}Body`).innerHTML = `<table class="table table-sm mb-0">
      ${filas.map(([k, v]) => `<tr><th class="text-muted" style="width:40%">${esc(k)}</th><td class="font-monospace">${esc(v)}</td></tr>`).join('')}
    </table>
    <div class="form-text mt-2">El N° de activo es informativo (placa oficial FEMSA): nunca se captura ni se edita aquí.</div>`;
  }

  return { esc, statusBadge, toast, confirmar, tabsPlaza, buscadorPill, barraSeleccion, excelHref, filaActivo, verDetalleActivo };
})();
