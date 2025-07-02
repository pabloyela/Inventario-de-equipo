<?php
require_once 'config_inventario.php';
checkSession();

$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $password_actual = $_POST['password_actual'];
    $password_nueva = $_POST['password_nueva'];
    $password_confirmacion = $_POST['password_confirmacion'];
    
    // Validaciones
    if (empty($password_actual) || empty($password_nueva) || empty($password_confirmacion)) {
        $mensaje = 'Todos los campos son obligatorios';
        $tipo_mensaje = 'danger';
    } elseif ($password_nueva !== $password_confirmacion) {
        $mensaje = 'La nueva contraseña y su confirmación no coinciden';
        $tipo_mensaje = 'danger';
    } elseif (strlen($password_nueva) < 6) {
        $mensaje = 'La nueva contraseña debe tener al menos 6 caracteres';
        $tipo_mensaje = 'danger';
    } elseif (!verifyPassword($password_actual, $_SESSION['inv_username'])) {
        $mensaje = 'La contraseña actual es incorrecta';
        $tipo_mensaje = 'danger';
    } else {
        // Intentar cambiar la contraseña
        if (changePassword($_SESSION['inv_username'], $password_actual, $password_nueva)) {
            $mensaje = 'Contraseña cambiada exitosamente';
            $tipo_mensaje = 'success';
        } else {
            $mensaje = 'Error al cambiar la contraseña. Intente nuevamente.';
            $tipo_mensaje = 'danger';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar Contraseña - <?php echo APP_NAME; ?></title>
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
            background-color: var(--light-gray);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue)) !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            color: white !important;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .navbar-nav .nav-link {
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
            margin: 0 5px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .navbar-nav .nav-link:hover {
            color: white !important;
            background-color: rgba(255,255,255,0.1);
        }

        .main-content {
            padding: 40px 0;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 20px;
            border: none;
        }

        .card-header h4 {
            margin: 0;
            font-weight: 600;
        }

        .form-control {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--light-blue);
            box-shadow: 0 0 0 0.2rem rgba(93, 173, 226, 0.25);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            border: none;
            border-radius: 8px;
            padding: 12px 25px;
            font-weight: 600;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(30, 60, 114, 0.3);
        }

        .btn-secondary {
            border-radius: 8px;
            padding: 12px 25px;
            font-weight: 600;
        }

        .alert {
            border-radius: 8px;
            border: none;
        }

        .password-requirements {
            background-color: var(--very-light-blue);
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            border-left: 4px solid var(--light-blue);
        }

        .password-requirements h6 {
            color: var(--primary-blue);
            margin-bottom: 10px;
            font-weight: 600;
        }

        .password-requirements ul {
            margin-bottom: 0;
            padding-left: 20px;
        }

        .password-requirements li {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index_inventario.php">
                <img src="logo_INDE.png" alt="INDE" style="height: 30px; width: auto; margin-right: 8px;">
                INDE - Inventario de Equipos
            </a>
            
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index_inventario.php">
                    <i class="fas fa-arrow-left me-1"></i>Volver al Dashboard
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container main-content">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4>
                            <i class="fas fa-key me-2"></i>
                            Cambiar Contraseña
                        </h4>
                        <p class="mb-0 mt-2 opacity-75">
                            Usuario: <strong><?php echo $_SESSION['inv_username']; ?></strong>
                        </p>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($mensaje)): ?>
                        <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                            <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                            <?php echo $mensaje; ?>
                        </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="password_actual" class="form-label">
                                    <i class="fas fa-lock me-1"></i>Contraseña Actual
                                </label>
                                <input type="password" 
                                       class="form-control" 
                                       id="password_actual" 
                                       name="password_actual" 
                                       placeholder="Ingrese su contraseña actual"
                                       required>
                            </div>

                            <div class="mb-3">
                                <label for="password_nueva" class="form-label">
                                    <i class="fas fa-key me-1"></i>Nueva Contraseña
                                </label>
                                <input type="password" 
                                       class="form-control" 
                                       id="password_nueva" 
                                       name="password_nueva" 
                                       placeholder="Ingrese su nueva contraseña"
                                       minlength="6"
                                       required>
                            </div>

                            <div class="mb-3">
                                <label for="password_confirmacion" class="form-label">
                                    <i class="fas fa-check me-1"></i>Confirmar Nueva Contraseña
                                </label>
                                <input type="password" 
                                       class="form-control" 
                                       id="password_confirmacion" 
                                       name="password_confirmacion" 
                                       placeholder="Confirme su nueva contraseña"
                                       minlength="6"
                                       required>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="index_inventario.php" class="btn btn-secondary me-md-2">
                                    <i class="fas fa-times me-2"></i>Cancelar
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Cambiar Contraseña
                                </button>
                            </div>
                        </form>

                        <div class="password-requirements">
                            <h6><i class="fas fa-info-circle me-2"></i>Requisitos de Contraseña</h6>
                            <ul>
                                <li>Mínimo 6 caracteres de longitud</li>
                                <li>Se recomienda usar una combinación de letras, números y símbolos</li>
                                <li>Evite usar información personal fácil de adivinar</li>
                                <li>No comparta su contraseña con otras personas</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Validación en tiempo real de confirmación de contraseña
        document.getElementById('password_confirmacion').addEventListener('input', function() {
            const nueva = document.getElementById('password_nueva').value;
            const confirmacion = this.value;
            
            if (confirmacion && nueva !== confirmacion) {
                this.setCustomValidity('Las contraseñas no coinciden');
                this.classList.add('is-invalid');
            } else {
                this.setCustomValidity('');
                this.classList.remove('is-invalid');
            }
        });

        document.getElementById('password_nueva').addEventListener('input', function() {
            const confirmacion = document.getElementById('password_confirmacion');
            if (confirmacion.value) {
                confirmacion.dispatchEvent(new Event('input'));
            }
        });
    </script>
</body>
</html>
