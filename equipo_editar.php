<?php
require_once 'config_inventario.php';
checkSession();
requireAdmin(); // Solo administradores pueden editar equipos

$pdo = getDBConnection();

// Verificar que se proporcionó un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: equipos_lista.php?error=id_invalido');
    exit;
}

$equipo_id = (int)$_GET['id'];
$mensaje = '';
$tipo_mensaje = '';

// Obtener información actual del equipo
$stmt = $pdo->prepare("SELECT * FROM equipos_info WHERE id = ?");
$stmt->execute([$equipo_id]);
$equipo = $stmt->fetch();

if (!$equipo) {
    header('Location: equipos_lista.php?error=equipo_no_encontrado');
    exit;
}

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
        $fecha_ultimo_mantenimiento = !empty($_POST['fecha_ultimo_mantenimiento']) ? $_POST['fecha_ultimo_mantenimiento'] : null;
        $garantia_vencimiento = !empty($_POST['garantia_vencimiento']) ? $_POST['garantia_vencimiento'] : null;
        
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
        
        // Verificar si ya existe otro equipo con el mismo nombre (solo si cambió el nombre)
        if ($nombre_equipo !== $equipo['nombre_equipo']) {
            $stmt = $pdo->prepare("SELECT id FROM equipos_info WHERE nombre_equipo = ? AND id != ?");
            $stmt->execute([$nombre_equipo, $equipo_id]);
            if ($stmt->fetch()) {
                throw new Exception('Ya existe otro equipo registrado con este nombre');
            }
        }
        
        // NOTA: Removida la validación de IP duplicada ya que varios equipos 
        // pueden compartir la misma IP pública (NAT/Firewall corporativo)
        
        // Actualizar el equipo
        $sql = "
            UPDATE equipos_info SET
                ip_equipo = ?, ip_local = ?, procesador = ?, memoria_ram = ?, 
                disco_duro = ?, tarjeta_red = ?, usuario_sistema = ?, 
                sistema_operativo = ?, nombre_equipo = ?, fabricante = ?, 
                modelo = ?, numero_serie = ?, observaciones = ?, 
                ubicacion = ?, responsable = ?, estado_equipo = ?,
                fecha_ultimo_mantenimiento = ?, garantia_vencimiento = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $ip_equipo, $ip_local, $procesador, $memoria_ram, $disco_duro, 
            $tarjeta_red, $usuario_sistema, $sistema_operativo, $nombre_equipo, 
            $fabricante, $modelo, $numero_serie, $observaciones, 
            $ubicacion, $responsable, $estado_equipo, $fecha_ultimo_mantenimiento,
            $garantia_vencimiento, $equipo_id
        ]);
        
        $mensaje = 'Equipo actualizado exitosamente';
        $tipo_mensaje = 'success';
        
        // Recargar datos del equipo
        $stmt = $pdo->prepare("SELECT * FROM equipos_info WHERE id = ?");
        $stmt->execute([$equipo_id]);
        $equipo = $stmt->fetch();
        
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
    <title>Editar Equipo - <?php echo $equipo['nombre_equipo']; ?></title>
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

        .navbar-brand, .navbar-nav .nav-link {
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .navbar-nav .nav-link:hover {
            color: white !important;
            background-color: rgba(255,255,255,0.1);
            border-radius: 5px;
        }

        .main-content {
            /*padding: 20px 0;*/
	    max-width: 1400px;
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

        .breadcrumb {
            background: none;
            padding: 0;
            margin-bottom: 20px;
        }

        .breadcrumb-item a {
            color: var(--primary-blue);
            text-decoration: none;
        }

        .breadcrumb-item a:hover {
            color: var(--secondary-blue);
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

        .info-badge {
            background-color: var(--very-light-blue);
            color: var(--primary-blue);
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            margin-bottom: 15px;
        }

        .date-warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
            padding: 8px 12px;
            margin-top: 5px;
            font-size: 0.85rem;
            color: #856404;
        }

        .date-danger {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 6px;
            padding: 8px 12px;
            margin-top: 5px;
            font-size: 0.85rem;
            color: #721c24;
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
                <a class="nav-link" href="equipo_detalle.php?id=<?php echo $equipo_id; ?>">
                    <i class="fas fa-eye me-1"></i>Ver Detalle
                </a>
                <a class="nav-link" href="equipos_lista.php">
                    <i class="fas fa-arrow-left me-1"></i>Volver a Lista
                </a>
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
                <li class="breadcrumb-item"><a href="equipo_detalle.php?id=<?php echo $equipo_id; ?>"><?php echo $equipo['nombre_equipo']; ?></a></li>
                <li class="breadcrumb-item active">Editar</li>
            </ol>
        </nav>

        <!-- Header -->
        <div class="card">
            <div class="card-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4>
                            <i class="fas fa-edit me-2"></i>
                            Editar Equipo: <?php echo $equipo['nombre_equipo']; ?>
                        </h4>
                        <p class="mb-0 mt-2 opacity-75">
                            ID: <?php echo $equipo['id']; ?> | 
                            Registrado: <?php echo formatDate($equipo['created_at'], 'd/m/Y H:i'); ?> |
                            Origen: <?php echo $equipo['origen_datos']; ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <a href="equipo_detalle.php?id=<?php echo $equipo_id; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-eye me-2"></i>Ver Detalle
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información del registro -->
        <div class="info-badge">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Información de captura:</strong>
            Fecha: <?php echo formatDate($equipo['fecha_captura'], 'd/m/Y H:i'); ?> | 
            Origen: <?php echo $equipo['origen_datos']; ?> |
            Última actualización: <?php echo formatDate($equipo['updated_at'], 'd/m/Y H:i'); ?>
        </div>

        <!-- Mensajes -->
        <?php if (!empty($mensaje)): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?>">
            <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
            <?php echo $mensaje; ?>
            <?php if ($tipo_mensaje == 'success'): ?>
            <div class="mt-2">
                <a href="equipo_detalle.php?id=<?php echo $equipo_id; ?>" class="btn btn-sm btn-outline-success">
                    <i class="fas fa-eye me-1"></i>Ver Equipo Actualizado
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
                                       value="<?php echo htmlspecialchars($equipo['nombre_equipo']); ?>"
                                       placeholder="Ej: PC-ADMIN-01"
                                       required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="fabricante" class="form-label">Fabricante</label>
                            <select class="form-select" id="fabricante" name="fabricante">
                                <option value="">Seleccionar fabricante</option>
                                <option value="Dell Inc." <?php echo ($equipo['fabricante'] == 'Dell Inc.') ? 'selected' : ''; ?>>Dell Inc.</option>
                                <option value="HP Inc." <?php echo ($equipo['fabricante'] == 'HP Inc.') ? 'selected' : ''; ?>>HP Inc.</option>
                                <option value="Lenovo" <?php echo ($equipo['fabricante'] == 'Lenovo') ? 'selected' : ''; ?>>Lenovo</option>
                                <option value="ASUS" <?php echo ($equipo['fabricante'] == 'ASUS') ? 'selected' : ''; ?>>ASUS</option>
                                <option value="Acer" <?php echo ($equipo['fabricante'] == 'Acer') ? 'selected' : ''; ?>>Acer</option>
                                <option value="Apple" <?php echo ($equipo['fabricante'] == 'Apple') ? 'selected' : ''; ?>>Apple</option>
                                <option value="innotek GmbH" <?php echo ($equipo['fabricante'] == 'innotek GmbH') ? 'selected' : ''; ?>>innotek GmbH (VirtualBox)</option>
                                <option value="Otro" <?php echo ($equipo['fabricante'] == 'Otro') ? 'selected' : ''; ?>>Otro</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="modelo" class="form-label">Modelo</label>
                            <input type="text" class="form-control" id="modelo" name="modelo" 
                                   value="<?php echo htmlspecialchars($equipo['modelo']); ?>"
                                   placeholder="Ej: OptiPlex 7060">
                        </div>

                        <div class="mb-3">
                            <label for="numero_serie" class="form-label">Número de Serie</label>
                            <input type="text" class="form-control" id="numero_serie" name="numero_serie" 
                                   value="<?php echo htmlspecialchars($equipo['numero_serie']); ?>"
                                   placeholder="Ej: ABC123XYZ">
                        </div>

                        <div class="mb-3">
                            <label for="estado_equipo" class="form-label">Estado del Equipo</label>
                            <select class="form-select" id="estado_equipo" name="estado_equipo">
                                <option value="Activo" <?php echo ($equipo['estado_equipo'] == 'Activo') ? 'selected' : ''; ?>>Activo</option>
                                <option value="Inactivo" <?php echo ($equipo['estado_equipo'] == 'Inactivo') ? 'selected' : ''; ?>>Inactivo</option>
                                <option value="Mantenimiento" <?php echo ($equipo['estado_equipo'] == 'Mantenimiento') ? 'selected' : ''; ?>>Mantenimiento</option>
                                <option value="Dado de baja" <?php echo ($equipo['estado_equipo'] == 'Dado de baja') ? 'selected' : ''; ?>>Dado de baja</option>
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
                                       value="<?php echo htmlspecialchars($equipo['ip_equipo']); ?>"
                                       placeholder="Ej: 192.168.1.100"
                                       pattern="^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$"
                                       required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="ip_local" class="form-label">IP Local</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-home"></i>
                                </span>
                                <input type="text" class="form-control" id="ip_local" name="ip_local" 
                                       value="<?php echo htmlspecialchars($equipo['ip_local']); ?>"
                                       placeholder="Ej: 10.0.0.50"
                                       pattern="^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="tarjeta_red" class="form-label">Tarjeta(s) de Red</label>
                            <textarea class="form-control" id="tarjeta_red" name="tarjeta_red" rows="2"
                                      placeholder="Ej: Intel(R) Ethernet Connection"><?php echo htmlspecialchars($equipo['tarjeta_red']); ?></textarea>
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
                            <textarea class="form-control" id="procesador" name="procesador" rows="2"
                                      placeholder="Ej: Intel(R) Core(TM) i7-8700 CPU @ 3.20GHz"><?php echo htmlspecialchars($equipo['procesador']); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="memoria_ram" class="form-label">Memoria RAM</label>
                            <input type="text" class="form-control" id="memoria_ram" name="memoria_ram" 
                                   value="<?php echo htmlspecialchars($equipo['memoria_ram']); ?>"
                                   placeholder="Ej: Total: 16 GB">
                        </div>

                        <div class="mb-3">
                            <label for="disco_duro" class="form-label">Almacenamiento</label>
                            <textarea class="form-control" id="disco_duro" name="disco_duro" rows="3"
                                      placeholder="Ej: Unidad C: 174GB de 930GB (18% usado)"><?php echo htmlspecialchars($equipo['disco_duro']); ?></textarea>
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
                            <textarea class="form-control" id="sistema_operativo" name="sistema_operativo" rows="2"
                                      placeholder="Ej: Microsoft Windows 11 Pro"><?php echo htmlspecialchars($equipo['sistema_operativo']); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="usuario_sistema" class="form-label">Usuario del Sistema</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-user"></i>
                                </span>
                                <input type="text" class="form-control" id="usuario_sistema" name="usuario_sistema" 
                                       value="<?php echo htmlspecialchars($equipo['usuario_sistema']); ?>"
                                       placeholder="Ej: jperez">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información Adicional -->
                <div class="col-md-6">
                    <div class="form-section">
                        <h5 class="section-title">
                            <i class="fas fa-sticky-note me-2"></i>Información Adicional
                        </h5>
                        
                        <div class="mb-3">
                            <label for="ubicacion" class="form-label">Ubicación</label>
                            <input type="text" class="form-control" id="ubicacion" name="ubicacion" 
                                   value="<?php echo htmlspecialchars($equipo['ubicacion'] ?? ''); ?>"
                                   placeholder="Ej: Oficina 201, División de Informática">
                        </div>

                        <div class="mb-3">
                            <label for="responsable" class="form-label">Responsable</label>
                            <input type="text" class="form-control" id="responsable" name="responsable" 
                                   value="<?php echo htmlspecialchars($equipo['responsable'] ?? ''); ?>"
                                   placeholder="Ej: Juan Pérez">
                        </div>

                        <div class="mb-3">
                            <label for="observaciones" class="form-label">Observaciones</label>
                            <textarea class="form-control" id="observaciones" name="observaciones" rows="3"
                                      placeholder="Comentarios adicionales sobre el equipo..."><?php echo htmlspecialchars($equipo['observaciones'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Gestión y Garantía -->
                <div class="col-md-6">
                    <div class="form-section">
                        <h5 class="section-title">
                            <i class="fas fa-calendar-check me-2"></i>Gestión y Garantía
                        </h5>
                        
                        <div class="mb-3">
                            <label for="fecha_ultimo_mantenimiento" class="form-label">
                                <i class="fas fa-tools me-1"></i>Fecha Último Mantenimiento
                            </label>
                            <input type="date" class="form-control" id="fecha_ultimo_mantenimiento" name="fecha_ultimo_mantenimiento" 
                                   value="<?php echo $equipo['fecha_ultimo_mantenimiento'] ?? ''; ?>">
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Fecha del último mantenimiento preventivo o correctivo realizado al equipo
                            </div>
                            <?php if (!empty($equipo['fecha_ultimo_mantenimiento'])): ?>
                                <?php 
                                $fecha_mantenimiento = new DateTime($equipo['fecha_ultimo_mantenimiento']);
                                $hoy = new DateTime();
                                $diferencia = $hoy->diff($fecha_mantenimiento);
                                $dias_transcurridos = $diferencia->days;
                                ?>
                                <?php if ($dias_transcurridos > 365): ?>
                                <div class="date-danger">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    Han transcurrido <?php echo $dias_transcurridos; ?> días desde el último mantenimiento. Se recomienda programar mantenimiento.
                                </div>
                                <?php elseif ($dias_transcurridos > 180): ?>
                                <div class="date-warning">
                                    <i class="fas fa-exclamation-circle me-1"></i>
                                    Han transcurrido <?php echo $dias_transcurridos; ?> días desde el último mantenimiento.
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="garantia_vencimiento" class="form-label">
                                <i class="fas fa-shield-alt me-1"></i>Vencimiento de Garantía
                            </label>
                            <input type="date" class="form-control" id="garantia_vencimiento" name="garantia_vencimiento" 
                                   value="<?php echo $equipo['garantia_vencimiento'] ?? ''; ?>">
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Fecha en que vence la garantía del fabricante o proveedor
                            </div>
                            <?php if (!empty($equipo['garantia_vencimiento'])): ?>
                                <?php 
                                $fecha_garantia = new DateTime($equipo['garantia_vencimiento']);
                                $hoy = new DateTime();
                                $diferencia = $hoy->diff($fecha_garantia);
                                $dias_restantes = $diferencia->days;
                                $vencida = $hoy > $fecha_garantia;
                                ?>
                                <?php if ($vencida): ?>
                                <div class="date-danger">
                                    <i class="fas fa-times-circle me-1"></i>
                                    La garantía venció hace <?php echo $dias_restantes; ?> días (<?php echo $fecha_garantia->format('d/m/Y'); ?>).
                                </div>
                                <?php elseif ($dias_restantes <= 30): ?>
                                <div class="date-danger">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    La garantía vence en <?php echo $dias_restantes; ?> días (<?php echo $fecha_garantia->format('d/m/Y'); ?>).
                                </div>
                                <?php elseif ($dias_restantes <= 90): ?>
                                <div class="date-warning">
                                    <i class="fas fa-clock me-1"></i>
                                    La garantía vence en <?php echo $dias_restantes; ?> días (<?php echo $fecha_garantia->format('d/m/Y'); ?>).
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botones -->
            <div class="text-center mt-4">
                <a href="equipo_detalle.php?id=<?php echo $equipo_id; ?>" class="btn btn-secondary me-3">
                    <i class="fas fa-times me-2"></i>Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Actualizar Equipo
                </button>
                <a href="equipos_lista.php" class="btn btn-outline-primary ms-3">
                    <i class="fas fa-list me-2"></i>Volver a Lista
                </a>
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

        document.getElementById('ip_equipo').addEventListener('input', function() {
            validarIP(this);
        });

        document.getElementById('ip_local').addEventListener('input', function() {
            validarIP(this);
        });

        // Validación de fechas
        function validarFechas() {
            const fechaMantenimiento = document.getElementById('fecha_ultimo_mantenimiento').value;
            const fechaGarantia = document.getElementById('garantia_vencimiento').value;
            const hoy = new Date().toISOString().split('T')[0];
            
            // Validar que las fechas no sean futuras (excepto garantía)
            if (fechaMantenimiento && fechaMantenimiento > hoy) {
                alert('La fecha de último mantenimiento no puede ser futura');
                return false;
            }
            
            return true;
        }

        // Confirmar cambios importantes
        document.querySelector('form').addEventListener('submit', function(e) {
            if (!validarFechas()) {
                e.preventDefault();
                return false;
            }
            
            const originalIP = '<?php echo $equipo['ip_equipo']; ?>';
            const newIP = document.getElementById('ip_equipo').value;
            
            if (originalIP !== newIP) {
                if (!confirm('¿Está seguro de cambiar la IP del equipo de ' + originalIP + ' a ' + newIP + '?')) {
                    e.preventDefault();
                    return false;
                }
            }
        });

        // Alertas para fechas próximas a vencer
        document.getElementById('garantia_vencimiento').addEventListener('change', function() {
            const fechaGarantia = new Date(this.value);
            const hoy = new Date();
            const diferenciaDias = Math.ceil((fechaGarantia - hoy) / (1000 * 60 * 60 * 24));
            
            if (diferenciaDias <= 30 && diferenciaDias > 0) {
                this.classList.add('border-warning');
                if (!document.getElementById('garantia-warning')) {
                    const warning = document.createElement('div');
                    warning.id = 'garantia-warning';
                    warning.className = 'date-warning mt-2';
                    warning.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>La garantía vence en ' + diferenciaDias + ' días.';
                    this.parentNode.appendChild(warning);
                }
            } else if (diferenciaDias <= 0) {
                this.classList.add('border-danger');
                if (!document.getElementById('garantia-warning')) {
                    const warning = document.createElement('div');
                    warning.id = 'garantia-warning';
                    warning.className = 'date-danger mt-2';
                    warning.innerHTML = '<i class="fas fa-times-circle me-1"></i>La garantía está vencida.';
                    this.parentNode.appendChild(warning);
                }
            } else {
                this.classList.remove('border-warning', 'border-danger');
                const warning = document.getElementById('garantia-warning');
                if (warning) warning.remove();
            }
        });

        // Alerta para mantenimiento atrasado
        document.getElementById('fecha_ultimo_mantenimiento').addEventListener('change', function() {
            const fechaMantenimiento = new Date(this.value);
            const hoy = new Date();
            const diferenciaDias = Math.ceil((hoy - fechaMantenimiento) / (1000 * 60 * 60 * 24));
            
            if (diferenciaDias > 365) {
                this.classList.add('border-danger');
                if (!document.getElementById('mantenimiento-warning')) {
                    const warning = document.createElement('div');
                    warning.id = 'mantenimiento-warning';
                    warning.className = 'date-danger mt-2';
                    warning.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>Han transcurrido ' + diferenciaDias + ' días desde el último mantenimiento. Se recomienda programar mantenimiento.';
                    this.parentNode.appendChild(warning);
                }
            } else if (diferenciaDias > 180) {
                this.classList.add('border-warning');
                if (!document.getElementById('mantenimiento-warning')) {
                    const warning = document.createElement('div');
                    warning.id = 'mantenimiento-warning';
                    warning.className = 'date-warning mt-2';
                    warning.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i>Han transcurrido ' + diferenciaDias + ' días desde el último mantenimiento.';
                    this.parentNode.appendChild(warning);
                }
            } else {
                this.classList.remove('border-warning', 'border-danger');
                const warning = document.getElementById('mantenimiento-warning');
                if (warning) warning.remove();
            }
        });

        // Inicializar validaciones al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            // Disparar eventos de cambio para mostrar alertas existentes
            document.getElementById('garantia_vencimiento').dispatchEvent(new Event('change'));
            document.getElementById('fecha_ultimo_mantenimiento').dispatchEvent(new Event('change'));
        });
    </script>
</body>
</html>
