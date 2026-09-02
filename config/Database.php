<?php
namespace App\Config;

use PDO;
use PDOException;

class Database
{
    private string $host;
    private string $db_name;
    private string $username;
    private string $password;
    public $conn;

    public function __construct()
    {
        $this->cargarEnv();
        $this->host     = getenv('DB_HOST') ?: 'localhost';
        $this->db_name  = getenv('DB_NAME') ?: 'femsa_assets';
        $this->username = getenv('DB_USER') ?: 'root';
        $this->password = getenv('DB_PASS') ?: '';
    }

    private function cargarEnv(): void
    {
        $envPath = dirname(__DIR__, 1) . '/.env';
        if (!file_exists($envPath)) {
            return;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ($value === 'null') {
                $value = '';
            }

            if ($key !== '') {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }

    public function getConnection()
    {
        $this->conn = null;
        try {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $this->host, $this->db_name);
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            // Forzar zona horaria de la sesión a México Central (UTC-6).
            // Sin esto, MySQL usa la zona horaria del SERVIDOR (normalmente
            // UTC en hosting compartido como alwaysdata), y como
            // fecha_alta/fecha_modificacion usan DEFAULT current_timestamp(),
            // las fechas se guardaban ~6 horas adelantadas.
            $this->conn->exec("SET time_zone = '-06:00'");
        } catch (PDOException $exception) {
            // Usa error_log para no romper el JSON si falla la conexión
            error_log("Error de conexión: " . $exception->getMessage());
        }
        return $this->conn;
    }
}
