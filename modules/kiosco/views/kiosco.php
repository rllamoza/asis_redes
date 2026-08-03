<?php
declare(strict_types=1);

$kiosco = new KioscoService();
$personas = $kiosco->personas();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Control de Asistencia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/kiosco.css">
</head>
<body>
    <header class="app-header text-center">
        <h1><i class="fas fa-fingerprint me-2"></i>Control de Asistencia</h1>
        <p class="lead">Registro digital de participación</p>
    </header>

    <main class="container pb-5">
        <div class="row g-3" id="personButtons">
            <?php foreach ($personas as $persona): ?>
                <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                    <button type="button"
                            data-nombre="<?= htmlspecialchars($persona['nombre']) ?>"
                            class="btn btn-person btn-outline-primary w-100 py-3 position-relative pulsar-btn">
                        <i class="fas fa-user me-1"></i>
                        <span class="d-inline-block text-truncate" style="max-width: 80px;"><?= htmlspecialchars($persona['nombre']) ?></span>
                        <span class="badge bg-danger rounded-pill contador-btn"><?= $persona['contador'] ?></span>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function () {
            $('.pulsar-btn').click(function () {
                const boton = $(this);
                const nombre = boton.data('nombre');

                boton.addClass('btn-loading');
                boton.prop('disabled', true);

                $.ajax({
                    url: 'index.php?ruta=kiosco/api&action=pulsar',
                    method: 'POST',
                    data: { nombre: nombre },
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            boton.find('.contador-btn').text(response.contador);
                            boton.removeClass('btn-outline-primary').addClass('btn-outline-success');
                            setTimeout(function () {
                                boton.removeClass('btn-outline-success').addClass('btn-outline-primary');
                            }, 1000);
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function (xhr, status, error) {
                        alert('Error en la petición: ' + error);
                    },
                    complete: function () {
                        boton.removeClass('btn-loading');
                        boton.prop('disabled', false);
                    }
                });
            });
        });
    </script>
</body>
</html>
