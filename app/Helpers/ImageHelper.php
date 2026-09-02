<?php

namespace App\Helpers;

/**
 * ImageHelper — Gestión profesional de imágenes
 *
 * Características:
 *  - Subida, validación y compresión automática
 *  - Conversión a WebP para máxima compresión sin pérdida visible
 *  - Borrado físico del servidor al eliminar/reemplazar imágenes
 *  - Thumbnails para vistas de lista
 *  - Fallback JPEG/PNG si GD no soporta WebP
 */
class ImageHelper
{
    // ── Configuración global ──────────────────────────────────────────────────

    /** Extensiones aceptadas en upload */
    private const EXTENSIONES_VALIDAS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /** MIME types aceptados */
    private const MIMES_VALIDOS = [
        'image/jpeg', 'image/jpg', 'image/png',
        'image/gif',  'image/webp',
    ];

    /** Tamaño máximo por defecto: 8 MB */
    private const MAX_SIZE_DEFAULT = 8_388_608;

    /** Dimensión máxima para imágenes de activos */
    private const MAX_DIM_ACTIVO = 1024;

    /** Dimensión máxima para fotos de usuario */
    private const MAX_DIM_USUARIO = 512;

    /** Calidad JPEG/WebP para activos (0-100) */
    private const CALIDAD_ACTIVO = 78;

    /** Calidad JPEG/WebP para usuarios */
    private const CALIDAD_USUARIO = 80;

    /** Subcarpeta de thumbnails dentro de la carpeta de destino */
    private const THUMBS_DIR = 'thumbs';

    /** Tamaño de thumbnail */
    private const THUMB_W = 200;
    private const THUMB_H = 200;

    // ── API pública ───────────────────────────────────────────────────────────

    /**
     * Procesa y sube las 3 fotos de un activo (foto_equipo, foto_serie, foto_activo).
     * Si existe una imagen previa y se reemplaza, borra la antigua del disco.
     *
     * @param string   $rutaBase    Carpeta destino (sin slash final)
     * @param int|null $activoId    ID del activo (para nombrar archivos)
     * @param array    $fotosViejas ['foto_equipo' => 'nombre.jpg', ...]  Fotos actuales en BD
     * @return array   Claves foto_equipo, foto_serie, foto_activo (null si no se subió)
     */
    public static function procesarYSubirImagenes(
        string $rutaBase,
        ?int   $activoId  = null,
        array  $fotosViejas = []
    ): array {
        $campos    = ['foto_equipo', 'foto_serie', 'foto_activo'];
        $resultado = [];

        foreach ($campos as $campo) {
            // Sin archivo o el usuario no envió nada → conservar la vieja
            if (
                empty($_FILES[$campo])
                || ($_FILES[$campo]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
            ) {
                $resultado[$campo] = null; // null = "no cambiar" en el modelo
                continue;
            }

            $file      = $_FILES[$campo];
            $validacion = self::validarImagen($file);

            if (!$validacion['valido']) {
                error_log("ImageHelper [{$campo}]: " . $validacion['error']);
                $resultado[$campo] = null;
                continue;
            }

            // Borrar imagen vieja si existía
            if (!empty($fotosViejas[$campo])) {
                self::borrarArchivo($rutaBase, $fotosViejas[$campo]);
            }

            // Generar nombre único sin extensión original (salida será WebP o jpg)
            $prefijo  = $activoId ? "activo_{$activoId}_{$campo}" : "activo_{$campo}";
            $nombreFinal = $prefijo . '_' . uniqid() . self::extensionSalida();

            $rutaDest = self::rutaSegura($rutaBase) . '/' . $nombreFinal;

            if (!self::procesarImagen($file['tmp_name'], $rutaDest, self::CALIDAD_ACTIVO, self::MAX_DIM_ACTIVO)) {
                error_log("ImageHelper [{$campo}]: Falló al procesar → {$rutaDest}");
                $resultado[$campo] = null;
                continue;
            }

            // Crear thumbnail automáticamente
            self::generarThumbnail($rutaDest, $rutaBase, $nombreFinal);

            $resultado[$campo] = $nombreFinal;
        }

        return $resultado;
    }

    /**
     * Sube la foto de perfil de un usuario.
     * Borra la foto vieja si se reemplaza.
     *
     * @param string   $rutaBase   Carpeta destino
     * @param int|null $usuarioId  ID del usuario
     * @param string|null $fotoVieja  Nombre de la foto actual en BD
     * @return string|null  Nombre del archivo guardado, o null si falló
     */
    public static function subirFotoUsuario(
        string  $rutaBase,
        ?int    $usuarioId = null,
        ?string $fotoVieja = null
    ): ?string {
        $file = $_FILES['foto'] ?? null;

        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $validacion = self::validarImagen($file);
        if (!$validacion['valido']) {
            error_log("ImageHelper [foto_usuario]: " . $validacion['error']);
            return null;
        }

        // Borrar foto vieja
        if ($fotoVieja) {
            self::borrarArchivo($rutaBase, $fotoVieja);
        }

        $prefijo     = $usuarioId ? "usuario_{$usuarioId}" : 'usuario';
        $nombreFinal = $prefijo . '_' . uniqid() . self::extensionSalida();
        $rutaDest    = self::rutaSegura($rutaBase) . '/' . $nombreFinal;

        if (!self::procesarImagen($file['tmp_name'], $rutaDest, self::CALIDAD_USUARIO, self::MAX_DIM_USUARIO)) {
            error_log("ImageHelper [foto_usuario]: Falló al procesar → {$rutaDest}");
            return null;
        }

        return $nombreFinal;
    }

    /**
     * Borra una imagen (y su thumbnail) del servidor.
     *
     * @param string $rutaBase  Carpeta base de uploads
     * @param string $nombreArchivo  Solo el nombre del archivo (sin ruta)
     * @return bool
     */
    public static function borrarArchivo(string $rutaBase, string $nombreArchivo): bool
    {
        if (empty($nombreArchivo) || in_array($nombreArchivo, ['default.png', ''], true)) {
            return false;
        }

        $rutaBase    = self::rutaSegura($rutaBase);
        $rutaFull    = $rutaBase . '/' . basename($nombreArchivo);
        $rutaThumb   = $rutaBase . '/' . self::THUMBS_DIR . '/' . basename($nombreArchivo);

        $ok = false;
        if (file_exists($rutaFull)) {
            $ok = unlink($rutaFull);
        }
        if (file_exists($rutaThumb)) {
            unlink($rutaThumb);
        }

        return $ok;
    }

    /**
     * Borra múltiples imágenes de activo a la vez (las 3 fotos).
     *
     * @param string $rutaBase
     * @param array  $fotos  ['foto_equipo' => 'nombre.jpg', ...]
     */
    public static function borrarFotosActivo(string $rutaBase, array $fotos): void
    {
        foreach (['foto_equipo', 'foto_serie', 'foto_activo'] as $campo) {
            if (!empty($fotos[$campo])) {
                self::borrarArchivo($rutaBase, $fotos[$campo]);
            }
        }
    }

    /**
     * Valida que un array de $_FILES sea una imagen aceptable.
     *
     * @param array $file      Elemento de $_FILES
     * @param int   $maxSize   Tamaño máximo en bytes
     * @return array ['valido' => bool, 'error' => string|null]
     */
    public static function validarImagen(array $file, int $maxSize = self::MAX_SIZE_DEFAULT): array
    {
        // Error de subida de PHP
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errores = [
                UPLOAD_ERR_INI_SIZE   => 'El archivo supera el límite del servidor (php.ini).',
                UPLOAD_ERR_FORM_SIZE  => 'El archivo supera el límite del formulario.',
                UPLOAD_ERR_PARTIAL    => 'El archivo se subió de forma incompleta.',
                UPLOAD_ERR_NO_FILE    => 'No se seleccionó ningún archivo.',
                UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal en el servidor.',
                UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo en disco.',
                UPLOAD_ERR_EXTENSION  => 'Una extensión de PHP bloqueó la subida.',
            ];
            return [
                'valido' => false,
                'error'  => $errores[$file['error']] ?? "Error de subida desconocido (código {$file['error']}).",
            ];
        }

        // Tamaño
        if ($file['size'] > $maxSize) {
            $mb = number_format($maxSize / 1_048_576, 1);
            return ['valido' => false, 'error' => "El archivo supera el máximo permitido ({$mb} MB)."];
        }

        // Extensión
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::EXTENSIONES_VALIDAS, true)) {
            $lista = implode(', ', array_map('strtoupper', self::EXTENSIONES_VALIDAS));
            return ['valido' => false, 'error' => "Solo se permiten imágenes: {$lista}."];
        }

        // MIME real del archivo (no el declarado por el cliente)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, self::MIMES_VALIDOS, true)) {
            return ['valido' => false, 'error' => 'El archivo no es una imagen válida o su contenido es sospechoso.'];
        }

        return ['valido' => true, 'error' => null];
    }

    // ── Internos ──────────────────────────────────────────────────────────────

    /**
     * Procesa una imagen temporal: redimensiona si excede maxDim y guarda
     * en WebP (o JPEG si WebP no está disponible).
     */
    private static function procesarImagen(
        string $tmpPath,
        string $destino,
        int    $calidad,
        int    $maxDim
    ): bool {
        if (!extension_loaded('gd')) {
            // Sin GD: mover tal cual sin comprimir
            return move_uploaded_file($tmpPath, $destino)
                ?: copy($tmpPath, $destino);
        }

        $info = @getimagesize($tmpPath);
        if (!$info) {
            return false;
        }

        $mime  = $info['mime'];
        $imagen = match ($mime) {
            'image/jpeg'  => @imagecreatefromjpeg($tmpPath),
            'image/png'   => @imagecreatefrompng($tmpPath),
            'image/gif'   => @imagecreatefromgif($tmpPath),
            'image/webp'  => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmpPath) : false,
            default       => false,
        };

        if (!$imagen) {
            return false;
        }

        // Preservar transparencia PNG/GIF
        if (in_array($mime, ['image/png', 'image/gif'], true)) {
            $imagen = self::preservarTransparencia($imagen);
        }

        // Redimensionar si es necesario
        [$w, $h] = [imagesx($imagen), imagesy($imagen)];
        if ($w > $maxDim || $h > $maxDim) {
            $imagen = self::redimensionar($imagen, $w, $h, $maxDim);
        }

        // Asegurar directorio
        $dir = dirname($destino);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        // Guardar en WebP si está disponible, si no en JPEG
        $ok = self::guardarOptimizado($imagen, $destino, $calidad);
        imagedestroy($imagen);

        return $ok;
    }

    /** Redimensiona manteniendo proporción */
    private static function redimensionar($src, int $w, int $h, int $maxDim)
    {
        if ($w >= $h) {
            $nw = $maxDim;
            $nh = (int) round($h * $maxDim / $w);
        } else {
            $nh = $maxDim;
            $nw = (int) round($w * $maxDim / $h);
        }

        $dst = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);
        return $dst;
    }

    /** Preserva canal alfa para PNG/GIF */
    private static function preservarTransparencia($imagen)
    {
        $w = imagesx($imagen);
        $h = imagesy($imagen);
        $nueva = imagecreatetruecolor($w, $h);
        imagealphablending($nueva, false);
        imagesavealpha($nueva, true);
        $transparente = imagecolorallocatealpha($nueva, 255, 255, 255, 127);
        imagefilledrectangle($nueva, 0, 0, $w, $h, $transparente);
        imagecopy($nueva, $imagen, 0, 0, 0, 0, $w, $h);
        imagedestroy($imagen);
        return $nueva;
    }

    /**
     * Guarda en WebP si GD lo soporta; si no, en JPEG.
     * La extensión del archivo de destino se ignora: se escribe el formato óptimo.
     */
    private static function guardarOptimizado($imagen, string $destino, int $calidad): bool
    {
        if (function_exists('imagewebp')) {
            return imagewebp($imagen, $destino, $calidad);
        }
        // Fallback JPEG
        return imagejpeg($imagen, $destino, $calidad);
    }

    /** Devuelve '.webp' si GD lo soporta, si no '.jpg' */
    private static function extensionSalida(): string
    {
        return function_exists('imagewebp') ? '.webp' : '.jpg';
    }

    /** Genera thumbnail y lo guarda en {rutaBase}/thumbs/ */
    private static function generarThumbnail(string $rutaImagen, string $rutaBase, string $nombreFinal): void
    {
        $dirThumbs = self::rutaSegura($rutaBase) . '/' . self::THUMBS_DIR;
        if (!is_dir($dirThumbs)) {
            mkdir($dirThumbs, 0775, true);
        }

        $info = @getimagesize($rutaImagen);
        if (!$info) {
            return;
        }

        $mime = $info['mime'];
        $src  = match ($mime) {
            'image/jpeg'  => @imagecreatefromjpeg($rutaImagen),
            'image/png'   => @imagecreatefrompng($rutaImagen),
            'image/gif'   => @imagecreatefromgif($rutaImagen),
            'image/webp'  => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($rutaImagen) : false,
            default       => false,
        };

        if (!$src) {
            return;
        }

        [$w, $h] = [imagesx($src), imagesy($src)];

        // Crop cuadrado centrado
        $lado = min($w, $h);
        $x    = (int)(($w - $lado) / 2);
        $y    = (int)(($h - $lado) / 2);

        $thumb = imagecreatetruecolor(self::THUMB_W, self::THUMB_H);
        imagecopyresampled($thumb, $src, 0, 0, $x, $y, self::THUMB_W, self::THUMB_H, $lado, $lado);

        $destThumb = $dirThumbs . '/' . $nombreFinal;

        if (function_exists('imagewebp')) {
            imagewebp($thumb, $destThumb, 75);
        } else {
            imagejpeg($thumb, $destThumb, 75);
        }

        imagedestroy($src);
        imagedestroy($thumb);
    }

    /** Normaliza ruta quitando slash final */
    private static function rutaSegura(string $ruta): string
    {
        return rtrim($ruta, '/\\');
    }
}