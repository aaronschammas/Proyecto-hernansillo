<?php
// caja.php
date_default_timezone_set('America/Argentina/Buenos_Aires');
session_start();

// Si no hay usuario logueado, volver al login
if (!isset($_SESSION['usuario'])) {
    header("Location: ../index.php");
    exit();
}

$nombreUsuario = strtoupper($_SESSION['usuario']);

require_once '../conexion.php';

// Variables de fechas (por compatibilidad si después las querés usar)
$fechaHoy    = date("Y-m-d");
$fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fechaFin    = $_GET['fecha_final']  ?? $fechaHoy;

$db  = new Conexion();
$pdo = $db->getConexion();

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
  <title>Caja Grande Mega</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/interfaz.css">

  <style>
    .total-caja{
      text-align: right;
      font-weight: bold;
      width: 140px;
    }

    /* Switch caja abierta/cerrada */
    .switch {
      position: relative;
      display: inline-block;
      width: 160px;
      height: 38px;
    }
    .switch input {
      opacity: 0;
      width: 0;
      height: 0;
    }
    .slider {
      position: absolute;
      cursor: default;
      top: 0; left: 0; right: 0; bottom: 0;
      background-color: #ccc;
      transition: .4s;
      border-radius: 34px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      color: #ffffff;
      font-size: 0.9rem;
      padding: 0 10px;
      box-sizing: border-box;
    }
    .slider:before {
      position: absolute;
      content: "";
      height: 30px;
      width: 30px;
      left: 4px;
      bottom: 4px;
      background-color: white;
      transition: .4s;
      border-radius: 50%;
    }
    input:checked + .slider {
      background-color: #28a745;
    }
    input:checked + .slider:before {
      transform: translateX(120px);
    }
    .slider.round {
      border-radius: 34px;
    }
    .slider.round:before {
      border-radius: 50%;
    }
    .switch-text {
      position: relative;
      z-index: 2;
      white-space: nowrap;
    }

    /* Input diferencia caja coloreado */
    #inputDiferenciaCaja {
      color: #fff;
    }
  </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid position-relative">

    <a class="navbar-brand" href="#">
      Bienvenido <?= htmlspecialchars($nombreUsuario) ?>
    </a>

    <div class="position-absolute top-50 start-50 translate-middle text-white fw-bold">
      Registro Caja Central
    </div>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavAltMarkup">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse justify-content-end" id="navbarNavAltMarkup">
      <div class="navbar-nav">
        <a class="nav-link" href="../registro1.php">Registro</a>
        <a class="nav-link" href="../InformeDiario.php">Informes</a>
        <a class="nav-link" href="#"
           onclick="window.open('https://www.google.com/search?q=calculadora','Calculadora','width=750,height=600');return false;">
          Calculadora
        </a>
        <a class="nav-link" href="../index.php">Salir</a>
      </div>
    </div>
  </div>
</nav>

<div class="container mt-4">

  <!-- Botón abrir + switch -->
  <div class="d-flex justify-content-between align-items-center mb-1">
    <button type="button" class="btn btn-success btn-lg" id="btnAbrirCaja"
            data-bs-toggle="modal" data-bs-target="#modalMontoInicial">
      Abrir caja
    </button>

    <div class="text-end">
      <label class="switch mb-0">
        <input type="checkbox" id="switchCajaAbierta" disabled>
        <span class="slider round">
          <span class="switch-text" id="textoSwitchCaja">Caja Cerrada</span>
        </span>
      </label>
      <div class="mt-1">
        <small id="labelIdCaja" class="text-light"></small>
      </div>
    </div>
  </div>

  <!-- INICIO / SALDO -->
  <div class="row align-items-center mb-4 mt-2">
    <div class="col-md-6 d-flex align-items-center">
      <label for="inputMontoInicialPagina" class="col-form-label me-2 fw-bold">
        Inicio:
      </label>
      <input type="text" class="form-control total-caja"
             id="inputMontoInicialPagina"
             value="0"
             disabled>
    </div>

    <div class="col-md-6 d-flex align-items-center justify-content-end">
      <label for="saldoActualCaja" class="col-form-label me-2 fw-bold">
        Saldo:
      </label>
      <input type="text" class="form-control total-caja"
             id="saldoActualCaja"
             value="0"
             disabled>
    </div>
  </div>

  <!-- INGRESOS / EGRESOS -->
  <div class="row mt-4">
    <!-- INGRESOS -->
    <div class="col-md-6 d-flex flex-column align-items-center mb-4">
      <div class="d-flex align-items-center mb-3">
        <h4 class="mb-0 me-2">Ingresos</h4>
        <button type="button"
                class="btn btn-outline-success btn-sm"
                id="btnAgregarIngreso"
                data-bs-toggle="modal"
                data-bs-target="#modalIngreso"
                disabled>+</button>
      </div>

      <table class="table table-bordered table-sm w-75">
        <thead class="table-light">
          <tr>
            <th class="text-center fw-bold">Origen</th>
            <th class="text-center fw-bold">Detalle</th>
            <th class="text-center fw-bold">Monto</th>
          </tr>
        </thead>
        <tbody id="tbodyIngresos"></tbody>
      </table>

      <div class="d-flex align-items-center justify-content-center mt-2">
        <label for="totalIngresos" class="me-2 mb-0">Total ingresos:</label>
        <input type="text"
               id="totalIngresos"
               class="form-control total-caja"
               value="0"
               disabled>
      </div>
    </div>

    <!-- EGRESOS -->
    <div class="col-md-6 d-flex flex-column align-items-center mb-4">
      <div class="d-flex align-items-center mb-3">
        <h4 class="mb-0 me-2">Egresos</h4>
        <button type="button"
                class="btn btn-outline-danger btn-sm"
                id="btnAgregarEgreso"
                data-bs-toggle="modal"
                data-bs-target="#modalEgreso"
                disabled>+</button>
      </div>

      <table class="table table-bordered table-sm w-75">
        <thead class="table-light">
          <tr>
            <th class="text-center fw-bold">Destino</th>
            <th class="text-center fw-bold">Detalle</th>
            <th class="text-center fw-bold">Monto</th>
          </tr>
        </thead>
        <tbody id="tbodyEgresos"></tbody>
      </table>

      <div class="d-flex align-items-center justify-content-center mt-2">
        <label for="totalEgresos" class="me-2 mb-0">Total egresos:</label>
        <input type="text"
               id="totalEgresos"
               class="form-control total-caja"
               value="0"
               disabled>
      </div>
    </div>
  </div>

  <!-- BOTÓN CERRAR CAJA -->
  <div class="row mt-4">
    <div class="col text-center">
      <button type="button"
              class="btn btn-danger btn-lg"
              id="btnCerrarCaja"
              data-bs-toggle="modal"
              data-bs-target="#modalCerrarCaja"
              disabled>
        Cerrar caja
      </button>
    </div>
  </div>

</div>

<!-- ===============================================
     MODAL: Monto inicial (Abrir caja)
     =============================================== -->
<div class="modal fade" id="modalMontoInicial" tabindex="-1"
     aria-labelledby="modalMontoInicialLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalMontoInicialLabel">Abrir caja</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <label for="inputMontoInicial" class="form-label fw-bold">
          Monto inicial de caja:
        </label>
        <input type="text" id="inputMontoInicial"
               class="form-control text-end"
               placeholder="0">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-success" id="btnConfirmarMonto">
          Confirmar
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ===============================================
     MODAL: Ingreso
     =============================================== -->
<div class="modal fade" id="modalIngreso" tabindex="-1"
     aria-labelledby="modalIngresoLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalIngresoLabel">Nuevo ingreso</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="ingresoOrigen" class="form-label">Origen:</label>
          <input type="text" id="ingresoOrigen" class="form-control">
        </div>
        <div class="mb-3">
          <label for="ingresoDetalle" class="form-label">Detalle:</label>
          <input type="text" id="ingresoDetalle" class="form-control">
        </div>
        <div class="mb-3">
          <label for="ingresoMonto" class="form-label">Monto:</label>
          <input type="text" id="ingresoMonto" class="form-control text-end" placeholder="0">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-success" id="btnAceptarIngreso">Aceptar</button>
      </div>
    </div>
  </div>
</div>

<!-- ===============================================
     MODAL: Egreso
     =============================================== -->
<div class="modal fade" id="modalEgreso" tabindex="-1"
     aria-labelledby="modalEgresoLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalEgresoLabel">Nuevo egreso</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="egresoDestino" class="form-label">Destino:</label>
          <input type="text" id="egresoDestino" class="form-control">
        </div>
        <div class="mb-3">
          <label for="egresoDetalle" class="form-label">Detalle:</label>
          <input type="text" id="egresoDetalle" class="form-control">
        </div>
        <div class="mb-3">
          <label for="egresoMonto" class="form-label">Monto:</label>
          <input type="text" id="egresoMonto" class="form-control text-end" placeholder="0">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger" id="btnAceptarEgreso">Aceptar</button>
      </div>
    </div>
  </div>
</div>

<!-- ===============================================
     MODAL: Cerrar caja
     =============================================== -->
<div class="modal fade" id="modalCerrarCaja" tabindex="-1"
     aria-labelledby="modalCerrarCajaLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalCerrarCajaLabel">Cerrar caja</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">

        <!-- Saldo calculado -->
        <div class="mb-3 d-flex align-items-center">
          <label for="inputSaldoRegistro" class="form-label me-2 fw-bold">
            Saldo calculado:
          </label>
          <input type="text" id="inputSaldoRegistro"
                 class="form-control total-caja"
                 readonly>
        </div>

        <!-- Tabla de billetes -->
        <div class="mb-3">
          <table class="table table-sm" id="tablaBilletes">
            <thead class="table-light">
              <tr>
                <th class="text-center">Denominación</th>
                <th class="text-center">Cantidad</th>
              </tr>
            </thead>
            <tbody>
              <tr data-denominacion="20000">
                <td class="text-end">20.000</td>
                <td><input type="number" min="0" step="1" class="form-control form-control-sm billete-cantidad"></td>
              </tr>
              <tr data-denominacion="10000">
                <td class="text-end">10.000</td>
                <td><input type="number" min="0" step="1" class="form-control form-control-sm billete-cantidad"></td>
              </tr>
              <tr data-denominacion="2000">
                <td class="text-end">2.000</td>
                <td><input type="number" min="0" step="1" class="form-control form-control-sm billete-cantidad"></td>
              </tr>
              <tr data-denominacion="1000">
                <td class="text-end">1.000</td>
                <td><input type="number" min="0" step="1" class="form-control form-control-sm billete-cantidad"></td>
              </tr>
              <tr data-denominacion="500">
                <td class="text-end">500</td>
                <td><input type="number" min="0" step="1" class="form-control form-control-sm billete-cantidad"></td>
              </tr>
              <tr data-denominacion="200">
                <td class="text-end">200</td>
                <td><input type="number" min="0" step="1" class="form-control form-control-sm billete-cantidad"></td>
              </tr>
              <tr data-denominacion="100">
                <td class="text-end">100</td>
                <td><input type="number" min="0" step="1" class="form-control form-control-sm billete-cantidad"></td>
              </tr>
              <tr data-denominacion="50">
                <td class="text-end">50</td>
                <td><input type="number" min="0" step="1" class="form-control form-control-sm billete-cantidad"></td>
              </tr>
              <tr data-denominacion="20">
                <td class="text-end">20</td>
                <td><input type="number" min="0" step="1" class="form-control form-control-sm billete-cantidad"></td>
              </tr>
              <tr data-denominacion="10">
                <td class="text-end">10</td>
                <td><input type="number" min="0" step="1" class="form-control form-control-sm billete-cantidad"></td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Efectivo rendido -->
        <div class="mb-3 d-flex align-items-center">
          <label for="inputEfectivoRendido" class="form-label me-2 fw-bold">
            Efectivo rendido:
          </label>
          <input type="text" id="inputEfectivoRendido"
                 class="form-control total-caja"
                 readonly>
        </div>

        <!-- Diferencia de caja -->
        <div class="mb-3 d-flex align-items-center">
          <label for="inputDiferenciaCaja" class="form-label me-2 fw-bold">
            Diferencia caja:
          </label>
          <input type="text" id="inputDiferenciaCaja"
                 class="form-control total-caja"
                 readonly>
        </div>

        <!-- Comentario -->
        <hr class="my-3">
        <div class="mb-3">
          <label for="comentarioCierre" class="form-label fw-bold">
            Comentario:
          </label>
          <textarea id="comentarioCierre"
                    class="form-control"
                    rows="3"
                    placeholder="Escriba aquí cualquier aclaración sobre el cierre de caja..."></textarea>
        </div>

      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="btnGuardarImprimir">
          Guardar e imprimir
        </button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const USUARIO_CIERRE = "<?= htmlspecialchars($nombreUsuario) ?>";

  let montoInicialNum  = 0;
  let totalIngresosNum = 0;
  let totalEgresosNum  = 0;
  let saldoActualNum   = 0;
  let idCierreCajaActual = null;

  function parseNumero(valor) {
    if (!valor) return 0;
    const limpio = valor.toString().replace(/\./g, '').replace(',', '.');
    const num = parseFloat(limpio);
    return isNaN(num) ? 0 : num;
  }

  function formatearNumeroMiles(valor) {
    const numero = parseNumero(valor);
    return numero.toLocaleString('es-AR', {
      minimumFractionDigits: 0,
      maximumFractionDigits: 0
    });
  }

  function formatearNumeroConSigno(valor) {
    const abs = Math.abs(valor);
    const textoAbs = formatearNumeroMiles(abs);
    if (valor < 0) return '-' + textoAbs;
    if (valor > 0) return '+' + textoAbs;
    return '0';
  }

  function actualizarSaldoActual() {
    const saldo = montoInicialNum + totalIngresosNum - totalEgresosNum;
    saldoActualNum = saldo;
    document.getElementById('saldoActualCaja').value = formatearNumeroMiles(saldo);
  }

  function obtenerFechaDesdeId(id) {
    if (!id || id.length < 8) return '';
    const dia  = id.substring(0, 2);
    const mes  = id.substring(2, 4);
    const anio = id.substring(4, 8);
    return `${dia}/${mes}/${anio}`;
  }

  function obtenerTurnoDesdeId(id) {
    if (!id) return '';
    if (id.indexOf('T') !== -1) return 'Tarde';
    return 'Mañana';
  }

  // -------- Ventana de impresión tipo comandera ----------
  function imprimirTicketCierre(info, billetes) {
    const fechaStr = obtenerFechaDesdeId(info.idCierreCaja);
    const turnoStr = obtenerTurnoDesdeId(info.idCierreCaja);

    const lineas = [];

    lineas.push('RESUMEN CIERRE DE CAJA');
    lineas.push('Fecha: ' + fechaStr + '   Turno: ' + turnoStr);
    lineas.push('Usuario: ' + info.usuario);
    lineas.push('--------------------------------');
    lineas.push('Saldo inicial caja: ' + formatearNumeroMiles(info.saldoInicial));
    lineas.push('');
    lineas.push('INGRESOS');

    if (info.ingresos && info.ingresos.length) {
      info.ingresos.forEach(i => {
        lineas.push('- ' + i.origen + ' | ' + i.detalle);
        lineas.push('  $ ' + formatearNumeroMiles(i.monto));
      });
    } else {
      lineas.push('(sin ingresos)');
    }

    lineas.push('Total Ingresos: ' + formatearNumeroMiles(info.totalIngresos));
    lineas.push('');
    lineas.push('EGRESOS');

    if (info.egresos && info.egresos.length) {
      info.egresos.forEach(e => {
        lineas.push('- ' + e.destino + ' | ' + e.detalle);
        lineas.push('  $ ' + formatearNumeroMiles(e.monto));
      });
    } else {
      lineas.push('(sin egresos)');
    }

    lineas.push('Total Egresos: ' + formatearNumeroMiles(info.totalEgresos));
    lineas.push('');
    lineas.push('Saldo calculado: ' + formatearNumeroMiles(info.saldoSegunRegistro));
    lineas.push('');
    lineas.push('EFECTIVO RENDIDO');

    if (billetes && billetes.length) {
      billetes.forEach(b => {
        if (b.cantidad > 0) {
          lineas.push(
            b.cantidad + ' x ' +
            formatearNumeroMiles(b.denominacion) + ' = ' +
            formatearNumeroMiles(b.subtotal)
          );
        }
      });
    }

    lineas.push('Total Rendido: ' + formatearNumeroMiles(info.efectivoRendido));
    lineas.push('Diferencia Caja: ' + formatearNumeroConSigno(info.diferencia));
    lineas.push('');
    lineas.push('Comentario:');
    lineas.push((info.comentario || '').trim() || '(sin comentario)');
    lineas.push('');

    const contenido = lineas.join('\n');
    const contenidoEscapado = contenido
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');

    const w = window.open('', '_blank', 'width=400,height=800');

    w.document.write(`
      <html>
        <head>
          <title>Resumen cierre de caja</title>
          <style>
            body {
              font-family: "Consolas", "Courier New", monospace;
              font-size: 16px;        /* letra más grande */
              line-height: 1.7;       /* más espacio entre líneas */
              margin: 0;
              padding: 8px;
              background: #ffffff;
            }
            pre {
              margin: 0;
              white-space: pre;
            }
          </style>
        </head>
        <body>
          <pre>${contenidoEscapado}</pre>
          <script>
            window.onload = function() {
              window.print();
              setTimeout(function(){ window.close(); }, 500);
            };
          <\/script>
        </body>
      </html>
    `);

    w.document.close();
  }

  // ================== Abrir caja ==================
  document.getElementById('btnConfirmarMonto').addEventListener('click', async function () {
    const montoModalStr = document.getElementById('inputMontoInicial').value.trim();

    if (!montoModalStr) {
      alert('Por favor ingrese el monto inicial de caja.');
      return;
    }
    const montoModalNum = parseNumero(montoModalStr);
    if (montoModalNum <= 0) {
      alert('El monto inicial debe ser mayor a cero.');
      return;
    }

    const formData = new FormData();
    formData.append('montoInicial', montoModalNum);

    try {
      const resp = await fetch('caja_abrir.php', {
        method: 'POST',
        body: formData
      });

      if (!resp.ok) throw new Error('Error de red');

      const data = await resp.json();
      if (!data.ok) {
        alert(data.mensaje || 'No se pudo abrir la caja en la base de datos.');
        return;
      }

      idCierreCajaActual = data.IdCierreCaja;
      document.getElementById('labelIdCaja').textContent = 'ID Caja: ' + idCierreCajaActual;

      montoInicialNum = montoModalNum;

      const inputInicio = document.getElementById('inputMontoInicialPagina');
      inputInicio.value   = formatearNumeroMiles(montoInicialNum);
      inputInicio.disabled = false;
      inputInicio.readOnly = true;

      const saldoInput = document.getElementById('saldoActualCaja');
      saldoInput.disabled = false;
      saldoInput.readOnly = true;

      const totalIngInput = document.getElementById('totalIngresos');
      totalIngInput.disabled = false;
      totalIngInput.readOnly = true;
      totalIngInput.value = formatearNumeroMiles(totalIngresosNum);

      const totalEgrInput = document.getElementById('totalEgresos');
      totalEgrInput.disabled = false;
      totalEgrInput.readOnly = true;
      totalEgrInput.value = formatearNumeroMiles(totalEgresosNum);

      const switchCaja  = document.getElementById('switchCajaAbierta');
      const textoSwitch = document.getElementById('textoSwitchCaja');
      switchCaja.checked      = true;
      textoSwitch.textContent = 'Caja Abierta';

      document.getElementById('btnAgregarIngreso').disabled = false;
      document.getElementById('btnAgregarEgreso').disabled  = false;
      document.getElementById('btnCerrarCaja').disabled     = false;

      actualizarSaldoActual();

      const modalEl = document.getElementById('modalMontoInicial');
      bootstrap.Modal.getInstance(modalEl).hide();

    } catch (err) {
      console.error(err);
      alert('Ocurrió un error al abrir la caja. Intente de nuevo.');
    }
  });

  // ================== Ingresos ==================
  document.getElementById('btnAceptarIngreso').addEventListener('click', function () {
    const origen   = document.getElementById('ingresoOrigen').value.trim();
    const detalle  = document.getElementById('ingresoDetalle').value.trim();
    const montoStr = document.getElementById('ingresoMonto').value.trim();
    const monto    = parseNumero(montoStr);

    if (!origen || !detalle || !montoStr) {
      alert('Complete origen, detalle y monto del ingreso.');
      return;
    }
    if (monto <= 0) {
      alert('El monto del ingreso debe ser mayor a cero.');
      return;
    }

    const tbody = document.getElementById('tbodyIngresos');
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${origen}</td>
      <td>${detalle}</td>
      <td class="text-end fw-bold">${formatearNumeroMiles(monto)}</td>
    `;
    tbody.appendChild(tr);

    totalIngresosNum += monto;
    document.getElementById('totalIngresos').value =
      formatearNumeroMiles(totalIngresosNum);

    actualizarSaldoActual();

    document.getElementById('ingresoOrigen').value  = '';
    document.getElementById('ingresoDetalle').value = '';
    document.getElementById('ingresoMonto').value   = '';

    const modalEl = document.getElementById('modalIngreso');
    bootstrap.Modal.getInstance(modalEl).hide();
  });

  // ================== Egresos ==================
  document.getElementById('btnAceptarEgreso').addEventListener('click', function () {
    const destino  = document.getElementById('egresoDestino').value.trim();
    const detalle  = document.getElementById('egresoDetalle').value.trim();
    const montoStr = document.getElementById('egresoMonto').value.trim();
    const monto    = parseNumero(montoStr);

    if (!destino || !detalle || !montoStr) {
      alert('Complete destino, detalle y monto del egreso.');
      return;
    }
    if (monto <= 0) {
      alert('El monto del egreso debe ser mayor a cero.');
      return;
    }

    const tbody = document.getElementById('tbodyEgresos');
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${destino}</td>
      <td>${detalle}</td>
      <td class="text-end fw-bold">${formatearNumeroMiles(monto)}</td>
    `;
    tbody.appendChild(tr);

    totalEgresosNum += monto;
    document.getElementById('totalEgresos').value =
      formatearNumeroMiles(totalEgresosNum);

    actualizarSaldoActual();

    document.getElementById('egresoDestino').value = '';
    document.getElementById('egresoDetalle').value = '';
    document.getElementById('egresoMonto').value   = '';

    const modalEl = document.getElementById('modalEgreso');
    bootstrap.Modal.getInstance(modalEl).hide();
  });

  // ================== Modal Cerrar caja: cálculo de billetes ==================
  function recalcularEfectivoYDiff() {
    const filas = document.querySelectorAll('#tablaBilletes tbody tr');
    let efectivoRendidoNum = 0;

    filas.forEach(fila => {
      const denom = parseInt(fila.dataset.denominacion, 10);
      const inputCant = fila.querySelector('.billete-cantidad');
      let cant = parseNumero(inputCant.value);
      if (cant < 0) cant = 0;
      inputCant.value = cant === 0 ? '' : cant;
      efectivoRendidoNum += denom * cant;
    });

    document.getElementById('inputEfectivoRendido').value =
      formatearNumeroMiles(efectivoRendidoNum);

    const diferenciaNum = efectivoRendidoNum - saldoActualNum;
    const inputDiff = document.getElementById('inputDiferenciaCaja');
    inputDiff.value = formatearNumeroConSigno(diferenciaNum);

    if (diferenciaNum === 0) {
      inputDiff.style.backgroundColor = '#198754'; // verde
    } else if (diferenciaNum < 0) {
      inputDiff.style.backgroundColor = '#dc3545'; // rojo
    } else {
      inputDiff.style.backgroundColor = '#0d6efd'; // azul
    }
  }

  document.querySelectorAll('.billete-cantidad').forEach(inp => {
    inp.addEventListener('input', recalcularEfectivoYDiff);
  });

  // Cuando se abre el modal de cerrar caja, inicializar campos
  const modalCerrar = document.getElementById('modalCerrarCaja');
  modalCerrar.addEventListener('show.bs.modal', function () {
    document.getElementById('inputSaldoRegistro').value =
      formatearNumeroMiles(saldoActualNum);

    // Limpiar cantidades
    document.querySelectorAll('.billete-cantidad').forEach(inp => inp.value = '');

    document.getElementById('inputEfectivoRendido').value = '0';
    document.getElementById('inputDiferenciaCaja').value  = '0';
    document.getElementById('inputDiferenciaCaja').style.backgroundColor = '#198754';
    document.getElementById('comentarioCierre').value = '';

    recalcularEfectivoYDiff();
  });

  // ================== Guardar e imprimir cierre ==================
  document.getElementById('btnGuardarImprimir').addEventListener('click', async function () {
    if (!idCierreCajaActual) {
      alert('No hay caja abierta.');
      return;
    }

    // 1) Billetes
    const filas = document.querySelectorAll('#tablaBilletes tbody tr');
    let efectivoRendidoNum = 0;
    const billetes = [];

    filas.forEach(fila => {
      const denom = parseInt(fila.dataset.denominacion, 10);
      const inputCant = fila.querySelector('.billete-cantidad');
      const cant = parseNumero(inputCant.value);
      const subtotal = denom * cant;
      efectivoRendidoNum += subtotal;
      billetes.push({
        denominacion: denom,
        cantidad: cant,
        subtotal: subtotal
      });
    });

    const diferenciaNum = efectivoRendidoNum - saldoActualNum;
    const comentario = document.getElementById('comentarioCierre').value || '';

    // 2) Ingresos
    const ingresos = [];
    document.querySelectorAll('#tbodyIngresos tr').forEach(tr => {
      const tds = tr.querySelectorAll('td');
      const origen   = tds[0].innerText.trim();
      const detalle  = tds[1].innerText.trim();
      const montoStr = tds[2].innerText.trim();
      const monto    = parseNumero(montoStr);
      ingresos.push({ origen, detalle, monto });
    });

    // 3) Egresos
    const egresos = [];
    document.querySelectorAll('#tbodyEgresos tr').forEach(tr => {
      const tds = tr.querySelectorAll('td');
      const destino = tds[0].innerText.trim();
      const detalle = tds[1].innerText.trim();
      const montoStr = tds[2].innerText.trim();
      const monto    = parseNumero(montoStr);
      egresos.push({ destino, detalle, monto });
    });

    // 4) Payload
    const payload = {
      idCierreCaja:       idCierreCajaActual,
      usuario:            USUARIO_CIERRE,
      saldoInicial:       montoInicialNum,
      totalIngresos:      totalIngresosNum,
      totalEgresos:       totalEgresosNum,
      saldoSegunRegistro: saldoActualNum,
      efectivoRendido:    efectivoRendidoNum,
      diferencia:         diferenciaNum,
      comentario:         comentario,
      ingresos:           ingresos,
      egresos:            egresos
    };

    try {
      const resp = await fetch('caja_cerrar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      if (!resp.ok) throw new Error('Error de red');
      const data = await resp.json();

      if (!data.ok) {
        alert(data.mensaje || 'Error al guardar el cierre.');
        return;
      }

      alert('Datos cargados correctamente.');

      // Imprimir en ventana nueva tipo comandera
      imprimirTicketCierre(payload, billetes);

      // Recargar la página de caja después de un pequeño delay
      setTimeout(() => {
        window.location.href = window.location.href;
      }, 1500);

    } catch (err) {
      console.error(err);
      alert('Ocurrió un error al guardar el cierre.');
    }
  });
</script>

</body>
</html>



