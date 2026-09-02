<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuario - Inventario 123</title>
    <?php include ROOT_PATH . '/app/views/components/favicon.php'; ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family:'Inter',sans-serif; background:#f8f9fa; }
        .card { background:white; border-radius:15px; padding:30px; box-shadow:0 4px 20px rgba(0,0,0,.08); max-width:800px; margin:0 auto; }
        .section-title { font-weight:700; color:#212529; border-bottom:2px solid #f1f3f5; padding-bottom:12px; margin-bottom:20px; margin-top:28px; }
        .avatar-circle { width:120px; height:120px; border-radius:50%; background:linear-gradient(135deg,#667eea,#764ba2); display:flex; align-items:center; justify-content:center; color:white; font-size:52px; font-weight:700; margin:0 auto 20px; }
    </style>
</head>
<body>
<?php include ROOT_PATH . '/app/views/components/navbar.php'; ?>

<div class="container py-4">
<div class="card">

    <div class="d-flex align-items-center mb-4">
        <a href="index.php?controller=usuario&action=index" class="btn btn-outline-secondary btn-sm me-3">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h2 class="mb-0 fw-bold"><i class="fas fa-user-edit me-2"></i>Editar Usuario</h2>
    </div>

    <!-- Avatar actual -->
    <div class="text-center mb-3">
        <?php if (!empty($usuario['foto'])): ?>
            <img src="uploads/usuarios/<?= htmlspecialchars($usuario['foto']) ?>"
                 class="rounded-circle border shadow-sm"
                 style="width:120px;height:120px;object-fit:cover;"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
            <div class="avatar-circle" style="display:none;">
                <?= strtoupper(substr($usuario['nombre'],0,1)) ?>
            </div>
        <?php else: ?>
            <div class="avatar-circle">
                <?= strtoupper(substr($usuario['nombre'],0,1)) ?>
            </div>
        <?php endif; ?>
        <div class="text-muted small"><?= htmlspecialchars($usuario['email']) ?></div>
    </div>

    <div class="alert alert-info border-0 shadow-sm rounded-3">
        <i class="fas fa-info-circle me-2"></i>
        Deja la contraseña vacía si no deseas cambiarla.
    </div>

    <form method="POST" action="index.php?controller=usuario&action=actualizar" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= (int)$usuario['id'] ?>">

        <h5 class="section-title">Datos personales</h5>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">Nombre Completo <span class="text-danger">*</span></label>
                <input type="text" name="nombre" class="form-control" required
                       value="<?= htmlspecialchars($usuario['nombre'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Correo Electrónico <span class="text-danger">*</span></label>
                <input type="email" name="email" class="form-control" required
                       value="<?= htmlspecialchars($usuario['email'] ?? '') ?>">
            </div>
        </div>

        <h5 class="section-title">Acceso y Rol</h5>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Nueva Contraseña</label>
                <input type="password" name="password" class="form-control"
                       minlength="6" placeholder="Dejar vacío para no cambiar">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Tipo <span class="text-danger">*</span></label>
                <select name="tipo" class="form-select" required>
                    <?php
                    $tipos = ['fs' => 'FS — Field Service', 'ati' => 'ATI — Asesor TI', 'coordinador' => 'Coordinador', 'admin' => 'Admin'];
                    foreach ($tipos as $val => $label):
                    ?>
                        <option value="<?= $val ?>" <?= ($usuario['tipo'] ?? '') === $val ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Plazas <span class="text-danger">*</span></label>
                <?php
                    $usuarioPlazaIds = array_map('intval', array_column($usuarioPlazas ?? [], 'id'));
                ?>
                <div class="border rounded p-2" style="max-height: 220px; overflow-y: auto;">
                    <?php foreach ($plazas as $p): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="plaza_id[]"
                                   id="plaza_<?= $p['id'] ?>" value="<?= $p['id'] ?>"
                                   <?= in_array((int)$p['id'], $usuarioPlazaIds, true) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="plaza_<?= $p['id'] ?>">
                                <?= htmlspecialchars($p['nombre']) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <small class="text-muted">Marca todas las plazas a las que este usuario debe tener acceso (puede ser más de una, incluso de negocios distintos).</small>
            </div>
        </div>

        <h5 class="section-title">Foto de perfil</h5>
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <input type="file" name="foto" id="foto" class="form-control" accept="image/*"
                       onchange="previewImg()">
                <small class="text-muted">Opcional · JPG, PNG (máx. 5MB)</small>
            </div>
            <div class="col-md-3">
                <div id="previewContainer" style="display:none;">
                    <img id="preview" class="img-thumbnail rounded-circle"
                         style="width:80px;height:80px;object-fit:cover;">
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-primary px-5">
                <i class="fas fa-save me-2"></i>Guardar Cambios
            </button>
            <a href="index.php?controller=usuario&action=index" class="btn btn-outline-secondary px-4">
                <i class="fas fa-times me-2"></i>Cancelar
            </a>
        </div>
    </form>
</div>
</div>

<script>
function previewImg() {
    const file = document.getElementById('foto').files[0];
    if (file && file.size > 5 * 1024 * 1024) { alert('La imagen excede 5MB.'); return; }
    if (file) {
        document.getElementById('preview').src = URL.createObjectURL(file);
        document.getElementById('previewContainer').style.display = 'block';
    }
}
</script>
</body>
</html>