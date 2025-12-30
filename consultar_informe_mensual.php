<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include('conexion.php');

try {
    $db = new Conexion();
    $conn = $db->getConexion();
} catch (Exception $e) {
    die(json_encode(["error" => "Error de conexión a la base de datos: " . $e->getMessage()]));
}

$mes = $_POST['mes'] ?? $_GET['mes'] ?? null;

if (!$mes) {
    die(json_encode(["error" => "Mes no proporcionado."]));
}

$fechaInicio = $mes . "-01";
$fechaFin    = date("Y-m-t", strtotime($fechaInicio)); // último día del mes

try {
    // Leemos de control_cierres_resumen y devolvemos los ALIAS que espera el front
    $sql = "SELECT 
                Sucursal,
                SUM(total_efectivo)      AS total_efectivo,
                SUM(total_credito)        AS total_tarjeta_credito,
                SUM(total_debito)         AS total_tarjeta_debito,
                SUM(total_financieras)    AS total_financiera,
                SUM(total_transferencias) AS total_transferencia,
                SUM(total_qr)             AS total_transferenciaQR
            FROM control_cierres_resumen
            WHERE Fecha BETWEEN :fechaInicio AND :fechaFin
            GROUP BY Sucursal
            ORDER BY Sucursal";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':fechaInicio', $fechaInicio);
    $stmt->bindParam(':fechaFin', $fechaFin);
    $stmt->execute();

    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($datos);

} catch (PDOException $e) {
    echo json_encode(["error" => "Error en la consulta: " . $e->getMessage()]);
}
