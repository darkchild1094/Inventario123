<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud #<?= (int) $sol['id'] ?> - Inventario 123</title>
    <?php include ROOT_PATH . '/app/views/components/favicon.php'; ?>
    <style>
        body { background:#f4f7f6; }
        .card { max-width:820px; margin:0 auto; }
        #firmaPad { border:2px dashed #adb5bd; border-radius:8px; touch-action:none; background:#fff; width:100%; height:180px; display:block; }
        .firma-img { max-height:120px; border:1px solid #e9ecef; border-radius:8px; background:#fff; }
    </style>
</head>
<body>
<?php include ROOT_PATH . '/app/views/components/navbar.php'; ?>
<?php
$badge = match ($sol['estado']) {
    'pendiente' => 'warning text-dark',
    'aprobada'  => 'success',
    'rechazada' => 'danger',
    'cancelada' => 'secondary',
    default     => 'light text-dark',
};
?>
<div class="container py-4">
    <div class="card border-0 shadow-sm p-4">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <h4 class="fw-bold mb-0">
                <i class="fas fa-right-left me-2"></i>Solicitud #<?= (int) $sol['id'] ?>
            </h4>
            <span class="badge bg-<?= $badge ?> text-uppercase fs-6"><?= $estados[$sol['estado']] ?? $sol['estado'] ?></span>
        </div>

        <?php
        $destTxt = $destinos[$sol['destino']] ?? $sol['destino'];
        $origen  = $sol['origen_nombre'] ?: ($sol['origen_tienda_nombre'] ? 'Tienda ' . $sol['origen_tienda_nombre'] : '—');
        ?>
        <dl class="row mb-3">
            <dt class="col-sm-3">Movimiento</dt><dd class="col-sm-9"><strong><?= htmlspecialchars($destTxt) ?></strong></dd>
            <dt class="col-sm-3">Origen</dt><dd class="col-sm-9"><?= htmlspecialchars($origen) ?></dd>
            <?php if ($sol['destino'] === 'en_bodega'): ?>
                <dt class="col-sm-3">Bodega destino</dt><dd class="col-sm-9"><?= htmlspecialchars($sol['bodega_nombre'] ?? '—') ?></dd>
            <?php elseif ($sol['destino'] === 'asignado'): ?>
                <dt class="col-sm-3">Recibe</dt><dd class="col-sm-9"><?= htmlspecialchars($sol['destino_usuario_nombre'] ?? '—') ?></dd>
            <?php endif; ?>
            <dt class="col-sm-3">Plaza</dt><dd class="col-sm-9"><?= htmlspecialchars($sol['plaza_nombre'] ?? '—') ?></dd>
            <dt class="col-sm-3">Solicitante</dt><dd class="col-sm-9"><?= htmlspecialchars($sol['solicitante_nombre'] ?? '—') ?></dd>
            <dt class="col-sm-3">Creada</dt><dd class="col-sm-9"><?= htmlspecialchars(substr((string) $sol['creado_en'], 0, 16)) ?></dd>
            <?php if (!empty($sol['nota'])): ?>
                <dt class="col-sm-3">Motivo</dt><dd class="col-sm-9"><?= htmlspecialchars($sol['nota']) ?></dd>
            <?php endif; ?>
            <?php if ($sol['estado'] === 'rechazada' && !empty($sol['motivo_rechazo'])): ?>
                <dt class="col-sm-3 text-danger">Motivo del rechazo</dt>
                <dd class="col-sm-9 text-danger"><?= htmlspecialchars($sol['motivo_rechazo']) ?></dd>
            <?php endif; ?>
        </dl>

        <h6 class="fw-semibold">Activos (<?= count($activos) ?>)</h6>
        <div class="table-responsive mb-3">
            <table class="table table-sm align-middle">
                <thead class="table-light"><tr><th>Equipo</th><th>Categoría</th><th>Serie</th><th>Código</th><th>Estatus actual</th></tr></thead>
                <tbody>
                <?php foreach ($activos as $a): ?>
                    <tr>
                        <td><?= htmlspecialchars(trim(($a['marca_nombre'] ?? '') . ' ' . ($a['modelo_nombre'] ?? '')) ?: 'Sin modelo') ?></td>
                        <td><?= htmlspecialchars($a['dispositivo_nombre'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($a['serie'] ?? 'ninguna') ?></td>
                        <td><?= htmlspecialchars($a['codigo_barras'] ?? '—') ?></td>
                        <td><span class="badge bg-light text-dark border text-uppercase"><?= htmlspecialchars($a['status'] ?? '—') ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php
        $dobleFirma = $sol['destino'] === 'garantia';
        $lbl1 = $dobleFirma ? 'Firma del ATI' : 'Firma del aprobador';
        ?>
        <div class="row g-3 mb-3">
            <div class="col-sm-<?= $dobleFirma ? 4 : 6 ?>">
                <div class="text-muted small mb-1">Firma del solicitante</div>
                <?php if (!empty($sol['firma_solicitante'])): ?>
                    <img class="firma-img" src="uploads/firmas/<?= htmlspecialchars($sol['firma_solicitante']) ?>" alt="Firma">
                    <div class="small text-muted"><?= htmlspecialchars($sol['solicitante_nombre'] ?? '') ?></div>
                <?php else: ?><span class="text-muted">—</span><?php endif; ?>
            </div>
            <div class="col-sm-<?= $dobleFirma ? 4 : 6 ?>">
                <div class="text-muted small mb-1"><?= $lbl1 ?></div>
                <?php if (!empty($sol['firma_aprobador'])): ?>
                    <img class="firma-img" src="uploads/firmas/<?= htmlspecialchars($sol['firma_aprobador']) ?>" alt="Firma">
                    <div class="small text-muted"><?= htmlspecialchars($sol['aprobador_nombre'] ?? '') ?></div>
                <?php else: ?><span class="text-muted">Pendiente</span><?php endif; ?>
            </div>
            <?php if ($dobleFirma): ?>
            <div class="col-sm-4">
                <div class="text-muted small mb-1">Firma del coordinador</div>
                <?php if (!empty($sol['firma_aprobador2'])): ?>
                    <img class="firma-img" src="uploads/firmas/<?= htmlspecialchars($sol['firma_aprobador2']) ?>" alt="Firma">
                    <div class="small text-muted"><?= htmlspecialchars($sol['aprobador2_nombre'] ?? '') ?></div>
                <?php else: ?><span class="text-muted">Pendiente</span><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($movimientos)): ?>
            <h6 class="fw-semibold">Movimientos generados</h6>
            <ul class="small text-muted">
                <?php foreach ($movimientos as $m): ?>
                    <li>#<?= (int) $m['id'] ?> · <?= htmlspecialchars($m['evento']) ?> ·
                        serie <?= htmlspecialchars($m['serie'] ?? 'ninguna') ?>
                        (<?= htmlspecialchars($m['codigo_barras'] ?? '—') ?>)</li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if (!empty($puedeFirmar)): ?>
            <hr>
            <h6 class="fw-semibold">Firmar (requiere tu firma)</h6>
            <form method="POST" action="index.php?controller=solicitud&action=aprobar" id="formAprobar">
                <input type="hidden" name="id" value="<?= (int) $sol['id'] ?>">
                <canvas id="firmaPad"></canvas>
                <div class="mt-1 mb-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnLimpiarFirma">
                        <i class="fas fa-eraser me-1"></i>Limpiar
                    </button>
                </div>
                <input type="hidden" name="firma" id="firmaInput">
                <button class="btn btn-success" id="btnAprobar"><i class="fas fa-check me-1"></i>Firmar</button>
            </form>

            <form method="POST" action="index.php?controller=solicitud&action=rechazar" class="mt-3"
                  onsubmit="return this.motivo.value.trim() !== '' || (alert('Indica el motivo del rechazo.'), false);">
                <input type="hidden" name="id" value="<?= (int) $sol['id'] ?>">
                <div class="input-group">
                    <input type="text" name="motivo" class="form-control" maxlength="255" placeholder="Motivo del rechazo">
                    <button class="btn btn-outline-danger"><i class="fas fa-times me-1"></i>Rechazar</button>
                </div>
            </form>
        <?php endif; ?>

        <?php if (!empty($puedeCancelar)): ?>
            <hr>
            <form method="POST" action="index.php?controller=solicitud&action=cancelar"
                  onsubmit="return confirm('¿Cancelar esta solicitud?');">
                <input type="hidden" name="id" value="<?= (int) $sol['id'] ?>">
                <button class="btn btn-outline-secondary"><i class="fas fa-ban me-1"></i>Cancelar solicitud</button>
            </form>
        <?php endif; ?>

        <div class="mt-3">
            <a href="index.php?controller=solicitud&action=index" class="btn btn-link">&larr; Volver a Traslados</a>
        </div>
    </div>
</div>

<?php if (!empty($puedeFirmar)): ?>
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
        ctx.lineWidth = 2.2; ctx.lineCap = 'round'; ctx.strokeStyle = '#111';
    }
    ajustar();
    window.addEventListener('resize', ajustar);

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
        ctx.clearRect(0, 0, canvas.width, canvas.height); huboTrazo = false;
    });
    document.getElementById('formAprobar').addEventListener('submit', function (ev) {
        if (!huboTrazo) { ev.preventDefault(); alert('Falta tu firma para aprobar.'); return; }
        document.getElementById('firmaInput').value = canvas.toDataURL('image/png');
        document.getElementById('btnAprobar').disabled = true;
    });
})();
</script>
<?php endif; ?>
</body>
</html>
