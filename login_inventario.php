<?php
require_once 'config_inventario.php';

// Si ya hay sesión activa, redirigir
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['inv_user_id'])) {
    header('Location: index_inventario.php');
    exit;
}

$error = '';
$timeout = isset($_GET['timeout']) ? true : false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Por favor ingrese usuario y contraseña';
    } else {
        if (userExists($username) && verifyPassword($password, $username)) {
            $userInfo = getUserInfo($username);
            
            $_SESSION['inv_user_id'] = $username;
            $_SESSION['inv_username'] = $username;
            $_SESSION['inv_full_name'] = $userInfo['nombre'];
            $_SESSION['inv_is_admin'] = $userInfo['es_admin'];
            $_SESSION['inv_last_activity'] = time();
            
            header('Location: index_inventario.php');
            exit;
        } else {
            $error = 'Usuario o contraseña incorrectos';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso al Sistema - <?php echo APP_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #1e3c72;
            --secondary-blue: #2a5298;
            --light-blue: #5dade2;
            --very-light-blue: #ebf3fd;
            --white: #ffffff;
            --light-gray: #f8f9fa;
        }

        body {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .login-container {
            max-width: 450px;
            width: 100%;
            margin: 0 auto;
        }

        .login-card {
            background: var(--white);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }

        .login-header {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            padding: 30px;
            text-align: center;
        }

        .login-header h2 {
            margin: 0;
            font-weight: 600;
            font-size: 1.5rem;
        }

        .login-header p {
            margin: 8px 0 0 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }

        .login-body {
            padding: 30px;
        }

        .form-control {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--light-blue);
            box-shadow: 0 0 0 0.2rem rgba(93, 173, 226, 0.25);
        }

        .btn-login {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }

        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(30, 60, 114, 0.3);
        }

        .alert {
            border-radius: 8px;
            border: none;
        }

        .input-group-text {
            background-color: var(--very-light-blue);
            border: 2px solid #e9ecef;
            border-right: none;
            color: var(--primary-blue);
        }

        .input-group .form-control {
            border-left: none;
        }

        .logo-section {
            margin-bottom: 20px;
        }

        .info-box {
            background-color: var(--very-light-blue);
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            border-left: 4px solid var(--light-blue);
        }

        .info-box h6 {
            color: var(--primary-blue);
            margin-bottom: 8px;
            font-weight: 600;
        }

        .info-box small {
            color: #6c757d;
            line-height: 1.4;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="login-card">
                <div class="login-header">
                    <div class="logo-section">
                        <img src="logo_INDE.png" alt="INDE Logo" style="width: 400px; height: 100px; object-fit: contain; margin-bottom: 10px;">
                    </div>
                    <h2>Sistema de Inventario</h2>
                    <p>División de Informática - INDE</p>
                </div>
                
                <div class="login-body">
                    <?php if ($timeout): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-clock me-2"></i>
                        Su sesión ha expirado. Por favor, inicie sesión nuevamente.
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo $error; ?>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="username" class="form-label">Usuario</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-user"></i>
                                </span>
                                <input type="text" 
                                       class="form-control" 
                                       id="username" 
                                       name="username" 
                                       placeholder="Ingrese su usuario"
                                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                                       required 
                                       autofocus>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="password" class="form-label">Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" 
                                       class="form-control" 
                                       id="password" 
                                       name="password" 
                                       placeholder="Ingrese su contraseña"
                                       required>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-login w-100">
                            <i class="fas fa-sign-in-alt me-2"></i>
                            Iniciar Sesión
                        </button>
                    </form>
                    <!--
                    <div class="info-box">
                        <h6><i class="fas fa-info-circle me-2"></i>Información del Sistema</h6>
                        <small>
                            <strong>Administradores:</strong> pyela, ccatalan<br>
                            <strong>Contraseña inicial:</strong> abc123**<br>
                            <strong>Versión:</strong> <?php echo APP_VERSION; ?>
                        </small>
                    </div> -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>
