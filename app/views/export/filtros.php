<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exportar inventario - Inventario 123</title>
    <?php include ROOT_PATH . '/app/views/components/favicon.php'; ?>
    <style> body { background:#f4f7f6; } .card { max-width:640px; margin:0 auto; } </style>
</head>
<body>
<?php include ROOT_PATH . '/app/views/components/navbar.php'; ?>
<div class="container py-4">
    <div class="card border-0 shadow-sm p-4">
        <h4 class="fw-bold mb-1"><i class="fas fa-file-excel me-2 text-success"></i>Exportar inventario</h4>
        <p class="text-muted small mb-3">
            Elige qué exportar. Deja un campo vacío para no filtrar por él.
            <?php if (\App\Helpers\Permisos::tipo() === 'fs'): ?>
                Se exporta únicamente tu stock personal.
            <?php elseif (\App\Helpers\Permisos::tipo() === 'coordinador' || \App\Helpers\Permisos::tipo() === 'ati'): ?>
                Se exporta únicamente lo de tu(s) plaza(s).
            <?php endif; ?>
        </p>

        <form method="GET" action="index.php" id="formExport">
            <input type="hidden" name="controller" value="export">
            <input type="hidden" name="action" value="inventario">
            <input type="hidden" name="generar" value="1">

            <?php if (!empty($plazas)): ?>
                <div class="mb-3">
                    <label class="form-label">Plaza</label>
                    <select name="plaza_id" id="selPlaza" class="form-select">
                        <option value="">Todas</option>
                        <?php foreach ($plazas as $p): ?>
                            <option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tienda</label>
                    <select name="tienda_id" id="selTienda" class="form-select">
                        <option value="">Todas</option>
                        <?php foreach ($tiendas as $t): ?>
                            <option value="<?= (int) $t['id'] ?>" data-plaza="<?= (int) $t['plaza_id'] ?>">
                                <?= htmlspecialchars($t['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="mb-3">
                <label class="form-label">Tipo de dispositivo</label>
                <select name="dispositivo_id" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($dispositivos as $d): ?>
                        <option value="<?= (int) $d['id'] ?>"><?= htmlspecialchars($d['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Estatus</label>
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($statusOpts as $val => $lbl): ?>
                        <option value="<?= $val ?>"><?= htmlspecialchars($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-4">
                <label class="form-label">Búsqueda (serie, código, N° activo, modelo…)</label>
                <input type="text" name="busqueda" class="form-control" maxlength="60" placeholder="Opcional">
            </div>

            <div class="d-flex gap-2">
                <button class="btn btn-success" id="btnGenerar">
                    <i class="fas fa-download me-1"></i>Generar Excel
                </button>
                <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
            </div>
            <div class="form-text mt-2" id="aviso" style="display:none">
                Generando el archivo… puede tardar un poco si son muchos registros.
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var selPlaza = document.getElementById('selPlaza');
    var selTienda = document.getElementById('selTienda');
    if (selPlaza && selTienda) {
        var todas = Array.prototype.slice.call(selTienda.querySelectorAll('option[data-plaza]'));
        function filtrar() {
            var p = selPlaza.value;
            selTienda.value = '';
            todas.forEach(function (o) {
                o.hidden = (p !== '' && o.getAttribute('data-plaza') !== p);
            });
        }
        selPlaza.addEventListener('change', filtrar);
    }
    document.getElementById('formExport').addEventListener('submit', function () {
        document.getElementById('btnGenerar').disabled = true;
        document.getElementById('aviso').style.display = 'block';
        // el botón se rehabilita solo al volver la descarga; por si el navegador
        // no dispara 'pageshow', lo soltamos tras unos segundos.
        setTimeout(function () {
            document.getElementById('btnGenerar').disabled = false;
        }, 8000);
    });
})();
</script>
</body>
</html>
