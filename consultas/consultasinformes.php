<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

include("conexion.php");

if (!isset($_POST['fecha'])) {
    echo json_encode(["error" => "Fecha no proporcionada"]);
    exit();
}

$fecha = $_POST['fecha'];

$servidor = "localhost";
$usuario = "u467512787_moda";
$contrasenia = "Hernan2215";
$base_datos = "u467512787_mega";

try {
    $conexion = new PDO("mysql:host=$servidor;dbname=$base_datos;charset=utf8", $usuario, $contrasenia);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(["error" => "Error de conexión: " . $e->getMessage()]);
    exit();
}

// Obtener totales
$sqlTotales = "SELECT sucursal, turno,
                      SUM(Total_efectivo) AS Total_efectivo,
                      SUM(Total_tarjetas_credito) AS Total_tarjeta_credito,
                      SUM(Total_tarjetas_debito) AS Total_tarjeta_debito,
                      SUM(Total_transferencias) AS Total_transferencias,
                      SUM(Total_transferenciasQR) AS Total_transferenciasQR,
                      SUM(Total_financieras) AS Total_financieras
               FROM totales_ventas
               WHERE Fecha = :fecha
               GROUP BY sucursal, turno";

$stmtTotales = $conexion->prepare($sqlTotales);
$stmtTotales->execute(['fecha' => $fecha]);
$ventas = $stmtTotales->fetchAll(PDO::FETCH_ASSOC);

// Obtener chicas
$sqlChicas = "SELECT Sucursal, Turno, GROUP_CONCAT(Nombre ORDER BY Nombre SEPARATOR ' + ') AS Chicas
              FROM detalle_chicas
              WHERE Fecha = :fecha
              GROUP BY Sucursal, Turno";

$stmtChicas = $conexion->prepare($sqlChicas);
$stmtChicas->execute(['fecha' => $fecha]);
$chicas = $stmtChicas->fetchAll(PDO::FETCH_ASSOC);

// Convertir chicas a array asociativo para fácil acceso
$mapaChicas = [];
foreach ($chicas as $fila) {
    $clave = $fila['Sucursal'] . '-' . $fila['Turno'];
    $mapaChicas[$clave] = $fila['Chicas'];
}

// Agregar los nombres al array principal
foreach ($ventas as &$venta) {
    $clave = $venta['sucursal'] . '-' . $venta['turno'];
    $venta['Chicas'] = isset($mapaChicas[$clave]) ? $mapaChicas[$clave] : '';
}

echo json_encode($ventas);

