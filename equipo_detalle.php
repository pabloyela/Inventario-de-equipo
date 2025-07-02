<?php
require_once 'config_inventario.php';
checkSession();

$pdo = getDBConnection();

// Verificar que se proporcionó un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: equipos_lista.php?error=id_invalido');
    exit;
}

$equipo_id = (int)$_GET['id'];

// Obtener información del equipo
$stmt = $pdo->prepare("
    SELECT * FROM equipos_info 
    WHERE id = ?
");
$stmt->execute([$equipo_id]);
$equipo = $stmt->fetch();

if (!$equipo) {
    header('Location: equipos_lista.php?error=equipo_no_encontrado');
    exit;
}

// Calcular información adicional
$estado_disco = getEstadoDisco($equipo['disco_duro']);
$porcentaje_uso = calcularPorcentajeUso($equipo['disco_duro']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Equipo - <?php echo $equipo['nombre_equipo']; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="styles_detalle.css" rel="stylesheet">
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
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index_inventario.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="equipos_lista.php">Equipos</a></li>
                <li class="breadcrumb-item active"><?php echo $equipo['nombre_equipo']; ?></li>
            </ol>
        </nav>

        <!-- Header del equipo -->
        <div class="card">
            <div class="card-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4>
                            <i class="fas fa-desktop me-2"></i>
                            <?php echo $equipo['nombre_equipo']; ?>
                        </h4>
                        <p class="mb-0 mt-2 opacity-75">
                            <?php echo $equipo['fabricante']; ?> <?php echo $equipo['modelo']; ?>
                            <?php if ($equipo['numero_serie']): ?>
                            | S/N: <?php echo $equipo['numero_serie']; ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <?php if ($_SESSION['inv_is_admin']): ?>
                        <a href="equipo_editar.php?id=<?php echo $equipo['id']; ?>" class="btn btn-warning">
                            <i class="fas fa-edit me-2"></i>Editar Equipo
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alertas de estado -->
        <?php if ($porcentaje_uso >= 90): ?>
        <div class="alert alert-danger alert-custom">
            <h6><i class="fas fa-exclamation-triangle me-2"></i>Espacio en Disco Crítico</h6>
            <p class="mb-0">El equipo tiene <?php echo $porcentaje_uso; ?>% de uso en disco. Se recomienda liberar espacio inmediatamente.</p>
        </div>
        <?php elseif ($porcentaje_uso >= 70): ?>
        <div class="alert alert-warning alert-custom">
            <h6><i class="fas fa-exclamation-circle me-2"></i>Espacio en Disco Bajo</h6>
            <p class="mb-0">El equipo tiene <?php echo $porcentaje_uso; ?>% de uso en disco. Se recomienda liberar espacio pronto.</p>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Información General -->
            <div class="col-md-6">
                <div class="info-section">
                    <h5 class="section-title">
                        <i class="fas fa-info-circle me-2"></i>Información General
                    </h5>
                    
                    <div class="info-label">Nombre del Equipo</div>
                    <div class="info-value"><?php echo $equipo['nombre_equipo']; ?></div>
                    
                    <div class="info-label">Fabricante</div>
                    <div class="info-value"><?php echo $equipo['fabricante']; ?></div>
                    
                    <div class="info-label">Modelo</div>
                    <div class="info-value"><?php echo $equipo['modelo']; ?></div>
                    
                    <?php if ($equipo['numero_serie']): ?>
                    <div class="info-label">Número de Serie</div>
                    <div class="info-value"><?php echo $equipo['numero_serie']; ?></div>
                    <?php endif; ?>
                    
                    <div class="info-label">Usuario del Sistema</div>
                    <div class="info-value">
                        <i class="fas fa-user me-2"></i>
                        <?php echo $equipo['usuario_sistema'] ?: 'No especificado'; ?>
                    </div>
                    
                    <div class="info-label">Origen de Datos</div>
                    <div class="info-value">
                        <?php if ($equipo['origen_datos'] === 'InventarioAgent'): ?>
                            <span class="status-badge success">
                                <i class="fas fa-robot me-1"></i>Agente Inventario
                            </span>
                        <?php elseif ($equipo['origen_datos'] === 'Script Bash'): ?>
                            <span class="status-badge info">
                                <i class="fas fa-terminal me-1"></i>Script Bash
                            </span>
                        <?php else: ?>
                            <span class="status-badge warning">
                                <i class="fas fa-globe me-1"></i><?php echo $equipo['origen_datos']; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Información Administrativa -->
            <div class="col-md-6">
                <div class="info-section">
                    <h5 class="section-title">
                        <i class="fas fa-cogs me-2"></i>Información Administrativa
                    </h5>
                    
                    <div class="info-label">Estado del Equipo</div>
                    <div class="info-value">
                        <?php 
                        $estado_clase = '';
                        $estado_icono = '';
                        switch($equipo['estado_equipo']) {
                            case 'Activo':
                                $estado_clase = 'estado-equipo-activo';
                                $estado_icono = 'check-circle';
                                break;
                            case 'Inactivo':
                                $estado_clase = 'estado-equipo-inactivo';
                                $estado_icono = 'pause-circle';
                                break;
                            case 'Mantenimiento':
                                $estado_clase = 'estado-equipo-mantenimiento';
                                $estado_icono = 'tools';
                                break;
                            case 'Dado de baja':
                                $estado_clase = 'estado-equipo-baja';
                                $estado_icono = 'times-circle';
                                break;
                            default:
                                $estado_clase = 'estado-equipo-activo';
                                $estado_icono = 'check-circle';
                        }
                        ?>
                        <span class="status-badge <?php echo $estado_clase; ?>">
                            <i class="fas fa-<?php echo $estado_icono; ?> me-1"></i>
                            <?php echo $equipo['estado_equipo'] ?: 'Activo'; ?>
                        </span>
                    </div>
                    
                    <div class="info-label">Ubicación</div>
                    <?php if (!empty($equipo['ubicacion'])): ?>
                    <div class="info-value">
                        <i class="fas fa-map-marker-alt me-2"></i>
                        <?php echo $equipo['ubicacion']; ?>
                    </div>
                    <?php else: ?>
                    <div class="info-value empty-field">
                        <i class="fas fa-map-marker-alt me-2"></i>
                        No especificada
                    </div>
                    <?php endif; ?>
                    
                    <div class="info-label">Responsable</div>
                    <?php if (!empty($equipo['responsable'])): ?>
                    <div class="info-value">
                        <i class="fas fa-user-tie me-2"></i>
                        <?php echo $equipo['responsable']; ?>
                    </div>
                    <?php else: ?>
                    <div class="info-value empty-field">
                        <i class="fas fa-user-tie me-2"></i>
                        No asignado
                    </div>
                    <?php endif; ?>
                    
                    <div class="info-label">Último Mantenimiento</div>
                    <?php if (!empty($equipo['fecha_ultimo_mantenimiento']) && $equipo['fecha_ultimo_mantenimiento'] !== '0000-00-00'): ?>
                    <div class="info-value">
                        <i class="fas fa-wrench me-2"></i>
                        <?php echo formatDate($equipo['fecha_ultimo_mantenimiento'], 'd/m/Y'); ?>
                    </div>
                    <?php else: ?>
                    <div class="info-value empty-field">
                        <i class="fas fa-wrench me-2"></i>
                        Sin registro
                    </div>
                    <?php endif; ?>
                    
                    <div class="info-label">Vencimiento de Garantía</div>
                    <?php if (!empty($equipo['garantia_vencimiento']) && $equipo['garantia_vencimiento'] !== '0000-00-00'): ?>
                    <div class="info-value">
                        <i class="fas fa-calendar-check me-2"></i>
                        <?php echo formatDate($equipo['garantia_vencimiento'], 'd/m/Y'); ?>
                        <?php 
                        $fecha_garantia = strtotime($equipo['garantia_vencimiento']);
                        $fecha_actual = time();
                        $dias_restantes = ceil(($fecha_garantia - $fecha_actual) / (60 * 60 * 24));
                        
                        if ($dias_restantes < 0): ?>
                            <span class="status-badge danger ms-2">
                                <i class="fas fa-exclamation-triangle me-1"></i>Vencida
                            </span>
                        <?php elseif ($dias_restantes <= 30): ?>
                            <span class="status-badge warning ms-2">
                                <i class="fas fa-clock me-1"></i>Próxima a vencer
                            </span>
                        <?php else: ?>
                            <span class="status-badge success ms-2">
                                <i class="fas fa-check me-1"></i>Vigente
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="info-value empty-field">
                        <i class="fas fa-calendar-check me-2"></i>
                        Sin información
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Información de Red -->
            <div class="col-md-6">
                <div class="info-section">
                    <h5 class="section-title">
                        <i class="fas fa-network-wired me-2"></i>Configuración de Red
                    </h5>
                    
                    <div class="info-label">IP Externa</div>
                    <div class="info-value">
                        <i class="fas fa-globe me-2"></i>
                        <?php echo $equipo['ip_equipo']; ?>
                    </div>
                    
                    <?php if ($equipo['ip_local']): ?>
                    <div class="info-label">IP Local</div>
                    <div class="info-value">
                        <i class="fas fa-home me-2"></i>
                        <?php echo $equipo['ip_local']; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($equipo['tarjeta_red']): ?>
                    <div class="info-label">Tarjeta(s) de Red</div>
                    <div class="info-value">
                        <i class="fas fa-ethernet me-2"></i>
                        <?php echo $equipo['tarjeta_red']; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Información de Hardware -->
            <div class="col-md-6">
                <div class="info-section">
                    <h5 class="section-title">
                        <i class="fas fa-microchip me-2"></i>Hardware
                    </h5>
                    
                    <?php if ($equipo['procesador']): ?>
                    <div class="info-label">Procesador</div>
                    <div class="info-value">
                        <i class="fas fa-microchip me-2"></i>
                        <?php echo $equipo['procesador']; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($equipo['memoria_ram']): ?>
                    <div class="info-label">Memoria RAM</div>
                    <div class="info-value">
                        <i class="fas fa-memory me-2"></i>
                        <?php echo $equipo['memoria_ram']; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($equipo['disco_duro']): ?>
                    <div class="info-label">Almacenamiento</div>
                    <div class="info-value">
                        <i class="fas fa-hdd me-2"></i>
                        <?php echo $equipo['disco_duro']; ?>
                        
                        <?php if ($porcentaje_uso > 0): ?>
                        <div class="disk-usage-bar mt-2">
                            <div class="disk-usage-fill bg-<?php echo $estado_disco['clase']; ?>" 
                                 style="width: <?php echo $porcentaje_uso; ?>%">
                                <div class="disk-usage-text"><?php echo $porcentaje_uso; ?>%</div>
                            </div>
                        </div>
                        <small class="text-<?php echo $estado_disco['clase']; ?> fw-bold">
                            Estado: <?php echo ucfirst($estado_disco['estado']); ?>
                        </small>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Información de Software -->
            <div class="col-md-6">
                <div class="info-section">
                    <h5 class="section-title">
                        <i class="fas fa-laptop me-2"></i>Software
                    </h5>
                    
                    <div class="info-label">Sistema Operativo</div>
                    <div class="info-value">
                        <?php if (strpos($equipo['sistema_operativo'], 'Windows 11') !== false): ?>
                            <i class="fab fa-windows me-2 text-primary"></i>
                        <?php elseif (strpos($equipo['sistema_operativo'], 'Windows') !== false): ?>
                            <i class="fab fa-windows me-2 text-info"></i>
                        <?php elseif (strpos($equipo['sistema_operativo'], 'Ubuntu') !== false): ?>
                            <i class="fab fa-ubuntu me-2 text-warning"></i>
                        <?php else: ?>
                            <i class="fas fa-desktop me-2"></i>
                        <?php endif; ?>
                        <?php echo $equipo['sistema_operativo']; ?>
                    </div>
                    
                    <?php if ($equipo['navegador']): ?>
                    <div class="info-label">Navegador/User Agent</div>
                    <div class="info-value">
                        <i class="fas fa-globe me-2"></i>
                        <?php echo $equipo['navegador']; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($equipo['user_agent'] && $equipo['user_agent'] !== $equipo['navegador']): ?>
                    <div class="info-label">User Agent Completo</div>
                    <div class="info-value" style="font-size: 0.8rem; word-break: break-all;">
                        <?php echo $equipo['user_agent']; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Información de Fechas -->
            <div class="col-md-6">
                <div class="info-section">
                    <h5 class="section-title">
                        <i class="fas fa-clock me-2"></i>Historial de Registro
                    </h5>
                    
                    <div class="row">
                        <div class="col-md-3">
                            <div class="metric-card bg-light">
                                <div class="metric-number text-primary">
                                    <?php echo formatDate($equipo['fecha_captura'], 'd'); ?>
                                </div>
                                <div class="metric-label">Día de Captura</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="metric-card bg-light">
                                <div class="metric-number text-info">
                                    <?php echo formatDate($equipo['fecha_captura'], 'm'); ?>
                                </div>
                                <div class="metric-label">Mes de Captura</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="metric-card bg-light">
                                <div class="metric-number text-success">
                                    <?php echo formatDate($equipo['created_at'], 'd/m/Y'); ?>
                                </div>
                                <div class="metric-label">Fecha de Creación</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="metric-card bg-light">
                                <div class="metric-number text-warning">
                                    <?php echo formatDate($equipo['updated_at'], 'd/m/Y'); ?>
                                </div>
                                <div class="metric-label">Última Actualización</div>
                            </div>
                        </div>
                    </div>

                    <div class="timeline-item">
                        <strong>Captura de Datos:</strong> <?php echo formatDate($equipo['fecha_captura'], 'd/m/Y H:i'); ?>
                        <br><small class="text-muted">Información obtenida desde <?php echo $equipo['origen_datos']; ?></small>
                    </div>
                    
                    <div class="timeline-item">
                        <strong>Registro en Sistema:</strong> <?php echo formatDate($equipo['created_at'], 'd/m/Y H:i'); ?>
                        <br><small class="text-muted">Primera vez registrado en la base de datos</small>
                    </div>
                    
                    <div class="timeline-item">
                        <strong>Última Modificación:</strong> <?php echo formatDate($equipo['updated_at'], 'd/m/Y H:i'); ?>
                        <br><small class="text-muted">Última actualización de información</small>
                    </div>
                </div>
            </div>

            <!-- Observaciones al final -->
            <?php if ($equipo['observaciones']): ?>
            <div class="col-md-12">
                <div class="info-section">
                    <h5 class="section-title">
                        <i class="fas fa-sticky-note me-2"></i>Observaciones
                    </h5>
                    <div class="info-value">
                        <i class="fas fa-comment me-2"></i>
                        <?php echo nl2br($equipo['observaciones']); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Acciones adicionales -->
        <div class="text-center mt-4">
            <a href="equipos_lista.php" class="btn btn-outline-primary">
                <i class="fas fa-list me-2"></i>Volver a Lista de Equipos
            </a>
            <?php if ($_SESSION['inv_is_admin']): ?>
            <a href="equipo_editar.php?id=<?php echo $equipo['id']; ?>" class="btn btn-warning">
                <i class="fas fa-edit me-2"></i>Editar Información
            </a>
            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#eliminarModal">
                <i class="fas fa-trash me-2"></i>Eliminar Equipo
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($_SESSION['inv_is_admin']): ?>
    <!-- Modal de Confirmación de Eliminación -->
    <div class="modal fade" id="eliminarModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Confirmar Eliminación
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro que desea eliminar el equipo <strong><?php echo $equipo['nombre_equipo']; ?></strong>?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-warning me-2"></i>
                        <strong>Advertencia:</strong> Esta acción no se puede deshacer.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <a href="equipo_eliminar.php?id=<?php echo $equipo['id']; ?>" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Eliminar Equipo
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>
