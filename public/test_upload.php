<?php
/**
 * Script de diagnóstico para subida de archivos
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

define('ROOT_PATH', dirname(__DIR__));

echo "<h1>🔍 Diagnóstico de Subida de Archivos</h1>";

// 1. Verificar ROOT_PATH
echo "<h2>1. ROOT_PATH</h2>";
echo "<p><strong>ROOT_PATH:</strong> " . ROOT_PATH . "</p>";

// 2. Verificar carpeta uploads
echo "<h2>2. Carpeta uploads</h2>";
$uploadDir = ROOT_PATH . '/public/uploads/usuarios/';
echo "<p><strong>Ruta completa:</strong> $uploadDir</p>";

if (is_dir($uploadDir)) {
    echo "<p style='color: green;'>✅ La carpeta existe</p>";
    
    if (is_writable($uploadDir)) {
        echo "<p style='color: green;'>✅ La carpeta tiene permisos de escritura</p>";
    } else {
        echo "<p style='color: red;'>❌ La carpeta NO tiene permisos de escritura</p>";
        echo "<p>Ejecuta: <code>chmod -R 777 " . ROOT_PATH . "/public/uploads</code></p>";
    }
} else {
    echo "<p style='color: red;'>❌ La carpeta NO existe</p>";
    echo "<p>Intentando crearla...</p>";
    
    if (mkdir($uploadDir, 0777, true)) {
        echo "<p style='color: green;'>✅ Carpeta creada exitosamente</p>";
    } else {
        echo "<p style='color: red;'>❌ No se pudo crear la carpeta</p>";
    }
}

// 3. Configuración PHP
echo "<h2>3. Configuración PHP</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Directiva</th><th>Valor</th></tr>";
echo "<tr><td>file_uploads</td><td>" . (ini_get('file_uploads') ? 'Habilitado' : 'Deshabilitado') . "</td></tr>";
echo "<tr><td>upload_max_filesize</td><td>" . ini_get('upload_max_filesize') . "</td></tr>";
echo "<tr><td>post_max_size</td><td>" . ini_get('post_max_size') . "</td></tr>";
echo "<tr><td>max_file_uploads</td><td>" . ini_get('max_file_uploads') . "</td></tr>";
echo "<tr><td>upload_tmp_dir</td><td>" . (ini_get('upload_tmp_dir') ?: 'Default') . "</td></tr>";
echo "</table>";

// 4. Formulario de prueba
echo "<h2>4. Prueba de Subida</h2>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['test_file']['name'])) {
    echo "<h3>Resultado de la subida:</h3>";
    
    echo "<pre>";
    print_r($_FILES['test_file']);
    echo "</pre>";
    
    $file = $_FILES['test_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $newName = 'test_' . time() . '.' . $ext;
        $destination = $uploadDir . $newName;
        
        echo "<p><strong>Archivo original:</strong> {$file['name']}</p>";
        echo "<p><strong>Tamaño:</strong> {$file['size']} bytes</p>";
        echo "<p><strong>Tipo:</strong> {$file['type']}</p>";
        echo "<p><strong>Nombre nuevo:</strong> $newName</p>";
        echo "<p><strong>Destino:</strong> $destination</p>";
        
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            echo "<p style='color: green; font-size: 20px;'>✅ ¡ARCHIVO SUBIDO EXITOSAMENTE!</p>";
            echo "<p><img src='uploads/usuarios/$newName' style='max-width: 300px; border-radius: 10px;'></p>";
        } else {
            echo "<p style='color: red; font-size: 20px;'>❌ Error al mover el archivo</p>";
            echo "<p>Verifica permisos de la carpeta</p>";
        }
    } else {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'El archivo excede upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'El archivo excede MAX_FILE_SIZE del formulario',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
            UPLOAD_ERR_NO_FILE => 'No se subió ningún archivo',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal',
            UPLOAD_ERR_CANT_WRITE => 'Error al escribir en disco',
            UPLOAD_ERR_EXTENSION => 'Una extensión de PHP detuvo la subida'
        ];
        
        echo "<p style='color: red;'>❌ Error: " . ($errors[$file['error']] ?? 'Error desconocido') . "</p>";
    }
}
?>

<form method="POST" enctype="multipart/form-data" style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 10px;">
    <h3>Selecciona una imagen para probar:</h3>
    <input type="file" name="test_file" accept="image/*" style="margin: 10px 0;">
    <br>
    <button type="submit" style="padding: 10px 20px; background: #212529; color: white; border: none; border-radius: 5px; cursor: pointer;">
        Subir Imagen de Prueba
    </button>
</form>

<hr>
<h2>5. Archivos en la carpeta usuarios</h2>
<?php
if (is_dir($uploadDir)) {
    $files = scandir($uploadDir);
    $imageFiles = array_diff($files, ['.', '..']);
    
    if (count($imageFiles) > 0) {
        echo "<p>Archivos encontrados: " . count($imageFiles) . "</p>";
        echo "<div style='display: flex; flex-wrap: wrap; gap: 10px;'>";
        foreach ($imageFiles as $file) {
            echo "<div style='text-align: center;'>";
            echo "<img src='uploads/usuarios/$file' style='max-width: 150px; border-radius: 10px;'>";
            echo "<br><small>$file</small>";
            echo "</div>";
        }
        echo "</div>";
    } else {
        echo "<p>No hay archivos en la carpeta</p>";
    }
}
?>

<hr>
<p><a href="index.php">← Volver al sistema</a></p>