<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta - Inventario 123</title>
    <?php include ROOT_PATH . '/app/views/components/favicon.php'; ?>
    <style>
        body { background: #f4f7f6; }
        .consulta-box { max-width: 720px; margin: 0 auto; }
        .card-soft { border: 0; border-radius: 14px; box-shadow: 0 .25rem .75rem rgba(0,0,0,.05); }
        .tl { list-style: none; margin: 0; padding: 0; }
        .tl li { position: relative; padding: .5rem 0 .5rem 1.4rem; border-left: 2px solid #e3e6ea; }
        .tl li::before { content: ''; position: absolute; left: -6px; top: .9rem; width: 10px; height: 10px;
            border-radius: 50%; background: #0d6efd; }
        .tl .ev { font-weight: 600; }
        .tl .fecha { color: #6c757d; font-size: .8rem; }
        .big-input { font-size: 1.15rem; padding: .8rem 1rem; }
    </style>
</head>
<body>
<?php include ROOT_PATH . '/app/views/components/navbar.php'; ?>

<div class="container py-4 consulta-box">

    <h2 class="h3 fw-bold mb-1"><i class="fas fa-barcode text-primary me-2"></i> Consulta</h2>
    <p class="text-muted">Escanea o teclea la <strong>serie</strong>, el <strong>código de barras</strong>
       o el <strong>N° de activo</strong> para saber a qué tienda pertenece, dónde está y su historial.</p>

    <div class="card card-soft mb-4">
        <div class="card-body">
            <div class="input-group">
                <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                <input type="text" id="qInput" class="form-control big-input"
                       placeholder="Serie / código / N° de activo…" autocomplete="off" autofocus
                       value="<?= htmlspecialchars($q ?? '') ?>">
                <button class="btn btn-primary px-4" id="btnBuscar"><i class="fas fa-arrow-right"></i></button>
            </div>
            <div class="form-text">Presiona Enter para consultar.</div>
        </div>
    </div>

    <div id="estado" class="text-muted small"></div>
    <div id="resultado"></div>
</div>

<script>
(function () {
    const input   = document.getElementById('qInput');
    const btn     = document.getElementById('btnBuscar');
    const estado  = document.getElementById('estado');
    const salida  = document.getElementById('resultado');

    const EVENTOS = {
        alta: 'Alta', cambio_status: 'Cambio de estatus', cambio_stock: 'Cambio de stock',
        reemplazo_entra: 'Entra por reemplazo', reemplazo_sale: 'Sale por reemplazo',
        edicion: 'Edición', baja: 'Baja', eliminacion: 'Eliminación',
    };

    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => (
            {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]
        ));
    }

    function tarjetaUbicacion(a, u) {
        const eq = [a.dispositivo_nombre, a.marca_nombre, a.modelo_nombre].filter(Boolean).join(' · ');
        return `
        <div class="card card-soft mb-3">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="fw-bold">${esc(eq || 'Equipo')}</div>
                <div class="text-muted small">
                  Serie: ${esc(a.serie || '—')} · CB: ${esc(a.codigo_barras || '—')} · N° activo: ${esc(a.num_activo || '—')}
                </div>
              </div>
              <span class="badge bg-secondary text-uppercase">${esc((u && u.status) || a.status || '')}</span>
            </div>
            <hr>
            <div class="row g-2 small">
              <div class="col-md-6"><i class="fas fa-location-dot text-primary me-1"></i> <strong>${esc(u && u.resumen || '—')}</strong></div>
              <div class="col-md-6"><i class="fas fa-store text-muted me-1"></i> Tienda: ${esc((u && (u.tienda_stock || u.tienda_uso)) || '—')}</div>
              <div class="col-md-6"><i class="fas fa-warehouse text-muted me-1"></i> Bodega: ${esc(u && u.bodega || '—')}</div>
              <div class="col-md-6"><i class="fas fa-user text-muted me-1"></i> Con: ${esc(u && u.usuario || '—')}</div>
              <div class="col-md-6"><i class="fas fa-map text-muted me-1"></i> Plaza: ${esc(u && u.plaza_nombre || '—')}</div>
              <div class="col-md-6"><i class="fas fa-sitemap text-muted me-1"></i> Región: ${esc(u && u.region_nombre || '—')} / ${esc(u && u.negocio_nombre || '—')}</div>
              <div class="col-md-6"><i class="fas fa-right-from-bracket text-muted me-1"></i> Procedencia: ${esc(u && u.procedencia || '—')}</div>
            </div>
            <a href="index.php?action=detalle&id=${a.id}" class="btn btn-sm btn-outline-primary mt-3">
              <i class="fas fa-up-right-from-square me-1"></i> Ver ficha completa
            </a>
          </div>
        </div>`;
    }

    function timeline(historial) {
        if (!historial || !historial.length) return '<p class="text-muted small">Sin movimientos registrados.</p>';
        const filas = historial.map(m => {
            const ev = EVENTOS[m.evento] || m.evento;
            const de = m.stock_ant_nombre ? ` · de ${esc(m.stock_ant_nombre)}` : '';
            const a  = m.stock_new_nombre ? ` → ${esc(m.stock_new_nombre)}` : '';
            const nota = m.nota ? `<div class="text-muted small">${esc(m.nota)}</div>` : '';
            return `<li>
                <div class="ev">${esc(ev)}${de}${a}</div>
                <div class="fecha">${esc((m.creado_en || '').substring(0, 16))}${m.actor_nombre ? ' · ' + esc(m.actor_nombre) : ''}</div>
                ${nota}
            </li>`;
        }).join('');
        return `<div class="card card-soft"><div class="card-body">
            <h6 class="fw-bold mb-3"><i class="fas fa-clock-rotate-left me-2 text-primary"></i>Historial de movimientos</h6>
            <ul class="tl">${filas}</ul></div></div>`;
    }

    function listaCoincidencias(items) {
        const filas = items.map(a => {
            const eq = [a.dispositivo_nombre, a.marca_nombre, a.modelo_nombre].filter(Boolean).join(' · ');
            return `<a href="index.php?controller=consulta&action=index&q=${encodeURIComponent(a.serie || a.codigo_barras || a.num_activo || '')}"
                       class="list-group-item list-group-item-action">
                <div class="d-flex justify-content-between">
                  <span>${esc(eq || 'Equipo')}</span>
                  <span class="badge bg-secondary text-uppercase">${esc(a.status || '')}</span>
                </div>
                <div class="small text-muted">Serie ${esc(a.serie || '—')} · CB ${esc(a.codigo_barras || '—')} · ${esc(a.ubicacion_corta || '')}</div>
            </a>`;
        }).join('');
        return `<div class="card card-soft"><div class="list-group list-group-flush">${filas}</div></div>`;
    }

    async function consultar() {
        const q = input.value.trim();
        if (!q) { input.focus(); return; }
        estado.textContent = 'Buscando…';
        salida.innerHTML = '';
        history.replaceState(null, '', 'index.php?controller=consulta&action=index&q=' + encodeURIComponent(q));
        try {
            const resp = await fetch('index.php?controller=api&action=consultar&q=' + encodeURIComponent(q), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await resp.json();
            if (resp.status === 404 || data.encontrado === false) {
                estado.textContent = '';
                salida.innerHTML = '<div class="alert alert-warning">Sin coincidencias para «' + esc(q) + '».</div>';
                return;
            }
            estado.textContent = '';
            if (data.activo) {
                salida.innerHTML = tarjetaUbicacion(data.activo, data.ubicacion) + timeline(data.historial);
            } else if (data.coincidencias) {
                salida.innerHTML = '<p class="text-muted small">' + data.coincidencias.length +
                    ' coincidencias. Elige una:</p>' + listaCoincidencias(data.coincidencias);
            }
        } catch (e) {
            estado.textContent = '';
            salida.innerHTML = '<div class="alert alert-danger">Error de conexión. Intenta de nuevo.</div>';
        }
    }

    btn.addEventListener('click', consultar);
    input.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); consultar(); } });
    if (input.value.trim()) consultar();
})();
</script>
</body>
</html>
