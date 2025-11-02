<?php

/******************************************************
 * reportes_uniformes.php
 * Reporte con filtro por fecha (salidas.fecha)
 * Solo visible en navbar para roles admin/inventarios.
 ******************************************************/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'intranet';

$mysqli = @mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if (!$mysqli) {
    die('Error de conexión a MySQL: ' . htmlspecialchars(mysqli_connect_error()));
}
mysqli_set_charset($mysqli, 'utf8mb4');

/* ========== Filtros de fecha (GET) ========== */
date_default_timezone_set('America/Hermosillo');

// Defaults: del 1° de enero del año actual a hoy
$hoy = date('Y-m-d');
$inicioAnio = date('Y-01-01');

$fecha_desde = isset($_GET['fecha_desde']) && $_GET['fecha_desde'] !== '' ? $_GET['fecha_desde'] : '';
$fecha_hasta = isset($_GET['fecha_hasta']) && $_GET['fecha_hasta'] !== '' ? $_GET['fecha_hasta'] : '';

/* Sanitizado simple de formato (YYYY-MM-DD) */
$validaFecha = fn($f) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $f);

$usaFiltro = ($fecha_desde && $fecha_hasta && $validaFecha($fecha_desde) && $validaFecha($fecha_hasta));

/* ========== Construcción de consulta ========== */
/* 1) Si hay filtro de fechas, consultamos con JOIN y WHERE s.fecha BETWEEN ? AND ?
      Esto devuelve SOLO empleados con entregas en ese rango. */
if ($usaFiltro) {
    $sql = "
    SELECT 
      e.no_empleado AS `No. Empleado`,
      e.nombre_completo AS `Nombre Empleado`,
      s.fecha AS `Fecha`,

      MAX(CASE WHEN eq.categoria = 'Uniforme' AND eq.descripcion LIKE '%Pantalón%' THEN v.talla END) AS `Talla Pantalón`,
      SUM(CASE WHEN eq.categoria = 'Uniforme' AND eq.descripcion LIKE '%Pantalón%' THEN sd.cantidad ELSE 0 END) AS `Cantidad Pantalón`,

      MAX(CASE WHEN eq.categoria = 'Uniforme' AND eq.descripcion LIKE '%Camisa%' THEN v.talla END) AS `Talla Camisa`,
      SUM(CASE WHEN eq.categoria = 'Uniforme' AND eq.descripcion LIKE '%Camisa%' THEN sd.cantidad ELSE 0 END) AS `Cantidad Camisa`,

      MAX(CASE WHEN eq.categoria = 'Calzado' THEN v.talla END) AS `Talla Botas`,
      SUM(CASE WHEN eq.categoria = 'Calzado' THEN sd.cantidad ELSE 0 END) AS `Cantidad Botas`

    FROM empleados e
    /* JOINs para garantizar que haya entregas dentro del rango */
    INNER JOIN salidas s          ON s.id_empleado = e.id_empleado
    INNER JOIN salidas_detalle sd ON sd.id_salida = s.id_salida
    LEFT  JOIN item_variantes v   ON v.id_variante = sd.id_variante
    LEFT  JOIN equipo eq          ON eq.id_equipo = v.id_equipo

    WHERE s.fecha BETWEEN ? AND ?
    GROUP BY e.id_empleado, e.no_empleado, e.nombre_completo
    HAVING SUM(sd.cantidad) > 0
    ORDER BY e.no_empleado
  ";
    $stmt = mysqli_prepare($mysqli, $sql);
    mysqli_stmt_bind_param($stmt, 'ss', $fecha_desde, $fecha_hasta);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
} else {
    /* 2) Sin filtro: usamos la VISTA v_uniformes_empleados (ya filtra quienes recibieron algo, sin fechas) */
    $fecha_desde = $inicioAnio; // solo para mostrar algo por defecto en el form
    $fecha_hasta = $hoy;

    $sql = "SELECT 
            `No. Empleado`,
            `Nombre Empleado`,
            `Fecha`,
            `Talla Pantalón`,
            `Cantidad Pantalón`,
            `Talla Camisa`,
            `Cantidad Camisa`,
            `Talla Botas`,
            `Cantidad Botas`
          FROM v_uniformes_empleados
          ORDER BY `No. Empleado`";
    $res = mysqli_query($mysqli, $sql);
    if (!$res) {
        die('<h3 style="font-family:Arial">No se pudo leer la vista <code>v_uniformes_empleados</code>.<br>
         Crea la vista o corrige el error.<br><br>
         Detalle: ' . htmlspecialchars(mysqli_error($mysqli)) . '</h3>');
    }
}
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Reporte de Uniformes por Empleado</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables + Buttons -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
    <style>
        body {
            background: #f7f7f9;
        }

        .card {
            border-radius: 14px;
        }

        .table thead th {
            white-space: nowrap;
        }

        .dt-buttons .btn {
            margin-right: .25rem;
        }

        .page-title {
            font-weight: 700;
        }

        .small-muted {
            font-size: .9rem;
            color: #6c757d;
        }
    </style>
</head>

<body>
    <div class="container-fluid py-4">

        <div class="row mb-3">
            <div class="col">
                <h1 class="page-title">Reporte de Uniformes por Empleado</h1>
                <p class="small-muted mb-0">
                    Fuente: <code>v_uniformes_empleados</code> (sin fechas) o consulta agregada (con fechas).
                </p>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <form class="row g-3" method="get" action="">
                    <div class="col-sm-6 col-md-3">
                        <label class="form-label">Fecha desde</label>
                        <input type="date" class="form-control" name="fecha_desde" value="<?= htmlspecialchars($fecha_desde) ?>">
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <label class="form-label">Fecha hasta</label>
                        <input type="date" class="form-control" name="fecha_hasta" value="<?= htmlspecialchars($fecha_hasta) ?>">
                    </div>
                    <div class="col-sm-12 col-md-6 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">Aplicar</button>
                        <a href="reportes_uniformes.php" class="btn btn-outline-secondary">Limpiar</a>
                    </div>
                    <div class="col-12 small-muted">
                        Si especificas ambas fechas, el reporte se limita a las salidas en ese rango (incluye solo empleados con entregas en el rango).
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla -->
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tablaUniformes" class="table table-striped table-bordered align-middle" style="width:100%">
                        <thead class="table-dark">
                            <tr>
                                <th>No. Empleado</th>
                                <th>Nombre Empleado</th>
                                <th>Fecha</th>
                                <th>Talla Pantalón</th>
                                <th>Cantidad Pantalón</th>
                                <th>Talla Camisa</th>
                                <th>Cantidad Camisa</th>
                                <th>Talla Botas</th>
                                <th>Cantidad Botas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($res)): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['No. Empleado'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['Nombre Empleado'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['Fecha'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['Talla Pantalón'] ?? '') ?></td>
                                    <td class="text-center"><?= (int)($row['Cantidad Pantalón'] ?? 0) ?></td>
                                    <td><?= htmlspecialchars($row['Talla Camisa'] ?? '') ?></td>
                                    <td class="text-center"><?= (int)($row['Cantidad Camisa'] ?? 0) ?></td>
                                    <td><?= htmlspecialchars($row['Talla Botas'] ?? '') ?></td>
                                    <td class="text-center"><?= (int)($row['Cantidad Botas'] ?? 0) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3 small-muted">
            Nota: las categorías y descripciones de <code>equipo</code> determinan qué se clasifica como Pantalón, Camisa o Botas.
        </div>

    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables + Buttons -->
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    <script>
        $(function() {
            $('#tablaUniformes').DataTable({
                pageLength: 20,
                lengthMenu: [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "Todos"]
                ],
                order: [
                    [0, 'asc']
                ],
                dom: 'Bfrtip',
                buttons: [{
                        extend: 'copyHtml5',
                        text: 'Copiar'
                    },
                    {
                        extend: 'excelHtml5',
                        title: 'Uniformes_por_Empleado'
                    },
                    {
                        extend: 'pdfHtml5',
                        title: 'Uniformes_por_Empleado',
                        orientation: 'landscape',
                        pageSize: 'A4'
                    },
                    {
                        extend: 'print',
                        text: 'Imprimir'
                    }
                ],
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json'
                }
            });
        });
    </script>
</body>

</html>
<?php
if (isset($stmt) && $stmt) {
    mysqli_stmt_close($stmt);
}
if (isset($res) && $res instanceof mysqli_result) {
    mysqli_free_result($res);
}
mysqli_close($mysqli);
