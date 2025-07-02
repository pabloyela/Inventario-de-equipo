<?php
require_once 'config_inventario.php';
checkSession();
requireAdmin(); // Solo administradores pueden agregar equipos

$pdo = getDBConnection();
$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Recoger y validar datos del formulario
        $nombre_equipo = sanitizeInput($_POST['nombre_equipo']);
        $ip_equipo = sanitizeInput($_POST['ip_equipo']);
        $ip_local = sanitizeInput($_POST['ip_local']);
        $procesador = sanitizeInput($_POST['procesador']);
        $memoria_ram = sanitizeInput($_POST['memoria_ram']);
        $disco_duro = sanitizeInput($_POST['disco_duro']);
        $tarjeta_red = sanitizeInput($_POST['tarjeta_red']);
        $usuario_sistema = sanitizeInput($_POST['usuario_sistema']);
        $sistema_operativo = sanitizeInput($_POST['sistema_operativo']);
        $fabricante = sanitizeInput($_POST['fabricante']);
        $modelo = sanitizeInput($_POST['modelo']);
        $numero_serie = sanitizeInput($_POST['numero_serie']);
        $observaciones = sanitizeInput($_POST['observaciones']);
        $ubicacion = sanitizeInput($_POST['ubicacion']);
        $responsable = sanitizeInput($_POST['responsable']);
        $estado_equipo = sanitizeInput($_POST['estado_equipo']);
        
        // Validaciones básicas
        if (empty($nombre_equipo)) {
            throw new Exception('El nombre del equipo es obligatorio');
        }
        
        if (empty($ip_equipo) || !validarIP($ip_equipo)) {
            throw new Exception('La IP externa es obligatoria y debe ser válida');
        }
        
        if (!empty($ip_local) && !validarIP($ip_local)) {
            throw new Exception('La IP local debe ser válida');
        }
        
        // Verificar si ya existe un equipo con el mismo nombre
        $stmt = $pdo->prepare("SELECT id FROM equipos_info WHERE nombre_equipo = ?");
        $stmt->execute([$nombre_equipo]);
        if ($stmt->fetch()) {
            throw new Exception('Ya existe un equipo registrado con este nombre');
        }
        
        // NOTA: No validamos IP duplicada ya que varios equipos pueden 
        // compartir la misma IP pública (NAT/Firewall corporativo)
        
        // Insertar el nuevo equipo
        $sql = "
            INSERT INTO equipos_info (
                ip_equipo, ip_local, procesador, memoria_ram, disco_duro, tarjeta_red,
                usuario_sistema, fecha_captura, user_agent, navegador, sistema_operativo,
                nombre_equipo, fabricante, modelo, numero_serie, origen_datos,
                observaciones, ubicacion, responsable, estado_equipo
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, 'Manual',
                ?, ?, ?, ?
            )
        ";
        
        $user_agent = 'Registro Manual - ' . $_SESSION['inv_full_name'];
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $ip_equipo, $ip_local, $procesador, $memoria_ram, $disco_duro, $tarjeta_red,
            $usuario_sistema, $user_agent, $user_agent, $sistema_operativo,
            $nombre_equipo, $fabricante, $modelo, $numero_serie,
            $observaciones, $ubicacion, $responsable, $estado_equipo
        ]);
        
        $equipo_id = $pdo->lastInsertId();
        $mensaje = 'Equipo registrado exitosamente';
        $tipo_mensaje = 'success';
        
        // Limpiar formulario después de éxito
        $_POST = array();
        
    } catch (Exception $e) {
        $mensaje = 'Error: ' . $e->getMessage();
        $tipo_mensaje = 'danger';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Nuevo Equipo - <?php echo APP_NAME; ?></title>
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
            min-height: 60px;
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
            padding: 8px 12px;
        }

        .navbar-nav .nav-link:hover {
            color: white !important;
            background-color: rgba(255,255,255,0.1);
        }

        .navbar-nav .nav-link.active {
            color: white !important;
            background-color: rgba(255,255,255,0.2);
        }

        .dropdown-menu {
            background-color: white;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-radius: 8px;
        }

        .dropdown-item {
            color: var(--primary-blue);
            transition: all 0.3s ease;
            padding: 8px 16px;
        }

        .dropdown-item:hover {
            background-color: var(--very-light-blue);
            color: var(--primary-blue);
        }

        .dropdown-item.active {
            background-color: var(--primary-blue);
            color: white;
        }

        .navbar-toggler {
            border: none;
            padding: 4px 8px;
        }

        .navbar-toggler:focus {
            box-shadow: none;
        }

        .badge {
            font-size: 0.7rem;
        }

        .main-content {
            padding: 20px 0;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
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

        .form-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .section-title {
            color: var(--primary-blue);
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--very-light-blue);
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--light-blue);
            box-shadow: 0 0 0 0.2rem rgba(93, 173, 226, 0.25);
        }

        .btn {
            border-radius: 8px;
            font-weight: 500;
            padding: 12px 25px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            border: none;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(30, 60, 114, 0.3);
        }

        .alert {
            border-radius: 8px;
            border: none;
        }

        .required {
            color: #dc3545;
        }

        .form-text {
            font-size: 0.85rem;
            color: #6c757d;
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
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index_inventario.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="equipos_lista.php">Ver Equipos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="buscar_equipos.php">Buscar Equipos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="equipo_nuevo.php">Agregar Equipo</a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="logout_inventario.php">
                            <?php echo $_SESSION['inv_full_name']; ?> - Cerrar Sesión
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container-fluid main-content">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index_inventario.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="equipos_lista.php">Equipos</a></li>
                <li class="breadcrumb-item active" aria-current="page">Agregar Nuevo</li>
            </ol>
        </nav>

        <!-- Header -->
        <div class="card">
            <div class="card-header">
                <h4>
                    <i class="fas fa-plus me-2"></i>
                    Agregar Nuevo Equipo
                </h4>
                <p class="mb-0 mt-2 opacity-75">
                    Complete la información del equipo que desea registrar en el inventario
                </p>
            </div>
        </div>

        <!-- Mensajes -->
        <?php if (!empty($mensaje)): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?>">
            <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
            <?php echo $mensaje; ?>
            <?php if ($tipo_mensaje == 'success' && isset($equipo_id)): ?>
            <div class="mt-2">
                <a href="equipo_detalle.php?id=<?php echo $equipo_id; ?>" class="btn btn-sm btn-outline-success">
                    <i class="fas fa-eye me-1"></i>Ver Equipo Registrado
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="row">
                <!-- Información Básica -->
                <div class="col-md-6">
                    <div class="form-section">
                        <h5 class="section-title">
                            <i class="fas fa-info-circle me-2"></i>Información Básica
                        </h5>
                        
                        <div class="mb-3">
                            <label for="nombre_equipo" class="form-label">
                                Nombre del Equipo <span class="required">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-desktop"></i>
                                </span>
                                <input type="text" class="form-control" id="nombre_equipo" name="nombre_equipo" 
                                       value="<?php echo isset($_POST['nombre_equipo']) ? htmlspecialchars($_POST['nombre_equipo']) : ''; ?>"
                                       placeholder="Ej: PC-ADMIN-01"
                                       required>
                            </div>
                            <div class="form-text">Nombre identificativo único del equipo</div>
                        </div>

                        <div class="mb-3">
                            <label for="fabricante" class="form-label">Fabricante</label>
                            <select class="form-select" id="fabricante" name="fabricante">
                                <option value="">Seleccionar fabricante</option>
                                <option value="Dell Inc." <?php echo (isset($_POST['fabricante']) && $_POST['fabricante'] == 'Dell Inc.') ? 'selected' : ''; ?>>Dell Inc.</option>
                                <option value="HP Inc." <?php echo (isset($_POST['fabricante']) && $_POST['fabricante'] == 'HP Inc.') ? 'selected' : ''; ?>>HP Inc.</option>
                                <option value="Lenovo" <?php echo (isset($_POST['fabricante']) && $_POST['fabricante'] == 'Lenovo') ? 'selected' : ''; ?>>Lenovo</option>
                                <option value="ASUS" <?php echo (isset($_POST['fabricante']) && $_POST['fabricante'] == 'ASUS') ? 'selected' : ''; ?>>ASUS</option>
                                <option value="Acer" <?php echo (isset($_POST['fabricante']) && $_POST['fabricante'] == 'Acer') ? 'selected' : ''; ?>>Acer</option>
                                <option value="Apple" <?php echo (isset($_POST['fabricante']) && $_POST['fabricante'] == 'Apple') ? 'selected' : ''; ?>>Apple</option>
                                <option value="Otro" <?php echo (isset($_POST['fabricante']) && $_POST['fabricante'] == 'Otro') ? 'selected' : ''; ?>>Otro</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="modelo" class="form-label">Modelo</label>
                            <input type="text" class="form-control" id="modelo" name="modelo" 
                                   value="<?php echo isset($_POST['modelo']) ? htmlspecialchars($_POST['modelo']) : ''; ?>"
                                   placeholder="Ej: OptiPlex 7060">
                        </div>

                        <div class="mb-3">
                            <label for="numero_serie" class="form-label">Número de Serie</label>
                            <input type="text" class="form-control" id="numero_serie" name="numero_serie" 
                                   value="<?php echo isset($_POST['numero_serie']) ? htmlspecialchars($_POST['numero_serie']) : ''; ?>"
                                   placeholder="Ej: ABC123XYZ">
                        </div>

                        <div class="mb-3">
                            <label for="estado_equipo" class="form-label">Estado del Equipo</label>
                            <select class="form-select" id="estado_equipo" name="estado_equipo">
                                <option value="Activo" <?php echo (isset($_POST['estado_equipo']) && $_POST['estado_equipo'] == 'Activo') ? 'selected' : ''; ?>>Activo</option>
                                <option value="Inactivo" <?php echo (isset($_POST['estado_equipo']) && $_POST['estado_equipo'] == 'Inactivo') ? 'selected' : ''; ?>>Inactivo</option>
                                <option value="Mantenimiento" <?php echo (isset($_POST['estado_equipo']) && $_POST['estado_equipo'] == 'Mantenimiento') ? 'selected' : ''; ?>>Mantenimiento</option>
                                <option value="Dado de baja" <?php echo (isset($_POST['estado_equipo']) && $_POST['estado_equipo'] == 'Dado de baja') ? 'selected' : ''; ?>>Dado de baja</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Información de Red -->
                <div class="col-md-6">
                    <div class="form-section">
                        <h5 class="section-title">
                            <i class="fas fa-network-wired me-2"></i>Configuración de Red
                        </h5>
                        
                        <div class="mb-3">
                            <label for="ip_equipo" class="form-label">
                                IP Externa <span class="required">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-globe"></i>
                                </span>
                                <input type="text" class="form-control" id="ip_equipo" name="ip_equipo" 
                                       value="<?php echo isset($_POST['ip_equipo']) ? htmlspecialchars($_POST['ip_equipo']) : ''; ?>"
                                       placeholder="Ej: 192.168.1.100"
                                       pattern="^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$"
                                       required>
                            </div>
                            <div class="form-text">Dirección IP principal del equipo</div>
                        </div>

                        <div class="mb-3">
                            <label for="ip_local" class="form-label">IP Local</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-home"></i>
                                </span>
                                <input type="text" class="form-control" id="ip_local" name="ip_local" 
                                       value="<?php echo isset($_POST['ip_local']) ? htmlspecialchars($_POST['ip_local']) : ''; ?>"
                                       placeholder="Ej: 10.0.0.50"
                                       pattern="^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$">
                            </div>
                            <div class="form-text">IP en red local (opcional)</div>
                        </div>

                        <div class="mb-3">
                            <label for="tarjeta_red" class="form-label">Tarjeta(s) de Red</label>
                            <textarea class="form-control" id="tarjeta_red" name="tarjeta_red" rows="2"
                                      placeholder="Ej: Intel(R) Ethernet Connection"><?php echo isset($_POST['tarjeta_red']) ? htmlspecialchars($_POST['tarjeta_red']) : ''; ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Hardware -->
                <div class="col-md-6">
                    <div class="form-section">
                        <h5 class="section-title">
                            <i class="fas fa-microchip me-2"></i>Hardware
                        </h5>
                        
                        <div class="mb-3">
                            <label for="procesador" class="form-label">Procesador</label>
                            <input type="text" class="form-control" id="procesador" name="procesador" 
                                   value="<?php echo isset($_POST['procesador']) ? htmlspecialchars($_POST['procesador']) : ''; ?>"
                                   placeholder="Ej: Intel(R) Core(TM) i7-8700 CPU @ 3.20GHz">
                        </div>

                        <div class="mb-3">
                            <label for="memoria_ram" class="form-label">Memoria RAM</label>
                            <input type="text" class="form-control" id="memoria_ram" name="memoria_ram" 
                                   value="<?php echo isset($_POST['memoria_ram']) ? htmlspecialchars($_POST['memoria_ram']) : ''; ?>"
                                   placeholder="Ej: Total: 16 GB">
                        </div>

                        <div class="mb-3">
                            <label for="disco_duro" class="form-label">Almacenamiento</label>
                            <textarea class="form-control" id="disco_duro" name="disco_duro" rows="2"
                                      placeholder="Ej: Unidad C: 174GB de 930GB (18% usado)"><?php echo isset($_POST['disco_duro']) ? htmlspecialchars($_POST['disco_duro']) : ''; ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Software y Usuario -->
                <div class="col-md-6">
                    <div class="form-section">
                        <h5 class="section-title">
                            <i class="fas fa-laptop me-2"></i>Software y Usuario
                        </h5>
                        
                        <div class="mb-3">
                            <label for="sistema_operativo" class="form-label">Sistema Operativo</label>
                            <select class="form-select" id="sistema_operativo" name="sistema_operativo">
                                <option value="">Seleccionar SO</option>
                                <option value="Microsoft Windows 11 Pro 10.0.26100 64 bits" <?php echo (isset($_POST['sistema_operativo']) && $_POST['sistema_operativo'] == 'Microsoft Windows 11 Pro 10.0.26100 64 bits') ? 'selected' : ''; ?>>Windows 11 Pro</option>
                                <option value="Microsoft Windows 10 Pro 10.0.19045 64 bits" <?php echo (isset($_POST['sistema_operativo']) && $_POST['sistema_operativo'] == 'Microsoft Windows 10 Pro 10.0.19045 64 bits') ? 'selected' : ''; ?>>Windows 10 Pro</option>
                                <option value="Ubuntu 24.04.2 LTS" <?php echo (isset($_POST['sistema_operativo']) && $_POST['sistema_operativo'] == 'Ubuntu 24.04.2 LTS') ? 'selected' : ''; ?>>Ubuntu 24.04 LTS</option>
                                <option value="Ubuntu 22.04.2 LTS" <?php echo (isset($_POST['sistema_operativo']) && $_POST['sistema_operativo'] == 'Ubuntu 22.04.2 LTS') ? 'selected' : ''; ?>>Ubuntu 22.04 LTS</option>
                                <option value="Otro" <?php echo (isset($_POST['sistema_operativo']) && $_POST['sistema_operativo'] == 'Otro') ? 'selected' : ''; ?>>Otro</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="usuario_sistema" class="form-label">Usuario del Sistema</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-user"></i>
                                </span>
                                <input type="text" class="form-control" id="usuario_sistema" name="usuario_sistema" 
                                       value="<?php echo isset($_POST['usuario_sistema']) ? htmlspecialchars($_POST['usuario_sistema']) : ''; ?>"
                                       placeholder="Ej: jperez">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información Adicional -->
                <div class="col-md-12">
                    <div class="form-section">
                        <h5 class="section-title">
                            <i class="fas fa-sticky-note me-2"></i>Información Adicional
                        </h5>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="ubicacion" class="form-label">Ubicación</label>
                                    <input type="text" class="form-control" id="ubicacion" name="ubicacion" 
                                           value="<?php echo isset($_POST['ubicacion']) ? htmlspecialchars($_POST['ubicacion']) : ''; ?>"
                                           placeholder="Ej: Oficina 201, División de Informática">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="responsable" class="form-label">Responsable</label>
                                    <input type="text" class="form-control" id="responsable" name="responsable" 
                                           value="<?php echo isset($_POST['responsable']) ? htmlspecialchars($_POST['responsable']) : ''; ?>"
                                           placeholder="Ej: Juan Pérez">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="observaciones" class="form-label">Observaciones</label>
                            <textarea class="form-control" id="observaciones" name="observaciones" rows="3"
                                      placeholder="Comentarios adicionales sobre el equipo..."><?php echo isset($_POST['observaciones']) ? htmlspecialchars($_POST['observaciones']) : ''; ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botones -->
            <div class="text-center mt-4">
                <a href="equipos_lista.php" class="btn btn-secondary me-3">
                    <i class="fas fa-times me-2"></i>Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Registrar Equipo
                </button>
            </div>
        </form>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Validación de IPs en tiempo real
        function validarIP(input) {
            const ipPattern = /^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;
            
            if (input.value && !ipPattern.test(input.value)) {
                input.setCustomValidity('Formato de IP inválido');
                input.classList.add('is-invalid');
            } else {
                input.setCustomValidity('');
                input.classList.remove('is-invalid');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Validar IPs
            const ipFields = document.querySelectorAll('#ip_equipo, #ip_local');
            ipFields.forEach(function(field) {
                field.addEventListener('input', function() {
                    validarIP(this);
                });
            });

            // Auto-generar nombre de equipo basado en otros campos
            const fabricanteField = document.getElementById('fabricante');
            const usuarioField = document.getElementById('usuario_sistema');
            const nombreField = document.getElementById('nombre_equipo');
            
            function autoGenerarNombre() {
                const fabricante = fabricanteField.value;
                const usuario = usuarioField.value;
                
                if (fabricante && usuario && !nombreField.value) {
                    let prefijo = '';
                    if (fabricante.includes('Dell')) prefijo = 'DELL';
                    else if (fabricante.includes('HP')) prefijo = 'HP';
                    else if (fabricante.includes('Lenovo')) prefijo = 'LEN';
                    else prefijo = 'PC';
                    
                    const sugerencia = prefijo + '-' + usuario.toUpperCase();
                    if (confirm('¿Desea usar el nombre sugerido: ' + sugerencia + '?')) {
                        nombreField.value = sugerencia;
                    }
                }
            }

            fabricanteField.addEventListener('change', autoGenerarNombre);
            usuarioField.addEventListener('blur', autoGenerarNombre);
        });
    </script>
</body>
</html>
