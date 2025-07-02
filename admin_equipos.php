<?php
require_once 'config_inventario.php';
checkSession();
requireAdmin(); // Solo administradores

$pdo = getDBConnection();

// Obtener estadísticas de administración
$stats = [];

// Total de equipos y distribución por origen
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN origen_datos = 'InventarioAgent' THEN 1 END) as con_agente,
        COUNT(CASE WHEN origen_datos = 'Script Bash' THEN 1 END) as script_bash,
        COUNT(CASE WHEN origen_datos = 'Manual' THEN 1 END) as manual,
        COUNT(CASE WHEN origen_datos = 'Web' THEN 1 END) as web
    FROM equipos_info
");
$stats['origen'] = $stmt->fetch();

// Equipos con problemas potenciales
$equipos_problemas = getEquiposBajoEspacio($pdo, 80);
$stats['problemas_disco'] = count($equipos_problemas);

// Equipos sin información crítica
$stmt = $pdo->query("
    SELECT 
        COUNT(CASE WHEN memoria_ram IS NULL OR memoria_ram = '' THEN 1 END) as sin_ram,
        COUNT(CASE WHEN disco_duro IS NULL OR disco_duro = '' THEN 1 END) as sin_disco,
        COUNT(CASE WHEN sistema_operativo IS NULL OR sistema_operativo = '' THEN 1 END) as sin_so,
        COUNT(CASE WHEN usuario_sistema IS NULL OR usuario_sistema = '' THEN 1 END) as sin_usuario
    FROM equipos_info
");
$stats['faltantes'] = $stmt->fetch();

// Equipos por estado
$stmt = $pdo->query("
    SELECT estado_equipo, COUNT(*) as cantidad
    FROM equipos_info 
    WHERE estado_equipo IS NOT NULL
    GROUP BY estado_equipo
    ORDER BY cantidad DESC
");
$stats['por_estado'] = $stmt->fetchAll();

// Duplicados potenciales
$stmt = $pdo->query("
    SELECT ip_equipo, COUNT(*) as cantidad
    FROM equipos_info 
    GROUP BY ip_equipo 
    HAVING COUNT(*) > 1
");
$duplicados_ip = $stmt->fetchAll();

$stmt = $pdo->query("
    SELECT nombre_equipo, COUNT(*) as cantidad
    FROM equipos_info 
    WHERE nombre_equipo IS NOT NULL AND nombre_equipo != ''
    GROUP BY nombre_equipo 
    HAVING COUNT(*) > 1
");
$duplicados_nombre = $stmt->fetchAll();

// Actividad reciente
$stmt = $pdo->query("
    SELECT 
        DATE(fecha_captura) as fecha,
        COUNT(*) as registros
    FROM equipos_info 
    WHERE fecha_captura >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
    GROUP BY DATE(fecha_captura)
    ORDER BY fecha DESC
    LIMIT 10
");
$actividad_reciente = $stmt->fetchAll();

// Equipos más antiguos sin actualizar
$stmt = $pdo->query("
    SELECT id, nombre_equipo, ip_equipo, fecha_captura, origen_datos,
           DATEDIFF(NOW(), fecha_captura) as dias_sin_actualizar
    FROM equipos_info 
    ORDER BY fecha_captura ASC
    LIMIT 10
");
$equipos_antiguos = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administración de Equipos - <?php echo APP_NAME; ?></title>
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
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
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

        .navbar-nav .nav-link:hover, .navbar-nav .nav-link.active {
            color: white !important;
            background-color: rgba(255,255,255,0.1);
            border-radius: 5px;
        }

        .main-content {
            /*padding: 20px 20px;*/
            max-width: 1400px;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.12);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 15px 20px;
            border: none;
        }

        .stats-card {
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
            color: white;
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .stats-card.primary {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
        }

        .stats-card.success {
            background: linear-gradient(135deg, var(--success-color), #20a338);
        }

        .stats-card.warning {
            background: linear-gradient(135deg, var(--warning-color), #e0a800);
            color: #333;
        }

        .stats-card.danger {
            background: linear-gradient(135deg, var(--danger-color), #c82333);
        }

        .stats-card .stats-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .stats-card .stats-label {
            font-size: 0.9rem;
            opacity: 0.9;
            font-weight: 500;
        }

        .admin-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .section-title {
            color: var(--primary-blue);
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--very-light-blue);
        }

        .table thead th {
            background-color: var(--very-light-blue);
            color: var(--primary-blue);
            font-weight: 600;
            border: none;
            font-size: 0.85rem;
        }

        .table tbody tr:hover {
            background-color: var(--very-light-blue);
        }

        .badge {
            border-radius: 15px;
            padding: 4px 8px;
            font-size: 0.7rem;
        }

        .btn {
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            border: none;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(30, 60, 114, 0.2);
        }

        .alert-custom {
            border-radius: 10px;
            border: none;
            padding: 15px 20px;
        }

        .progress {
            height: 6px;
            border-radius: 3px;
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

        .problem-item {
            background: #fff5f5;
            border-left: 4px solid var(--danger-color);
            padding: 10px 15px;
            margin-bottom: 10px;
            border-radius: 0 6px 6px 0;
        }

        .warning-item {
            background: #fffbf0;
            border-left: 4px solid var(--warning-color);
            padding: 10px 15px;
            margin-bottom: 10px;
            border-radius: 0 6px 6px 0;
        }

        .quick-action-btn {
            background-color: var(--primary-blue);
            border: none;
            border-radius: 8px;
            color: white;
            padding: 10px 15px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            margin: 5px;
            font-size: 0.85rem;
        }

        .quick-action-btn:hover {
            background-color: var(--secondary-blue);
            transform: translateY(-1px);
            color: white;
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
                        <a class="nav-link" href="index_inventario.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="equipos_lista.php">
                            <i class="fas fa-desktop me-1"></i>Ver Equipos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="buscar_equipos.php">
                            <i class="fas fa-search me-1"></i>Buscar Equipos
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-cog me-1"></i>Administración
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="equipo_nuevo.php">
                                <i class="fas fa-plus me-2"></i>Agregar Equipo
                            </a></li>
                            <li><a class="dropdown-item active" href="admin_equipos.php">
                                <i class="fas fa-edit me-2"></i>Gestionar Equipos
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="reportes_equipos.php">
                                <i class="fas fa-chart-bar me-2"></i>Reportes Avanzados
                            </a></li>
                        </ul>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i>
                            <?php echo $_SESSION['inv_full_name']; ?>
                            <span class="badge bg-warning ms-1">Admin</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="cambiar_password_inventario.php">
                                <i class="fas fa-key me-2"></i>Cambiar Contraseña
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout_inventario.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión
                            </a></li>
                        </ul>
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
                <li class="breadcrumb-item active">Administración</li>
            </ol>
        </nav>

        <!-- Header -->
        <div class="card">
            <div class="card-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4>
                            <i class="fas fa-cogs me-2"></i>
                            Panel de Administración de Equipos
                        </h4>
                        <p class="mb-0 mt-2 opacity-75">
                            Herramientas avanzadas para gestionar el inventario de equipos
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="badge bg-warning fs-6">
                            <i class="fas fa-shield-alt me-1"></i>Modo Administrador
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estadísticas de origen de datos -->
        <div class="row mb-3">
            <div class="col-md-3">
                <div class="stats-card primary">
                    <div class="stats-number"><?php echo $stats['origen']['total']; ?></div>
                    <div class="stats-label">
                        <i class="fas fa-desktop me-2"></i>Total Equipos
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card success">
                    <div class="stats-number"><?php echo $stats['origen']['con_agente']; ?></div>
                    <div class="stats-label">
                        <i class="fas fa-robot me-2"></i>Con Agente
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card warning">
                    <div class="stats-number"><?php echo $stats['problemas_disco']; ?></div>
                    <div class="stats-label">
                        <i class="fas fa-exclamation-triangle me-2"></i>Problemas Disco
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card danger">
                    <div class="stats-number"><?php echo count($duplicados_ip) + count($duplicados_nombre); ?></div>
                    <div class="stats-label">
                        <i class="fas fa-copy me-2"></i>Posibles Duplicados
                    </div>
                </div>
            </div>
        </div>

        <!-- Acciones Rápidas -->
        <div class="admin-section">
            <h5 class="section-title">
                <i class="fas fa-bolt me-2"></i>Acciones Rápidas
            </h5>
            
            <div class="text-center">
                <a href="equipo_nuevo.php" class="quick-action-btn">
                    <i class="fas fa-plus me-1"></i>Agregar Equipo
                </a>
                <a href="buscar_equipos.php" class="quick-action-btn">
                    <i class="fas fa-search me-1"></i>Búsqueda Avanzada
                </a>
                <a href="reportes_equipos.php" class="quick-action-btn">
                    <i class="fas fa-chart-bar me-1"></i>Generar Reportes
                </a>
                <a href="equipos_lista.php?orden=fecha_captura&direccion=asc" class="quick-action-btn">
                    <i class="fas fa-clock me-1"></i>Equipos Antiguos
                </a>
                <a href="buscar_equipos.php?buscar=1&min_disco_libre=20" class="quick-action-btn">
                    <i class="fas fa-hdd me-1"></i>Revisar Almacenamiento
                </a>
                <a href="#" onclick="exportarInventario()" class="quick-action-btn">
                    <i class="fas fa-download me-1"></i>Exportar Inventario
                </a>
            </div>
        </div>

        <div class="row">
            <!-- Problemas Detectados -->
            <div class="col-md-6">
                <div class="admin-section">
                    <h5 class="section-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>Problemas Detectados
                    </h5>
                    
                    <?php if ($stats['problemas_disco'] > 0): ?>
                    <div class="problem-item">
                        <strong><i class="fas fa-hdd me-2"></i>Equipos con poco espacio:</strong>
                        <?php echo $stats['problemas_disco']; ?> equipos con >80% de uso en disco
                        <div class="mt-2">
                            <a href="buscar_equipos.php?buscar=1&min_disco_libre=20" class="btn btn-sm btn-outline-danger">
                                Ver Equipos
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (count($duplicados_ip) > 0): ?>
                    <div class="problem-item">
                        <strong><i class="fas fa-network-wired me-2"></i>IPs duplicadas:</strong>
                        <?php echo count($duplicados_ip); ?> direcciones IP compartidas
                        <div class="mt-1">
                            <?php foreach (array_slice($duplicados_ip, 0, 3) as $dup): ?>
                            <small class="d-block">• <?php echo $dup['ip_equipo']; ?> (<?php echo $dup['cantidad']; ?> equipos)</small>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (count($duplicados_nombre) > 0): ?>
                    <div class="problem-item">
                        <strong><i class="fas fa-desktop me-2"></i>Nombres duplicados:</strong>
                        <?php echo count($duplicados_nombre); ?> nombres de equipo repetidos
                        <div class="mt-1">
                            <?php foreach (array_slice($duplicados_nombre, 0, 3) as $dup): ?>
                            <small class="d-block">• <?php echo $dup['nombre_equipo']; ?> (<?php echo $dup['cantidad']; ?> equipos)</small>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($stats['faltantes']['sin_usuario'] > 0): ?>
                    <div class="warning-item">
                        <strong><i class="fas fa-user me-2"></i>Sin usuario asignado:</strong>
                        <?php echo $stats['faltantes']['sin_usuario']; ?> equipos sin usuario
                    </div>
                    <?php endif; ?>

                    <?php if ($stats['faltantes']['sin_ram'] > 0): ?>
                    <div class="warning-item">
                        <strong><i class="fas fa-memory me-2"></i>Sin información de RAM:</strong>
                        <?php echo $stats['faltantes']['sin_ram']; ?> equipos
                    </div>
                    <?php endif; ?>

                    <?php if ($stats['problemas_disco'] == 0 && count($duplicados_ip) == 0 && count($duplicados_nombre) == 0): ?>
                    <div class="text-center text-success">
                        <i class="fas fa-check-circle fa-2x mb-2"></i>
                        <div>No se detectaron problemas críticos</div>
                        <small class="text-muted">El inventario está en buen estado</small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Distribución por Estado -->
            <div class="col-md-6">
                <div class="admin-section">
                    <h5 class="section-title">
                        <i class="fas fa-chart-pie me-2"></i>Distribución por Estado
                    </h5>
                    
                    <?php if (!empty($stats['por_estado'])): ?>
                    <?php foreach ($stats['por_estado'] as $estado): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-truncate">
                            <?php 
                            $icon = '';
                            $color = 'secondary';
                            switch($estado['estado_equipo']) {
                                case 'Activo':
                                    $icon = 'check-circle';
                                    $color = 'success';
                                    break;
                                case 'Inactivo':
                                    $icon = 'pause-circle';
                                    $color = 'warning';
                                    break;
                                case 'Mantenimiento':
                                    $icon = 'tools';
                                    $color = 'info';
                                    break;
                                case 'Dado de baja':
                                    $icon = 'times-circle';
                                    $color = 'danger';
                                    break;
                            }
                            ?>
                            <i class="fas fa-<?php echo $icon; ?> text-<?php echo $color; ?> me-2"></i>
                            <?php echo $estado['estado_equipo'] ?: 'Sin estado'; ?>
                        </span>
                        <span class="badge bg-<?php echo $color; ?>"><?php echo $estado['cantidad']; ?></span>
                    </div>
                    <div class="progress mb-3" style="height: 4px;">
                        <div class="progress-bar bg-<?php echo $color; ?>" role="progressbar" 
                             style="width: <?php echo ($estado['cantidad'] / $stats['origen']['total']) * 100; ?>%">
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div class="text-center text-muted">
                        <i class="fas fa-info-circle fa-2x mb-2"></i>
                        <div>No hay información de estados</div>
                        <small>Los equipos no tienen estados asignados</small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Distribución por Origen -->
            <div class="col-md-6">
                <div class="admin-section">
                    <h5 class="section-title">
                        <i class="fas fa-source-branch me-2"></i>Origen de Datos
                    </h5>
                    
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <div class="border rounded p-2">
                                <div class="text-success fs-4 fw-bold"><?php echo $stats['origen']['con_agente']; ?></div>
                                <small class="text-muted">
                                    <i class="fas fa-robot me-1"></i>Agente
                                </small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="border rounded p-2">
                                <div class="text-info fs-4 fw-bold"><?php echo $stats['origen']['script_bash']; ?></div>
                                <small class="text-muted">
                                    <i class="fas fa-terminal me-1"></i>Script
                                </small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-2">
                                <div class="text-warning fs-4 fw-bold"><?php echo $stats['origen']['manual']; ?></div>
                                <small class="text-muted">
                                    <i class="fas fa-hand-paper me-1"></i>Manual
                                </small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-2">
                                <div class="text-secondary fs-4 fw-bold"><?php echo $stats['origen']['web']; ?></div>
                                <small class="text-muted">
                                    <i class="fas fa-globe me-1"></i>Web
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Equipos Antiguos sin Actualizar -->
            <div class="col-md-6">
                <div class="admin-section">
                    <h5 class="section-title">
                        <i class="fas fa-clock me-2"></i>Equipos sin Actualizar
                    </h5>
                    
                    <?php if (!empty($equipos_antiguos)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Equipo</th>
                                    <th>Días</th>
                                    <th>Origen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($equipos_antiguos, 0, 5) as $equipo): ?>
                                <tr>
                                    <td>
                                        <div style="font-size: 0.8rem;">
                                            <div class="fw-bold"><?php echo $equipo['nombre_equipo']; ?></div>
                                            <small class="text-muted"><?php echo $equipo['ip_equipo']; ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $equipo['dias_sin_actualizar'] > 30 ? 'danger' : ($equipo['dias_sin_actualizar'] > 7 ? 'warning' : 'info'); ?>">
                                            <?php echo $equipo['dias_sin_actualizar']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?php echo $equipo['origen_datos']; ?></small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="text-center mt-2">
                        <a href="equipos_lista.php?orden=fecha_captura&direccion=asc" class="btn btn-sm btn-outline-primary">
                            Ver Todos los Equipos Antiguos
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="text-center text-muted">
                        <i class="fas fa-check-circle fa-2x mb-2 text-success"></i>
                        <div>Todos los equipos están actualizados</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Actividad Reciente -->
            <div class="col-md-12">
                <div class="admin-section">
                    <h5 class="section-title">
                        <i class="fas fa-chart-line me-2"></i>Actividad de Registro (Últimos 30 días)
                    </h5>
                    
                    <?php if (!empty($actividad_reciente)): ?>
                    <div class="row">
                        <?php foreach ($actividad_reciente as $actividad): ?>
                        <div class="col-md-2 mb-2">
                            <div class="text-center border rounded p-2">
                                <div class="fw-bold text-primary"><?php echo $actividad['registros']; ?></div>
                                <small class="text-muted"><?php echo formatDate($actividad['fecha'], 'd/m'); ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Muestra la cantidad de equipos registrados o actualizados por día
                        </small>
                    </div>
                    <?php else: ?>
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-calendar-times fa-2x mb-2"></i>
                        <div>No hay actividad reciente</div>
                        <small>No se han registrado equipos en los últimos 30 días</small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Herramientas de Mantenimiento -->
            <div class="col-md-12">
                <div class="admin-section">
                    <h5 class="section-title">
                        <i class="fas fa-tools me-2"></i>Herramientas de Mantenimiento
                    </h5>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card border-warning">
                                <div class="card-body text-center">
                                    <i class="fas fa-broom fa-2x text-warning mb-2"></i>
                                    <h6>Limpieza de Datos</h6>
                                    <p class="small text-muted">Detectar y corregir datos inconsistentes</p>
                                    <button class="btn btn-outline-warning btn-sm" onclick="iniciarLimpieza()">
                                        <i class="fas fa-play me-1"></i>Ejecutar
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card border-info">
                                <div class="card-body text-center">
                                    <i class="fas fa-sync fa-2x text-info mb-2"></i>
                                    <h6>Sincronización</h6>
                                    <p class="small text-muted">Forzar actualización desde agentes</p>
                                    <button class="btn btn-outline-info btn-sm" onclick="sincronizarDatos()">
                                        <i class="fas fa-download me-1"></i>Sincronizar
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card border-success">
                                <div class="card-body text-center">
                                    <i class="fas fa-file-export fa-2x text-success mb-2"></i>
                                    <h6>Exportar Datos</h6>
                                    <p class="small text-muted">Generar respaldo completo del inventario</p>
                                    <button class="btn btn-outline-success btn-sm" onclick="exportarInventario()">
                                        <i class="fas fa-download me-1"></i>Exportar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Nota:</strong> Las herramientas de mantenimiento están diseñadas para uso administrativo. 
                        Ejecute estas operaciones durante horarios de menor actividad para evitar interferencias.
                    </div>
                </div>
            </div>

            <!-- Configuración del Sistema -->
            <div class="col-md-12">
                <div class="admin-section">
                    <h5 class="section-title">
                        <i class="fas fa-cog me-2"></i>Configuración del Sistema
                    </h5>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-database me-2"></i>Estado de la Base de Datos</h6>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span>Total de registros:</span>
                                    <strong><?php echo $stats['origen']['total']; ?></strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Última actualización:</span>
                                    <strong><?php echo date('d/m/Y H:i'); ?></strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Versión del sistema:</span>
                                    <strong><?php echo APP_VERSION; ?></strong>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h6><i class="fas fa-users me-2"></i>Usuarios Administrativos</h6>
                            <div class="mb-3">
                                <?php 
                                global $ADMIN_USERS;
                                foreach ($ADMIN_USERS as $username => $info): 
                                ?>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span>
                                        <i class="fas fa-user-shield me-1"></i>
                                        <?php echo $info['nombre']; ?>
                                    </span>
                                    <small class="text-muted"><?php echo $username; ?></small>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <a href="reportes_equipos.php" class="btn btn-primary">
                            <i class="fas fa-chart-bar me-2"></i>Ir a Reportes Avanzados
                        </a>
                        <a href="buscar_equipos.php" class="btn btn-outline-primary">
                            <i class="fas fa-search me-2"></i>Búsqueda Avanzada
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function iniciarLimpieza() {
            if (confirm('¿Está seguro de que desea ejecutar la limpieza de datos?\n\nEsto puede tomar varios minutos y detectará:\n- Datos duplicados\n- Información inconsistente\n- Registros huérfanos')) {
                // Aquí iría la lógica de limpieza
                alert('Función de limpieza en desarrollo.\n\nPor ahora, revise manualmente los duplicados mostrados en el panel.');
            }
        }

        function sincronizarDatos() {
            if (confirm('¿Desea forzar la sincronización de datos desde los agentes?\n\nEsto intentará contactar todos los equipos con agentes activos.')) {
                // Aquí iría la lógica de sincronización
                alert('Función de sincronización en desarrollo.\n\nLos agentes se actualizarán automáticamente según su configuración.');
            }
        }

        function exportarInventario() {
            if (confirm('¿Desea generar un archivo de exportación completo del inventario?\n\nEsto incluirá todos los datos de equipos en formato CSV.')) {
                // Crear formulario para exportar
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'reportes_equipos.php';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'export_all';
                input.value = '1';
                
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
                document.body.removeChild(form);
            }
        }

        // Actualizar estadísticas cada 5 minutos
        function actualizarEstadisticas() {
            // Aquí se podría implementar una actualización via AJAX
            console.log('Actualizando estadísticas...');
        }

        // Auto-refresh cada 5 minutos
        setInterval(actualizarEstadisticas, 300000);

        // Mostrar tooltips
        document.addEventListener('DOMContentLoaded', function() {
            const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            tooltips.forEach(function(tooltip) {
                new bootstrap.Tooltip(tooltip);
            });
        });

        // Función para resaltar problemas críticos
        function resaltarProblemas() {
            const problemasDiscos = <?php echo $stats['problemas_disco']; ?>;
            const duplicados = <?php echo count($duplicados_ip) + count($duplicados_nombre); ?>;
            
            if (problemasDiscos > 5) {
                console.warn('Advertencia: Muchos equipos con poco espacio en disco');
            }
            
            if (duplicados > 0) {
                console.warn('Advertencia: Se detectaron equipos duplicados');
            }
        }

        // Ejecutar al cargar la página
        document.addEventListener('DOMContentLoaded', resaltarProblemas);
    </script>
</body>
</html>
