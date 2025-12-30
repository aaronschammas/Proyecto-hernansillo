<?php

class Conexion {
    protected $servidor = "localhost:33061";
    protected $usuario = "root";
    protected $contrasenia = "";
    protected $conexion;

    public function __construct() {
        try {
            $this->conexion = new PDO("mysql:host=$this->servidor;dbname=registro_mega", $this->usuario, $this->contrasenia);
            $this->conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw new Exception("Falla de conexión: " . $e->getMessage());
        }
    }

    public function ejecutar($sql) {
        $this->conexion->exec($sql);
        return $this->conexion->lastInsertId();
    }

    public function consultar($sql) {
        $sentencia = $this->conexion->prepare($sql);
        $sentencia->execute();
        return $sentencia->fetchAll();
    }

    // Nuevo método para obtener la conexión
    public function getConexion() {
        return $this->conexion;
    }
}

try {
    // Consulta para seleccionar solo las tarjetas activas
    $query = "SELECT nombre_formaDePAgo FROM formas_de_pago WHERE nombre_formaDePago != 'Efectivo'";

    // Crear una instancia de la clase Conexion
    $conexion = new Conexion();

    // Obtener la conexión directamente desde la propiedad (ahora como protegida)
    $conn = $conexion->getConexion();

    // Preparar la consulta
    $stmt = $conn->prepare($query);
    $stmt->execute();

    // Obtener resultados
    $result = $stmt->fetchAll();
        
} catch (Exception $e) {
    echo "Error en la conexión: " . $e->getMessage();
}
?>

