<?php
include("conexion.php");
require_once 'reminder_service.php';

date_default_timezone_set('America/Mexico_City');

header('Content-Type: text/plain; charset=utf-8');

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Este script solo puede ejecutarse desde la linea de comandos.\n";
    exit();
}

$modoPrueba = in_array('--modo-prueba', $argv, true) || in_array('--dry-run', $argv, true);
$resultado = procesarRecordatoriosCitas($conexion, $modoPrueba);

foreach ($resultado['mensajes'] as $mensaje) {
    echo $mensaje . PHP_EOL;
}

$conexion->close();
?>
