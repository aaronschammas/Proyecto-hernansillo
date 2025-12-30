<?php
// caja_cerrar.php
date_default_timezone_set('America/Argentina/Buenos_Aires');
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['ok' => false, 'mensaje' => 'Sesión no válida. Vuelva a iniciar sesión.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
    exit;
}

// El frontend envía JSON en el cuerpo
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    echo json_encode(['ok' => false, 'mensaje' => 'Datos JSON inválidos.']);
    exit;
}

// ===== Datos principales =====
$idCierreCaja       = $data['idCierreCaja']       ?? null;
$usuario            = $data['usuario']            ?? ($_SESSION['usuario'] ?? '');
$saldoInicial       = (int) round($data['saldoInicial']       ?? 0);
$totalIngresos      = (int) round($data['totalIngresos']      ?? 0);
$totalEgresos       = (int) round($data['totalEgresos']       ?? 0);
$saldoSegunRegistro = (int) round($data['saldoSegunRegistro'] ?? 0); // sólo informativo
$efectivoRendido    = (int) round($data['efectivoRendido']    ?? 0);
$diferencia         = (int) round($data['diferencia']         ?? 0);
$comentario         = trim($data['comentario']    ?? '');

$ingresos = $data['ingresos'] ?? [];  // cada item: origen, detalle, monto
$egresos  = $data['egresos']  ?? [];  // cada item: destino, detalle, monto

if (!$idCierreCaja) {
    echo json_encode(['ok' => false, 'mensaje' => 'Falta IdCierreCaja.']);
    exit;
}

require_once '../conexion.php';

try {
    $db  = new Conexion();
    $pdo = $db->getConexion();

    // Trabajamos dentro de una transacción
    $pdo->beginTransaction();

    // 1) Actualizar tabla caja_cierres
    //    (IMPORTANTE: acá NO usamos Saldo_final porque esa columna no existe)
    $stmtUpdate = $pdo->prepare("
        UPDATE caja_cierres
        SET Total_ingresos         = :total_ingresos,
            Total_egresos          = :total_egresos,
            Total_efectivo_rendido = :total_rendido,
            Diferencia             = :diferencia,
            Comentario             = :comentario
        WHERE IdCierreCaja = :id
    ");

    $stmtUpdate->execute([
        ':total_ingresos' => $totalIngresos,
        ':total_egresos'  => $totalEgresos,
        ':total_rendido'  => $efectivoRendido,
        ':diferencia'     => $diferencia,
        ':comentario'     => $comentario,
        ':id'             => $idCierreCaja
    ]);

    // 2) Insertar detalle de INGRESOS en caja_ingresos
    //    IdIngreso = IdCierreCaja + correlativo 1,2,3...
    if (!empty($ingresos)) {
        $stmtIng = $pdo->prepare("
            INSERT INTO caja_ingresos
            (IdCierreCaja, IdIngreso, Origen, Detalle, Monto)
            VALUES
            (:idcierre, :idingreso, :origen, :detalle, :monto)
        ");

        $contador = 1;
        foreach ($ingresos as $ing) {
            $origen  = $ing['origen']  ?? '';
            $detalle = $ing['detalle'] ?? '';
            $monto   = (int) round($ing['monto'] ?? 0);

            // saltar filas totalmente vacías
            if ($monto <= 0 && $origen === '' && $detalle === '') {
                continue;
            }

            $idIngreso = $idCierreCaja . $contador;

            $stmtIng->execute([
                ':idcierre'  => $idCierreCaja,
                ':idingreso' => $idIngreso,
                ':origen'    => $origen,
                ':detalle'   => $detalle,
                ':monto'     => $monto
            ]);

            $contador++;
        }
    }

    // 3) Insertar detalle de EGRESOS en caja_egresos
    //    IdEgreso = IdCierreCaja + correlativo 1,2,3...
    if (!empty($egresos)) {
        $stmtEgr = $pdo->prepare("
            INSERT INTO caja_egresos
            (IdCierreCaja, IdEgreso, Destino, Detalle, Monto)
            VALUES
            (:idcierre, :idegreso, :destino, :detalle, :monto)
        ");

        $contador = 1;
        foreach ($egresos as $egr) {
            $destino = $egr['destino'] ?? '';
            $detalle = $egr['detalle'] ?? '';
            $monto   = (int) round($egr['monto'] ?? 0);

            if ($monto <= 0 && $destino === '' && $detalle === '') {
                continue;
            }

            $idEgreso = $idCierreCaja . $contador;

            $stmtEgr->execute([
                ':idcierre' => $idCierreCaja,
                ':idegreso' => $idEgreso,
                ':destino'  => $destino,
                ':detalle'  => $detalle,
                ':monto'    => $monto
            ]);

            $contador++;
        }
    }

    // 4) Confirmar transacción
    $pdo->commit();

    echo json_encode(['ok' => true]);

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'ok'      => false,
        'mensaje' => 'Error en el cierre de caja: ' . $e->getMessage()
    ]);
}



