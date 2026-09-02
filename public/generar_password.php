<?php
/**
 * Script simplificado para diagnosticar el login
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔐 Diagnóstico de Login</h1>";

// Paso 1: Verificar funciones básicas de PHP
echo "<h2>1. Verificar funciones de password</h2>";
if (function_exists('password_hash')) {
    echo "<p>✅ password_hash disponible</p>";
} else {
    echo "<p>❌ password_hash NO disponible</p>";
}

if (function_exists('password_verify')) {
    echo "<p>✅ password_verify disponible</p>";
} else {
    echo "<p>❌ password_verify NO disponible</p>";
}

echo "<hr>";

// Paso 2: Probar generación de hash
echo "<h2>2. Generar hash de 'Admin123!'</h2>";
try {
    $password = 'Admin123!';
    $hash = password_hash($password, PASSWORD_BCRYPT);
    echo "<p>Hash generado: <code style='word-break: break-all;'>$hash</code></p>";
    
    // Verificar inmediatamente
    $verify = password_verify($password, $hash);
    echo "<p>Verificación: " . ($verify ? "✅ CORRECTO" : "❌ ERROR") . "</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";

// Paso 3: Probar conexión a DB
echo "<h2>3. Conexión a Base de Datos</h2>";

$rootPath = dirname(__DIR__);
echo "<p>ROOT_PATH: $rootPath</p>";

$dbFile = $rootPath . '/config/Database.php';
echo "<p>Buscando Database.php en: $dbFile</p>";

if (!file_exists($dbFile)) {
    echo "<p style='color:red'>❌ No se encontró config/Database.php</p>";
    exit;
}

echo "<p>✅ Archivo encontrado</p>";

try {
    require_once $dbFile;
    echo "<p>✅ Database.php cargado</p>";
    
    $database = new \App\Config\Database();
    echo "<p>✅ Clase Database instanciada</p>";
    
    $db = $database->getConnection();
    
    if (!$db) {
        echo "<p style='color:red'>❌ getConnection() retornó null</p>";
        exit;
    }
    
    echo "<p>✅ Conexión establecida</p>";
    
    // Verificar tabla usuarios
    $stmt = $db->query("SHOW TABLES LIKE 'usuarios'");
    if ($stmt->rowCount() == 0) {
        echo "<p style='color:red'>❌ La tabla 'usuarios' NO existe</p>";
        echo "<p>Ejecuta el archivo crear_tabla_usuarios.sql</p>";
        exit;
    }
    
    echo "<p>✅ Tabla 'usuarios' existe</p>";
    
    // Listar usuarios
    $stmt = $db->query("SELECT id, nombre, email, rol, activo FROM usuarios");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($usuarios) == 0) {
        echo "<p style='color:red'>❌ No hay usuarios en la tabla</p>";
        echo "<p>Ejecuta el INSERT del archivo crear_tabla_usuarios.sql</p>";
        exit;
    }
    
    echo "<p>✅ Usuarios encontrados: " . count($usuarios) . "</p>";
    
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; margin-top: 20px;'>";
    echo "<tr style='background: #667eea; color: white;'>";
    echo "<th>ID</th><th>Nombre</th><th>Email</th><th>Rol</th><th>Activo</th>";
    echo "</tr>";
    
    foreach ($usuarios as $user) {
        echo "<tr>";
        echo "<td>{$user['id']}</td>";
        echo "<td>{$user['nombre']}</td>";
        echo "<td>{$user['email']}</td>";
        echo "<td>{$user['rol']}</td>";
        echo "<td>" . ($user['activo'] ? '✅ Sí' : '❌ No') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<hr>";
    
    // Probar login con admin
    echo "<h2>4. Prueba de Login</h2>";
    
    $testEmail = 'admin@sigma.com';
    $testPassword = 'Admin123!';
    
    echo "<p>Intentando login con:</p>";
    echo "<ul>";
    echo "<li>Email: <strong>$testEmail</strong></li>";
    echo "<li>Password: <strong>$testPassword</strong></li>";
    echo "</ul>";
    
    $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = :email");
    $stmt->bindValue(':email', $testEmail, PDO::PARAM_STR);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        echo "<p style='color:red'>❌ Usuario '$testEmail' NO encontrado</p>";
        exit;
    }
    
    echo "<p>✅ Usuario encontrado: {$usuario['nombre']}</p>";
    echo "<p>Password hash (primeros 40 caracteres): <code>" . substr($usuario['password'], 0, 40) . "...</code></p>";
    echo "<p>Longitud del hash: " . strlen($usuario['password']) . " caracteres</p>";
    
    // Verificar password
    $isValid = password_verify($testPassword, $usuario['password']);
    
    if ($isValid) {
        echo "<p style='color: green; font-size: 20px; font-weight: bold;'>✅ ¡CONTRASEÑA CORRECTA!</p>";
        echo "<p>El login debería funcionar. Si no funciona, hay un problema en AuthController.php</p>";
    } else {
        echo "<p style='color: red; font-size: 20px; font-weight: bold;'>❌ CONTRASEÑA INCORRECTA</p>";
        echo "<p>El hash en la base de datos NO coincide con 'Admin123!'</p>";
        
        // Generar nuevo hash
        $newHash = password_hash($testPassword, PASSWORD_BCRYPT);
        echo "<h3>Solución:</h3>";
        echo "<p>Ejecuta este SQL para actualizar la contraseña:</p>";
        echo "<pre style='background: #f4f4f4; padding: 15px; border-radius: 5px;'>";
        echo "UPDATE usuarios SET password = '$newHash' WHERE email = 'admin@sigma.com';";
        echo "</pre>";
        
        echo "<p>O crea un usuario nuevo:</p>";
        echo "<pre style='background: #f4f4f4; padding: 15px; border-radius: 5px;'>";
        echo "INSERT INTO usuarios (nombre, email, password, rol, activo) VALUES\n";
        echo "('Admin Prueba', 'test@sigma.com', '$newHash', 'admin', 1);";
        echo "</pre>";
        echo "<p>Luego intenta con:</p>";
        echo "<ul>";
        echo "<li>Email: <strong>test@sigma.com</strong></li>";
        echo "<li>Password: <strong>Admin123!</strong></li>";
        echo "</ul>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>Archivo: " . $e->getFile() . "</p>";
    echo "<p>Línea: " . $e->getLine() . "</p>";
}

echo "<hr>";
echo "<p><a href='index.php' style='background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>← Volver al Login</a></p>";