<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Usuario - Inventario 123</title>
    <?php include ROOT_PATH . '/app/views/components/favicon.php'; ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family:'Inter',sans-serif; background:#f4f7f6; }
        .card { background:white; border-radius:15px; padding:30px; box-shadow:0 4px 20px rgba(0,0,0,.08); max-width:800px; margin:0 auto; }
        .section-title { font-weight:700; color:#212529; border-bottom:2px solid #f1f3f5; padding-bottom:12px; margin-bottom:20px; margin-top:28px; }
        .password-strength { height:5px; border-radius:3px; margin-top:6px; transition:all .3s; }
        .strength-weak   { background:#dc3545; width:33%; }
        .strength-medium { background:#ffc107; width:66%; }
        .strength-strong { background:#28a745; width:100%; }
        .password-btn { padding:5px 12px; background:#e9ecef; border:none; border-radius:6px; cursor:pointer; font-size:.82rem; transition:all .2s; }
        .password-btn:hover { background:#667eea; color:white; }
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
        <h2 class="mb-0 fw-bold"><i class="fas fa-user-plus me-2"></i>Crear Nuevo Usuario</h2>
    </div>

    <form method="POST" action="index.php?controller=usuario&action=guardar" enctype="multipart/form-data">

        <h5 class="section-title">Datos personales</h5>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">Nombre Completo <span class="text-danger">*</span></label>
                <input type="text" name="nombre" class="form-control" required placeholder="Ej: Juan Pérez García">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Correo Electrónico <span class="text-danger">*</span></label>
                <input type="email" name="email" class="form-control" required placeholder="usuario@femsa.com">
                <small class="text-muted">Se usará para iniciar sesión</small>
            </div>
        </div>

        <h5 class="section-title">Acceso y Rol</h5>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">Contraseña <span class="text-danger">*</span></label>
                <input type="password" name="password" id="password" class="form-control"
                       required minlength="6" placeholder="Mínimo 6 caracteres"
                       onkeyup="checkStrength()">
                <div id="passwordStrength" class="password-strength"></div>
                <div class="d-flex gap-2 mt-2 flex-wrap">
                    <button type="button" class="password-btn" onclick="setPass('Femsa123!')">Femsa123!</button>
                    <button type="button" class="password-btn" onclick="setPass('Admin123!')">Admin123!</button>
                    <button type="button" class="password-btn" onclick="genPass()">
                        <i class="fas fa-dice me-1"></i>Generar
                    </button>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Tipo <span class="text-danger">*</span></label>
                <select name="tipo" class="form-select" required>
                    <option value="fs">FS — Field Service</option>
                    <option value="ati">ATI — Asesor TI</option>
                    <option value="coordinador">Coordinador</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Plazas <span class="text-danger">*</span></label>
                <div class="border rounded p-2" style="max-height: 220px; overflow-y: auto;">
                    <?php foreach ($plazas as $p): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="plaza_id[]"
                                   id="plaza_<?= $p['id'] ?>" value="<?= $p['id'] ?>">
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
                <i class="fas fa-save me-2"></i>Crear Usuario
            </button>
            <a href="index.php?controller=usuario&action=index" class="btn btn-outline-secondary px-4">
                <i class="fas fa-times me-2"></i>Cancelar
            </a>
        </div>
    </form>
</div>
</div>

<script>
function checkStrength() {
    const p   = document.getElementById('password').value;
    const bar = document.getElementById('passwordStrength');
    if (!p.length) { bar.className = 'password-strength'; return; }
    let s = 0;
    if (p.length >= 6)  s++;
    if (p.length >= 10) s++;
    if (/[A-Z]/.test(p) && /[a-z]/.test(p)) s++;
    if (/[0-9]/.test(p))       s++;
    if (/[^A-Za-z0-9]/.test(p)) s++;
    bar.className = 'password-strength ' + (s <= 2 ? 'strength-weak' : s <= 4 ? 'strength-medium' : 'strength-strong');
}
function setPass(p) {
    document.getElementById('password').value = p;
    checkStrength();
}
function genPass() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%';
    let p = '';
    for (let i = 0; i < 12; i++) p += chars[Math.floor(Math.random() * chars.length)];
    setPass(p);
}
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