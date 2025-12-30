<?php
include('conexion.php');

$mes = $_POST['mes']; // formato YYYY-MM

// Armar fecha inicio y fin
$fechaInicio = $mes . "-01";
$fechaFin = date("Y-m-t", strtotime($fechaInicio)); // último día del mes

$sql = "SELECT Sucursal,
               SUM(total_efectivo) AS total_efectivo,
               SUM(total_tarjeta_credito) AS total_tarjeta_credito,
               SUM(total_tarjeta_debito) AS total_tarjeta_debito,
               SUM(total_financiera) AS total_financiera,
               SUM(total_transferencia) AS total_transferencia,
               SUM(total_transferenciaQR) AS total_transferenciaQR
        FROM totales_ventas
        WHERE Fecha BETWEEN ? AND ?
        GROUP BY Sucursal
        ORDER BY Sucursal";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $fechaInicio, $fechaFin);
$stmt->execute();
$resultado = $stmt->get_result();

$datos = array();
while ($fila = $resultado->fetch_assoc()) {
    $datos[] = $fila;
}

echo json_encode($datos);
?>
