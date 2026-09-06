<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva solicitud de traslado - Inventario 123</title>
    <?php include ROOT_PATH . '/app/views/components/favicon.php'; ?>
    <style>
        body { background:#f4f7f6; }
        .card { max-width:760px; margin:0 auto; }
        #firmaPad { border:2px dashed #adb5bd; border-radius:8px; touch-action:none; background:#fff; width:100%; height:180px; display:block; }
        .activo-row { border:1px solid #e9ecef; border-radius:8px; }
        .activo-row:has(input:checked) { border-color:#0d6efd; background:#f0f6ff; }
    </style>
</head>
<body>
<?php include ROOT_PATH . '/app/views/components/navbar.php'; ?>
<div class="container py-4">
    <div class="card border-0 shadow-sm p-4">
        <h4 class="fw-bold mb-3"><i class="fas fa-right-left me-2"></i>Nueva solicitud de traslado a bodega</h4>

        <?php if (empty($activos)): ?>
            <div class="alert alert-info">No tienes activos <strong>asignados</strong> en tu stock personal para trasladar.</div>
            <a href="index.php?controller=solicitud&action=index" class="btn btn-outline-secondary">Volver</a>
        <?php else: ?>
        <form method="POST" action="index.php?controller=solicitud&action=guardar" id="formSolicitud">
            <div class="mb-3">
                <label class="form-label fw-semibold">Activos a trasladar *</label>
                <div class="d-flex flex-column gap-2">
                    <?php foreach ($activos as $a): ?>
                        <label class="activo-row d-flex align-items-center gap-2 p-2 mb-0">
                            <input type="checkbox" class="form-check-input mt-0" name="activos[]" value="<?= (int) $a['id'] ?>">
                            <span>
                                <strong><?= htmlspecialchars(trim(($a['marca_nombre'] ?? '') . ' ' . ($a['modelo_nombre'] ?? '')) ?: 'Sin modelo') ?></strong>
                                <span class="text-muted">· <?= htmlspecialchars($a['dispositivo_nombre'] ?? '—') ?></span><br>
                                <small class="text-muted">
                                    Serie: <?= htmlspecialchars($a['serie'] ?? 'ninguna') ?>
                                    &nbsp;·&nbsp; Código: <?= htmlspecialchars($a['codigo_barras'] ?? '—') ?>
                                </small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Bodega destino *</label>
                <select name="destino_bodega_id" class="form-select" required>
                    <?php foreach ($bodegas as $b): ?>
                        <option value="<?= (int) $b['id'] ?>" <?= ((int) $b['id'] === (int) $bodegaDefault) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Motivo del traslado</label>
                <textarea name="nota" class="form-control" rows="2" maxlength="255"
                          placeholder="Ej.: fin de proyecto, equipo sobrante de instalación…"></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Tu firma *</label>
                <canvas id="firmaPad"></canvas>
                <div class="mt-1">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnLimpiarFirma">
                        <i class="fas fa-eraser me-1"></i>Limpiar
                    </button>
                    <small class="text-muted ms-2">Dibuja tu firma con el dedo o el mouse.</small>
                </div>
                <input type="hidden" name="firma" id="firmaInput">
            </div>

            <div class="d-flex gap-2">
                <button class="btn btn-primary" id="btnEnviar"><i class="fas fa-paper-plane me-1"></i>Enviar solicitud</button>
                <a href="index.php?controller=solicitud&action=index" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    var canvas = document.getElementById('firmaPad');
    if (!canvas) return;
    var ctx = canvas.getContext('2d');
    var dibujando = false, huboTrazo = false, lastX = 0, lastY = 0;

    function ajustar() {
        var ratio = window.devicePixelRatio || 1;
        var rect = canvas.getBoundingClientRect();
        canvas.width = rect.width * ratio;
        canvas.height = rect.height * ratio;
        ctx.scale(ratio, ratio);
        ctx.lineWidth = 2.2;
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#111';
    }
    ajustar();
    window.addEventListener('resize', function () { var d = canvas.toDataURL(); ajustar(); });

    function pos(e) {
        var rect = canvas.getBoundingClientRect();
        var t = e.touches ? e.touches[0] : e;
        return [t.clientX - rect.left, t.clientY - rect.top];
    }
    function start(e) { e.preventDefault(); dibujando = true; var p = pos(e); lastX = p[0]; lastY = p[1]; }
    function move(e) {
        if (!dibujando) return;
        e.preventDefault();
        var p = pos(e);
        ctx.beginPath(); ctx.moveTo(lastX, lastY); ctx.lineTo(p[0], p[1]); ctx.stroke();
        lastX = p[0]; lastY = p[1]; huboTrazo = true;
    }
    function end() { dibujando = false; }

    canvas.addEventListener('pointerdown', start);
    canvas.addEventListener('pointermove', move);
    window.addEventListener('pointerup', end);

    document.getElementById('btnLimpiarFirma').addEventListener('click', function () {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        huboTrazo = false;
    });

    document.getElementById('formSolicitud').addEventListener('submit', function (ev) {
        if (!document.querySelector('input[name="activos[]"]:checked')) {
            ev.preventDefault(); alert('Selecciona al menos un activo.'); return;
        }
        if (!huboTrazo) {
            ev.preventDefault(); alert('Falta tu firma.'); return;
        }
        document.getElementById('firmaInput').value = canvas.toDataURL('image/png');
        document.getElementById('btnEnviar').disabled = true;
    });
})();
</script>
</body>
</html>
