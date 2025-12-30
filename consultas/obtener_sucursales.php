<?php
//session_start();

//include("conexion.php");

try {
   
    $query = "SELECT nombre_sucursal FROM sucursales";

    // Crear una instancia de la clase Conexion
    $conexion = new Conexion();

    // Obtener la conexión directamente desde la propiedad (ahora como protegida)
    $conn = $conexion->getConexion();

    // Preparar la consulta
    $stmt = $conn->prepare($query);
    $stmt->execute();

    // Obtener resultados
    $nombres_sucursales = $stmt->fetchAll();
        
} catch (Exception $e) {
    echo "Error en la conexión: " . $e->getMessage();
}
?>