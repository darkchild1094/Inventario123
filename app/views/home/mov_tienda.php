<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimiento en tienda - Inventario 123</title>
    <?php include ROOT_PATH . '/app/views/components/favicon.php'; ?>
    <style>
        body { background:#f4f7f6; }
        .card-soft { border:0; border-radius:14px; box-shadow:0 .25rem .75rem rgba(0,0,0,.06); }
        .modo-btn { flex:1; }
        .oculto { display:none; }
    </style>
</head>
<body>
<?php include ROOT_PATH . '/app/views/components/navbar.php'; ?>

<div class="container py-4" style="max-width:640px">
    <h2 class="h4 fw-bold mb-1"><i class="fas fa-store text-primary me-2"></i>Movimiento en tienda</h2>
    <?php if ($tiendaFija): ?>
        <p class="text-muted mb-3">Tienda: <strong><?= htmlspecialchars($tiendaFija['nombre']) ?></strong></p>
    <?php endif; ?>

    <form class="card card-soft" method="POST" action="index.php?action=movimientoTienda" enctype="multipart/form-data" id="formMov">
        <div class="card-body">

            <?php if ($tiendaFija): ?>
                <input type="hidden" name="tienda_uso_id" value="<?= (int) $tiendaFija['id'] ?>">
            <?php else: ?>
                <label class="form-label fw-semibold">Tienda</label>
                <select name="tienda_uso_id" class="form-select mb-3" required>
                    <option value="">Selecciona tienda...</option>
                    <?php foreach ($tiendas as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars(($t['cr_tienda'] ?? '') . ' · ' . $t['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <label class="form-label fw-semibold">Operación</label>
            <div class="btn-group w-100 mb-3" role="group">
                <input type="radio" class="btn-check" name="modo" id="m_inst" value="instalacion" checked>
                <label class="btn btn-outline-primary modo-btn" for="m_inst">Instalación</label>
                <input type="radio" class="btn-check" name="modo" id="m_ret" value="retiro">
                <label class="btn btn-outline-primary modo-btn" for="m_ret">Retiro</label>
                <input type="radio" class="btn-check" name="modo" id="m_rep" value="reemplazo">
                <label class="btn btn-outline-primary modo-btn" for="m_rep">Reemplazo</label>
            </div>

            <div class="row g-2">
                <div class="col-8">
                    <label class="form-label">Serie <span class="text-danger">*</span></label>
                    <input type="text" name="serie" id="serie" class="form-control" required autocomplete="off">
                </div>
                <div class="col-4">
                    <label class="form-label">Código de barras</label>
                    <input type="text" name="codigo_barras" class="form-control" autocomplete="off"
                           inputmode="numeric" pattern="\d{8}" maxlength="8" title="8 dígitos numéricos">
                </div>
            </div>
            <div id="hintSerie" class="form-text mb-2"></div>

            <!-- Alta nueva (instalación de equipo que no está en tu stock) -->
            <div id="bloqueNuevo" class="row g-2 oculto">
                <div class="col-6">
                    <label class="form-label">Dispositivo</label>
                    <select name="dispositivo_id" id="dispositivo_id" class="form-select">
                        <option value="">—</option>
                        <?php foreach ($dispositivos as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label">Modelo</label>
                    <select name="modelo_id" id="modelo_id" class="form-select">
                        <option value="">—</option>
                        <?php foreach ($modelos as $m): ?>
                            <option value="<?= $m['id'] ?>" data-disp="<?= (int) ($m['dispositivo_id'] ?? 0) ?>">
                                <?= htmlspecialchars(($m['marca_nombre'] ? $m['marca_nombre'] . ' ' : '') . $m['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Equipo que sale (solo Reemplazo) -->
            <div id="bloqueSalida" class="row g-2 oculto mt-1">
                <div class="col-12"><hr class="my-2"><small class="text-muted">Equipo que se retira (pasa a tu stock)</small></div>
                <div class="col-8">
                    <label class="form-label">Serie del que sale</label>
                    <input type="text" name="salida_serie" class="form-control" autocomplete="off">
                </div>
                <div class="col-4">
                    <label class="form-label">CB del que sale</label>
                    <input type="text" name="salida_codigo_barras" class="form-control" autocomplete="off"
                           inputmode="numeric" pattern="\d{8}" maxlength="8" title="8 dígitos numéricos">
                </div>
            </div>

            <label class="form-label mt-3">Motivo</label>
            <select name="motivo" class="form-select mb-3">
                <option value="">—</option>
                <?php foreach (['Renovación tecnológica','Daño','Garantía','Alta','Baja','Traspaso'] as $mot): ?>
                    <option value="<?= htmlspecialchars($mot) ?>"><?= htmlspecialchars($mot) ?></option>
                <?php endforeach; ?>
            </select>

            <label class="form-label" id="lblFotoEquipo">Foto del equipo</label>
            <input type="file" name="foto_equipo" accept="image/*" capture="environment" class="form-control mb-3">

            <div id="bloqueFotoSalida" class="oculto">
                <label class="form-label">Foto del equipo retirado</label>
                <input type="file" name="foto_equipo_salida" accept="image/*" capture="environment" class="form-control mb-3">
            </div>

            <div class="d-flex gap-2">
                <a href="index.php?modulo=tiendas<?= $tiendaFija ? '&tienda_id=' . (int) $tiendaFija['id'] : '' ?>" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary flex-grow-1"><i class="fas fa-check me-1"></i> Guardar</button>
            </div>
        </div>
    </form>
</div>

<script>
(function () {
    const modo   = () => document.querySelector('input[name=modo]:checked').value;
    const serie  = document.getElementById('serie');
    const hint   = document.getElementById('hintSerie');
    const nuevo  = document.getElementById('bloqueNuevo');
    const salida = document.getElementById('bloqueSalida');
    const tiendaSel = document.querySelector('select[name=tienda_uso_id]');
    const tiendaFija = <?= $tiendaFija ? (int) $tiendaFija['id'] : 0 ?>;

    // Cascada dispositivo -> modelo
    const dispSel = document.getElementById('dispositivo_id');
    const modSel  = document.getElementById('modelo_id');
    if (dispSel) dispSel.addEventListener('change', () => {
        const d = dispSel.value;
        Array.from(modSel.options).forEach(o => {
            if (!o.value) return;
            o.hidden = d !== '' && o.dataset.disp !== d;
        });
        if (modSel.selectedOptions[0] && modSel.selectedOptions[0].hidden) modSel.value = '';
    });

    const fotoSalida = document.getElementById('bloqueFotoSalida');
    const lblFoto = document.getElementById('lblFotoEquipo');
    function pintarModo() {
        const m = modo();
        salida.classList.toggle('oculto', m !== 'reemplazo');
        fotoSalida.classList.toggle('oculto', m !== 'reemplazo');
        lblFoto.textContent = m === 'reemplazo' ? 'Foto del equipo instalado' : 'Foto del equipo';
        // en instalación mostramos "alta nueva" solo si la serie no está en tu stock (lo decide el lookup)
        if (m !== 'instalacion' && m !== 'reemplazo') nuevo.classList.add('oculto');
        hint.textContent = '';
        lookup();
    }
    document.querySelectorAll('input[name=modo]').forEach(r => r.addEventListener('change', pintarModo));

    let t = null;
    function lookup() {
        clearTimeout(t);
        t = setTimeout(async () => {
            const q = serie.value.trim();
            const m = modo();
            if (q.length < 3) { nuevo.classList.toggle('oculto', m === 'retiro'); return; }
            const tid = tiendaFija || (tiendaSel ? tiendaSel.value : '');
            try {
                const r = await fetch('index.php?controller=api&action=resolverSerie&serie=' + encodeURIComponent(q) + (tid ? '&tienda_id=' + tid : ''), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const d = await r.json();
                if (m === 'instalacion') {
                    if (d.encontrado && d.en_mi_stock) {
                        hint.className = 'form-text text-success mb-2';
                        hint.textContent = 'Está en tu stock — se moverá ese equipo a la tienda.';
                        nuevo.classList.add('oculto');
                    } else {
                        hint.className = 'form-text mb-2';
                        hint.textContent = d.encontrado ? 'Serie ya existe en otra ubicación (' + (d.ubicacion_corta || '') + ').' : 'Serie nueva — captura dispositivo y modelo.';
                        nuevo.classList.remove('oculto');
                    }
                } else if (m === 'retiro') {
                    nuevo.classList.add('oculto');
                    if (d.encontrado && d.en_esta_tienda) {
                        hint.className = 'form-text text-success mb-2';
                        hint.textContent = 'Instalado aquí — se retirará a tu stock.';
                    } else {
                        hint.className = 'form-text text-danger mb-2';
                        hint.textContent = 'Esa serie no está instalada en esta tienda.';
                    }
                } else { // reemplazo: la serie es del equipo que ENTRA
                    hint.className = 'form-text mb-2';
                    hint.textContent = (d.encontrado && d.en_mi_stock) ? 'El equipo que entra está en tu stock.' : 'Equipo que entra: captura dispositivo y modelo si es nuevo.';
                    nuevo.classList.toggle('oculto', d.encontrado && d.en_mi_stock);
                }
            } catch (e) { /* silencioso */ }
        }, 350);
    }
    serie.addEventListener('input', lookup);
    if (tiendaSel) tiendaSel.addEventListener('change', lookup);
    pintarModo();
})();
</script>
</body>
</html>
