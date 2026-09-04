<?php if (!defined('BOOTSTRAP_LOADED')): ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php define('BOOTSTRAP_LOADED', true); endif; ?>

<style>
    .navbar-sigma { background-color: #212529; box-shadow: 0 2px 8px rgba(0,0,0,.15); }
    .navbar-sigma .navbar-brand { font-weight: 700; color: white; display: flex; align-items: center; }
    .navbar-sigma .nav-link {
        color: rgba(255,255,255,.85); font-weight: 500; transition: color .2s;
        white-space: nowrap; padding-left: .65rem; padding-right: .65rem;
        display: flex; align-items: center;
    }
    .navbar-sigma .nav-link:hover { color: white; }
    .navbar-sigma .nav-link.active { color: #0d6efd !important; font-weight: 700; }
    @media (max-width: 1199.98px) {
        .navbar-sigma .navbar-collapse { padding-top: .5rem; }
        .navbar-sigma .nav-link { padding: .5rem .25rem; }
    }
    .user-menu { color: rgba(255,255,255,.95) !important; padding: 8px 15px !important; border-radius: 10px; transition: all .3s; }
    .user-menu:hover { background-color: rgba(255,255,255,.1); }
    .user-avatar { width:35px; height:35px; border-radius:50%; background:linear-gradient(135deg,#667eea,#764ba2); display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.9rem; color:white; border:2px solid rgba(255,255,255,.2); }
    .user-avatar-large { width:50px; height:50px; border-radius:50%; background:linear-gradient(135deg,#667eea,#764ba2); display:flex; align-items:center; justify-content:center; font-weight:700; font-size:1.3rem; color:white; }
    .user-name { font-weight:600; font-size:.9rem; line-height:1.2; }
    .user-tipo { font-size:.75rem; opacity:.8; line-height:1; }
    .user-dropdown { border:none; border-radius:15px; padding:0; min-width:280px; margin-top:10px; }
    .user-dropdown .dropdown-header { padding:20px; background:linear-gradient(135deg,#f8f9fa,#e9ecef); border-radius:15px 15px 0 0; }
</style>

<nav class="navbar navbar-expand-xl navbar-dark navbar-sigma mb-4">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <img src="assets/img/logo_texto_white_520x120.png" alt="Inventario 123" style="height:36px;width:auto;"
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';">
            <span style="display:none;font-size:1.25rem;">Inventario 123</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto align-items-xl-center gap-xl-1">

                <?php
                $tipo    = $_SESSION['usuario']['tipo'] ?? $_SESSION['usuario_tipo'] ?? '';
                $vistaAc = $_GET['vista'] ?? '';
                $ctrl    = $_GET['controller'] ?? '';
                ?>

                <?php
                // ── BODEGA: admin, coordinador, ati ──────────────────────────
                if (in_array($tipo, ['admin', 'coordinador', 'ati'])): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= ($vistaAc === 'bodega' || ($vistaAc === '' && $ctrl === '')) ? 'active' : '' ?>"
                           href="index.php?vista=bodega">
                            <i class="fas fa-warehouse me-1"></i> Bodega
                        </a>
                    </li>
                <?php endif; ?>

                <?php
                // ── MI STOCK: fs ve solo esto | ati también ve su stock ───────
                if (in_array($tipo, ['fs', 'ati'])): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $vistaAc === 'mi_stock' ? 'active' : '' ?>"
                           href="index.php?vista=mi_stock">
                            <i class="fas fa-toolbox me-1"></i> Mi Stock
                        </a>
                    </li>
                <?php endif; ?>

                <?php
                // ── TODOS: admin y coordinador ────────────────────────────────
                if (in_array($tipo, ['admin', 'coordinador'])): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $vistaAc === 'todos' ? 'active' : '' ?>"
                           href="index.php?vista=todos">
                            <i class="fas fa-list me-1"></i> Todos
                        </a>
                    </li>
                <?php endif; ?>

                <?php
                // ── NUEVO ACTIVO: todos los roles ─────────────────────────────
                if (in_array($tipo, ['admin', 'coordinador', 'fs', 'ati'])): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= ($_GET['action'] ?? '') === 'crear' ? 'active' : '' ?>"
                           href="index.php?action=crear">
                            <i class="fas fa-plus-circle me-1"></i> Nuevo Activo
                        </a>
                    </li>
                <?php endif; ?>

                <?php
                // ── HISTORIAL: admin, coordinador, fs, ati ────────────────────
                if (in_array($tipo, ['admin', 'coordinador', 'fs', 'ati'])): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $ctrl === 'historial' ? 'active' : '' ?>"
                           href="index.php?controller=historial&action=index">
                            <i class="fas fa-clock-rotate-left me-1"></i> Historial
                        </a>
                    </li>
                <?php endif; ?>

                <?php
                // ── TIENDAS (ATI responsable): solo admin ─────────────────────
                if ($tipo === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $ctrl === 'tienda' ? 'active' : '' ?>"
                           href="index.php?controller=tienda&action=index">
                            <i class="fas fa-store me-1"></i> Tiendas
                        </a>
                    </li>
                <?php endif; ?>

                <?php
                // ── EXPORTAR: todos los roles ─────────────────────────────────
                if (in_array($tipo, ['admin', 'coordinador', 'fs', 'ati'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php?controller=export&action=inventario">
                            <i class="fas fa-file-excel me-1 text-success"></i> Exportar
                        </a>
                    </li>
                <?php endif; ?>

                <?php
                // ── USUARIOS: solo admin ──────────────────────────────────────
                if ($tipo === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $ctrl === 'usuario' ? 'active' : '' ?>"
                           href="index.php?controller=usuario&action=index">
                            <i class="fas fa-users-cog me-1"></i> Usuarios
                        </a>
                    </li>
                <?php endif; ?>

            </ul>

            <!-- Menú de usuario -->
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center user-menu"
                       href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <?php if (!empty($_SESSION['usuario']['foto'])): ?>
                            <img src="uploads/usuarios/<?= htmlspecialchars($_SESSION['usuario']['foto']) ?>"
                                 class="user-avatar me-2" style="object-fit:cover;"
                                 onerror="this.style.display='none'">
                        <?php else: ?>
                            <div class="user-avatar me-2">
                                <?= strtoupper(substr($_SESSION['usuario']['nombre'] ?? 'U', 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <div class="d-none d-md-block text-start">
                            <div class="user-name"><?= htmlspecialchars($_SESSION['usuario']['nombre'] ?? 'Usuario') ?></div>
                            <div class="user-tipo text-uppercase"><?= htmlspecialchars($tipo ?: '—') ?></div>
                        </div>
                    </a>

                    <ul class="dropdown-menu dropdown-menu-end user-dropdown shadow-lg">
                        <li class="dropdown-header">
                            <div class="d-flex align-items-center">
                                <?php if (!empty($_SESSION['usuario']['foto'])): ?>
                                    <img src="uploads/usuarios/<?= htmlspecialchars($_SESSION['usuario']['foto']) ?>"
                                         class="user-avatar-large me-3" style="object-fit:cover;"
                                         onerror="this.style.display='none'">
                                <?php else: ?>
                                    <div class="user-avatar-large me-3">
                                        <?= strtoupper(substr($_SESSION['usuario']['nombre'] ?? 'U', 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <div class="fw-bold"><?= htmlspecialchars($_SESSION['usuario']['nombre'] ?? '') ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($_SESSION['usuario']['email'] ?? '') ?></small>
                                    <div class="mt-1">
                                        <?php
                                        $badgeColor = match($tipo) {
                                            'admin'       => 'danger',
                                            'coordinador' => 'warning text-dark',
                                            'fs'          => 'primary',
                                            'ati'         => 'info text-dark',
                                            default       => 'secondary',
                                        };
                                        ?>
                                        <span class="badge bg-<?= $badgeColor ?> text-uppercase" style="font-size:.65rem;">
                                            <?= htmlspecialchars($tipo ?: '—') ?>
                                        </span>
                                        <?php if (!empty($_SESSION['usuario']['plaza_nombre'])): ?>
                                            <span class="badge bg-light text-muted border ms-1" style="font-size:.65rem;">
                                                <?= htmlspecialchars($_SESSION['usuario']['plaza_nombre']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </li>
                        <li><hr class="dropdown-divider"></li>

                        <!-- Mi perfil: todos pueden ver/editar el suyo -->
                        <li>
                            <a class="dropdown-item" href="index.php?controller=usuario&action=perfil&id=<?= $_SESSION['usuario']['id'] ?? '' ?>">
                                <i class="fas fa-user-edit me-2 text-info"></i> Mi Perfil
                            </a>
                        </li>

                        <?php if (in_array($tipo, ['admin', 'coordinador', 'fs', 'ati'])): ?>
                        <li>
                            <a class="dropdown-item" href="index.php?controller=export&action=inventario">
                                <i class="fas fa-file-excel me-2 text-success"></i>
                                <?= $tipo === 'fs' ? 'Exportar mi stock' : 'Exportar Excel' ?>
                            </a>
                        </li>
                        <?php endif; ?>

                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="index.php?controller=auth&action=logout">
                                <i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<?php foreach(['success' => 'check-circle', 'error' => 'exclamation-circle'] as $key => $icon): ?>
    <?php if (isset($_SESSION[$key])): ?>
        <div class="container">
            <div class="alert alert-<?= $key === 'error' ? 'danger' : 'success' ?> alert-dismissible fade show shadow-sm">
                <i class="fas fa-<?= $icon ?> me-2"></i>
                <?= htmlspecialchars($_SESSION[$key]) ?>
                <?php unset($_SESSION[$key]); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<?php if (!defined('BOOTSTRAP_JS_LOADED')): ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php define('BOOTSTRAP_JS_LOADED', true); endif; ?>