<!-- reportes_uniformes.php (versión esqueleto) -->
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Reporte · Uniformes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Luego se cambia a tu header.php -->
    <link rel="stylesheet" href="/intranet-CEPESP/assets/css/bootstrap.min.css">
</head>

<body>
    <nav>…(placeholder)…</nav>

    <main class="container my-4">
        <h2 class="mb-3">Reporte · Entrega de uniformes</h2>

        <!-- Filtros -->
        <form id="filtros" class="row g-2 mb-3 no-print">
            <div class="col-md-4">
                <label class="form-label">Buscar</label>
                <input type="text" class="form-control" placeholder="Empleado, No. emp., producto o talla">
            </div>
            <div class="col-md-2">
                <label class="form-label">Desde</label>
                <input type="date" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">Hasta</label>
                <input type="date" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">Categoría</label>
                <select class="form-select">
                    <option>— Todas —</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Empleado</label>
                <select class="form-select">
                    <option>— Todos —</option>
                </select>
            </div>
            <div class="col-12 col-md-auto d-grid">
                <label class="form-label">&nbsp;</label>
                <button class="btn btn-outline-secondary" type="button">Aplicar</button>
            </div>
        </form>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Listado de entregas</span>
                <div id="toolbar-uniformes" class="btn-group btn-group-sm no-print"></div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="dt-uniformes" class="table table-striped table-hover align-middle w-100">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>No. emp.</th>
                                <th>Empleado</th>
                                <th>Categoría</th>
                                <th>Producto</th>
                                <th>Talla</th>
                                <th class="text-end">Cantidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- filas dummy estáticas para ver layout -->
                            <tr>
                                <td>2025-01-10</td>
                                <td>1234</td>
                                <td>Juan Pérez</td>
                                <td>Camisas</td>
                                <td>Camisa táctica</td>
                                <td>M</td>
                                <td class="text-end">2</td>
                            </tr>
                            <tr>
                                <td>2025-01-12</td>
                                <td>5678</td>
                                <td>Ana Ruiz</td>
                                <td>Calzado</td>
                                <td>Botas</td>
                                <td>6</td>
                                <td class="text-end">1</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pie institucional solo impresión: placeholder -->
        <footer class="pie-institucional print-only">…</footer>
    </main>

    <script src="/intranet-CEPESP/assets/js/bootstrap.bundle.min.js"></script>
    <!-- debajo de bootstrap.bundle.min.js -->
    <script src="/intranet-CEPESP/assets/js/jquery.min.js"></script>
    <script src="/intranet-CEPESP/assets/js/datatables/jquery.dataTables.min.js"></script>
    <script src="/intranet-CEPESP/assets/js/datatables/dataTables.bootstrap5.min.js"></script>
    <script src="/intranet-CEPESP/assets/js/datatables/dataTables.buttons.min.js"></script>
    <script src="/intranet-CEPESP/assets/js/datatables/buttons.bootstrap5.min.js"></script>
    <script src="/intranet-CEPESP/assets/js/datatables/jszip.min.js"></script>
    <script src="/intranet-CEPESP/assets/js/datatables/pdfmake.min.js"></script>
    <script src="/intranet-CEPESP/assets/js/datatables/vfs_fonts.js"></script>
    <script src="/intranet-CEPESP/assets/js/datatables/buttons.html5.min.js"></script>
    <script src="/intranet-CEPESP/assets/js/datatables/buttons.print.min.js"></script>
    <script>
        $(function() {
            const dt = $('#dt-uniformes').DataTable({
                dom: 'rtip',
                pageLength: 25,
                order: [
                    [0, 'desc']
                ]
            });
            new $.fn.dataTable.Buttons(dt, {
                buttons: [{
                        extend: 'excelHtml5',
                        text: 'Excel',
                        className: 'btn btn-success btn-sm'
                    },
                    {
                        extend: 'pdfHtml5',
                        text: 'PDF',
                        className: 'btn btn-danger btn-sm',
                        orientation: 'landscape',
                        pageSize: 'A4'
                    },
                    {
                        extend: 'print',
                        text: 'Imprimir',
                        className: 'btn btn-secondary btn-sm'
                    },
                    {
                        extend: 'colvis',
                        text: 'Columnas',
                        className: 'btn btn-outline-dark btn-sm'
                    }
                ]
            });
            dt.buttons().container().appendTo('#toolbar-uniformes');
        });
    </script>

</body>

</html>