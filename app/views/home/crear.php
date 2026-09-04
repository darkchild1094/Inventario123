<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario 123 - Registrar Activo</title>
    <?php include ROOT_PATH . '/app/views/components/favicon.php'; ?>
    <style>
        body { background-color: #f8f9fa; }
        .card { border-radius: 15px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,.1); }
        .form-label { font-weight: 600; color: #495057; }
        .section-title { color: #0d6efd; border-bottom: 2px solid #e9ecef; padding-bottom: 8px; margin-bottom: 20px; margin-top: 30px; }
        .campo-condicional { display: none; }
        .usuario-fijo { background:#f1f3f5; border:1px solid #dee2e6; border-radius:8px; padding:10px 14px; display:flex; align-items:center; gap:10px; }
    </style>
</head>
<body>
<?php include ROOT_PATH . '/app/views/components/navbar.php'; ?>

<?php
$tipo          = $_SESSION['usuario']['tipo']     ?? '';
$plazaId       = (int)($_GET['plaza_id'] ?? $_SESSION['usuario']['plaza_id'] ?? 0);
$negocioId     = (int)($_GET['negocio_id'] ?? $negocioId ?? 0);
$usuarioId     = (int)($_SESSION['usuario']['id']       ?? 0);
$usuarioPlazas = $_SESSION['usuario']['plazas'] ?? [];

$esAdmin       = $tipo === 'admin';
$esCoordinador = $tipo === 'coordinador';
$esFs          = $tipo === 'fs';
$esAti         = $tipo === 'ati';
$esAdminOrCoordinador = $esAdmin || $esCoordinador;
$statusDefault = $esCoordinador ? 'en_bodega' : 'asignado';

$mostrarNegocio = !empty($negociosDisponibles) && count($negociosDisponibles) > 1;
$mostrarPlaza   = !empty($plazasPorNegocio) && count($plazasPorNegocio) > 1;

// Bodega de la plaza seleccionada (vía bodega_acceso_plaza)
$bodegaDefault = null;
foreach ($bodegas as $b) {
    $idsAcceso = array_map('intval', array_filter(explode(',', $b['plazas_ids'] ?? '')));
    if (in_array($plazaId, $idsAcceso, true)) {
        if ($bodegaDefault === null) {
            $bodegaDefault = $b;
        }
    }
}

$tiendasPlaza = array_values(array_filter($tiendas, fn($t) => (int)$t['plaza_id'] === $plazaId));

$bodegasPlaza = array_values(array_filter($bodegas, fn($b) => in_array($plazaId, array_map('intval', array_filter(explode(',', $b['plazas_ids'] ?? ''))), true)));

$usuariosPlaza = array_values(array_filter($usuariosPorPlaza, fn($u) =>
    in_array($u['tipo'], ['fs', 'ati', 'coordinador'], true)
));

// Los admin no están atados a una plaza específica (tienen acceso global),
// por eso no aparecen en $usuariosPorPlaza aunque deban poder ser elegidos
// como destinatarios de un activo igual que cualquier otro usuario.
$idsYaEnLista = array_column($usuariosPlaza, 'id');
foreach ($usuarios as $u) {
    if ($u['tipo'] === 'admin' && !in_array($u['id'], $idsYaEnLista, true)) {
        $usuariosPlaza[] = $u;
    }
}

// Usuario actual
$usuarioActual = null;
foreach ($usuarios as $u) {
    if ((int)$u['id'] === $usuarioId) { $usuarioActual = $u; break; }
}
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-9">
            <div class="card p-4">

                <div class="d-flex align-items-center mb-4">
                    <a href="index.php" class="btn btn-outline-secondary btn-sm me-3">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                        <h2 class="mb-0">Registrar Activo</h2>
                        <small class="text-muted">
                            Bodega: <strong><?= htmlspecialchars($bodegaDefault['nombre'] ?? 'Sin bodega') ?></strong>
                        </small>
                    </div>
                </div>

                <div id="alertContainer">
                <?php if (!empty($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_SESSION['success']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
                <?php if (!empty($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($_SESSION['error']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                </div>

                <form action="index.php?action=guardar" method="POST" id="formActivo">
                    <input type="hidden" name="stock_destino_default" id="stock_destino_hidden" value="<?= !empty($bodegaPorNegocio['id']) ? 'bodega_' . $bodegaPorNegocio['id'] : (!empty($bodegaOxxo['id']) ? 'bodega_' . $bodegaOxxo['id'] : '') ?>">
                    <?php if ($mostrarNegocio || $mostrarPlaza): ?>
                        <div class="row g-3 mb-3">
                            <?php if ($mostrarNegocio): ?>
                                <div class="col-md-6">
                                    <label class="form-label">Unidad de negocio <span class="text-danger">*</span></label>
                                    <select name="negocio_id" id="negocio_activo" class="form-select"
                                            onchange="window.location='index.php?action=crear&negocio_id=' + this.value">
                                        <?php foreach ($negociosDisponibles as $n): ?>
                                            <option value="<?= $n['id'] ?>" <?= (int)$n['id'] === $negocioId ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($n['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Selecciona la unidad de negocio antes de elegir plaza.</small>
                                </div>
                            <?php else: ?>
                                <input type="hidden" name="negocio_id" value="<?= $negocioId ?>">
                            <?php endif; ?>

                            <?php if ($mostrarPlaza): ?>
                                <div class="col-md-6">
                                    <label class="form-label">Plaza activa <span class="text-danger">*</span></label>
                                    <select name="plaza_id" id="plaza_activa" class="form-select"
                                            onchange="window.location='index.php?action=crear&negocio_id=<?= $negocioId ?>&plaza_id=' + this.value">
                                        <?php foreach ($plazasPorNegocio as $p): ?>
                                            <option value="<?= $p['id'] ?>" <?= (int)$p['id'] === $plazaId ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($p['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">La tienda y la bodega se cargarán según esta plaza.</small>
                                </div>
                            <?php else: ?>
                                <input type="hidden" name="plaza_id" value="<?= $plazaId ?>">
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="plaza_id" value="<?= $plazaId ?>">
                        <input type="hidden" name="negocio_id" value="<?= $negocioId ?>">
                    <?php endif; ?>

                    <!-- ── Dispositivo y Modelo ───────────────────────────── -->
                    <h5 class="section-title"><i class="fas fa-laptop me-2"></i> Dispositivo</h5>
                    <div class="row g-3">

                        <div class="col-md-4">
                            <label class="form-label">Dispositivo <span class="text-danger">*</span></label>
                            <select name="dispositivo_id" id="dispositivo" class="form-select" required
                                    onchange="cargarModelos()">
                                <option value="">Seleccione...</option>
                                <?php foreach ($dispositivos as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Modelo <span class="text-danger">*</span></label>
                            <select name="modelo_id" id="modelo" class="form-select" required disabled>
                                <option value="">Primero seleccione dispositivo</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Estatus <span class="text-danger">*</span></label>
                            <select name="status" id="estatus" class="form-select" required
                                    onchange="manejarEstatus()">

                                <?php if ($esAdmin || $esCoordinador): ?>
                                    <option value="en_bodega" <?= $statusDefault === 'en_bodega' ? 'selected' : '' ?>>En Bodega</option>
                                    <option value="en_uso" <?= $statusDefault === 'en_uso' ? 'selected' : '' ?>>En Uso</option>
                                    <option value="asignado" <?= $statusDefault === 'asignado' ? 'selected' : '' ?>>Asignado</option>
                                    <option value="garantia" <?= $statusDefault === 'garantia' ? 'selected' : '' ?>>Garantía</option>
                                    <option value="baja" <?= $statusDefault === 'baja' ? 'selected' : '' ?>>Baja</option>

                                <?php elseif ($esFs): ?>
                                    <option value="asignado" selected>Asignado (a mí)</option>
                                    <option value="en_uso">En Uso</option>
                                    <option value="en_bodega">En Bodega</option>
                                    <option value="garantia">Garantía</option>

                                <?php elseif ($esAti): ?>
                                    <option value="asignado" <?= $statusDefault === 'asignado' ? 'selected' : '' ?>>Asignado</option>
                                    <option value="en_uso" <?= $statusDefault === 'en_uso' ? 'selected' : '' ?>>En Uso</option>
                                    <option value="en_bodega" <?= $statusDefault === 'en_bodega' ? 'selected' : '' ?>>En Bodega</option>
                                    <option value="garantia" <?= $statusDefault === 'garantia' ? 'selected' : '' ?>>Garantía</option>
                                    <option value="baja" <?= $statusDefault === 'baja' ? 'selected' : '' ?>>Baja</option>
                                <?php endif; ?>

                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-primary">Serie <span class="text-danger">*</span></label>
                            <input type="text" name="serie" id="campo_serie"
                                   class="form-control border-primary" required autofocus
                                   placeholder="Escanee o escriba la serie...">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Código de barras</label>
                            <input type="text" name="codigo_barras" class="form-control" placeholder="Opcional">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">N° de activo</label>
                            <input type="text" name="num_activo" class="form-control" placeholder="Opcional">
                        </div>

                    </div>

                    <!-- ── Asignación / Ubicación ─────────────────────────── -->
                    <h5 class="section-title"><i class="fas fa-map-marker-alt me-2"></i> Asignación</h5>
                    <div class="row g-3">

                        <!-- CAMPO ASIGNADO ─────────────────────────────────────────────── -->
                        <div class="col-md-6 campo-condicional" id="campo_asignado">
                            <label class="form-label text-primary fw-bold">
                                <i class="fas fa-user me-1"></i> Asignado a <span class="text-danger">*</span>
                            </label>

                            <?php if ($esFs): ?>
                                <!-- FS: solo él mismo, no editable -->
                                <input type="hidden" name="asignado_usuario_id" value="<?= $usuarioId ?>">
                                <div class="usuario-fijo">
                                    <i class="fas fa-user-circle text-primary fa-lg"></i>
                                    <div>
                                        <div class="fw-bold"><?= htmlspecialchars($usuarioActual['nombre'] ?? '—') ?></div>
                                        <small class="text-muted text-uppercase">FS · Stock personal</small>
                                    </div>
                                </div>

                            <?php else: ?>
                                <select name="asignado_usuario_id" id="select_asignado" class="form-select">
                                    <option value="">Seleccione usuario...</option>
                                    <?php $listaAsignables = $esAdmin ? $usuarios : $usuariosPlaza; ?>
                                    <?php foreach ($listaAsignables as $u): ?>
                                        <?php if (in_array($u['tipo'], ['admin', 'fs', 'ati', 'coordinador'])): ?>
                                            <option value="<?= $u['id'] ?>"
                                                <?= (int)$u['id'] === $usuarioId ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($u['nombre']) ?>
                                                (<?= strtoupper($u['tipo']) ?>
                                                <?= !empty($u['plaza_nombre']) ? '· ' . htmlspecialchars($u['plaza_nombre']) : '' ?>)
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($esAti): ?>
                                    <small class="text-muted">Por defecto tú mismo; puedes cambiar a otro usuario.</small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>

                        <!-- CAMPO EN USO ─────────────────────────────────────────────── -->
                        <div class="col-md-6 campo-condicional" id="campo_tienda_uso">
                            <label class="form-label text-success fw-bold">
                                <i class="fas fa-store me-1"></i> Tienda en uso <span class="text-danger">*</span>
                            </label>
                            <select name="tienda_uso_id" id="select_tienda_uso" class="form-select"
                                    onchange="cargarReemplazos()">
                                <option value="">Seleccione tienda...</option>
                                <?php foreach ($tiendasPlaza as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- ¿REEMPLAZA A? -->
                        <div class="col-md-6 campo-condicional" id="campo_reemplazo">
                            <label class="form-label text-warning fw-bold">
                                <i class="fas fa-right-left me-1"></i> ¿Reemplaza a?
                            </label>
                            <select name="reemplaza_activo_id" id="select_reemplazo" class="form-select"
                                    onchange="manejarReemplazo()">
                                <option value="">— Ninguno (equipo adicional) —</option>
                            </select>
                            <small class="text-muted">Elige qué activo SALE de la tienda en su lugar.</small>
                        </div>

                        <!-- EQUIPO QUE SALE -->
                        <div class="col-12 campo-condicional" id="campo_salida">
                            <div class="border rounded p-3 bg-light">
                                <label class="form-label fw-bold mb-2">
                                    <i class="fas fa-box-open me-1"></i> Equipo que sale — destino
                                </label>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <select name="salida_destino" id="salida_destino" class="form-select"
                                                onchange="manejarSalida()">
                                            <option value="asignado" selected>Asignar a usuario</option>
                                            <option value="en_bodega">Enviar a bodega</option>
                                            <option value="garantia">Garantía</option>
                                            <option value="baja">Baja</option>
                                        </select>
                                    </div>
                                    <div class="col-md-5" id="salida_usuario_wrap">
                                        <select name="salida_usuario_id" id="salida_usuario_id" class="form-select"></select>
                                    </div>
                                    <div class="col-md-5 d-none" id="salida_ati_wrap">
                                        <select name="salida_ati_usuario_id" id="salida_ati_usuario_id" class="form-select">
                                            <option value="">ATI de la tienda (automático)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ATI RESPONSABLE (garantía / baja) -->
                        <div class="col-md-6 campo-condicional" id="campo_ati">
                            <label class="form-label fw-bold">
                                <i class="fas fa-user-shield me-1"></i> ATI responsable
                            </label>
                            <select name="ati_usuario_id" id="select_ati" class="form-select">
                                <option value="">ATI de la tienda (automático)</option>
                            </select>
                            <small class="text-muted">Garantía y baja quedan en el stock de este ATI.</small>
                        </div>

                        <!-- INFO BODEGA (fs/ati al elegir en_bodega) ───────────────── -->
                        <?php if (!$esAdmin && $bodegaDefault): ?>
                            <div class="col-12 campo-condicional" id="campo_bodega_info">
                                <div class="alert alert-secondary border-0 mb-0 py-2">
                                    <i class="fas fa-warehouse me-2"></i>
                                    El activo se guardará en la bodega
                                    <strong><?= htmlspecialchars($bodegaDefault['nombre']) ?></strong>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- STOCK DESTINO solo bodegas (admin, y coordinador con valor oculto OXXO) -->
                        <?php if ($esAdmin): ?>
                            <div class="col-md-6 campo-condicional" id="campo_bodega_destino">
                                <label class="form-label">Bodega destino</label>
                                <select name="stock_destino" id="select_bodega_destino" class="form-select">
                                    <option value="">Bodega de la plaza seleccionada (por defecto)</option>
                                    <?php $bodegaPreseleccionada = $bodegaPorNegocio ?? $bodegaOxxo; ?>
                                    <?php foreach ($bodegasPlaza as $b): ?>
                                        <option value="bodega_<?= $b['id'] ?>"
                                            <?= (!empty($bodegaPreseleccionada['id']) && $b['id'] == $bodegaPreseleccionada['id']) ? 'selected' : '' ?>>
                                            🏭 <?= htmlspecialchars($b['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Opcional — si no elige, va a la bodega de la plaza seleccionada.</small>
                            </div>
                        <?php endif; ?>

                        <!-- PROCEDENCIA (siempre visible, opcional) ─────────────────── -->
                        <div class="col-md-6">
                            <label class="form-label">
                                Procedencia <span class="text-muted fw-normal small">(Opcional)</span>
                            </label>
                            <select name="procedencia_tienda_id" class="form-select">
                                <option value="">¿De qué tienda proviene?</option>
                                <?php foreach ($tiendasPlaza as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary btn-lg" id="btnGuardar">
                            <i class="fas fa-save me-2"></i> Guardar Activo
                            <small class="ms-2 opacity-75">(Ctrl+Enter)</small>
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<script>
const ES_ADMIN = <?= $esAdmin ? 'true' : 'false' ?>;
const ES_COORD = <?= $esCoordinador ? 'true' : 'false' ?>;
const ES_FS    = <?= $esFs    ? 'true' : 'false' ?>;
const ES_ATI   = <?= $esAti   ? 'true' : 'false' ?>;
const ES_ADMIN_OR_COORD = ES_ADMIN || ES_COORD;
const USUARIO_ACTUAL = <?= $usuarioId ?>;
const BODEGA_OXXO = '<?= !empty($bodegaOxxo['id']) ? 'bodega_' . $bodegaOxxo['id'] : '' ?>';
// Bodega correspondiente a la unidad de negocio realmente seleccionada en el formulario
// (BARA, OXXO, o la que sea). Debe usarse SIEMPRE con prioridad sobre BODEGA_OXXO.
const BODEGA_NEGOCIO_SELECCIONADO = '<?= !empty($bodegaPorNegocio['id']) ? 'bodega_' . $bodegaPorNegocio['id'] : '' ?>';
const BODEGA_DEFAULT = BODEGA_NEGOCIO_SELECCIONADO || BODEGA_OXXO;
const PLAZA_ID       = <?= (int)$plazaId ?>;
const ACTIVO_ID      = 0;
const USUARIOS_PLAZA = <?= json_encode(array_map(fn($u) => ['id' => (int)$u['id'], 'nombre' => $u['nombre'], 'tipo' => $u['tipo']], $usuariosPlaza), JSON_UNESCAPED_UNICODE) ?>;

// ── Reemplazo: activos del mismo dispositivo ya en la tienda elegida ──
async function cargarReemplazos() {
    const est    = document.getElementById('estatus').value;
    const tienda = document.getElementById('select_tienda_uso');
    const disp   = document.getElementById('dispositivo');
    const cRe    = document.getElementById('campo_reemplazo');
    const sRe    = document.getElementById('select_reemplazo');
    if (!cRe || !sRe) return;
    if (est !== 'en_uso' || !tienda || !tienda.value || !disp || !disp.value) {
        cRe.style.display = 'none'; sRe.value = ''; manejarReemplazo(); return;
    }
    try {
        const r = await fetch(`index.php?controller=api&action=obtenerActivosEnTiendaPorDispositivo&tienda_id=${tienda.value}&dispositivo_id=${disp.value}&excepto_id=${ACTIVO_ID}`);
        const data = await r.json();
        sRe.innerHTML = '<option value="">— Ninguno (equipo adicional) —</option>';
        (data || []).forEach(a => sRe.appendChild(new Option(`${a.modelo_nombre || ''} · ${a.serie}`, a.id)));
        cRe.style.display = (data && data.length) ? 'block' : 'none';
    } catch (e) { cRe.style.display = 'none'; }
    manejarReemplazo();
}

function manejarReemplazo() {
    const sRe = document.getElementById('select_reemplazo');
    const cSal = document.getElementById('campo_salida');
    if (cSal) cSal.style.display = (sRe && sRe.value) ? 'block' : 'none';
    if (sRe && sRe.value) { poblarSalidaUsuarios(); manejarSalida(); }
}

function poblarSalidaUsuarios() {
    const sel = document.getElementById('salida_usuario_id');
    if (!sel || sel.dataset.listo) return;
    sel.innerHTML = '';
    if (ES_FS) {
        sel.appendChild(new Option('Yo mismo', USUARIO_ACTUAL));
    } else {
        USUARIOS_PLAZA.forEach(u => sel.appendChild(new Option(`${u.nombre} (${u.tipo.toUpperCase()})`, u.id)));
    }
    sel.value = USUARIO_ACTUAL;
    sel.dataset.listo = '1';
}

function manejarSalida() {
    const dest = document.getElementById('salida_destino').value;
    const uw = document.getElementById('salida_usuario_wrap');
    const aw = document.getElementById('salida_ati_wrap');
    if (uw) uw.classList.toggle('d-none', dest !== 'asignado');
    if (aw) aw.classList.toggle('d-none', dest !== 'garantia' && dest !== 'baja');
}

async function cargarAtis() {
    if (!PLAZA_ID) return;
    try {
        const r = await fetch(`index.php?controller=api&action=obtenerAtisPorPlaza&plaza_id=${PLAZA_ID}`);
        const data = await r.json();
        ['select_ati', 'salida_ati_usuario_id'].forEach(id => {
            const sel = document.getElementById(id);
            if (!sel) return;
            const primera = sel.options[0];
            sel.innerHTML = ''; sel.appendChild(primera);
            (data || []).forEach(u => sel.appendChild(new Option(u.nombre, u.id)));
        });
    } catch (e) { /* noop */ }
}

function cargarModelos() {
    const dispId = document.getElementById('dispositivo').value;
    const sel    = document.getElementById('modelo');
    sel.innerHTML = '<option value="">Cargando...</option>';
    sel.disabled  = true;
    if (!dispId) { sel.innerHTML = '<option value="">Primero seleccione dispositivo</option>'; return; }
    fetch('index.php?controller=api&action=obtenerModelosPorDispositivo&dispositivo_id=' + dispId)
        .then(r => r.json())
        .then(data => {
            sel.innerHTML = '<option value="">Seleccione modelo</option>';
            data.forEach(m => sel.appendChild(new Option(m.nombre, m.id)));
            sel.disabled = false;
        })
        .catch(() => { sel.innerHTML = '<option value="">Error al cargar</option>'; });
}

function manejarEstatus() {
    const est = document.getElementById('estatus').value;

    // Referencias a elementos
    const cAsig     = document.getElementById('campo_asignado');
    const cTienda   = document.getElementById('campo_tienda_uso');
    const cBodega   = document.getElementById('campo_bodega_info');
    const cStockDst = document.getElementById('campo_bodega_destino');
    const sTienda   = document.getElementById('select_tienda_uso');
    const sAsig     = document.getElementById('select_asignado');
    const sStockDst = document.getElementById('select_bodega_destino');

    const stockDestinoHidden = document.getElementById('stock_destino_hidden');

    const cAti    = document.getElementById('campo_ati');
    const cReempl = document.getElementById('campo_reemplazo');
    const cSalida = document.getElementById('campo_salida');

    // Reset: ocultar todo, quitar required, limpiar stock_destino cuando no sea bodega
    [cAsig, cTienda, cBodega, cStockDst, cAti, cReempl, cSalida].forEach(el => { if (el) el.style.display = 'none'; });
    if (sTienda)   sTienda.required = false;
    if (sAsig)     sAsig.required   = false;
    if (sStockDst) sStockDst.value  = '';
    if (stockDestinoHidden && !BODEGA_DEFAULT) stockDestinoHidden.value = '';

    if ((est === 'garantia' || est === 'baja') && cAti) cAti.style.display = 'block';
    if (est === 'en_uso') setTimeout(cargarReemplazos, 0);

    if (stockDestinoHidden && est !== 'en_bodega') {
        stockDestinoHidden.value = '';
    }

    if (stockDestinoHidden && est === 'en_bodega' && BODEGA_DEFAULT) {
        stockDestinoHidden.value = BODEGA_DEFAULT;
    }

    if (ES_ADMIN_OR_COORD) {
        // ── Admin / Coordinador ───────────────────────────────────────────
        if (est === 'asignado') {
            if (cAsig) cAsig.style.display = 'block';
            if (sAsig && !sAsig.value) sAsig.value = USUARIO_ACTUAL;
            if (sAsig) sAsig.required      = true;
        } else if (est === 'en_uso') {
            if (cTienda)   cTienda.style.display   = 'block';
            if (sTienda)   sTienda.required         = true;
            if (cStockDst) cStockDst.style.display  = 'block';
        } else if (est === 'en_bodega') {
            // No mostrar select de bodega destino cuando el estatus es 'en_bodega'
            if (stockDestinoHidden && BODEGA_DEFAULT) {
                stockDestinoHidden.value = BODEGA_DEFAULT;
            }
        }
        // garantia / baja: sin campos extra

    } else if (ES_FS) {
        // ── FS ────────────────────────────────────────────────────────────
        if (est === 'asignado') {
            if (cAsig) cAsig.style.display = 'block';
            if (sAsig && !sAsig.value) sAsig.value = USUARIO_ACTUAL;
            if (sAsig) sAsig.required = true;
        } else if (est === 'en_uso') {
            if (cAsig)   cAsig.style.display   = 'block'; // sigue en su stock
            if (cTienda) cTienda.style.display  = 'block';
            if (sTienda) sTienda.required        = true;
        } else if (est === 'en_bodega') {
            if (cBodega) cBodega.style.display = 'block';
            if (stockDestinoHidden && BODEGA_DEFAULT) {
                stockDestinoHidden.value = BODEGA_DEFAULT;
            }
        }
        // garantia: sin campos extra, va a su stock personal

    } else if (ES_ATI) {
        // ── ATI ───────────────────────────────────────────────────────────
        if (est === 'asignado') {
            if (cAsig) cAsig.style.display = 'block';
            if (sAsig) sAsig.required      = true;
        } else if (est === 'en_uso') {
            if (cAsig)   cAsig.style.display   = 'block'; // sigue en su stock
            if (cTienda) cTienda.style.display  = 'block';
            if (sTienda) sTienda.required        = true;
        } else if (est === 'en_bodega') {
            if (cBodega) cBodega.style.display = 'block';
            if (stockDestinoHidden && BODEGA_DEFAULT) {
                stockDestinoHidden.value = BODEGA_DEFAULT;
            }
        }
        // garantia / baja: sin campos extra
    }
}

document.addEventListener('keydown', e => {
    if (e.ctrlKey && e.key === 'Enter') document.getElementById('btnGuardar').click();
});

document.addEventListener('DOMContentLoaded', () => {
    cargarAtis();
    manejarEstatus();
    const disp = document.getElementById('dispositivo');
    if (disp) disp.addEventListener('change', () => setTimeout(cargarReemplazos, 300));
    const serie = document.getElementById('campo_serie');
    if (document.querySelector('.alert-success')) {
        if (serie) { serie.value = ''; serie.focus(); }
    } else {
        if (serie) serie.focus();
    }
});

// ── Envío por AJAX: no recarga la página, conserva negocio/plaza/dispositivo/
//    modelo/estatus/asignación tal cual quedaron, y solo limpia serie, código
//    y procedencia para registrar el siguiente activo más rápido. ──────────
(function () {
    const form = document.getElementById('formActivo');
    if (!form) return;

    function mostrarAlerta(exito, mensaje) {
        const cont = document.getElementById('alertContainer');
        if (!cont) return;

        const div = document.createElement('div');
        div.className = 'alert ' + (exito ? 'alert-success' : 'alert-danger') + ' alert-dismissible fade show';

        const icono = document.createElement('i');
        icono.className = 'fas ' + (exito ? 'fa-check-circle' : 'fa-exclamation-triangle') + ' me-2';
        div.appendChild(icono);
        div.appendChild(document.createTextNode(mensaje || (exito ? 'Guardado correctamente.' : 'Ocurrió un error.')));

        const btnCerrar = document.createElement('button');
        btnCerrar.type = 'button';
        btnCerrar.className = 'btn-close';
        btnCerrar.setAttribute('data-bs-dismiss', 'alert');
        div.appendChild(btnCerrar);

        cont.prepend(div);
        setTimeout(() => {
            div.classList.remove('show');
            setTimeout(() => div.remove(), 300);
        }, 5000);
    }

    function limpiarCamposDeInstancia() {
        // Solo estos 3 se limpian entre un activo y el siguiente:
        const serie = document.getElementById('campo_serie');
        if (serie) serie.value = '';

        const codBarras = form.querySelector('[name="codigo_barras"]');
        if (codBarras) codBarras.value = '';

        const procedencia = form.querySelector('[name="procedencia_tienda_id"]');
        if (procedencia) procedencia.value = '';

        if (serie) serie.focus();
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        const btn = document.getElementById('btnGuardar');
        const textoOriginal = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = 'Guardando...';
        }

        try {
            const formData = new FormData(form);
            const resp = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            let data;
            try {
                data = await resp.json();
            } catch (parseErr) {
                mostrarAlerta(false, 'Respuesta inesperada del servidor.');
                return;
            }

            mostrarAlerta(!!data.success, data.message || '');

            if (data.success) {
                limpiarCamposDeInstancia();
            }
        } catch (err) {
            mostrarAlerta(false, 'Error de conexión. Verifica tu internet e intenta de nuevo.');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = textoOriginal;
            }
        }
    });
})();

</script>
</body>
</html>