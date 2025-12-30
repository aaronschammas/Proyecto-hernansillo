<?php
// administracion.php
date_default_timezone_set('America/Argentina/Buenos_Aires');
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

$nombreUsuario       = strtoupper($_SESSION['usuario']);
include("conexion.php");

// Fechas para cierres pendientes
$fechaHoy    = date("Y-m-d");
$fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fechaFin    = $_GET['fecha_final']  ?? $fechaHoy;

// Conexión PDO
$db  = new Conexion();
$pdo = $db->getConexion();

// Consulta cierres pendientes
$stmtPend = $pdo->prepare(
    "SELECT tv.Id_cierre, tv.sucursal AS Sucursal, tv.Fecha, tv.Turno
     FROM totales_ventas tv
     LEFT JOIN control_cierres cc ON tv.Id_cierre = cc.Id_cierre
     WHERE cc.Id_cierre IS NULL
       AND tv.Fecha BETWEEN :inicio AND :fin
     ORDER BY tv.Fecha"
);
$stmtPend->execute(['inicio' => $fechaInicio, 'fin' => $fechaFin]);
$cierresPendientes = $stmtPend->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Administración Mega Moda</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/interfaz.css">
  <style>
  /* Provisorio: gris + fondo suave + itálica */
  .input-provisorio{
    color:#6c757d !important;
    background-color:#f8f9fa !important;
    border-color:#dee2e6 !important;
    font-style: italic;
  }
  .input-provisorio:focus{
    color:#6c757d !important;
    background-color:#f8f9fa !important;
    box-shadow: none;
  }
  /* Definitivo: look normal */
  .input-definitivo{
    color:#212529 !important;
    background-color:#ffffff !important;
    border-color:#ced4da !important;
    font-style: normal;
  }
</style>

</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">Bienvenido <?= $nombreUsuario; ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavAltMarkup">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNavAltMarkup">
      <div class="navbar-nav ms-auto">
        <a class="nav-link" href="registro1.php">Registro</a>
        <a class="nav-link" href="InformeDiario.php">Informes</a>
        <a class="nav-link" href="#" onclick="window.open('https://www.google.com/search?q=calculadora','Calculadora','width=750,height=600');return false;">Calculadora</a>
        <a class="nav-link" href="index.php">Salir</a>
      </div>
    </div>
  </div>
</nav>

<div class="container mt-4">
  <ul class="nav nav-tabs mb-4" id="tabAdmin" role="tablist">
    <li class="nav-item">
      <button class="nav-link active" id="tab-control-cierres" data-bs-toggle="tab" data-bs-target="#controlCierres" type="button">CONTROL DE CIERRES</button>
    </li>
    <li class="nav-item">
      <button class="nav-link" id="tab-cierres-pendientes" data-bs-toggle="tab" data-bs-target="#cierresPendientes" type="button">CIERRES PENDIENTES</button>
    </li>
    <li class="nav-item">
      <button class="nav-link" id="tab-control-acreditaciones" data-bs-toggle="tab" data-bs-target="#controlAcreditaciones" type="button">CONTROL ACREDITACIONES</button>
    </li>
  </ul>

  <div class="tab-content" id="tabAdminContent">

    <!-- 1. Control de cierres -->
    <div class="tab-pane fade show active" id="controlCierres" role="tabpanel">
      <div class="mb-3 d-flex align-items-center gap-2">
        <label for="id_cierre" class="form-label fw-bold mb-0">ID CIERRE:</label>
        <input type="text" id="id_cierre" class="form-control w-auto">
        <button class="btn btn-primary" id="consultarCierre">Consultar</button>
      </div>

      <div id="resultadoCierre" class="mt-4 d-none">
        <!-- Datos del cierre -->
        <div class="container mb-4">
          <div class="row bg-white p-3 gx-3 gy-2 rounded shadow-sm">
            <div class="col-12 col-md-4">
              <label for="inputSucursal" class="form-label">Sucursal</label>
              <input type="text" id="inputSucursal" class="form-control" readonly>
            </div>
            <div class="col-12 col-md-2">
              <label for="inputFecha" class="form-label">Fecha</label>
              <input type="text" id="inputFecha" class="form-control text-center" readonly>
            </div>
            <div class="col-12 col-md-2">
              <label for="inputTurno" class="form-label">Turno</label>
              <input type="text" id="inputTurno" class="form-control text-center" readonly>
            </div>
            <div class="col-12 col-md-4">
              <label for="inputCajero" class="form-label">Cajero</label>
              <input type="text" id="inputCajero" class="form-control" readonly>
            </div>
          </div>
        </div>

        <!-- Totales y tarjetas -->
        <div class="container my-2">
          <div class="row">
            <div class="col-sm-12 col-md-6 col-lg-3 py-3 bg-white rounded shadow-sm">
              <div class="form-group mb-2">
  <!-- fila con label y checks -->
  <div class="d-flex justify-content-between align-items-center">
    <label for="inputTotalEfectivo" class="form-label mb-0">
      Total Ventas Efectivo
    </label>
    <div class="d-flex align-items-center">
      <div class="form-check me-3 mb-0">
        <input
          class="form-check-input"
          type="checkbox"
          id="editarTotalEfectivo"
        >
        <label
          class="form-check-label"
          for="editarTotalEfectivo"
        >Editar</label>
      </div>
      <div class="form-check mb-0">
        <input
          class="form-check-input"
          type="checkbox"
          id="OKTotalEfectivo"
        >
        <label
          class="form-check-label"
          for="OKTotalEfectivo"
        >OK</label>
      </div>
    </div>
  </div>
  
  <input
    type="text"
    id="inputTotalEfectivo"
    class="form-control mt-2"
    style="text-align:right;"
    disabled
  >
</div>
            <div class="form-group mb-2">
              <label for="inputTotalCredito">Total Crédito</label>
              <input type="text" class="form-control input-provisorio" id="inputTotalCredito" style="text-align:right;" readonly>
            </div>
            
            <div class="form-group mb-2">
              <label for="inputTotalDebito">Total Débito</label>
              <input type="text" class="form-control input-provisorio" id="inputTotalDebito" style="text-align:right;" readonly>
            </div>
            
            <div class="form-group mb-2">
              <label for="inputTotalTransferencias">Total Transferencias</label>
              <input type="text" class="form-control input-provisorio" id="inputTotalTransferencias" style="text-align:right;" readonly>
            </div>
            
            <div class="form-group mb-2">
              <label for="inputTotalTransferenciasQR">Transferencias QR</label>
              <input type="text" class="form-control input-provisorio" id="inputTotalTransferenciasQR" style="text-align:right;" readonly>
            </div>
            
            <div class="form-group mb-2">
              <label for="inputTotalFinancieras">Total Financieras</label>
              <input type="text" class="form-control input-provisorio" id="inputTotalFinancieras" style="text-align:right;" readonly>
            </div>
        </div>
      <div class="col-sm-12 col-md-6 col-lg-9 py-3 bg-white rounded shadow-sm">
              <h5>Lista de Tarjetas, Financieras y Transferencias</h5>
              <table class="table table-bordered" id="tablaTarjetasAgregadas">
                <thead>
                  <tr>
                    <th>Forma de pago</th>
                    <th>Tarjeta/Financiera</th>
                    <th>Monto</th>
                    <th>Lote</th>
                    <th>Cupón</th>
                    <th>Ok</th>
                    <th>Editar</th>
                    <th>Borrar</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
              <div class="d-grid mt-2">
                  <button type="button"
                          class="btn btn-outline-primary"
                          id="btnAgregarTarjeta">
                    Agregar tarjeta
                  </button>
                </div>
            </div>
          </div>
        </div>

        <div class="container my-2">
          <div class="row">
            <!-- Columna 1: Chicas + Gastos -->
                <div class="col-sm-12 col-md-6 col-lg-3 bg-white rounded shadow-sm p-3">
                  <!-- Encabezado Chicas -->
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Chicas</h6>
                    <div>
                      <div class="form-check form-check-inline mb-0">
                        <input class="form-check-input" type="checkbox" id="editarChicas">
                        <label class="form-check-label" for="editarChicas">Editar</label>
                      </div>
                      <div class="form-check form-check-inline mb-0">
                        <input class="form-check-input" type="checkbox" id="okChicas">
                        <label class="form-check-label" for="okChicas">OK</label>
                      </div>
                    </div>
                  </div>
                  <table class="table table-light table-striped" id="tablaChicas">
                    <thead><tr><th>Nombre</th></tr></thead>
                    <tbody>
                      <?php for($i=0; $i<6; $i++): ?>
                        <tr><td contenteditable="true"></td></tr>
                      <?php endfor; ?>
                    </tbody>
                  </table>
                
                  <!-- Botón para agregar chica -->
                  <div class="d-grid mt-2">
                    <button type="button"
                            class="btn btn-outline-primary"
                            id="btnAgregarChica"
                            disabled>
                      Agregar chica
                    </button>
                  </div>
                  <!-- Encabezado Gastos -->
                  <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
                    <h6 class="mb-0">Gastos</h6>
                    <div>
                      <div class="form-check form-check-inline mb-0">
                        <input class="form-check-input" type="checkbox" id="editarGastos">
                        <label class="form-check-label" for="editarGastos">Editar</label>
                      </div>
                      <div class="form-check form-check-inline mb-0">
                        <input class="form-check-input" type="checkbox" id="okGastos">
                        <label class="form-check-label" for="okGastos">OK</label>
                      </div>
                    </div>
                  </div>
                  <table class="table table-light table-striped" id="tablaDeGastos">
                    <thead><tr><th>Concepto</th><th>Monto</th></tr></thead>
                    <tbody>
                      <?php for($i=1; $i<=6; $i++): ?>
                        <tr>
                          <td contenteditable="true"></td>
                          <td contenteditable="true"
                              id="montoGasto<?= $i ?>"
                              oninput="actualizarTotalGastos()"
                              onkeydown="enterEnGasto(event)"></td>
                        </tr>
                      <?php endfor; ?>
                    </tbody>
                  </table>
                  <!-- Botón para agregar gasto -->
                  <div class="d-grid mt-2">
                    <button type="button"
                            class="btn btn-outline-primary"
                            id="btnAgregarGasto"
                            disabled>
                      Agregar gasto
                    </button>
                  </div>
                </div>
            <!-- Columna 2: Billetes Rendidos -->
            <div class="col-sm-12 col-md-6 col-lg-3 bg-white rounded shadow-sm p-3">
              <!-- Encabezado Billetes Rendidos -->
              <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Billetes Rendidos</h6>
                <div>
                  <div class="form-check form-check-inline mb-0">
                    <input class="form-check-input" type="checkbox" id="editarBilletes">
                    <label class="form-check-label" for="editarBilletes">Editar</label>
                  </div>
                  <div class="form-check form-check-inline mb-0">
                    <input class="form-check-input" type="checkbox" id="okBilletes">
                    <label class="form-check-label" for="okBilletes">OK</label>
                  </div>
                </div>
              </div>
              <table class="table table-light table-striped" id="tablaBilletes">
                <thead><tr><th>Denominación</th><th>Cantidad</th></tr></thead>
                <tbody>
                  <?php 
                    $denoms = [20000,10000,2000,1000,500,200,100];
                    foreach($denoms as $idx => $valor): 
                  ?>
                    <tr>
                      <td id="denominacionBillete<?= $idx+1 ?>"><?= number_format($valor,0,'.','.') ?></td>
                      <td><input id="cantidadBillete<?= $idx+1 ?>" onblur="actualizarTotalEfectivoRendido()" onkeydown="enterEnBillete(event)" style="width:60px;border:none;background:transparent;text-align:center;"></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <!-- Columna 3: Resumen con totales -->
            <div class="col-sm-12 col-md-6 col-lg-3 bg-white rounded shadow-sm p-3">
              <h6>Resumen</h6>
              <div class="form-group">
                <label for="inputTotalVentas" style="text-align:center;"><strong>TOTAL VENTAS</strong></label><br>
                <input type="text"
                       class="form-control"
                       id="inputTotalVentas"
                       value="0"
                       style="text-align:right; font-weight:bold;"
                       readonly tabindex="-1">
              </div>
              <div class="form-group mb-3">
                <label for="inputTotalGastos">Total Gastos</label><br>
                <input type="text"
                       class="form-control"
                       id="inputTotalGastos"
                       value="0"
                       style="text-align:right;"
                       readonly tabindex="-1">
              </div>
              <div class="form-group mb-3">
                <label for="inputSaldoEfectivo">Saldo Caja Efectivo</label><br>
                <input type="text"
                       class="form-control"
                       id="inputSaldoEfectivo"
                       value="0"
                       style="text-align:right;"
                       readonly tabindex="-1">
              </div>
              <div class="form-group mb-3">
                <label for="inputEfectivoRendido">Efectivo Rendido</label><br>
                <input type="text"
                       class="form-control"
                       id="inputEfectivoRendido"
                       value="0"
                       style="text-align:right;"
                       readonly tabindex="-1">
              </div>
              <div class="form-group mb-3">
                <label for="inputDiferenciaCaja">Diferencia Caja</label><br>
                <input type="text"
                       class="form-control"
                       id="inputDiferenciaCaja"
                       value="0"
                       style="text-align:right;"
                       readonly tabindex="-1">
              </div>
            </div>
            <!-- Columna 4: Comentarios -->
            <div class="col-sm-12 col-md-6 col-lg-3 bg-white rounded shadow-sm p-3">
              <h6>Comentarios</h6>
              <label for="txtComentarioCajero" class="form-label">Comentario del cajero</label>
              <textarea id="txtComentarioCajero" class="form-control mb-3" style="height:80px; resize:none;" readonly tabindex="-1"></textarea>
              <label for="txtComentarioControl" class="form-label">Comentario del control</label>
              <textarea id="txtComentarioControl" class="form-control mb-3" style="height:100px; resize:none;"></textarea>
              <div class="d-grid gap-2">
                <button id="btnGuardar" type="button" class="btn btn-success">Guardar</button>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- 2. Cierres pendientes -->
<div class="tab-pane fade show" id="cierresPendientes" role="tabpanel" aria-labelledby="cierresPendientes-tab">
  <div class="mt-3">

    <h5>CIERRES PENDIENTES DE CONTROL</h5>

    <form class="row g-2 align-items-center mb-3" method="get" action="<?= htmlspecialchars(basename($_SERVER['PHP_SELF'])) ?>">
      <div class="col-auto">
        <label class="form-label mb-0">Fecha de Inicio:</label>
        <input type="date" class="form-control" name="fecha_inicio" value="<?= htmlspecialchars($fechaInicio) ?>">
      </div>
      <div class="col-auto">
        <label class="form-label mb-0">Fecha Final:</label>
        <input type="date" class="form-control" name="fecha_final" value="<?= htmlspecialchars($fechaFin) ?>">
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-success mt-4">Filtrar</button>
      </div>
    </form>

    <div class="table-responsive">
      <table id="tblCierresPendientes" class="table table-sm table-hover align-middle">
        <thead>
          <tr>
            <th>ID</th>
            <th>SUCURSAL</th>
            <th>FECHA</th>
            <th class="text-center">TURNO</th>
            <th class="text-center">Acción</th>
            <th class="text-center">Eliminar</th> <!-- última columna -->
          </tr>
        </thead>
        <tbody>
          <?php if (empty($cierresPendientes)): ?>
            <tr><td colspan="6" class="text-center">No hay cierres pendientes en este rango.</td></tr>
          <?php else: foreach ($cierresPendientes as $cp): ?>
            <tr>
              <td><?= htmlspecialchars($cp['Id_cierre']) ?></td>
              <td><?= htmlspecialchars($cp['Sucursal']) ?></td>
              <td><?= htmlspecialchars($cp['Fecha']) ?></td>
              <td class="text-center"><?= htmlspecialchars($cp['Turno']) ?></td>

              <!-- Acción: Cargar -->
              <td class="text-center">
                <button type="button"
                        class="btn btn-primary btn-sm btnCargar"
                        data-id-cierre="<?= $cp['Id_cierre'] ?>">
                  Cargar
                </button>
              </td>

              <!-- Eliminar (última) -->
              <td class="text-center">
                <button type="button"
                        class="btn btn-danger btn-sm btnEliminar"
                        data-id-cierre="<?= $cp['Id_cierre'] ?>">
                  Eliminar
                </button>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>

    <!-- 3. Control Acreditaciones -->
    <div class="tab-pane fade" id="controlAcreditaciones" role="tabpanel">
      <p class="mt-3">Aquí podés consultar las acreditaciones de tarjetas por lote, cupón o fecha.</p>
      <div class="mb-3"><label for="fecha_acreditacion" class="form-label">Fecha:</label><input type="date" id="fecha_acreditacion" class="form-control w-auto" value="<?= $fechaHoy ?>"></div>
      <button class="btn btn-success">Buscar Acreditaciones</button>
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="controlcierres.js"></script>
<script src="cierrespendientes.js"></script>

<!-- === Botón ELIMINAR: confirmación + borrado + evita disparar "Cargar" === -->
<script>
document.addEventListener('click', async function(ev){
  const btn = ev.target.closest('.btnEliminar');
  if (!btn) return;
  ev.preventDefault();
  ev.stopPropagation(); // evita que se dispare handler de "Cargar"

  const id = btn.dataset.idCierre;
  if (!id) return;

  const ok = confirm(
    "Vas a ELIMINAR DEFINITIVAMENTE el cierre " + id +
    " y todos sus detalles (chicas, gastos, efectivo, tarjetas).\n\n¿Confirmás?"
  );
  if (!ok) return;

  try {
    const resp = await fetch('eliminar_cierre.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id_cierre: id })
    });
    const data = await resp.json();
    if (!data.ok) {
      alert("No se pudo eliminar: " + (data.error || "Error desconocido"));
      return;
    }
    const d = data.deleted || {};
    alert(
      "Cierre " + id + " eliminado.\n" +
      "Chicas: "   + (d.chicas   ?? 0) + "\n" +
      "Gastos: "   + (d.gastos   ?? 0) + "\n" +
      "Efectivo: " + (d.efectivo ?? 0) + "\n" +
      "Tarjetas: " + (d.tarjetas ?? 0) + "\n" +
      "Totales: "  + (d.totales  ?? 0)
    );
    location.reload();
  } catch (e) {
    alert("Error eliminando el cierre: " + e.message);
  }
}, true);
</script>

</body>
</html>
