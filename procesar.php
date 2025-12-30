<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.txt');
error_reporting(E_ALL);

header('Content-Type: application/json');

include 'conexion.php';  // Debe exponer class Conexion { public function getConexion(): PDO; }

$conexionObj = new Conexion();
$pdo = $conexionObj->getConexion();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["error" => "Método no permitido"]);
    exit;
}

$data_raw = file_get_contents("php://input");
error_log("Datos recibidos en bruto: " . $data_raw);
$data = json_decode($data_raw, true);
if (!$data) {
    echo json_encode(["error" => "No se recibieron datos"]);
    exit;
}

/* ========= Helpers ========= hoy */

function normalizarMonto($n) {
    $n = trim((string)$n);
    if ($n === '') return 0.0;

    // Dejo solo dígitos, separadores y signo
    $n = preg_replace('/[^\d.,-]/', '', $n);

    $hasDot  = strpos($n, '.') !== false;
    $hasComma= strpos($n, ',') !== false;

    // Ambos separadores: el ÚLTIMO es decimal
    if ($hasDot && $hasComma) {
        if (strrpos($n, '.') > strrpos($n, ',')) {
            // '.' es decimal → quitar comas (miles)
            $n = str_replace(',', '', $n);
            return (float)$n;
        } else {
            // ',' es decimal → quitar puntos (miles) y cambiar ',' por '.'
            $n = str_replace('.', '', $n);
            $n = str_replace(',', '.', $n);
            return (float)$n;
        }
    }

    // Solo coma
    if ($hasComma) {
        $pos = strrpos($n, ',');
        $after = strlen($n) - $pos - 1;
        if ($after === 3 && strlen($n) > 4) {
            // probablemente miles
            $n = str_replace(',', '', $n);
            return (float)$n;
        } else {
            // coma decimal
            $n = str_replace(',', '.', $n);
            return (float)$n;
        }
    }

    // Solo punto
    if ($hasDot) {
        $pos = strrpos($n, '.');
        $after = strlen($n) - $pos - 1;
        if ($after === 3 && strlen($n) > 4) {
            // probablemente miles
            $n = str_replace('.', '', $n);
            return (float)$n;
        } else {
            // punto decimal
            return (float)$n;
        }
    }

    // Sin separadores
    return (float)$n;
}


function buscarIdCierreExistente(PDO $pdo, $sucursal, $fechaISO, $turno, $usuario) {
    $sql = "SELECT Id_cierre
            FROM totales_ventas
            WHERE Sucursal = :s AND Fecha = :f AND Turno = :t AND Usuario = :u
            ORDER BY Id_cierre DESC
            LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([':s'=>$sucursal, ':f'=>$fechaISO, ':t'=>$turno, ':u'=>$usuario]);
    $id = $st->fetchColumn();
    return $id !== false ? $id : null;
}

function borrarCierreCompleto(PDO $pdo, $Id_Cierre) {
    // Borrar DETALLES primero
    $pdo->prepare("DELETE FROM detalle_tarjetas           WHERE Id_cierre = ?")->execute([$Id_Cierre]);
    $pdo->prepare("DELETE FROM detalle_gastos             WHERE Id_cierre = ?")->execute([$Id_Cierre]);
    $pdo->prepare("DELETE FROM detalle_chicas             WHERE Id_cierre = ?")->execute([$Id_Cierre]);
    $pdo->prepare("DELETE FROM detalle_efectivo_rendido   WHERE Id_cierre = ?")->execute([$Id_Cierre]);
    // Borrar CABECERA
    $pdo->prepare("DELETE FROM totales_ventas WHERE Id_cierre = ?")->execute([$Id_Cierre]);
}

function generarIdCierreDesdeBase(PDO $pdo, string $base) {
    // Robusto: toma el mayor sufijo existente (evita saltos si hubo borrados)
    $sql = "SELECT MAX(CAST(SUBSTRING(Id_cierre, :len_base_plus) AS UNSIGNED)) AS max_suf
            FROM totales_ventas
            WHERE Id_cierre LIKE CONCAT(:base, '%')";
    $st = $pdo->prepare($sql);
    $lenBase = strlen($base) + 1;
    $st->execute([':len_base_plus'=>$lenBase, ':base'=>$base]);
    $max = (int)($st->fetchColumn() ?: 0);
    $next = $max + 1; // si no hay ninguno -> 1 => "01"
    return $base . str_pad($next, 2, '0', STR_PAD_LEFT);
}

/* ========= Datos del payload ========= */

$sucursal = trim($data["sucursal"] ?? "");                         // *** NOMBRE de sucursal (como se guarda en DB) ***
$fechaRaw = trim($data["fecha"] ?? "");                            // puede venir "YYYY-MM-DD" (ideal)
$turno    = strtoupper(trim($data["turno"] ?? ""));
$usuario  = trim($data["Usuario"] ?? ($data["usuario"] ?? ""));

$comentario   = substr(trim($data["comentarios"] ?? ""), 0, 150);
$idCierreBase = trim($data["Id_cierre_base"] ?? "");
$accion       = strtolower(trim($data["accion"] ?? ""));          // "" | "sobrescribir"

// Normalizar fecha a ISO Y-m-d (si viniera en otro formato, strtotime lo resuelve)
$fechaISO = date('Y-m-d', strtotime($fechaRaw ?: 'today'));

// Totales (ajustá las claves si cambian en tu front)
$totalEfectivo         = normalizarMonto($data["inputTotalEfectivo"]         ?? "0");
$totalCredito          = normalizarMonto($data["inputTotalCredito"]          ?? "0");
$totalDebito           = normalizarMonto($data["inputTotalDebito"]           ?? "0");
$totalTransferencias   = normalizarMonto($data["inputTotalTransferencias"]   ?? "0");
$totalTransferenciasQR = normalizarMonto($data["inputTotalTransferenciasQR"] ?? "0");
$totalFinancieras      = normalizarMonto($data["inputTotalFinancieras"]      ?? "0");

// Detalles
$tarjetas = is_array($data["tarjetas"] ?? null) ? $data["tarjetas"] : [];
$gastos   = is_array($data["gastos"]   ?? null) ? $data["gastos"]   : [];
$chicas   = is_array($data["chicas"]   ?? null) ? $data["chicas"]   : [];
$billetes = is_array($data["billetes"] ?? null) ? $data["billetes"] : [];

try {
    if ($sucursal==='' || $fechaISO==='' || $turno==='' || $usuario==='') {
        throw new Exception('Faltan sucursal/fecha/turno/usuario');
    }

    $pdo->beginTransaction();

    /* ===== Resolver Id_cierre ===== */
    $Id_Cierre = null;

    if ($accion === 'sobrescribir') {
        // 1) Buscar existente
        $Id_Cierre = buscarIdCierreExistente($pdo, $sucursal, $fechaISO, $turno, $usuario);
        if ($Id_Cierre) {
            // 2) Borrar todo lo anterior de ese Id (reutilizaremos el mismo)
            borrarCierreCompleto($pdo, $Id_Cierre);
        } else {
            // Si no hay existente, caemos a alta normal
            $accion = '';
        }
    }

    if ($accion !== 'sobrescribir') {
        // Alta normal → generar nuevo ID con base
        if ($idCierreBase === '') {
            // reconstruir base por seguridad
            $anio2 = substr($fechaISO, 2, 2);
            $mes   = substr($fechaISO, 5, 2);
            $dia   = substr($fechaISO, 8, 2);
            $suc3  = strtoupper(substr($sucursal, 0, 3));
            $idCierreBase = $anio2 . $mes . $dia . $suc3 . $turno;
        }
        $Id_Cierre = generarIdCierreDesdeBase($pdo, $idCierreBase);
    }

    error_log("Id_cierre_final resuelto en PHP: " . $Id_Cierre);

    /* ===== Insertar CABECERA ===== */
    $sqlCab = "INSERT INTO totales_ventas
      (Id_cierre, Sucursal, Fecha, Turno, Usuario,
       Total_efectivo, Total_tarjetas_credito, Total_tarjetas_debito,
       Total_transferencias, Total_transferenciasQR, Total_financieras,
       Comentario)
      VALUES
      (:id, :suc, :fec, :tur, :usr,
       :tef, :tcred, :tdeb, :ttrans, :tqr, :tfin, :com)";
    $stCab = $pdo->prepare($sqlCab);
    $stCab->execute([
        ':id'   => $Id_Cierre,
        ':suc'  => $sucursal,
        ':fec'  => $fechaISO,
        ':tur'  => $turno,
        ':usr'  => $usuario,
        ':tef'  => $totalEfectivo,
        ':tcred'=> $totalCredito,
        ':tdeb' => $totalDebito,
        ':ttrans'=> $totalTransferencias,
        ':tqr'  => $totalTransferenciasQR,
        ':tfin' => $totalFinancieras,
        ':com'  => $comentario,
    ]);

    /* ===== Insertar DETALLES ===== */

    // Tarjetas
    if (!empty($tarjetas)) {
        $sql = "INSERT INTO detalle_tarjetas
            (Id_tarjeta, Id_cierre, Sucursal, Fecha, Turno,
             Forma_de_pago, Tarjeta, Monto, Lote, Cupon)
            VALUES (:idt, :idc, :s, :f, :t, :fp, :tar, :m, :lote, :cupon)";
        $ins = $pdo->prepare($sql);
        $i = 1;
        foreach ($tarjetas as $t) {
            $ins->execute([
                ':idt'  => $Id_Cierre . str_pad($i++, 2, '0', STR_PAD_LEFT),
                ':idc'  => $Id_Cierre,
                ':s'    => $sucursal,
                ':f'    => $fechaISO,
                ':t'    => $turno,
                ':fp'   => $t['formaPago'] ?? '',
                ':tar'  => $t['tarjeta']   ?? '',
                ':m'    => normalizarMonto($t['monto'] ?? 0),
                ':lote' => $t['lote']      ?? '',
                ':cupon'=> $t['cupon']     ?? '',
            ]);
        }
    }

    // Gastos
    if (!empty($gastos)) {
        $sql = "INSERT INTO detalle_gastos
            (Id_gasto, Id_cierre, Sucursal, Fecha, Turno, Concepto, Monto)
            VALUES (:idg, :idc, :s, :f, :t, :c, :m)";
        $ins = $pdo->prepare($sql);
        $i = 1;
        foreach ($gastos as $g) {
            $ins->execute([
                ':idg' => $Id_Cierre . str_pad($i++, 2, '0', STR_PAD_LEFT),
                ':idc' => $Id_Cierre,
                ':s'   => $sucursal,
                ':f'   => $fechaISO,
                ':t'   => $turno,
                ':c'   => $g['concepto'] ?? '',
                ':m'   => normalizarMonto($g['monto'] ?? 0),
            ]);
        }
    }

    // Chicas
    if (!empty($chicas)) {
        $sql = "INSERT INTO detalle_chicas
            (Id_chica, Id_cierre, Sucursal, Fecha, Turno, Nombre)
            VALUES (:idch, :idc, :s, :f, :t, :n)";
        $ins = $pdo->prepare($sql);
        $i = 1;
        foreach ($chicas as $nombre) {
            $ins->execute([
                ':idch'=> $Id_Cierre . str_pad($i++, 2, '0', STR_PAD_LEFT),
                ':idc' => $Id_Cierre,
                ':s'   => $sucursal,
                ':f'   => $fechaISO,
                ':t'   => $turno,
                ':n'   => trim($nombre),
            ]);
        }
    }

    // Efectivo rendido
    $sql = "INSERT INTO detalle_efectivo_rendido
        (Id_cierre, Sucursal, Fecha, Turno,
         Denominacion_billete1, Cantidad_billete1,
         Denominacion_billete2, Cantidad_billete2,
         Denominacion_billete3, Cantidad_billete3,
         Denominacion_billete4, Cantidad_billete4,
         Denominacion_billete5, Cantidad_billete5,
         Denominacion_billete6, Cantidad_billete6,
         Denominacion_billete7, Cantidad_billete7,
         Efectivo_rendido, Diferencia_caja)
        VALUES
        (:idc, :s, :f, :t,
         :d1, :c1, :d2, :c2, :d3, :c3, :d4, :c4, :d5, :c5, :d6, :c6, :d7, :c7,
         :er, :dif)";
    $ins = $pdo->prepare($sql);

    // Armar valores para las 7 denominaciones
    $den = []; $cant = [];
    for ($i=0; $i<7; $i++) {
        $den[$i]  = isset($billetes[$i]["denominacion"])
            ? str_replace('.', '', (string)$billetes[$i]["denominacion"])
            : null;
        $cant[$i] = isset($billetes[$i]["cantidad"])
            ? (int)$billetes[$i]["cantidad"]
            : null;
    }

    $efectivoRendido = isset($data["inputEfectivoRendido"])
        ? (float)str_replace('.', '', (string)$data["inputEfectivoRendido"])
        : 0;
    $diferenciaCaja  = isset($data["inputDiferenciaCaja"])
        ? (float)str_replace('.', '', (string)$data["inputDiferenciaCaja"])
        : 0;

    $ins->execute([
        ':idc'=>$Id_Cierre, ':s'=>$sucursal, ':f'=>$fechaISO, ':t'=>$turno,
        ':d1'=>$den[0], ':c1'=>$cant[0],
        ':d2'=>$den[1], ':c2'=>$cant[1],
        ':d3'=>$den[2], ':c3'=>$cant[2],
        ':d4'=>$den[3], ':c4'=>$cant[3],
        ':d5'=>$den[4], ':c5'=>$cant[4],
        ':d6'=>$den[5], ':c6'=>$cant[5],
        ':d7'=>$den[6], ':c7'=>$cant[6],
        ':er'=>$efectivoRendido, ':dif'=>$diferenciaCaja
    ]);

    $pdo->commit();

    echo json_encode([
        "success"   => "Datos guardados correctamente",
        "Id_Cierre" => $Id_Cierre
    ]);
    error_log("Se ejecutó procesar.php correctamente");

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log("Error en procesar.php: " . $e->getMessage());
    echo json_encode(["error" => "Error al guardar los datos", "detalles" => $e->getMessage()]);
} finally {
    if (isset($pdo)) $pdo = null;
}



