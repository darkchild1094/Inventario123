<?php
$nombre = $_SESSION['usuario']['nombre'] ?? 'Usuario';
$tipo   = $_SESSION['usuario']['tipo'] ?? '';
$plaza  = $_SESSION['usuario']['plaza_nombre'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inicio - Inventario 123</title>
<?php include ROOT_PATH . '/app/views/components/favicon.php'; ?>
<style>
  .kpi { border: 0; border-radius: 14px; text-decoration: none; display: block; color: inherit; transition: transform .12s, box-shadow .12s; }
  .kpi:hover { transform: translateY(-2px); box-shadow: 0 .5rem 1.2rem rgba(0,0,0,.1); }
  .kpi .num { font-size: 1.9rem; font-weight: 700; line-height: 1; }
  .kpi .lbl { font-size: .8rem; opacity: .85; }
  .kpi .ico { font-size: 1.6rem; opacity: .35; }
  .card-soft { border: 0; border-radius: 14px; box-shadow: 0 .25rem .75rem rgba(0,0,0,.05); }
  .bar-track { background: #e9ecef; border-radius: 6px; height: 10px; overflow: hidden; }
  .bar-fill { height: 100%; border-radius: 6px; background: #0d6efd; }
  .mov-row { border-bottom: 1px solid #f0f0f0; padding: .55rem 0; font-size: .87rem; }
  .mov-row:last-child { border-bottom: 0; }
</style>
</head>
<body>
<?php include ROOT_PATH . '/app/views/components/navbar.php'; ?>
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="mb-4">
    <h4 class="fw-bold mb-0">Hola, <?= htmlspecialchars(explode(' ', $nombre)[0]) ?></h4>
    <div class="text-muted small"><span class="text-uppercase"><?= htmlspecialchars($tipo) ?></span><?= $plaza !== '' ? ' · ' . htmlspecialchars($plaza) : '' ?></div>
  </div>

  <div class="row g-3 mb-4" id="kpis"></div>

  <div class="row g-3 mb-4">
    <div class="col-lg-6">
      <div class="card card-soft h-100"><div class="card-body">
        <h6 class="fw-semibold mb-3">Avance RENTEC</h6>
        <div id="avanceGlobal" class="mb-3"></div>
        <div id="avancePorProyecto"></div>
      </div></div>
    </div>
    <div class="col-lg-6">
      <div class="card card-soft h-100"><div class="card-body">
        <h6 class="fw-semibold mb-3">Inventario de tiendas</h6>
        <div id="inventarioTiendas"></div>
      </div></div>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-lg-6">
      <div class="card card-soft h-100"><div class="card-body">
        <h6 class="fw-semibold mb-3">Bodega por plaza</h6>
        <div id="bodegaPlaza"></div>
      </div></div>
    </div>
    <div class="col-lg-6">
      <div class="card card-soft h-100"><div class="card-body">
        <h6 class="fw-semibold mb-3">Mi stock por tipo</h6>
        <div id="miStockTipo"></div>
      </div></div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card card-soft"><div class="card-body">
        <div class="d-flex justify-content-between mb-2"><h6 class="fw-semibold mb-0">Movimientos recientes</h6>
          <a href="index.php?controller=movimientos" class="small text-decoration-none">Ver todo</a></div>
        <div id="movsRecientes"></div>
      </div></div>
    </div>
    <div class="col-lg-4">
      <div class="card card-soft"><div class="card-body d-flex flex-column gap-2">
        <h6 class="fw-semibold mb-1">Accesos</h6>
        <a href="index.php?controller=consulta" class="btn btn-outline-secondary text-start"><i class="fas fa-barcode me-2"></i>Consulta</a>
        <a href="index.php?controller=usuario" class="btn btn-outline-secondary text-start d-none" id="btnUsuarios"><i class="fas fa-users-cog me-2"></i>Usuarios</a>
      </div></div>
    </div>
  </div>
</div>

<script>
const ETIQUETA_MODULO = { mi_stock: 'Mi stock', bodega: 'Bodega', tiendas: 'Tiendas', stock_pfs: 'Stock PFS' };
function bar(n, max) { return `<div class="d-flex align-items-center gap-2 mb-2"><div class="small flex-shrink-0" style="width:7rem">${UI.esc(n.etiqueta)}</div>
  <div class="bar-track flex-grow-1"><div class="bar-fill" style="width:${max ? Math.round(n.n/max*100) : 0}%"></div></div>
  <div class="small text-muted" style="width:2rem;text-align:right">${n.n}</div></div>`; }

async function cargar() {
  const [r, rentec, perfil] = await Promise.all([
    Api.get('resumenDashboard'), Api.get('rentecListar'), Api.get('obtenerPerfil'),
  ]);
  if (perfil.usuario && perfil.usuario.tipo === 'admin') document.getElementById('btnUsuarios').classList.remove('d-none');

  const kpis = [];
  if ('mi_stock' in r.por_modulo) kpis.push({ ico: 'fa-toolbox', cls: 'primary', lbl: 'Mi stock', val: r.por_modulo.mi_stock, href: 'mistock' });
  if ('bodega' in r.por_modulo) kpis.push({ ico: 'fa-warehouse', cls: 'success', lbl: 'Bodega total', val: r.por_modulo.bodega, href: 'bodega' });
  if ('tiendas' in r.por_modulo) kpis.push({ ico: 'fa-store', cls: 'secondary', lbl: 'Activos en tiendas', val: r.por_modulo.tiendas, href: 'tienda' });
  const abiertos = (rentec || []).filter(p => p.estado === 'abierto').length;
  const finalizados = (rentec || []).filter(p => p.estado === 'cerrado').length;
  kpis.push({ ico: 'fa-arrows-rotate', cls: 'warning text-dark', lbl: 'RENTEC abiertos/finalizados', val: `${abiertos}/${finalizados}`, href: 'rentec' });
  document.getElementById('kpis').innerHTML = kpis.map(k => `
    <div class="col-6 col-lg-3"><a class="kpi card" href="index.php?controller=${k.href}">
      <div class="card-body d-flex justify-content-between align-items-center">
        <div><div class="num">${k.val}</div><div class="lbl">${UI.esc(k.lbl)}</div></div>
        <i class="fas ${k.ico} ico text-${k.cls}"></i>
      </div></a></div>`).join('');

  const abiertosP = (rentec || []).filter(p => p.estado === 'abierto');
  const totInst = abiertosP.reduce((a,p) => a + (parseInt(p.instalados)||0), 0);
  const totTotal = abiertosP.reduce((a,p) => a + (parseInt(p.instalados)||0) + (parseInt(p.recibidos)||0), 0);
  const pctGlobal = totTotal ? Math.round(totInst/totTotal*100) : 0;
  document.getElementById('avanceGlobal').innerHTML = `<div class="d-flex align-items-center gap-2">
    <div class="bar-track flex-grow-1"><div class="bar-fill" style="width:${pctGlobal}%"></div></div>
    <small class="text-muted">${totInst}/${totTotal} (${pctGlobal}%)</small></div>`;
  document.getElementById('avancePorProyecto').innerHTML = abiertosP.map(p => {
    const t = (parseInt(p.instalados)||0) + (parseInt(p.recibidos)||0);
    const pct = t ? Math.round(p.instalados/t*100) : 0;
    return `<a href="index.php?controller=rentec&action=detalle&id=${p.id}" class="d-block text-decoration-none text-dark small mb-1">
      ${UI.esc(p.folio)} <span class="text-muted">${pct}%</span>
      <div class="bar-track"><div class="bar-fill" style="width:${pct}%"></div></div></a>`;
  }).join('') || '<div class="text-muted small">Sin proyectos abiertos.</div>';

  document.getElementById('inventarioTiendas').innerHTML = `
    <div class="d-flex align-items-center gap-2 mb-1">
      <div class="bar-track flex-grow-1"><div class="bar-fill" style="width:${r.tiendas_total ? Math.round(r.tiendas_con_inventario/r.tiendas_total*100) : 0}%"></div></div>
      <small class="text-muted">${r.tiendas_con_inventario}/${r.tiendas_total}</small>
    </div><div class="text-muted small">tiendas con checklist cerrado</div>`;

  const bp = (r.bodega_por_plaza || []).map(x => ({ etiqueta: x.plaza_nombre || x.nombre, n: x.n }));
  const maxBp = Math.max(1, ...bp.map(x => x.n));
  document.getElementById('bodegaPlaza').innerHTML = bp.map(x => bar(x, maxBp)).join('') || '<div class="text-muted small">—</div>';

  const mt = (r.mi_stock_por_tipo || []).map(x => ({ etiqueta: x.dispositivo_nombre || x.nombre, n: x.n }));
  const maxMt = Math.max(1, ...mt.map(x => x.n));
  document.getElementById('miStockTipo').innerHTML = mt.map(x => bar(x, maxMt)).join('') || '<div class="text-muted small">—</div>';

  document.getElementById('movsRecientes').innerHTML = (r.movimientos || []).map(m => `
    <div class="mov-row d-flex justify-content-between">
      <span>${UI.esc(m.equipo || '—')} <span class="text-muted font-monospace small">${UI.esc(m.serie||'')}</span></span>
      <span class="text-muted small">${UI.esc((m.creado_en||'').replace('T',' '))}</span>
    </div>`).join('') || '<div class="text-muted small">Sin movimientos recientes.</div>';
}
cargar();
</script>
</body>
</html>
