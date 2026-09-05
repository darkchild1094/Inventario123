<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo de modelos - Inventario 123</title>
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
        <h2 class="fw-bold"><i class="fas fa-tags me-2"></i>Catálogo de modelos</h2>
        <a href="index.php?controller=modelo&action=crear" class="btn btn-primary rounded-pill px-4">
            <i class="fas fa-plus me-2"></i>Nuevo modelo
        </a>
    </div>

    <form class="row g-2 mb-3" method="GET">
        <input type="hidden" name="controller" value="modelo">
        <input type="hidden" name="action" value="index">
        <div class="col-md-4">
            <input type="text" name="busqueda" class="form-control" placeholder="Buscar modelo / marca / categoría…"
                   value="<?= htmlspecialchars($_GET['busqueda'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <select name="dispositivo_id" class="form-select">
                <option value="">Todas las categorías</option>
                <?php foreach ($dispositivos as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= (int)($_GET['dispositivo_id'] ?? 0) === (int)$d['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select name="marca_id" class="form-select">
                <option value="">Todas las marcas</option>
                <?php foreach ($marcas as $ma): ?>
                    <option value="<?= $ma['id'] ?>" <?= (int)($_GET['marca_id'] ?? 0) === (int)$ma['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ma['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-grid">
            <button class="btn btn-outline-secondary"><i class="fas fa-filter me-1"></i>Filtrar</button>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-4">Categoría (dispositivo)</th>
                        <th>Marca</th>
                        <th>Modelo</th>
                        <th class="text-center">Activos</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($modelos)): foreach ($modelos as $m): ?>
                    <tr>
                        <td class="ps-4"><?= htmlspecialchars($m['dispositivo_nombre'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($m['marca_nombre'] ?? '—') ?></td>
                        <td class="fw-semibold"><?= htmlspecialchars($m['nombre']) ?></td>
                        <td class="text-center">
                            <span class="badge rounded-pill <?= $m['activos_count'] > 0 ? 'bg-secondary' : 'bg-light text-muted' ?>">
                                <?= (int)$m['activos_count'] ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <a href="index.php?controller=modelo&action=editar&id=<?= $m['id'] ?>"
                               class="btn btn-sm btn-outline-primary me-1" title="Editar"><i class="fas fa-edit"></i></a>
                            <button class="btn btn-sm btn-outline-danger" title="Eliminar"
                                    data-bs-toggle="modal" data-bs-target="#modalEliminar"
                                    data-id="<?= $m['id'] ?>"
                                    data-nombre="<?= htmlspecialchars($m['nombre'], ENT_QUOTES) ?>"
                                    data-disp="<?= (int)$m['dispositivo_id'] ?>"
                                    data-count="<?= (int)$m['activos_count'] ?>">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="5" class="text-center text-muted py-5">
                        <i class="fas fa-tags fa-2x mb-2 d-block opacity-25"></i>No hay modelos con esos filtros.
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal eliminar (con reasignación si el modelo está en uso) -->
<div class="modal fade" id="modalEliminar" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="POST" action="index.php?controller=modelo&action=eliminar">
      <div class="modal-header">
        <h5 class="modal-title">Eliminar modelo</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="elimId">
        <p>¿Eliminar el modelo <strong id="elimNombre"></strong>?</p>
        <div id="elimEnUso" class="d-none">
          <div class="alert alert-warning py-2">
            <span id="elimCount"></span> activos usan este modelo. Elige a qué modelo reasignarlos:
          </div>
          <select name="reasignar_a" id="elimReasignar" class="form-select">
            <option value="">Selecciona un modelo destino…</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-danger">Eliminar</button>
      </div>
    </form>
  </div>
</div>

<script>
const TODOS_MODELOS = <?= json_encode(array_map(fn($m) => [
    'id' => (int)$m['id'], 'nombre' => $m['nombre'],
    'disp' => (int)$m['dispositivo_id'],
    'disp_nombre' => $m['dispositivo_nombre'] ?? '',
    'marca' => $m['marca_nombre'] ?? '',
], $todosModelos), JSON_UNESCAPED_UNICODE) ?>;

document.getElementById('modalEliminar').addEventListener('show.bs.modal', function (ev) {
    const b = ev.relatedTarget;
    const id = +b.dataset.id, disp = +b.dataset.disp, count = +b.dataset.count;
    document.getElementById('elimId').value = id;
    document.getElementById('elimNombre').textContent = b.dataset.nombre;
    const wrap = document.getElementById('elimEnUso');
    const sel  = document.getElementById('elimReasignar');
    if (count > 0) {
        wrap.classList.remove('d-none');
        document.getElementById('elimCount').textContent = count;
        sel.required = true;
        sel.innerHTML = '<option value="">Selecciona un modelo destino…</option>';
        // mismo dispositivo primero, luego el resto
        const orden = [...TODOS_MODELOS].filter(m => m.id !== id)
            .sort((a, c) => (c.disp === disp) - (a.disp === disp) || a.disp_nombre.localeCompare(c.disp_nombre));
        orden.forEach(m => {
            const et = `${m.disp_nombre} · ${m.marca ? m.marca + ' ' : ''}${m.nombre}`;
            sel.appendChild(new Option(et, m.id));
        });
    } else {
        wrap.classList.add('d-none');
        sel.required = false;
    }
});
</script>
</body>
</html>
