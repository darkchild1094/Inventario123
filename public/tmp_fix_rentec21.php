<?php
// Script temporal: corrige los retirados del RENTEC-0021 que quedaron
// mal etiquetados como "pendiente por instalar" (ver detalle_recibidos
// en ProyectoRentec::obtenerDetalle — cuenta cualquier activo con
// proyecto_rentec_id=21 y status='en_bodega', sin distinguir si es
// equipo NUEVO esperando instalarse o equipo VIEJO ya retirado ahí).
// Les quita la etiqueta del proyecto; su historial de retiro (tabla
// movimiento) no se toca, así que el Excel/retirados_sin_reemplazo
// sigue mostrando todo igual.
require __DIR__ . '/../vendor/autoload.php';
$db = (new \App\Config\Database())->getConnection();

$sel = $db->prepare("SELECT id, status FROM activo WHERE proyecto_rentec_id = 21 AND status != 'en_uso'");
$sel->execute();
$rows = $sel->fetchAll(PDO::FETCH_ASSOC);
echo "Candidatos a destaggear: " . count($rows) . "\n";

$upd = $db->prepare("UPDATE activo SET proyecto_rentec_id = NULL WHERE proyecto_rentec_id = 21 AND status != 'en_uso'");
$upd->execute();
echo "Filas actualizadas: " . $upd->rowCount() . "\n";

$chk = $db->query("SELECT status, COUNT(*) n FROM activo WHERE proyecto_rentec_id = 21 GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
echo "Estado final del proyecto 21:\n";
print_r($chk);
