<?php
/**
 * ═══════════════════════════════════════════════════════════
 * PÁGINA DE LOGIN - SISTEMA MEGA MODA
 * ═══════════════════════════════════════════════════════════
 * 
 * MEJORAS DE SEGURIDAD IMPLEMENTADAS:
 * ✅ Protección CSRF
 * ✅ Rate limiting (anti fuerza bruta)
 * ✅ Hashing de contraseñas con Argon2id
 * ✅ Sesiones seguras
 * ✅ Sanitización de inputs
 * ✅ Headers de seguridad
 * ✅ Logging de intentos fallidos
 * 
 * @version 2.0.0
 */

// Definir constante de acceso
define('APP_ACCESS', true);

// Cargar configuración y seguridad
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/funcionesphp1.php';

// Iniciar sesión segura
Security::startSecureSession();

// Enviar headers de seguridad
Security::setSecurityHeaders();

// Verificar que exista la función conectar()
if (!function_exists('conectar')) {
    die("Error: La función conectar() no está definida. Revisa conexion.php.");
}

// Si ya hay sesión activa, redirigir
if (isset($_SESSION['usuario'])) {
    $sucursal = $_SESSION['sucursal'] ?? '';
    
    // Redirección según sucursal
    if ($sucursal === 'ADM') {
        header("Location: administracion.php");
        exit();
    } elseif ($sucursal === 'CAJ') {
        header("Location: caja/caja.php");
        exit();
    } else {
        header("Location: registro1.php");
        exit();
    }
}

// Generar token CSRF
$csrfToken = Security::generateCSRFToken();

// Variables para mensajes
$mensajeError = '';
$mensajeTipo = 'danger'; // danger, warning, info

// ═══════════════════════════════════════════════════════════
// PROCESAR LOGIN
// ═══════════════════════════════════════════════════════════

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        // ─────────────────────────────────────────────────────
        // 1. VALIDAR TOKEN CSRF
        // ─────────────────────────────────────────────────────
        $tokenRecibido = $_POST['csrf_token'] ?? '';
        
        if (!Security::validateCSRFToken($tokenRecibido)) {
            error_log('SECURITY: CSRF token validation failed from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            $mensajeError = 'Token de seguridad inválido. Por favor recargue la página.';
            throw new Exception($mensajeError);
        }
        
        // ─────────────────────────────────────────────────────
        // 2. SANITIZAR Y VALIDAR INPUTS
        // ─────────────────────────────────────────────────────
        $username = Security::sanitizeInput($_POST['usuario'] ?? '', 'string');
        $password = $_POST['contrasena'] ?? ''; // No sanitizar password (puede tener caracteres especiales)
        $sucursal = Security::sanitizeInput($_POST['sucursal'] ?? '', 'alphanumeric');
        
        // Validar campos no vacíos
        if (empty($username) || empty($password) || empty($sucursal)) {
            $mensajeError = 'Por favor complete todos los campos.';
            throw new Exception($mensajeError);
        }
        
        // ─────────────────────────────────────────────────────
        // 3. VERIFICAR RATE LIMITING
        // ─────────────────────────────────────────────────────
        $identifier = $username . '|' . ($_SERVER['REMOTE_ADDR'] ?? '');
        
        if (Security::isRateLimited($identifier)) {
            $remaining = Security::getRateLimitRemaining($identifier);
            $minutos = ceil($remaining / 60);
            
            error_log("SECURITY: Rate limit exceeded for user: $username from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            
            $mensajeError = "Demasiados intentos fallidos. Por favor intente nuevamente en $minutos minuto(s).";
            $mensajeTipo = 'warning';
            throw new Exception($mensajeError);
        }
        
        // ─────────────────────────────────────────────────────
        // 4. CONECTAR A BASE DE DATOS
        // ─────────────────────────────────────────────────────
        $conexion = conectar(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        // ─────────────────────────────────────────────────────
        // 5. OBTENER SUCURSALES DISPONIBLES
        // ─────────────────────────────────────────────────────
        $sucursales = obtenerSucursales($conexion);
        
        // ─────────────────────────────────────────────────────
        // 6. BUSCAR USUARIO EN BASE DE DATOS
        // ─────────────────────────────────────────────────────
        $sql = "SELECT * FROM usuarios WHERE nombre_usuario = :u LIMIT 1";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([':u' => $username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // ─────────────────────────────────────────────────────
        // 7. VERIFICAR USUARIO Y CONTRASEÑA
        // ─────────────────────────────────────────────────────
        if (!$row) {
            // Usuario no existe
            Security::recordFailedLogin($identifier);
            
            error_log("LOGIN FAILED: User not found: $username from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            
            // Mensaje genérico (no revelar si usuario existe o no)
            $mensajeError = 'Usuario y/o contraseña incorrectos.';
            throw new Exception($mensajeError);
        }
        
        // Verificar contraseña
        $passwordHash = $row['contrasena'] ?? '';
        
        // MIGRACIÓN: Soportar contraseñas legacy (texto plano) y nuevas (hash)
        $passwordValida = false;
        
        // Verificar si es un hash válido (empieza con $2y$ o $argon2)
        if (strpos($passwordHash, '$') === 0) {
            // Es un hash, verificar con password_verify
            $passwordValida = Security::verifyPassword($password, $passwordHash);
            
            // Si la contraseña es válida pero el hash está desactualizado, rehashear
            if ($passwordValida && Security::needsRehash($passwordHash)) {
                $nuevoHash = Security::hashPassword($password);
                $stmtUpdate = $conexion->prepare("UPDATE usuarios SET contrasena = :hash WHERE id = :id");
                $stmtUpdate->execute([':hash' => $nuevoHash, ':id' => $row['id']]);
                
                error_log("PASSWORD REHASHED for user: $username");
            }
        } else {
            // Es texto plano (legacy), comparar directamente
            $passwordValida = ($password === $passwordHash);
            
            // Si es válida, actualizar a hash inmediatamente
            if ($passwordValida) {
                $nuevoHash = Security::hashPassword($password);
                $stmtUpdate = $conexion->prepare("UPDATE usuarios SET contrasena = :hash WHERE id = :id");
                $stmtUpdate->execute([':hash' => $nuevoHash, ':id' => $row['id']]);
                
                error_log("PASSWORD MIGRATED TO HASH for user: $username");
            }
        }
        
        if (!$passwordValida) {
            // Contraseña incorrecta
            Security::recordFailedLogin($identifier);
            
            error_log("LOGIN FAILED: Wrong password for user: $username from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            
            $mensajeError = 'Usuario y/o contraseña incorrectos.';
            throw new Exception($mensajeError);
        }
        
        // ─────────────────────────────────────────────────────
        // 8. VERIFICAR ACCESO A SUCURSAL
        // ─────────────────────────────────────────────────────
        $habilitadas = array_map('trim', explode(',', $row['sucursales_habilitadas'] ?? ''));
        
        if (!in_array($sucursal, $habilitadas, true)) {
            Security::recordFailedLogin($identifier);
            
            error_log("LOGIN FAILED: Access denied to branch: $sucursal for user: $username");
            
            $mensajeError = 'No tiene acceso a la sucursal seleccionada.';
            throw new Exception($mensajeError);
        }
        
        //
        // 9. LOGIN EXITOSO
        // 
        
        // Limpiar intentos fallidos
        Security::clearFailedLogins($identifier);
        
        // Regenerar ID de sesión (prevenir session fixation)
        Security::regenerateSession();
        
        // Guardar datos en sesión
        $_SESSION['usuario'] = $username;
        $_SESSION['sucursal'] = $sucursal;
        $_SESSION['sucursales'] = $habilitadas;
        $_SESSION['rol'] = $row['Rol'] ?? '';
        $_SESSION['user_id'] = $row['id'] ?? '';
        $_SESSION['login_time'] = time();
        
        // Log de login exitoso
        error_log("LOGIN SUCCESS: User: $username, Branch: $sucursal, IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        
        // Regenerar token CSRF para la nueva sesión
        Security::regenerateCSRFToken();
        
        // ─────────────────────────────────────────────────────
        // 10. REDIRECCIONAR SEGÚN CONFIGURACIÓN
        // ─────────────────────────────────────────────────────
        
        // Caso especial: usuario Pico
        if (strcasecmp($username, 'Pico') === 0) {
            header("Location: consulta_precios_pico.php");
            exit();
        }
        
        // Redirección según sucursal
        if ($sucursal === 'ADM') {
            header("Location: administracion.php");
            exit();
        } elseif ($sucursal === 'CAJ') {
            header("Location: caja/caja.php");
            exit();
        } else {
            header("Location: registro1.php");
            exit();
        }
        
    } catch (Exception $e) {
        // Capturar cualquier error y mostrar mensaje
        if (empty($mensajeError)) {
            $mensajeError = $e->getMessage();
        }
        
        // En producción, no mostrar detalles técnicos
        if (!APP_DEBUG) {
            $mensajeError = 'Error al procesar el inicio de sesión. Por favor intente nuevamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Sistema de Gestión Mega Moda - Login">
  <title>Mega Moda SRL : Login</title>
  
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.1/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/login.css">
  
  <!-- Prevenir indexación de página de login -->
  <meta name="robots" content="noindex, nofollow">
</head>
<body>
  <div class="login-container">
    <div class="login-card">
      <div class="card-header">
        <h1>REGISTRO MEGA MODA</h1>
        <p>Sistema de Gestión</p>
      </div>
      
      <div class="card-body">
        <?php if (!empty($mensajeError)): ?>
          <div class="alert alert-<?= Security::escape($mensajeTipo) ?>" role="alert">
            <i class="fas fa-exclamation-triangle"></i>
            <?= Security::escape($mensajeError) ?>
          </div>
        <?php endif; ?>
        
        <form action="index.php" method="post" autocomplete="off">
          <!-- Token CSRF -->
          <input type="hidden" name="csrf_token" value="<?= Security::escape($csrfToken) ?>">
          
          <div class="form-group">
            <label class="form-label" for="usuario">
              <i class="fas fa-user"></i>
              Usuario
            </label>
            <div class="input-wrapper">
              <input 
                id="usuario"
                name="usuario" 
                type="text" 
                class="form-control" 
                placeholder="Ingrese su usuario"
                autocomplete="username"
                required
                maxlength="50"
                autofocus
              >
              <span class="input-icon">
                <i class="fas fa-user"></i>
              </span>
            </div>
          </div>
          
          <div class="form-group">
            <label class="form-label" for="contrasena">
              <i class="fas fa-lock"></i>
              Contraseña
            </label>
            <div class="input-wrapper">
              <input 
                id="contrasena"
                name="contrasena" 
                type="password" 
                class="form-control" 
                placeholder="Ingrese su contraseña"
                autocomplete="current-password"
                required
                maxlength="255"
              >
              <span class="input-icon">
                <i class="fas fa-lock"></i>
              </span>
              <button 
                type="button" 
                class="password-toggle"
                onclick="togglePassword()"
                aria-label="Mostrar contraseña"
              >
                <i class="fas fa-eye" id="toggleIcon"></i>
              </button>
            </div>
          </div>
          
          <div class="form-group">
            <label class="form-label" for="sucursal">
              <i class="fas fa-store"></i>
              Sucursal
            </label>
            <div class="input-wrapper">
              <select 
                id="sucursal"
                name="sucursal" 
                class="form-select" 
                required
              >
                <option value="" disabled selected>Seleccione una sucursal</option>
                <?php 
                // Obtener sucursales para el dropdown
                try {
                    $conexion = conectar(DB_HOST, DB_USER, DB_PASS, DB_NAME);
                    $sucursalesDisponibles = obtenerSucursales($conexion);
                    
                    foreach ($sucursalesDisponibles as $s): 
                ?>
                  <option value="<?= Security::escape($s['nombre_abreviado']) ?>">
                    <?= Security::escape($s['nombre_abreviado']) ?>
                  </option>
                <?php 
                    endforeach;
                } catch (Exception $e) {
                    error_log("Error al cargar sucursales: " . $e->getMessage());
                }
                ?>
              </select>
              <span class="input-icon">
                <i class="fas fa-store"></i>
              </span>
            </div>
          </div>
          
          <button class="btn-login" type="submit">
            <i class="fas fa-sign-in-alt"></i>
            Iniciar Sesión
          </button>
        </form>
        
        <?php if (APP_DEBUG): ?>
          <div class="mt-3 text-center">
            <small class="text-muted">
              Versión <?= Security::escape(APP_VERSION) ?> | 
              Ambiente: <?= Security::escape(APP_ENV) ?>
            </small>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  
  <script>
    // Toggle visibilidad de contraseña
    function togglePassword() {
      const passwordInput = document.getElementById('contrasena');
      const toggleIcon = document.getElementById('toggleIcon');
      
      if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
      } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
      }
    }
    
    // Prevenir múltiples envíos de formulario
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
      const submitBtn = form.querySelector('button[type="submit"]');
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verificando...';
    });
    
    // Auto-focus en primer campo vacío
    window.addEventListener('load', function() {
      const usuario = document.getElementById('usuario');
      const contrasena = document.getElementById('contrasena');
      const sucursal = document.getElementById('sucursal');
      
      if (!usuario.value) {
        usuario.focus();
      } else if (!contrasena.value) {
        contrasena.focus();
      } else if (!sucursal.value) {
        sucursal.focus();
      }
    });
  </script>
  
  <!-- Font Awesome para iconos -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</body>
</html>