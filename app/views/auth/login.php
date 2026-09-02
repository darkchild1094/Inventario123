<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Inventario 123</title>
    <?php include ROOT_PATH . '/app/views/components/favicon.php'; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #212529;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }
        body::before {
            content: '';
            position: absolute; inset: 0;
            background:
                radial-gradient(circle at 20% 50%, rgba(102,126,234,.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(118,75,162,.1)  0%, transparent 50%);
            pointer-events: none;
        }
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,.5);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
            animation: fadeInUp .5s ease;
            position: relative;
            z-index: 1;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .login-header {
            background: #212529;
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .login-header img { height: 80px; margin-bottom: 15px; border-radius: 16px; }
        .login-header h1  { font-size: 26px; font-weight: 700; margin-bottom: 6px; }
        .login-header p   { font-size: 14px; opacity: .85; margin: 0; }
        .login-body  { padding: 40px 35px; }
        .form-floating { margin-bottom: 20px; }
        .form-floating > .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            height: 58px;
            transition: all .3s;
        }
        .form-floating > .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 .2rem rgba(102,126,234,.15);
        }
        .form-floating > label { padding: 1rem 15px; color: #6c757d; }
        .input-icon {
            position: absolute; right: 15px; top: 50%;
            transform: translateY(-50%);
            color: #adb5bd; font-size: 1.1rem; z-index: 10; cursor: pointer;
        }
        .btn-login {
            width: 100%; padding: 14px;
            background: #212529; color: white;
            border: none; border-radius: 10px;
            font-size: 16px; font-weight: 600;
            cursor: pointer; transition: all .3s; margin-top: 10px;
        }
        .btn-login:hover {
            background: #343a40;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(33,37,41,.3);
        }
        .alert { border-radius: 10px; margin-bottom: 25px; border: none; }
        .login-footer {
            text-align: center; padding: 20px;
            background: #f8f9fa; font-size: 13px;
            color: #6c757d; border-top: 1px solid #e9ecef;
        }
        .form-check-input:checked { background-color: #212529; border-color: #212529; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <img src="assets/img/icon_512x512.png" alt="Inventario 123" onerror="this.style.display='none'">
            <h1>Inventario 123</h1>
            <p>Sistema de Gestión de Activos</p>
        </div>

        <div class="login-body">
            <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= htmlspecialchars($_SESSION['error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            <?php if (!empty($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= htmlspecialchars($_SESSION['success']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <form method="POST" action="index.php?controller=auth&action=login">
                <div class="form-floating position-relative">
                    <input type="email" class="form-control" id="email" name="email"
                           placeholder="correo@femsa.com" required autofocus>
                    <label for="email"><i class="fas fa-envelope me-2"></i>Correo Electrónico</label>
                </div>
                <div class="form-floating position-relative">
                    <input type="password" class="form-control" id="password" name="password"
                           placeholder="Contraseña" required>
                    <label for="password"><i class="fas fa-lock me-2"></i>Contraseña</label>
                    <span class="input-icon" onclick="togglePassword()">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </span>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="remember" name="remember">
                    <label class="form-check-label" for="remember">Recordarme</label>
                </div>
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
                </button>
            </form>
        </div>

        <div class="login-footer">
            <i class="fas fa-shield-alt me-1"></i>
            &copy; <?= date('Y') ?> Kernel94 · Todos los derechos reservados.
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword() {
            const p = document.getElementById('password');
            const i = document.getElementById('toggleIcon');
            p.type = p.type === 'password' ? 'text' : 'password';
            i.classList.toggle('fa-eye');
            i.classList.toggle('fa-eye-slash');
        }
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(a => bootstrap.Alert.getOrCreateInstance(a).close());
        }, 5000);
    </script>
</body>
</html>