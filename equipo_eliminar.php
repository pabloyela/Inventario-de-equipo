<?php
require_once 'config_inventario.php';
checkSession();
requireAdmin(); // Solo administradores pueden eliminar equipos

$pdo = getDBConnection();

// Verificar que se proporcionó un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: equipos_lista.php?error=id_invalido');
    exit;
}

$equipo_id = (int)$_GET['id'];

// Obtener información del equipo antes de eliminarlo
$stmt = $pdo->prepare("SELECT * FROM equipos_info WHERE id = ?");
$stmt->execute([$equipo_id]);
$equipo = $stmt->fetch();

if (!$equipo) {
    header('Location: equipos_lista.php?error=equipo_no_encontrado');
    exit;
}

$mensaje = '';
$tipo_mensaje = '';
$equipo_eliminado = false;

// Procesar confirmación de eliminación
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirmar_eliminacion'])) {
    try {
        $razon = sanitizeInput($_POST['razon_eliminacion']);
        
        if (empty($razon)) {
            throw new Exception('Debe proporcionar una razón para la eliminación');
        }
        
        // Crear un respaldo de la información antes de eliminar
        $backup_data = [
            'equipo_eliminado' => $equipo,
            'fecha_eliminacion' => date('Y-m-d H:i:s'),
            'eliminado_por' => $_SESSION['inv_full_name'],
            'razon' => $razon
        ];
        
        // Log de auditoría manual (opcional, según tu sistema de auditoría)
        error_log("ELIMINACIÓN DE EQUIPO - ID: {$equipo_id}, Nombre: {$equipo['nombre_equipo']}, Por: {$_SESSION['inv_full_name']}, Razón: {$razon}");
        
        // Eliminar el equipo de la base de datos
        $stmt = $pdo->prepare("DELETE FROM equipos_info WHERE id = ?");
        $stmt->execute([$equipo_id]);
        
        if ($stmt->rowCount() > 0) {
            $mensaje = "Equipo '{$equipo['nombre_equipo']}' eliminado exitosamente";
            $tipo_mensaje = 'success';
            $equipo_eliminado = true;
        } else {
            throw new Exception('No se pudo eliminar el equipo');
        }
        
    } catch (Exception $e) {
        $mensaje = 'Error al eliminar: ' . $e->getMessage();
        $tipo_mensaje = 'danger';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eliminar Equipo - <?php echo $equipo['nombre_equipo']; ?></title>
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
            --danger-color: #dc3545;
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
            padding: 40px 0;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }

        .card-header {
            background: linear-gradient(135deg, var(--danger-color), #c82333);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 20px;
            border: none;
        }

        .card-header h4 {
            margin: 0;
            font-weight: 600;
        }

        .danger-section {
            background: #fff5f5;
            border: 2px solid #fed7d7;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
        }

        .equipo-info {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .info-label {
            font-weight: 600;
            color: var(--primary-blue);
            margin-bottom: 5px;
        }

        .info-value {
            color: #333;
            margin-bottom: 10px;
            padding: 5px 10px;
            background-color: var(--very-light-blue);
            border-radius: 4px;
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--danger-color);
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }

        .btn {
            border-radius: 8px;
            font-weight: 500;
            padding: 12px 25px;
            transition: all 0.3s ease;
        }

        .btn-danger {
            background-color: var(--danger-color);
            border-color: var(--danger-color);
        }

        .btn-danger:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
        }

        .alert {
            border-radius: 8px;
            border: none;
        }

        .warning-box {
            background: #fff3cd;
            border: 2px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .warning-box h6 {
            color: #856404;
            margin-bottom: 10px;
        }

        .warning-box ul {
            color: #856404;
            margin-bottom: 0;
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

        .required {
            color: var(--danger-color);
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
                <?php if (!$equipo_eliminado): ?>
                <a class="nav-link" href="equipo_detalle.php?id=<?php echo $equipo_id; ?>">
                    <i class="fas fa-eye me-1"></i>Ver Detalle
                </a>
                <?php endif; ?>
                <a class="nav-link" href="equipos_lista.php">
                    <i class="fas fa-arrow-left me-1"></i>Volver a Lista
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container main-content">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index_inventario.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="equipos_lista.php">Equipos</a></li>
                <?php if (!$equipo_eliminado): ?>
                <li class="breadcrumb-item"><a href="equipo_detalle.php?id=<?php echo $equipo_id; ?>"><?php echo $equipo['nombre_equipo']; ?></a></li>
                <li class="breadcrumb-item active">Eliminar</li>
                <?php else: ?>
                <li class="breadcrumb-item active">Equipo Eliminado</li>
                <?php endif; ?>
            </ol>
        </nav>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <!-- Header -->
                <div class="card">
                    <div class="card-header">
                        <h4>
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo $equipo_eliminado ? 'Equipo Eliminado' : 'Eliminar Equipo'; ?>
                        </h4>
                        <?php if (!$equipo_eliminado): ?>
                        <p class="mb-0 mt-2 opacity-75">
                            Esta acción eliminará permanentemente el equipo del inventario
                        </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Mensajes -->
                <?php if (!empty($mensaje)): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                    <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo $mensaje; ?>
                    <?php if ($equipo_eliminado): ?>
                    <div class="mt-2">
                        <a href="equipos_lista.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-list me-1"></i>Volver a Lista de Equipos
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if (!$equipo_eliminado): ?>
                <!-- Información del equipo a eliminar -->
                <div class="equipo-info">
                    <h5 class="text-danger mb-3">
                        <i class="fas fa-desktop me-2"></i>
                        Información del Equipo a Eliminar
                    </h5>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-label">Nombre del Equipo</div>
                            <div class="info-value"><?php echo $equipo['nombre_equipo']; ?></div>
                            
                            <div class="info-label">Fabricante</div>
                            <div class="info-value"><?php echo $equipo['fabricante']; ?></div>
                            
                            <div class="info-label">Modelo</div>
                            <div class="info-value"><?php echo $equipo['modelo']; ?></div>
                            
                            <div class="info-label">IP Externa</div>
                            <div class="info-value"><?php echo $equipo['ip_equipo']; ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-label">Usuario del Sistema</div>
                            <div class="info-value"><?php echo $equipo['usuario_sistema'] ?: 'No especificado'; ?></div>
                            
                            <div class="info-label">Sistema Operativo</div>
                            <div class="info-value"><?php echo $equipo['sistema_operativo']; ?></div>
                            
                            <div class="info-label">Fecha de Registro</div>
                            <div class="info-value"><?php echo formatDate($equipo['created_at'], 'd/m/Y H:i'); ?></div>
                            
                            <div class="info-label">Origen de Datos</div>
                            <div class="info-value"><?php echo $equipo['origen_datos']; ?></div>
                        </div>
                    </div>

                    <?php if ($equipo['numero_serie']): ?>
                    <div class="info-label">Número de Serie</div>
                    <div class="info-value"><?php echo $equipo['numero_serie']; ?></div>
                    <?php endif; ?>

                    <?php if ($equipo['observaciones']): ?>
                    <div class="info-label">Observaciones</div>
                    <div class="info-value"><?php echo nl2br($equipo['observaciones']); ?></div>
                    <?php endif; ?>
                </div>

                <!-- Advertencias -->
                <div class="warning-box">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Advertencias Importantes</h6>
                    <ul>
                        <li><strong>Esta acción no se puede deshacer.</strong> El equipo será eliminado permanentemente.</li>
                        <li>Se perderá todo el historial de este equipo en el sistema.</li>
                        <li>Si el equipo tiene datos importantes, considere marcarlo como "Inactivo" en lugar de eliminarlo.</li>
                        <li>Esta eliminación será registrada en los logs del sistema.</li>
                    </ul>
                </div>

                <!-- Formulario de confirmación -->
                <div class="danger-section">
                    <h5 class="text-danger mb-3">
                        <i class="fas fa-shield-alt me-2"></i>
                        Confirmación de Eliminación
                    </h5>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="razon_eliminacion" class="form-label">
                                Razón para la eliminación <span class="required">*</span>
                            </label>
                            <select class="form-select mb-2" id="razon_tipo" onchange="updateRazonField()">
                                <option value="">Seleccionar tipo de razón</option>
                                <option value="Equipo dado de baja">Equipo dado de baja</option>
                                <option value="Equipo duplicado">Registro duplicado</option>
                                <option value="Equipo vendido/transferido">Equipo vendido o transferido</option>
                                <option value="Error en registro">Error en el registro</option>
                                <option value="Mantenimiento de base de datos">Mantenimiento de base de datos</option>
                                <option value="Otro">Otro (especificar)</option>
                            </select>
                            <textarea class="form-control" id="razon_eliminacion" name="razon_eliminacion" rows="3"
                                      placeholder="Describa detalladamente la razón para eliminar este equipo..."
                                      required></textarea>
                            <div class="form-text">
                                Esta información será registrada para auditoría. Sea específico sobre el motivo.
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="confirmar_entendimiento" required>
                                <label class="form-check-label" for="confirmar_entendimiento">
                                    <strong>Confirmo que entiendo que esta acción es irreversible y eliminará permanentemente el equipo del inventario.</strong>
                                </label>
                            </div>
                        </div>

                        <div class="text-center">
                            <a href="equipo_detalle.php?id=<?php echo $equipo_id; ?>" class="btn btn-secondary me-3">
                                <i class="fas fa-times me-2"></i>Cancelar
                            </a>
                            <button type="submit" name="confirmar_eliminacion" class="btn btn-danger" 
                                    onclick="return confirmarEliminacion()">
                                <i class="fas fa-trash me-2"></i>Eliminar Equipo Permanentemente
                            </button>
                        </div>
                    </form>
                </div>
                <?php else: ?>
                <!-- Mensaje de eliminación exitosa -->
                <div class="equipo-info text-center">
                    <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                    <h5>El equipo ha sido eliminado exitosamente</h5>
                    <p class="text-muted">
                        El equipo "<?php echo $equipo['nombre_equipo']; ?>" ya no está disponible en el inventario.
                    </p>
                    
                    <div class="mt-4">
                        <a href="equipos_lista.php" class="btn btn-primary">
                            <i class="fas fa-list me-2"></i>Ver Lista de Equipos
                        </a>
                        <a href="index_inventario.php" class="btn btn-outline-primary">
                            <i class="fas fa-tachometer-alt me-2"></i>Ir al Dashboard
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function updateRazonField() {
            const tipo = document.getElementById('razon_tipo').value;
            const textarea = document.getElementById('razon_eliminacion');
            
            if (tipo && tipo !== 'Otro') {
                textarea.value = tipo + ': ';
                textarea.focus();
                // Posicionar cursor al final
                textarea.setSelectionRange(textarea.value.length, textarea.value.length);
            } else if (tipo === 'Otro') {
                textarea.value = '';
                textarea.focus();
            }
        }

        function confirmarEliminacion() {
            const equipoNombre = '<?php echo addslashes($equipo['nombre_equipo']); ?>';
            const razon = document.getElementById('razon_eliminacion').value.trim();
            
            if (razon.length < 10) {
                alert('La razón de eliminación debe ser más específica (mínimo 10 caracteres).');
                return false;
            }
            
            const confirmacion = confirm(
                '¿ESTÁ COMPLETAMENTE SEGURO que desea eliminar el equipo "' + equipoNombre + '"?\n\n' +
                'Esta acción NO SE PUEDE DESHACER.\n\n' +
                'Razón: ' + razon
            );
            
            if (confirmacion) {
                return confirm(
                    'ÚLTIMA CONFIRMACIÓN:\n\n' +
                    'Al hacer clic en OK, el equipo será eliminado PERMANENTEMENTE.\n\n' +
                    '¿Continuar con la eliminación?'
                );
            }
            
            return false;
        }

        // Deshabilitar botón si no se ha marcado el checkbox
        document.getElementById('confirmar_entendimiento').addEventListener('change', function() {
            const btn = document.querySelector('button[name="confirmar_eliminacion"]');
            btn.disabled = !this.checked;
        });

        // Inicialmente deshabilitar el botón
        document.addEventListener('DOMContentLoaded', function() {
            const btn = document.querySelector('button[name="confirmar_eliminacion"]');
            if (btn) {
                btn.disabled = true;
            }
        });
    </script>
</body>
</html>
