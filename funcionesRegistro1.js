// 155724
function formatearTotalEfectivo() {
    var inputSinFormato = document.getElementById("inputTotalEfectivo").value;
    var inputFormateado = inputSinFormato.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    document.getElementById("inputTotalEfectivo").value = inputFormateado;
    calcularTotalVentas();
    actualizarTotalEfectivoRendido();
}

function actualizarTotales() {
    if (document.querySelectorAll(".table-bordered tbody tr").length === 0) {
        document.getElementById("inputTotalCredito").value = "0";
        document.getElementById("inputTotalDebito").value = "0";
        document.getElementById("inputTotalTransferencias").value = "0";
        document.getElementById("inputTotalTransferenciasQR").value = "0";
        document.getElementById("inputTotalFinancieras").value = "0";
        calcularTotalVentas();
        return;
    }

    let totalCredito = 0, totalDebito = 0, totalTransferencias = 0, totalTransferenciasQR = 0, totalFinancieras = 0;
    const filas = document.querySelectorAll(".table-bordered tbody tr");

    filas.forEach(fila => {
        const celdas = fila.querySelectorAll("td");
        const formaPago = celdas[0].innerText;
        const monto = parseFloat(celdas[2].innerText.replace(/\./g, '').replace(',', '.')) || 0;

        switch (formaPago) {
            case "Tarjeta Crédito": totalCredito += monto; break;
            case "Tarjeta Débito": totalDebito += monto; break;
            case "Transferencia": totalTransferencias += monto; break;
            case "Transferencia QR": totalTransferenciasQR += monto; break;
            case "Financiera": totalFinancieras += monto; break;
        }
    });

    function formatearMonto(valor) {
        return valor.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    document.getElementById("inputTotalCredito").value = formatearMonto(totalCredito);
    document.getElementById("inputTotalDebito").value = formatearMonto(totalDebito);
    document.getElementById("inputTotalTransferencias").value = formatearMonto(totalTransferencias);
    document.getElementById("inputTotalTransferenciasQR").value = formatearMonto(totalTransferenciasQR);
    document.getElementById("inputTotalFinancieras").value = formatearMonto(totalFinancieras);

    calcularTotalVentas();
}

function calcularTotalVentas() {
    const get = id => parseFloat(document.getElementById(id).value.replace(/\./g, '').replace(',', '.')) || 0;
    const total = get("inputTotalEfectivo") + get("inputTotalCredito") + get("inputTotalDebito") + get("inputTotalFinancieras") + get("inputTotalTransferencias") + get("inputTotalTransferenciasQR");
    document.getElementById("inputTotalVentas").value = total.toLocaleString(undefined, { minimumFractionDigits: 0, useGrouping: true });
}

function agregarFila() {
    const formaPago = document.getElementById("selectFormasDePago").value;
    const tarjeta = document.getElementById("selectTarjetas").value;
    const monto = parseFloat(document.getElementById("montoTarjeta").value.replace(/\./g, '').replace(',', '.')) || 0;

    function formatearMonto(valor) {
        return valor.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    if (!formaPago || !tarjeta || monto === 0) {
        alert("Por favor, completa todos los campos antes de agregar una fila.");
        return;
    }

    const cuerpoTabla = document.querySelector(".table-bordered tbody");
    const nuevaFila = document.createElement("tr");
    nuevaFila.innerHTML = `
        <td>${formaPago}</td>
        <td>${tarjeta}</td>
        <td style="text-align: right;">${formatearMonto(monto)}</td>
        <td contenteditable="true" style="text-align: center;">0</td>
        <td contenteditable="true" style="text-align: center;">0</td>
        <td class="text-center">
            <button class="btn btn-danger btn-sm" onclick="borrarFila(this); actualizarTotales();">Borrar</button>
        </td>
    `;
    cuerpoTabla.appendChild(nuevaFila);

    document.getElementById("selectFormasDePago").value = "";
    document.getElementById("selectTarjetas").innerHTML = "<option value=''>Seleccionar</option>";
    document.getElementById("montoTarjeta").value = "";

    actualizarTotales();
    document.getElementById("selectFormasDePago").focus();
}

function borrarFila(boton) {
    boton.closest("tr").remove();
    actualizarTotales();
}

function actualizarTotalGastos() {
    let totalGastos = 0;
    const celdasMonto = document.querySelectorAll("#tablaDeGastos tbody td:nth-child(2)");
    celdasMonto.forEach(celda => {
        let monto = parseFloat(celda.innerText.replace(/\./g, '').replace(',', '.')) || 0;
        totalGastos += monto;
        if (monto !== 0) {
            celda.innerText = monto.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }
    });
    document.getElementById("inputTotalGastos").value = totalGastos.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    calcularDiferenciaCaja();
}

function actualizarTotalEfectivoRendido() {
    let total = 0;
    document.querySelectorAll("#tablaBilletes tbody tr").forEach(fila => {
        const denominacion = parseFloat(fila.cells[0].innerText.replace(/\./g, '').replace(',', '.')) || 0;
        const cantidad = parseFloat(fila.cells[1].querySelector("input").value) || 0;
        total += denominacion * cantidad;
    });
    document.getElementById("inputEfectivoRendido").value = total.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    calcularDiferenciaCaja();
}

function calcularDiferenciaCaja() {
    const get = id => parseFloat(document.getElementById(id).value.replace(/\./g, '').replace(',', '.')) || 0;
    const diferencia = get("inputEfectivoRendido") - (get("inputTotalEfectivo") - get("inputTotalGastos"));
    const input = document.getElementById("inputDiferenciaCaja");
    input.value = diferencia.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ".");

    if (diferencia < 0) {
        input.style.backgroundColor = "#f8d7da";
        input.style.color = "#721c24";
    } else if (diferencia > 0) {
        input.style.backgroundColor = "#fff3cd";
        input.style.color = "#856404";
    } else {
        input.style.backgroundColor = "#d4edda";
        input.style.color = "#155724";
    }
}

function recargarPagina() {
    location.reload();
}

function enterEnEfeVtas(event) {
    if (event.key === "Enter") {
        event.preventDefault();
        formatearTotalEfectivo();
        calcularDiferenciaCaja();
        const select = document.getElementById("selectFormasDePago");
        if (select) select.focus();
    }
}

function enterEnMontoTarjeta(event) {
    if (event.key === "Enter") {
        event.preventDefault();
        agregarFila();
        actualizarTotales();
        calcularTotalVentas();
        calcularDiferenciaCaja();
    }
}

function enterEnBillete(event) {
    if (event.key === "Enter") {
        event.preventDefault();
        actualizarTotalEfectivoRendido();
        calcularDiferenciaCaja();
        const inputs = Array.from(document.querySelectorAll("input"));
        const i = inputs.indexOf(event.target);
        if (i >= 0 && i < inputs.length - 1) {
            inputs[i + 1].focus();
        }
    }
}

function enterEnGasto(event) {
    if (event.key === "Enter") {
        event.preventDefault();
        actualizarTotalGastos();
        calcularDiferenciaCaja();
        const inputs = Array.from(document.querySelectorAll("input"));
        const i = inputs.indexOf(event.target);
        if (i >= 0 && i < inputs.length - 1) {
            inputs[i + 1].focus();
        }
    }
}

function abrirVentanaParaImprimir() {
    console.log("🔍 Se ejecutó abrirVentanaParaImprimir()");
    const idCierre = document.getElementById('inputIdCierreGenerado').value;
    const fecha = document.getElementById('inputFecha').value;
    const turno = document.getElementById('inputTurno').value;
    const usuario = document.getElementById('inputUsuario').value;
    const sucursal = document.getElementById('inputSucursal').value;
    const hora = new Date().toLocaleTimeString();
    const efectivo = document.getElementById('inputTotalEfectivo').value;
    const debito = document.getElementById('inputTotalDebito').value;
    const credito = document.getElementById('inputTotalCredito').value;
    const financieras = document.getElementById('inputTotalFinancieras').value;
    const transfer = document.getElementById('inputTotalTransferencias').value;
    const transferQR = document.getElementById('inputTotalTransferenciasQR').value;
    const total = document.getElementById('inputTotalVentas').value;
    const gastos = document.getElementById('inputTotalGastos').value;
    const rendido = document.getElementById('inputEfectivoRendido').value;
    const diferencia = document.getElementById('inputDiferenciaCaja').value;
    const comentario = document.getElementById('txtComentario').value;

    const chicas = Array.from(document.querySelectorAll('#tablaChicas tbody tr td'))
                        .map(td => td.textContent.trim())
                        .filter(nombre => nombre !== "");

    const filasGastos = document.querySelectorAll('#tablaDeGastos tbody tr');
    let detalleGastos = [];
    filasGastos.forEach(fila => {
        const concepto = fila.cells[0].textContent.trim();
        const monto = fila.cells[1].textContent.trim();
        if (concepto && monto) {
            detalleGastos.push(`${concepto.padEnd(15)} $ ${monto}`);
        }
    });

    const filasBilletes = document.querySelectorAll('#tablaBilletes tbody tr');
    let detalleBilletes = [];
    filasBilletes.forEach(fila => {
        const denominacion = fila.cells[0].textContent.trim();
        const cantidad = fila.cells[1].querySelector('input').value.trim();
        if (cantidad && cantidad !== "0") {
            detalleBilletes.push(`${denominacion.padEnd(10)} x ${cantidad}`);
        }
    });

    const contenidoVentana = `
<html>
<head>
    <title>Resumen de Cierre</title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
</head>
<body>
<pre>
Resumen de Cierre - ${sucursal}

<div style="text-align: left; margin: 10px 0;">
    <svg id="codigoBarras"></svg>
</div>

Fecha: ${fecha} 
Turno: ${turno} 
Hora: ${hora}
Usuario Caja: ${usuario}

VENDEDORAS:
${chicas.map(nombre => "    " + nombre).join("\n")}

TOTAL EFECTIVO:       $ ${efectivo}
TARJETA DÉBITO:       $ ${debito}
TARJETA CRÉDITO:      $ ${credito}
FINANCIERAS:          $ ${financieras}
TRANSFERENCIAS:       $ ${transfer}
TRANSFERENCIAS QR:    $ ${transferQR}
------------------------------
TOTAL VENTAS:         $ ${total}

GASTOS:
${detalleGastos.length > 0 ? detalleGastos.map(g => "    " + g).join("\n") : "    Sin gastos"}

EFECTIVO RENDIDO:     $ ${rendido}
DIF. DE CAJA:         $ ${diferencia}

BILLETES:
${detalleBilletes.map(b => "    " + b).join("\n")}

COMENTARIO:
${comentario}
</pre>

<script>
    // Generar el código de barras cuando la ventana se carga
    window.onload = function() {
        JsBarcode("#codigoBarras", "${idCierre}", {
            format: "CODE128",
            lineColor: "#000",
            width: 2,
            height: 60,
            displayValue: true,
            fontSize: 14
        });
    };
</script>

</body>
</html>
`;

    const ventana = window.open("", "_blank", "width=600,height=800");
    ventana.document.open();
    ventana.document.write(contenidoVentana);
    ventana.document.close();
}

function collectAndSubmit(event) {
  event.preventDefault();

  // ---- 1) Armar "datos" como ya hacías ----
  let datos = {};

  document.querySelectorAll("form").forEach(form => {
    new FormData(form).forEach((valor, clave) => {
      datos[clave] = valor;
    });
  });

  // Campos sueltos
  const usuario = document.getElementById("inputUsuario")?.value || "";
  datos.usuario = usuario;
  datos.comentarios = (document.getElementById("txtComentario")?.value || "").trim();

  // Tablas
  datos.chicas   = (typeof obtenerDatosTabla === "function") ? obtenerDatosTabla("tablaChicas") : [];
  datos.gastos   = (typeof obtenerDatosTablaGastos === "function") ? obtenerDatosTablaGastos("tablaDeGastos") : [];
  datos.tarjetas = (typeof obtenerDatosTablaTarjetas === "function") ? obtenerDatosTablaTarjetas("tablaTarjetasAgregadas") : [];
  datos.billetes = (typeof obtenerDatosBilletes === "function") ? obtenerDatosBilletes() : [];

  // Identificadores visibles/ocultos
  datos.Id_Cierre = document.getElementById("inputIdCierreGenerado")?.value || "";

  // Muy importante para el backend (para sobrescribir por combo):
  datos.sucursal = document.getElementById("inputSucursal")?.value?.trim() || "";
  datos.fecha    = document.getElementById("inputFecha")?.value || "";    // YYYY-MM-DD
  datos.turno    = document.getElementById("inputTurno")?.value || "";    // M/T

  // ---- 2) Leer la "foto" tomada en PHP al cargar ----
  const dupExiste = (document.getElementById("dupExiste")?.value || "").toUpperCase();

  // ---- 3) Si NO existe → guardar normal; si SI → abrir modal y decidir ----
  if (dupExiste === "NO") {
    // Guardado normal
    datos.accion = "";  // sin sobrescribir
    return guardarCierre(datos);
  }

  // Hay duplicado → mostrar modal de confirmación
  const modalEl = document.getElementById("modalDuplicado");
  const modal   = new bootstrap.Modal(modalEl);

  // Para evitar duplicar listeners si el usuario toca Guardar más de una vez
  const btnCancelar = document.getElementById("btnCancelarDup");
  const btnOver     = document.getElementById("btnSobrescribirDup");

  // Clonar botones para limpiar handlers previos
  const btnCancelarClone = btnCancelar.cloneNode(true);
  const btnOverClone     = btnOver.cloneNode(true);
  btnCancelar.parentNode.replaceChild(btnCancelarClone, btnCancelar);
  btnOver.parentNode.replaceChild(btnOverClone, btnOver);

  // Conectar handlers "limpios"
  document.getElementById("btnCancelarDup").addEventListener("click", () => {
    // No hacemos nada: el modal se cierra y no se guarda
  });

  document.getElementById("btnSobrescribirDup").addEventListener("click", async () => {
    modal.hide();
    datos.accion = "sobrescribir";   // <-- clave para el backend
    await guardarCierre(datos);
  });

  modal.show();
}

// ---- Helper para enviar al backend y manejar respuesta ----
async function guardarCierre(datos) {
  try {
    const resp = await fetch("procesar.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(datos)
    });

    const data = await resp.json().catch(() => ({}));

    if (!resp.ok || data.success == null) {
      alert("Error al guardar los datos. " + (data.error || resp.status));
      return;
    }

    alert(data.success || "Datos guardados correctamente");

    if (data.Id_Cierre) {
      // Usá tu impresora/ventana
      if (typeof abrirVentanaParaImprimir === "function") {
        abrirVentanaParaImprimir(data.Id_Cierre);
      } else {
        window.open(`resumenCierre.php?Id_cierre=${encodeURIComponent(data.Id_Cierre)}`, "_blank");
      }
    }

    setTimeout(() => {
      // Recarga suave para limpiar el formulario
      location.reload();
    }, 1000);

  } catch (err) {
    console.error("Error al enviar los datos:", err);
    alert("Hubo un error al enviar los datos al servidor.");
  }
}


// Función para obtener los datos de la tabla de "Chicas"
function obtenerDatosTabla(idTabla) {
    let datos = [];
    document.querySelectorAll(`#${idTabla} tbody tr td`).forEach(td => {
        let valor = td.textContent.trim();
        if (valor) datos.push(valor);
    });
    return datos;
}

// Función para obtener los datos de la tabla de "Gastos"
function obtenerDatosTablaGastos(idTabla) {
    let datos = [];
    document.querySelectorAll(`#${idTabla} tbody tr`).forEach(tr => {
        let concepto = tr.cells[0]?.textContent.trim() || "";
        let monto = tr.cells[1]?.textContent.trim() || "";
        if (concepto && monto) datos.push({ concepto, monto });
    });
    return datos;
}

// Función para obtener los datos de la tabla de "Tarjetas Agregadas"
function obtenerDatosTablaTarjetas(idTabla) {
    let datos = [];
    document.querySelectorAll(`#${idTabla} tbody tr`).forEach(tr => {
        let formaPago = tr.cells[0]?.textContent.trim() || "";
        let tarjeta = tr.cells[1]?.textContent.trim() || "";
        let monto = tr.cells[2]?.textContent.trim() || "";
        let lote = tr.cells[3]?.textContent.trim() || "";
        let cupon = tr.cells[4]?.textContent.trim() || "";
        if (formaPago && tarjeta && monto) {
            datos.push({ formaPago, tarjeta, monto, lote, cupon });
        }
    });
    return datos;
    

}

