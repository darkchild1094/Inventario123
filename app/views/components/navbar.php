<?php if (!defined('BOOTSTRAP_LOADED')): ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php define('BOOTSTRAP_LOADED', true); endif; ?>

<?php
$tipo      = $_SESSION['usuario']['tipo'] ?? $_SESSION['usuario_tipo'] ?? '';
$vistaAc   = $_GET['vista']      ?? '';
$ctrl      = $_GET['controller'] ?? '';
$act       = $_GET['action']     ?? 'index';
$navActivo = $navActivo ?? '';
$esDash    = $navActivo === 'dashboard' || ($ctrl === '' && $vistaAc === '' && $act === 'index');

// Traslados pendientes (badge en la barra lateral).
$pendTraslados = 0;
if (in_array($tipo, ['coordinador', 'admin'], true)) {
    global $db;
    if (isset($db) && $db instanceof \PDO) {
        try {
            $stm = new \App\Models\SolicitudTraslado($db);
            $pendTraslados = $tipo === 'admin'
                ? $stm->contarPendientesTodas()
                : $stm->contarPendientesPorPlazas(\App\Helpers\Permisos::misPlazas());
        } catch (\Throwable $e) { $pendTraslados = 0; }
    }
}

$nombreUsuario = $_SESSION['usuario']['nombre'] ?? 'Usuario';
$fotoUsuario   = $_SESSION['usuario']['foto']   ?? null;
$plazaUsuario  = $_SESSION['usuario']['plaza_nombre'] ?? '';
$idUsuario     = $_SESSION['usuario']['id'] ?? '';
$badgeColor = match ($tipo) {
    'admin' => 'danger', 'coordinador' => 'warning text-dark',
    'fs' => 'primary', 'ati' => 'info text-dark', default => 'secondary',
};
?>

<style>
    :root { --sb-w: 244px; }
    body { padding-left: var(--sb-w); }

    .app-sidebar {
        position: fixed; inset: 0 auto 0 0; width: var(--sb-w);
        background: #1e2227; color: rgba(255,255,255,.8);
        display: flex; flex-direction: column; z-index: 1040;
        box-shadow: 2px 0 12px rgba(0,0,0,.12);
    }
    .app-brand {
        display: flex; align-items: center; gap: .6rem;
        padding: 1rem 1.15rem; border-bottom: 1px solid rgba(255,255,255,.08);
        color: #fff; text-decoration: none; font-weight: 700;
    }
    .app-brand img { height: 32px; width: auto; }
    .app-nav { flex: 1 1 auto; overflow-y: auto; padding: .5rem 0; }
    .app-nav a {
        display: flex; align-items: center; gap: .75rem;
        padding: .62rem 1.15rem; color: rgba(255,255,255,.78);
        text-decoration: none; font-size: .9rem; line-height: 1.2;
        border-left: 3px solid transparent; transition: background .15s, color .15s;
    }
    .app-nav a i { width: 18px; text-align: center; opacity: .85; font-size: .95rem; }
    .app-nav a:hover { background: rgba(255,255,255,.06); color: #fff; }
    .app-nav a.active {
        background: rgba(13,110,253,.16); color: #fff;
        border-left-color: #0d6efd; font-weight: 600;
    }
    .app-nav .badge { margin-left: auto; }
    .app-nav .app-sep {
        margin: .5rem 1.15rem; border-top: 1px solid rgba(255,255,255,.08);
        font-size: .68rem; text-transform: uppercase; letter-spacing: .06em;
        color: rgba(255,255,255,.35); padding-top: .5rem;
    }
    .app-user { border-top: 1px solid rgba(255,255,255,.08); padding: .85rem 1.15rem; }
    .app-user .row1 { display: flex; align-items: center; gap: .6rem; }
    .app-user .avatar {
        width: 38px; height: 38px; border-radius: 50%; flex: 0 0 38px;
        background: linear-gradient(135deg,#667eea,#764ba2); color: #fff;
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; object-fit: cover;
    }
    .app-user .nm { color: #fff; font-size: .86rem; font-weight: 600; line-height: 1.15; }
    .app-user .links { margin-top: .5rem; display: flex; flex-direction: column; gap: .15rem; }
    .app-user .links a { color: rgba(255,255,255,.7); font-size: .82rem; text-decoration: none; padding: .2rem 0; }
    .app-user .links a:hover { color: #fff; }

    .app-topbar {
        position: fixed; top: 0; left: 0; right: 0; height: 3.25rem;
        background: #1e2227; color: #fff; z-index: 1030;
        display: flex; align-items: center; gap: .75rem; padding: 0 1rem;
    }
    .app-topbar img { height: 26px; }
    .app-burger { background: none; border: 0; color: #fff; font-size: 1.35rem; line-height: 1; }
    .app-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 1035; display: none; }
    .app-backdrop.show { display: block; }

    @media (max-width: 991.98px) {
        body { padding-left: 0; padding-top: 3.25rem; }
        .app-sidebar { transform: translateX(-100%); transition: transform .22s ease; }
        .app-sidebar.abierta { transform: none; }
    }
    @media (min-width: 992px) {
        .app-topbar, .app-backdrop { display: none !important; }
    }
</style>

<header class="app-topbar">
    <button class="app-burger" type="button" data-app-burger aria-label="Menú"><i class="fas fa-bars"></i></button>
    <img src="assets/img/logo_texto_white_520x120.png" alt="Inventario 123"
         onerror="this.replaceWith(document.createTextNode('Inventario 123'))">
</header>
<div class="app-backdrop" id="appBackdrop"></div>

<aside class="app-sidebar" id="appSidebar">
    <a class="app-brand" href="index.php?controller=dashboard">
        <img src="assets/img/logo_texto_white_520x120.png" alt="Inventario 123"
             onerror="this.replaceWith(document.createTextNode('Inventario 123'))">
    </a>

    <nav class="app-nav">
        <a class="<?= $esDash ? 'active' : '' ?>" href="index.php?controller=dashboard">
            <i class="fas fa-gauge-high"></i> Inicio
        </a>

        <?php if (in_array($tipo, ['admin', 'coordinador', 'ati'], true)): ?>
            <a class="<?= $vistaAc === 'bodega' ? 'active' : '' ?>" href="index.php?vista=bodega">
                <i class="fas fa-warehouse"></i> Bodega
            </a>
        <?php endif; ?>

        <?php if (in_array($tipo, ['fs', 'ati'], true)): ?>
            <a class="<?= $vistaAc === 'mi_stock' ? 'active' : '' ?>" href="index.php?vista=mi_stock">
                <i class="fas fa-toolbox"></i> Mi Stock
            </a>
        <?php endif; ?>

        <?php if (in_array($tipo, ['admin', 'coordinador'], true)): ?>
            <a class="<?= $vistaAc === 'todos' ? 'active' : '' ?>" href="index.php?vista=todos">
                <i class="fas fa-list"></i> Todos
            </a>
        <?php endif; ?>

        <?php if (in_array($tipo, ['admin', 'coordinador', 'fs', 'ati'], true)): ?>
            <a class="<?= $act === 'crear' && $ctrl === '' ? 'active' : '' ?>" href="index.php?action=crear">
                <i class="fas fa-plus-circle"></i> Nuevo activo
            </a>
        <?php endif; ?>

        <?php if (in_array($tipo, ['admin', 'coordinador', 'fs', 'ati'], true)): ?>
            <a class="<?= $ctrl === 'historial' ? 'active' : '' ?>" href="index.php?controller=historial&action=index">
                <i class="fas fa-clock-rotate-left"></i> Historial
            </a>
        <?php endif; ?>

        <?php if (in_array($tipo, ['fs', 'coordinador', 'admin'], true)): ?>
            <a class="<?= $ctrl === 'solicitud' ? 'active' : '' ?>" href="index.php?controller=solicitud&action=index">
                <i class="fas fa-right-left"></i> Traslados
                <?php if ($pendTraslados > 0): ?>
                    <span class="badge rounded-pill bg-warning text-dark"><?= (int) $pendTraslados ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if (in_array($tipo, ['admin', 'coordinador', 'fs', 'ati'], true)): ?>
            <a href="index.php?controller=export&action=inventario">
                <i class="fas fa-file-excel text-success"></i> Exportar
            </a>
        <?php endif; ?>

        <?php if ($tipo === 'admin'): ?>
            <div class="app-sep">Administración</div>
            <a class="<?= $ctrl === 'tienda' ? 'active' : '' ?>" href="index.php?controller=tienda&action=index">
                <i class="fas fa-store"></i> Tiendas
            </a>
            <a class="<?= $ctrl === 'modelo' ? 'active' : '' ?>" href="index.php?controller=modelo&action=index">
                <i class="fas fa-tags"></i> Catálogo
            </a>
            <a class="<?= $ctrl === 'usuario' && $act !== 'perfil' ? 'active' : '' ?>" href="index.php?controller=usuario&action=index">
                <i class="fas fa-users-cog"></i> Usuarios
            </a>
        <?php endif; ?>
    </nav>

    <div class="app-user">
        <div class="row1">
            <?php if (!empty($fotoUsuario)): ?>
                <img class="avatar" src="uploads/usuarios/<?= htmlspecialchars($fotoUsuario) ?>"
                     onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'avatar',textContent:'<?= strtoupper(substr($nombreUsuario, 0, 1)) ?>'}))">
            <?php else: ?>
                <div class="avatar"><?= strtoupper(substr($nombreUsuario, 0, 1)) ?></div>
            <?php endif; ?>
            <div class="flex-grow-1" style="min-width:0">
                <div class="nm text-truncate"><?= htmlspecialchars($nombreUsuario) ?></div>
                <span class="badge bg-<?= $badgeColor ?> text-uppercase" style="font-size:.6rem;"><?= htmlspecialchars($tipo ?: '—') ?></span>
                <?php if ($plazaUsuario !== ''): ?>
                    <span class="text-white-50" style="font-size:.7rem;"><?= htmlspecialchars($plazaUsuario) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="links">
            <a href="index.php?controller=usuario&action=perfil&id=<?= $idUsuario ?>"><i class="fas fa-user-edit me-1"></i> Mi perfil</a>
            <a href="index.php?controller=auth&action=logout" class="text-danger"><i class="fas fa-sign-out-alt me-1"></i> Cerrar sesión</a>
        </div>
    </div>
</aside>

<?php foreach (['success' => 'check-circle', 'error' => 'exclamation-circle'] as $key => $icon): ?>
    <?php if (isset($_SESSION[$key])): ?>
        <div class="container pt-3">
            <div class="alert alert-<?= $key === 'error' ? 'danger' : 'success' ?> alert-dismissible fade show shadow-sm">
                <i class="fas fa-<?= $icon ?> me-2"></i>
                <?= htmlspecialchars($_SESSION[$key]) ?>
                <?php unset($_SESSION[$key]); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<script>
(function () {
    var sb = document.getElementById('appSidebar');
    var bd = document.getElementById('appBackdrop');
    function abrir()  { sb.classList.add('abierta');  bd.classList.add('show'); }
    function cerrar() { sb.classList.remove('abierta'); bd.classList.remove('show'); }
    document.querySelectorAll('[data-app-burger]').forEach(function (b) { b.addEventListener('click', abrir); });
    if (bd) bd.addEventListener('click', cerrar);
    sb.querySelectorAll('.app-nav a').forEach(function (a) {
        a.addEventListener('click', function () { if (window.innerWidth < 992) cerrar(); });
    });
})();
</script>

<?php if (!defined('BOOTSTRAP_JS_LOADED')): ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php define('BOOTSTRAP_JS_LOADED', true); endif; ?>
