<?php
// config_inventario.php - Configuración del sistema de inventario de equipos

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'inventario_equipos');
define('DB_USER', 'root'); // Cambiar según tu configuración
define('DB_PASS', ''); // Cambiar según tu configuración

// Configuración de la aplicación
define('APP_NAME', 'Sistema de Inventario de Equipos - División de Informática');
define('APP_VERSION', '1.0');
define('SESSION_TIMEOUT', 3600); // 1 hora en segundos
define('DEFAULT_PASSWORD', 'abc123**');

// Configuración de paginación
define('RECORDS_PER_PAGE', 25);

// Administradores del sistema (hardcoded por seguridad)
$ADMIN_USERS = array(
    'pyela' => array(
        'nombre' => 'Pablo Yela',
        'es_admin' => true
    ),
    'ccatalan' => array(
        'nombre' => 'Carlos Catalan', 
        'es_admin' => true
    ),
    'eoregel' => array(
        'nombre' => 'Enrique Oregel',
        'es_admin' => true
    )
);

// Usuarios de solo lectura (hardcoded por seguridad)
$READONLY_USERS = array(
    'jgutierrezg' => array(
        'nombre' => 'J. Gutierrez',
        'es_admin' => false
    ),
    'hgonzalezn' => array(
        'nombre' => 'H. Gonzalez',
        'es_admin' => false
    )
);

// Combinar todos los usuarios
$ALL_USERS = array_merge($ADMIN_USERS, $READONLY_USERS);

// Función para conectar a la base de datos
function getDBConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        
        // Configurar zona horaria de MySQL para Guatemala (UTC-6)
        $pdo->exec("SET time_zone = '-06:00'");
        
        return $pdo;
    } catch (PDOException $e) {
        die("Error de conexión: " . $e->getMessage());
    }
}

// Función para verificar si un usuario es administrador
function isAdmin($username) {
    global $ADMIN_USERS;
    return array_key_exists($username, $ADMIN_USERS);
}

// Función para verificar si un usuario existe
function userExists($username) {
    global $ALL_USERS;
    return array_key_exists($username, $ALL_USERS);
}

// Función para obtener información del usuario
function getUserInfo($username) {
    global $ALL_USERS;
    return isset($ALL_USERS[$username]) ? $ALL_USERS[$username] : null;
}

// Función para verificar sesión activa
function checkSession() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['inv_user_id']) || !isset($_SESSION['inv_username'])) {
        header('Location: login_inventario.php');
        exit;
    }
    
    // Verificar timeout de sesión
    if (isset($_SESSION['inv_last_activity']) && (time() - $_SESSION['inv_last_activity'] > SESSION_TIMEOUT)) {
        session_destroy();
        header('Location: login_inventario.php?timeout=1');
        exit;
    }
    
    $_SESSION['inv_last_activity'] = time();
}

// Función para verificar permisos de administrador
function requireAdmin() {
    checkSession();
    if (!$_SESSION['inv_is_admin']) {
        header('Location: index_inventario.php?error=access_denied');
        exit;
    }
}

// Función para verificar contraseña
function verifyPassword($password, $username) {
    // Por simplicidad, verificamos contra la contraseña por defecto
    // En producción se podría usar hash y base de datos
    return $password === DEFAULT_PASSWORD;
}

// Función para cambiar contraseña (simulada - en producción usar BD)
function changePassword($username, $old_password, $new_password) {
    if (!verifyPassword($old_password, $username)) {
        return false;
    }
    
    // En una implementación real, aquí se actualizaría la BD
    // Por ahora solo simulamos el cambio
    return true;
}

// Función para formatear fechas
function formatDate($date, $format = 'd/m/Y H:i') {
    if (empty($date) || $date === '0000-00-00 00:00:00') {
        return '-';
    }
    return date($format, strtotime($date));
}

// Función para limpiar entrada de datos
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Función para calcular porcentaje de uso de disco
function calcularPorcentajeUso($disco_info) {
    if (empty($disco_info)) return 0;
    
    // Buscar patrones como "174GB de 930GB (18% usado)"
    if (preg_match('/\((\d+)%\s*usado\)/', $disco_info, $matches)) {
        return (int)$matches[1];
    }
    
    // Buscar patrones como "Usado: 4.6G de 79G (7% usado)"
    if (preg_match('/(\d+)%\s*usado/', $disco_info, $matches)) {
        return (int)$matches[1];
    }
    
    return 0;
}

// Función para obtener estado del equipo basado en uso de disco
function getEstadoDisco($disco_info) {
    $porcentaje = calcularPorcentajeUso($disco_info);
    
    if ($porcentaje >= 90) {
        return ['estado' => 'critico', 'clase' => 'danger', 'icono' => 'exclamation-triangle'];
    } elseif ($porcentaje >= 70) {
        return ['estado' => 'alerta', 'clase' => 'warning', 'icono' => 'exclamation-circle'];
    } elseif ($porcentaje >= 30) {
        return ['estado' => 'normal', 'clase' => 'success', 'icono' => 'check-circle'];
    } else {
        return ['estado' => 'optimo', 'clase' => 'info', 'icono' => 'info-circle'];
    }
}

// Función para extraer información de RAM
function formatearRAM($memoria_ram) {
    if (empty($memoria_ram)) return '-';
    
    // Extraer número de GB
    if (preg_match('/(\d+(?:\.\d+)?)\s*GB/', $memoria_ram, $matches)) {
        return $matches[1] . ' GB';
    }
    
    return $memoria_ram;
}

// Función para extraer información del procesador
function formatearProcesador($procesador) {
    if (empty($procesador)) return '-';
    
    // Extraer modelo básico del procesador
    if (preg_match('/Intel\(R\)\s+Core\(TM\)\s+(i\d+-\w+)/', $procesador, $matches)) {
        return $matches[1];
    }
    
    if (preg_match('/Intel\(R\)\s+Core\(TM\)\s+(i\d+-\d+\w*)/', $procesador, $matches)) {
        return $matches[1];
    }
    
    return substr($procesador, 0, 30) . (strlen($procesador) > 30 ? '...' : '');
}

// Función para validar IP
function validarIP($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP) !== false;
}

// Función para obtener equipos con bajo espacio en disco
function getEquiposBajoEspacio($pdo, $porcentaje_minimo = 70) {
    $stmt = $pdo->query("
        SELECT id, nombre_equipo, ip_equipo, disco_duro, usuario_sistema
        FROM equipos_info 
        WHERE disco_duro IS NOT NULL 
        ORDER BY fecha_captura DESC
    ");
    
    $equipos = $stmt->fetchAll();
    $equipos_filtrados = [];
    
    foreach ($equipos as $equipo) {
        $porcentaje = calcularPorcentajeUso($equipo['disco_duro']);
        if ($porcentaje >= $porcentaje_minimo) {
            $equipo['porcentaje_uso'] = $porcentaje;
            $equipos_filtrados[] = $equipo;
        }
    }
    
    return $equipos_filtrados;
}

// Configurar zona horaria para Guatemala
date_default_timezone_set('America/Guatemala');

// Configurar manejo de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'inventario_error.log');
?>
