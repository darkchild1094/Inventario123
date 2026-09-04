<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario 123 - Historial</title>
    <?php include ROOT_PATH . '/app/views/components/favicon.php'; ?>
</head>
<body>
<?php include ROOT_PATH . '/app/views/components/navbar.php'; ?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 fw-bold mb-0"><i class="fas fa-clock-rotate-left text-primary me-2"></i>Historial de movimientos</h2>
            <?php if (isset($paginacion)): ?>
                <small class="text-muted"><?= number_format($paginacion['total_resultados']) ?> movimiento<?= $paginacion['total_resultados'] !== 1 ? 's' : '' ?></small>
            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" action="index.php" id="formFiltrosHist" class="row g-3 align-items-end">
                <input type="hidden" name="controller" value="historial">
                <input type="hidden" name="action" value="index">

                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label text-muted small fw-bold mb-1">Serie / código</label>
                    <input type="text" name="serie" id="hf_serie" class="form-control bg-light"
                           value="<?= htmlspecialchars($f_serie) ?>" autocomplete="off" placeholder="Buscar activo...">
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label text-muted small fw-bold mb-1">Evento</label>
                    <select name="evento" id="hf_evento" class="form-select bg-light">
                        <option value="">Todos...</option>
                        <?php foreach ($eventos as $val => $label): ?>
                            <option value="<?= $val ?>" <?= $f_evento === $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label text-muted small fw-bold mb-1">Tienda</label>
                    <select name="tienda_id" id="hf_tienda" class="form-select bg-light">
                        <option value="">Todas...</option>
                        <?php foreach ($tiendasFiltro as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= (string) $f_tienda_id === (string) $t['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label text-muted small fw-bold mb-1">Usuario</label>
                    <select name="usuario_id" id="hf_usuario" class="form-select bg-light">
                        <option value="">Todos...</option>
                        <?php foreach ($usuariosFiltro as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= (string) $f_usuario_id === (string) $u['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label text-muted small fw-bold mb-1">Desde</label>
                    <input type="date" name="desde" id="hf_desde" class="form-control bg-light" value="<?= htmlspecialchars($f_desde) ?>">
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label text-muted small fw-bold mb-1">Hasta</label>
                    <input type="date" name="hasta" id="hf_hasta" class="form-control bg-light" value="<?= htmlspecialchars($f_hasta) ?>">
                </div>

                <div class="col-6 col-md-3 col-xl-2">
                    <a href="index.php?controller=historial&action=index" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-sync-alt me-1"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div id="histResultados">
        <?php require ROOT_PATH . '/app/views/historial/_resultados.php'; ?>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('formFiltrosHist');
    const cont = document.getElementById('histResultados');
    if (!form || !cont) return;

    let enCurso = null;
    async function aplicar(pagina) {
        const params = new URLSearchParams(new FormData(form));
        if (pagina) params.set('pagina', pagina);
        if (enCurso) enCurso.abort();
        enCurso = new AbortController();
        cont.style.opacity = '0.5';
        try {
            const resp = await fetch('index.php?' + params.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: enCurso.signal,
            });
            cont.innerHTML = await resp.text();
            history.replaceState(null, '', 'index.php?' + params.toString());
        } catch (e) {
            if (e.name !== 'AbortError') cont.innerHTML = '<p class="text-danger text-center py-4">Error al cargar.</p>';
        } finally {
            cont.style.opacity = '1';
        }
    }

    ['hf_evento', 'hf_tienda', 'hf_usuario', 'hf_desde', 'hf_hasta'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', () => aplicar());
    });
    const serie = document.getElementById('hf_serie');
    if (serie) {
        let t = null;
        serie.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => aplicar(), 400); });
    }
    cont.addEventListener('click', (e) => {
        const link = e.target.closest('.pagination a.page-link');
        if (!link) return;
        e.preventDefault();
        const url = new URL(link.href, window.location.origin);
        aplicar(url.searchParams.get('pagina'));
    });
})();
</script>
</body>
</html>
