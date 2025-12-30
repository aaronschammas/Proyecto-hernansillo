// hoy =====

document.addEventListener("DOMContentLoaded", function () {

  document.getElementById("btnGuardar").addEventListener("click", function(event) {
    event.preventDefault();

    // ===== 0) FOTO precalculada en PHP =====
    const dupExiste = (document.getElementById("dupExiste")?.value || "").toUpperCase();
    console.log("[DEBUG] dupExiste =", dupExiste);

    // ===== 1) Armar datos (igual que antes) =====
    let datos = {};

    // Recorrer todos los formularios y extraer sus datos
    document.querySelectorAll("form").forEach(form => {
      new FormData(form).forEach((valor, clave) => {
        datos[clave] = valor;
      });
    });

    // Capturar datos de las tablas editables
    datos["chicas"]   = obtenerDatosTabla("tablaChicas");
    datos["gastos"]   = obtenerDatosTablaGastos("tablaDeGastos");
    datos["tarjetas"] = obtenerDatosTablaTarjetas("tablaTarjetasAgregadas");

    // Capturar denominaciones de billetes
    datos["billetes"] = obtenerDatosBilletes();

    // ---- Fecha (normalizada a ISO YYYY-MM-DD) ----
    let fechaRaw = document.getElementById("inputFecha").value; // puede venir dd-mm-yyyy o dd/mm/yyyy
    let partes   = fechaRaw.includes("-") ? fechaRaw.split("-") : fechaRaw.split("/");
    // partes: [dd, mm, yyyy]
    let dd  = (partes[0] || "").padStart(2, "0");
    let mm  = (partes[1] || "").padStart(2, "0");
    let y4  = (partes[2] || "").padStart(4, "0");
    let y2  = y4.slice(-2);

    // ---- Sucursal (nombre) y turno ----
    let nombreSucursal   = document.getElementById("inputSucursal").value.trim();  // *** NOMBRE ***
    let sucursalAbrev    = nombreSucursal.substring(0, 3).toUpperCase();
    let turno            = document.getElementById("inputTurno").value;
    let usuario          = document.getElementById("inputUsuario").value;

    // Usuario / Sucursal para backend
    datos["Usuario"]   = usuario;            // tu backend acepta Usuario/usuario
    datos["sucursal"]  = nombreSucursal;     // *** que coincida con lo guardado en DB ***
    datos["turno"]     = turno;
    datos["fecha"]     = `${y4}-${mm}-${dd}`; // ISO para procesar.php (sobrescritura)

    // Id base (como antes)
    datos["Id_cierre_base"] = y2 + mm + dd + sucursalAbrev + turno;

    // *** Debug útil ***
    console.log("Datos que se enviarán a procesar.php:", JSON.stringify(datos, null, 2));

    // ===== 2) Decidir según duplicado =====
    if (dupExiste === "SI") {
      const ok = confirm(
        "Ya existe un cierre para esta sucursal, fecha, turno y usuario.\n\n¿Querés SOBRESCRIBIR ese cierre con lo que cargaste ahora?"
      );
      if (!ok) {
        console.log("[DEBUG] Usuario canceló sobrescritura.");
        return; // no guardar
      }
      datos["accion"] = "sobrescribir";  // <-- CLAVE para que procesar.php borre y re-use el mismo Id_Cierre
    } else {
      datos["accion"] = "";              // flujo normal
    }

    // ===== 3) Enviar al servidor =====
    fetch("procesar.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(datos)
    })
    .then(response => response.json())
    .then(data => {
      console.log("Respuesta del servidor:", data);

      if (data && data.Id_Cierre) {
        alert(data.success || "Datos guardados correctamente");

        // Setear el ID en el campo para impresión
        const idInput = document.getElementById("inputIdCierreGenerado");
        if (idInput) idInput.value = data.Id_Cierre;

        // Abrir la ventana de impresión
        if (typeof abrirVentanaParaImprimir === "function") {
          abrirVentanaParaImprimir();
        } else {
          // alternativa por si usás un resumen server-side
          window.open(`resumenCierre.php?Id_cierre=${encodeURIComponent(data.Id_Cierre)}`, "_blank");
        }

        // Esperar y recargar limpio
        setTimeout(() => {
          location.reload();
        }, 2000);
      } else {
        alert("Error: No se recibió un Id_Cierre válido.");
      }
    })
    .catch(error => {
      console.error("Error al enviar los datos:", error);
      alert("Hubo un error al enviar los datos al servidor.");
    });
  });

  // ==== Helpers existentes ====

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
      let concepto = tr.cells[0].textContent.trim();
      let monto    = tr.cells[1].textContent.trim();
      if (concepto && monto) datos.push({ concepto, monto });
    });
    return datos;
  }

  // Función para obtener los datos de la tabla de "Tarjetas Agregadas"
  function obtenerDatosTablaTarjetas(idTabla) {
    let datos = [];
    document.querySelectorAll(`#${idTabla} tbody tr`).forEach(tr => {
      let formaPago = tr.cells[0].textContent.trim();
      let tarjeta   = tr.cells[1].textContent.trim();
      let monto     = tr.cells[2].textContent.trim();
      let lote      = tr.cells[3].textContent.trim();
      let cupon     = tr.cells[4].textContent.trim();

      // Normalizar monto a formato "1234.56"
      monto = monto.replace(/\./g, '').replace(',', '.');

      if (formaPago && tarjeta && monto) {
        datos.push({ formaPago, tarjeta, monto, lote, cupon });
      }
    });
    return datos;
  }

  // Función para obtener los datos de los billetes y sus cantidades
  function obtenerDatosBilletes() {
    let billetes = [];
    for (let i = 1; i <= 7; i++) {
      let denominacion = document.getElementById(`denominacionBillete${i}`).textContent.trim();
      let cantidad     = document.getElementById(`cantidadBillete${i}`).value.trim();
      billetes.push({ denominacion, cantidad: cantidad ? parseInt(cantidad) : 0 });
    }
    return billetes;
  }

});

