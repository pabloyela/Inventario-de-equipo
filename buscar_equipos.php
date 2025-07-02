<?php
require_once 'config_inventario.php';
checkSession();

$pdo = getDBConnection();

// Variables para la búsqueda
$busqueda_realizada = false;
$equipos = [];
$total_resultados = 0;

// Procesar formulario de búsqueda
if ($_SERVER['REQUEST_METHOD'] == 'POST' || !empty($_GET)) {
    $busqueda_realizada = true;
    
    // Recoger parámetros de búsqueda
    $nombre = isset($_POST['nombre']) ? sanitizeInput($_POST['nombre']) : (isset($_GET['nombre']) ? sanitizeInput($_GET['nombre']) : '');
    $ip_externa = isset($_POST['ip_externa']) ? sanitizeInput($_POST['ip_externa']) : (isset($_GET['ip_externa']) ? sanitizeInput($_GET['ip_externa']) : '');
    $ip_local = isset($_POST['ip_local']) ? sanitizeInput($_POST['ip_local']) : (isset($_GET['ip_local']) ? sanitizeInput($_GET['ip_local']) : '');
    $usuario = isset($_POST['usuario']) ? sanitizeInput($_POST['usuario']) : (isset($_GET['usuario']) ? sanitizeInput($_GET['usuario']) : '');
    $fabricante = isset($_POST['fabricante']) ? sanitizeInput($_POST['fabricante']) : (isset($_GET['fabricante']) ? sanitizeInput($_GET['fabricante']) : '');
    $modelo = isset($_POST['modelo']) ? sanitizeInput($_POST['modelo']) : (isset($_GET['modelo']) ? sanitizeInput($_GET['modelo']) : '');
    $serie = isset($_POST['serie']) ? sanitizeInput($_POST['serie']) : (isset($_GET['serie']) ? sanitizeInput($_GET['serie']) : '');
    $so = isset($_POST['so']) ? sanitizeInput($_POST['so']) : (isset($_GET['so']) ? sanitizeInput($_GET['so']) : '');
    $estado = isset($_POST['estado']) ? sanitizeInput($_POST['estado']) : (isset($_GET['estado']) ? sanitizeInput($_GET['estado']) : '');
    $origen = isset($_POST['origen']) ? sanitizeInput($_POST['origen']) : (isset($_GET['origen']) ? sanitizeInput($_GET['origen']) : '');
    $ubicacion = isset($_POST['ubicacion']) ? sanitizeInput($_POST['ubicacion']) : (isset($_GET['ubicacion']) ? sanitizeInput($_GET['ubicacion']) : '');
    $responsable = isset($_POST['responsable']) ? sanitizeInput($_POST['responsable']) : (isset($_GET['responsable']) ? sanitizeInput($_GET['responsable']) : '');
    $fecha_desde = isset($_POST['fecha_desde']) ? sanitizeInput($_POST['fecha_desde']) : (isset($_GET['fecha_desde']) ? sanitizeInput($_GET['fecha_desde']) : '');
    $fecha_hasta = isset($_POST['fecha_hasta']) ? sanitizeInput($_POST['fecha_hasta']) : (isset($_GET['fecha_hasta']) ? sanitizeInput($_GET['fecha_hasta']) : '');
    
    // Filtros específicos de RAM (múltiples valores) - Corregir la captura
    $ram_especifica = [];
    if (isset($_POST['ram_especifica']) && is_array($_POST['ram_especifica'])) {
        $ram_especifica = $_POST['ram_especifica'];
    } elseif (isset($_GET['ram_especifica'])) {
        if (is_array($_GET['ram_especifica'])) {
            $ram_especifica = $_GET['ram_especifica'];
        } else {
            $ram_especifica = [$_GET['ram_especifica']];
        }
    }
    
    // Filtros de estado de disco
    $estado_disco_filtro = isset($_POST['estado_disco']) ? $_POST['estado_disco'] : (isset($_GET['estado_disco']) ? $_GET['estado_disco'] : '');
    $min_disco_libre = isset($_POST['min_disco_libre']) ? (int)$_POST['min_disco_libre'] : (isset($_GET['min_disco_libre']) ? (int)$_GET['min_disco_libre'] : 0);
    
    // Construir consulta SQL
    $where_conditions = [];
    $params = [];
    
    if (!empty($nombre)) {
        $where_conditions[] = "nombre_equipo LIKE ?";
        $params[] = "%$nombre%";
    }
    
    if (!empty($ip_externa)) {
        $where_conditions[] = "ip_equipo LIKE ?";
        $params[] = "%$ip_externa%";
    }
    
    if (!empty($ip_local)) {
        $where_conditions[] = "ip_local LIKE ?";
        $params[] = "%$ip_local%";
    }
    
    if (!empty($usuario)) {
        $where_conditions[] = "usuario_sistema LIKE ?";
        $params[] = "%$usuario%";
    }
    
    if (!empty($fabricante)) {
        $where_conditions[] = "fabricante LIKE ?";
        $params[] = "%$fabricante%";
    }
    
    if (!empty($modelo)) {
        $where_conditions[] = "modelo LIKE ?";
        $params[] = "%$modelo%";
    }
    
    if (!empty($serie)) {
        $where_conditions[] = "numero_serie LIKE ?";
        $params[] = "%$serie%";
    }
    
    if (!empty($so)) {
        $where_conditions[] = "sistema_operativo LIKE ?";
        $params[] = "%$so%";
    }
    
    if (!empty($estado)) {
        $where_conditions[] = "estado_equipo = ?";
        $params[] = $estado;
    }
    
    if (!empty($origen)) {
        $where_conditions[] = "origen_datos = ?";
        $params[] = $origen;
    }
    
    if (!empty($ubicacion)) {
        $where_conditions[] = "ubicacion LIKE ?";
        $params[] = "%$ubicacion%";
    }
    
    if (!empty($responsable)) {
        $where_conditions[] = "responsable LIKE ?";
        $params[] = "%$responsable%";
    }
    
    if (!empty($fecha_desde)) {
        $where_conditions[] = "DATE(fecha_captura) >= ?";
        $params[] = $fecha_desde;
    }
    
    if (!empty($fecha_hasta)) {
        $where_conditions[] = "DATE(fecha_captura) <= ?";
        $params[] = $fecha_hasta;
    }
    
    // Construir WHERE clause
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Ejecutar búsqueda básica - SIEMPRE mostrar todos si no hay filtros
    $sql = "
        SELECT id, nombre_equipo, ip_equipo, ip_local, procesador, memoria_ram, 
               disco_duro, sistema_operativo, fabricante, modelo, numero_serie,
               usuario_sistema, fecha_captura, origen_datos, observaciones,
               ubicacion, responsable, estado_equipo
        FROM equipos_info 
        $where_clause
        ORDER BY fecha_captura DESC
        LIMIT 100
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $equipos = $stmt->fetchAll();
    
    $total_resultados = count($equipos);
    
    // Aplicar filtros post-consulta para RAM específica y disco
    if (!empty($ram_especifica) || !empty($estado_disco_filtro) || $min_disco_libre > 0) {
        $equipos_filtrados = [];
        
        foreach ($equipos as $equipo) {
            $incluir = true;
            
            // Filtro de RAM específica (coincidencia exacta)
            if (!empty($ram_especifica)) {
                $ram_coincide = false;
                
                if (!empty($equipo['memoria_ram'])) {
                    foreach ($ram_especifica as $ram_requerida) {
                        if (preg_match('/(\d+(?:\.\d+)?)\s*GB/', $equipo['memoria_ram'], $matches)) {
                            $ram_gb = (float)$matches[1];
                            $ram_requerida_num = (float)$ram_requerida;
                            
                            // Permitir un margen de ±0.5GB para coincidencias aproximadas
                            if (abs($ram_gb - $ram_requerida_num) <= 0.5) {
                                $ram_coincide = true;
                                break;
                            }
                        }
                    }
                } else {
                    // Si no hay info de RAM y se requiere filtro específico, excluir
                    $ram_coincide = false;
                }
                
                if (!$ram_coincide) {
                    $incluir = false;
                }
            }
            
            // Filtro de estado de disco
            if (!empty($estado_disco_filtro) && $incluir) {
                if (!empty($equipo['disco_duro'])) {
                    $porcentaje_uso_actual = calcularPorcentajeUso($equipo['disco_duro']);
                    
                    switch ($estado_disco_filtro) {
                        case 'critico':
                            if ($porcentaje_uso_actual < 90) $incluir = false;
                            break;
                        case 'alerta':
                            if ($porcentaje_uso_actual < 70 || $porcentaje_uso_actual >= 90) $incluir = false;
                            break;
                        case 'normal':
                            if ($porcentaje_uso_actual < 30 || $porcentaje_uso_actual >= 70) $incluir = false;
                            break;
                        case 'optimo':
                            if ($porcentaje_uso_actual >= 30) $incluir = false;
                            break;
                    }
                } else {
                    // Si no hay info de disco y se requiere filtro específico, excluir
                    $incluir = false;
                }
            }
            
            // Filtro de espacio libre mínimo
            if ($min_disco_libre > 0 && $incluir) {
                if (!empty($equipo['disco_duro'])) {
                    $porcentaje_uso_actual = calcularPorcentajeUso($equipo['disco_duro']);
                    $porcentaje_libre = 100 - $porcentaje_uso_actual;
                    if ($porcentaje_libre < $min_disco_libre) {
                        $incluir = false;
                    }
                } else {
                    // Si no hay info de disco y se requiere espacio mínimo, excluir
                    $incluir = false;
                }
            }
            
            if ($incluir) {
                $equipos_filtrados[] = $equipo;
            }
        }
        
        $equipos = $equipos_filtrados;
        $total_resultados = count($equipos);
        
        // Debug filtros post-consulta (quitar en producción)
        // error_log("After post-filters: " . count($equipos));
    }
}

// Obtener listas para filtros
$stmt = $pdo->query("SELECT DISTINCT fabricante FROM equipos_info WHERE fabricante IS NOT NULL AND fabricante != 'No disponible' ORDER BY fabricante");
$fabricantes = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->query("SELECT DISTINCT origen_datos FROM equipos_info WHERE origen_datos IS NOT NULL ORDER BY origen_datos");
$origenes = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Obtener usuarios más frecuentes para filtros rápidos
$stmt = $pdo->query("
    SELECT usuario_sistema, COUNT(*) as cantidad 
    FROM equipos_info 
    WHERE usuario_sistema IS NOT NULL AND usuario_sistema != '' 
    GROUP BY usuario_sistema 
    ORDER BY cantidad DESC 
    LIMIT 5
");
$usuarios_frecuentes = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Búsqueda Avanzada - <?php echo APP_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="styles_buscar.css" rel="stylesheet">
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
                        <a class="nav-link active" href="buscar_equipos.php">
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
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index_inventario.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Búsqueda Avanzada</li>
            </ol>
        </nav>

        <!-- Header -->
        <div class="card">
            <div class="card-header">
                <h5>
                    <i class="fas fa-search me-2"></i>
                    Búsqueda Avanzada de Equipos
                </h5>
            </div>
        </div>

        <!-- Filtros rápidos por SO -->
        <div class="quick-filters">
            <div class="d-flex flex-wrap">
                <button type="button" class="btn btn-outline-primary quick-filter-btn" onclick="aplicarFiltroRapido('windows11')">
                    <i class="fab fa-windows me-1"></i>Windows 11
                </button>
                <button type="button" class="btn btn-outline-primary quick-filter-btn" onclick="aplicarFiltroRapido('windows10')">
                    <i class="fab fa-windows me-1"></i>Windows 10
                </button>
                <button type="button" class="btn btn-outline-primary quick-filter-btn" onclick="aplicarFiltroRapido('ubuntu')">
                    <i class="fab fa-ubuntu me-1"></i>Ubuntu
                </button>
                <button type="button" class="btn btn-outline-info quick-filter-btn" onclick="aplicarFiltroRapido('dell')">
                    <i class="fas fa-desktop me-1"></i>Dell
                </button>
                <button type="button" class="btn btn-outline-secondary quick-filter-btn" onclick="aplicarFiltroRapido('agente')">
                    <i class="fas fa-robot me-1"></i>Agente Inventario
                </button>
                <button type="button" class="btn btn-outline-warning quick-filter-btn" onclick="aplicarFiltroRapido('recientes')">
                    <i class="fas fa-clock me-1"></i>Últimos 7 días
                </button>
            </div>
        </div>

        <!-- Formulario de búsqueda -->
        <div class="search-section">
            <form method="POST" action="" id="searchForm">
                <div class="row">
                    <!-- Información Básica -->
                    <div class="col-md-6">
                        <h6 class="section-title">
                            <i class="fas fa-info-circle me-2"></i>Información Básica
                        </h6>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="nombre" class="form-label">Nombre del Equipo</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" 
                                       value="<?php echo isset($nombre) ? htmlspecialchars($nombre) : ''; ?>"
                                       placeholder="Ej: PC-ADMIN-01">
                            </div>
                            <div class="col-md-6">
                                <label for="usuario" class="form-label">Usuario</label>
                                <input type="text" class="form-control" id="usuario" name="usuario" 
                                       value="<?php echo isset($usuario) ? htmlspecialchars($usuario) : ''; ?>"
                                       placeholder="Ej: jperez">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="fabricante" class="form-label">Fabricante</label>
                                <select class="form-select" id="fabricante" name="fabricante">
                                    <option value="">Todos</option>
                                    <?php foreach ($fabricantes as $fab): ?>
                                    <option value="<?php echo $fab; ?>" <?php echo (isset($fabricante) && $fabricante === $fab) ? 'selected' : ''; ?>>
                                        <?php echo $fab; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="modelo" class="form-label">Modelo</label>
                                <input type="text" class="form-control" id="modelo" name="modelo" 
                                       value="<?php echo isset($modelo) ? htmlspecialchars($modelo) : ''; ?>"
                                       placeholder="Ej: OptiPlex 7060">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="serie" class="form-label">Número de Serie</label>
                                <input type="text" class="form-control" id="serie" name="serie" 
                                       value="<?php echo isset($serie) ? htmlspecialchars($serie) : ''; ?>"
                                       placeholder="Ej: ABC123XYZ">
                            </div>
                            <div class="col-md-6">
                                <label for="estado" class="form-label">Estado</label>
                                <select class="form-select" id="estado" name="estado">
                                    <option value="">Todos</option>
                                    <option value="Activo" <?php echo (isset($estado) && $estado === 'Activo') ? 'selected' : ''; ?>>Activo</option>
                                    <option value="Inactivo" <?php echo (isset($estado) && $estado === 'Inactivo') ? 'selected' : ''; ?>>Inactivo</option>
                                    <option value="Mantenimiento" <?php echo (isset($estado) && $estado === 'Mantenimiento') ? 'selected' : ''; ?>>Mantenimiento</option>
                                    <option value="Dado de baja" <?php echo (isset($estado) && $estado === 'Dado de baja') ? 'selected' : ''; ?>>Dado de baja</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Red y Sistema -->
                    <div class="col-md-6">
                        <h6 class="section-title">
                            <i class="fas fa-network-wired me-2"></i>Red y Sistema
                        </h6>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="ip_externa" class="form-label">IP Externa</label>
                                <input type="text" class="form-control" id="ip_externa" name="ip_externa" 
                                       value="<?php echo isset($ip_externa) ? htmlspecialchars($ip_externa) : ''; ?>"
                                       placeholder="Ej: 190.115.7.194">
                            </div>
                            <div class="col-md-6">
                                <label for="ip_local" class="form-label">IP Local</label>
                                <input type="text" class="form-control" id="ip_local" name="ip_local" 
                                       value="<?php echo isset($ip_local) ? htmlspecialchars($ip_local) : ''; ?>"
                                       placeholder="Ej: 172.17.0.12">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="so" class="form-label">Sistema Operativo</label>
                            <select class="form-select" id="so" name="so">
                                <option value="">Todos los sistemas</option>
                                <option value="Windows 11" <?php echo (isset($so) && strpos($so, 'Windows 11') !== false) ? 'selected' : ''; ?>>Windows 11</option>
                                <option value="Windows 10" <?php echo (isset($so) && strpos($so, 'Windows 10') !== false) ? 'selected' : ''; ?>>Windows 10</option>
                                <option value="Ubuntu" <?php echo (isset($so) && strpos($so, 'Ubuntu') !== false) ? 'selected' : ''; ?>>Ubuntu/Linux</option>
                                <option value="Windows" <?php echo (isset($so) && $so === 'Windows' && strpos($so, '10') === false && strpos($so, '11') === false) ? 'selected' : ''; ?>>Windows (Otros)</option>
                            </select>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="origen" class="form-label">Origen de Datos</label>
                                <select class="form-select" id="origen" name="origen">
                                    <option value="">Todos</option>
                                    <?php foreach ($origenes as $orig): ?>
                                    <option value="<?php echo $orig; ?>" <?php echo (isset($origen) && $origen === $orig) ? 'selected' : ''; ?>>
                                        <?php echo $orig; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="ubicacion" class="form-label">Ubicación</label>
                                <input type="text" class="form-control" id="ubicacion" name="ubicacion" 
                                       value="<?php echo isset($ubicacion) ? htmlspecialchars($ubicacion) : ''; ?>"
                                       placeholder="Ej: Oficina 201">
                            </div>
                        </div>
                    </div>

                    <!-- Filtros de Hardware -->
                    <div class="col-md-12">
                        <h6 class="section-title">
                            <i class="fas fa-microchip me-2"></i>Filtros de Hardware
                        </h6>
                        
                        <!-- Filtro específico de RAM -->
                        <div class="mb-3">
                            <label class="form-label">Memoria RAM Específica</label>
                            <small class="text-muted d-block mb-2">Seleccione las cantidades exactas de RAM que busca (puede seleccionar múltiples)</small>
                            <div class="ram-checkbox-group">
                                <?php 
                                $ram_options = ['3', '4', '6', '8', '16', '20', '32'];
                                foreach ($ram_options as $ram_val): 
                                    $checked = isset($ram_especifica) && in_array($ram_val, $ram_especifica) ? 'checked' : '';
                                    $selected_class = $checked ? 'selected' : '';
                                ?>
                                <div class="ram-checkbox-item <?php echo $selected_class; ?>" onclick="toggleRamCheckbox('ram_<?php echo $ram_val; ?>')">
                                    <input type="checkbox" id="ram_<?php echo $ram_val; ?>" name="ram_especifica[]" value="<?php echo $ram_val; ?>" <?php echo $checked; ?>>
                                    <label for="ram_<?php echo $ram_val; ?>"><?php echo $ram_val; ?> GB</label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Filtros de Disco -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="estado_disco" class="form-label">Estado de Disco</label>
                                <select class="form-select" id="estado_disco" name="estado_disco">
                                    <option value="">Cualquier estado</option>
                                    <option value="critico" <?php echo (isset($estado_disco_filtro) && $estado_disco_filtro === 'critico') ? 'selected' : ''; ?>>Crítico (>90% usado)</option>
                                    <option value="alerta" <?php echo (isset($estado_disco_filtro) && $estado_disco_filtro === 'alerta') ? 'selected' : ''; ?>>Alerta (70-90% usado)</option>
                                    <option value="normal" <?php echo (isset($estado_disco_filtro) && $estado_disco_filtro === 'normal') ? 'selected' : ''; ?>>Normal (30-70% usado)</option>
                                    <option value="optimo" <?php echo (isset($estado_disco_filtro) && $estado_disco_filtro === 'optimo') ? 'selected' : ''; ?>>Óptimo (<30% usado)</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="min_disco_libre" class="form-label">Espacio Libre Mínimo (%)</label>
                                <select class="form-select" id="min_disco_libre" name="min_disco_libre">
                                    <option value="0">Cualquiera</option>
                                    <option value="10" <?php echo (isset($min_disco_libre) && $min_disco_libre == 10) ? 'selected' : ''; ?>>10% o más libre</option>
                                    <option value="20" <?php echo (isset($min_disco_libre) && $min_disco_libre == 20) ? 'selected' : ''; ?>>20% o más libre</option>
                                    <option value="30" <?php echo (isset($min_disco_libre) && $min_disco_libre == 30) ? 'selected' : ''; ?>>30% o más libre</option>
                                    <option value="50" <?php echo (isset($min_disco_libre) && $min_disco_libre == 50) ? 'selected' : ''; ?>>50% o más libre</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Filtros Avanzados -->
                    <div class="col-md-12">
                        <h6 class="section-title">
                            <i class="fas fa-filter me-2"></i>Filtros Avanzados
                        </h6>
                        
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label for="responsable" class="form-label">Responsable</label>
                                <input type="text" class="form-control" id="responsable" name="responsable" 
                                       value="<?php echo isset($responsable) ? htmlspecialchars($responsable) : ''; ?>"
                                       placeholder="Ej: Juan Pérez">
                            </div>
                            <div class="col-md-3">
                                <label for="fecha_desde" class="form-label">Registrado Desde</label>
                                <input type="date" class="form-control" id="fecha_desde" name="fecha_desde" 
                                       value="<?php echo isset($fecha_desde) ? $fecha_desde : ''; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="fecha_hasta" class="form-label">Registrado Hasta</label>
                                <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta" 
                                       value="<?php echo isset($fecha_hasta) ? $fecha_hasta : ''; ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-search me-2"></i>Buscar Equipos
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-lg ms-3" onclick="limpiarFormulario()">
                        <i class="fas fa-times me-2"></i>Limpiar Filtros
                    </button>
                </div>
            </form>

            <div class="search-tips">
                <h6><i class="fas fa-lightbulb me-2"></i>Consejos de Búsqueda</h6>
                <ul class="mb-0 small">
                    <li>Puede buscar por texto parcial en la mayoría de campos</li>
                    <li><strong>Filtros de RAM:</strong> Seleccione cantidades exactas de RAM para búsquedas específicas</li>
                    <li><strong>Filtros de Disco:</strong> Use los estados predefinidos o porcentajes específicos</li>
                    <li><strong>Filtros de IP:</strong> Busque por rangos específicos (ej: 172.17 para la red local)</li>
                    <li><strong>Botones rápidos:</strong> Use los filtros rápidos arriba para búsquedas comunes</li>
                    <li>Combine múltiples criterios para búsquedas más precisas</li>
                </ul>
            </div>
        </div>

        <!-- Resultados -->
        <?php if ($busqueda_realizada): ?>
        <div class="results-section">
            <div class="card-header">
                <h5>
                    <i class="fas fa-list me-2"></i>
                    Resultados de Búsqueda (<?php echo $total_resultados; ?> equipos encontrados)
                    <?php if (!empty($ram_especifica)): ?>
                    <span class="badge bg-success ms-2">
                        RAM: <?php echo implode(', ', array_map(function($r) { return $r . 'GB'; }, $ram_especifica)); ?>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($estado_disco_filtro)): ?>
                    <span class="badge bg-warning ms-2">
                        Disco: <?php echo ucfirst($estado_disco_filtro); ?>
                    </span>
                    <?php endif; ?>
                </h5>
            </div>
            
            <?php if (empty($equipos)): ?>
            <div class="no-results">
                <i class="fas fa-search fa-3x mb-3 text-muted"></i>
                <h5>No se encontraron equipos</h5>
                <p class="text-muted">
                    Intente modificar los criterios de búsqueda o use menos filtros para obtener más resultados.
                </p>
                <button type="button" class="btn btn-outline-primary" onclick="limpiarFormulario()">
                    <i class="fas fa-refresh me-2"></i>Limpiar y Buscar Nuevamente
                </button>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Equipo</th>
                            <th>Red</th>
                            <th>Hardware</th>
                            <th>Sistema</th>
                            <th>Usuario</th>
                            <th>Estado Disco</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
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
                                    <div><strong>RAM:</strong> 
                                        <span class="<?php echo (!empty($ram_especifica)) ? 'text-success fw-bold' : ''; ?>">
                                            <?php echo formatearRAM($equipo['memoria_ram']); ?>
                                        </span>
                                    </div>
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
                                    <?php if ($equipo['responsable']): ?>
                                    <br><small class="text-muted">Resp: <?php echo $equipo['responsable']; ?></small>
                                    <?php endif; ?>
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
                                       class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($_SESSION['inv_is_admin']): ?>
                                    <a href="equipo_editar.php?id=<?php echo $equipo['id']; ?>" 
                                       class="btn btn-outline-warning btn-sm">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="card-footer text-center">
                <small class="text-muted">
                    Mostrando <?php echo $total_resultados; ?> resultados
                    <?php if ($total_resultados >= 100): ?>
                    (limitado a 100 resultados - refine su búsqueda para ver más)
                    <?php endif; ?>
                </small>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function limpiarFormulario() {
            document.getElementById('searchForm').reset();
            // Limpiar también las selecciones de RAM
            document.querySelectorAll('.ram-checkbox-item').forEach(item => {
                item.classList.remove('selected');
            });
            window.location.href = 'buscar_equipos.php';
        }

        function aplicarFiltroRapido(tipo) {
            limpiarFormulario();
            
            switch(tipo) {
                case 'windows11':
                    document.getElementById('so').value = 'Windows 11';
                    break;
                case 'windows10':
                    document.getElementById('so').value = 'Windows 10';
                    break;
                case 'ubuntu':
                    document.getElementById('so').value = 'Ubuntu';
                    break;
                case 'dell':
                    document.getElementById('fabricante').value = 'Dell Inc.';
                    break;
                case 'agente':
                    document.getElementById('origen').value = 'InventarioAgent';
                    break;
                case 'recientes':
                    const fecha = new Date();
                    fecha.setDate(fecha.getDate() - 7);
                    const fechaString = fecha.toISOString().split('T')[0];
                    document.getElementById('fecha_desde').value = fechaString;
                    break;
            }
            
            document.getElementById('searchForm').submit();
        }

        function toggleRamCheckbox(checkboxId) {
            const checkbox = document.getElementById(checkboxId);
            const container = checkbox.closest('.ram-checkbox-item');
            
            checkbox.checked = !checkbox.checked;
            
            if (checkbox.checked) {
                container.classList.add('selected');
            } else {
                container.classList.remove('selected');
            }
        }

        // Validación de fechas
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('fecha_hasta').addEventListener('change', function() {
                const fechaDesde = document.getElementById('fecha_desde').value;
                const fechaHasta = this.value;
                
                if (fechaDesde && fechaHasta && fechaDesde > fechaHasta) {
                    alert('La fecha "hasta" debe ser posterior a la fecha "desde"');
                    this.value = '';
                }
            });

            // Mantener estado visual de los checkboxes de RAM al cargar la página
            document.querySelectorAll('input[name="ram_especifica[]"]:checked').forEach(checkbox => {
                checkbox.closest('.ram-checkbox-item').classList.add('selected');
            });
        });
    </script>
</body>
</html>
