<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');
session_start();
if (isset($_SESSION['usuario'])) {
    $nombreUsuario = $_SESSION['usuario'];
    $nombreUsuarioMayuscula = strtoupper($nombreUsuario);
} else {
    header("Location: index.php");
    exit();
}

include("conexion.php");
include("funcionesphp.php");

$servidor = "localhost";
$usuario = "u467512787_moda";
$contrasenia = "Hernan2215";
$base_datos = "u467512787_mega";

try {
    $conexion = conectar($servidor, $usuario, $contrasenia, $base_datos);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    exit();
}

$fechaHoy = date("Y-m-d");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informe de Ventas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/interfaz.css">
    <style>
  table.table-bordered thead th{
    background:#f7dd93; text-transform:uppercase; font-weight:800; text-align:center;
  }
  tfoot td{ background:#f3e3a6; }
</style>

</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">Bienvenido <?php echo $nombreUsuarioMayuscula; ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavAltMarkup">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNavAltMarkup">
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="registro1.php">Registro</a>
                <a class="nav-link" href="administracion.php">Administración</a>
                <a class="nav-link" href="#" onclick="window.open('https://www.google.com/search?q=calculadora', 'Calculadora', 'width=750,height=600'); return false;">Calculadora</a>
                <a class="nav-link" href="index.php">Salir</a>
            </div>
        </div>
    </div>
</nav>
<div class="container mt-4">
    <!-- Nav Tabs -->
    <ul class="nav nav-tabs mb-4" id="tabInformes" role="tablist">
        <li class="nav-item" role="presentation">
            <button style="font-size: 1.5rem;" class="nav-link active" id="tab-diario" data-bs-toggle="tab" data-bs-target="#informeDiario" type="button" role="tab">INFORME DIARIO</button>
        </li>
        <li class="nav-item" role="presentation">
            <button style="font-size: 1.5rem;" class="nav-link" id="tab-mensual" data-bs-toggle="tab" data-bs-target="#informeMensual" type="button" role="tab">INFORME MENSUAL</button>
        </li>
        <li class="nav-item" role="presentation">
            <button style="font-size: 1.5rem;" class="nav-link" id="tab-sucursal" data-bs-toggle="tab" data-bs-target="#informeSucursal" type="button" role="tab">INFORME ENTRE FECHAS Y POR SUCURSAL</button>
        </li>
    </ul>
    
<div class="tab-content" id="tabInformesContent">
<!-- Informe Diario -->
        <div class="tab-pane fade show active" id="informeDiario" role="tabpanel">
            <div style="height: 30px; display: flex; align-items: center; justify-content: center;" class="text-center mb-3">
                <label for="fecha" class="form-label fw-bold">FECHA : </label>
                <input type="date" id="fecha" class="form-control d-inline-block w-auto" value="<?php echo $fechaHoy; ?>">
                <button class="btn btn-primary" id="consultarVentas">Consultar</button>
            </div>
            <h3>TURNO MAÑANA</h3><br>
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th style="width:10%; text-align:center;">SUCURSAL</th>
                  <th style="width:12%; text-align:center;">VENTA TOTAL</th>
                  <th style="width:12%; text-align:center;">VTA EFECTIVO</th>
                  <th style="width:11%; text-align:center;">GASTOS</th>
                  <th style="width:11%; text-align:center;">EFE REND</th>
                  <th style="width:11%; text-align:center;">VTA TAR</th>
                  <th style="width:20%; text-align:center;">CHICAS</th>
                  <th style="width:13%; text-align:center;">ACUM MES</th>
                </tr>
              </thead>
              <tbody id="tablaManana"></tbody>
              <tfoot id="tfootManana"></tfoot>
            </table>

            <div class="mb-4">
              <label class="form-control fw-bold text-center">Total ventas turno mañana:</label>
              <input type="text" id="inputTotalManana" class="form-control fw-bold text-center" style="font-size:1.5rem;" readonly>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
              <h3 class="m-0">TURNO TARDE</h3>
              <div id="badgeFechaTarde" class="px-3 py-2 bg-light rounded fw-bold" style="border:1px solid rgba(0,0,0,.15);">
                FECHA : --
              </div>
            </div>
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th style="width:10%; text-align:center;">SUCURSAL</th>
                  <th style="width:12%; text-align:center;">VENTA TOTAL</th>
                  <th style="width:12%; text-align:center;">VTA EFECTIVO</th>
                  <th style="width:11%; text-align:center;">GASTOS</th>
                  <th style="width:11%; text-align:center;">EFE REND</th>
                  <th style="width:11%; text-align:center;">VTA TAR</th>
                  <th style="width:20%; text-align:center;">CHICAS</th>
                  <th style="width:13%; text-align:center;">ACUM MES</th>
                </tr>
              </thead>
              <tbody id="tablaTarde"></tbody>
              <tfoot id="tfootTarde"></tfoot>
            </table>

            <div class="mb-2">
              <label class="form-control fw-bold text-center">Total ventas turno tarde:</label>
              <input type="text" id="inputTotalTarde" class="form-control fw-bold text-center" style="font-size:1.5rem;" readonly>
            </div>

            <div class="mb-5">
              <label class="form-control fw-bold text-center">Total ventas diarias:</label>
              <input type="text" id="inputTotalDiario" class="form-control fw-bold text-center" style="font-size:1.8rem;" readonly>
            </div>

</div>
<!-- Informe Mensual -->
        <div class="tab-pane fade" id="informeMensual" role="tabpanel">
        <div style="height: 30px; display: flex; align-items: center; justify-content: center;" class="text-center mb-3"> 
         <label class="form-label fw-bold" for="mesSeleccionado">MES :  </label>
          <input class="form-control d-inline-block w-auto" type="month" id="mesSeleccionado">
          <button style="align-items: center;" class="btn btn-primary" onclick="consultarInformeMensual()">Consultar</button>
        </div>
        </br>
        <table class="table table-bordered" id="tablaInformeMensual">
           <thead>
                    <tr>
                        <th style="width: 12%; text-align: center;">SUCURSAL</th>
                        <th style="width: 12%; text-align: center;">EFECTIVO</th>
                        <th style="width: 12%; text-align: center;">CREDITO</th>
                        <th style="width: 12%; text-align: center;">DEBITO</th>
                        <th style="width: 12%; text-align: center;">TRANSFERENCIAS</th>
                        <th style="width: 12%; text-align: center;">FINANCIERAS</th>
                        <th style="width: 13%; text-align: center;">TOTAL VENTAS</th>
                    </tr>
                </thead>
          <tbody></tbody>
        </table>
        </div>
<!-- Informe por FECHA Y Sucursal -->
<div class="tab-pane fade" id="informeSucursal" role="tabpanel">
    <h3 class="text-center">Informe por Sucursal e Intervalo específico de fechas</h3>
    </br>
    <label class="form-label fw-bold" for="fechaDesde">DESDE :</label>
    <input class="form-control d-inline-block w-auto" type="date" id="fechaDesde">

    <label class="form-label fw-bold" for="fechaHasta">HASTA :</label>
    <input class="form-control d-inline-block w-auto" type="date" id="fechaHasta">
<style>
    .dropdown-checklist {
        position: relative;
        display: inline-block;
        width: 300px;
    }

    .dropdown-checklist .dropdown-btn {
        padding: 8px 12px;
        border: 1px solid #ccc;
        cursor: pointer;
        background-color: #fff;
        width: 100%;
        text-align: left;
    }

    .dropdown-checklist .dropdown-content {
        display: none;
        position: absolute;
        background-color: white;
        border: 1px solid #ccc;
        max-height: 200px;
        overflow-y: auto;
        z-index: 1;
        width: 100%;
    }

    .dropdown-checklist .dropdown-content label {
        display: block;
        padding: 5px 10px;
        cursor: pointer;
    }

    .dropdown-checklist.open .dropdown-content {
        display: block;
    }
</style>
</br>
</br>
<div class="dropdown-checklist" id="sucursalDropdown">
    <div class="dropdown-btn" onclick="toggleDropdown()">Seleccionar sucursales</div>
    <div class="dropdown-content">
        <label><input type="checkbox" id="checkTodas" onchange="toggleTodas(this)"> Todas</label>
        <label><input type="checkbox" class="sucursalCheckbox" value="MAFALDA">MAFALDA</label>
        <label><input type="checkbox" class="sucursalCheckbox" value="BAIRES CLUB">BAIRES CLUB</label>
        <label><input type="checkbox" class="sucursalCheckbox" value="MADERO"> MADERO</label>
        <label><input type="checkbox" class="sucursalCheckbox" value="TERRACOTA"> TERRACOTA</label>
        <label><input type="checkbox" class="sucursalCheckbox" value="MIX"> MIX</label>
        <label><input type="checkbox" class="sucursalCheckbox" value="AMO LA MODA"> AMO LA MODA</label>
        <label><input type="checkbox" class="sucursalCheckbox" value="LOLITA">LOLITA</label>
        <label><input type="checkbox" class="sucursalCheckbox" value="VIA BARCELONA"> VIA BARCELONA</label>
        <label><input type="checkbox" class="sucursalCheckbox" value="PICO DE PATO"> PICO DE PATO</label>
        <label><input type="checkbox" class="sucursalCheckbox" value="ECO MAX"> ECO MAX</label>
    </div>
</div>

<button class="btn btn-primary" onclick="consultarInformeSucursal()">Consultar</button>
</br>
</br>
<small id="sucursalesSeleccionadas" class="form-text text-muted mt-2"></small>

</br>

<div id="mensajeSucursal" class="mt-3"></div> <!-- AGREGÁ ESTO -->

<table id="tablaSucursal" class="table table-bordered mt-3">
    <thead>
        <tr>
            <th>Sucursal</th>
            <th>Total Efectivo</th>
            <th>Total Crédito</th>
            <th>Total Débito</th>
            <th>Total Financiera</th>
            <th>Total Transferencia</th>
            <th>Total QR</th>
            <th>Total Ventas</th>
        </tr>
    </thead>
    <tbody></tbody>
</table>
</br>
<div class="mb-5">
    <label class="form-control fw-bold text-center">Total General</label>
    <input type="text" id="inputTotalSucursales" class="form-control fw-bold text-center" style="font-size: 1.8rem;" readonly>
</div>

    
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ====== Config ======
const MAPA_SUC = {
  "MAFALDA": "MAF",
  "BAIRES CLUB": "BAI",
  "MADERO": "MAD",
  "TERRACOTA": "TER",
  "MIX": "MIX",
  "LOLITA": "LOL",
  "AMO LA MODA": "ALM",
  "VIA BARCELONA": "BAR"
};
const ORDEN_SUC = Object.keys(MAPA_SUC);
const ABREV_TO_FULL = Object.fromEntries(Object.entries(MAPA_SUC).map(([full, ab]) => [ab, full]));

// ====== Utils ======
function fmtPesos(n){
  return " " + Math.round(n || 0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}
function fechaCorta(iso){
  if(!iso) return "";
  const parts = String(iso).split("-");
  if(parts.length === 3){
    const [y,m,d] = parts;
    return `${d}-${m}`;
  }
  return String(iso);
}
function sumaCredYOtros(cred, deb, trans, transQR, fin){
  return (cred||0) + (deb||0) + (trans||0) + (transQR||0) + (fin||0);
}
// Convierte cualquier variante de número (ej. "253.000", "253000", 253000)
function num(obj, ...keys) {
  for (const k of keys) {
    if (obj[k] !== undefined && obj[k] !== null && obj[k] !== "") {
      const v = parseFloat(String(obj[k]).replace(/\./g,"").replace(/,/g,"."));
      return isNaN(v) ? 0 : v;
    }
  }
  return 0;
}

// ====== Helpers DOM ======
function el(tag, text, styles){
  const e = document.createElement(tag);
  if (text !== undefined && text !== null) e.textContent = text;
  if (styles) Object.assign(e.style, styles);
  return e;
}

function filaPrincipal({abrev, ventaTotal, efectivo, gastos, efectivoRendido, vtaTar, chicas, acumMes}){
  const tr = document.createElement("tr");

  tr.appendChild(el("td", abrev, {textAlign:"center", fontWeight:"700"}));
  tr.appendChild(el("td", fmtPesos(ventaTotal), {textAlign:"right", fontWeight:"700"}));
  tr.appendChild(el("td", fmtPesos(efectivo), {textAlign:"right", fontWeight:"700"}));
  tr.appendChild(el("td", fmtPesos(gastos), {textAlign:"right", fontWeight:"700"}));
  tr.appendChild(el("td", fmtPesos(efectivoRendido), {textAlign:"right", fontWeight:"700"}));
  tr.appendChild(el("td", fmtPesos(vtaTar), {textAlign:"right", fontWeight:"700"}));
  tr.appendChild(el("td", (chicas||"").replace(/\s*\+\s*/g,"+").toUpperCase(), {textAlign:"center", fontWeight:"700"}));
  tr.appendChild(el("td", fmtPesos(acumMes), {textAlign:"right", fontWeight:"700"}));

  return tr;
}

function filaTotalTurno({ventaTotal, efectivo, gastos, efectivoRendido, vtaTar, acumMes}){
  const tr = document.createElement("tr");
  tr.appendChild(el("td", "TOTAL", {textAlign:"center", fontWeight:"800"}));
  tr.appendChild(el("td", fmtPesos(ventaTotal), {textAlign:"right", fontWeight:"800"}));
  tr.appendChild(el("td", fmtPesos(efectivo), {textAlign:"right", fontWeight:"800"}));
  tr.appendChild(el("td", fmtPesos(gastos), {textAlign:"right", fontWeight:"800"}));
  tr.appendChild(el("td", fmtPesos(efectivoRendido), {textAlign:"right", fontWeight:"800"}));
  tr.appendChild(el("td", fmtPesos(vtaTar), {textAlign:"right", fontWeight:"800"}));
  tr.appendChild(el("td", "-", {textAlign:"center", fontWeight:"800"}));
  tr.appendChild(el("td", fmtPesos(acumMes), {textAlign:"right", fontWeight:"800"}));
  return tr;
}

// ====== Render por turno ======
function renderTurno({datosTurno, tbodyId, tfootId, mapAcumMensual}){
  const tb = document.getElementById(tbodyId);
  const tf = document.getElementById(tfootId);
  tb.innerHTML = "";
  if (tf) tf.innerHTML = "";

  const tot = { ventaTotal:0, efectivo:0, gastos:0, efectivoRendido:0, vtaTar:0, acumMes:0 };

  // el acumulado mensual es por sucursal (mismo para M y T), el total lo mostramos igual en ambas tablas
  ORDEN_SUC.forEach(full => {
    const ab = MAPA_SUC[full];
    const d  = datosTurno[full] || { efe:0, tar:0, gastos:0, rend:0, chicas:"" };

    const acum = mapAcumMensual[full] || 0;
    const ventaTotal = (d.efe||0) + (d.tar||0);

    tot.ventaTotal      += ventaTotal;
    tot.efectivo        += (d.efe||0);
    tot.gastos          += (d.gastos||0);
    tot.efectivoRendido += (d.rend||0);
    tot.vtaTar          += (d.tar||0);
    tot.acumMes         += acum;

    tb.appendChild(filaPrincipal({
      abrev: ab,
      ventaTotal,
      efectivo: d.efe||0,
      gastos: d.gastos||0,
      efectivoRendido: d.rend||0,
      vtaTar: d.tar||0,
      chicas: d.chicas||"",
      acumMes: acum
    }));
  });

  const totalRow = filaTotalTurno(tot);
  if (tf) tf.appendChild(totalRow); else tb.appendChild(totalRow);

  return tot.ventaTotal;
}

// ====== Consulta principal ======
document.getElementById("consultarVentas").addEventListener("click", async function(){
  const fecha = document.getElementById("fecha").value;

  // Cartel de fecha para TURNO TARDE
  const badge = document.getElementById("badgeFechaTarde");
  if (badge) badge.textContent = "FECHA : " + fechaCorta(fecha);

  const resp = await fetch("consultasinformes.php", {
    method: "POST",
    headers: {"Content-Type": "application/x-www-form-urlencoded"},
    body: "fecha=" + encodeURIComponent(fecha)
  });

  let data;
  try { data = await resp.json(); }
  catch(e){
    console.error("Respuesta no-JSON (¿PHP con error?)", e);
    return;
  }

  console.log("consultasinformes →", data);

  const man = {};      // datos por suc (mañana)
  const tar = {};      // datos por suc (tarde)
  const mapAcum = {};  // acumulado mensual por suc

  // Si la API responde con {mensaje:"..."} lo tomamos como sin datos
  if (!Array.isArray(data)) {
    const totM = renderTurno({datosTurno: man, tbodyId:"tablaManana", tfootId:"tfootManana", mapAcumMensual: mapAcum});
    const totT = renderTurno({datosTurno: tar, tbodyId:"tablaTarde",  tfootId:"tfootTarde",  mapAcumMensual: mapAcum});
    document.getElementById("inputTotalManana").value = fmtPesos(totM);
    document.getElementById("inputTotalTarde").value  = fmtPesos(totT);
    document.getElementById("inputTotalDiario").value = fmtPesos(totM + totT);
    return;
  }

  // ===== Parseo robusto =====
  data.forEach(r => {
    // sucursal: admite r.sucursal o r.Sucursal; puede venir abreviada
    let suc = (r.sucursal ?? r.Sucursal ?? "").toString().trim().toUpperCase();
    if (!(suc in MAPA_SUC)) {
      if (suc in ABREV_TO_FULL) suc = ABREV_TO_FULL[suc];
      else return; // si no está mapeada, ignoramos
    }

    // turno: admite "M"/"T", "MANANA"/"TARDE", "Mañana"/"Tarde"
    let tur = (r.turno ?? r.Turno ?? "").toString().trim().toUpperCase();
    tur = tur.startsWith("M") ? "M" : tur.startsWith("T") ? "T" : "M";

    // montos (acepta distintas claves y formatos)
    const efe  = num(r, "Total_efectivo", "total_efectivo", "Efectivo");
    const cred = num(r, "Total_tarjeta_credito", "Total_tarjetas_credito", "Credito", "Crédito");
    const deb  = num(r, "Total_tarjeta_debito", "Total_tarjetas_debito", "Debito", "Débito");
    const tr   = num(r, "Total_transferencias", "Transferencias");
    const trQR = num(r, "Total_transferenciasQR", "TransferenciasQR", "TransfQR");
    const fin  = num(r, "Total_financieras", "Financieras");

    const gastos = num(r, "Total_gastos", "total_gastos", "Gastos");
    const rend   = num(r, "Efectivo_rendido", "efectivo_rendido", "Efe_rend", "EfeRend");

    const chicas = (r.Chicas ?? r.chicas ?? "").toString();

    const pack = {
      efe,
      tar: sumaCredYOtros(cred, deb, tr, trQR, fin),
      gastos,
      rend,
      chicas
    };

    if (tur === "M") man[suc] = pack; else tar[suc] = pack;

    // acumulado mensual (si viene)
    if ("acumulado_mensual" in r || "acumuladoMes" in r || "TotalMes" in r) {
      mapAcum[suc] = num(r, "acumulado_mensual", "acumuladoMes", "TotalMes");
    }
  });

  const totM = renderTurno({datosTurno: man, tbodyId:"tablaManana", tfootId:"tfootManana", mapAcumMensual: mapAcum});
  const totT = renderTurno({datosTurno: tar, tbodyId:"tablaTarde",  tfootId:"tfootTarde",  mapAcumMensual: mapAcum});

  document.getElementById("inputTotalManana").value = fmtPesos(totM);
  document.getElementById("inputTotalTarde").value  = fmtPesos(totT);
  document.getElementById("inputTotalDiario").value = fmtPesos(totM + totT);
});

// Auto-consulta
document.addEventListener("DOMContentLoaded", () => {
  document.getElementById("consultarVentas").click();
});
function consultarInformeMensual() {
    const mesSeleccionado = document.getElementById("mesSeleccionado").value;
    if (mesSeleccionado === "") {
        alert("Por favor selecciona un mes.");
        return;
    }

    fetch("consultar_informe_mensual.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "mes=" + mesSeleccionado,
    })
    .then((response) => response.json())
    .then((data) => {
        const tabla = document.getElementById("tablaInformeMensual").getElementsByTagName("tbody")[0];
        tabla.innerHTML = ""; // Limpiar tabla

        let totales = {
            efectivo: 0,
            credito: 0,
            debito: 0,
            financiera: 0,
            transferenciaTotal: 0,
        };

        data.forEach((fila) => {
            const row = tabla.insertRow();

            const celdaSucursal = row.insertCell();
            celdaSucursal.textContent = fila.Sucursal;
            celdaSucursal.style.backgroundColor = "#fff";
            celdaSucursal.style.fontWeight = "bold";

            // Parsear valores
            const efectivo = parseFloat(fila.total_efectivo);
            const credito = parseFloat(fila.total_tarjeta_credito);
            const debito = parseFloat(fila.total_tarjeta_debito);
            const financiera = parseFloat(fila.total_financiera);
            const transferencia = parseFloat(fila.total_transferencia);
            const transferenciaQR = parseFloat(fila.total_transferenciaQR);
            const transferenciaTotal = transferencia + transferenciaQR;

            // Insertar celdas por tipo
            const valores = [efectivo, credito, debito, transferenciaTotal, financiera];

            valores.forEach((valor) => {
                const celda = row.insertCell();
                celda.textContent = valor.toLocaleString("es-AR", {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
                celda.style.textAlign = "right";
                celda.style.backgroundColor = "#fff";
                celda.style.fontWeight = "bold";
            });

            // Total por fila
            const totalVentas = efectivo + credito + debito + financiera + transferenciaTotal;
            const celdaTotal = row.insertCell();
            celdaTotal.textContent = totalVentas.toLocaleString("es-AR", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
            celdaTotal.style.textAlign = "right";
            celdaTotal.style.backgroundColor = "#fff";
            celdaTotal.style.fontWeight = "bold";

            // Acumular totales
            totales.efectivo += efectivo;
            totales.credito += credito;
            totales.debito += debito;
            totales.financiera += financiera;
            totales.transferenciaTotal += transferenciaTotal;
        });

        // Fila de totales generales
        const filaTotales = tabla.insertRow();
        filaTotales.style.backgroundColor = "#e0e0e0";
        filaTotales.style.fontWeight = "bold";

        const celdaTitulo = filaTotales.insertCell();
        celdaTitulo.textContent = "Total General";

        const valoresTotales = [
            totales.efectivo,
            totales.credito,
            totales.debito,
            totales.transferenciaTotal,
            totales.financiera,
        ];

        valoresTotales.forEach((valor) => {
            const celda = filaTotales.insertCell();
            celda.textContent = valor.toLocaleString("es-AR", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
            celda.style.textAlign = "right";
        });

        const totalGeneral = valoresTotales.reduce((acc, val) => acc + val, 0);
        const celdaTotalGeneral = filaTotales.insertCell();
        celdaTotalGeneral.textContent = totalGeneral.toLocaleString("es-AR", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
        celdaTotalGeneral.style.textAlign = "right";
    })
    .catch(async (error) => {
        try {
            const response = await fetch("consultar_informe_mensual.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "mes=" + document.getElementById("mesSeleccionado").value,
            });
            const text = await response.text();
            console.error("Respuesta completa del servidor:");
            console.log(text);
        } catch (e) {
            console.error("Error capturando la respuesta del servidor:", e);
        }
    });
}

function consultarInformeSucursal() {
    const fechaDesde = document.getElementById('fechaDesde').value;
    const fechaHasta = document.getElementById('fechaHasta').value;
    const mensaje = document.getElementById('mensajeSucursal');
    const tabla = document.getElementById('tablaSucursal');
    const tbody = tabla.querySelector('tbody');

    // obtener múltiples sucursales seleccionadas
    const sucursales = obtenerSucursalesSeleccionadas();

    mensaje.innerHTML = '';
    tabla.style.display = 'none';
    tbody.innerHTML = '';

    if (!fechaDesde || !fechaHasta) {
        mensaje.innerHTML = '<div class="alert alert-warning">Por favor, selecciona ambas fechas.</div>';
        return;
    }

    fetch('consultar_informe_sucursal.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `fechaDesde=${fechaDesde}&fechaHasta=${fechaHasta}&sucursales=${encodeURIComponent(sucursales.join(','))}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.error) {
            mensaje.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
            return;
        }

        if (data.length === 0) {
            mensaje.innerHTML = '<div class="alert alert-info">No se encontraron resultados.</div>';
            return;
        }

     const tbody = document.querySelector('#tablaSucursal tbody');
tbody.innerHTML = '';

let totalGeneral = 0;

data.forEach(item => {
    const fila = document.createElement('tr');

    const celdaSucursal = document.createElement('td');
    celdaSucursal.textContent = item.Sucursal;
    celdaSucursal.style.backgroundColor = '#fff';
    celdaSucursal.style.fontWeight = 'bold';
    celdaSucursal.style.textAlign = 'left';
    fila.appendChild(celdaSucursal);

    const totales = [
        parseFloat(item.total_efectivo),
        parseFloat(item.total_tarjeta_credito),
        parseFloat(item.total_tarjeta_debito),
        parseFloat(item.total_financiera),
        parseFloat(item.total_transferencia),
        parseFloat(item.total_transferenciaQR)
    ];

    let totalVentas = 0;

    totales.forEach(valor => {
        const celda = document.createElement('td');
        celda.textContent = valor.toLocaleString('es-AR');
        celda.style.backgroundColor = '#fff';
        celda.style.fontWeight = 'bold';
        celda.style.textAlign = 'right';
        fila.appendChild(celda);
        totalVentas += valor;
    });

    // Agregar columna Total Ventas
    const celdaTotal = document.createElement('td');
    celdaTotal.textContent = totalVentas.toLocaleString('es-AR');
    celdaTotal.style.backgroundColor = '#fff';
    celdaTotal.style.fontWeight = 'bold';
    celdaTotal.style.textAlign = 'right';
    fila.appendChild(celdaTotal);

    tbody.appendChild(fila);

    // Acumular al total general
    totalGeneral += totalVentas;
});

// Mostrar total general en el input
document.getElementById('inputTotalSucursales').value = totalGeneral.toLocaleString('es-AR');




        tabla.style.display = 'table';
    })
    .catch(error => {
        console.error('Error al consultar informe por sucursal:', error);
        mensaje.innerHTML = '<div class="alert alert-danger">Error al obtener los datos.</div>';
    });
}
function toggleDropdown() {
    const dropdown = document.getElementById("sucursalDropdown");
    dropdown.classList.toggle("open");
}

function toggleTodas(checkbox) {
    const checkboxes = document.querySelectorAll(".sucursalCheckbox");
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
}

function obtenerSucursalesSeleccionadas() {
    const checkboxes = document.querySelectorAll(".sucursalCheckbox:checked");
    return Array.from(checkboxes).map(cb => cb.value);
}
function toggleDropdown() {
    const dropdown = document.getElementById('sucursalDropdown');
    dropdown.classList.toggle('open');
}

function toggleTodas(checkbox) {
    const todos = document.querySelectorAll('.sucursalCheckbox');
    todos.forEach(cb => cb.checked = checkbox.checked);
    actualizarTextoSucursales(); // actualizar texto cuando se tilda "todas"
}

function actualizarTextoSucursales() {
    const seleccionadas = Array.from(document.querySelectorAll('.sucursalCheckbox:checked'))
        .map(cb => cb.value);
    
    const texto = seleccionadas.length > 0
        ? `Sucursales seleccionadas: ${seleccionadas.join(', ')}`
        : 'Ninguna sucursal seleccionada';

    document.getElementById('sucursalesSeleccionadas').textContent = texto;
}

document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('sucursalDropdown');
    if (!dropdown.contains(event.target)) {
        dropdown.classList.remove('open');
        actualizarTextoSucursales(); // actualizar al cerrar también
    }
});

// Actualizar texto cada vez que se hace click en un checkbox
document.querySelectorAll('.sucursalCheckbox').forEach(cb => {
    cb.addEventListener('change', actualizarTextoSucursales);
});
document.getElementById('checkTodas').addEventListener('change', actualizarTextoSucursales);


</script>
</body>
</html>



