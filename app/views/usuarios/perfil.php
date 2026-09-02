<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Inventario 123</title>
    <?php include ROOT_PATH . '/app/views/components/favicon.php'; ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family:'Inter',sans-serif; background:#f4f7f6; }
        .card { background:white; border-radius:15px; padding:30px; box-shadow:0 4px 20px rgba(0,0,0,.08); max-width:700px; margin:0 auto; }
        .avatar-circle { width:140px; height:140px; border-radius:50%; background:linear-gradient(135deg,#667eea,#764ba2); display:inline-flex; align-items:center; justify-content:center; color:white; font-size:64px; font-weight:700; }
        .section-title { font-weight:700; color:#212529; border-bottom:2px solid #f1f3f5; padding-bottom:10px; margin-bottom:18px; margin-top:26px; }
        .info-row { display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid #f1f3f5; }
        .info-row:last-child { border-bottom:none; }
        .info-label { color:#6c757d; font-size:.9rem; }
        .info-value { font-weight:600; }
    </style>
</head>
<body>
<?php include ROOT_PATH . '/app/views/components/navbar.php'; ?>

<div class="container py-4">
<div class="card">

    <div class="d-flex align-items-center mb-4">
        <a href="index.php" class="btn btn-outline-secondary btn-sm me-3">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h2 class="mb-0 fw-bold"><i class="fas fa-user me-2"></i>Mi Perfil</h2>
    </div>

    <!-- Avatar -->
    <div class="text-center mb-4">
        <?php if (!empty($usuario['foto'])): ?>
            <img src="uploads/usuarios/<?= htmlspecialchars($usuario['foto']) ?>"
                 class="rounded-circle border shadow"
                 style="width:140px;height:140px;object-fit:cover;"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='inline-flex';">
            <div class="avatar-circle" style="display:none;">
                <?= strtoupper(substr($usuario['nombre'],0,1)) ?>
            </div>
        <?php else: ?>
            <div class="avatar-circle">
                <?= strtoupper(substr($usuario['nombre'],0,1)) ?>
            </div>
        <?php endif; ?>
        <div class="mt-2">
            <?php
            $tipoClases = ['admin'=>'bg-danger','coordinador'=>'bg-warning text-dark','fs'=>'bg-primary','ati'=>'bg-info text-dark'];
            $clase = $tipoClases[$usuario['tipo']] ?? 'bg-secondary';
            ?>
            <span class="badge rounded-pill <?= $clase ?> px-3 py-2">
                <?= strtoupper(htmlspecialchars($usuario['tipo'])) ?>
            </span>
        </div>
    </div>

    <!-- Info -->
    <h5 class="section-title">Información</h5>
    <div class="info-row">
        <span class="info-label"><i class="fas fa-user me-2"></i>Nombre</span>
        <span class="info-value"><?= htmlspecialchars($usuario['nombre']) ?></span>
    </div>
    <div class="info-row">
        <span class="info-label"><i class="fas fa-envelope me-2"></i>Correo</span>
        <span class="info-value"><?= htmlspecialchars($usuario['email']) ?></span>
    </div>
    <div class="info-row">
        <span class="info-label"><i class="fas fa-map-marker-alt me-2"></i>Plaza</span>
        <span class="info-value"><?= htmlspecialchars($usuario['plaza_nombre'] ?? '—') ?></span>
    </div>

    <!-- Cambiar contraseña -->
    <h5 class="section-title">Cambiar Contraseña</h5>
    <form method="POST" action="index.php?controller=usuario&action=actualizar">
        <input type="hidden" name="id"       value="<?= (int)$usuario['id'] ?>">
        <input type="hidden" name="nombre"   value="<?= htmlspecialchars($usuario['nombre']) ?>">
        <input type="hidden" name="email"    value="<?= htmlspecialchars($usuario['email']) ?>">
        <input type="hidden" name="tipo"     value="<?= htmlspecialchars($usuario['tipo']) ?>">
        <input type="hidden" name="plaza_id" value="<?= (int)($usuario['plaza_id'] ?? 0) ?>">

        <div class="row g-3 mb-4">
            <div class="col-md-8">
                <label class="form-label fw-semibold">Nueva Contraseña</label>
                <input type="password" name="password" class="form-control"
                       minlength="6" placeholder="Dejar vacío para no cambiar">
                <small class="text-muted">Mínimo 6 caracteres</small>
            </div>
        </div>

        <!-- Cambiar foto -->
        <h5 class="section-title">Cambiar Foto</h5>
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <input type="file" name="foto" id="foto" class="form-control"
                       accept="image/*" onchange="previewImg()">
                <small class="text-muted">Opcional · JPG, PNG (máx. 5MB)</small>
            </div>
            <div class="col-md-3">
                <div id="previewContainer" style="display:none;">
                    <img id="preview" class="img-thumbnail rounded-circle"
                         style="width:80px;height:80px;object-fit:cover;">
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary px-5">
                <i class="fas fa-save me-2"></i>Guardar Cambios
            </button>
            <a href="index.php" class="btn btn-outline-secondary px-4">
                <i class="fas fa-times me-2"></i>Cancelar
            </a>
        </div>
    </form>
</div>
</div>

<script>
// Nota: perfil.php usa form con enctype multipart — necesita el atributo
document.querySelector('form').setAttribute('enctype','multipart/form-data');

function previewImg() {
    const file = document.getElementById('foto').files[0];
    if (file && file.size > 5*1024*1024) { alert('La imagen excede 5MB.'); return; }
    if (file) {
        document.getElementById('preview').src = URL.createObjectURL(file);
        document.getElementById('previewContainer').style.display = 'block';
    }
}
</script>
</body>
</html>