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
  <a href="index.php?controller=auditoria" class="text-decoration-none small mb-2 d-inline-block"><i class="fas fa-arrow-left me-1"></i>Auditoría</a>
  <div id="cabecera" class="mb-3"></div>
  <div class="card card-soft"><div class="list-group list-group-flush" id="lista"></div></div>
</div>
<script>
const ID = <?= (int) $id ?>;
async function cargar() {
  const d = await Api.get('auditoriaDetalle', { id: ID });
  document.getElementById('cabecera').innerHTML = `
    <h4 class="fw-bold mb-0">${UI.esc(d.tienda_nombre)}</h4>
    <div class="text-muted">${UI.esc(d.cr_tienda)} · ${d.total_encontrado}/${d.total_esperado} encontrados · por ${UI.esc(d.usuario_nombre)} · ${UI.esc((d.cerrado_en||'').replace('T',' '))}</div>`;
  document.getElementById('lista').innerHTML = (d.detalle || []).map(r => `
    <div class="list-group-item d-flex align-items-center gap-2">
      <i class="fas ${r.encontrado ? 'fa-check-circle text-success' : 'fa-times-circle text-danger'}"></i>
      <div class="flex-grow-1">
        <div class="fw-semibold">${UI.esc([r.dispositivo_nombre, r.marca_nombre, r.modelo_nombre].filter(Boolean).join(' · '))}</div>
        <div class="text-muted small font-monospace">${UI.esc(r.serie || '')}${r.codigo_barras ? ' · CB:'+UI.esc(r.codigo_barras) : ''}</div>
        ${r.nota ? `<div class="text-muted small">${UI.esc(r.nota)}</div>` : ''}
      </div>
    </div>`).join('') || '<div class="list-group-item text-muted">Sin detalle.</div>';
}
cargar();
</script>
</body>
</html>
