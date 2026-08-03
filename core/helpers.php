<?php
declare(strict_types=1);

function config(string $clave, string $defecto = ''): string
{
    $v = getenv($clave);
    return $v === false || $v === '' ? $defecto : $v;
}

function base_url(string $ruta = ''): string
{
    $base = rtrim(config('APP_URL', 'http://localhost/conteo'), '/');
    return $base . ($ruta !== '' ? '/' . ltrim($ruta, '/') : '');
}

function esFechaValida(string $fecha): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $fecha);
    return $d !== false && $d->format('Y-m-d') === $fecha;
}
