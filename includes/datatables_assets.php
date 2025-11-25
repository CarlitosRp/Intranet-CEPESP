<?php // includes/datatables_assets.php 
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/datatables/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/datatables/buttons.bootstrap5.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/datatables/responsive.bootstrap5.min.css">

<!-- jQuery primero (DT 1.13 depende de jQuery) -->
<script src="<?= BASE_URL ?>assets/js/jquery-3.7.1.min.js"></script>

<!-- Núcleo DataTables + Bootstrap 5 + Responsive -->
<script src="<?= BASE_URL ?>assets/js/datatables/jquery.dataTables.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/datatables/dataTables.bootstrap5.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/datatables/dataTables.responsive.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/datatables/responsive.bootstrap5.min.js"></script>

<!-- Extensión Buttons + dependencias de exportación -->
<script src="<?= BASE_URL ?>assets/js/datatables/dataTables.buttons.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/datatables/buttons.bootstrap5.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/datatables/jszip.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/datatables/pdfmake.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/datatables/vfs_fonts.js"></script>
<script src="<?= BASE_URL ?>assets/js/datatables/buttons.html5.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/datatables/buttons.print.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/datatables/buttons.colVis.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/datatables/custom_print.js"></script>
<script src="<?= BASE_URL ?>assets/js/datatables/custom_pdf.js"></script>
<script src="<?= BASE_URL ?>assets/js/datatables/pdf_logos.js"></script>

<!-- Diagnóstico opcional (puedes quitarlo luego) -->
<script>
    console.log('DT:', $.fn.dataTable ? $.fn.dataTable.version : 'NO');
    console.log('Buttons:', $.fn.dataTable && $.fn.dataTable.Buttons ? 'OK' : 'MISSING');
</script>