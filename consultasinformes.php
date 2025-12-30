<?php
/**
 * consultasinformes.php — versión robusta (FECHAS MIXTAS) + ACUMULADO HASTA FECHA SELECCIONADA
 * Devuelve JSON con:
 *  - Totales del DÍA por sucursal y turno
 *  - Gastos del día
 *  - Efectivo rendido del día
 *  - Chicas del día
 *  - Acumulado mensual por sucursal = controlados + sin controlar
 *    desde el 1° del mes HASTA la fecha indicada en el date-picker (inclusive)
 */

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log_consultasinformes.txt');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=UTF-8');

function normalizarFechaISO($f) {
    $f = trim((string)$f);
    if ($f === '') return '';

    $formatos = ['Y-m-d', 'd-m-Y', 'd/m/Y'];
    foreach ($formatos as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $f);
        if ($dt && $dt->format($fmt) === $f) {
            return $dt->format('Y-m-d');
        }
    }

    $ts = strtotime($f);
    if ($ts !== false) return date('Y-m-d', $ts);

    return $f;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => 'Método no permitido']);
        exit;
    }

    $fechaIn = $_POST['fecha'] ?? '';
    $fecha = normalizarFechaISO($fechaIn);
    if (!$fecha) {
        echo json_encode(['error' => 'Fecha no proporcionada']);
        exit;
    }

    // Acumulado: desde el 1° del mes HASTA la fecha indicada (inclusive)
    $inicioMes  = date('Y-m-01', strtotime($fecha));
    $hastaFecha = $fecha;

    require_once 'conexion.php';
    $db  = new Conexion();
    $pdo = $db->getConexion();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

    // Colación consistente para evitar el 1267 (mix of collations)
    $COL = "utf8mb4_general_ci";

    // Expresión de fecha robusta (DATE o VARCHAR en distintos formatos)
    $fechaExpr = "COALESCE(
        DATE(Fecha),
        STR_TO_DATE(Fecha, '%d-%m-%Y'),
        STR_TO_DATE(Fecha, '%d/%m/%Y')
    )";

    // Misma expresión pero con alias tv (para evitar ambigüedad al hacer JOIN)
    $fechaExprTV = "COALESCE(
        DATE(tv.Fecha),
        STR_TO_DATE(tv.Fecha, '%d-%m-%Y'),
        STR_TO_DATE(tv.Fecha, '%d/%m/%Y')
    )";

    // Normalización de sucursal/turno
    $sucNorm = "UPPER(TRIM(CONVERT(Sucursal USING utf8mb4))) COLLATE $COL";
    $turNorm = "UPPER(TRIM(CONVERT(Turno USING utf8mb4))) COLLATE $COL";

    $caseSucursal = "
        CASE $sucNorm
            WHEN 'MAF' THEN 'MAFALDA'
            WHEN 'BAI' THEN 'BAIRES CLUB'
            WHEN 'MAD' THEN 'MADERO'
            WHEN 'TER' THEN 'TERRACOTA'
            WHEN 'MIX' THEN 'MIX'
            WHEN 'LOL' THEN 'LOLITA'
            WHEN 'ALM' THEN 'AMO LA MODA'
            WHEN 'BAR' THEN 'VIA BARCELONA'
            ELSE $sucNorm
        END
    ";

    $caseTurno = "
        CASE
            WHEN $turNorm IN ('M','MAÑANA','MANANA') THEN 'M'
            WHEN $turNorm IN ('T','TARDE')          THEN 'T'
            ELSE 'M'
        END
    ";

    // Base: 8 sucursales x 2 turnos = 16 filas aseguradas
    $sucs = [
        'MAFALDA','BAIRES CLUB','MADERO','TERRACOTA',
        'MIX','LOLITA','AMO LA MODA','VIA BARCELONA'
    ];
    $turnos = ['M','T'];

    $baseParts = [];
    foreach ($sucs as $s) {
        foreach ($turnos as $t) {
            $baseParts[] = "SELECT '{$s}' COLLATE $COL AS sucursal_norm, '{$t}' COLLATE $COL AS turno_norm";
        }
    }
    $baseSql = implode("\nUNION ALL\n", $baseParts);

    // 1) Totales del día (totales_ventas)
    $sqlDia = "
        SELECT
            $caseSucursal AS sucursal_norm,
            $caseTurno    AS turno_norm,
            SUM(IFNULL(Total_efectivo,0))           AS Total_efectivo,
            SUM(IFNULL(Total_tarjetas_credito,0))   AS Total_tarjeta_credito,
            SUM(IFNULL(Total_tarjetas_debito,0))    AS Total_tarjeta_debito,
            SUM(IFNULL(Total_transferencias,0))     AS Total_transferencias,
            SUM(IFNULL(Total_transferenciasQR,0))   AS Total_transferenciasQR,
            SUM(IFNULL(Total_financieras,0))        AS Total_financieras
        FROM totales_ventas
        WHERE $fechaExpr = :fecha
        GROUP BY sucursal_norm, turno_norm
    ";

    // 2) Gastos del día (detalle_gastos)
    $montoExpr = "CAST(REPLACE(REPLACE(Monto,'.',''),',','') AS UNSIGNED)";
    $sqlGastos = "
        SELECT
            $caseSucursal AS sucursal_norm,
            $caseTurno    AS turno_norm,
            SUM(IFNULL($montoExpr,0)) AS Total_gastos
        FROM detalle_gastos
        WHERE $fechaExpr = :fecha
        GROUP BY sucursal_norm, turno_norm
    ";

    // 3) Efectivo rendido del día (detalle_efectivo_rendido)
    $rendExpr = "CAST(REPLACE(REPLACE(Efectivo_rendido,'.',''),',','') AS UNSIGNED)";
    $sqlRend = "
        SELECT
            $caseSucursal AS sucursal_norm,
            $caseTurno    AS turno_norm,
            SUM(IFNULL($rendExpr,0)) AS Efectivo_rendido
        FROM detalle_efectivo_rendido
        WHERE $fechaExpr = :fecha
        GROUP BY sucursal_norm, turno_norm
    ";

    // 4) Chicas del día (detalle_chicas)
    $sqlCh = "
        SELECT
            $caseSucursal AS sucursal_norm,
            $caseTurno    AS turno_norm,
            GROUP_CONCAT(Nombre ORDER BY Nombre SEPARATOR ' + ') AS Chicas
        FROM detalle_chicas
        WHERE $fechaExpr = :fecha
        GROUP BY sucursal_norm, turno_norm
    ";

    // 5) Acumulado mensual HASTA la fecha indicada (controlados + sin controlar)
    $fechaExprCtrl = "COALESCE(
        DATE(Fecha),
        STR_TO_DATE(Fecha, '%d-%m-%Y'),
        STR_TO_DATE(Fecha, '%d/%m/%Y')
    )";

    $sqlAcumMes = "
        SELECT sucursal_norm, SUM(total_ventas) AS acumulado_mensual
        FROM (
            -- Sin controlar (totales_ventas)
            SELECT
                $caseSucursal AS sucursal_norm,
                SUM(
                    IFNULL(Total_efectivo,0)
                  + IFNULL(Total_tarjetas_credito,0)
                  + IFNULL(Total_tarjetas_debito,0)
                  + IFNULL(Total_transferencias,0)
                  + IFNULL(Total_transferenciasQR,0)
                  + IFNULL(Total_financieras,0)
                ) AS total_ventas
            FROM totales_ventas tv
            LEFT JOIN control_cierres cc
              ON CONVERT(cc.Id_cierre USING utf8mb4) COLLATE $COL
               = CONVERT(tv.Id_cierre USING utf8mb4) COLLATE $COL
            WHERE $fechaExprTV BETWEEN :iniMes AND :hastaFecha
              AND cc.Id_cierre IS NULL
            GROUP BY sucursal_norm

            UNION ALL

            -- Controlados (control_cierres_resumen)
            SELECT
                CASE UPPER(TRIM(CONVERT(Sucursal USING utf8mb4))) COLLATE $COL
                    WHEN 'MAF' THEN 'MAFALDA'
                    WHEN 'BAI' THEN 'BAIRES CLUB'
                    WHEN 'MAD' THEN 'MADERO'
                    WHEN 'TER' THEN 'TERRACOTA'
                    WHEN 'MIX' THEN 'MIX'
                    WHEN 'LOL' THEN 'LOLITA'
                    WHEN 'ALM' THEN 'AMO LA MODA'
                    WHEN 'BAR' THEN 'VIA BARCELONA'
                    ELSE UPPER(TRIM(CONVERT(Sucursal USING utf8mb4))) COLLATE $COL
                END AS sucursal_norm,
                SUM(IFNULL(total_ventas,0)) AS total_ventas
            FROM control_cierres_resumen
            WHERE $fechaExprCtrl BETWEEN :iniMes AND :hastaFecha
            GROUP BY sucursal_norm
        ) X
        GROUP BY sucursal_norm
    ";

    // Final: base + joins
    $sqlFinal = "
        SELECT
            b.sucursal_norm AS sucursal,
            b.turno_norm    AS turno,

            IFNULL(d.Total_efectivo,0)         AS Total_efectivo,
            IFNULL(d.Total_tarjeta_credito,0)  AS Total_tarjeta_credito,
            IFNULL(d.Total_tarjeta_debito,0)   AS Total_tarjeta_debito,
            IFNULL(d.Total_transferencias,0)   AS Total_transferencias,
            IFNULL(d.Total_transferenciasQR,0) AS Total_transferenciasQR,
            IFNULL(d.Total_financieras,0)      AS Total_financieras,

            IFNULL(g.Total_gastos,0)           AS Total_gastos,
            IFNULL(r.Efectivo_rendido,0)       AS Efectivo_rendido,

            IFNULL(c.Chicas,'')                AS Chicas,
            IFNULL(m.acumulado_mensual,0)      AS acumulado_mensual
        FROM (
            $baseSql
        ) b
        LEFT JOIN ($sqlDia)     d ON d.sucursal_norm = b.sucursal_norm AND d.turno_norm = b.turno_norm
        LEFT JOIN ($sqlGastos)  g ON g.sucursal_norm = b.sucursal_norm AND g.turno_norm = b.turno_norm
        LEFT JOIN ($sqlRend)    r ON r.sucursal_norm = b.sucursal_norm AND r.turno_norm = b.turno_norm
        LEFT JOIN ($sqlCh)      c ON c.sucursal_norm = b.sucursal_norm AND c.turno_norm = b.turno_norm
        LEFT JOIN ($sqlAcumMes) m ON m.sucursal_norm = b.sucursal_norm
        ORDER BY
            FIELD(b.sucursal_norm,'MAFALDA','BAIRES CLUB','MADERO','TERRACOTA','MIX','LOLITA','AMO LA MODA','VIA BARCELONA'),
            b.turno_norm
    ";

    $st = $pdo->prepare($sqlFinal);
    $st->execute([
        ':fecha'      => $fecha,
        ':iniMes'     => $inicioMes,
        ':hastaFecha' => $hastaFecha
    ]);

    $out = $st->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($out, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error'   => 'Excepción: ' . $e->getMessage(),
        'detalle' => 'Revisá error_log_consultasinformes.txt'
    ], JSON_UNESCAPED_UNICODE);
}
