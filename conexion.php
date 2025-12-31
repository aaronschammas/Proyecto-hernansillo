<?php
/**
 * 
 * CLASE DE CONEXIÓN A BASE DE DATOS - SISTEMA MEGA MODA
 * 
 * 
 * CAMBIOS DE SEGURIDAD:
 * Credenciales desde variables de entorno (no hardcoded)
 *  Manejo robusto de errores
 * Logging seguro
 * Conexión con SSL (opcional)

 */

// Definir constante de acceso
define('APP_ACCESS', true);

// Cargar configuración
require_once __DIR__ . '/config.php';

// Cargar clase de seguridad
require_once __DIR__ . '/Security.php';

class Conexion {
    protected $conexion;
    private static $instance = null;
    
    /**
     * Constructor - Establece conexión PDO segura
     * 
   
     * 
     * AHORA:
     * Lee desde variables de entorno vía config.php
     */
    public function __construct() {
        try {
            // Construir DSN (Data Source Name)
            $dsn = sprintf(
                "mysql:host=%s;dbname=%s;charset=%s",
                DB_HOST,
                DB_NAME,
                DB_CHARSET
            );
            
            // Opciones de PDO para máxima seguridad
            $options = [
                // Usar excepciones para errores (no warnings que exponen info)
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                
                // Devolver resultados como arrays asociativos
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                
                // Deshabilitar prepared statement emulation (previene algunos SQL injection)
                PDO::ATTR_EMULATE_PREPARES => false,
                
                // Usar conexiones persistentes (mejor rendimiento)
                PDO::ATTR_PERSISTENT => false,
                
                // Timeout de conexión
                PDO::ATTR_TIMEOUT => 5,
                
                // Usar SSL para conexión (si está configurado)
                // Descomentar si tu servidor MySQL tiene SSL habilitado
                // PDO::MYSQL_ATTR_SSL_CA => '/path/to/ca-cert.pem',
                // PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
            ];
            
            // Establecer conexión
            $this->conexion = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // Logging en modo desarrollo
            if (APP_DEBUG) {
                error_log('Database connection established successfully');
            }
            
        } catch (PDOException $e) {
            // Log del error (sin exponer detalles sensibles)
            error_log('DATABASE CONNECTION ERROR: ' . $e->getMessage());
            
            // En desarrollo: mostrar error detallado
            if (APP_DEBUG) {
                throw new Exception(
                    "Error de conexión a base de datos: " . $e->getMessage()
                );
            }
            
            // En producción: error genérico
            throw new Exception(
                "Error al conectar con la base de datos. Por favor intente más tarde."
            );
        }
    }
    
    /**
     * Obtener instancia de conexión (Singleton Pattern)
     * 
     * @return Conexion Instancia única de la conexión
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Obtener objeto PDO de conexión
     * 
     * @return PDO Objeto de conexión PDO
     */
    public function getConexion() {
        return $this->conexion;
    }
    
    /**
     * Verificar si la conexión está activa
     * 
     * @return bool True si la conexión está activa
     */
    public function isConnected() {
        try {
            return $this->conexion !== null && 
                   $this->conexion->query('SELECT 1') !== false;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Cerrar conexión explícitamente
     */
    public function close() {
        $this->conexion = null;
    }
    
    /**
     * Prevenir clonación de la instancia (Singleton)
     */
    private function __clone() {}
    
    /**
     * Prevenir deserialización (Singleton)
     */
    public function __wakeup() {
        throw new Exception("No se puede deserializar un singleton");
    }
}

/**
 * Función helper para compatibilidad con código legacy
 * 
 * @param string $servidor Host de la base de datos
 * @param string $usuario Usuario de la base de datos
 * @param string $contrasenia Contraseña de la base de datos
 * @param string $base_datos Nombre de la base de datos
 * @return PDO Conexión PDO
 * @deprecated Usar Conexion::getInstance()->getConexion() en su lugar
 */
function conectar($servidor, $usuario, $contrasenia, $base_datos) {
    // Log de uso de función deprecated
    if (APP_DEBUG) {
        error_log('WARNING: Using deprecated conectar() function. Use Conexion class instead.');
    }
    
    try {
        $dsn = "mysql:host=$servidor;dbname=$base_datos;charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $conexion = new PDO($dsn, $usuario, $contrasenia, $options);
        return $conexion;
        
    } catch (PDOException $e) {
        error_log('DATABASE CONNECTION ERROR (legacy): ' . $e->getMessage());
        
        if (APP_DEBUG) {
            throw new Exception("Error de conexión: " . $e->getMessage());
        }
        
        throw new Exception("Error al conectar con la base de datos");
    }
}