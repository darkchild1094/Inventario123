<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva solicitud de movimiento - Inventario 123</title>
    <?php include ROOT_PATH . '/app/views/components/favicon.php'; ?>
    <style>
        body { background:#f4f7f6; }
        .card { max-width:820px; margin:0 auto; }
        #firmaPad { border:2px dashed #adb5bd; border-radius:8px; touch-action:none; background:#fff; width:100%; height:180px; display:block; }
        .activo-row { border:1px solid #e9ecef; border-radius:8px; }
        .activo-row:has(input:checked) { border-color:#0d6efd; background:#f0f6ff; }
    </style>
</head>
<body>
<?php include ROOT_PATH . '/app/views/components/navbar.php'; ?>
<div class="container py-4">
    <div class="card border-0 shadow-sm p-4">
        <h4 class="fw-bold mb-3"><i class="fas fa-right-left me-2"></i>Nueva solicitud de movimiento</h4>

        <form method="POST" action="index.php?controller=solicitud&action=guardar" id="formSolicitud">

            <div class="mb-3">
                <label class="form-label fw-semibold">¿Qué movimiento? *</label>
                <select name="destino" id="selDestino" class="form-select" required>
                    <option value="">Selecciona…</option>
                    <?php foreach ($destinos as $k => $lbl): ?>
                        <option value="<?= $k ?>"><?= htmlspecialchars($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text" id="ayudaDestino"></div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">¿De dónde sale el equipo? *</label>
                <div class="btn-group w-100" role="group">
                    <input type="radio" class="btn-check" name="origen_tipo" id="oMi" value="asignado" checked>
                    <label class="btn btn-outline-primary" for="oMi">De mi stock</label>
                    <input type="radio" class="btn-check" name="origen_tipo" id="oTienda" value="tienda">
                    <label class="btn btn-outline-primary" for="oTienda">Instalado en una tienda</label>
                    <?php if (!empty($puedeOrigenBodega)): ?>
                    <input type="radio" class="btn-check" name="origen_tipo" id="oBodega" value="bodega">
                    <label class="btn btn-outline-primary" for="oBodega">De una bodega</label>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mb-3 d-none" id="boxTienda">
                <label class="form-label fw-semibold">Tienda de origen</label>
                <select name="origen_tienda_id" id="selTienda" class="form-select">
                    <option value="">Selecciona…</option>
                    <?php foreach ($tiendas as $t): ?>
                        <option value="<?= (int) $t['id'] ?>"><?= htmlspecialchars($t['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Se cargarán los equipos "en uso" de esa tienda.</div>
            </div>

            <?php if (!empty($puedeOrigenBodega)): ?>
            <div class="mb-3 d-none" id="boxBodegaOrigen">
                <label class="form-label fw-semibold">Bodega de origen</label>
                <select name="origen_bodega_id" id="selBodegaOrigen" class="form-select">
                    <option value="">Selecciona…</option>
                    <?php foreach ($bodegas as $b): ?>
                        <option value="<?= (int) $b['id'] ?>"><?= htmlspecialchars($b['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Se cargarán los equipos "en bodega" de esa bodega. Lo firma quien recibe.</div>
            </div>
            <?php endif; ?>

            <div class="mb-3">
                <label class="form-label fw-semibold">Activos *</label>
                <div id="listaActivos" class="d-flex flex-column gap-2">
                    <?php foreach ($misAsignados as $a): ?>
                        <label class="activo-row d-flex align-items-center gap-2 p-2 mb-0" data-origen="asignado">
                            <input type="checkbox" class="form-check-input mt-0" name="activos[]" value="<?= (int) $a['id'] ?>">
                            <span>
                                <strong><?= htmlspecialchars(trim(($a['marca_nombre'] ?? '') . ' ' . ($a['modelo_nombre'] ?? '')) ?: 'Sin modelo') ?></strong>
                                <span class="text-muted">· <?= htmlspecialchars($a['dispositivo_nombre'] ?? '—') ?></span><br>
                                <small class="text-muted">Serie: <?= htmlspecialchars($a['serie'] ?? 'ninguna') ?> · Código: <?= htmlspecialchars($a['codigo_barras'] ?? '—') ?></small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="text-muted small" id="sinActivos" <?= empty($misAsignados) ? '' : 'hidden' ?>>No tienes equipo asignado.</div>
            </div>

            <div class="mb-3 d-none" id="boxBodega">
                <label class="form-label fw-semibold">Bodega destino</label>
                <select name="destino_bodega_id" class="form-select">
                    <?php foreach ($bodegas as $b): ?>
                        <option value="<?= (int) $b['id'] ?>"><?= htmlspecialchars($b['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3 d-none" id="boxIngeniero">
                <label class="form-label fw-semibold">Ingeniero que recibe</label>
                <select name="destino_usuario_id" class="form-select">
                    <option value="">Selecciona…</option>
                    <?php foreach ($ingenieros as $u): ?>
                        <option value="<?= (int) $u['id'] ?>"><?= htmlspecialchars($u['nombre']) ?> (<?= htmlspecialchars($u['tipo']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Motivo</label>
                <textarea name="nota" class="form-control" rows="2" maxlength="255" placeholder="Opcional"></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Tu firma *</label>
                <canvas id="firmaPad"></canvas>
                <div class="mt-1">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnLimpiarFirma"><i class="fas fa-eraser me-1"></i>Limpiar</button>
                    <small class="text-muted ms-2">Dibuja tu firma.</small>
                </div>
                <input type="hidden" name="firma" id="firmaInput">
            </div>

            <div class="d-flex gap-2">
                <button class="btn btn-primary" id="btnEnviar"><i class="fas fa-paper-plane me-1"></i>Enviar solicitud</button>
                <a href="index.php?controller=solicitud&action=index" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var selDestino = document.getElementById('selDestino');
    var ayuda = document.getElementById('ayudaDestino');
    var boxBodega = document.getElementById('boxBodega');
    var boxIng = document.getElementById('boxIngeniero');
    var boxTienda = document.getElementById('boxTienda');
    var boxBodegaOrigen = document.getElementById('boxBodegaOrigen');
    var lista = document.getElementById('listaActivos');
    var sinAct = document.getElementById('sinActivos');
    var oMi = document.getElementById('oMi'), oTienda = document.getElementById('oTienda');
    var oBodega = document.getElementById('oBodega'); // puede no existir según rol

    var AYUDA = {
        asignado: 'Lo firma el ingeniero que recibe.',
        en_bodega: 'Lo aprueba y firma un coordinador de la plaza.',
        baja: 'Solo lo aprueba y firma el ATI de la plaza.',
        garantia: 'Lo firman el ATI y un coordinador (doble firma).'
    };

    function refrescarDestino() {
        var d = selDestino.value;
        ayuda.textContent = AYUDA[d] || '';
        boxBodega.classList.toggle('d-none', d !== 'en_bodega');
        boxIng.classList.toggle('d-none', d !== 'asignado');
        // "Instalado en una tienda" no aplica cuando el destino es otro ingeniero.
        if (d === 'asignado') {
            if (oTienda.checked) oMi.checked = true;
            oTienda.disabled = true;
        } else {
            oTienda.disabled = false;
        }
        // Sacar de bodega no aplica si el destino ya es la bodega.
        if (oBodega) {
            if (d === 'en_bodega') {
                if (oBodega.checked) oMi.checked = true;
                oBodega.disabled = true;
            } else {
                oBodega.disabled = false;
            }
        }
        refrescarOrigen();
    }

    function origenActual() {
        if (oTienda.checked) return 'tienda';
        if (oBodega && oBodega.checked) return 'bodega';
        return 'asignado';
    }

    function refrescarOrigen() {
        var origen = origenActual();
        boxTienda.classList.toggle('d-none', origen !== 'tienda');
        if (boxBodegaOrigen) boxBodegaOrigen.classList.toggle('d-none', origen !== 'bodega');
        // Las filas "de mi stock" solo cuentan cuando el origen es mi stock.
        lista.querySelectorAll('label.activo-row[data-origen="asignado"]').forEach(function (l) {
            l.style.display = origen === 'asignado' ? '' : 'none';
            if (origen !== 'asignado') l.querySelector('input').checked = false;
        });
        // Limpia filas dinámicas del otro origen.
        lista.querySelectorAll('label[data-origen="tienda"],label[data-origen="bodega"]').forEach(function (n) { n.remove(); });
        if (origen === 'tienda') cargarTienda();
        else if (origen === 'bodega') cargarBodega();
        else actualizarSinActivos();
    }

    function pintarFilas(arr, origen) {
        (arr || []).forEach(function (a) {
            var l = document.createElement('label');
            l.className = 'activo-row d-flex align-items-center gap-2 p-2 mb-0';
            l.setAttribute('data-origen', origen);
            l.innerHTML = '<input type="checkbox" class="form-check-input mt-0" name="activos[]" value="' + a.id + '">' +
                '<span><strong>' + ((a.marca_nombre || '') + ' ' + (a.modelo_nombre || '')).trim() + '</strong>' +
                ' <span class="text-muted">· ' + (a.dispositivo_nombre || '—') + '</span><br>' +
                '<small class="text-muted">Serie: ' + (a.serie || 'ninguna') + ' · Código: ' + (a.codigo_barras || '—') + '</small></span>';
            lista.appendChild(l);
        });
        actualizarSinActivos();
    }

    function actualizarSinActivos() {
        var visibles = 0;
        lista.querySelectorAll('label.activo-row').forEach(function (l) {
            if (l.style.display !== 'none') visibles++;
        });
        sinAct.hidden = visibles > 0;
        sinAct.textContent = visibles > 0 ? '' : 'No hay equipos disponibles para este origen.';
    }

    var selTienda = document.getElementById('selTienda');
    function cargarTienda() {
        var tid = selTienda.value;
        lista.querySelectorAll('label[data-origen="tienda"]').forEach(function (n) { n.remove(); });
        if (!tid) { actualizarSinActivos(); return; }
        fetch('index.php?controller=api&action=obtenerActivosEnTiendaPorDispositivo&tienda_id=' + encodeURIComponent(tid),
              { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (arr) { pintarFilas(arr, 'tienda'); });
    }

    var selBodegaOrigen = document.getElementById('selBodegaOrigen');
    function cargarBodega() {
        if (!selBodegaOrigen) return;
        var bid = selBodegaOrigen.value;
        lista.querySelectorAll('label[data-origen="bodega"]').forEach(function (n) { n.remove(); });
        if (!bid) { actualizarSinActivos(); return; }
        fetch('index.php?controller=api&action=obtenerActivosEnBodega&bodega_id=' + encodeURIComponent(bid),
              { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (arr) { pintarFilas(arr, 'bodega'); });
    }

    selDestino.addEventListener('change', refrescarDestino);
    oMi.addEventListener('change', refrescarOrigen);
    oTienda.addEventListener('change', refrescarOrigen);
    if (oBodega) oBodega.addEventListener('change', refrescarOrigen);
    selTienda.addEventListener('change', cargarTienda);
    if (selBodegaOrigen) selBodegaOrigen.addEventListener('change', cargarBodega);
    refrescarDestino();

    // ── Firma ──────────────────────────────────────────────────────────────
    var canvas = document.getElementById('firmaPad'), cx = canvas.getContext('2d');
    var dib = false, hubo = false, lx = 0, ly = 0;
    function ajustar() {
        var r = window.devicePixelRatio || 1, b = canvas.getBoundingClientRect();
        canvas.width = b.width * r; canvas.height = b.height * r;
        cx.scale(r, r); cx.lineWidth = 2.2; cx.lineCap = 'round'; cx.strokeStyle = '#111';
    }
    ajustar(); window.addEventListener('resize', ajustar);
    function pos(e) { var b = canvas.getBoundingClientRect(), t = e.touches ? e.touches[0] : e; return [t.clientX - b.left, t.clientY - b.top]; }
    canvas.addEventListener('pointerdown', function (e) { e.preventDefault(); dib = true; var p = pos(e); lx = p[0]; ly = p[1]; });
    canvas.addEventListener('pointermove', function (e) {
        if (!dib) return; e.preventDefault(); var p = pos(e);
        cx.beginPath(); cx.moveTo(lx, ly); cx.lineTo(p[0], p[1]); cx.stroke(); lx = p[0]; ly = p[1]; hubo = true;
    });
    window.addEventListener('pointerup', function () { dib = false; });
    document.getElementById('btnLimpiarFirma').addEventListener('click', function () { cx.clearRect(0, 0, canvas.width, canvas.height); hubo = false; });

    document.getElementById('formSolicitud').addEventListener('submit', function (ev) {
        if (!selDestino.value) { ev.preventDefault(); alert('Elige el tipo de movimiento.'); return; }
        if (!document.querySelector('input[name="activos[]"]:checked')) { ev.preventDefault(); alert('Selecciona al menos un activo.'); return; }
        if (!hubo) { ev.preventDefault(); alert('Falta tu firma.'); return; }
        document.getElementById('firmaInput').value = canvas.toDataURL('image/png');
        document.getElementById('btnEnviar').disabled = true;
    });
})();
</script>
</body>
</html>
