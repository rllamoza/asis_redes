<?php
declare(strict_types=1);

Session::destruir();
header('Location: index.php?ruta=login');
exit;
