<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo modelo - Inventario 123</title>
    <?php include ROOT_PATH . '/app/views/components/favicon.php'; ?>
    <style> body { background:#f4f7f6; } .card { max-width:640px; margin:0 auto; } </style>
</head>
<body>
<?php include ROOT_PATH . '/app/views/components/navbar.php'; ?>
<div class="container py-4">
    <div class="card border-0 shadow-sm p-4">
        <h4 class="fw-bold mb-3"><i class="fas fa-plus me-2"></i>Nuevo modelo</h4>
        <form method="POST" action="index.php?controller=modelo&action=guardar">
            <div class="mb-3">
                <label class="form-label">Categoría de dispositivo *</label>
                <select name="dispositivo_id" class="form-select" required>
                    <option value="">Selecciona…</option>
                    <?php foreach ($dispositivos as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Marca</label>
                <select name="marca_id" class="form-select" id="marcaSel">
                    <option value="">— Sin marca —</option>
                    <?php foreach ($marcas as $ma): ?>
                        <option value="<?= $ma['id'] ?>"><?= htmlspecialchars($ma['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="marca_nueva" class="form-control mt-2" id="marcaNueva"
                       placeholder="…o escribe una marca nueva">
                <small class="text-muted">Si escribes una marca nueva, se ignora la seleccionada arriba.</small>
            </div>
            <div class="mb-3">
                <label class="form-label">Modelo *</label>
                <input type="text" name="nombre" class="form-control" maxlength="100" required
                       placeholder="Ej.: M3044-V, TM-T88VII, Engage One Pro">
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-primary"><i class="fas fa-save me-1"></i>Guardar</button>
                <a href="index.php?controller=modelo&action=index" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
<script>
document.getElementById('marcaNueva').addEventListener('input', function () {
    document.getElementById('marcaSel').disabled = this.value.trim() !== '';
});
</script>
</body>
</html>
