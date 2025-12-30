// cierrespendientes.js (reemplazo completo)
(function () {
  // Ubica la tabla dentro del contenedor esperado (con tolerancia)
  const container =
    document.querySelector('#cierresPendientesControl') ||
    document.querySelector('#cierresPendientes') ||
    document;

  const table = container.querySelector('table');
  if (!table || !table.tHead || !table.tHead.rows.length) return;

  const headRow = table.tHead.rows[0];
  const center = (el) => {
    el.classList.add('text-center', 'align-middle');
    el.style.textAlign = 'center';
    el.style.verticalAlign = 'middle';
  };

  // Normaliza encabezados y detecta índices
  Array.from(headRow.cells).forEach((th) => {
    const txt = (th.textContent || '').trim();
    if (txt.toLowerCase() === 'turno') center(th);
    if (/^acci[oó]n?$/i.test(txt)) {
      th.textContent = 'Acción';
      center(th);
    }
  });

  const headerTexts = Array.from(headRow.cells).map((th) =>
    (th.textContent || '').trim()
  );
  const turnoIdx = headerTexts.findIndex((t) => t.toLowerCase() === 'turno');
  let accionIdx = headerTexts.findIndex((t) => t.toLowerCase() === 'acción');

  // Crea columna Acción si falta
  if (accionIdx === -1) {
    const th = document.createElement('th');
    th.textContent = 'Acción';
    center(th);
    headRow.appendChild(th);
    accionIdx = headRow.cells.length - 1;

    table.querySelectorAll('tbody tr').forEach((r) => {
      const td = document.createElement('td');
      center(td);
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.textContent = 'Cargar';
      btn.className = 'btn btn-sm btn-primary';
      btn.dataset.idCierre = (r.cells[0]?.textContent || '').trim();
      td.appendChild(btn);
      r.appendChild(td);
    });
  }

  // Centra columnas Turno y Acción en cuerpo
  table.querySelectorAll('tbody tr').forEach((r) => {
    if (turnoIdx > -1 && r.cells[turnoIdx]) center(r.cells[turnoIdx]);
    if (accionIdx > -1 && r.cells[accionIdx]) center(r.cells[accionIdx]);
  });

  // Delegación de eventos (una sola vez)
  const tbody = table.tBodies[0];
  if (tbody && !tbody.dataset.listener) {
    tbody.addEventListener('click', (e) => {
      const btn = e.target.closest('button');
      if (!btn) return;

      const id =
        btn.dataset.idCierre || btn.dataset.id || (btn.value || '').trim();
      const inputId = document.getElementById('id_cierre');
      if (inputId) inputId.value = id;

      // Llamado seguro (evita crash si la función no existe)
      if (typeof window.consultarCierre === 'function') {
        window.consultarCierre();
      } else if (typeof window.buscarCierre === 'function') {
        // por compatibilidad si la función se llama distinto
        window.buscarCierre();
      } else {
        console.warn(
          'No se encontró consultarCierre/buscarCierre en window. Verifica controlcierres.js'
        );
      }

      // Activa la pestaña de Control de Cierres si existe
      const posibles = ['#tab-control-cierres', '#controlCierres', '#tabControlCierres'];
      for (const sel of posibles) {
        const tabLink =
          document.querySelector(
            `.nav-link[data-bs-target="${sel}"], .nav-link[href="${sel}"]`
          ) || document.querySelector(sel);
        if (tabLink) {
          if (window.bootstrap?.Tab) {
            new bootstrap.Tab(tabLink).show();
          } else {
            tabLink.click?.();
          }
          break;
        }
      }
    });
    tbody.dataset.listener = '1';
  }

  // Si recarga con hash de pendientes, muestra esa pestaña
  document.addEventListener('DOMContentLoaded', () => {
    const hash = window.location.hash;
    if (hash === '#cierresPendientesControl') {
      const triggerEl = document.querySelector(
        `.nav-link[data-bs-target="${hash}"], .nav-link[href="${hash}"]`
      );
      if (triggerEl && window.bootstrap?.Tab) {
        new bootstrap.Tab(triggerEl).show();
      }
    }
  });
 
document.addEventListener('click', async (ev) => {
  const btn = ev.target.closest('.btnEliminar');
  if (!btn) return;

  const id = btn.dataset.idCierre;
  if (!id) return;

  const ok = confirm(
    "¿Eliminar DEFINITIVAMENTE el cierre " + id +
    " y todos sus detalles (chicas, gastos, efectivo)?\n\nEsta acción NO se puede deshacer."
  );
  if (!ok) return;

  try {
    const resp = await fetch('eliminar_cierre.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
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
      "Chicas: " + (d.chicas ?? 0) + "\n" +
      "Gastos: " + (d.gastos ?? 0) + "\n" +
      "Efectivo: " + (d.efectivo ?? 0) + "\n" +
      "Totales: " + (d.totales ?? 0)
    );
    location.reload();
  } catch (e) {
    alert("Error de red eliminando el cierre: " + e.message);
  }
});

})();

