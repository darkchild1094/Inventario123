<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario 123</title>
    <?php include ROOT_PATH . '/app/views/components/favicon.php'; ?>
</head>
<body>
<?php include ROOT_PATH . '/app/views/components/navbar.php'; ?>

<div class="container py-4">

    <!-- Encabezado -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 text-gray-800 fw-bold mb-0">
                <?php if ($vista === 'mi_stock'): ?>
                    <i class="fas fa-toolbox text-primary me-2"></i> Mi Stock
                <?php elseif ($vista === 'todos'): ?>
                    <i class="fas fa-list text-primary me-2"></i> Todos los Activos
                <?php else: ?>
                    <i class="fas fa-warehouse text-primary me-2"></i> Inventario de Bodega
                <?php endif; ?>
            </h2>
            <?php if (isset($paginacion)): ?>
                <small class="text-muted">
                    <?= number_format($paginacion['total_resultados']) ?> activo<?= $paginacion['total_resultados'] !== 1 ? 's' : '' ?>
                </small>
            <?php endif; ?>
        </div>
        <?php /* Nuevo Activo está en la navbar */ ?>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" action="index.php" id="formFiltros" class="row g-3 align-items-end">
                <input type="hidden" name="vista" value="<?= htmlspecialchars($vista) ?>">

                <div class="col-md-3">
                    <label class="form-label text-muted small fw-bold mb-1">Búsqueda rápida</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" name="busqueda" id="filtroBusqueda" class="form-control bg-light border-start-0"
                               placeholder="Serie, placa, modelo..."
                               value="<?= htmlspecialchars($busqueda ?? '') ?>" autocomplete="off">
                    </div>
                </div>

                <?php if (\App\Helpers\Permisos::puedeFiltrarPorPlaza()): ?>
                <div class="col-md-2">
                    <label class="form-label text-muted small fw-bold mb-1">Negocio</label>
                    <select name="negocio_id" id="filtroNegocio" class="form-select bg-light">
                        <option value="">Todos...</option>
                        <?php foreach ($negocios as $n): ?>
                            <option value="<?= $n['id'] ?>" <?= (($negocio_id ?? null) == $n['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($n['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-muted small fw-bold mb-1">Región</label>
                    <select name="region_id" id="filtroRegion" class="form-select bg-light">
                        <option value="">Todas...</option>
                        <?php foreach ($regiones as $r): ?>
                            <option value="<?= $r['id'] ?>" data-negocio-id="<?= (int) ($r['negocio_id'] ?? 0) ?>"
                                <?= (($region_id ?? null) == $r['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-muted small fw-bold mb-1">Plaza</label>
                    <select name="plaza_id" id="filtroPlaza" class="form-select bg-light">
                        <option value="">Todas...</option>
                        <?php foreach ($plazas as $p): ?>
                            <option value="<?= $p['id'] ?>"
                                data-negocio-id="<?= (int) ($p['negocio_id'] ?? 0) ?>"
                                data-region-id="<?= (int) ($p['region_id']  ?? 0) ?>"
                                <?= (($plaza_id ?? null) == $p['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-muted small fw-bold mb-1">Usuario</label>
                    <select name="usuario_id" id="filtroUsuario" class="form-select bg-light">
                        <option value="">Todos...</option>
                        <?php foreach ($usuariosFiltro as $u): ?>
                            <option value="<?= $u['id'] ?>" data-plaza-id="<?= (int) ($u['plaza_id'] ?? 0) ?>"
                                <?= (($usuario_id ?? null) == $u['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="col-md-2">
                    <label class="form-label text-muted small fw-bold mb-1">Status</label>
                    <select name="status" id="filtroStatus" class="form-select bg-light">
                        <option value="">Todos...</option>
                        <?php
                        $statusOpts = [
                            'en_bodega' => 'En Bodega',
                            'en_uso'    => 'En Uso',
                            'baja'      => 'Baja',
                            'garantia'  => 'Garantía',
                            'asignado'  => 'Asignado',
                        ];
                        foreach ($statusOpts as $val => $label):
                        ?>
                            <option value="<?= $val ?>" <?= (($status ?? null) === $val) ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <a href="index.php?vista=<?= htmlspecialchars($vista) ?>"
                       class="btn btn-outline-secondary w-100">
                        <i class="fas fa-sync-alt me-1"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div id="resultadosContainer">
        <?php require ROOT_PATH . '/app/views/home/_resultados.php'; ?>
    </div>

</div>

<!-- Modal de detalle de activo (se llena vía AJAX, sin recargar) -->
<div class="modal fade" id="modalDetalleActivo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle del activo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalDetalleBody">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
            </div>
            <div class="modal-footer" id="modalDetalleFooter">
                <!-- Botones de editar/eliminar se agregan aquí dinámicamente según permisos -->
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const form      = document.getElementById('formFiltros');
    const contenedor = document.getElementById('resultadosContainer');
    if (!form || !contenedor) return;

    let controladorEnCurso = null;

    async function aplicarFiltros(pagina) {
        const params = new URLSearchParams(new FormData(form));
        if (pagina) params.set('pagina', pagina);

        // Cancela una búsqueda anterior si sigue en vuelo (evita respuestas fuera de orden)
        if (controladorEnCurso) controladorEnCurso.abort();
        controladorEnCurso = new AbortController();

        contenedor.style.opacity = '0.5';
        try {
            const resp = await fetch('index.php?' + params.toString(), {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: controladorEnCurso.signal,
            });
            const html = await resp.text();
            contenedor.innerHTML = html;

            // Refleja los filtros actuales en la URL sin recargar (permite compartir/recargar la vista)
            history.replaceState(null, '', 'index.php?' + params.toString());
        } catch (err) {
            if (err.name !== 'AbortError') {
                contenedor.innerHTML = '<div class="col-12 text-center text-danger py-5">Error al cargar resultados. Intenta de nuevo.</div>';
            }
        } finally {
            contenedor.style.opacity = '1';
        }
    }

    // ── Filtrado en cascada: Negocio -> Región/Plaza, Región -> Plaza, Plaza -> Usuario ──
    const selNegocio = document.getElementById('filtroNegocio');
    const selRegion  = document.getElementById('filtroRegion');
    const selPlaza   = document.getElementById('filtroPlaza');
    const selUsuario = document.getElementById('filtroUsuario');

    function filtrarOpciones(select, atributo, valorPermitido) {
        if (!select) return;
        let seleccionSigueValida = false;
        Array.from(select.options).forEach(opt => {
            if (opt.value === '') { opt.hidden = false; return; } // "Todos..." siempre visible
            const valorOpt = opt.getAttribute(atributo);
            const visible  = !valorPermitido || valorOpt === String(valorPermitido);
            opt.hidden = !visible;
            if (opt.selected && visible) seleccionSigueValida = true;
        });
        // Si la opción que tenía seleccionada ya no aplica al nuevo negocio/región, se limpia
        if (select.value !== '' && !seleccionSigueValida) select.value = '';
    }

    function aplicarCascada() {
        const negocioSel = selNegocio ? selNegocio.value : '';
        const regionSel  = selRegion  ? selRegion.value  : '';
        const plazaSel   = selPlaza   ? selPlaza.value   : '';

        // Región y Plaza dependen del Negocio elegido
        filtrarOpciones(selRegion, 'data-negocio-id', negocioSel);
        filtrarOpciones(selPlaza,  'data-negocio-id', negocioSel);
        // Plaza también se acota por Región, si hay una elegida
        if (selRegion && selRegion.value) {
            filtrarOpciones(selPlaza, 'data-region-id', selRegion.value);
        }
        // Usuario se acota por la Plaza elegida
        filtrarOpciones(selUsuario, 'data-plaza-id', selPlaza ? selPlaza.value : '');
    }

    if (selNegocio) selNegocio.addEventListener('change', () => { aplicarCascada(); aplicarFiltros(); });
    if (selRegion)  selRegion.addEventListener('change',  () => { aplicarCascada(); aplicarFiltros(); });
    if (selPlaza)   selPlaza.addEventListener('change',   () => { aplicarCascada(); aplicarFiltros(); });

    // Aplica la cascada al cargar, por si ya venía un negocio/plaza preseleccionado (ej. desde la URL)
    aplicarCascada();

    // Selects sin dependencias en cascada: se aplican al instante al cambiar
    ['filtroUsuario', 'filtroStatus'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', () => aplicarFiltros());
    });

    // Búsqueda por texto: en tiempo real con un pequeño debounce para no
    // disparar una petición por cada tecla mientras se sigue escribiendo
    const campoBusqueda = document.getElementById('filtroBusqueda');
    if (campoBusqueda) {
        let temporizador = null;
        campoBusqueda.addEventListener('input', () => {
            clearTimeout(temporizador);
            temporizador = setTimeout(() => aplicarFiltros(), 400);
        });
    }

    // Paginación: interceptar clics dentro del contenedor (los links se regeneran en cada respuesta)
    contenedor.addEventListener('click', (e) => {
        const link = e.target.closest('.pagination a.page-link');
        if (!link) return;
        e.preventDefault();
        const url = new URL(link.href, window.location.origin);
        const pagina = url.searchParams.get('pagina');
        aplicarFiltros(pagina);
        window.scrollTo({ top: contenedor.offsetTop - 20, behavior: 'smooth' });
    });

    // ── Modal de detalle: clic en cualquier tarjeta abre sus datos completos
    //    sin recargar la página. Los clics en Editar/Eliminar (data-no-modal)
    //    se ignoran aquí y siguen su propio comportamiento normal. ──────────
    const modalEl       = document.getElementById('modalDetalleActivo');
    const modalBody     = document.getElementById('modalDetalleBody');
    const modalFooter   = document.getElementById('modalDetalleFooter');
    const modalInstancia = (modalEl && window.bootstrap) ? new bootstrap.Modal(modalEl) : null;

    contenedor.addEventListener('click', async (e) => {
        if (e.target.closest('[data-no-modal]')) return;
        const card = e.target.closest('.activo-card');
        if (!card || !modalInstancia) return;

        const id = card.getAttribute('data-activo-id');
        modalBody.innerHTML   = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>';
        modalFooter.innerHTML = '';
        modalInstancia.show();

        try {
            const resp = await fetch('index.php?action=detalle&id=' + encodeURIComponent(id), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await resp.json();

            if (!data.success) {
                modalBody.innerHTML = '<div class="alert alert-danger mb-0">' + (data.message || 'No se pudo cargar el detalle.') + '</div>';
                return;
            }

            modalBody.innerHTML = data.html;

            if (data.puedeEditar) {
                const btnEditar = document.createElement('a');
                btnEditar.href = 'index.php?action=editar&id=' + data.id;
                btnEditar.className = 'btn btn-outline-primary';
                btnEditar.innerHTML = '<i class="fas fa-edit me-1"></i> Editar';
                modalFooter.appendChild(btnEditar);
            }
            if (data.puedeEliminar) {
                const btnEliminar = document.createElement('button');
                btnEliminar.type = 'button';
                btnEliminar.className = 'btn btn-outline-danger';
                btnEliminar.innerHTML = '<i class="fas fa-trash-alt me-1"></i> Eliminar';
                btnEliminar.addEventListener('click', () => {
                    if (!confirm('¿Eliminar este activo permanentemente?')) return;
                    const formEliminar = document.createElement('form');
                    formEliminar.method = 'POST';
                    formEliminar.action = 'index.php?action=eliminar';
                    const input = document.createElement('input');
                    input.type  = 'hidden';
                    input.name  = 'id';
                    input.value = data.id;
                    formEliminar.appendChild(input);
                    document.body.appendChild(formEliminar);
                    formEliminar.submit();
                });
                modalFooter.appendChild(btnEliminar);
            }
        } catch (err) {
            modalBody.innerHTML = '<div class="alert alert-danger mb-0">Error de conexión. Intenta de nuevo.</div>';
        }
    });

    // Botón "Limpiar": también sin recargar
    const btnLimpiar = form.parentElement.querySelector('a.btn-outline-secondary');
    if (btnLimpiar) {
        btnLimpiar.addEventListener('click', (e) => {
            e.preventDefault();
            form.reset();
            aplicarFiltros();
        });
    }
})();
</script>


<style>
.hover-shadow:hover { transform: translateY(-3px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15) !important; }
.transition-all     { transition: all .3s ease; }
</style>
</body>
</html>