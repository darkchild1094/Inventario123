<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actualizar app - Inventario 123</title>
    <?php include ROOT_PATH . '/app/views/components/favicon.php'; ?>
    <style> body { background:#f8f9fa; } </style>
</head>
<body>
<?php include ROOT_PATH . '/app/views/components/navbar.php'; ?>
<div class="container py-4">

    <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($_SESSION['success']) ?>
            <button class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php unset($_SESSION['success']); endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($_SESSION['error']) ?>
            <button class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php unset($_SESSION['error']); endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="fw-bold"><i class="fas fa-mobile-screen-button me-2"></i>Actualizar app</h2>
    </div>

    <?php $ultima = $builds[0] ?? null; ?>
    <?php if ($ultima): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <div class="text-muted small text-uppercase fw-semibold">Última versión publicada</div>
                    <div class="fs-4 fw-bold">
                        <?= htmlspecialchars($ultima['version_name']) ?>
                        <span class="text-muted fs-6 fw-normal">(versionCode <?= (int) $ultima['version_code'] ?>)</span>
                    </div>
                    <div class="text-muted small">
                        Subida por <?= htmlspecialchars($ultima['subido_por_nombre']) ?>
                        el <?= date('d/m/Y H:i', strtotime($ultima['creado_en'])) ?>
                        &middot; <?= number_format($ultima['tamano_bytes'] / 1048576, 1) ?> MB
                    </div>
                </div>
                <a class="btn btn-primary rounded-pill px-4" href="index.php?controller=apk&action=descargar">
                    <i class="fas fa-download me-2"></i>Descargar
                </a>
            </div>
            <hr>
            <div class="small">
                <strong>Enlace fijo para compartir</strong> (siempre apunta a la más reciente, no requiere iniciar sesión):
                <div class="input-group input-group-sm mt-1" style="max-width:520px;">
                    <input type="text" class="form-control" readonly id="enlaceFijo"
                           value="<?= htmlspecialchars((isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/index.php?controller=apk&action=descargar') ?>">
                    <button class="btn btn-outline-secondary" type="button" onclick="
                        navigator.clipboard.writeText(document.getElementById('enlaceFijo').value);
                        this.innerHTML='<i class=\'fas fa-check\'></i>';
                        setTimeout(()=>this.innerHTML='<i class=\'fas fa-copy\'></i>', 1500);">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold"><i class="fas fa-upload me-2"></i>Subir nueva versión</div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data" action="index.php?controller=apk&action=subir" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Archivo APK *</label>
                    <input type="file" name="apk" accept=".apk" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Nombre de versión *</label>
                    <input type="text" name="version_name" class="form-control" placeholder="ej. 1.6.0" required>
                    <div class="form-text">El <code>versionName</code> de build.gradle.kts</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">versionCode *</label>
                    <input type="number" name="version_code" min="1" class="form-control" placeholder="ej. 8" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Notas (opcional)</label>
                    <input type="text" name="notas" class="form-control" placeholder="Qué cambió en esta versión…" maxlength="500">
                </div>
                <div class="col-12">
                    <button class="btn btn-primary rounded-pill px-4"><i class="fas fa-upload me-2"></i>Subir</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold"><i class="fas fa-history me-2"></i>Historial</div>
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-4">Versión</th>
                        <th>Notas</th>
                        <th>Subida por</th>
                        <th>Fecha</th>
                        <th class="text-center">Tamaño</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($builds)): foreach ($builds as $i => $b): ?>
                    <tr>
                        <td class="ps-4 fw-semibold">
                            <?= htmlspecialchars($b['version_name']) ?>
                            <span class="text-muted">(<?= (int) $b['version_code'] ?>)</span>
                            <?php if ($i === 0): ?><span class="badge bg-success ms-1">actual</span><?php endif; ?>
                        </td>
                        <td class="text-muted"><?= htmlspecialchars($b['notas'] ?? '') ?></td>
                        <td><?= htmlspecialchars($b['subido_por_nombre']) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($b['creado_en'])) ?></td>
                        <td class="text-center"><?= number_format($b['tamano_bytes'] / 1048576, 1) ?> MB</td>
                        <td class="text-center">
                            <a href="index.php?controller=apk&action=descargar&id=<?= $b['id'] ?>"
                               class="btn btn-sm btn-outline-primary me-1" title="Descargar esta versión">
                                <i class="fas fa-download"></i>
                            </a>
                            <button class="btn btn-sm btn-outline-danger" title="Eliminar"
                                    data-bs-toggle="modal" data-bs-target="#modalEliminar"
                                    data-id="<?= $b['id'] ?>"
                                    data-nombre="<?= htmlspecialchars($b['version_name'], ENT_QUOTES) ?>">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="6" class="text-center text-muted py-5">
                        <i class="fas fa-mobile-screen-button fa-2x mb-2 d-block opacity-25"></i>Todavía no se ha subido ninguna versión.
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEliminar" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="POST" action="index.php?controller=apk&action=eliminar">
      <div class="modal-header">
        <h5 class="modal-title">Eliminar versión</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="elimId">
        <p>¿Eliminar la versión <strong id="elimNombre"></strong>? El archivo se borra del servidor.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-danger">Eliminar</button>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('modalEliminar').addEventListener('show.bs.modal', (ev) => {
    const btn = ev.relatedTarget;
    document.getElementById('elimId').value = btn.dataset.id;
    document.getElementById('elimNombre').textContent = btn.dataset.nombre;
});
</script>
</body>
</html>
