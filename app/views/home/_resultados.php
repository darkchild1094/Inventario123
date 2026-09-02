<?php
/**
 * Parcial: grid de activos + paginación.
 * Se usa tanto en la carga normal de index.php como en las respuestas
 * AJAX de búsqueda/filtrado en tiempo real (sin recargar la página).
 * Variables esperadas: $activos, $paginacion, $vista, $busqueda,
 * $negocio_id, $region_id, $plaza_id, $usuario_id, $status.
 */
?>
    <!-- Grid de Activos -->
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-4 g-4">

        <?php if (!empty($activos)): ?>
            <?php foreach ($activos as $a): ?>
                <div class="col">
                    <div class="card h-100 shadow-sm border-0 hover-shadow transition-all activo-card" style="cursor:pointer;" data-activo-id="<?= $a['id'] ?>">

                        <?php
                        $statusClase = match($a['status'] ?? '') {
                            'en_bodega' => 'secondary',
                            'en_uso'    => 'success',
                            'baja'      => 'danger',
                            'garantia'  => 'warning text-dark',
                            'asignado'  => 'primary',
                            default     => 'dark',
                        };
                        $statusLabel = match($a['status'] ?? '') {
                            'en_bodega' => 'En Bodega',
                            'en_uso'    => 'En Uso',
                            'baja'      => 'Baja',
                            'garantia'  => 'Garantía',
                            'asignado'  => 'Asignado',
                            default     => 'Desconocido',
                        };
                        ?>

                        <!-- Cuerpo -->
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <span class="badge bg-light text-dark border me-1">
                                        <i class="fas fa-building text-primary me-1"></i>
                                        <?= htmlspecialchars($a['negocio_nombre'] ?? 'N/A') ?>
                                    </span>
                                    <span class="badge bg-light text-dark border">
                                        <i class="fas fa-map-marker-alt text-danger me-1"></i>
                                        <?= htmlspecialchars($a['plaza_nombre'] ?? 'N/A') ?>
                                    </span>
                                </div>
                                <span class="badge rounded-pill bg-<?= $statusClase ?> shadow-sm">
                                    <?= $statusLabel ?>
                                </span>
                            </div>

                            <h5 class="card-title fw-bold text-dark mb-1 text-truncate"
                                title="<?= htmlspecialchars($a['dispositivo_nombre'] ?? '') ?>">
                                <?= htmlspecialchars($a['dispositivo_nombre'] ?? 'Equipo') ?>
                            </h5>
                            <p class="text-muted small mb-3">
                                <?= htmlspecialchars($a['modelo_nombre'] ?? 'Sin modelo') ?>
                            </p>

                            <div class="mt-auto">
                                <div class="d-flex justify-content-between small text-muted mb-1">
                                    <span><i class="fas fa-barcode me-1"></i>Serie:</span>
                                    <span class="fw-bold text-dark text-truncate" style="max-width:130px;"
                                          title="<?= htmlspecialchars($a['serie'] ?? '') ?>">
                                        <?= htmlspecialchars($a['serie'] ?? '—') ?>
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between small text-muted mb-1">
                                    <span><i class="fas fa-tag me-1"></i>Placa:</span>
                                    <span class="fw-bold text-dark">
                                        <?= htmlspecialchars($a['placa'] ?? '—') ?>
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between small text-muted">
                                    <span><i class="fas fa-store me-1"></i>
                                        <?= $a['stock_tipo'] === 'usuario' ? 'Técnico:' : 'Bodega:' ?>
                                    </span>
                                    <span class="fw-bold text-dark text-truncate" style="max-width:130px;">
                                        <?= htmlspecialchars(
                                            $a['stock_tipo'] === 'usuario'
                                                ? ($a['usuario_nombre'] ?? '—')
                                                : ($a['bodega_nombre']  ?? '—')
                                        ) ?>
                                    </span>
                                </div>
                                <?php if (!empty($a['tienda_uso_nombre'])): ?>
                                    <div class="d-flex justify-content-between small text-muted mt-1">
                                        <span><i class="fas fa-map-pin me-1"></i>En uso en:</span>
                                        <span class="fw-bold text-success text-truncate" style="max-width:120px;">
                                            <?= htmlspecialchars($a['tienda_uso_nombre']) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="card-footer bg-white border-top-0 pt-0 pb-3 d-flex justify-content-between align-items-center" data-no-modal>
                            <small class="text-muted">ID: #<?= str_pad($a['id'], 4, '0', STR_PAD_LEFT) ?></small>

                            <?php
                                $puedeEditar   = \App\Helpers\Permisos::puedeEditarActivoConcreto($a);
                                $puedeEliminar = \App\Helpers\Permisos::puedeEliminarActivo($a);
                            ?>
                            <?php if ($puedeEditar || $puedeEliminar): ?>
                                <div class="btn-group">
                                    <?php if ($puedeEditar): ?>
                                        <a href="index.php?action=editar&id=<?= $a['id'] ?>"
                                           class="btn btn-sm btn-outline-primary <?= !$puedeEliminar ? '' : 'rounded-start' ?>" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($puedeEliminar): ?>
                                        <form action="index.php?action=eliminar" method="POST" class="d-inline"
                                              onsubmit="return confirm('¿Eliminar este activo permanentemente?');">
                                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                            <button type="submit"
                                                    class="btn btn-sm btn-outline-danger <?= !$puedeEditar ? '' : 'rounded-end border-start-0' ?>"
                                                    title="Eliminar">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>

        <?php else: ?>
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="fas fa-warehouse fa-3x text-muted opacity-50 mb-3"></i>
                    <h5 class="text-muted">No hay activos</h5>
                    <p class="text-muted small">
                        <?php if (!empty($busqueda) || !empty($negocio_id) || !empty($region_id) || !empty($plaza_id) || !empty($usuario_id) || !empty($status)): ?>
                            No se encontraron resultados con los filtros aplicados.
                            <a href="index.php?vista=<?= htmlspecialchars($vista) ?>" class="text-decoration-none">Limpiar filtros</a>
                        <?php else: ?>
                            Aún no hay activos registrados.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <!-- Paginación -->
    <?php if (isset($paginacion) && $paginacion['total_paginas'] > 1): ?>
        <nav aria-label="Paginación" class="mt-5">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= $paginacion['pagina_actual'] <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="index.php?<?= http_build_query(array_merge($_GET, ['pagina' => $paginacion['pagina_actual'] - 1])) ?>">&laquo;</a>
                </li>
                <?php
                $actual = $paginacion['pagina_actual'];
                $total  = $paginacion['total_paginas'];
                $inicio = max(1, $actual - 3);
                $fin    = min($total, $actual + 3);
                ?>
                <?php if ($inicio > 1): ?>
                    <li class="page-item"><a class="page-link" href="index.php?<?= http_build_query(array_merge($_GET, ['pagina' => 1])) ?>">1</a></li>
                    <?php if ($inicio > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                <?php endif; ?>
                <?php for ($i = $inicio; $i <= $fin; $i++): ?>
                    <li class="page-item <?= $i === $actual ? 'active' : '' ?>">
                        <a class="page-link" href="index.php?<?= http_build_query(array_merge($_GET, ['pagina' => $i])) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <?php if ($fin < $total): ?>
                    <?php if ($fin < $total - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                    <li class="page-item"><a class="page-link" href="index.php?<?= http_build_query(array_merge($_GET, ['pagina' => $total])) ?>"><?= $total ?></a></li>
                <?php endif; ?>
                <li class="page-item <?= $paginacion['pagina_actual'] >= $paginacion['total_paginas'] ? 'disabled' : '' ?>">
                    <a class="page-link" href="index.php?<?= http_build_query(array_merge($_GET, ['pagina' => $paginacion['pagina_actual'] + 1])) ?>">&raquo;</a>
                </li>
            </ul>
            <p class="text-center text-muted small mt-1">
                Página <?= $actual ?> de <?= $total ?> &nbsp;·&nbsp;
                <?= number_format($paginacion['total_resultados']) ?> resultado<?= $paginacion['total_resultados'] !== 1 ? 's' : '' ?>
            </p>
        </nav>
    <?php endif; ?>