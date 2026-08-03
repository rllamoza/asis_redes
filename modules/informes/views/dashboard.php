<?php
declare(strict_types=1);

include __DIR__ . '/layout/header.php';

$servicio = new InformesService();
[$fecha_inicio, $fecha_fin] = $servicio->rangoFechas($_GET['fecha_inicio'] ?? null, $_GET['fecha_fin'] ?? null);
?>
<form method="GET" action="index.php?ruta=dashboard" class="filter-section">
    <label for="fecha_inicio">Fecha de inicio:</label>
    <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?php echo htmlspecialchars($fecha_inicio); ?>">
    <label for="fecha_fin">Fecha de fin:</label>
    <input type="date" id="fecha_fin" name="fecha_fin" value="<?php echo htmlspecialchars($fecha_fin); ?>">
    <button type="submit">Filtrar</button>
</form>
<div class="dashboard-grid">
    <div class="chart-card grid-col-span-2">
        <h3>Top 5 Personas</h3>
        <canvas id="topPersonasChart"></canvas>
    </div>
    <div class="chart-card grid-col-span-2">
        <h3>Asistencias por Día</h3>
        <canvas id="asistenciasDiaChart"></canvas>
    </div>
    <div class="chart-card">
        <h3>Distribución de Servicios</h3>
        <canvas id="serviciosChart"></canvas>
    </div>
    <div class="chart-card">
        <h3>Total Asistencias</h3>
        <div class="progress-circle-container">
            <div class="progress-circle" data-progress="0"></div>
        </div>
    </div>
</div>

<script>
    var graficos = {};

    function cargarDashboard() {
        var params = new URLSearchParams({
            action: 'dashboard',
            fecha_inicio: document.getElementById('fecha_inicio').value,
            fecha_fin: document.getElementById('fecha_fin').value
        });

        fetch('index.php?ruta=informes/api&' + params.toString())
            .then(function (r) {
                if (r.status === 401) {
                    window.location.href = 'index.php?ruta=login&caducada=1';
                    throw new Error('Sesión caducada');
                }
                return r.json();
            })
            .then(function (datos) {
                if (!datos.success) { throw new Error(datos.message); }
                renderizar(datos.data);
            })
            .catch(function (e) { alert('Error al cargar el dashboard: ' + e.message); });
    }

    function destruirGraficos() {
        Object.keys(graficos).forEach(function (clave) {
            if (graficos[clave]) { graficos[clave].destroy(); }
        });
        graficos = {};
    }

    function renderizar(data) {
        destruirGraficos();

        var top = data.top_personas;
        graficos.top = new Chart(document.getElementById('topPersonasChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: top.map(function (f) { return f.nombre_persona; }),
                datasets: [{
                    label: 'Primer Servicio',
                    data: top.map(function (f) { return parseInt(f.primer_servicio); }),
                    backgroundColor: '#4a148c'
                }, {
                    label: 'Segundo Servicio',
                    data: top.map(function (f) { return parseInt(f.segundo_servicio); }),
                    backgroundColor: '#e91e63'
                }]
            },
            options: { scales: { y: { beginAtZero: true } } }
        });

        var dias = data.asistencias_por_dia;
        graficos.dias = new Chart(document.getElementById('asistenciasDiaChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: dias.map(function (f) { return f.dia; }),
                datasets: [{
                    label: 'Asistencias',
                    data: dias.map(function (f) { return parseInt(f.cantidad); }),
                    borderColor: '#9c27b0',
                    tension: 0.1
                }]
            }
        });

        var servicios = data.servicios;
        graficos.servicios = new Chart(document.getElementById('serviciosChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: servicios.map(function (f) { return f.servicio; }),
                datasets: [{
                    data: servicios.map(function (f) { return parseInt(f.cantidad); }),
                    backgroundColor: ['#4a148c', '#880e4f']
                }]
            }
        });

        var circulo = document.querySelector('.progress-circle');
        circulo.dataset.progress = data.total;
        circulo.textContent = data.total;
    }

    document.addEventListener('DOMContentLoaded', cargarDashboard);
    document.querySelector('.filter-section').addEventListener('submit', function (e) {
        e.preventDefault();
        cargarDashboard();
    });
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
