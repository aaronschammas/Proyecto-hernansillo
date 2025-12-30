<?php
// obtener_formaspago.php
header('Content-Type: application/json; charset=utf-8');
require_once 'conexion.php';

$id_cierre = $_POST['id_cierre'] ?? '';

if (!$id_cierre || strlen($id_cierre) < 9) {
    echo json_encode(['error' => 'ID de cierre inválido']);
    exit;
}

// Código de sucursal (posiciones 7,8,9 – base 1)
$codigo = substr($id_cierre, 6, 3); // ej: 250629MADT01 -> MAD

try {
    $conexion = new Conexion();
    $pdo = $conexion->getConexion();

    // Selecciona formas de pago cuyo campo sucursales está vacío (global)
    // o contiene el código abreviado de la sucursal.
    $sql = "
        SELECT nombre_forma_de_pago
        FROM formas_de_pago
        WHERE 
            sucursales = ''
            OR sucursales LIKE :inicio
            OR sucursales LIKE :medio
            OR sucursales LIKE :fin
        ORDER BY Id
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':inicio' => $codigo . ',%',      // MAD,BAI,...
        ':medio'  => '%,' . $codigo . ',%',// ...,MAD,...
        ':fin'    => '%,' . $codigo       // ...,MAD
    ]);

    $formas = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode(['formas' => $formas, 'codigo' => $codigo]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
