<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario 123 - Editar Activo</title>
    <?php include ROOT_PATH . '/app/views/components/favicon.php'; ?>
    <style>
        body { background-color: #f8f9fa; }
        .card { border-radius: 15px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,.1); }
        .form-label { font-weight: 600; color: #495057; }
        .section-title { color: #0d6efd; border-bottom: 2px solid #e9ecef; padding-bottom: 8px; margin-bottom: 20px; margin-top: 30px; }
        .campo-condicional { display: none; }
        .usuario-fijo { background:#f1f3f5; border:1px solid #dee2e6; border-radius:8px; padding:10px 14px; display:flex; align-items:center; gap:10px; }
        .info-readonly { background:#f8f9fa; border:1px solid #dee2e6; border-radius:8px; padding:8px 14px; font-size:.85rem; color:#6c757d; }
    </style>
</head>
<body>
<?php include ROOT_PATH . '/app/views/components/navbar.php'; ?>

<?php
$tipo      = $_SESSION['usuario']['tipo']     ?? '';
$plazaId   = (int)($_SESSION['usuario']['plaza_id'] ?? 0);
$usuarioId = (int)($_SESSION['usuario']['id']       ?? 0);

$esAdmin = in_array($tipo, ['admin', 'coordinador']);
$esFs    = $tipo === 'fs';
$esAti   = $tipo === 'ati';

// Status actual del activo
$statusActual  = $activo['status']                ?? 'en_bodega';
$stockTipo     = $activo['stock_tipo']            ?? 'bodega';
$tiendaUsoId   = $activo['tienda_uso_id']         ?? null;
$procedenciaId = $activo['procedencia_tienda_id'] ?? null;
$stockId       = $activo['stock_id']              ?? null;

// Bodega de la plaza del usuario (vía bodega_acceso_plaza)
$bodegaDefault = null;
foreach ($bodegas as $b) {
    $idsAcceso = array_map('intval', array_filter(explode(',', $b['plazas_ids'] ?? '')));
    if (in_array($plazaId, $idsAcceso, true)) { $bodegaDefault = $b; break; }
}

// Tiendas de la plaza del usuario
$tiendasPlaza = array_values(array_filter($tiendas, fn($t) => (int)$t['plaza_id'] === $plazaId));
if (empty($tiendasPlaza)) $tiendasPlaza = array_values($tiendas);

// Usuarios de la plaza (para ATI/admin)
$usuariosPlaza = array_values(array_filter($usuarios, fn($u) =>
    (int)($u['plaza_id'] ?? 0) === $plazaId &&
    in_array($u['tipo'], ['fs', 'ati', 'coordinador'])
));

// Los admin no están atados a una plaza específica (acceso global), por
// eso no caen en el filtro de arriba, pero deben poder ser elegidos igual.
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

// Usuario al que está asignado actualmente (si aplica)
$usuarioAsignadoId = null;
if ($stockTipo === 'usuario') {
    // Buscar via usuario_stock_id que devuelve el modelo
    $usuarioAsignadoId = (int)($activo['usuario_stock_id'] ?? 0) ?: null;
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
                        <h2 class="mb-0">Editar Activo</h2>
                        <small class="text-muted">
                            ID <strong>#<?= str_pad($activo['id'], 4, '0', STR_PAD_LEFT) ?></strong>
                            &nbsp;·&nbsp; Serie: <strong><?= htmlspecialchars($activo['serie'] ?? '—') ?></strong>
                        </small>
                    </div>
                </div>

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

                <form action="index.php?action=actualizar" method="POST" id="formEditar">
                    <input type="hidden" name="id"       value="<?= (int)$activo['id'] ?>">
                    <input type="hidden" name="stock_id" id="stock_id_final" value="<?= (int)$stockId ?>">

                    <!-- ── Dispositivo y Modelo ───────────────────────────── -->
                    <h5 class="section-title"><i class="fas fa-laptop me-2"></i> Dispositivo</h5>
                    <div class="row g-3">

                        <div class="col-md-4">
                            <label class="form-label">Dispositivo <span class="text-danger">*</span></label>
                            <select name="dispositivo_id" id="dispositivo" class="form-select" required
                                    onchange="cargarModelos()">
                                <option value="">Seleccione...</option>
                                <?php foreach ($dispositivos as $d): ?>
                                    <option value="<?= $d['id'] ?>"
                                        <?= (int)$d['id'] === (int)($activo['dispositivo_id'] ?? 0) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($d['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Modelo <span class="text-danger">*</span></label>
                            <select name="modelo_id" id="modelo" class="form-select" required>
                                <option value="<?= (int)$activo['modelo_id'] ?>">
                                    <?= htmlspecialchars($activo['modelo_nombre'] ?? 'Cargando...') ?>
                                </option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Estatus <span class="text-danger">*</span></label>
                            <select name="status" id="estatus" class="form-select" required
                                    onchange="manejarEstatus()">

                                <?php if ($esAdmin): ?>
                                    <option value="en_bodega" <?= $statusActual === 'en_bodega' ? 'selected' : '' ?>>En Bodega</option>
                                    <option value="en_uso"  <?= $statusActual === 'en_uso'  ? 'selected' : '' ?>>En Uso</option>
                                    <option value="asignado"  <?= $statusActual === 'asignado'  ? 'selected' : '' ?>>Asignado</option>
                                    <option value="garantia"  <?= $statusActual === 'garantia'  ? 'selected' : '' ?>>Garantía</option>
                                    <option value="baja"      <?= $statusActual === 'baja'      ? 'selected' : '' ?>>Baja</option>

                                <?php elseif ($esFs): ?>
                                    <option value="asignado"  <?= $statusActual === 'asignado'  ? 'selected' : '' ?>>Asignado (a mí)</option>
                                    <option value="en_uso"  <?= $statusActual === 'en_uso'  ? 'selected' : '' ?>>En Uso</option>
                                    <option value="en_bodega" <?= $statusActual === 'en_bodega' ? 'selected' : '' ?>>En Bodega</option>
                                    <option value="garantia"  <?= $statusActual === 'garantia'  ? 'selected' : '' ?>>Garantía</option>

                                <?php elseif ($esAti): ?>
                                    <option value="asignado"  <?= $statusActual === 'asignado'  ? 'selected' : '' ?>>Asignado</option>
                                    <option value="en_uso"  <?= $statusActual === 'en_uso'  ? 'selected' : '' ?>>En Uso</option>
                                    <option value="en_bodega" <?= $statusActual === 'en_bodega' ? 'selected' : '' ?>>En Bodega</option>
                                    <option value="garantia"  <?= $statusActual === 'garantia'  ? 'selected' : '' ?>>Garantía</option>
                                    <option value="baja"      <?= $statusActual === 'baja'      ? 'selected' : '' ?>>Baja</option>
                                <?php endif; ?>

                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-primary">Serie <span class="text-danger">*</span></label>
                            <input type="text" name="serie" class="form-control border-primary" required
                                   value="<?= htmlspecialchars($activo['serie'] ?? '') ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Código de barras</label>
                            <input type="text" name="codigo_barras" class="form-control"
                                   value="<?= htmlspecialchars($activo['codigo_barras'] ?? '') ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">N° de activo</label>
                            <input type="text" name="num_activo" class="form-control"
                                   value="<?= htmlspecialchars($activo['num_activo'] ?? '') ?>">
                        </div>

                    </div>

                    <!-- ── Asignación / Ubicación ─────────────────────────── -->
                    <h5 class="section-title"><i class="fas fa-map-marker-alt me-2"></i> Asignación</h5>
                    <div class="row g-3">

                        <!-- Ubicación actual (informativo) -->
                        <div class="col-12">
                            <div class="info-readonly d-flex flex-wrap gap-3">
                                <span>
                                    <?= $stockTipo === 'bodega' ? '🏭 Bodega:' : '👤 Técnico:' ?>
                                    <strong>
                                        <?= htmlspecialchars($stockTipo === 'bodega'
                                            ? ($activo['bodega_nombre']  ?? '—')
                                            : ($activo['usuario_nombre'] ?? '—')) ?>
                                    </strong>
                                </span>
                                <?php if (!empty($activo['plaza_nombre'])): ?>
                                    <span>📍 Plaza: <strong><?= htmlspecialchars($activo['plaza_nombre']) ?></strong></span>
                                <?php endif; ?>
                                <?php if (!empty($activo['tienda_uso_nombre'])): ?>
                                    <span>🏪 En uso: <strong><?= htmlspecialchars($activo['tienda_uso_nombre']) ?></strong></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- CAMPO ASIGNADO ──────────────────────────────── -->
                        <div class="col-md-6 campo-condicional" id="campo_asignado">
                            <label class="form-label text-primary fw-bold">
                                <i class="fas fa-user me-1"></i> Asignado a <span class="text-danger">*</span>
                            </label>

                            <?php if ($esFs): ?>
                                <input type="hidden" name="asignado_usuario_id" value="<?= $usuarioId ?>">
                                <div class="usuario-fijo">
                                    <i class="fas fa-user-circle text-primary fa-lg"></i>
                                    <div>
                                        <div class="fw-bold"><?= htmlspecialchars($usuarioActual['nombre'] ?? '—') ?></div>
                                        <small class="text-muted text-uppercase">FS · Stock personal</small>
                                    </div>
                                </div>

                            <?php elseif ($esAti): ?>
                                <select name="asignado_usuario_id" id="select_asignado" class="form-select">
                                    <option value="">Seleccione usuario...</option>
                                    <?php foreach ($usuariosPlaza as $u): ?>
                                        <option value="<?= $u['id'] ?>"
                                            <?= (int)$u['id'] === ($usuarioAsignadoId ?? $usuarioId) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($u['nombre']) ?> (<?= strtoupper($u['tipo']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                            <?php else: ?>
                                <select name="asignado_usuario_id" id="select_asignado" class="form-select">
                                    <option value="">Seleccione usuario...</option>
                                    <?php foreach ($usuarios as $u): ?>
                                        <?php if (in_array($u['tipo'], ['admin', 'fs', 'ati', 'coordinador'])): ?>
                                            <option value="<?= $u['id'] ?>"
                                                <?= (int)$u['id'] === ($usuarioAsignadoId ?? 0) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($u['nombre']) ?>
                                                (<?= strtoupper($u['tipo']) ?>
                                                <?= !empty($u['plaza_nombre']) ? '· ' . htmlspecialchars($u['plaza_nombre']) : '' ?>)
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>

                        <!-- CAMPO EN USO ─────────────────────────────────── -->
                        <div class="col-md-6 campo-condicional" id="campo_tienda_uso">
                            <label class="form-label text-success fw-bold">
                                <i class="fas fa-store me-1"></i> Tienda en uso <span class="text-danger">*</span>
                            </label>
                            <select name="tienda_uso_id" id="select_tienda_uso" class="form-select"
                                    onchange="cargarReemplazos()">
                                <option value="">Seleccione tienda...</option>
                                <?php foreach ($tiendasPlaza as $t): ?>
                                    <option value="<?= $t['id'] ?>"
                                        <?= (int)$t['id'] === (int)$tiendaUsoId ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($t['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- ¿REEMPLAZA A? (si la tienda ya tiene un activo del mismo dispositivo) -->
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

                        <!-- EQUIPO QUE SALE (destino del reemplazado) -->
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

                        <!-- ATI RESPONSABLE (estatus garantía / baja) -->
                        <div class="col-md-6 campo-condicional" id="campo_ati">
                            <label class="form-label fw-bold">
                                <i class="fas fa-user-shield me-1"></i> ATI responsable
                            </label>
                            <select name="ati_usuario_id" id="select_ati" class="form-select">
                                <option value="">ATI de la tienda (automático)</option>
                            </select>
                            <small class="text-muted">Garantía y baja quedan en el stock de este ATI.</small>
                        </div>

                        <!-- INFO BODEGA (fs/ati al elegir en_bodega) ──────── -->
                        <?php if (!$esAdmin && $bodegaDefault): ?>
                            <div class="col-12 campo-condicional" id="campo_bodega_info">
                                <div class="alert alert-secondary border-0 mb-0 py-2">
                                    <i class="fas fa-warehouse me-2"></i>
                                    El activo se moverá a la bodega
                                    <strong><?= htmlspecialchars($bodegaDefault['nombre']) ?></strong>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- REASIGNACIÓN DE STOCK (solo admin/coordinador) ── -->
                        <?php if ($esAdmin): ?>
                            <div class="col-md-6 campo-condicional" id="campo_bodega_destino">
                                <label class="form-label">Reasignar stock a</label>
                                <select name="stock_destino" id="select_bodega_destino" class="form-select"
                                        onchange="resolverStockId()">
                                    <option value="">Sin cambios (mantener stock actual)</option>
                                    <?php foreach ($bodegas as $b): ?>
                                        <option value="bodega_<?= $b['id'] ?>"
                                            <?= ($stockTipo === 'bodega' && (int)$b['id'] === (int)($activo['bodega_id'] ?? 0)) ? 'selected' : '' ?>>
                                            🏭 Bodega: <?= htmlspecialchars($b['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <?php foreach ($usuarios as $u): ?>
                                        <?php if (in_array($u['tipo'], ['fs', 'ati', 'coordinador'])): ?>
                                            <option value="usuario_<?= $u['id'] ?>"
                                                <?= ($stockTipo === 'usuario' && (int)$u['id'] === ($usuarioAsignadoId ?? 0)) ? 'selected' : '' ?>>
                                                👤 <?= htmlspecialchars($u['nombre']) ?> (<?= strtoupper($u['tipo']) ?>)
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Solo si necesita mover el activo a otro stock.</small>
                            </div>
                        <?php endif; ?>

                        <!-- PROCEDENCIA (siempre visible, opcional) ──────── -->
                        <div class="col-md-6">
                            <label class="form-label">
                                Procedencia <span class="text-muted fw-normal small">(Opcional)</span>
                            </label>
                            <select name="procedencia_tienda_id" class="form-select">
                                <option value="">¿De qué tienda proviene?</option>
                                <?php foreach ($tiendas as $t): ?>
                                    <option value="<?= $t['id'] ?>"
                                        <?= (int)$t['id'] === (int)$procedenciaId ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($t['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                    </div>

                    <!-- ── Metadatos ──────────────────────────────────────── -->
                    <h5 class="section-title"><i class="fas fa-info-circle me-2"></i> Registro</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Fecha de Alta</label>
                            <input type="text" class="form-control bg-light"
                                   value="<?= htmlspecialchars($activo['fecha_alta'] ?? '—') ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Última Modificación</label>
                            <input type="text" class="form-control bg-light"
                                   value="<?= htmlspecialchars($activo['fecha_modificacion'] ?? '—') ?>" readonly>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary flex-grow-1" id="btnGuardar">
                            <i class="fas fa-save me-2"></i> Guardar Cambios
                            <small class="ms-2 opacity-75">(Ctrl+Enter)</small>
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<script>
const ES_ADMIN       = <?= $esAdmin ? 'true' : 'false' ?>;
const ES_FS          = <?= $esFs    ? 'true' : 'false' ?>;
const ES_ATI         = <?= $esAti   ? 'true' : 'false' ?>;
const MODELO_ACTUAL  = <?= (int)($activo['modelo_id']    ?? 0) ?>;
const STATUS_ACTUAL  = '<?= $statusActual ?>';
const PLAZA_ID       = <?= (int)$plazaId ?>;
const ACTIVO_ID      = <?= (int)$activo['id'] ?>;
const USUARIO_ACTUAL = <?= (int)$usuarioId ?>;
const USUARIOS_PLAZA = <?= json_encode(array_map(fn($u) => ['id' => (int)$u['id'], 'nombre' => $u['nombre'], 'tipo' => $u['tipo']], $usuariosPlaza), JSON_UNESCAPED_UNICODE) ?>;

// ── Selector "¿Reemplaza a?": activos del mismo dispositivo ya en esa tienda ──
async function cargarReemplazos() {
    const est    = document.getElementById('estatus').value;
    const tienda = document.getElementById('select_tienda_uso');
    const disp   = document.getElementById('dispositivo');
    const cRe    = document.getElementById('campo_reemplazo');
    const sRe    = document.getElementById('select_reemplazo');
    if (!cRe || !sRe) return;

    if (est !== 'en_uso' || !tienda || !tienda.value || !disp || !disp.value) {
        cRe.style.display = 'none';
        sRe.value = '';
        manejarReemplazo();
        return;
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
        sel.value = USUARIO_ACTUAL;
    } else {
        USUARIOS_PLAZA.forEach(u => sel.appendChild(new Option(`${u.nombre} (${u.tipo.toUpperCase()})`, u.id)));
        sel.value = USUARIO_ACTUAL;
    }
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
            sel.innerHTML = '';
            sel.appendChild(primera);
            (data || []).forEach(u => sel.appendChild(new Option(u.nombre, u.id)));
        });
    } catch (e) { /* noop */ }
}

// ── Cargar modelos pre-seleccionando el actual ─────────────────────────────
function cargarModelos(preselect = null) {
    const dispId = document.getElementById('dispositivo').value;
    const sel    = document.getElementById('modelo');
    if (!dispId) { sel.innerHTML = '<option value="">Primero seleccione dispositivo</option>'; sel.disabled = true; return; }
    sel.innerHTML = '<option value="">Cargando...</option>';
    sel.disabled  = true;
    fetch('index.php?controller=api&action=obtenerModelosPorDispositivo&dispositivo_id=' + dispId)
        .then(r => r.json())
        .then(data => {
            sel.innerHTML = '<option value="">Seleccione modelo</option>';
            data.forEach(m => {
                const opt    = new Option(m.nombre, m.id);
                opt.selected = parseInt(m.id) === parseInt(preselect ?? MODELO_ACTUAL);
                sel.appendChild(opt);
            });
            sel.disabled = false;
        })
        .catch(() => { sel.innerHTML = '<option value="">Error al cargar</option>'; });
}

// ── Manejar campos condicionales (idéntica lógica que crear.php) ───────────
function manejarEstatus() {
    const est = document.getElementById('estatus').value;

    const cAsig     = document.getElementById('campo_asignado');
    const cTienda   = document.getElementById('campo_tienda_uso');
    const cBodega   = document.getElementById('campo_bodega_info');
    const cStockDst = document.getElementById('campo_bodega_destino');
    const cAti      = document.getElementById('campo_ati');
    const cReempl   = document.getElementById('campo_reemplazo');
    const cSalida   = document.getElementById('campo_salida');
    const sTienda   = document.getElementById('select_tienda_uso');
    const sAsig     = document.getElementById('select_asignado');
    const sStockDst = document.getElementById('select_bodega_destino');

    [cAsig, cTienda, cBodega, cStockDst, cAti, cReempl, cSalida].forEach(el => { if (el) el.style.display = 'none'; });
    if (sTienda)   sTienda.required = false;
    if (sAsig)     sAsig.required   = false;
    if (sStockDst) sStockDst.value  = '';

    // Garantía / baja → elegir ATI responsable de la tienda
    if ((est === 'garantia' || est === 'baja') && cAti) cAti.style.display = 'block';
    // En uso → ofrecer el selector de reemplazo (según la tienda/dispositivo)
    if (est === 'en_uso') setTimeout(cargarReemplazos, 0);

    if (ES_ADMIN) {
        if (est === 'asignado') {
            if (cAsig) cAsig.style.display = 'block';
            if (sAsig) sAsig.required      = true;
        } else if (est === 'en_uso') {
            if (cTienda)   cTienda.style.display   = 'block';
            if (sTienda)   sTienda.required         = true;
            if (cStockDst) cStockDst.style.display  = 'block';
        } else if (est === 'en_bodega') {
            // No mostrar select de bodega destino cuando el estatus es 'en_bodega'
            if (cBodega) cBodega.style.display = 'block';
        }
    } else if (ES_FS) {
        if (est === 'asignado') {
            if (cAsig) cAsig.style.display = 'block';
        } else if (est === 'en_uso') {
            if (cAsig)   cAsig.style.display   = 'block';
            if (cTienda) cTienda.style.display  = 'block';
            if (sTienda) sTienda.required        = true;
        } else if (est === 'en_bodega') {
            if (cBodega) cBodega.style.display = 'block';
        }
    } else if (ES_ATI) {
        if (est === 'asignado') {
            if (cAsig) cAsig.style.display = 'block';
            if (sAsig) sAsig.required      = true;
        } else if (est === 'en_uso') {
            if (cAsig)   cAsig.style.display   = 'block';
            if (cTienda) cTienda.style.display  = 'block';
            if (sTienda) sTienda.required        = true;
        } else if (est === 'en_bodega') {
            if (cBodega) cBodega.style.display = 'block';
        }
    }
}

// ── Resolver stock_id si admin cambia el destino ───────────────────────────
async function resolverStockId() {
    const sel = document.getElementById('select_bodega_destino');
    if (!sel || !sel.value) return;
    const [tipoDest, idDest] = sel.value.split('_');
    const endpoint = tipoDest === 'bodega'
        ? `index.php?controller=api&action=obtenerStockPorBodega&bodega_id=${idDest}`
        : `index.php?controller=api&action=obtenerStockPorUsuario&usuario_id=${idDest}`;
    try {
        const resp  = await fetch(endpoint);
        const stock = await resp.json();
        if (stock && stock.id) document.getElementById('stock_id_final').value = stock.id;
    } catch(e) { console.error('Error resolviendo stock:', e); }
}

document.addEventListener('keydown', e => {
    if (e.ctrlKey && e.key === 'Enter') document.getElementById('btnGuardar').click();
});

document.addEventListener('DOMContentLoaded', () => {
    // Cargar modelos del dispositivo actual
    cargarModelos(MODELO_ACTUAL);
    // ATIs de la plaza para los selectores de responsable
    cargarAtis();
    // Mostrar campos según el status actual del activo
    manejarEstatus();
    // Recalcular el selector de reemplazo si cambia el dispositivo
    const disp = document.getElementById('dispositivo');
    if (disp) disp.addEventListener('change', () => setTimeout(cargarReemplazos, 300));
});
</script>
</body>
</html>