<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Auditoría - Inventario 123</title>
<?php include ROOT_PATH . '/app/views/components/favicon.php'; ?>
</head>
<body>
<?php include ROOT_PATH . '/app/views/components/navbar.php'; ?>
<div class="container-fluid px-3 px-lg-4 py-4">
  <h4 class="fw-bold mb-3"><i class="fas fa-clipboard-check me-2 text-primary"></i>Auditoría</h4>
  <div class="card card-soft">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="table-light"><tr><th>Tienda</th><th>Responsable</th><th>Resultado</th><th>Cerrado</th><th></th></tr></thead>
        <tbody id="tbody"></tbody>
      </table>
    </div>
    <div class="p-3 text-muted small" id="vacio" style="display:none">No hay checklists cerrados.</div>
  </div>
</div>
<script>
async function cargar() {
  const r = await Api.get('auditoriasListar');
  const items = r.auditorias || [];
  document.getElementById('tbody').innerHTML = items.map(a => {
    const faltan = a.total_esperado - a.total_encontrado;
    return `<tr style="cursor:pointer" onclick="location.href='index.php?controller=auditoria&action=detalle&id=${a.id}'">
      <td><div class="fw-semibold">${UI.esc(a.tienda_nombre)}</div><div class="text-muted small">${UI.esc(a.cr_tienda)}</div></td>
      <td>${UI.esc(a.usuario_nombre)}</td>
      <td>${a.total_encontrado}/${a.total_esperado} encontrados ${faltan > 0 ? `<span class="badge bg-danger ms-1">${faltan} no encontrado(s)</span>` : '<span class="badge bg-success ms-1">Completo</span>'}</td>
      <td class="text-muted small">${UI.esc((a.cerrado_en||'').replace('T',' '))}</td>
      <td><i class="fas fa-chevron-right text-muted"></i></td>
    </tr>`;
  }).join('');
  document.getElementById('vacio').style.display = items.length ? 'none' : 'block';
}
cargar();
</script>
</body>
</html>
