<?php
// guardar_control_cierre.php — versión alineada al esquema u467512787_mega
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors','0');

try {
  require_once __DIR__ . '/conexion.php';
  $db  = new Conexion();
  $pdo = $db->getConexion();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // ==== Leer JSON del front ====
  $raw  = file_get_contents('php://input');
  $in   = json_decode($raw, true);
  if (!is_array($in)) throw new Exception('Payload JSON inválido');

  // helpers
  $i = fn($v)=> (int)preg_replace('/\D+/', '', (string)$v);
  $s = fn($v)=> trim((string)$v);

  // === Campos principales del front ===
  $id_cierre       = $s($in['id_cierre'] ?? '');
  $usuario_control = $s($in['usuario_control'] ?? 'ADMIN');
  $comentario      = $s($in['comentario'] ?? '');
  $sucursal        = $s($in['sucursal'] ?? ''); // para _resumen y efectivo_rendido
  $fecha           = $s($in['fecha'] ?? '');    // YYYY-MM-DD
  $turno           = $s($in['turno'] ?? '');    // 'M'/'T'

  if ($id_cierre === '') throw new Exception('Falta id_cierre');

  $tarjetas_ok = is_array($in['tarjetas_ok'] ?? null) ? $in['tarjetas_ok'] : [];
  $gastos_ok   = is_array($in['gastos_ok']   ?? null) ? $in['gastos_ok']   : [];
  $chicas_ok   = is_array($in['chicas_ok']   ?? null) ? $in['chicas_ok']   : [];
  $billetes    = is_array($in['billetes']    ?? null) ? $in['billetes']    : null;

  // === Cálculos de totales ===
  $total_credito = $total_debito = $total_transf = $total_financ = 0;
  foreach ($tarjetas_ok as $t) {
    $forma = mb_strtolower($s($t['forma'] ?? ''));
    $m     = $i($t['monto'] ?? 0);
    if ($m <= 0) continue;

    if (strpos($forma,'crédito')!==false || strpos($forma,'credito')!==false)      $total_credito += $m;
    elseif (strpos($forma,'débito')!==false || strpos($forma,'debito')!==false)    $total_debito  += $m;
    elseif (strpos($forma,'financ')!==false)                                       $total_financ  += $m;
    else                                                                           $total_transf  += $m; // transf / banco / etc.
  }

  $total_gastos = 0;
  foreach ($gastos_ok as $g) $total_gastos += $i($g['monto'] ?? 0);

  $total_efectivo = $i($in['total_efectivo'] ?? 0);
  $total_qr       = 0; // tu UI separa “Transferencias QR”; si luego lo mandas en el JSON, léelo aquí
  $total_ventas   = $total_efectivo + $total_credito + $total_debito + $total_transf + $total_financ;
  $saldo_efe      = $total_efectivo - $total_gastos;

  // efectivo rendido desde 7 denominaciones
  $den = array_map($i, $billetes['denoms'] ?? []);
  $can = array_map($i, $billetes['cants']  ?? []);
  // normalizar a 7 posiciones
  for ($k=0;$k<7;$k++){
    $den[$k] = $den[$k] ?? 0;
    $can[$k] = $can[$k] ?? 0;
  }
  $efectivo_rendido = 0;
  for ($k=0;$k<7;$k++) $efectivo_rendido += $den[$k] * $can[$k];
  $dif_caja = $efectivo_rendido - $saldo_efe;

  // === Persistencia ===
  $pdo->beginTransaction();

  // 1) control_cierres (cabecera)
  $pdo->prepare("DELETE FROM control_cierres WHERE Id_cierre = ?")->execute([$id_cierre]);
  $pdo->prepare("
    INSERT INTO control_cierres
      (Id_cierre, Usuario_control, Fecha_control, Comentario)
    VALUES
      (:Id_cierre, :Usuario_control, NOW(), :Comentario)
  ")->execute([
    ':Id_cierre'       => $id_cierre,
    ':Usuario_control' => $usuario_control,
    ':Comentario'      => $comentario
  ]);

  // 2) control_cierres_resumen (totales)
  $pdo->prepare("DELETE FROM control_cierres_resumen WHERE Id_cierre = ?")->execute([$id_cierre]);
  $pdo->prepare("
    INSERT INTO control_cierres_resumen
      (Id_cierre, Sucursal, Fecha, Turno,
       total_efectivo, total_credito, total_debito, total_transferencias, total_qr, total_financieras,
       total_ventas, total_gastos, saldo_caja_efectivo, efectivo_rendido, diferencia_caja)
    VALUES
      (:Id_cierre, :Sucursal, :Fecha, :Turno,
       :t_efe, :t_cre, :t_deb, :t_trf, :t_qr, :t_fin,
       :t_ven, :t_gas, :saldo, :rend, :dif)
  ")->execute([
    ':Id_cierre' => $id_cierre,
    ':Sucursal'  => $sucursal,
    ':Fecha'     => ($fecha ?: date('Y-m-d')),
    ':Turno'     => ($turno ?: ''),
    ':t_efe'     => $total_efectivo,
    ':t_cre'     => $total_credito,
    ':t_deb'     => $total_debito,
    ':t_trf'     => $total_transf,
    ':t_qr'      => $total_qr,
    ':t_fin'     => $total_financ,
    ':t_ven'     => $total_ventas,
    ':t_gas'     => $total_gastos,
    ':saldo'     => $saldo_efe,
    ':rend'      => $efectivo_rendido,
    ':dif'       => $dif_caja
  ]);

  // 3) Detalles
  $pdo->prepare("DELETE FROM control_detalle_tarjetas WHERE Id_cierre = ?")->execute([$id_cierre]);
  $pdo->prepare("DELETE FROM control_detalle_gastos   WHERE Id_cierre = ?")->execute([$id_cierre]);
  $pdo->prepare("DELETE FROM control_detalle_chicas   WHERE Id_cierre = ?")->execute([$id_cierre]);

  if (!empty($tarjetas_ok)) {
    $insT = $pdo->prepare("
      INSERT INTO control_detalle_tarjetas
        (Id_cierre, Forma_de_pago, Tarjeta, Monto, Lote, Cupon, ok)
      VALUES
        (:Id_cierre, :Forma_de_pago, :Tarjeta, :Monto, :Lote, :Cupon, 1)
    ");
    foreach ($tarjetas_ok as $t) {
      $insT->execute([
        ':Id_cierre'     => $id_cierre,
        ':Forma_de_pago' => $s($t['forma'] ?? ''),
        ':Tarjeta'       => $s($t['tarjeta'] ?? ''),
        ':Monto'         => $i($t['monto'] ?? 0),
        ':Lote'          => $s($t['lote']  ?? ''),
        ':Cupon'         => $s($t['cupon'] ?? '')
      ]);
    }
  }

  if (!empty($gastos_ok)) {
    $insG = $pdo->prepare("
      INSERT INTO control_detalle_gastos
        (Id_cierre, Concepto, Monto)
      VALUES
        (:Id_cierre, :Concepto, :Monto)
    ");
    foreach ($gastos_ok as $g) {
      $insG->execute([
        ':Id_cierre' => $id_cierre,
        ':Concepto'  => $s($g['concepto'] ?? ''),
        ':Monto'     => $i($g['monto']    ?? 0)
      ]);
    }
  }

  if (!empty($chicas_ok)) {
    $insC = $pdo->prepare("
      INSERT INTO control_detalle_chicas
        (Id_cierre, Nombre)
      VALUES
        (:Id_cierre, :Nombre)
    ");
    foreach ($chicas_ok as $nombre) {
      $insC->execute([
        ':Id_cierre' => $id_cierre,
        ':Nombre'    => $s($nombre)
      ]);
    }
  }

  // 4) Efectivo rendido (7 denominaciones fijas)
  $pdo->prepare("DELETE FROM control_efectivo_rendido WHERE Id_cierre = ?")->execute([$id_cierre]);
  $pdo->prepare("
    INSERT INTO control_efectivo_rendido
      (Id_cierre, Denominacion_billete1, Cantidad_billete1,
       Denominacion_billete2, Cantidad_billete2,
       Denominacion_billete3, Cantidad_billete3,
       Denominacion_billete4, Cantidad_billete4,
       Denominacion_billete5, Cantidad_billete5,
       Denominacion_billete6, Cantidad_billete6,
       Denominacion_billete7, Cantidad_billete7,
       Efectivo_rendido, Diferencia_caja)
    VALUES
      (:Id_cierre, :d1,:c1,:d2,:c2,:d3,:c3,:d4,:c4,:d5,:c5,:d6,:c6,:d7,:c7,:rend,:dif)
  ")->execute([
    ':Id_cierre' => $id_cierre,
    ':d1'=>$den[0], ':c1'=>$can[0],
    ':d2'=>$den[1], ':c2'=>$can[1],
    ':d3'=>$den[2], ':c3'=>$can[2],
    ':d4'=>$den[3], ':c4'=>$can[3],
    ':d5'=>$den[4], ':c5'=>$can[4],
    ':d6'=>$den[5], ':c6'=>$can[5],
    ':d7'=>$den[6], ':c7'=>$can[6],
    ':rend'=>$efectivo_rendido,
    ':dif' =>$dif_caja
  ]);

  $pdo->commit();

  echo json_encode([
    'ok'=>true,
    'mensaje'=>'Control guardado',
    'resumen'=>[
      'total_ventas'=>$total_ventas,
      'total_gastos'=>$total_gastos,
      'saldo_caja_efectivo'=>$saldo_efe,
      'efectivo_rendido'=>$efectivo_rendido,
      'diferencia_caja'=>$dif_caja
    ]
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}


