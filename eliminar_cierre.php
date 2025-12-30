<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log_eliminar_cierre.txt');
header('Content-Type: application/json; charset=UTF-8');
session_start();
if (!isset($_SESSION['usuario'])) { echo json_encode(['ok'=>false,'error'=>'Sesión no válida']); exit; }
require_once 'conexion.php';
try {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  $id = trim($data['id_cierre'] ?? '');
  if ($id==='') { echo json_encode(['ok'=>false,'error'=>'Falta id_cierre']); exit; }

  $db = new Conexion(); $pdo = $db->getConexion();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $stmt = $pdo->prepare("SELECT 1 FROM totales_ventas WHERE Id_cierre=:id LIMIT 1");
  $stmt->execute([':id'=>$id]);
  if(!$stmt->fetchColumn()){ echo json_encode(['ok'=>false,'error'=>'El Id_cierre no existe en totales_ventas']); exit; }

  $pdo->beginTransaction();
  $deleted = ['chicas'=>0,'gastos'=>0,'efectivo'=>0,'tarjetas'=>0,'totales'=>0];

  $stmt = $pdo->prepare("DELETE FROM detalle_chicas WHERE Id_cierre=:id");              $stmt->execute([':id'=>$id]); $deleted['chicas']   = $stmt->rowCount();
  $stmt = $pdo->prepare("DELETE FROM detalle_gastos WHERE Id_cierre=:id");              $stmt->execute([':id'=>$id]); $deleted['gastos']   = $stmt->rowCount();
  $stmt = $pdo->prepare("DELETE FROM detalle_efectivo_rendido WHERE Id_cierre=:id");    $stmt->execute([':id'=>$id]); $deleted['efectivo'] = $stmt->rowCount();
  $stmt = $pdo->prepare("DELETE FROM detalle_tarjetas WHERE Id_cierre=:id");            $stmt->execute([':id'=>$id]); $deleted['tarjetas'] = $stmt->rowCount();
  $stmt = $pdo->prepare("DELETE FROM totales_ventas WHERE Id_cierre=:id");              $stmt->execute([':id'=>$id]); $deleted['totales']  = $stmt->rowCount();

  $pdo->commit();
  echo json_encode(['ok'=>true,'deleted'=>$deleted]);
} catch (Throwable $e) {
  if(isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
