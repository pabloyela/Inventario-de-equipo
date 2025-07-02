<?php
require_once 'config_inventario.php';
checkSession();

$pdo = getDBConnection();

// Procesar exportación si se solicita
if (isset($_POST['export_all']) || isset($_GET['export'])) {
    $format = isset($_GET['format']) ? $_GET['format'] : 'csv';
    
    // Obtener todos los equipos
    $stmt = $pdo->query("
        SELECT 
            id, nombre_equipo, ip_equipo, ip_local, procesador, memoria_ram, 
            disco_duro, tarjeta_red, usuario_sistema, fecha_captura, user_agent,
            navegador, sistema_operativo, fabricante, modelo, numero_serie,
            origen_datos, observaciones, ubicacion, responsable, estado_equipo,
            created_at, updated_at
        FROM equipos_info 
        ORDER BY nombre_equipo
    ");
    $equipos = $stmt->fetchAll();
    
    if ($format === 'csv') {
        // Configurar headers para descarga CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="inventario_equipos_' . date('Y-m-d_H-i') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Crear archivo CSV
        $output = fopen('php://output', 'w');
        
        // BOM para UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Headers del CSV
        fputcsv($output, [
            'ID', 'Nombre_Equipo', 'IP_Externa', 'IP_Local', 'Procesador', 'Memoria_RAM',
            'Disco_Duro', 'Tarjeta_Red', 'Usuario_Sistema', 'Fecha_Captura', 'User_Agent',
            'Navegador', 'Sistema_Operativo', 'Fabricante', 'Modelo', 'Numero_Serie',
            'Origen_Datos', 'Observaciones', 'Ubicacion', 'Responsable', 'Estado_Equipo',
            'Fecha_Creacion', 'Ultima_Actualizacion'
        ], ';');
        
        // Datos
        foreach ($equipos as $equipo) {
            fputcsv($output, [
                $equipo['id'],
                $equipo['nombre_equipo'],
                $equipo['ip_equipo'],
                $equipo['ip_local'],
                $equipo['procesador'],
                $equipo['memoria_ram'],
                $equipo['disco_duro'],
                $equipo['tarjeta_red'],
                $equipo['usuario_sistema'],
                $equipo['fecha_captura'],
                $equipo['user_agent'],
                $equipo['navegador'],
                $equipo['sistema_operativo'],
                $equipo['fabricante'],
                $equipo['modelo'],
                $equipo['numero_serie'],
                $equipo['origen_datos'],
                $equipo['observaciones'],
                $equipo['ubicacion'],
                $equipo['responsable'],
                $equipo['estado_equipo'],
                $equipo['created_at'],
                $equipo['updated_at']
            ], ';');
        }
        
        fclose($output);
        exit;
    }
}

// Obtener estadísticas para reportes
$reportes = [];

// Resumen general
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_equipos,
        COUNT(DISTINCT fabricante) as total_fabricantes,
        COUNT(DISTINCT usuario_sistema) as total_usuarios,
        COUNT(CASE WHEN fecha_captura >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as equipos_mes,
        COUNT(CASE WHEN estado_equipo = 'Activo' THEN 1 END) as equipos_activos
    FROM equipos_info
");
$reportes['resumen'] = $stmt->fetch();

// Top fabricantes
$stmt = $pdo->query("
    SELECT fabricante, COUNT(*) as cantidad, 
           ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM equipos_info)), 1) as porcentaje
    FROM equipos_info 
    WHERE fabricante IS NOT NULL AND fabricante != 'No disponible'
    GROUP BY fabricante 
    ORDER BY cantidad DESC 
    LIMIT 10
");
$reportes['fabricantes'] = $stmt->fetchAll();

// Distribución por SO
$stmt = $pdo->query("
    SELECT 
        CASE 
            WHEN sistema_operativo LIKE '%Windows 11%' THEN 'Windows 11'
            WHEN sistema_operativo LIKE '%Windows 10%' THEN 'Windows 10'
            WHEN sistema_operativo LIKE '%Windows%' THEN 'Windows (Otros)'
            WHEN sistema_operativo LIKE '%Ubuntu%' OR sistema_operativo LIKE '%Linux%' THEN 'Linux/Ubuntu'
            ELSE 'Otros'
        END as so_categoria,
        COUNT(*) as cantidad,
        ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM equipos_info)), 1) as porcentaje
    FROM equipos_info 
    WHERE sistema_operativo IS NOT NULL 
    GROUP BY so_categoria
    ORDER BY cantidad DESC
");
$reportes['sistemas'] = $stmt->fetchAll();

// Análisis de memoria RAM
$stmt = $pdo->query("
    SELECT 
        CASE 
            WHEN memoria_ram LIKE '%32%GB%' OR memoria_ram LIKE '%32 GB%' THEN '32+ GB'
            WHEN memoria_ram LIKE '%16%GB%' OR memoria_ram LIKE '%16 GB%' THEN '16 GB'
            WHEN memoria_ram LIKE '%8%GB%' OR memoria_ram LIKE '%8 GB%' THEN '8 GB'
            WHEN memoria_ram LIKE '%4%GB%' OR memoria_ram LIKE '%4 GB%' THEN '4 GB'
            WHEN memoria_ram IS NOT NULL AND memoria_ram != '' THEN 'Otra cantidad'
            ELSE 'Sin información'
        END as ram_categoria,
        COUNT(*) as cantidad
    FROM equipos_info 
    GROUP BY ram_categoria
    ORDER BY 
        CASE ram_categoria
            WHEN '32+ GB' THEN 1
            WHEN '16 GB' THEN 2
            WHEN '8 GB' THEN 3
            WHEN '4 GB' THEN 4
            WHEN 'Otra cantidad' THEN 5
            ELSE 6
        END
");
$reportes['memoria'] = $stmt->fetchAll();

// Análisis de espacio en disco
$equipos_disco = [];
$stmt = $pdo->query("
    SELECT nombre_equipo, disco_duro, usuario_sistema, ip_equipo
    FROM equipos_info 
    WHERE disco_duro IS NOT NULL AND disco_duro != ''
    ORDER BY nombre_equipo
");
$equipos_temp = $stmt->fetchAll();

foreach ($equipos_temp as $equipo) {
    $porcentaje = calcularPorcentajeUso($equipo['disco_duro']);
    if ($porcentaje > 0) {
        $estado = getEstadoDisco($equipo['disco_duro']);
        $equipos_disco[] = [
            'nombre' => $equipo['nombre_equipo'],
            'usuario' => $equipo['usuario_sistema'],
            'ip' => $equipo['ip_equipo'],
            'porcentaje_uso' => $porcentaje,
            'estado' => $estado['estado'],
            'clase' => $estado['clase']
        ];
    }
}

// Ordenar por porcentaje de uso descendente
usort($equipos_disco, function($a, $b) {
    return $b['porcentaje_uso'] - $a['porcentaje_uso'];
});

// Actividad por mes
$stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(fecha_captura, '%Y-%m') as mes,
        COUNT(*) as registros
    FROM equipos_info 
    WHERE fecha_captura >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(fecha_captura, '%Y-%m')
    ORDER BY mes DESC
    LIMIT 12
");
$reportes['actividad_mensual'] = $stmt->fetchAll();

// Equipos por origen de datos
$stmt = $pdo->query("
    SELECT origen_datos, COUNT(*) as cantidad
    FROM equipos_info 
    GROUP BY origen_datos
    ORDER BY cantidad DESC
");
$reportes['origen_datos'] = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes y Análisis - <?php echo APP_NAME; ?></title>
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
            padding: 20px 0;
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

        .stats-card.info {
            background: linear-gradient(135deg, var(--info-color), #138496);
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

        .report-section {
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

        .progress {
            height: 6px;
            border-radius: 3px;
        }

        .progress-bar {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
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

        .export-section {
            background: var(--very-light-blue);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid var(--light-blue);
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

        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
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
                            <li><a class="dropdown-item active" href="reportes_equipos.php">
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
                <li class="breadcrumb-item active">Reportes y Análisis</li>
            </ol>
        </nav>

        <!-- Header -->
        <div class="card">
            <div class="card-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4>
                            <i class="fas fa-chart-bar me-2"></i>
                            Reportes y Análisis del Inventario
                        </h4>
                        <p class="mb-0 mt-2 opacity-75">
                            Análisis detallado y estadísticas del inventario de equipos
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="dropdown">
                            <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-download me-1"></i>Exportar
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="?export=1&format=csv">
                                    <i class="fas fa-file-csv me-2"></i>Exportar a CSV
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="imprimirReporte()">
                                    <i class="fas fa-print me-2"></i>Imprimir Reporte
                                </a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resumen Ejecutivo -->
        <div class="row mb-3">
            <div class="col-md-3">
                <div class="stats-card primary">
                    <div class="stats-number"><?php echo $reportes['resumen']['total_equipos']; ?></div>
                    <div class="stats-label">
                        <i class="fas fa-desktop me-2"></i>Total Equipos
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card success">
                    <div class="stats-number"><?php echo $reportes['resumen']['equipos_activos']; ?></div>
                    <div class="stats-label">
                        <i class="fas fa-check-circle me-2"></i>Equipos Activos
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card info">
                    <div class="stats-number"><?php echo $reportes['resumen']['total_fabricantes']; ?></div>
                    <div class="stats-label">
                        <i class="fas fa-industry me-2"></i>Fabricantes
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card warning">
                    <div class="stats-number"><?php echo $reportes['resumen']['equipos_mes']; ?></div>
                    <div class="stats-label">
                        <i class="fas fa-calendar-month me-2"></i>Nuevos (30 días)
                    </div>
                </div>
            </div>
        </div>

        <!-- Exportación Rápida -->
        <div class="export-section">
            <h5><i class="fas fa-download me-2"></i>Exportación de Datos</h5>
            <p class="mb-3">Genere reportes personalizados en diferentes formatos para análisis externo.</p>
            
            <div class="row">
                <div class="col-md-4">
                    <div class="text-center">
                        <a href="?export=1&format=csv" class="btn btn-primary">
                            <i class="fas fa-file-csv me-2"></i>Exportar Todo (CSV)
                        </a>
                        <div class="mt-2">
                            <small class="text-muted">Inventario completo en formato CSV</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center">
                        <button class="btn btn-outline-primary" onclick="exportarFiltrado()">
                            <i class="fas fa-filter me-2"></i>Exportar Filtrado
                        </button>
                        <div class="mt-2">
                            <small class="text-muted">Solo equipos que cumplan criterios</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center">
                        <button class="btn btn-outline-secondary" onclick="imprimirReporte()">
                            <i class="fas fa-print me-2"></i>Imprimir Reporte
                        </button>
                        <div class="mt-2">
                            <small class="text-muted">Versión optimizada para impresión</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Distribución por Fabricantes -->
            <div class="col-md-6">
                <div class="report-section">
                    <h5 class="section-title">
                        <i class="fas fa-industry me-2"></i>Distribución por Fabricantes
                    </h5>
                    
                    <?php foreach ($reportes['fabricantes'] as $fabricante): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-truncate"><?php echo $fabricante['fabricante']; ?></span>
                        <div class="text-end">
                            <span class="badge bg-primary"><?php echo $fabricante['cantidad']; ?></span>
                            <small class="text-muted ms-2"><?php echo $fabricante['porcentaje']; ?>%</small>
                        </div>
                    </div>
                    <div class="progress mb-3" style="height: 4px;">
                        <div class="progress-bar" role="progressbar" 
                             style="width: <?php echo $fabricante['porcentaje']; ?>%">
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Sistemas Operativos -->
            <div class="col-md-6">
                <div class="report-section">
                    <h5 class="section-title">
                        <i class="fas fa-laptop me-2"></i>Sistemas Operativos
                    </h5>
                    
                    <?php foreach ($reportes['sistemas'] as $sistema): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-truncate">
                            <?php if (strpos($sistema['so_categoria'], 'Windows 11') !== false): ?>
                                <i class="fab fa-windows text-primary me-2"></i>
                            <?php elseif (strpos($sistema['so_categoria'], 'Windows') !== false): ?>
                                <i class="fab fa-windows text-info me-2"></i>
                            <?php elseif (strpos($sistema['so_categoria'], 'Linux') !== false): ?>
                                <i class="fab fa-linux text-warning me-2"></i>
                            <?php else: ?>
                                <i class="fas fa-desktop text-secondary me-2"></i>
                            <?php endif; ?>
                            <?php echo $sistema['so_categoria']; ?>
                        </span>
                        <div class="text-end">
                            <span class="badge bg-secondary"><?php echo $sistema['cantidad']; ?></span>
                            <small class="text-muted ms-2"><?php echo $sistema['porcentaje']; ?>%</small>
                        </div>
                    </div>
                    <div class="progress mb-3" style="height: 4px;">
                        <div class="progress-bar bg-secondary" role="progressbar" 
                             style="width: <?php echo $sistema['porcentaje']; ?>%">
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Análisis de Memoria RAM -->
            <div class="col-md-6">
                <div class="report-section">
                    <h5 class="section-title">
                        <i class="fas fa-memory me-2"></i>Distribución de Memoria RAM
                    </h5>
                    
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Capacidad</th>
                                    <th>Cantidad</th>
                                    <th>%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportes['memoria'] as $mem): ?>
                                <tr>
                                    <td>
                                        <?php if ($mem['ram_categoria'] === 'Sin información'): ?>
                                            <i class="fas fa-question-circle text-muted me-1"></i>
                                        <?php else: ?>
                                            <i class="fas fa-memory text-info me-1"></i>
                                        <?php endif; ?>
                                        <?php echo $mem['ram_categoria']; ?>
                                    </td>
                                    <td><?php echo $mem['cantidad']; ?></td>
                                    <td>
                                        <?php 
                                        $porcentaje = round(($mem['cantidad'] / $reportes['resumen']['total_equipos']) * 100, 1);
                                        echo $porcentaje; 
                                        ?>%
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Origen de Datos -->
            <div class="col-md-6">
                <div class="report-section">
                    <h5 class="section-title">
                        <i class="fas fa-source-branch me-2"></i>Origen de los Datos
                    </h5>
                    
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Fuente</th>
                                    <th>Cantidad</th>
                                    <th>%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportes['origen_datos'] as $origen): ?>
                                <tr>
                                    <td>
                                        <?php if ($origen['origen_datos'] === 'InventarioAgent'): ?>
                                            <i class="fas fa-robot text-success me-1"></i>
                                        <?php elseif ($origen['origen_datos'] === 'Script Bash'): ?>
                                            <i class="fas fa-terminal text-info me-1"></i>
                                        <?php elseif ($origen['origen_datos'] === 'Manual'): ?>
                                            <i class="fas fa-hand-paper text-warning me-1"></i>
                                        <?php else: ?>
                                            <i class="fas fa-globe text-secondary me-1"></i>
                                        <?php endif; ?>
                                        <?php echo $origen['origen_datos']; ?>
                                    </td>
                                    <td><?php echo $origen['cantidad']; ?></td>
                                    <td>
                                        <?php 
                                        $porcentaje = round(($origen['cantidad'] / $reportes['resumen']['total_equipos']) * 100, 1);
                                        echo $porcentaje; 
                                        ?>%
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Estado del Almacenamiento -->
            <div class="col-md-12">
                <div class="report-section">
                    <h5 class="section-title">
                        <i class="fas fa-hdd me-2"></i>Análisis de Almacenamiento
                    </h5>
                    
                    <?php if (!empty($equipos_disco)): ?>
                    <div class="row mb-3">
                        <div class="col-md-3 text-center">
                            <div class="border rounded p-2">
                                <div class="text-success fs-5 fw-bold">
                                    <?php echo count(array_filter($equipos_disco, function($e) { return $e['porcentaje_uso'] < 50; })); ?>
                                </div>
                                <small class="text-muted">Espacio Óptimo (<50%)</small>
                            </div>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="border rounded p-2">
                                <div class="text-warning fs-5 fw-bold">
                                    <?php echo count(array_filter($equipos_disco, function($e) { return $e['porcentaje_uso'] >= 50 && $e['porcentaje_uso'] < 80; })); ?>
                                </div>
                                <small class="text-muted">Alerta (50-80%)</small>
                            </div>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="border rounded p-2">
                                <div class="text-danger fs-5 fw-bold">
                                    <?php echo count(array_filter($equipos_disco, function($e) { return $e['porcentaje_uso'] >= 80; })); ?>
                                </div>
                                <small class="text-muted">Crítico (>80%)</small>
                            </div>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="border rounded p-2">
                                <div class="text-muted fs-5 fw-bold">
                                    <?php echo $reportes['resumen']['total_equipos'] - count($equipos_disco); ?>
                                </div>
                                <small class="text-muted">Sin Información</small>
                            </div>
                        </div>
                    </div>

                    <h6>Equipos con Mayor Uso de Disco (Top 10)</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Equipo</th>
                                    <th>Usuario</th>
                                    <th>IP</th>
                                    <th>Uso de Disco</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($equipos_disco, 0, 10) as $equipo): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo $equipo['nombre']; ?></td>
                                    <td><?php echo $equipo['usuario'] ?: '-'; ?></td>
                                    <td><small><?php echo $equipo['ip']; ?></small></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="status-indicator <?php echo $equipo['clase']; ?>"></span>
                                            <strong class="text-<?php echo $equipo['clase']; ?>">
                                                <?php echo $equipo['porcentaje_uso']; ?>%
                                            </strong>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $equipo['clase']; ?>">
                                            <?php echo ucfirst($equipo['estado']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-info-circle fa-2x mb-2"></i>
                        <div>No hay información de almacenamiento disponible</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Actividad Mensual -->
            <div class="col-md-12">
                <div class="report-section">
                    <h5 class="section-title">
                        <i class="fas fa-chart-line me-2"></i>Actividad de Registro (Últimos 12 meses)
                    </h5>
                    
                    <?php if (!empty($reportes['actividad_mensual'])): ?>
                    <div class="row">
                        <?php foreach (array_reverse($reportes['actividad_mensual']) as $mes): ?>
                        <div class="col-md-2 mb-2">
                            <div class="text-center border rounded p-2">
                                <div class="fw-bold text-primary"><?php echo $mes['registros']; ?></div>
                                <small class="text-muted">
                                    <?php 
                                    $fecha = DateTime::createFromFormat('Y-m', $mes['mes']);
                                    echo $fecha->format('M Y'); 
                                    ?>
                                </small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Muestra la cantidad de equipos registrados o actualizados cada mes
                        </small>
                    </div>
                    <?php else: ?>
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-calendar-times fa-2x mb-2"></i>
                        <div>No hay datos de actividad disponibles</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Pie del reporte -->
        <div class="text-center mt-4 mb-4">
            <small class="text-muted">
                Reporte generado el <?php echo date('d/m/Y H:i'); ?> por <?php echo $_SESSION['inv_full_name']; ?> |
                Total de equipos: <?php echo $reportes['resumen']['total_equipos']; ?> |
                Sistema de Inventario INDE v<?php echo APP_VERSION; ?>
            </small>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function exportarFiltrado() {
            // Abrir modal para seleccionar filtros de exportación
            if (confirm('¿Desea exportar solo los equipos activos?')) {
                window.location.href = 'buscar_equipos.php?estado=Activo';
            } else {
                window.location.href = 'buscar_equipos.php';
            }
        }

        function imprimirReporte() {
            // Ocultar elementos no necesarios para impresión
            const navbar = document.querySelector('.navbar');
            const breadcrumb = document.querySelector('.breadcrumb');
            const exportSection = document.querySelector('.export-section');
            
            navbar.style.display = 'none';
            breadcrumb.style.display = 'none';
            exportSection.style.display = 'none';
            
            // Imprimir
            window.print();
            
            // Restaurar elementos
            navbar.style.display = 'block';
            breadcrumb.style.display = 'block';
            exportSection.style.display = 'block';
        }

        // Agregar estilos para impresión
        const printStyles = `
            @media print {
                .navbar, .breadcrumb, .export-section, .btn { display: none !important; }
                .card { box-shadow: none !important; border: 1px solid #ccc !important; }
                body { background: white !important; }
                .main-content { padding: 0 !important; }
            }
        `;
        
        const styleSheet = document.createElement('style');
        styleSheet.textContent = printStyles;
        document.head.appendChild(styleSheet);

        // Funciones para análisis
        function analizarTendencias() {
            console.log('Analizando tendencias de crecimiento...');
            // Aquí se podría implementar análisis más avanzado
        }

        // Auto-actualizar cada 10 minutos
        setTimeout(function() {
            if (confirm('Los datos han cambiado. ¿Desea actualizar el reporte?')) {
                location.reload();
            }
        }, 600000);
    </script>
</body>
</html>
