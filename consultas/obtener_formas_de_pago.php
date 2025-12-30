<?php
//session_start();

//include("conexion.php");

try {
    // Consulta para seleccionar solo las tarjetas activas
    $query = "SELECT nombre_forma_de_pago FROM formas_de_pago";

    // Crear una instancia de la clase Conexion
    $conexion = new Conexion();

    // Obtener la conexión directamente desde la propiedad (ahora como protegida)
    $conn = $conexion->getConexion();

    // Preparar la consulta
    $stmt = $conn->prepare($query);
    $stmt->execute();

    // Obtener resultados
    $formas_de_pago = $stmt->fetchAll();
        
} catch (Exception $e) {
    echo "Error en la conexión: " . $e->getMessage();
}
?>