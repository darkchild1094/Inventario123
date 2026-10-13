-- 037 — Fecha de inicio/fin del proyecto RENTEC, que pide el nuevo diseño
-- al crear el proyecto. Nullable: los proyectos ya existentes no tienen
-- estas fechas y no hay forma de inferirlas retroactivamente; la UI nueva
-- las exige al CREAR un proyecto, pero el backend no debe reventar al leer
-- los que ya existen sin ellas.
ALTER TABLE `proyecto_rentec`
  ADD COLUMN `fecha_inicio` date DEFAULT NULL AFTER `plaza_id`,
  ADD COLUMN `fecha_fin` date DEFAULT NULL AFTER `fecha_inicio`;
