<?php
declare(strict_types=1);

include __DIR__ . '/layout/header.php';

$servicio = new InformesService();
[$fecha_inicio, $fecha_fin] = $servicio->rangoFechas($_GET['fecha_inicio'] ?? null, $_GET['fecha_fin'] ?? null);
?>
<div class="container">
    <h1>Informe de Asistencia</h1>

    <div class="filter-section">
        <form action="index.php?ruta=informes" method="GET">
            <label for="fecha_inicio">Fecha de inicio:</label>
            <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?php echo htmlspecialchars($fecha_inicio); ?>">
            <label for="fecha_fin">Fecha de fin:</label>
            <input type="date" id="fecha_fin" name="fecha_fin" value="<?php echo htmlspecialchars($fecha_fin); ?>">
            <button type="submit">Filtrar</button>
        </form>
    </div>

    <h2>Contadores</h2>
    <table id="tabla-contadores" class="display">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Contador</th>
                <th>Última Pulsación</th>
            </tr>
        </thead>
    </table>

    <h2>Pulsaciones</h2>
    <table id="tabla-pulsaciones" class="display">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre Persona</th>
                <th>Fecha y Hora</th>
                <th>IP Usuario</th>
                <th>User Agent</th>
            </tr>
        </thead>
    </table>
</div>

<!-- jQuery -->
<script type="text/javascript" src="https://code.jquery.com/jquery-3.5.1.js"></script>
<!-- DataTables JS -->
<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
<!-- DataTables Buttons JS -->
<script type="text/javascript" src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>

<script>
    $(document).ready(function () {
        function datosFiltro(d) {
            d.fecha_inicio = $('#fecha_inicio').val();
            d.fecha_fin = $('#fecha_fin').val();
        }

        function manejarErrorAjax(xhr) {
            if (xhr.status === 401) {
                window.location.href = 'index.php?ruta=login&caducada=1';
            }
        }

        var tablaContadores = $('#tabla-contadores').DataTable({
            ajax: {
                url: 'index.php?ruta=informes/api&action=contadores',
                data: datosFiltro,
                dataSrc: 'data',
                error: manejarErrorAjax
            },
            columns: [
                { data: 'id' },
                { data: 'nombre' },
                { data: 'contador' },
                { data: 'ultima_pulsacion' }
            ],
            dom: 'Bfrtip',
            buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
        });

        var tablaPulsaciones = $('#tabla-pulsaciones').DataTable({
            ajax: {
                url: 'index.php?ruta=informes/api&action=pulsaciones',
                data: datosFiltro,
                dataSrc: 'data',
                error: manejarErrorAjax
            },
            columns: [
                { data: 'id' },
                { data: 'nombre_persona' },
                { data: 'fecha_hora' },
                { data: 'ip_usuario' },
                { data: 'user_agent' }
            ],
            dom: 'Bfrtip',
            buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
            order: [[0, 'desc']]
        });

        $('form').on('submit', function (e) {
            e.preventDefault();
            tablaContadores.ajax.reload();
            tablaPulsaciones.ajax.reload();
        });
    });
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
