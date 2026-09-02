<?php
// Script TEMPORAL: muestra la config real de OPcache tal como la ve Apache/web.
echo "SAPI: " . php_sapi_name() . "\n";
echo "PHP version: " . phpversion() . "\n\n";

echo "opcache.enable: " . ini_get('opcache.enable') . "\n";
echo "opcache.validate_timestamps: " . ini_get('opcache.validate_timestamps') . "\n";
echo "opcache.revalidate_freq: " . ini_get('opcache.revalidate_freq') . "\n";
echo "opcache.file_cache: " . ini_get('opcache.file_cache') . "\n";
echo "opcache.max_accelerated_files: " . ini_get('opcache.max_accelerated_files') . "\n\n";

if (function_exists('opcache_get_status')) {
    $status = opcache_get_status(true);
    if ($status !== false) {
        echo "Total de scripts en cache: " . count($status['scripts'] ?? []) . "\n\n";
        foreach (($status['scripts'] ?? []) as $ruta => $info) {
            if (str_contains($ruta, 'ApiController.php')) {
                echo "ENCONTRADO EN CACHE: $ruta\n";
                echo "  Timestamp cacheado: " . date('Y-m-d H:i:s', $info['timestamp']) . "\n";
                echo "  Hits: " . $info['hits'] . "\n";
                echo "  Ultima modificacion real del archivo: " . date('Y-m-d H:i:s', filemtime($ruta)) . "\n";
            }
        }
    } else {
        echo "opcache_get_status() = false\n";
    }
} else {
    echo "opcache_get_status no existe\n";
}
