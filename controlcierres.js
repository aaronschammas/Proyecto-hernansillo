// controlcierres.js
// (parche anti-impresión forzada)
try { if (typeof window !== 'undefined' && typeof window.print === 'function') {
  window.print = function(){ console.warn('window.print() bloqueado en esta vista'); };
}} catch(_) {}

// Maneja la consulta y despliegue de datos de un cierre

// ==================== CACHES GLOBALES ====================
let cacheFormasPago = [];
let cacheTarjetas   = {};   // { formaPago: [ ... ] }
let cacheIdCierre   = null;

// ==================== UTILIDADES ====================
const nfAR0 = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
function parseARNumber(v) {
  if (v == null) return 0;
  let s = String(v).trim();
  if (!s) return 0;
  s = s.replace(/\./g, '').replace(/,/g, '.').replace(/[^\d.-]/g, '');
  const n = Number(s);
  return Number.isFinite(n) ? n : 0;
}
function formatAR0(n) {
  const num = typeof n === 'number' ? n : parseARNumber(n);
  return nfAR0.format(Math.round(num));
}

// ==================== FUNCIONES DE RESUMEN (GLOBAL/USADAS DESDE HTML) ====================
// 1) Total Gastos (suma la 2° columna de la tabla de gastos)
function actualizarTotalGastos() {
  const tbody = document.querySelector('#tablaDeGastos tbody');
  const inp = document.getElementById('inputTotalGastos'); // Resumen > Total Gastos
  if (!tbody || !inp) return;
  let total = 0;
  [...tbody.rows].forEach(tr => {
    const tdMonto = tr.cells?.[1];
    if (!tdMonto) return;
    total += parseARNumber(tdMonto.textContent);
  });
  inp.value = formatAR0(total);
  actualizarResumen();
}
window.actualizarTotalGastos = actualizarTotalGastos;

// 2) Efectivo Rendido (denominación × cantidad)
function actualizarTotalEfectivoRendido() {
  const denoms = [20000,10000,2000,1000,500,200,100]; // mismos del HTML
  let total = 0;
  for (let i = 0; i < denoms.length; i++) {
    const qty = parseARNumber(document.getElementById(`cantidadBillete${i+1}`)?.value || 0);
    total += denoms[i] * qty;
  }
  const inp = document.getElementById('inputEfectivoRendido');
  if (inp) inp.value = formatAR0(total);
  actualizarResumen();
}
window.actualizarTotalEfectivoRendido = actualizarTotalEfectivoRendido;

// 3) Resumen: Saldo Caja Efectivo y Diferencia
function actualizarResumen() {
  const inpTotGastos = document.getElementById('inputTotalGastos');
  const inpEfeVentas = document.getElementById('inputTotalEfectivo'); // “Total Ventas Efectivo”
  const inpSaldo     = document.getElementById('inputSaldoEfectivo');
  const inpRendido   = document.getElementById('inputEfectivoRendido');
  const inpDif       = document.getElementById('inputDiferenciaCaja');

  const totalGastos   = parseARNumber(inpTotGastos?.value || 0);
  const ventasEfect   = parseARNumber(inpEfeVentas?.value || 0);
  const saldoCaja     = ventasEfect - totalGastos;
  const efectivoRend  = parseARNumber(inpRendido?.value || 0);
  const diferencia    = efectivoRend - saldoCaja;

  if (inpSaldo) inpSaldo.value = formatAR0(saldoCaja);
  if (inpDif)   inpDif.value   = formatAR0(diferencia);
}
window.actualizarResumen = actualizarResumen;

// (helper para los inputs de billetes, viene referenciado en el HTML)
function enterEnBillete(e) {
  if (e?.key !== 'Enter') return;
  e.preventDefault();
  const inputs = [...document.querySelectorAll('#tablaBilletes input[id^="cantidadBillete"]')];
  const idx = inputs.indexOf(e.target);
  const next = idx >= 0 ? inputs[idx + 1] : null;
  if (next) next.focus(); else e.target.blur();
}
window.enterEnBillete = enterEnBillete;

// ==================== FETCHERS (con cache) ====================
async function cargarFormasPago(idCierre) {
  if (cacheIdCierre === idCierre && cacheFormasPago.length) return cacheFormasPago;
  const fd = new FormData();
  fd.append('id_cierre', idCierre);
  const resp = await fetch('obtener_formaspago.php', { method: 'POST', body: fd });
  const json = await resp.json();
  cacheFormasPago = json.formas || [];
  cacheIdCierre   = idCierre;
  cacheTarjetas   = {};
  return cacheFormasPago;
}
async function cargarTarjetas(idCierre, formaPago) {
  if (cacheIdCierre === idCierre && cacheTarjetas[formaPago]) return cacheTarjetas[formaPago];
  const fd = new FormData();
  fd.append('id_cierre', idCierre);
  fd.append('forma_pago', formaPago);
  const resp = await fetch('obtener_tarjetas.php', { method: 'POST', body: fd });
  const json = await resp.json();
  cacheTarjetas[formaPago] = json.tarjetas || [];
  return cacheTarjetas[formaPago];
}

// ==================== INICIO ====================
document.addEventListener('DOMContentLoaded', () => {
  // -------- Total Ventas Efectivo: Editar/OK + formateo --------
  const chkEditarTotal = document.getElementById('editarTotalEfectivo');
  const chkOkTotal     = document.getElementById('OKTotalEfectivo');
  const inputTotalEfe  = document.getElementById('inputTotalEfectivo');

  // Recalcular “Total Ventas” (efectivo + tarjetas OK)
  function recalcTotalesTarjetas() {
    const tbody = document.querySelector('#tablaTarjetasAgregadas tbody');
    if (!tbody) return;

    const sums = { credito:0, debito:0, transferencias:0, qr:0, financieras:0 };
    const normalizaForma = (txt) => {
      const t = (txt||'').toLowerCase().normalize('NFD').replace(/\p{Diacritic}/gu,'').trim();
      if (t.includes('credito')) return 'credito';
      if (t.includes('debito')) return 'debito';
      if (t.includes('financier')) return 'financieras';
      if (t.includes('qr')) return 'qr';
      if (t.includes('transfer')) return t.includes('qr') ? 'qr' : 'transferencias';
      return null;
    };

    [...tbody.rows].forEach(tr => {
      const ok = tr.querySelector('input.ok-checkbox');
      if (!ok || !ok.checked) return;
      const formaCell = tr.cells[0];
      const montoCell = tr.cells[2];
      if (!formaCell || !montoCell) return;
      const key = normalizaForma(formaCell.textContent);
      if (!key) return;
      const monto = parseARNumber(montoCell.textContent || montoCell.querySelector('input')?.value);
      sums[key] += monto;
    });

    // Actualizar inputs por forma de pago
    const map = {
      credito:        document.getElementById('inputTotalCredito'),
      debito:         document.getElementById('inputTotalDebito'),
      transferencias: document.getElementById('inputTotalTransferencias'),
      qr:             document.getElementById('inputTotalTransferenciasQR'),
      financieras:    document.getElementById('inputTotalFinancieras')
    };
    Object.entries(map).forEach(([k, inp]) => {
      if (!inp) return;
      const total = sums[k] || 0;
      inp.value = total ? formatAR0(total) : '0';
      if (total > 0) {
        inp.classList.remove('input-provisorio');
        inp.classList.add('input-definitivo');
      } else {
        inp.classList.remove('input-definitivo');
        if (!inp.classList.contains('input-provisorio')) inp.classList.add('input-provisorio');
      }
    });

    // === TOTAL VENTAS = Efectivo + (tarjetas OK) ===
    const totalTarjetas = sums.credito + sums.debito + sums.transferencias + sums.qr + sums.financieras;
    const inpEfe = document.getElementById('inputTotalEfectivo');
    const inpTV  = document.getElementById('inputTotalVentas');
    const efectivoNum = inpEfe ? parseARNumber(inpEfe.value) : 0;
    const totalVentas = efectivoNum + totalTarjetas;
    if (inpTV) inpTV.value = formatAR0(totalVentas);
  }

  // Listeners “Total Ventas Efectivo”
  if (chkEditarTotal && chkOkTotal && inputTotalEfe) {
    const formatearTotal = () => { inputTotalEfe.value = formatAR0(inputTotalEfe.value); };

    inputTotalEfe.addEventListener('blur', () => {
      formatearTotal();
      chkEditarTotal.checked  = false;
      chkEditarTotal.disabled = true;
      chkOkTotal.checked      = true;
      inputTotalEfe.disabled  = true;
      recalcTotalesTarjetas();
      actualizarResumen();
    });

    chkEditarTotal.addEventListener('change', () => {
      if (chkEditarTotal.checked) {
        inputTotalEfe.disabled = false;
        inputTotalEfe.focus();
        inputTotalEfe.select();
      } else {
        inputTotalEfe.disabled = true;
        formatearTotal();
        recalcTotalesTarjetas();
        actualizarResumen();
      }
    });

    chkOkTotal.addEventListener('change', () => {
      if (chkOkTotal.checked) {
        chkEditarTotal.checked  = false;
        chkEditarTotal.disabled = true;
        inputTotalEfe.disabled  = true;
        formatearTotal();
      } else {
        chkEditarTotal.disabled = false;
      }
      recalcTotalesTarjetas();
      actualizarResumen();
    });

    // Formateo de Monto dentro de la tabla de tarjetas al perder foco
    const tablaTarjetas = document.getElementById('tablaTarjetasAgregadas');
    if (tablaTarjetas) {
      tablaTarjetas.addEventListener('blur', e => {
        const td = e.target;
        if (td.tagName === 'TD' && td.cellIndex === 2 && td.isContentEditable) {
          td.textContent = formatAR0(td.textContent);
        }
      }, true);
    }
  }

  // Referencias a elementos de consulta
  const inputId      = document.getElementById('id_cierre');
  const btnConsultar = document.getElementById('consultarCierre');
  const resultado    = document.getElementById('resultadoCierre');
  if (resultado) resultado.classList.add('d-none');

  // Recalc por cambios de OK en tarjetas
  document.addEventListener('change', (e) => {
    const t = e.target;
    if (t && t.matches('input[type="checkbox"].ok-checkbox') && t.closest('#tablaTarjetasAgregadas')) {
      recalcTotalesTarjetas();
      actualizarResumen(); // por si afecta Total Ventas Efectivo vs Gastos/Diferencia
    }
  });

  // -------- Consultar Cierre --------
  async function consultarCierre() {
    if (resultado) resultado.classList.add('d-none');
    const id = (inputId?.value || '').trim();
    if (!id) { alert('Ingresá un ID de cierre.'); return; }

    // 1) Buscar datos
    let data;
    try {
      const fd = new FormData();
      fd.append('id_cierre', id);
      const resp = await fetch('buscar_cierre.php', { method: 'POST', body: fd });
      data = await resp.json();
      if (!resp.ok || data.error) throw new Error(data.error || `HTTP ${resp.status}`);
    } catch (err) {
      console.error('Error cargar cierre:', err);
      alert('No se pudo cargar el cierre: ' + err.message);
      return;
    }

    if (data.ya_controlado) { alert(`El cierre ${id} ya fue controlado.`); return; }

    // 2) Precargar caches
    const formas = await cargarFormasPago(id);
    await Promise.all(formas.map(fp => cargarTarjetas(id, fp)));

    // 3) Pintar encabezados y totales
    const m = data.cierre || {};
    const campos = {
      inputSucursal:              m.Sucursal,
      inputFecha:                 m.Fecha,
      inputCajero:                m.Cajero,
      inputTurno:                 m.Turno,
      inputTotalEfectivo:         formatAR0(m.Total_efectivo),
      inputTotalCredito:          formatAR0(m.Total_tarjetas_credito),
      inputTotalDebito:           formatAR0(m.Total_tarjetas_debito),
      inputTotalTransferencias:   formatAR0(m.Total_transferencias),
      inputTotalTransferenciasQR: formatAR0(m.Total_transferenciasQR),
      inputTotalFinancieras:      formatAR0(m.Total_financieras)

    };
    Object.entries(campos).forEach(([idField, val]) => {
      const el = document.getElementById(idField);
      if (el) el.value = val ?? '';
    });

    // Comentarios  (líneas ~290 en tu archivo)
        const caj = document.getElementById('txtComentarioCajero');
        const adm = document.getElementById('txtComentarioControl');
        if (caj) caj.value = (m.Comentario ?? m.comentario ?? '').toString(); 
        if (adm) adm.value = (data.comentario_control ?? '');

    // 4) Pintar tablas
    pintarTabla('tablaTarjetasAgregadas', data.tarjetas || [], ['Forma_de_pago','Tarjeta','Monto','Lote','CUPON']);
    pintarTabla('tablaDeGastos',          data.gastos   || [], ['Concepto','Monto']);
    pintarChicas('tablaChicas',           data.chicas   || []);
    pintarBilletes(data.billetes         || []);

    if (resultado) resultado.classList.remove('d-none');

    // Recalcular por si corresponde
    recalcTotalesTarjetas();
    actualizarTotalGastos();
    actualizarTotalEfectivoRendido();
  }
  window.consultarCierre = consultarCierre;

  btnConsultar?.addEventListener('click', consultarCierre);
  inputId?.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); consultarCierre(); } });

  // -------- Pintar tabla genérica (Tarjetas/Gastos) --------
  function pintarTabla(tableId, rows, keys) {
    const tbody = document.querySelector(`#${tableId} tbody`);
    if (!tbody) return;
    tbody.innerHTML = '';

    rows.forEach(r => {
      const tr = document.createElement('tr');

      keys.forEach((key) => {
        const td = document.createElement('td');
        let text = r[key] != null ? r[key] : '';
        if (key === 'Monto') text = formatAR0(text);
        td.textContent = text;
        td.classList.add('centrado');
        tr.appendChild(td);
      });

      if (tableId === 'tablaTarjetasAgregadas') {
        // OK
        const tdOk = document.createElement('td');
        tdOk.classList.add('text-center');
        const chkOk = document.createElement('input'); chkOk.type = 'checkbox'; chkOk.classList.add('ok-checkbox');
        tdOk.appendChild(chkOk);
        tr.appendChild(tdOk);

        // Editar
        const tdEdit = document.createElement('td');
        tdEdit.classList.add('text-center');
        const chkEdit = document.createElement('input'); chkEdit.type = 'checkbox'; chkEdit.classList.add('editar-checkbox');
        tdEdit.appendChild(chkEdit);
        tr.appendChild(tdEdit);

        // Borrar
        const tdDel = document.createElement('td');
        tdDel.classList.add('text-center');
        const btnDel = document.createElement('button'); btnDel.textContent = '×'; btnDel.className = 'btn btn-sm btn-danger';
        btnDel.addEventListener('click', () => { tr.remove(); recalcTotalesTarjetas(); actualizarResumen(); });
        tdDel.appendChild(btnDel);
        tr.appendChild(tdDel);

        // --- EDITAR fila ---
        chkEdit.addEventListener('change', () => {
          const editable = chkEdit.checked;
          const tdForma = tr.children[0];
          const tdTarj  = tr.children[1];
          if (editable) {
            const selectForma = document.createElement('select'); selectForma.classList.add('form-select','form-select-sm');
            const actualF = tdForma.textContent.trim();
            cacheFormasPago.forEach(fp => {
              const opt = document.createElement('option'); opt.value = fp; opt.textContent = fp;
              if (fp === actualF) opt.selected = true;
              selectForma.appendChild(opt);
            });
            tdForma.dataset.original = actualF;
            tdForma.textContent = '';
            tdForma.appendChild(selectForma);

            const selectTarj = document.createElement('select'); selectTarj.classList.add('form-select','form-select-sm');
            const actualT = tdTarj.textContent.trim();
            tdTarj.dataset.original = actualT;
            tdTarj.textContent = '';
            tdTarj.appendChild(selectTarj);

            selectForma.addEventListener('change', () => {
              selectTarj.innerHTML = '';
              (cacheTarjetas[selectForma.value] || []).forEach(t => {
                const o = document.createElement('option'); o.value = t; o.textContent = t;
                selectTarj.appendChild(o);
              });
            });
            selectForma.dispatchEvent(new Event('change'));

            // Montos/Lote/Cupón editables
            for (let i = 2; i < keys.length; i++) tr.children[i].contentEditable = 'true';
            tr.classList.add('row-editable');
          } else {
            tdForma.textContent = tdForma.dataset.original || '';
            tdTarj.textContent  = tdTarj.dataset.original  || '';
            for (let i = 2; i < keys.length; i++) tr.children[i].contentEditable = 'false';
            tr.classList.remove('row-editable');
          }
        });

        // --- OK fila ---
        chkOk.addEventListener('change', () => {
          const ok = chkOk.checked;
          chkEdit.disabled = ok;
          btnDel.disabled  = ok;
          if (ok) {
            const sf = tr.children[0].querySelector('select'); if (sf) tr.children[0].textContent = sf.value;
            const st = tr.children[1].querySelector('select'); if (st) tr.children[1].textContent = st.value;
            for (let i = 2; i < keys.length; i++) tr.children[i].contentEditable = 'false';
            tr.classList.remove('row-editable');
          } else {
            chkEdit.checked = false;
            chkEdit.disabled = false;
          }
          recalcTotalesTarjetas();
          actualizarResumen();
        });
      }

      tbody.appendChild(tr);
    });
  }

  // -------- Chicas --------
  function pintarChicas(tableId, chicas) {
    const tbody = document.querySelector(`#${tableId} tbody`);
    if (!tbody) return;
    tbody.innerHTML = '';
    chicas.forEach(c => {
      const tr = document.createElement('tr');
      const td = document.createElement('td'); td.textContent = c.Nombre; td.classList.add('centrado');
      tr.appendChild(td);
      tbody.appendChild(tr);
    });
  }

  // -------- Billetes (relleno por denominación) --------
  function pintarBilletes(billetes) {
    const denoms = [20000,10000,2000,1000,500,200,100];
    denoms.forEach((denom,i) => {
      const rec = (billetes || []).find(b => Number(b.Denominacion) === denom);
      const input = document.getElementById(`cantidadBillete${i+1}`);
      if (input) input.value = rec ? rec.Cantidad : 0;
    });
    actualizarTotalEfectivoRendido(); // y a su vez llama a actualizarResumen()
  }

  // -------- Agregar Tarjeta --------
  const btnAgregarTarjeta = document.getElementById('btnAgregarTarjeta');
  if (btnAgregarTarjeta) {
    btnAgregarTarjeta.addEventListener('click', () => {
      const tbody = document.querySelector('#tablaTarjetasAgregadas tbody');
      const tr    = document.createElement('tr');

      // Forma de pago
      const tdFP = document.createElement('td');
      tdFP.classList.add('centrado');
      const selFP = document.createElement('select');
      selFP.classList.add('form-select', 'form-select-sm');
      selFP.appendChild(new Option('-Seleccionar-', ''));
      cacheFormasPago.forEach(fp => selFP.appendChild(new Option(fp, fp)));
      tdFP.appendChild(selFP);
      tr.appendChild(tdFP);

      // Tarjeta
      const tdT = document.createElement('td');
      tdT.classList.add('centrado');
      const selT = document.createElement('select');
      selT.classList.add('form-select', 'form-select-sm');
      selT.appendChild(new Option('-Seleccionar-', ''));
      tdT.appendChild(selT);
      tr.appendChild(tdT);

      // Al cambiar forma de pago, cargar tarjetas
      selFP.addEventListener('change', () => {
        selT.innerHTML = '';
        selT.appendChild(new Option('-Seleccionar-', ''));
        (cacheTarjetas[selFP.value] || []).forEach(t => selT.appendChild(new Option(t, t)));
      });

      // Monto, Lote, Cupón (editables)
      for (let i = 0; i < 3; i++) {
        const td = document.createElement('td');
        td.contentEditable = 'true';
        td.classList.add('centrado');
        tr.appendChild(td);
      }

      // OK
      const tdOk = document.createElement('td');
      tdOk.classList.add('text-center');
      const cbOk = document.createElement('input');
      cbOk.type = 'checkbox';
      cbOk.classList.add('ok-checkbox');
      tdOk.appendChild(cbOk);
      tr.appendChild(tdOk);

      // Editar
      const tdEd = document.createElement('td');
      tdEd.classList.add('text-center');
      const cbEd = document.createElement('input');
      cbEd.type = 'checkbox';
      cbEd.classList.add('editar-checkbox');
      tdEd.appendChild(cbEd);
      tr.appendChild(tdEd);

      // Borrar
      const tdDel = document.createElement('td');
      tdDel.classList.add('text-center');
      const btnDel = document.createElement('button');
      btnDel.className = 'btn btn-sm btn-danger';
      btnDel.textContent = '×';
      btnDel.addEventListener('click', () => { tr.remove(); recalcTotalesTarjetas(); actualizarResumen(); });
      tdDel.appendChild(btnDel);
      tr.appendChild(tdDel);

      // Editar fila (sólo celdas editables)
      cbEd.addEventListener('change', () => {
        const editable = cbEd.checked;
        for (let i = 2; i < 5; i++) tr.children[i].contentEditable = editable ? 'true' : 'false';
        tr.classList.toggle('row-editable', editable);
      });

      // OK fila
      cbOk.addEventListener('change', function() {
        const ok     = this.checked;
        const row    = this.closest('tr');
        const editCb = row.querySelector('.editar-checkbox');
        const delBtn = row.querySelector('button.btn-danger');

        editCb.disabled = ok;
        delBtn.disabled = ok;
        if (ok) {
          const selFp = row.querySelector('td:nth-child(1) select');
          const selTa = row.querySelector('td:nth-child(2) select');
          if (selFp) row.children[0].textContent = selFp.value;
          if (selTa) row.children[1].textContent = selTa.value;
          for (let i = 2; i < 5; i++) row.children[i].contentEditable = 'false';
          row.classList.remove('row-editable');
        } else {
          editCb.checked  = false;
          editCb.disabled = false;
          delBtn.disabled = false;
        }
        recalcTotalesTarjetas();
        actualizarResumen();
      });

      tbody.appendChild(tr);
    });
  }
    // === CHICAS: Editar / OK / Agregar ===
(function initChicas() {
  const tbodyChicas   = document.querySelector('#tablaChicas tbody');
  const chkEditar     = document.getElementById('editarChicas');
  const chkOk         = document.getElementById('okChicas');
  const btnAgregar    = document.getElementById('btnAgregarChica');

  if (!tbodyChicas || !chkEditar || !chkOk || !btnAgregar) return;

  // Habilita/Deshabilita edición y el botón "Agregar chica"
  const setEditable = (on) => {
    btnAgregar.disabled = !on;
    // Si querés que el nombre sea editable en la celda:
    tbodyChicas.querySelectorAll('td').forEach(td => {
      td.contentEditable = on ? 'true' : 'false';
    });
  };

  // Estado inicial (igual que Gastos: deshabilitado si no está tildado Editar)
  setEditable(!!chkEditar.checked);

  // Al tildar Editar, habilita agregar/edición y destilda OK
  chkEditar.addEventListener('change', () => {
    if (chkEditar.checked) {
      chkOk.checked = false;
      setEditable(true);
      btnAgregar.focus();
    } else {
      setEditable(false);
    }
  });

  // Al tildar OK, bloquea edición y botón, y destilda Editar
  chkOk.addEventListener('change', () => {
    if (chkOk.checked) {
      chkEditar.checked = false;
      setEditable(false);
    }
  });

  // Agregar fila (usa el mismo patrón de tu función actual; si ya tenés otra, dejá este handler llamando a la tuya)
  btnAgregar.addEventListener('click', () => {
    if (btnAgregar.disabled) return;

    // Si tenés un input para el nombre, usalo aquí (ajustá el id si es distinto)
    const inputNombre = document.getElementById('inputNombreChica');
    let nombre = (inputNombre?.value || '').trim();

    // Si no hay input, pedimos por prompt
    if (!nombre) {
      nombre = prompt('Nombre de la chica');
      if (!nombre) return;
    }

    const tr = document.createElement('tr');
    const td = document.createElement('td');
    td.textContent = nombre;
    td.classList.add('centrado');
    tr.appendChild(td);
    tbodyChicas.appendChild(tr);

    if (inputNombre) inputNombre.value = '';

    // Si tenés un recalculador de resumen, llamalo:
    if (typeof actualizarResumen === 'function') actualizarResumen();
  });
})();
// === GASTOS: Editar / OK / Agregar (modal 1 sola ventana) ===
(function initGastos() {
  const tbody      = document.querySelector('#tablaDeGastos tbody');
  const chkEditar  = document.getElementById('editarGastos');
  const chkOk      = document.getElementById('okGastos');
  const btnAgregar = document.getElementById('btnAgregarGasto');
  if (!tbody || !chkEditar || !chkOk || !btnAgregar) return;

  // ------- helpers -------
  const nfAR0 = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
  const toNum = (s) => Number(String(s ?? '').replace(/\./g, '').replace(/,/g, '.').replace(/[^\d.-]/g, '')) || 0;
  const el = (id) => document.getElementById(id);

  const setEditable = (on) => { btnAgregar.disabled = !on; };

  function recalcularGastosYResumen() {
    // 1) Total Gastos (suma columna 2)
    let totalGastos = 0;
    tbody.querySelectorAll('tr').forEach(tr => {
      const txt = (tr.cells[1]?.textContent || '').trim();
      totalGastos += toNum(txt);
    });
    if (el('inputTotalGastos')) el('inputTotalGastos').value = nfAR0.format(totalGastos);

    // 2) Saldo Caja Efectivo = Total Ventas Efectivo - Total Gastos
    const totalVentas = toNum(el('inputTotalEfectivo')?.value || el('inputTotalVentas')?.value || 0);
    const saldoCaja   = totalVentas - totalGastos;
    if (el('inputSaldoCajaEfectivo')) el('inputSaldoCajaEfectivo').value = nfAR0.format(saldoCaja);

    // 3) Diferencia = Efectivo Rendido - Saldo Caja Efectivo
    // (si tenés una función que recalcula efectivo rendido por billetes, llamala primero)
    if (typeof actualizarTotalEfectivoRendido === 'function') actualizarTotalEfectivoRendido();
    const efectivoRendido = toNum(el('inputEfectivoRendido')?.value || 0);
    const diferencia = efectivoRendido - saldoCaja;
    if (el('inputDiferenciaCaja')) el('inputDiferenciaCaja').value = nfAR0.format(diferencia);
  }

  // ------- modal Bootstrap (se crea 1 sola vez) -------
  function getOrCreateGastoModal() {
    let modal = el('modalGasto');
    if (modal) return modal;

    const wrapper = document.createElement('div');
    wrapper.innerHTML = `
      <div class="modal fade" id="modalGasto" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Agregar gasto</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">Concepto</label>
                <input id="mgConcepto" type="text" class="form-control" placeholder="Ej.: Pan, viáticos...">
              </div>
              <div class="mb-3">
                <label class="form-label">Monto</label>
                <input id="mgMonto" type="text" class="form-control" placeholder="Ej.: 5.800">
              </div>
            </div>
            <div class="modal-footer">
              <button id="mgCancelar" type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button id="mgAceptar"  type="button" class="btn btn-primary">Agregar</button>
            </div>
          </div>
        </div>
      </div>`;
    document.body.appendChild(wrapper.firstElementChild);
    return el('modalGasto');
  }

  function pedirGasto() {
    return new Promise((resolve) => {
      const modalEl = getOrCreateGastoModal();
      const bs = window.bootstrap?.Modal
        ? bootstrap.Modal.getOrCreateInstance(modalEl)
        : null;

      const $concepto = el('mgConcepto');
      const $monto    = el('mgMonto');
      const $aceptar  = el('mgAceptar');

      const onAccept = () => {
        const concepto = ($concepto.value || '').trim();
        const montoVal = toNum($monto.value);
        if (!concepto) { alert('El concepto no puede estar vacío.'); return; }
        if (montoVal <= 0) { alert('El monto debe ser mayor a 0.'); return; }
        bs?.hide();
        resolve({ concepto, monto: montoVal });
      };

      $concepto.value = '';
      $monto.value = '';
      $aceptar.onclick = onAccept;

      modalEl.addEventListener('shown.bs.modal', () => $concepto.focus(), { once: true });
      modalEl.addEventListener('hidden.bs.modal', () => resolve(null), { once: true });

      bs ? bs.show() : (modalEl.style.display = 'block'); // fallback muy simple
    });
  }

  // ------- wiring Editar/OK -------
  setEditable(!!chkEditar.checked);

  chkEditar.addEventListener('change', () => {
    if (chkEditar.checked) {
      chkOk.checked = false;
      setEditable(true);
      btnAgregar.focus();
    } else {
      setEditable(false);
    }
  });

  chkOk.addEventListener('change', () => {
    if (chkOk.checked) {
      chkEditar.checked = false;
      setEditable(false);
    }
  });

  // ------- Agregar gasto (modal 1 paso) -------
  btnAgregar.addEventListener('click', async () => {
    if (btnAgregar.disabled) return;
    const res = await pedirGasto();
    if (!res) return;

    // agregar fila
    const tr  = document.createElement('tr');
    const tdC = document.createElement('td');
    const tdM = document.createElement('td');
    tdC.textContent = res.concepto;
    tdM.textContent = nfAR0.format(res.monto);
    tdM.classList.add('centrado');
    tr.appendChild(tdC);
    tr.appendChild(tdM);
    tbody.appendChild(tr);

    // recalcular totales/resumen
    recalcularGastosYResumen();
  });

  // (Opcional) Edición rápida con doble click
  tbody.addEventListener('dblclick', (e) => {
    if (!chkEditar.checked) return;
    const cell = e.target.closest('td');
    if (!cell) return;
    const tr = cell.parentElement;

    if (cell.cellIndex === 0) {
      const nuevo = prompt('Editar concepto:', tr.cells[0].textContent.trim());
      if (nuevo !== null) tr.cells[0].textContent = nuevo.trim();
    } else if (cell.cellIndex === 1) {
      const nuevo = prompt('Editar monto:', tr.cells[1].textContent.trim());
      if (nuevo !== null) {
        const val = toNum(nuevo);
        if (val > 0) tr.cells[1].textContent = nfAR0.format(val);
      }
    }
    recalcularGastosYResumen();
  });
})();

  // -------- BILLETES: Editar / OK (habilitar Cantidad) --------
  (function initBilletes() {
    const tbodyBilletes   = document.querySelector('#tablaBilletes tbody');
    const chkEditarBil    = document.getElementById('editarBilletes');
    const chkOkBil        = document.getElementById('okBilletes');
    if (!tbodyBilletes || !chkEditarBil || !chkOkBil) return;

    const inputsCant = Array.from(tbodyBilletes.querySelectorAll('input[id^="cantidadBillete"]'));

    const setEditableBilletes = (editable) => {
      inputsCant.forEach(inp => {
        inp.readOnly = !editable;
        inp.tabIndex = editable ? 0 : -1;
        inp.style.setProperty('background', editable ? '#fff' : 'transparent');
        inp.style.setProperty('border', editable ? '1px solid #ced4da' : 'none');
      });
    };

    setEditableBilletes(chkEditarBil.checked && !chkOkBil.checked);

    chkEditarBil.addEventListener('change', () => {
      if (chkOkBil.checked) { chkEditarBil.checked = false; return; }
      setEditableBilletes(chkEditarBil.checked);
    });

    chkOkBil.addEventListener('change', () => {
      const ok = chkOkBil.checked;
      chkEditarBil.checked  = false;
      chkEditarBil.disabled = ok;
      setEditableBilletes(false);
      actualizarTotalEfectivoRendido(); // y a su vez llama a actualizarResumen()
    });

    inputsCant.forEach((inp, idx) => {
      inp.addEventListener('input', () => {
        inp.value = inp.value.replace(/[^\d]/g, '');
        actualizarTotalEfectivoRendido(); // recálculo en vivo
      });
      inp.addEventListener('blur', () => {
        actualizarTotalEfectivoRendido(); // por si vino de pegado rápido
      });
      inp.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          const next = inputsCant[idx + 1];
          if (next) next.focus(); else inp.blur();
        }
      });
    });
  })();

  // util formateo → número sin puntos
  const parseMonto = (txt) => {
    if (typeof txt !== 'string') txt = String(txt ?? '');
    return Number(txt.replace(/\./g,'').replace(/,/g,'.').replace(/[^\d.-]/g,'')) || 0;
  };
// ====== Guardar Control (blindado y recarga al tab Control de cierres) ======
(function initGuardarControl() {
  let btn = document.getElementById('btnGuardar');
  if (!btn) return;

  // 1) Evitar submit por defecto e inline handlers
  btn.setAttribute('type', 'button');
  btn.removeAttribute('onclick');
  btn.onclick = null;

  // 2) Bloquear submit del form, si existe
  const form = btn.closest('form');
  if (form) {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      e.stopPropagation();
    }, true);
  }

  // 3) Clonar botón para limpiar listeners previos
  const clone = btn.cloneNode(true);
  btn.replaceWith(clone);
  btn = clone;

  // 4) Handler ÚNICO
  btn.addEventListener('click', async () => {
    const parseMontoLocal = (txt) => {
      if (typeof txt !== 'string') txt = String(txt ?? '');
      return Number(txt.replace(/\./g, '').replace(/,/g, '.').replace(/[^\d.-]/g, '')) || 0;
    };

    const id = document.getElementById('id_cierre')?.value?.trim();
    if (!id) { alert('Primero cargá un cierre.'); return; }

    // --- Validaciones OK ---
    const faltantes = [];

    const okEfe = document.getElementById('OKTotalEfectivo');
    if (!okEfe || !okEfe.checked) faltantes.push('Ventas en efectivo');

    const rowsTar = Array.from(document.querySelectorAll('#tablaTarjetasAgregadas tbody tr'));
    if (rowsTar.length) {
      const sinOk = rowsTar.filter(tr => {
        const cb = tr.querySelector('input.ok-checkbox');
        return cb && !cb.checked;
      }).length;
      if (sinOk > 0) faltantes.push(`Tarjetas (${sinOk} fila(s) sin OK)`);
    }

    const okCh = document.getElementById('okChicas');
    if (!okCh || !okCh.checked) faltantes.push('Chicas');

    const okGa = document.getElementById('okGastos');
    if (!okGa || !okGa.checked) faltantes.push('Gastos');

    const okBil = document.getElementById('okBilletes');
    if (!okBil || !okBil.checked) faltantes.push('Billetes rendidos');

    if (faltantes.length) {
      alert('Para guardar, primero tildá OK en:\n- ' + faltantes.join('\n- '));
      return;
    }
    // --- fin validación ---

    const payload = {
      id_cierre: id,
      usuario_control: (window.USUARIO_CONTROL || 'ADMIN'),
      comentario: document.getElementById('txtComentarioControl')?.value || '',
      sucursal: document.getElementById('inputSucursal')?.value || '',
      fecha:    document.getElementById('inputFecha')?.value || '',
      turno:    document.getElementById('inputTurno')?.value || '',
      total_efectivo: parseMontoLocal(document.getElementById('inputTotalEfectivo')?.value || 0),
      tarjetas_ok: Array.from(document.querySelectorAll('#tablaTarjetasAgregadas tbody tr'))
        .filter(tr => tr.querySelector('input.ok-checkbox')?.checked)
        .map(tr => {
          const tds = tr.querySelectorAll('td');
          return {
            forma:   (tds[0]?.textContent || '').trim(),
            tarjeta: (tds[1]?.textContent || '').trim(),
            monto:   (tds[2]?.textContent || '0').trim(),
            lote:    (tds[3]?.textContent || '').trim(),
            cupon:   (tds[4]?.textContent || '').trim(),
          };
        }),
      gastos_ok: [],
      chicas_ok: []
    };

    if (document.getElementById('okGastos')?.checked) {
      Array.from(document.querySelectorAll('#tablaDeGastos tbody tr')).forEach(tr => {
        const concepto = (tr.cells[0]?.textContent || '').trim();
        const montoTxt = (tr.cells[1]?.textContent || '').trim();
        if (concepto && parseMontoLocal(montoTxt) > 0) payload.gastos_ok.push({ concepto, monto: montoTxt });
      });
    }

    if (document.getElementById('okChicas')?.checked) {
      Array.from(document.querySelectorAll('#tablaChicas tbody td')).forEach(td => {
        const nombre = (td.textContent || '').trim();
        if (nombre) payload.chicas_ok.push(nombre);
      });
    }

    if (document.getElementById('okBilletes')?.checked) {
      const denoms = [], cants = [];
      for (let i = 1; i <= 7; i++) {
        const dTxt = (document.getElementById('denominacionBillete' + i)?.textContent || '0').replace(/\./g, '');
        denoms.push(Number(dTxt) || 0);
        cants.push(Number(document.getElementById('cantidadBillete' + i)?.value || 0));
      }
      payload.billetes = { denoms, cants };
    }
    // Estado del botón
    const oldTxt = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Guardando...';

// --------- Guardar ---------
try {
  const resp = await fetch('guardar_control_cierre.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });

  const raw = await resp.text();
  let data;
  try { data = JSON.parse(raw); }
  catch {
    throw new Error(`Respuesta no-JSON (${resp.status}): ${raw.slice(0, 200)}`);
  }

  if (!resp.ok || data?.ok === false) {
    throw new Error(data?.error || `HTTP ${resp.status}`);
  }

  // ÉXITO
  alert('¡Control guardado correctamente!');
    window.location.reload();

} catch (e) {
  console.error(e);
  alert('No se pudo guardar: ' + e.message);
} finally {
  btn.disabled = false;
  btn.textContent = oldTxt;
}
}); })(); });
