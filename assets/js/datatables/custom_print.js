// Configuración estándar para impresión en toda la Intranet-CEPESP
// Usar siempre en DataTables Buttons:
// customize: dt_standard_print_customization

function dt_standard_print_customization(win) {
  const $doc  = $(win.document);
  const $head = $doc.find('head');
  const $body = $doc.find('body');

  // ============================
  // ESTILOS GLOBALES PARA IMPRESIÓN
  // ============================
  const style = `
    <style>
      /* Margen físico de la hoja */
      @page {
        margin: 15mm 10mm 15mm 10mm;
      }

      body {
        margin: 0;
        /* 🔴 CLAVE: reservamos espacio para header y footer */
        padding-top: 40mm;   /* altura aproximada del encabezado */
        padding-bottom: 25mm;/* altura aproximada del pie */
        font-family: Arial, Helvetica, sans-serif;
        font-size: 12px;
        color: #000;
        counter-reset: page;
      }

      /* Encabezado y pie fijos en todas las páginas */
      #encabezado-institucional,
      #pie-institucional {
        position: fixed;
        left: 0;
        right: 0;
      }

      #encabezado-institucional {
        top: 0;
        border-bottom: 2px solid #000;
      }

      #pie-institucional {
        bottom: 0;
        border-top: 2px solid #000;
      }

      table {
        width: 100% !important;
        border-collapse: collapse;
        font-size: 12px;
        page-break-inside: auto;
        /* ❌ Ya NO usamos margin-top/margin-bottom en la tabla */
      }

      thead { display: table-header-group; }
      tfoot { display: table-footer-group; }

      tr {
        page-break-inside: avoid;
        page-break-after: auto;
      }

      th {
        font-size: 12px;
        font-weight: bold;
        border-bottom: 1px solid #000;
        padding: 4px 6px;
        text-align: left;
      }

      td {
        padding: 3px 6px;
        border-bottom: 0.5px solid #ccc;
      }

      /* Numeración de páginas en el pie */
      #pie-institucional .page-number::after {
        counter-increment: page;
        content: "Página " counter(page);
      }
    </style>
  `;
  $head.append(style);

  // Ocultar el h1 que pone DataTables por defecto
  $body.find('h1').css('display', 'none');

  // ============================
  // ENCABEZADO INSTITUCIONAL
  // ============================
  const headerHtml = `
    <div id="encabezado-institucional" style="
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 5px 0 10px 0;
      ">
      <div style="flex: 1; text-align: left;">
        <img src="${window.location.origin}/intranet-CEPESP/assets/img/logo.png"
             style="max-width: 95px; max-height: 95px;"
             onerror="this.style.display='none'">
      </div>
      <div style="flex: 3; text-align: center;">
        <div style="font-size: 14pt; font-weight: bold;">
          POLICÍA ESTATAL DE SEGURIDAD PÚBLICA
        </div>
      </div>
      <div style="flex: 1; text-align: right; font-size: 10pt;">
        <strong>Fecha:</strong><br>${new Date().toLocaleString()}
      </div>
    </div>
  `;

  // ============================
  // PIE INSTITUCIONAL + PÁGINAS
  // ============================
  const footerHtml = `
    <div id="pie-institucional" style="
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 5px 0 5px 0;
        font-size: 8px;
      ">
      <div style="flex: 4; text-align: right;">
        <img src="${window.location.origin}/intranet-CEPESP/assets/img/logo2.png"
             style="max-width: 95px; max-height: 95px;"
             onerror="this.style.display='none'">
      </div>
      <div style="flex: 8; text-align: center;">
        LUIS ENCINAS Y CALLEJÓN OBREGÓN, COLONIA EL TORREÓN<br>
        TEL. +52 (662) 218-9419 Y 218-9420<br>
        HERMOSILLO, SONORA, MÉXICO · www.sonora.gob.mx
        <div class="page-number" style="margin-top: 4px;"></div>
      </div>
    </div>
  `;

  // Insertar en el documento de impresión
  $body.prepend(headerHtml);
  $body.append(footerHtml);
}
