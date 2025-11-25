// PDF oficial para Intranet-CEPESP
// Usar en Buttons:
// customize: function (doc) { dt_standard_pdf_customization(doc, 'Título del reporte'); }

function dt_standard_pdf_customization(doc, tituloReporte) {
  // Estilos base
  doc.defaultStyle.fontSize = 9;
  doc.styles.tableHeader.fontSize = 9;
  doc.styles.tableHeader.bold = true;

  // Márgenes de página: [izq, arriba, der, abajo]
  doc.pageMargins = [40, 80, 40, 60];

  // Registrar imágenes (de pdf_logos.js)
  doc.images = doc.images || {};
  doc.images.logo_izq = LOGO_CEPESP;
  doc.images.logo_der = LOGO2_CEPESP;

  // =========================
  // ENCABEZADO INSTITUCIONAL
  // =========================
  doc.header = function () {
    const fecha = new Date().toLocaleDateString("es-MX"); // solo fecha

    return {
      margin: [40, 20, 40, 0],
      columns: [
        {
          image: "logo_izq",
          width: 60,
          alignment: "left",
          margin: [0, 0, 10, 0],
        },
        {
          width: "*",
          stack: [
            {
              text: "POLICÍA ESTATAL DE SEGURIDAD PÚBLICA",
              fontSize: 12,
              bold: true,
              alignment: "center",
              margin: [0, 2, 0, 2],
            },
            {
              text: tituloReporte || "",
              fontSize: 10,
              alignment: "center",
            },
          ],
        },
        {
          width: 80,
          text: "Fecha:\n" + fecha,
          alignment: "right",
          fontSize: 8,
          margin: [0, 5, 0, 0],
        },
      ],
    };
  };

  // =========================
  // PIE INSTITUCIONAL EN UNA SOLA LÍNEA
  // =========================
  doc.footer = function (currentPage, pageCount) {
    return {
      margin: [40, 0, 40, 20],
      columns: [
        // COLUMNA IZQUIERDA: LOGO
        {
          image: "logo_der",
          width: 60, // tamaño del logo
          alignment: "left",
        },

        // COLUMNA CENTRAL: TEXTO CENTRADO
        {
          text:
            "LUIS ENCINAS Y CALLEJÓN OBREGÓN, COLONIA EL TORREÓN · " +
            "TEL. +52 (662) 218-9419 Y 218-9420 · " +
            "HERMOSILLO, SONORA, MÉXICO · www.sonora.gob.mx",
          alignment: "center",
          fontSize: 8,
        },

        // COLUMNA DERECHA: PÁGINA
        {
          text: "Página " + currentPage.toString() + " de " + pageCount,
          alignment: "right",
          width: 80, // fija para alineación limpia
          fontSize: 8,
        },
      ],
    };
  };

  // Ajuste del título generado por DataTables
  if (doc.content.length > 0 && doc.content[0].text) {
    doc.content[0].margin = [0, 0, 0, 5];
    doc.content[0].fontSize = 0;
    doc.content[0].bold = true;
    doc.content[0].alignment = "left";
  }

  // Layout suave de la tabla
  doc.content.forEach(function (c) {
    if (c.table) {
      const colCount = c.table.body[0].length;

      if (colCount <= 6) {
        // pocas columnas → expandir
        c.table.widths = Array(colCount).fill("*");
      /*} else if (colCount <= 6) {
        // La tabla es angosta → centramos la tabla completa
        // pdfMake no soporta margin:auto, así que centramos manualmente
        c.margin = [70, 0, 70, 0];
        // Ajusta 70 → más grande = más centrado*/
      }

      c.layout = {
        hLineWidth: function () {
          return 0.3;
        },
        vLineWidth: function () {
          return 0.3;
        },
        hLineColor: function () {
          return "#cccccc";
        },
        vLineColor: function () {
          return "#cccccc";
        },
        paddingLeft: function () {
          return 4;
        },
        paddingRight: function () {
          return 4;
        },
        paddingTop: function () {
          return 2;
        },
        paddingBottom: function () {
          return 2;
        },
      };
    }
  });
}
