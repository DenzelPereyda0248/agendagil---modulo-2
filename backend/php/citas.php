<?php
include("conexion.php");

$sql = "
SELECT 
    c.idCita,
    c.fecha,
    c.hora,
    c.estado
FROM citas c
ORDER BY c.fecha, c.hora
";

$result = $conn->query($sql);
?>