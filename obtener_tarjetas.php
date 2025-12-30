<?php
// obtener_tarjetas.php
header('Content-Type: application/json; charset=utf-8');
require_once 'conexion.php';

$id_cierre  = $_POST['id_cierre']  ?? '';
$forma_pago = $_POST['forma_pago'] ?? '';

// Extraer código de sucursal (ajusta offsets si tu ID tiene otra estructura)
$codigo = substr($id_cierre, 6, 3);

// Función para quitar tildes
function sinTildes($s) {
    $a = ['á','Á','é','É','í','Í','ó','Ó','ú','Ú','ñ','Ñ'];
    $b = ['a','A','e','E','i','I','o','O','u','U','n','N'];
    return str_replace($a, $b, $s);
}

try {
    $db  = new Conexion();
    $pdo = $db->getConexion();

    // Normalizar texto de forma de pago: minúsculas y sin tildes
    $fp = mb_strtolower(trim($forma_pago), 'UTF-8');
    $fp = sinTildes($fp);

    // Determinar consulta según forma de pago
    if (str_contains($fp, 'debito')) {
        $sql = "SELECT nombre_tarjeta       FROM tarjetas_debito   WHERE sucursales LIKE CONCAT('%', ?, '%')";
    } elseif (str_contains($fp, 'credito')) {
        $sql = "SELECT nombre_plan          FROM tarjetas_credito   WHERE sucursales LIKE CONCAT('%', ?, '%')";
    } elseif (str_contains($fp, 'financiera')) {
        $sql = "SELECT nombre_plan          FROM financieras         WHERE sucursales LIKE CONCAT('%', ?, '%')";
    } elseif (str_contains($fp, 'transferencia')) {
        $sql = "SELECT nombre_transferencia FROM transferencias      WHERE sucursales LIKE CONCAT('%', ?, '%')";
    } else {
        // Forma de pago no reconocida
        echo json_encode([
            'tarjetas'     => [],
            'debug_id'     => $id_cierre,
            'debug_fp'     => $forma_pago,
            'debug_codigo' => $codigo,
            'debug_count'  => 0
        ]);
        exit;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$codigo]);
    $filas = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'tarjetas'     => $filas,
        'debug_id'     => $id_cierre,
        'debug_fp'     => $forma_pago,
        'debug_codigo' => $codigo,
        'debug_count'  => count($filas)
    ]);
} catch (Exception $e) {
    echo json_encode([
        'error'        => $e->getMessage(),
        'debug_id'     => $id_cierre,
        'debug_fp'     => $forma_pago,
        'debug_codigo' => $codigo,
        'debug_count'  => 0
    ]);
}

