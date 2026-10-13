-- 034 — CR de tienda único, requisito del rediseño de "Tiendas" (CRUD completo,
-- hoy el modelo solo tiene lectura + asignar ATI). Verificado antes de aplicar:
-- 0 duplicados y 0 vacíos en los 6 negocios/plazas actuales.
ALTER TABLE `tienda`
  ADD UNIQUE KEY `uq_tienda_cr` (`cr_tienda`);
