<?php
require_once 'config_inventario.php';
checkSession();

$pdo = getDBConnection();

// Parámetros de paginación y filtrado
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$records_per_page = RECORDS_PER_PAGE;
$offset = ($page - 1) * $records_per_page;

// Filtros
$filtro_so = isset($_GET['filtro_so']) ? sanitizeInput($_GET['filtro_so']) : '';
$filtro_fabricante = isset($_GET['filtro_fabricante']) ? sanitizeInput($_GET['filtro_fabricante']) : '';
$filtro_usuario = isset($_GET['filtro_usuario']) ? sanitizeInput($_GET['filtro_usuario']) : '';
$filtro_busqueda = isset($_GET['busqueda']) ? sanitizeInput($_GET['busqueda']) : '';
$orden = isset($_GET['orden']) ? sanitizeInput($_GET['orden']) : 'fecha_captura';
$direccion = isset($_GET['direccion']) && $_GET['direccion'] === 'asc' ? 'ASC' : 'DESC';

// Construir consulta con filtros
$where_conditions = [];
$params = [];

if (!empty($filtro_so)) {
    $where_conditions[] = "sistema_operativo LIKE ?";
    $params[] = "%$filtro_so%";
}

if (!empty($filtro_fabricante)) {
    $where_conditions[] = "fabricante = ?";
    $params[] = $filtro_fabricante;
}

if (!empty($filtro_usuario)) {
    $where_conditions[] = "usuario_sistema LIKE ?";
    $params[] = "%$filtro_usuario%";
}

// CORREGIR: Búsqueda general completa en TODOS los campos relevantes
if (!empty($filtro_busqueda)) {
    $where_conditions[] = "(
        nombre_equipo LIKE ? OR 
        ip_equipo LIKE ? OR 
        ip_local LIKE ? OR 
        numero_serie LIKE ? OR 
        modelo LIKE ? OR 
        usuario_sistema LIKE ? OR
        fabricante LIKE ? OR
        sistema_operativo LIKE ? OR
        ubicacion LIKE ? OR
        responsable LIKE ? OR
        procesador LIKE ? OR
        origen_datos LIKE ?
    )";
    // Agregar el parámetro para cada campo (12 campos = 12 parámetros)
    for ($i = 0; $i < 12; $i++) {
        $params[] = "%$filtro_busqueda%";
    }
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Validar columna de ordenamiento
$columnas_validas = ['nombre_equipo', 'ip_equipo', 'sistema_operativo', 'fabricante', 'fecha_captura', 'usuario_sistema'];
if (!in_array($orden, $columnas_validas)) {
    $orden = 'fecha_captura';
}

// Contar total de registros
$count_sql = "SELECT COUNT(*) as total FROM equipos_info $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$count_result = $count_stmt->fetch();
$total_records = $count_result ? $count_result['total'] : 0;
$total_pages = ceil($total_records / $records_per_page);

// Obtener equipos con paginación - MEJORAR manejo de errores
$sql = "
    SELECT id, nombre_equipo, ip_equipo, ip_local, procesador, memoria_ram, 
           disco_duro, sistema_operativo, fabricante, modelo, numero_serie,
           usuario_sistema, fecha_captura, origen_datos, observaciones,
           ubicacion, responsable, estado_equipo
    FROM equipos_info 
    $where_clause
    ORDER BY $orden $direccion
    LIMIT ? OFFSET ?
";

try {
    $stmt = $pdo->prepare($sql);
    // Agregar parámetros de LIMIT y OFFSET al final
    $exec_params = array_merge($params, [$records_per_page, $offset]);
    $stmt->execute($exec_params);
    $equipos = $stmt->fetchAll();
    
    // Asegurar que $equipos sea siempre un array
    if ($equipos === false || $equipos === null) {
        $equipos = [];
    }
} catch (PDOException $e) {
    // Log del error para administradores (opcional)
    error_log("Error en consulta SQL: " . $e->getMessage());
    $equipos = []; // Array vacío en caso de error
}

// Obtener listas para filtros
$stmt = $pdo->query("SELECT DISTINCT fabricante FROM equipos_info WHERE fabricante IS NOT NULL AND fabricante != 'No disponible' ORDER BY fabricante");
$fabricantes = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->query("
    SELECT DISTINCT 
        CASE 
            WHEN sistema_operativo LIKE '%Windows 11%' THEN 'Windows 11'
            WHEN sistema_operativo LIKE '%Windows 10%' THEN 'Windows 10'
            WHEN sistema_operativo LIKE '%Windows%' THEN 'Windows (Otros)'
            WHEN sistema_operativo LIKE '%Ubuntu%' OR sistema_operativo LIKE '%Linux%' THEN 'Linux/Ubuntu'
            ELSE 'Otros'
        END as so_categoria
    FROM equipos_info 
    WHERE sistema_operativo IS NOT NULL 
    ORDER BY so_categoria
");
$sistemas_operativos = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Equipos - <?php echo APP_NAME; ?></title>
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
            padding: 20px 20px;
            max-width: 1400px;
            margin: 0 auto;
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
            padding: 15px 20px;
            border: none;
        }

        .card-header h5 {
            margin: 0;
            font-weight: 600;
            font-size: 1rem;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background-color: var(--very-light-blue);
            color: var(--primary-blue);
            font-weight: 600;
            border: none;
            font-size: 0.85rem;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .table tbody tr:hover {
            background-color: var(--very-light-blue);
        }

        .table tbody tr {
            transition: background-color 0.2s ease;
        }

        .badge {
            border-radius: 15px;
            padding: 4px 8px;
            font-size: 0.7rem;
            font-weight: 500;
        }

        .btn {
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-sm {
            padding: 4px 8px;
            font-size: 0.75rem;
        }

        .form-control, .form-select {
            border-radius: 6px;
            border: 1px solid #dee2e6;
            font-size: 0.85rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--light-blue);
            box-shadow: 0 0 0 0.2rem rgba(93, 173, 226, 0.25);
        }

        .filters-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .pagination {
            margin-bottom: 0;
        }

        .pagination .page-link {
            color: var(--primary-blue);
            border-color: #dee2e6;
            border-radius: 6px;
            margin: 0 2px;
        }

        .pagination .page-link:hover {
            background-color: var(--very-light-blue);
            border-color: var(--light-blue);
        }

        .pagination .page-item.active .page-link {
            background-color: var(--primary-blue);
            border-color: var(--primary-blue);
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

        .equipo-info {
            font-size: 0.8rem;
        }

        .equipo-info .text-primary {
            color: var(--primary-blue) !important;
            font-weight: 600;
        }

        .sort-link {
            color: var(--primary-blue);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .sort-link:hover {
            color: var(--secondary-blue);
        }

        .sort-link.active {
            font-weight: 600;
        }

        .table-responsive {
            border-radius: 8px;
        }

        .info-tooltip {
            position: relative;
            cursor: help;
        }

        .stats-mini {
            background: var(--very-light-blue);
            border-radius: 8px;
            padding: 10px 15px;
            margin-bottom: 15px;
        }

        .stats-mini .text-primary {
            color: var(--primary-blue) !important;
            font-weight: 600;
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
                        <a class="nav-link active" href="equipos_lista.php">
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
        <!-- Estadísticas rápidas -->
        <div class="stats-mini">
            <div class="row text-center">
                <div class="col-md-3">
                    <span class="text-primary"><?php echo $total_records; ?></span>
                    <small class="text-muted d-block">Total Equipos</small>
                </div>
                <div class="col-md-3">
                    <span class="text-primary"><?php echo $page; ?></span>
                    <small class="text-muted d-block">Página Actual</small>
                </div>
                <div class="col-md-3">
                    <span class="text-primary"><?php echo $total_pages; ?></span>
                    <small class="text-muted d-block">Total Páginas</small>
                </div>
                <div class="col-md-3">
                    <span class="text-primary"><?php echo count($equipos); ?></span>
                    <small class="text-muted d-block">Mostrando</small>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filters-section">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label for="busqueda" class="form-label">Búsqueda General</label>
                    <input type="text" class="form-control" id="busqueda" name="busqueda" 
                           value="<?php echo htmlspecialchars($filtro_busqueda); ?>"
                           placeholder="Nombre, IP, Serie, Modelo...">
                </div>
                <div class="col-md-2">
                    <label for="filtro_so" class="form-label">Sistema Operativo</label>
                    <select class="form-select" id="filtro_so" name="filtro_so">
                        <option value="">Todos</option>
                        <?php foreach ($sistemas_operativos as $so): ?>
                        <option value="<?php echo $so; ?>" <?php echo $filtro_so === $so ? 'selected' : ''; ?>>
                            <?php echo $so; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="filtro_fabricante" class="form-label">Fabricante</label>
                    <select class="form-select" id="filtro_fabricante" name="filtro_fabricante">
                        <option value="">Todos</option>
                        <?php foreach ($fabricantes as $fabricante): ?>
                        <option value="<?php echo $fabricante; ?>" <?php echo $filtro_fabricante === $fabricante ? 'selected' : ''; ?>>
                            <?php echo $fabricante; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="filtro_usuario" class="form-label">Usuario</label>
                    <input type="text" class="form-control" id="filtro_usuario" name="filtro_usuario" 
                           value="<?php echo htmlspecialchars($filtro_usuario); ?>"
                           placeholder="Usuario del sistema">
                </div>
                <div class="col-md-2">
                    <label for="orden" class="form-label">Ordenar por</label>
                    <select class="form-select" id="orden" name="orden">
                        <option value="fecha_captura" <?php echo $orden === 'fecha_captura' ? 'selected' : ''; ?>>Fecha</option>
                        <option value="nombre_equipo" <?php echo $orden === 'nombre_equipo' ? 'selected' : ''; ?>>Nombre</option>
                        <option value="ip_equipo" <?php echo $orden === 'ip_equipo' ? 'selected' : ''; ?>>IP</option>
                        <option value="fabricante" <?php echo $orden === 'fabricante' ? 'selected' : ''; ?>>Fabricante</option>
                        <option value="usuario_sistema" <?php echo $orden === 'usuario_sistema' ? 'selected' : ''; ?>>Usuario</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <label for="direccion" class="form-label">Orden</label>
                    <select class="form-select" id="direccion" name="direccion">
                        <option value="desc" <?php echo $direccion === 'DESC' ? 'selected' : ''; ?>>⬇ DESC</option>
                        <option value="asc" <?php echo $direccion === 'ASC' ? 'selected' : ''; ?>>⬆ ASC</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-1"></i>Aplicar Filtros
                    </button>
                    <a href="equipos_lista.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i>Limpiar
                    </a>
                    <?php if ($_SESSION['inv_is_admin']): ?>
                    <a href="equipo_nuevo.php" class="btn btn-success">
                        <i class="fas fa-plus me-1"></i>Agregar Equipo
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Tabla de Equipos -->
        <div class="card">
            <div class="card-header">
                <h5>
                    <i class="fas fa-desktop me-2"></i>
                    Lista de Equipos (<?php echo $total_records; ?> equipos)
                </h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th scope="col">Equipo</th>
                            <th scope="col">Red</th>
                            <th scope="col">Hardware</th>
                            <th scope="col">Sistema</th>
                            <th scope="col">Usuario</th>
                            <th scope="col">Estado Disco</th>
                            <th scope="col">Fecha</th>
                            <th scope="col">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($equipos)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                No se encontraron equipos con los filtros aplicados
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($equipos as $equipo): ?>
                        <?php 
                        $estado_disco = getEstadoDisco($equipo['disco_duro']);
                        $porcentaje_uso = calcularPorcentajeUso($equipo['disco_duro']);
                        ?>
                        <tr>
                            <td>
                                <div class="equipo-info">
                                    <div class="text-primary fw-bold"><?php echo $equipo['nombre_equipo']; ?></div>
                                    <small class="text-muted">
                                        <?php echo $equipo['fabricante']; ?> 
                                        <?php echo $equipo['modelo']; ?>
                                    </small>
                                    <?php if ($equipo['numero_serie']): ?>
                                    <br><small class="text-muted">S/N: <?php echo $equipo['numero_serie']; ?></small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="equipo-info">
                                    <div><strong>Externa:</strong> <?php echo $equipo['ip_equipo']; ?></div>
                                    <?php if ($equipo['ip_local']): ?>
                                    <small class="text-muted">Local: <?php echo $equipo['ip_local']; ?></small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="equipo-info">
                                    <div><strong>CPU:</strong> <?php echo formatearProcesador($equipo['procesador']); ?></div>
                                    <div><strong>RAM:</strong> <?php echo formatearRAM($equipo['memoria_ram']); ?></div>
                                </div>
                            </td>
                            <td>
                                <div class="equipo-info">
                                    <?php if (strpos($equipo['sistema_operativo'], 'Windows 11') !== false): ?>
                                        <span class="badge bg-primary">Windows 11</span>
                                    <?php elseif (strpos($equipo['sistema_operativo'], 'Windows 10') !== false): ?>
                                        <span class="badge bg-info">Windows 10</span>
                                    <?php elseif (strpos($equipo['sistema_operativo'], 'Ubuntu') !== false): ?>
                                        <span class="badge bg-warning text-dark">Ubuntu</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Otro</span>
                                    <?php endif; ?>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo $equipo['origen_datos']; ?>
                                    </small>
                                </div>
                            </td>
                            <td>
                                <div class="equipo-info">
                                    <strong><?php echo $equipo['usuario_sistema'] ?: 'N/A'; ?></strong>
                                </div>
                            </td>
                            <td>
                                <div class="equipo-info">
                                    <?php if ($porcentaje_uso > 0): ?>
                                    <span class="status-indicator <?php echo $estado_disco['clase']; ?>"></span>
                                    <strong class="text-<?php echo $estado_disco['clase']; ?>">
                                        <?php echo $porcentaje_uso; ?>%
                                    </strong>
                                    <br><small class="text-muted"><?php echo $estado_disco['estado']; ?></small>
                                    <?php else: ?>
                                    <span class="text-muted">Sin datos</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="equipo-info">
                                    <small><?php echo formatDate($equipo['fecha_captura'], 'd/m/Y'); ?></small>
                                    <br><small class="text-muted"><?php echo formatDate($equipo['fecha_captura'], 'H:i'); ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="btn-group-vertical btn-group-sm" role="group">
                                    <a href="equipo_detalle.php?id=<?php echo $equipo['id']; ?>" 
                                       class="btn btn-outline-primary btn-sm" title="Ver detalles">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($_SESSION['inv_is_admin']): ?>
                                    <a href="equipo_editar.php?id=<?php echo $equipo['id']; ?>" 
                                       class="btn btn-outline-warning btn-sm" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Paginación -->
        <?php if ($total_pages > 1): ?>
        <div class="d-flex justify-content-center mt-4">
            <nav aria-label="Navegación de páginas">
                <ul class="pagination">
                    <!-- Página anterior -->
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                            <i class="fas fa-chevron-left"></i> Anterior
                        </a>
                    </li>
                    <?php endif; ?>

                    <!-- Páginas -->
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($start_page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">1</a>
                    </li>
                    <?php if ($start_page > 2): ?>
                    <li class="page-item disabled">
                        <span class="page-link">...</span>
                    </li>
                    <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>

                    <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                    <li class="page-item disabled">
                        <span class="page-link">...</span>
                    </li>
                    <?php endif; ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>">
                            <?php echo $total_pages; ?>
                        </a>
                    </li>
                    <?php endif; ?>

                    <!-- Página siguiente -->
                    <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                            Siguiente <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>

        <!-- Información de paginación -->
        <div class="text-center mt-3">
            <small class="text-muted">
                Mostrando <?php echo ($offset + 1); ?> - <?php echo min($offset + $records_per_page, $total_records); ?> 
                de <?php echo $total_records; ?> equipos
                <?php if (!empty($filtro_busqueda)): ?>
                <br>Filtro aplicado: <strong>"<?php echo htmlspecialchars($filtro_busqueda); ?>"</strong>
                <?php endif; ?>
            </small>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-submit del formulario al cambiar los filtros de select
        document.addEventListener('DOMContentLoaded', function() {
            const selects = document.querySelectorAll('#filtro_so, #filtro_fabricante, #orden, #direccion');
            selects.forEach(function(select) {
                select.addEventListener('change', function() {
                    this.form.submit();
                });
            });

            // Tooltip para información adicional
            const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            tooltips.forEach(function(tooltip) {
                new bootstrap.Tooltip(tooltip);
            });

            // Resaltar texto de búsqueda en resultados
            const busqueda = '<?php echo addslashes($filtro_busqueda); ?>';
            if (busqueda.length > 0) {
                highlightSearchTerm(busqueda);
            }
        });

        // Función para resaltar términos de búsqueda
        function highlightSearchTerm(term) {
            if (!term) return;
            
            const elements = document.querySelectorAll('.equipo-info');
            elements.forEach(function(element) {
                const regex = new RegExp(`(${term})`, 'gi');
                element.innerHTML = element.innerHTML.replace(regex, '<mark>$1</mark>');
            });
        }

        // Función para exportar datos (solo para admins)
        function exportarDatos() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.location.href = 'equipos_lista.php?' + params.toString();
        }
    </script>
</body>
</html>
