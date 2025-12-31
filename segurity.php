<?php
/*
 * CLASE DE SEGURIDAD - SISTEMA MEGA MODA
 * 
 * Biblioteca centralizada de funciones de seguridad.
 * 
 * FUNCIONALIDADES:
 * - Protección CSRF
 * - Sanitización de inputs/outputs
 * - Manejo seguro de contraseñas
 * - Gestión de sesiones seguras
 * - Headers de seguridad
 * - Rate limiting para login
 * 
 * @version 1.0.0
 */

class Security {
    
    
    // PROTECCIÓN CSRF (Cross-Site Request Forgery)

    
    /**
     * Genera un token CSRF y lo almacena en la sesión
     * 
     * @return string Token CSRF de 64 caracteres hexadecimales
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Valida un token CSRF contra el almacenado en sesión
     * 
     * @param string $token Token a validar
     * @return bool True si el token es válido
     */
    public static function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        
        // Comparación timing-safe para prevenir timing attacks
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Regenera el token CSRF (útil después de acciones importantes)
     */
    public static function regenerateCSRFToken() {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
        return $_SESSION['csrf_token'];
    }
    
    // SANITIZACIÓN Y ESCAPE DE DATOS
    
    /**
     * Escapa datos para prevenir XSS (Cross-Site Scripting)
     * 
     * @param mixed $data Dato a escapar (string o array)
     * @return mixed Dato escapado
     */
    public static function escape($data) {
        if (is_array($data)) {
            return array_map([self::class, 'escape'], $data);
        }
        
        return htmlspecialchars(
            (string)$data,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
    }
    
    /**
     * Sanitiza input del usuario según tipo
     * 
     * @param mixed $data Dato a sanitizar
     * @param string $type Tipo: 'int', 'float', 'email', 'url', 'string'
     * @return mixed Dato sanitizado o null si no es válido
     */
    public static function sanitizeInput($data, $type = 'string') {
        $data = trim((string)$data);
        
        switch ($type) {
            case 'int':
                if (filter_var($data, FILTER_VALIDATE_INT) !== false) {
                    return (int)$data;
                }
                return null;
                
            case 'float':
                if (filter_var($data, FILTER_VALIDATE_FLOAT) !== false) {
                    return (float)$data;
                }
                return null;
                
            case 'email':
                $email = filter_var($data, FILTER_VALIDATE_EMAIL);
                return $email !== false ? strtolower($email) : null;
                
            case 'url':
                $url = filter_var($data, FILTER_VALIDATE_URL);
                return $url !== false ? $url : null;
                
            case 'alphanumeric':
                // Solo letras y números
                return preg_replace('/[^a-zA-Z0-9]/', '', $data);
                
            case 'string':
            default:
                // Sanitizar string general
                return htmlspecialchars(
                    strip_tags($data),
                    ENT_QUOTES | ENT_HTML5,
                    'UTF-8'
                );
        }
    }
    
    /**
     * Sanitiza y valida fecha en formato ISO (YYYY-MM-DD)
     * 
     * @param string $date Fecha a validar
     * @return string|null Fecha válida o null
     */
    public static function sanitizeDate($date) {
        $date = trim($date);
        
        // Validar formato YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $parts = explode('-', $date);
            if (checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0])) {
                return $date;
            }
        }
        
        return null;
    }
    
    // MANEJO DE CONTRASEÑAS
    
    /**
     * Hashea una contraseña de forma segura usando Argon2id
     * 
     * @param string $password Contraseña en texto plano
     * @return string Hash de la contraseña
     */
    public static function hashPassword($password) {
        // Usar Argon2id si está disponible (PHP 7.3+)
        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($password, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536,  // 64 MB
                'time_cost' => 4,        // 4 iteraciones
                'threads' => 3           // 3 threads paralelos
            ]);
        }
        
        // Fallback a Argon2i (PHP 7.2+)
        if (defined('PASSWORD_ARGON2I')) {
            return password_hash($password, PASSWORD_ARGON2I);
        }
        
        // Fallback a bcrypt (PHP 5.5+)
        return password_hash($password, PASSWORD_BCRYPT, [
            'cost' => 12  // Factor de costo
        ]);
    }
    
    /**
     * Verifica una contraseña contra su hash
     * 
     * @param string $password Contraseña en texto plano
     * @param string $hash Hash almacenado
     * @return bool True si la contraseña es correcta
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Verifica si un hash necesita ser rehashed (algoritmo desactualizado)
     * 
     * @param string $hash Hash a verificar
     * @return bool True si necesita rehash
     */
    public static function needsRehash($hash) {
        if (defined('PASSWORD_ARGON2ID')) {
            return password_needs_rehash($hash, PASSWORD_ARGON2ID);
        }
        if (defined('PASSWORD_ARGON2I')) {
            return password_needs_rehash($hash, PASSWORD_ARGON2I);
        }
        return password_needs_rehash($hash, PASSWORD_BCRYPT);
    }
    
    /**
     * Valida fortaleza de contraseña
     * 
     * @param string $password Contraseña a validar
     * @return array ['valid' => bool, 'errors' => array]
     */
    public static function validatePasswordStrength($password) {
        $errors = [];
        
        // Longitud mínima
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            $errors[] = "La contraseña debe tener al menos " . PASSWORD_MIN_LENGTH . " caracteres";
        }
        
        // Al menos una letra
        if (!preg_match('/[a-zA-Z]/', $password)) {
            $errors[] = "Debe contener al menos una letra";
        }
        
        // Al menos un número
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Debe contener al menos un número";
        }
        
        // Contraseñas comunes (lista básica)
        $commonPasswords = [
            'password', '123456', '12345678', 'qwerty', 'abc123',
            'password123', 'admin', 'letmein', 'welcome', '123123'
        ];
        
        if (in_array(strtolower($password), $commonPasswords)) {
            $errors[] = "La contraseña es demasiado común";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
  
    // GESTIÓN DE SESIONES SEGURAS

    
    /**
     * Inicia una sesión segura con configuración hardened
     */
    public static function startSecureSession() {
        // Si ya hay sesión activa, no hacer nada
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        
        // Configuración segura de sesión
        ini_set('session.cookie_httponly', '1');  // No accesible desde JavaScript
        ini_set('session.cookie_secure', '1');     // Solo sobre HTTPS
        ini_set('session.cookie_samesite', 'Strict'); // Protección CSRF
        ini_set('session.use_only_cookies', '1');  // No permitir ID en URL
        ini_set('session.use_strict_mode', '1');   // Rechazar IDs no inicializados
        
        // Configurar nombre de sesión
        session_name(SESSION_NAME);
        
        // Configurar tiempo de vida
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path' => '/',
            'domain' => '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        
        // Iniciar sesión
        session_start();
        
        // Regenerar ID de sesión si es nueva
        if (!isset($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
            $_SESSION['created_at'] = time();
        }
        
        // Verificar timeout de sesión
        if (isset($_SESSION['last_activity'])) {
            $elapsed = time() - $_SESSION['last_activity'];
            if ($elapsed > SESSION_LIFETIME) {
                self::destroySession();
                return;
            }
        }
        
        $_SESSION['last_activity'] = time();
        
        // Verificar IP y User Agent (protección adicional contra session hijacking)
        $fingerprint = self::getSessionFingerprint();
        if (isset($_SESSION['fingerprint'])) {
            if ($_SESSION['fingerprint'] !== $fingerprint) {
                // Posible session hijacking
                error_log('WARNING: Session fingerprint mismatch. Possible hijacking attempt.');
                self::destroySession();
                return;
            }
        } else {
            $_SESSION['fingerprint'] = $fingerprint;
        }
    }
    
    /**
     * Genera un fingerprint de la sesión basado en IP y User Agent
     * 
     * @return string Hash del fingerprint
     */
    private static function getSessionFingerprint() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        return hash('sha256', $ip . $userAgent . SESSION_SECRET);
    }
    
    /**
     * Regenera el ID de sesión (útil después del login)
     */
    public static function regenerateSession() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            $_SESSION['regenerated_at'] = time();
        }
    }
    
    /**
     * Destruye la sesión completamente
     */
    public static function destroySession() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            
            // Eliminar cookie de sesión
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }
            
            session_destroy();
        }
    }
    
    // ═══════════════════════════════════════════════════════
    // RATE LIMITING (Protección contra Fuerza Bruta)
    // ═══════════════════════════════════════════════════════
    
    /**
     * Verifica si un usuario/IP está bloqueado por intentos fallidos
     * 
     * @param string $identifier Identificador único (username, email, IP)
     * @return bool True si está bloqueado
     */
    public static function isRateLimited($identifier) {
        $key = 'login_attempts_' . hash('sha256', $identifier);
        
        if (!isset($_SESSION[$key])) {
            return false;
        }
        
        $data = $_SESSION[$key];
        $attempts = $data['attempts'] ?? 0;
        $lockedUntil = $data['locked_until'] ?? 0;
        
        // Verificar si sigue bloqueado
        if ($attempts >= MAX_LOGIN_ATTEMPTS && time() < $lockedUntil) {
            return true;
        }
        
        // Si el período de bloqueo expiró, resetear
        if ($attempts >= MAX_LOGIN_ATTEMPTS && time() >= $lockedUntil) {
            unset($_SESSION[$key]);
            return false;
        }
        
        return false;
    }
    
    /**
     * Registra un intento de login fallido
     * 
     * @param string $identifier Identificador único
     */
    public static function recordFailedLogin($identifier) {
        $key = 'login_attempts_' . hash('sha256', $identifier);
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'attempts' => 0,
                'first_attempt' => time(),
                'locked_until' => 0
            ];
        }
        
        $_SESSION[$key]['attempts']++;
        
        // Si alcanzó el máximo, bloquear
        if ($_SESSION[$key]['attempts'] >= MAX_LOGIN_ATTEMPTS) {
            $_SESSION[$key]['locked_until'] = time() + LOGIN_LOCKOUT_TIME;
            
            error_log('WARNING: Rate limit triggered for: ' . $identifier);
        }
    }
    
    /**
     * Limpia los intentos de login (después de login exitoso)
     * 
     * @param string $identifier Identificador único
     */
    public static function clearFailedLogins($identifier) {
        $key = 'login_attempts_' . hash('sha256', $identifier);
        unset($_SESSION[$key]);
    }
    
    /**
     * Obtiene el tiempo restante de bloqueo
     * 
     * @param string $identifier Identificador único
     * @return int Segundos restantes, 0 si no está bloqueado
     */
    public static function getRateLimitRemaining($identifier) {
        $key = 'login_attempts_' . hash('sha256', $identifier);
        
        if (!isset($_SESSION[$key])) {
            return 0;
        }
        
        $lockedUntil = $_SESSION[$key]['locked_until'] ?? 0;
        $remaining = $lockedUntil - time();
        
        return $remaining > 0 ? $remaining : 0;
    }
    
    // ═══════════════════════════════════════════════════════
    // HEADERS DE SEGURIDAD
    // ═══════════════════════════════════════════════════════
    
    /**
     * Envía headers de seguridad HTTP
     */
    public static function setSecurityHeaders() {
        // Prevenir clickjacking
        header('X-Frame-Options: DENY');
        
        // Prevenir MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Habilitar XSS protection del navegador
        header('X-XSS-Protection: 1; mode=block');
        
        // Content Security Policy (CSP)
        // Ajustar según necesidades específicas de la aplicación
        header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com 'unsafe-inline'; style-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; img-src 'self' data:; font-src 'self' https://cdn.jsdelivr.net; connect-src 'self'; frame-ancestors 'none';");
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Permissions Policy (Feature Policy)
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        
        // HSTS (HTTP Strict Transport Security) - Solo en producción con HTTPS
        if (!APP_DEBUG && isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
    }
    
    // ═══════════════════════════════════════════════════════
    // UTILIDADES ADICIONALES
    // ═══════════════════════════════════════════════════════
    
    /**
     * Genera un token aleatorio seguro
     * 
     * @param int $length Longitud en bytes
     * @return string Token hexadecimal
     */
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Valida que una petición viene de un origen autorizado
     * 
     * @return bool True si el origen es válido
     */
    public static function validateOrigin() {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        
        // Permitir misma origen
        if (empty($origin)) {
            return true;
        }
        
        // Comparar con APP_URL
        $appUrlParts = parse_url(APP_URL);
        $originParts = parse_url($origin);
        
        return ($originParts['host'] ?? '') === ($appUrlParts['host'] ?? '');
    }
}