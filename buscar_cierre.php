<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log_buscar_cierre.txt');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=UTF-8');

try {
    require_once 'conexion.php';
    $db  = new Conexion();
    $pdo = $db->getConexion();

    // ====== Leer ID de cierre (POST/GET/JSON) y admitir id_cierre o id
    $id_cierre = '';

    // 1) POST (FormData o x-www-form-urlencoded)
    if (isset($_POST['id_cierre'])) $id_cierre = $_POST['id_cierre'];
    elseif (isset($_POST['id']))    $id_cierre = $_POST['id'];

    // 2) GET
    if ($id_cierre === '' && isset($_GET['id_cierre'])) $id_cierre = $_GET['id_cierre'];
    elseif ($id_cierre === '' && isset($_GET['id']))    $id_cierre = $_GET['id'];

    // 3) JSON crudo
    if ($id_cierre === '') {
        $raw = file_get_contents('php://input');
        if ($raw) {
            $j = json_decode($raw, true);
            if (is_array($j)) {
                $id_cierre = $j['id_cierre'] ?? ($j['id'] ?? '');
            }
        }
    }

    if ($id_cierre === '') {
        echo json_encode(['error' => 'Falta id']);
        exit;
    }

    // ====== totales_ventas (incluye Comentario del cajero)
    $stCierre = $pdo->prepare("
        SELECT Id_cierre, Sucursal, Fecha, Turno, Usuario AS Cajero,
               Total_efectivo, Total_tarjetas_credito, Total_tarjetas_debito,
               Total_transferencias, Total_transferenciasQR, Total_financieras,
               Comentario
          FROM totales_ventas
         WHERE Id_cierre = :id
         LIMIT 1
    ");
    $stCierre->execute([':id' => $id_cierre]);
    $cierre = $stCierre->fetch(PDO::FETCH_ASSOC);
    if (!$cierre) {
        echo json_encode(['error' => 'No existe el cierre solicitado']);
        exit;
    }

    // ====== detalle_tarjetas
    $stTar = $pdo->prepare("
        SELECT Id_tarjeta, Forma_de_pago, Tarjeta, Monto, Lote, Cupon
          FROM detalle_tarjetas
         WHERE Id_cierre = :id
         ORDER BY Id_tarjeta ASC
    ");
    $stTar->execute([':id' => $id_cierre]);
    $tarjetas = $stTar->fetchAll(PDO::FETCH_ASSOC);

    // ====== detalle_gastos
    $stGas = $pdo->prepare("
        SELECT Id_gasto, Concepto, Monto
          FROM detalle_gastos
         WHERE Id_cierre = :id
         ORDER BY Id_gasto ASC
    ");
    $stGas->execute([':id' => $id_cierre]);
    $gastos = $stGas->fetchAll(PDO::FETCH_ASSOC);

    // ====== detalle_chicas
    $stCh = $pdo->prepare("
        SELECT Id_chica, Nombre
          FROM detalle_chicas
         WHERE Id_cierre = :id
         ORDER BY Id_chica ASC
    ");
    $stCh->execute([':id' => $id_cierre]);
    $chicas = $stCh->fetchAll(PDO::FETCH_ASSOC);

    // ====== detalle_efectivo_rendido (una fila)
    $stEf = $pdo->prepare("
        SELECT Denominacion_billete1, Cantidad_billete1,
               Denominacion_billete2, Cantidad_billete2,
               Denominacion_billete3, Cantidad_billete3,
               Denominacion_billete4, Cantidad_billete4,
               Denominacion_billete5, Cantidad_billete5,
               Denominacion_billete6, Cantidad_billete6,
               Denominacion_billete7, Cantidad_billete7,
               Efectivo_rendido, Diferencia_caja
          FROM detalle_efectivo_rendido
         WHERE Id_cierre = :id
         LIMIT 1
    ");
    $stEf->execute([':id' => $id_cierre]);
    $ef = $stEf->fetch(PDO::FETCH_ASSOC);

    // Mapeo a arreglo de billetes
    $billetes = [];
    if ($ef) {
        for ($i = 1; $i <= 7; $i++) {
            $den = $ef["Denominacion_billete{$i}"] ?? null;
            $can = $ef["Cantidad_billete{$i}"] ?? null;
            if (!is_null($den) || !is_null($can)) {
                $billetes[] = [
                    'Denominacion' => (int)$den,
                    'Cantidad'     => (int)$can,
                ];
            }
        }
    }
    $efectivo_rendido = $ef['Efectivo_rendido'] ?? 0;
    $diferencia_caja  = $ef['Diferencia_caja']  ?? 0;

    // ====== Comentario del control (administración) desde control_cierres_resumen
    $stCtrl = $pdo->prepare("
        SELECT Comentario_Control
          FROM control_cierres_resumen
         WHERE Id_cierre = :id
         LIMIT 1
    ");
    $stCtrl->execute([':id' => $id_cierre]);
    $ctrl = $stCtrl->fetch(PDO::FETCH_ASSOC);
    $comentario_control = $ctrl['Comentario_Control'] ?? '';

    // ====== Respuesta
    echo json_encode([
        'cierre'             => $cierre,           // incluye Comentario (cajero)
        'tarjetas'           => $tarjetas,
        'gastos'             => $gastos,
        'chicas'             => $chicas,
        'billetes'           => $billetes,
        'efectivo_rendido'   => $efectivo_rendido,
        'diferencia_caja'    => $diferencia_caja,
        'comentario_control' => $comentario_control
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Fallo en buscar_cierre', 'detalle' => $e->getMessage()]);
}



