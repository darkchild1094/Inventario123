<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Inventario 123</title>
    <?php include ROOT_PATH . '/app/views/components/favicon.php'; ?>
    <style>
        body { background-color: #f8f9fa; }
        .user-avatar { width:50px; height:50px; border-radius:50%; object-fit:cover; }
        .user-avatar-placeholder {
            width:50px; height:50px; border-radius:50%;
            background: linear-gradient(135deg,#667eea,#764ba2);
            color:white; display:flex; align-items:center;
            justify-content:center; font-size:20px; font-weight:700;
        }
    </style>
</head>
<body>
<?php include ROOT_PATH . '/app/views/components/navbar.php'; ?>
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="fas fa-users me-2"></i>Gestión de Usuarios</h2>
        <a href="index.php?controller=usuario&action=crear" class="btn btn-primary rounded-pill px-4">
            <i class="fas fa-user-plus me-2"></i>Nuevo Usuario
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-4">Usuario</th>
                        <th>Plaza</th>
                        <th>Tipo</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($usuarios)): ?>
                    <?php foreach ($usuarios as $u): ?>
                        <?php
                        $tipoClases = [
                            'admin'       => 'bg-danger',
                            'coordinador' => 'bg-warning text-dark',
                            'fs'          => 'bg-primary',
                            'ati'         => 'bg-info text-dark',
                        ];
                        $tipoClase = $tipoClases[$u['tipo']] ?? 'bg-secondary';
                        $esMismo   = (int)$u['id'] === (int)($_SESSION['usuario']['id'] ?? $_SESSION['usuario_id'] ?? 0);
                        ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-3">
                                    <?php if (!empty($u['foto'])): ?>
                                        <img src="uploads/usuarios/<?= htmlspecialchars($u['foto']) ?>"
                                             class="user-avatar"
                                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                        <div class="user-avatar-placeholder" style="display:none;">
                                            <?= strtoupper(substr($u['nombre'],0,1)) ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="user-avatar-placeholder">
                                            <?= strtoupper(substr($u['nombre'],0,1)) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="fw-bold">
                                            <?= htmlspecialchars($u['nombre']) ?>
                                            <?php if ($esMismo): ?>
                                                <span class="badge bg-secondary ms-1" style="font-size:.65rem;">Tú</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-muted small"><?= htmlspecialchars($u['email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($u['plaza_nombre'] ?? '—') ?></td>
                            <td>
                                <span class="badge rounded-pill <?= $tipoClase ?>">
                                    <?= strtoupper(htmlspecialchars($u['tipo'])) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <a href="index.php?controller=usuario&action=perfil&id=<?= $u['id'] ?>"
                                   class="btn btn-sm btn-outline-secondary me-1" title="Ver perfil">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="index.php?controller=usuario&action=editar&id=<?= $u['id'] ?>"
                                   class="btn btn-sm btn-outline-primary me-1" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if (!$esMismo): ?>
                                    <form action="index.php?controller=usuario&action=eliminar"
                                          method="POST" class="d-inline"
                                          onsubmit="return confirm('¿Eliminar a <?= addslashes(htmlspecialchars($u['nombre'])) ?>?');">
                                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted py-5">
                            <i class="fas fa-users fa-2x mb-2 d-block opacity-25"></i>
                            No hay usuarios registrados.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>