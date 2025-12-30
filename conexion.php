<?php
class Conexion {
    protected $servidor = "localhost";
    protected $usuario = "u467512787_moda";
    protected $contrasenia = "Hernan2215";
    protected $conexion;

    public function __construct() {
        try {
            $this->conexion = new PDO("mysql:host=$this->servidor;dbname=u467512787_mega", $this->usuario, $this->contrasenia);
            $this->conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw new Exception("Falla de conexión: " . $e->getMessage());
        }
    }

    public function getConexion() {
        return $this->conexion;
    }
}


function conectar($servidor, $usuario, $contrasenia, $base_datos) {
    try {
        $conexion = new PDO("mysql:host=$servidor;dbname=$base_datos", $usuario, $contrasenia);
        $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conexion;
    } catch (PDOException $e) {
        throw new Exception("Falla de conexión: " . $e->getMessage());
    }
}
?>