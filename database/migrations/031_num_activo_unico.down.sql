-- Revierte 031. No toca datos: sólo retira la restricción.

ALTER TABLE `activo`
  DROP INDEX `uq_activo_num_activo`;
