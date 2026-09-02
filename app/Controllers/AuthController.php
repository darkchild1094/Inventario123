<?php

namespace App\Controllers;

use App\Models\Usuario;

/**
 * AuthController — femsa_assets
 *
 * Login, logout y verificación de sesión.
 * Roles válidos: admin, fs, coordinador, ati
 */
class AuthController
{
    private $db;
    private const SESSION_TIMEOUT = 1800; // 30 minutos

    public function __construct($db)
    {
        $this->db = $db;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    // ── Acciones públicas ─────────────────────────────────────────────────────

    public function mostrarLogin(): void
    {
        if ($this->estaAutenticado()) {
            header('Location: index.php');
            exit;
        }
        require_once ROOT_PATH . '/app/views/auth/login.php';
    }

    public function login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?controller=auth&action=mostrarLogin');
            exit;
        }

        $email    = trim($_POST['email']    ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $_SESSION['error'] = 'Por favor complete todos los campos.';
            header('Location: index.php?controller=auth&action=mostrarLogin');
            exit;
        }

        $usuarioModel = new Usuario($this->db);
        $usuario      = $usuarioModel->buscarPorEmail($email);

        if (!$usuario || !password_verify($password, $usuario['password'])) {
            $_SESSION['error'] = 'Correo o contraseña incorrectos.';
            header('Location: index.php?controller=auth&action=mostrarLogin');
            exit;
        }

        $usuarioPlazas = $usuarioModel->obtenerPlazas($usuario['id']);
        $plazaId       = (int) ($usuario['plaza_id'] ?? 0);
        if ($plazaId === 0 && !empty($usuarioPlazas[0]['id'])) {
            $plazaId = (int) $usuarioPlazas[0]['id'];
        }

        $plazaNombre = '';
        foreach ($usuarioPlazas as $plaza) {
            if ((int)$plaza['id'] === $plazaId) {
                $plazaNombre = $plaza['nombre'];
                break;
            }
        }

        session_regenerate_id(true);

        $tipo = strtolower(trim($usuario['tipo'] ?? 'fs'));

        $_SESSION['usuario'] = [
            'id'        => $usuario['id'],
            'nombre'    => $usuario['nombre'],
            'tipo'      => $tipo,
            'email'     => $usuario['email'],
            'foto'      => $usuario['foto'] ?? null,
            'plaza_id'  => $plazaId,
            'plaza_nombre' => $plazaNombre,
            'plazas'    => $usuarioPlazas,
            'plaza_ids' => array_map(fn($p) => (int)$p['id'], $usuarioPlazas),
        ];

        // Compatibilidad con código que lee sesión plana
        $_SESSION['usuario_id']    = $usuario['id'];
        $_SESSION['usuario_nombre'] = $usuario['nombre'];
        $_SESSION['usuario_tipo']  = $tipo;
        $_SESSION['usuario_email'] = $usuario['email'];

        $_SESSION['last_activity'] = time();
        $_SESSION['user_agent']    = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $_SESSION['success'] = '¡Bienvenido, ' . $usuario['nombre'] . '!';
        header('Location: index.php');
        exit;
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        session_destroy();
        header('Location: index.php?controller=auth&action=mostrarLogin');
        exit;
    }

    // ── Helpers de autenticación ──────────────────────────────────────────────

    public function estaAutenticado(): bool
    {
        if (!isset($_SESSION['usuario_id'])) {
            return false;
        }

        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > self::SESSION_TIMEOUT) {
                $this->logout();
                return false;
            }
            $_SESSION['last_activity'] = time();
        }

        return true;
    }

    public function requerirAutenticacion(): void
    {
        if (!$this->estaAutenticado()) {
            header('Location: index.php?controller=auth&action=mostrarLogin');
            exit;
        }
    }

    /**
     * Verifica si el usuario en sesión tiene el tipo indicado.
     * Tipos válidos: admin, fs, coordinador, ati
     */
    public function tieneTipo(string $tipo): bool
    {
        $tipoSesion = $_SESSION['usuario']['tipo'] ?? $_SESSION['usuario_tipo'] ?? '';
        return strtolower(trim($tipoSesion)) === strtolower(trim($tipo));
    }

    /** Alias semántico para compatibilidad con código que usa tieneRol() */
    public function tieneRol(string $tipo): bool
    {
        return $this->tieneTipo($tipo);
    }
}