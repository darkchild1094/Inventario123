<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notificaciones - Inventario 123</title>
<?php include ROOT_PATH . '/app/views/components/favicon.php'; ?>
<style>.notif-no-leida { background: #eef5ff; }</style>
</head>
<body>
<?php include ROOT_PATH . '/app/views/components/navbar.php'; ?>
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="fw-bold mb-0"><i class="fas fa-bell me-2 text-primary"></i>Notificaciones</h4>
    <button class="btn btn-outline-secondary btn-sm" id="btnMarcarTodas"><i class="fas fa-check-double me-1"></i>Marcar todas</button>
  </div>
  <div class="card card-soft"><div class="list-group list-group-flush" id="lista"></div></div>
</div>

<script>
const DIRECCION_LBL = {
  a_bodega: (n, lugar) => `te transfirió ${n} activo(s) a ${lugar}`,
  a_stock: (n, lugar) => `te transfirió ${n} activo(s) a tu stock`,
};

async function cargar() {
  const notifs = await Api.get('listarNotificaciones');
  document.getElementById('lista').innerHTML = (notifs || []).map(n => {
    const n_activos = (n.activos || []).length;
    const texto = (DIRECCION_LBL[n.direccion] || (() => n.lugar))(n_activos, n.lugar);
    return `<div class="list-group-item ${n.leida ? '' : 'notif-no-leida'}" data-id="${n.id}" style="cursor:pointer">
      <div class="d-flex justify-content-between">
        <div><strong>${UI.esc(n.de_nombre)}</strong> ${UI.esc(texto)}</div>
        ${!n.leida ? '<span class="badge bg-primary rounded-pill">●</span>' : ''}
      </div>
      <div class="text-muted small">${UI.esc((n.creado_en || '').replace('T',' '))}</div>
      <div class="text-muted small">${(n.activos||[]).map(a => UI.esc([a.dispositivo_nombre,a.modelo_nombre,a.serie].filter(Boolean).join(' · '))).join('<br>')}</div>
    </div>`;
  }).join('') || '<div class="list-group-item text-muted">Sin notificaciones.</div>';

  document.querySelectorAll('#lista [data-id]').forEach(el => el.addEventListener('click', async () => {
    await Api.post('marcarNotificacionLeida', { id: el.dataset.id });
    el.classList.remove('notif-no-leida');
    el.querySelector('.badge')?.remove();
  }));
}

document.getElementById('btnMarcarTodas').addEventListener('click', async () => {
  await Api.post('marcarNotificacionLeida', {});
  cargar();
});

cargar();
</script>
</body>
</html>
