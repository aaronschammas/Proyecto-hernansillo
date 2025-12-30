<?php
// consultar_informe_sucursal.php (FIX)
// Informe entre fechas y por sucursal:
// - Suma cierres CONTROLADOS (control_cierres_resumen)
// - + cierres NO CONTROLADOS (totales_ventas donde NO exista en control_cierres)
// - Soporta fechas guardadas como DATE o como texto: YYYY-mm-dd / dd-mm-YYYY / dd/mm/YYYY

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log_consultar_informe_sucursal.txt');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=UTF-8');

require_once 'conexion.php';

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
    $db = new Conexion();
    $pdo = $db->getConexion();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Para permitir placeholders repetidos dentro de subconsultas/UNION sin problemas
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

    $fechaDesdeIn = $_POST['fechaDesde'] ?? '';
    $fechaHastaIn = $_POST['fechaHasta'] ?? '';
    $sucursalesStr = $_POST['sucursales'] ?? ''; // cadena separada por comas

    $fechaDesde = normalizarFechaISO($fechaDesdeIn);
    $fechaHasta = normalizarFechaISO($fechaHastaIn);

    if (!$fechaDesde || !$fechaHasta) {
        echo json_encode(["error" => "Fechas no proporcionadas."]);
        exit;
    }

    // Collation consistente para evitar mix de collations
    $COL = 'utf8mb4_general_ci';

    // Expresión robusta de fecha (DATE o VARCHAR con formatos mixtos)
    $fechaExpr = "COALESCE(DATE(Fecha), STR_TO_DATE(Fecha, '%d-%m-%Y'), STR_TO_DATE(Fecha, '%d/%m/%Y'))";

    // Normalización de sucursal (abreviado -> nombre completo). Agregadas PDP y ECO.
    $sucNorm = "UPPER(TRIM(CONVERT(%s USING utf8mb4))) COLLATE $COL";
    $caseSucursalTpl = "CASE %s
        WHEN 'MAF' THEN 'MAFALDA'
        WHEN 'MAFALDA' THEN 'MAFALDA'
        WHEN 'BAI' THEN 'BAIRES CLUB'
        WHEN 'BAIRES CLUB' THEN 'BAIRES CLUB'
        WHEN 'MAD' THEN 'MADERO'
        WHEN 'MADERO' THEN 'MADERO'
        WHEN 'TER' THEN 'TERRACOTA'
        WHEN 'TERRACOTA' THEN 'TERRACOTA'
        WHEN 'MIX' THEN 'MIX'
        WHEN 'LOL' THEN 'LOLITA'
        WHEN 'LOLITA' THEN 'LOLITA'
        WHEN 'ALM' THEN 'AMO LA MODA'
        WHEN 'AMO LA MODA' THEN 'AMO LA MODA'
        WHEN 'BAR' THEN 'VIA BARCELONA'
        WHEN 'VIA BARCELONA' THEN 'VIA BARCELONA'
        WHEN 'PDP' THEN 'PICO DE PATO'
        WHEN 'PICO DE PATO' THEN 'PICO DE PATO'
        WHEN 'ECO' THEN 'ECO MAX'
        WHEN 'ECO MAX' THEN 'ECO MAX'
        ELSE %s
    END";

    // CASE para cada tabla
    $caseSucursalCtrl = sprintf($caseSucursalTpl, sprintf($sucNorm, 'Sucursal'), sprintf($sucNorm, 'Sucursal'));
    $caseSucursalTv   = sprintf($caseSucursalTpl, sprintf($sucNorm, 'tv.Sucursal'), sprintf($sucNorm, 'tv.Sucursal'));

    // Parámetros base
    $params = [
        ':desde' => $fechaDesde,
        ':hasta' => $fechaHasta,
    ];

    // Filtro opcional por lista de sucursales (normalizadas a MAYUS)
    $whereSuc = '';
    if (!empty($sucursalesStr)) {
        $sucursales = array_filter(array_map('trim', explode(',', $sucursalesStr)));
        $placeholders = [];
        foreach ($sucursales as $i => $suc) {
            $key = ":suc$i";
            $placeholders[] = $key;
            $params[$key] = strtoupper(trim($suc));
        }
        if ($placeholders) {
            $whereSuc = " WHERE UPPER(TRIM(sucursal_norm)) IN (" . implode(',', $placeholders) . ")";
        }
    }

    // IMPORTANTÍSIMO:
    // - control_cierres_resumen trae CONTROLADOS
    // - totales_ventas trae NO CONTROLADOS => excluimos los que ya estén en control_cierres por Id_cierre
    $sql = "
        SELECT
            sucursal_norm AS Sucursal,
            SUM(total_efectivo)         AS total_efectivo,
            SUM(total_tarjeta_credito)  AS total_tarjeta_credito,
            SUM(total_tarjeta_debito)   AS total_tarjeta_debito,
            SUM(total_financiera)       AS total_financiera,
            SUM(total_transferencia)    AS total_transferencia,
            SUM(total_transferenciaQR)  AS total_transferenciaQR
        FROM (
            -- CONTROLADOS
            SELECT
                $caseSucursalCtrl AS sucursal_norm,
                IFNULL(total_efectivo,0)        AS total_efectivo,
                IFNULL(total_credito,0)         AS total_tarjeta_credito,
                IFNULL(total_debito,0)          AS total_tarjeta_debito,
                IFNULL(total_financieras,0)     AS total_financiera,
                IFNULL(total_transferencias,0)  AS total_transferencia,
                IFNULL(total_qr,0)              AS total_transferenciaQR
            FROM control_cierres_resumen
            WHERE $fechaExpr BETWEEN :desde AND :hasta

            UNION ALL

            -- NO CONTROLADOS
            SELECT
                $caseSucursalTv AS sucursal_norm,
                IFNULL(tv.Total_efectivo,0)          AS total_efectivo,
                IFNULL(tv.Total_tarjetas_credito,0)  AS total_tarjeta_credito,
                IFNULL(tv.Total_tarjetas_debito,0)   AS total_tarjeta_debito,
                IFNULL(tv.Total_financieras,0)       AS total_financiera,
                IFNULL(tv.Total_transferencias,0)    AS total_transferencia,
                IFNULL(tv.Total_transferenciasQR,0)  AS total_transferenciaQR
            FROM totales_ventas tv
            WHERE $fechaExpr BETWEEN :desde AND :hasta
              AND NOT EXISTS (
                SELECT 1
                FROM control_cierres cc
                WHERE CONVERT(cc.Id_cierre USING utf8mb4) COLLATE $COL =
                      CONVERT(tv.Id_cierre USING utf8mb4) COLLATE $COL
              )
        ) X
        $whereSuc
        GROUP BY sucursal_norm
        ORDER BY sucursal_norm
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }

    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($rows, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error en la consulta: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}

