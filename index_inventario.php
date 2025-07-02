<?php
require_once 'config_inventario.php';
checkSession();

$pdo = getDBConnection();

// Obtener estadísticas para el dashboard
$stats = [];

// Total de equipos
$stmt = $pdo->query("SELECT COUNT(*) as total FROM equipos_info");
$stats['total_equipos'] = $stmt->fetch()['total'];

// Equipos por sistema operativo
$stmt = $pdo->query("
    SELECT 
        CASE 
            WHEN sistema_operativo LIKE '%Windows 11%' THEN 'Windows 11'
            WHEN sistema_operativo LIKE '%Windows 10%' THEN 'Windows 10'
            WHEN sistema_operativo LIKE '%Windows%' THEN 'Windows (Otros)'
            WHEN sistema_operativo LIKE '%Ubuntu%' OR sistema_operativo LIKE '%Linux%' THEN 'Linux/Ubuntu'
            ELSE 'Otros'
        END as so_categoria,
        COUNT(*) as cantidad
    FROM equipos_info 
    WHERE sistema_operativo IS NOT NULL 
    GROUP BY so_categoria
    ORDER BY cantidad DESC
");
$stats['por_so'] = $stmt->fetchAll();

// Equipos por fabricante
$stmt = $pdo->query("
    SELECT fabricante, COUNT(*) as cantidad 
    FROM equipos_info 
    WHERE fabricante IS NOT NULL AND fabricante != 'No disponible'
    GROUP BY fabricante 
    ORDER BY cantidad DESC 
    LIMIT 5
");
$stats['por_fabricante'] = $stmt->fetchAll();

// Equipos registrados en el último mes
$stmt = $pdo->query("
    SELECT COUNT(*) as total 
    FROM equipos_info 
    WHERE fecha_captura >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
");
$stats['ultimo_mes'] = $stmt->fetch()['total'];

// Equipos con poco espacio libre (menos del 30%)
$equipos_bajo_espacio = getEquiposBajoEspacio($pdo, 70);
$stats['bajo_espacio'] = count($equipos_bajo_espacio);

// Equipos recientes (últimos 10)
$stmt = $pdo->query("
    SELECT id, nombre_equipo, ip_equipo, sistema_operativo, fabricante, 
           modelo, fecha_captura, disco_duro, usuario_sistema
    FROM equipos_info
    ORDER BY fecha_captura DESC
    LIMIT 10
");
$equipos_recientes = $stmt->fetchAll();

// Estado general del sistema
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN fecha_captura >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as activos_semana,
        COUNT(CASE WHEN origen_datos = 'InventarioAgent' THEN 1 END) as con_agente,
        COUNT(CASE WHEN memoria_ram IS NOT NULL THEN 1 END) as con_ram_info
    FROM equipos_info
");
$estado_sistema = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
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
            --info-color: #17a2b8;
            --danger-color: #dc3545;
            --secondary-color: #6c757d;
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

        .navbar-nav .nav-link.active {
            color: white !important;
            background-color: rgba(255,255,255,0.2);
        }

        .main-content {
            padding: 20px 0;
            max-width: 1400px;
            margin: 0 auto;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
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

        .card-header h5 {
            margin: 0;
            font-weight: 600;
            font-size: 1rem;
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
            background-color: var(--primary-blue);
        }

        .stats-card.success {
            background-color: var(--success-color);
        }

        .stats-card.warning {
            background-color: var(--warning-color);
            color: #333;
        }

        .stats-card.info {
            background-color: var(--info-color);
        }

        .stats-card.danger {
            background-color: var(--danger-color);
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

        .quick-action-btn {
            background-color: var(--primary-blue);
            border: none;
            border-radius: 8px;
            color: white;
            padding: 12px 20px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            margin: 8px 4px;
        }

        .quick-action-btn:hover {
            background-color: var(--secondary-blue);
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(30, 60, 114, 0.2);
            color: white;
        }

        .table {
            border-radius: 8px;
            overflow: hidden;
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
            padding: 4px 10px;
            font-size: 0.75rem;
        }

        .logo-container {
            text-align: center;
            margin-bottom: 25px;
        }

        .logo-container h1 {
            color: var(--primary-blue);
            font-weight: 700;
            margin: 0;
            font-size: 1.8rem;
        }

        .logo-container p {
            color: var(--secondary-blue);
            margin: 5px 0 0 0;
            font-size: 0.9rem;
        }

        .welcome-section {
            background: var(--very-light-blue);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            border-left: 4px solid var(--light-blue);
        }

        .welcome-section h3 {
            font-size: 1.3rem;
            margin-bottom: 8px;
        }

        .card-body {
            padding: 15px;
        }

        .list-group-item {
            border: none;
            padding: 8px 0;
        }

        .progress {
            height: 4px;
            border-radius: 2px;
        }

        .progress-bar {
            background-color: var(--primary-blue);
        }

        .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }

        .status-indicator.success { background-color: var(--success-color); }
        .status-indicator.warning { background-color: var(--warning-color); }
        .status-indicator.danger { background-color: var(--danger-color); }
        .status-indicator.info { background-color: var(--info-color); }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index_inventario.php">
                <img src="logo_INDE.png" alt="INDE" style="height: 40px; width: auto; margin-right: 8px;">
                INDE - Inventario de Equipos
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index_inventario.php">
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
                    <?php if ($_SESSION['inv_is_admin']): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-cog me-1"></i>Administración
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="equipo_nuevo.php">
                                <i class="fas fa-plus me-2"></i>Agregar Equipo
                            </a></li>
                            <li><a class="dropdown-item" href="admin_equipos.php">
                                <i class="fas fa-edit me-2"></i>Gestionar Equipos
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="reportes_equipos.php">
                                <i class="fas fa-chart-bar me-2"></i>Reportes Avanzados
                            </a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i>
                            <?php echo $_SESSION['inv_full_name']; ?>
                            <?php if ($_SESSION['inv_is_admin']): ?>
                                <span class="badge bg-warning ms-1">Admin</span>
                            <?php endif; ?>
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
        <!-- Logo y Título -->
        <div class="logo-container">
            <img src="logo_INDE.png" alt="INDE Logo" style="width: 200px; height: 80px; object-fit: contain; margin-bottom: 8px;">
            <h1>Sistema de Inventario de Equipos</h1>
            <p>División de Informática - Instituto Nacional de Electrificación</p>
        </div>

        <!-- Sección de Bienvenida -->
        <div class="welcome-section">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h3 class="mb-1">
                        <i class="fas fa-user-circle text-primary me-2"></i>
                        Bienvenido, <?php echo $_SESSION['inv_full_name']; ?>
                    </h3>
                    <p class="mb-0 text-muted">
                        <?php if ($_SESSION['inv_is_admin']): ?>
                            <i class="fas fa-shield-alt text-warning me-1"></i>
                            Administrador del Sistema
                        <?php else: ?>
                            <i class="fas fa-user text-info me-1"></i>
                            Usuario de Solo Lectura
                        <?php endif; ?>
                        - Última actividad: <?php echo date('d/m/Y H:i'); ?>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <?php if ($_SESSION['inv_is_admin']): ?>
                    <a href="equipo_nuevo.php" class="quick-action-btn">
                        <i class="fas fa-plus me-2"></i>Agregar Equipo
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="row mb-3">
            <div class="col-md-3">
                <div class="stats-card primary">
                    <div class="stats-number"><?php echo $stats['total_equipos']; ?></div>
                    <div class="stats-label">
                        <i class="fas fa-desktop me-2"></i>Total Equipos
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card success">
                    <div class="stats-number"><?php echo $stats['ultimo_mes']; ?></div>
                    <div class="stats-label">
                        <i class="fas fa-calendar-month me-2"></i>Registrados (30 días)
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card <?php echo $stats['bajo_espacio'] > 0 ? 'danger' : 'info'; ?>">
                    <div class="stats-number"><?php echo $stats['bajo_espacio']; ?></div>
                    <div class="stats-label">
                        <i class="fas fa-hdd me-2"></i>Bajo Espacio (>70%)
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card warning">
                    <div class="stats-number"><?php echo $estado_sistema['con_agente']; ?></div>
                    <div class="stats-label">
                        <i class="fas fa-robot me-2"></i>Con Agente Activo
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Acciones Rápidas -->
            <div class="col-md-4 mb-3">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-bolt me-2"></i>Acciones Rápidas</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="equipos_lista.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-list me-2"></i>Ver Todos los Equipos
                            </a>
                            <a href="buscar_equipos.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-search me-2"></i>Buscar Equipos
                            </a>
                            <?php if ($_SESSION['inv_is_admin']): ?>
                            <a href="equipo_nuevo.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-plus me-2"></i>Agregar Nuevo Equipo
                            </a>
                            <a href="admin_equipos.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-cog me-2"></i>Administrar Equipos
                            </a>
                            <?php endif; ?>
                            <a href="reportes_equipos.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-chart-bar me-2"></i>Ver Reportes
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Estado del Sistema -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle me-2"></i>Estado del Sistema</h5>
                    </div>
                    <div class="card-body">
                        <div class="small">
                            <div class="d-flex justify-content-between mb-2">
                                <span><span class="status-indicator success"></span>Activos (7 días)</span>
                                <strong><?php echo $estado_sistema['activos_semana']; ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span><span class="status-indicator info"></span>Con Info RAM</span>
                                <strong><?php echo $estado_sistema['con_ram_info']; ?></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span><span class="status-indicator warning"></span>Con Agente</span>
                                <strong><?php echo $estado_sistema['con_agente']; ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Equipos por SO -->
            <div class="col-md-4 mb-3">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-pie me-2"></i>Sistemas Operativos</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($stats['por_so'] as $so): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-truncate small"><?php echo $so['so_categoria']; ?></span>
                            <span class="badge bg-primary"><?php echo $so['cantidad']; ?></span>
                        </div>
                        <div class="progress mb-2" style="height: 4px;">
                            <div class="progress-bar" role="progressbar" 
                                 style="width: <?php echo $stats['total_equipos'] > 0 ? ($so['cantidad'] / $stats['total_equipos']) * 100 : 0; ?>%">
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <hr class="my-3">
                        <h6 class="small fw-bold text-muted">Top Fabricantes</h6>
                        <?php foreach ($stats['por_fabricante'] as $fab): ?>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-truncate small"><?php echo $fab['fabricante']; ?></span>
                            <span class="badge bg-secondary"><?php echo $fab['cantidad']; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Equipos Recientes y Alertas -->
            <div class="col-md-4 mb-3">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-clock me-2"></i>Equipos Recientes</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($equipos_recientes)): ?>
                        <p class="text-muted text-center small">
                            <i class="fas fa-info-circle me-2"></i>
                            No hay equipos registrados
                        </p>
                        <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach (array_slice($equipos_recientes, 0, 5) as $equipo): ?>
                            <?php 
                            $estado_disco = getEstadoDisco($equipo['disco_duro']);
                            ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="me-auto">
                                        <div class="fw-bold text-primary small">
                                            <?php echo $equipo['nombre_equipo']; ?>
                                        </div>
                                        <div class="text-muted small">
                                            <i class="fas fa-network-wired me-1"></i>
                                            <?php echo $equipo['ip_equipo']; ?>
                                        </div>
                                        <div class="text-muted small">
                                            <i class="fas fa-user me-1"></i>
                                            <?php echo $equipo['usuario_sistema'] ?: 'N/A'; ?>
                                        </div>
                                        <?php if (!empty($equipo['disco_duro'])): ?>
                                        <div class="small mt-1">
                                            <i class="fas fa-<?php echo $estado_disco['icono']; ?> text-<?php echo $estado_disco['clase']; ?> me-1"></i>
                                            <span class="text-<?php echo $estado_disco['clase']; ?>">
                                                <?php echo calcularPorcentajeUso($equipo['disco_duro']); ?>% usado
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo formatDate($equipo['fecha_captura'], 'd/m'); ?>
                                    </small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-center mt-2">
                            <a href="equipos_lista.php" class="btn btn-sm btn-outline-primary">
                                Ver todos los equipos
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Alertas de Espacio en Disco -->
                <?php if (!empty($equipos_bajo_espacio)): ?>
                <div class="card mt-3">
                    <div class="card-header bg-danger text-white">
                        <h5><i class="fas fa-exclamation-triangle me-2"></i>Alertas de Disco</h5>
                    </div>
                    <div class="card-body">
                        <div class="small">
                            <?php foreach (array_slice($equipos_bajo_espacio, 0, 3) as $equipo): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <div class="fw-bold"><?php echo $equipo['nombre_equipo']; ?></div>
                                    <div class="text-muted"><?php echo $equipo['usuario_sistema']; ?></div>
                                </div>
                                <span class="badge bg-danger">
                                    <?php echo $equipo['porcentaje_uso']; ?>%
                                </span>
                            </div>
                            <?php endforeach; ?>
                            <?php if (count($equipos_bajo_espacio) > 3): ?>
                            <div class="text-center mt-2">
                                <small class="text-muted">
                                    +<?php echo count($equipos_bajo_espacio) - 3; ?> más con problemas de espacio
                                </small>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>
