<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');
session_start();

// Si no hay usuario logueado, redirigir al login
if (empty($_SESSION['usuario'])) {
    header('Location: index.php');
    exit();
}

// Capturar datos de sesión
$nombreUsuario = strtoupper($_SESSION['usuario']);
$sucursalSeleccionada = $_SESSION['sucursal'];
$rol = $_SESSION['rol'] ?? '';

// Conexión a BD
include 'conexion.php';
include 'funcionesphp1.php';
try {
    $conexion = conectar('localhost','u467512787_moda','Hernan2215','u467512787_mega');
} catch (Exception $e) {
    die('Error de conexión: '.$e->getMessage());
}

// Funciones de datos previas...
$nombreSucursalSeleccionada = obtenerNombreSucursal($sucursalSeleccionada);
$formas_de_pago      = obtenerFormasDePago($sucursalSeleccionada);
$tarjetas_credito    = obtenerTarjetasCredito($sucursalSeleccionada);
$tarjetas_debito     = obtenerTarjetasDebito($sucursalSeleccionada);
$transferencias      = obtenerTransferencias($sucursalSeleccionada);
$transferenciasQR    = obtenerTransferenciasQR($sucursalSeleccionada);
$financieras         = obtenerFinancieras($sucursalSeleccionada);

// Generación de ID de cierre...
$horaActual = date('H:i');
$turno = ($horaActual < '14:30') ? 'M' : 'T';
$fechaHoy = date('Y-m-d');
$anio = date('y', strtotime($fechaHoy));
$mes  = date('m', strtotime($fechaHoy));
$dia  = date('d', strtotime($fechaHoy));
$sucursalAbreviada = strtoupper(substr($sucursalSeleccionada, 0, 3));
$idCierreBase = $anio.$mes.$dia.$sucursalAbreviada.$turno;
$idCierre = generarIdCierreFinal($conexion, $idCierreBase);
$nombreSucursalSeleccionada = obtenerNombreSucursal($sucursalSeleccionada);
$existeCierreUsuario = existeCierrePorUsuario($conexion, $nombreSucursalSeleccionada, $fechaHoy, $turno, $nombreUsuario);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Formulario de carga cierres</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="css/interfaz.css" />
</head>
<body>
  <input type="hidden" id="inputUsuario" value="<?= htmlspecialchars($nombreUsuario) ?>">
  <input type="hidden" id="dupExiste" value="<?php echo $existeCierreUsuario; ?>">
  <!-- Navbar dinámico según rol -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
      <a class="navbar-brand" href="#">Bienvenido <?= htmlspecialchars($nombreUsuario) ?></a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavAltMarkup" aria-controls="navbarNavAltMarkup" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNavAltMarkup">
        <div class="navbar-nav ms-auto">
          <?php if ($rol === 'CAJERA'): ?>
            <a class="nav-link" href="#" onclick="window.open('https://www.google.com/search?q=calculadora','Calculadora','width=750,height=600');return false;">Calculadora</a>
          <?php elseif ($rol === 'ADMINISTRACION'): ?>
            <a class="nav-link" href="administracion.php">Administración</a>
            <a class="nav-link" href="#" onclick="window.open('https://www.google.com/search?q=calculadora','Calculadora','width=750,height=600');return false;">Calculadora</a>
          <?php elseif ($rol === 'SOCIO'): ?>
            <a class="nav-link" href="administracion.php">Administración</a>
            <a class="nav-link" href="InformeDiario.php">Informes</a>
            <a class="nav-link" href="#" onclick="window.open('https://www.google.com/search?q=calculadora','Calculadora','width=750,height=600');return false;">Calculadora</a>
          <?php endif; ?>
          <a class="nav-link" href="index.php">Salir</a>
        </div>
      </div>
    </div>
  </nav>
    <div class="container my-1">
    <form id="formEncabezado">
    <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12">
        <label id="labelSucursal" for="inputSucursal" style="height: 30px; font-weight: bold;">Sucursal:</label>
        <input id="inputSucursal" name="sucursal" style="margin: 3px; width: 200px; height: 30px; font-weight: bold;" value="<?php echo isset($nombreSucursalSeleccionada) ? $nombreSucursalSeleccionada : ''; ?>" readonly>

        <label id="labelFecha" for="inputFecha" style="margin: 3px; font-weight: bold">Fecha:</label>
        <input type="text" id="inputFecha" name="fecha" style="width: 110px; height: 30px; margin: 1px; text-align: center; font-weight: bold" value="<?php echo date('d-m-Y'); ?>" readonly>

        <label for="inputTurno" style="margin: 3px; font-weight: bold">Turno:</label>
        <input type="text" id="inputTurno" name="turno" style="width: 110px; height: 30px; margin: 3px; text-align: center; font-weight: bold" value="<?php echo (date('H:i') < '14:30:00') ? 'M' : 'T'; ?>" readonly>
        <input type="hidden" id="turno_hidden" value="<?php echo (date('H:i') < '14:30:00') ? 'M' : 'T'; ?>">
        <label for="inputIdCierreGenerado" style="margin: 3px; font-weight: bold">Id Cierre:</label>
        <input type="text" id="inputIdCierreGenerado" name="id_cierre" style="width: 180px; height: 30px; margin: 3px; text-align: center; font-weight: bold;" value="<?php echo $idCierre ?? ''; ?>" readonly>
    </div>
    </form>
    </div>            
    <div class="container my-2">
    <div class="row">
    <div class="col-sm-12 col-md-6 col-lg-3 col-xl-3 py-3 bg-white">
        <form id="formTotales" >
            <div class="form-group">
                <label for="inputTotalEfectivo">Total Venta Efectivo</label>
                <input name="inputTotalEfectivo" type="text" class="form-control" id="inputTotalEfectivo" style="text-align: right;" onblur="formatearTotalEfectivo()" onkeydown="enterEnEfeVtas(event)" >
            </div>
            <div class="form-group">
                <label for="inputTotalCredito">Total Tarjetas de Crédito</label>
                <input type="text" class="form-control" name="inputTotalCredito" id="inputTotalCredito" value=0 style="text-align: right;"readonly tabindex="-1">
            </div>
            <div class="form-group">
                <label for="inputTotalDebito">Tota Tarjetas de Débito</label>
                <input type="text" class="form-control" name="inputTotalDebito" id="inputTotalDebito" value=0 style="text-align: right;"readonly tabindex="-1">
            </div>
             <div class="form-group">
                <label for="inputTotalTransferencias">Total Transferecias</label>
                <input type="text" class="form-control" name="inputTotalTransferencias" id="inputTotalTransferencias" value=0 style="text-align: right;"readonly tabindex="-1">
            </div>
             <div class="form-group">
                <label for="inputTotalTransferenciasQR">Total Transferecias QR</label>
                <input type="text" class="form-control" name="inputTotalTransferenciasQR" id="inputTotalTransferenciasQR" value=0 style="text-align: right;"readonly tabindex="-1">
            </div>
            <div class="form-group">
                <label for="inputTotalFinancieras">Total Financieras</label>
                <input type="text" class="form-control" name="inputTotalFinancieras" id="inputTotalFinancieras" value=0 style="text-align: right;"readonly tabindex="-1">
            </div>
            <div class="form-group">
                <label for="inputTotalGastos">Total Gastos</label></br>
                <input type="text" class="form-control" name="inputTotalGastos" id="inputTotalGastos" value=0 style="text-align: right;" readonly tabindex="-1">
            </div>    
            <div class="form-group">
                <label for="inputEfectivoRendido">Efectivo Rendido</label></br>
                <input type="text" class="form-control" name="inputEfectivoRendido" id="inputEfectivoRendido" value=0 style="text-align: right;"readonly tabindex="-1">
            </div>  
            <div class="form-group">
                <label for="inputDiferenciaCaja">Diferencia Efectivo</label>
                <input type="text" class="form-control" name="inputDiferenciaCaja" id="inputDiferenciaCaja" value=0 style="text-align: right;" readonly tabindex="-1">
            </div>
            <div class="form-group">
                <label for="inputTotalVentas"style="text-align: center;"><strong>TOTAL VENTAS</strong></label></br>
                <input type="text" class="form-control" name="inputTotalVentas" id="inputTotalVentas" value=0 style="text-align: right; font-weight: bold;"readonly tabindex="-1">
            </div>
        </form>
        </div>    
        <div class="col-sm-12 col-md-9 col-lg-9 col-xl-9 py-3 bg-white">
            <h3>Lista de Tarjetas, Financieras y Transferencias</h3>
            <table class="table table-light table-striped" id="tablaTarjetas">
                    <thead>
                        <tr style="height: 40px;">
                            <th class="centrado"style="width: 35%;">Forma de pago</th>
                            <th class="centrado"style="width: 32%;">Tarjeta/Financiera</th>
                            <th class="centrado"style="width: 28%;">Monto</th>
                        </tr>
                    </thead>
                    <tbody id="cuerpoTablaTarjetas">
                            <tr>
                            <td>
                            <select class="form-select" id="selectFormasDePago">
                                <option value="">Seleccionar</option><?php foreach ($formas_de_pago as $fdp) {echo "<option value=\"{$fdp['nombre_forma_de_pago']}\">{$fdp['nombre_forma_de_pago']}</option>";}?>
                            </select>
                            </td>
                            <td>    
                            <select class="form-select" id="selectTarjetas">
                                <option value="">Seleccionar</option>
                                    <script>
                                            document.getElementById("selectFormasDePago").addEventListener("change", function() {
                                            var formaPagoSeleccionada = this.value;
                                            var selectTarjetas = document.getElementById("selectTarjetas");
                                            selectTarjetas.innerHTML = "";
                                                <?php
                                                foreach ($formas_de_pago as $fdp) {
                                                    $formaPago = $fdp['nombre_forma_de_pago'];
                                                    echo "if (formaPagoSeleccionada === \"$formaPago\") {";
                                                if ($formaPago === "Tarjeta Débito") {
                                                    foreach ($tarjetas_debito as $tarjeta) {
                                                    echo "selectTarjetas.innerHTML += \"<option value='{$tarjeta['nombre_tarjeta']}'>{$tarjeta['nombre_tarjeta']}</option>\";";
                                                    }
                                                } elseif ($formaPago === "Tarjeta Crédito") {
                                                foreach ($tarjetas_credito as $tarjeta) {
                                                    echo "selectTarjetas.innerHTML += \"<option value='{$tarjeta['nombre_plan']}'>{$tarjeta['nombre_plan']}</option>\";";
                                                }
                                                }elseif ($formaPago === "Financiera") {
                                                foreach ($financieras as $financiera) {
                                                    echo "selectTarjetas.innerHTML += \"<option value='{$financiera['nombre_plan']}'>{$financiera['nombre_plan']}</option>\";";
                                                    }
                                                } elseif ($formaPago === "Transferencia") {
                                                foreach ($transferencias as $transferencias) {
                                                    echo "selectTarjetas.innerHTML += \"<option value='{$transferencias['nombre_transferencia']}'>{$transferencias['nombre_transferencia']}</option>\";";
                                                }
                                                } elseif ($formaPago === "Transferencia QR") {
                                                foreach ($transferenciasQR as $transferenciasQR) {
                                                    echo "selectTarjetas.innerHTML += \"<option value='{$transferenciasQR['nombre_transferencia']}'>{$transferenciasQR['nombre_transferencia']}</option>\";";
                                                }
                                                }
                                                echo "}";
                                                }
                                                ?>
                                               });
                                    </script>
                                    </select>                                   
                                    <td id="inputMontoTarjeta" class="centrado" style="width: 28%;">
                                        <input id="montoTarjeta" style="width: 100%; text-align: right;" onkeydown="enterEnMontoTarjeta(event)">
                                    </td>                                                
                        </tr>
                    </tbody>
            </table>
            <div class="text-end"> 
            <button id="btnAgregarFila" type="button" class="btn btn-info" style="width: 200px; height: 40px;" onclick="agregarFila(); actualizarTotales();">Agregar</button>
            </div>
    <br>
    <form id="formTarjetas" >
    <table class="table table-bordered" id="tablaTarjetasAgregadas">
    <thead>
        
        <tr style="height: 35px;">
            <th class="centrado"style="width: 25%;">Forma de pago</th>
            <th class="centrado"style="width: 25%;">Tarjeta/Financiera</th>
            <th class="centrado"style="width: 20%;">Monto</th>
            <th class="centrado"style="width: 10%;">Lote</th>
            <th class="centrado"style="width: 10%;">Cupón</th>
            <th class="centrado"style="width: 10%;">Borrar</th>
        </tr>
    </thead>
    <tbody>
    
    </tbody>
</table>
</form>
</div>
<div class="col-sm-12 col-md-6 col-lg-3 col-xl-3 bg-white">
</br>
</br>
    <form id="formChicas" >
    <h5>Chicas</h5>
    <table class="table table-light table-striped" id="tablaChicas">
        <thead>
            <th style="height: 30px;" class="centrado">Nombre</th>
            </thead>
        <tbody>
            <tr style="height: 20px;">
                    <td class="centrado" style="text-align: center;" contenteditable="true"></td>
                </tr>
                <tr style="height: 20px;">
                    <td class="centrado" style="text-align: center;" contenteditable="true"></td>
                </tr>
                <tr style="height: 20px;">
                    <td class="centrado" style="text-align: center;" contenteditable="true"></td>
                </tr>
                <tr style="height: 20px;">
                    <td class="centrado" style="text-align: center;" contenteditable="true"></td>
                </tr>
                <tr style="height: 20px;">
                    <td class="centrado" style="text-align: center;" contenteditable="true"></td>
                </tr>
                <tr style="height: 20px;">
                    <td class="centrado" style="text-align: center;" contenteditable="true"></td>
                </tr>
        </tbody>
    </table>
    </form>
</div>
    <div class="col-sm-12 col-md-6 col-lg-3 col-xl-3 bg-white">
        </br>
        </br>
        <form id="formGastos" >
        <h5>Listado de gastos</h5>
        <table class="table table-light table-striped" id="tablaDeGastos">
            <thead>
                <tr style="height: 30px;">
                    <th class="centrado">Concepto</th>
                    <th class="centrado">Monto</th>
                </tr>
            </thead>
            <tbody>
                <tr style="height: 20px;">
                    <td class="centrado" style="text-align: left;" contenteditable="true"></td>
                    <td class="centrado" contenteditable="true" style="text-align: right;" id="montoGasto1" onblur="actualizarTotalGastos()" onkeydown="enterEnGasto(event)"></td>
                </tr>
                <tr style="height: 20px;">
                    <td class="centrado" style="text-align: left;" contenteditable="true"></td>
                    <td class="centrado" contenteditable="true" style="text-align: right;" id="montoGasto2" onblur="actualizarTotalGastos()" onkeydown="enterEnGasto(event)"></td>
                </tr>
                <tr style="height: 20px;">
                    <td class="centrado" style="text-align: left;" contenteditable="true"></td>
                    <td class="centrado" contenteditable="true" style="text-align: right;" id="montoGasto3" onblur="actualizarTotalGastos()" onkeydown="enterEnGasto(event)"></td>
                </tr>
                <tr style="height: 20px;">
                    <td class="centrado" style="text-align: left;" contenteditable="true"></td>
                    <td class="centrado" contenteditable="true" style="text-align: right;" id="montoGasto4" onblur="actualizarTotalGastos()" onkeydown="enterEnGasto(event)"></td>
                </tr>
                <tr style="height: 20px;">
                    <td class="centrado" style="text-align: left;" contenteditable="true"></td>
                    <td class="centrado" contenteditable="true" style="text-align: right;" id="montoGasto5" onblur="actualizarTotalGastos()" onkeydown="enterEnGasto(event)"></td>
                </tr>
                <tr style="height: 20px;">
                    <td class="centrado" style="text-align: left;" contenteditable="true"></td>
                    <td class="centrado" contenteditable="true" style="text-align: right;" id="montoGasto6" onblur="actualizarTotalGastos()" onkeydown="enterEnGasto(event)"></td>
                </tr>
            </tbody>
        </table>
        </form>
    </div>
    <div class="col-sm-12 col-md-6 col-lg-3 col-xl-3 bg-white">    
        <table class="table table-light table-striped" id="tablaBilletes">
        </br>
        </br>
            <form id="formBilletes" >
            <h5>Billetes Rendidos</h5>
            <thead>
                <tr style="height: 20px;">
                    <th class="centrado">Denominación</th>
                    <th class="centrado">Cantidad</th>
                </tr>
            </thead>
            <tbody>
                <tr style="height: 10px;">
                    <td class="centrado" id="denominacionBillete1">20.000</td>
                    <td>
                        <input contenteditable="true" style="border: none; background-color: transparent; text-align: center; width: 100%; box-sizing: border-box;" id="cantidadBillete1" name="cantidadBillete1" onblur="actualizarTotalEfectivoRendido()" onkeydown="enterEnBillete(event)"></input>
                    </td>
                </tr>
                <tr style="height: 10px;">
                    <td class="centrado" id="denominacionBillete2">10.000</td>
                    <td>
                        <input contenteditable="true" style="border: none; background-color: transparent; text-align: center; width: 100%; box-sizing: border-box;" id="cantidadBillete2" name="cantidadBillete2" onblur="actualizarTotalEfectivoRendido()" onkeydown="enterEnBillete(event)"></input>
                    </td>
                </tr>
                <tr style="height: 10px;">
                    <td class="centrado" id="denominacionBillete3">2.000</td>
                    <td>
                        <input contenteditable="true" style="border: none; background-color: transparent; text-align: center; width: 100%; box-sizing: border-box;" id="cantidadBillete3" name="cantidadBillete3" onblur="actualizarTotalEfectivoRendido()" onkeydown="enterEnBillete(event)"></input>
                    </td>
                </tr>
                <tr style="height: 10px;">
                    <td class="centrado" id="denominacionBillete4">1.000</td>
                    <td>
                        <input contenteditable="true" style="border: none; background-color: transparent; text-align: center; width: 100%; box-sizing: border-box;" id="cantidadBillete4" name="cantidadBillete4" onblur="actualizarTotalEfectivoRendido()" onkeydown="enterEnBillete(event)"></input>
                    </td>
                </tr>
                <tr style="height: 10px;">
                    <td class="centrado"id="denominacionBillete5">500</td>
                    <td>
                        <input contenteditable="true" style="border: none; background-color: transparent; text-align: center; width: 100%; box-sizing: border-box;" id="cantidadBillete5" name="cantidadBillete5" onblur="actualizarTotalEfectivoRendido()" onkeydown="enterEnBillete(event)"></input>
                    </td>                            
                </tr>
                <tr style="height: 10px;">
                    <td class="centrado" id="denominacionBillete6">200</td>
                    <td>
                        <input contenteditable="true" style="border: none; background-color: transparent; text-align: center; width: 100%; box-sizing: border-box;" id="cantidadBillete6" name="cantidadBillete6" onblur="actualizarTotalEfectivoRendido()" onkeydown="enterEnBillete(event)"></input>
                    </td>                           
                </tr>
                <tr style="height: 10px;">
                    <td class="centrado" id="denominacionBillete7">100</td>
                    <td>
                        <input contenteditable="true" style="border: none; background-color: transparent; text-align: center; width: 100%; box-sizing: border-box;" id="cantidadBillete7" name="cantidadBillete7" onblur="actualizarTotalEfectivoRendido()" onkeydown="enterEnBillete(event)"></input>
                    </td>
                </tr>
            </tbody>
        </table>
        </form>
        </div>
        <div class="col-sm-12 col-md-6 col-lg-3 col-xl-3 bg-white">
            </br>
            </br>
            <form id="formComentarios" >
            <h5>Comentarios / Aclaraciones</h5>
              <div class="form-group">
                <textarea id="txtComentario" name="comentarios" value="" style="background-color: #f0f0f0; width: 100%; height: 140px; resize: none;"></textarea></br>
            </div></br>
                <div class="d-gridgap-2">
                <button id="btnLimpiar" class="btn btn-secondary" style="width: 100%; height: 60px; " onclick="recargarPagina()">Limpiar</button>
                </br></br>
                <button id="btnGuardar" type="button" class="btn btn-success" style="width: 100%; height: 60px;">Guardar e Imprimir</button>
                </div></br>
            </div>                              
          </form>  
        </div>
        <div class="text-end">
        </div>
        </form>
    <!-- Modal duplicado -->
        <div class="modal fade" id="modalDuplicado" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius:14px;">
              <div class="modal-header">
                <h5 class="modal-title">Cierre ya existente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
              </div>
              <div class="modal-body">
                Ya existe un cierre para esta <strong>sucursal, fecha, turno y usuario</strong>.<br>
                ¿Querés <strong>sobrescribir</strong> ese cierre con lo que cargaste ahora?
              </div>
              <div class="modal-footer">
                <button id="btnCancelarDup" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button id="btnConfirmarDup" class="btn btn-danger">Sobrescribir</button>
              </div>
            </div>
          </div>
        </div>

    <script src="funcionesRegistro1.js?v=<?php echo time(); ?>"></script>
    <script src="ajax1.js"></script>
    </body>
</html>

