<?php
// Script TEMPORAL: limpia el cache de opcode de PHP (OPcache).
// Bórralo del servidor en cuanto termines de usarlo.

if (function_exists('opcache_reset')) {
    $ok = opcache_reset();
    echo $ok ? "OPcache reiniciado correctamente." : "opcache_reset() devolvio false (revisa permisos).";
} else {
    echo "OPcache no esta disponible/habilitado en este servidor.";
}

echo "\n\nHora del servidor: " . date('Y-m-d H:i:s') . "\n";
echo "Ultima modificacion de ApiController.php: " . date('Y-m-d H:i:s', filemtime(__DIR__ . '/../app/controllers/ApiController.php')) . "\n";

if (function_exists('opcache_get_status')) {
    $estado = opcache_get_status(false);
    if ($estado !== false) {
        echo "\nOPcache habilitado: SI\n";
        echo "Hits: " . ($estado['opcache_statistics']['hits'] ?? '?') . "\n";
        echo "Misses: " . ($estado['opcache_statistics']['misses'] ?? '?') . "\n";
    } else {
        echo "\nOPcache no esta activo (status = false).\n";
    }
}
