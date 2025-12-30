<?php
// caja_abrir.php
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

$montoInicial = isset($_POST['montoInicial']) ? floatval($_POST['montoInicial']) : 0;

if ($montoInicial <= 0) {
    echo json_encode(['ok' => false, 'mensaje' => 'Monto inicial inválido.']);
    exit;
}

require_once '../conexion.php';

try {
    $db  = new Conexion();
    $pdo = $db->getConexion();

    $fechaHoy = date('Y-m-d');
    $fechaId  = date('dmY');                 // ddmmaaaa para el IdCierreCaja

    // Determinar turno por hora de servidor: <15 = M, >=15 = T
    $hora  = intval(date('H'));              // 0..23
    $turno = ($hora >= 15) ? 'T' : 'M';

    $baseId = $fechaId . $turno;            // ej: 01122025M

    // Buscar el último IdCierreCaja para ese día+turno
    $stmt = $pdo->prepare("
        SELECT IdCierreCaja
        FROM caja_cierres
        WHERE IdCierreCaja LIKE :base
        ORDER BY IdCierreCaja DESC
        LIMIT 1
    ");
    $stmt->execute([':base' => $baseId . '%']);
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($fila) {
        $ultimoSufijo = substr($fila['IdCierreCaja'], strlen($baseId)); // ej '01'
        $siguienteNum = intval($ultimoSufijo) + 1;
    } else {
        $siguienteNum = 1;
    }

    // Sufijo de 2 dígitos: 01, 02, 03...
    $sufijo   = str_pad($siguienteNum, 2, '0', STR_PAD_LEFT);
    $idCierre = $baseId . $sufijo;

    $usuario         = $_SESSION['usuario'];
    $saldoInicialInt = (int) round($montoInicial);

    // OJO: aquí ya NO usamos Saldo_final
    $stmtIns = $pdo->prepare("
        INSERT INTO caja_cierres
        (IdCierreCaja, Usuario, Fecha, Turno,
         Saldo_inicial, Total_ingresos, Total_egresos,
         Total_efectivo_rendido, Diferencia)
        VALUES
        (:id, :usuario, :fecha, :turno,
         :saldo_inicial, 0, 0, 0, 0)
    ");
    $stmtIns->execute([
        ':id'            => $idCierre,
        ':usuario'       => $usuario,
        ':fecha'         => $fechaHoy,
        ':turno'         => $turno,
        ':saldo_inicial' => $saldoInicialInt
    ]);

    echo json_encode([
        'ok'           => true,
        'IdCierreCaja' => $idCierre
    ]);

} catch (Exception $e) {
    echo json_encode([
        'ok'      => false,
        'mensaje' => 'Error en la base de datos: ' . $e->getMessage()
    ]);
}

