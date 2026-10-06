<?php

namespace App\Controllers;

use App\Models\AppBuild;
use App\Helpers\Permisos;

/**
 * ApkController — subir y repartir el APK de la app Android. Solo admin.
 *
 * El archivo se guarda en storage/apks/ (fuera de public/, que solo sirve
 * imágenes — ver public/index.php) y se entrega por `descargar()`, que SÍ es
 * pública: un técnico la abre desde el celular, sin cuenta en el panel web,
 * para instalar o actualizar la app.
 */
class ApkController
{
    private $db;
    private AuthController $auth;

    private const DIR_APKS = ROOT_PATH . '/storage/apks';

    public function __construct($db)
    {
        $this->db   = $db;
        $this->auth = new AuthController($db);
        // La autenticación NO se exige aquí: descargar() es pública (ver
        // rutasPublicas en public/index.php) — un técnico la abre desde el
        // celular sin cuenta en el panel. Las demás acciones la piden ellas
        // mismas, antes de soloAdmin().
    }

    public function index(): void
    {
        $this->auth->requerirAutenticacion();
        $this->soloAdmin();
        $builds = (new AppBuild($this->db))->listar();
        require ROOT_PATH . '/app/views/apk/index.php';
    }

    public function subir(): void
    {
        $this->auth->requerirAutenticacion();
        $this->soloAdmin();
        $this->requerirPost();

        $versionName = trim($_POST['version_name'] ?? '');
        $versionCode = (int) ($_POST['version_code'] ?? 0);
        $notas       = trim($_POST['notas'] ?? '');

        if ($versionName === '') $this->error('Falta el nombre de versión (ej. 1.6.0).');
        if ($versionCode <= 0)   $this->error('Falta el versionCode (ej. 8), debe ser mayor a 0.');

        $archivo = $_FILES['apk'] ?? null;
        if (!$archivo || $archivo['error'] !== UPLOAD_ERR_OK) {
            $this->error('No se recibió el archivo APK (o falló la subida).');
        }
        if (strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION)) !== 'apk') {
            $this->error('El archivo debe tener extensión .apk.');
        }

        if (!is_dir(self::DIR_APKS)) {
            mkdir(self::DIR_APKS, 0770, true);
        }

        // Nombre fijo por versión; si se vuelve a subir la misma, se sobreescribe.
        $nombreArchivo = "inventario123-{$versionName}-{$versionCode}.apk";
        $destino       = self::DIR_APKS . '/' . $nombreArchivo;

        if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
            $this->error('No se pudo guardar el archivo en el servidor.');
        }

        (new AppBuild($this->db))->crear([
            'version_code'  => $versionCode,
            'version_name'  => $versionName,
            'archivo'       => $nombreArchivo,
            'tamano_bytes'  => filesize($destino),
            'notas'         => $notas,
            'subido_por'    => Permisos::idUsuario(),
        ]);

        $_SESSION['success'] = "APK {$versionName} (versionCode {$versionCode}) subida correctamente.";
        $this->redirigir('index.php?controller=apk&action=index');
    }

    public function eliminar(): void
    {
        $this->auth->requerirAutenticacion();
        $this->soloAdmin();
        $this->requerirPost();

        $id    = (int) ($_POST['id'] ?? 0);
        $model = new AppBuild($this->db);
        $build = $id > 0 ? $model->obtenerPorId($id) : false;
        if (!$build) $this->error('Versión no encontrada.');

        $ruta = self::DIR_APKS . '/' . $build['archivo'];
        if (is_file($ruta)) @unlink($ruta);
        $model->eliminar($id);

        $_SESSION['success'] = "Versión {$build['version_name']} eliminada.";
        $this->redirigir('index.php?controller=apk&action=index');
    }

    /**
     * Descarga pública (sin requerir sesión web — ver rutasPublicas en
     * public/index.php). `id` descarga esa versión puntual; sin `id` descarga
     * siempre la más reciente, para poder compartir un enlace fijo.
     */
    public function descargar(): void
    {
        $model = new AppBuild($this->db);
        $id    = (int) ($_GET['id'] ?? 0);
        $build = $id > 0 ? $model->obtenerPorId($id) : $model->ultima();

        if (!$build) {
            http_response_code(404);
            echo 'No hay ninguna versión disponible.';
            return;
        }

        $ruta = realpath(self::DIR_APKS . '/' . $build['archivo']);
        $dir  = realpath(self::DIR_APKS);
        if ($ruta === false || $dir === false || !str_starts_with($ruta, $dir . DIRECTORY_SEPARATOR) || !is_file($ruta)) {
            http_response_code(404);
            echo 'Archivo no encontrado en el servidor.';
            return;
        }

        header('Content-Type: application/vnd.android.package-archive');
        header('Content-Disposition: attachment; filename="' . basename($ruta) . '"');
        header('Content-Length: ' . filesize($ruta));
        header('Cache-Control: no-cache');
        readfile($ruta);
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function soloAdmin(): void
    {
        if (!Permisos::esAdmin()) {
            $_SESSION['error'] = 'Acceso restringido a administradores.';
            $this->redirigir('index.php');
        }
    }

    private function requerirPost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirigir('index.php?controller=apk&action=index');
        }
    }

    private function error(string $msg): never
    {
        $_SESSION['error'] = $msg;
        $this->redirigir('index.php?controller=apk&action=index');
    }

    private function redirigir(string $url): never
    {
        header("Location: {$url}");
        exit;
    }
}
