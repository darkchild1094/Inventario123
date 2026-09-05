<?php
session_name('INV123_SESSID');

// Soporte para sesión vía Header (móvil) o Cookie (web) o Query Param (debug/móvil)
if (isset($_SERVER['HTTP_X_SESSION_ID'])) {
    session_id($_SERVER['HTTP_X_SESSION_ID']);
} elseif (isset($_GET['sid'])) {
    session_id($_GET['sid']);
}

session_start();
header("X-Debug-Session-Used: " . session_id());

ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('pcre.jit', 0);

date_default_timezone_set('America/Mexico_City');

define('ROOT_PATH', dirname(__DIR__));

// ── Autoload ──────────────────────────────────────────────────────────────────

$composerAutoload = ROOT_PATH . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
} else {
    spl_autoload_register(function ($class) {
        // El prefijo más específico primero (igual que hace Composer).
        $maps = [
            'App\\Config\\' => ROOT_PATH . '/config/',
            'App\\'         => ROOT_PATH . '/app/',
            'Config\\'      => ROOT_PATH . '/config/',
        ];
        foreach ($maps as $prefix => $base_dir) {
            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) continue;
            $relative_class = substr($class, $len);
            $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
            if (file_exists($file)) require $file;
            return;
        }
    });
}

// ── Conexión BD ───────────────────────────────────────────────────────────────


try {
    $database = new \App\Config\Database();
    $db       = $database->getConnection();
    if (!$db) throw new Exception('Error de conexión a la base de datos.');
} catch (Exception $e) {
    if (($_GET['controller'] ?? '') === 'api') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
    die('Error crítico: ' . $e->getMessage());
}

// ── Servir uploads ────────────────────────────────────────────────────────────

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (strpos($requestUri, '/uploads/') !== false) {
    $filePath = ROOT_PATH . '/public' . $requestUri;
    if (file_exists($filePath)) {
        $ext  = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
        header('Content-Type: '   . ($mime[$ext] ?? 'image/jpeg'));
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: public, max-age=86400');
        readfile($filePath);
        exit;
    }
    http_response_code(404);
    exit;
}

// ── Auth ──────────────────────────────────────────────────────────────────────


$authController = new \App\Controllers\AuthController($db);

// ── Routing ───────────────────────────────────────────────────────────────────

$controllerName = $_GET['controller'] ?? 'home';
$action         = $_GET['action']     ?? 'index';

$rutasPublicas = [
    'auth' => ['mostrarLogin', 'login', 'logout'],
    'api'  => ['login'],
];

$requiereAuth = !(
    isset($rutasPublicas[$controllerName]) &&
    in_array($action, $rutasPublicas[$controllerName])
);

if ($requiereAuth && !$authController->estaAutenticado()) {
    $esPeticionApp = $controllerName === 'api' || isset($_SERVER['HTTP_X_SESSION_ID']);
    if ($esPeticionApp) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No autorizado.']);
        exit;
    }
    header('Location: index.php?controller=auth&action=mostrarLogin');
    exit;
}

// ── Controladores ─────────────────────────────────────────────────────────────

switch ($controllerName) {

    case 'auth':
        if (method_exists($authController, $action)) {
            $authController->$action();
        } else {
            header('Location: index.php?controller=auth&action=mostrarLogin');
        }
        exit;

    case 'api':
        header('Content-Type: application/json; charset=UTF-8');
        $controller = new \App\Controllers\ApiController($db);
        break;

    case 'export':
        $controller = new \App\Controllers\ExportController($db);
        break;

    case 'usuario':
        $controller = new \App\Controllers\UsuarioController($db);
        break;

    case 'historial':
        $controller = new \App\Controllers\HistorialController($db);
        break;

    case 'tienda':
        $controller = new \App\Controllers\TiendaController($db);
        break;

    case 'modelo':
        $controller = new \App\Controllers\ModeloController($db);
        break;

    default:
        $controller = new \App\Controllers\HomeController($db);
        break;
}

// ── Ejecución ─────────────────────────────────────────────────────────────────

if (method_exists($controller, $action)) {
    $controller->$action();
} else {
    if ($controllerName === 'api') {
        http_response_code(404);
        echo json_encode(['error' => "Acción '{$action}' no encontrada."]);
    } else {
        header('Location: index.php');
    }
}

exit;
