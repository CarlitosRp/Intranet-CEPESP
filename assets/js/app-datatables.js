// assets/js/app-datatables.js
(function (w) {
  function exists(fn) {
    try {
      return typeof fn === "function";
    } catch {
      return false;
    }
  }

  w.initDataTable = function (selector, options) {
    if (!w.jQuery || !exists(jQuery.fn.DataTable)) {
      console.warn("[DT] jQuery/DataTables no disponible aún.");
      return;
    }
    var $el = jQuery(selector);
    if (!$el.length) {
      console.warn("[DT] Selector no encontrado:", selector);
      return;
    }

    var defaults = {
      processing: true,
      responsive: true,
      pageLength: 25,
      lengthMenu: [
        [10, 25, 50, 100, -1],
        [10, 25, 50, 100, "Todos"],
      ],
      ordering: true,
      dom:
        "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6 text-end'B>>" +
        "<'row'<'col-sm-12'tr>>" +
        "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
      buttons: [
        {
          extend: "excelHtml5",
          text: "Excel",
          title: document.title || "reporte",
        },
        { extend: "csvHtml5", text: "CSV", title: document.title || "reporte" },
        { extend: "print", text: "Imprimir" },
        // { extend: 'pdfHtml5', text: 'PDF' }, // (actívalo si quieres PDF)
      ],
      language: {
        url: null,
        decimal: ",",
        thousands: ".",
        search: "Buscar:",
        lengthMenu: "Mostrar _MENU_",
        info: "Mostrando _START_–_END_ de _TOTAL_",
        infoEmpty: "Sin registros",
        infoFiltered: "(filtrado de _MAX_)",
        zeroRecords: "Sin resultados",
        paginate: { first: "«", last: "»", next: "›", previous: "‹" },
      },
    };

    var cfg = jQuery.extend(true, {}, defaults, options || {});
    $el.DataTable(cfg);
  };
})(window);

// assets/js/app-datatables.js
(function () {
  function initDT(selector) {
    const $table = $(selector);
    if (!$table.length || $.fn.dataTable.isDataTable($table)) return;

    $table.DataTable({
      responsive: true,
      lengthChange: true,
      pageLength: 25,
      ordering: true,
      processing: true,
      // Dom: coloca los botones arriba a la derecha, búsqueda y paginación estándar
      dom:
        "<'row'<'col-sm-6'l><'col-sm-6 text-end'B>>" +
        "<'row'<'col-sm-12'tr>>" +
        "<'row'<'col-sm-5'i><'col-sm-7'p>>",
      buttons: [
        {
          extend: "excelHtml5",
          className: "btn btn-success btn-sm",
          text: "Excel",
          title: "Reporte_uniformes",
        },
        {
          extend: "pdfHtml5",
          className: "btn btn-danger btn-sm",
          text: "PDF",
          title: "Reporte_uniformes",
          orientation: "landscape",
          pageSize: "A4",
        },
        {
          extend: "print",
          className: "btn btn-secondary btn-sm",
          text: "Imprimir",
          title: "Reporte de entrega de uniformes",
        },
      ],
      language: {
        url: "", // puedes dejarlo vacío o poner un JSON de traducción si lo tienes local
      },
    });
  }

  // auto-init de todas las tablas marcadas
  $(document).ready(function () {
    initDT(".js-dt");
  });
})();
