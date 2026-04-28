<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if(!isset($_SESSION['idUsuario'])){
    echo json_encode(['error' => 'Sesión no válida']);
    exit();
}

include("conexion.php");

// Obtener idPaciente del usuario logueado
$sql_paciente = "SELECT idPaciente FROM pacientes WHERE idUsuario = ?";
$stmt_p = $conexion->prepare($sql_paciente);
$stmt_p->bind_param("i", $_SESSION['idUsuario']);
$stmt_p->execute();
$result_p = $stmt_p->get_result();

if ($result_p->num_rows == 0) {
    echo json_encode(['error' => 'Paciente no encontrado']);
    exit();
}

$paciente = $result_p->fetch_assoc();
$idPaciente = $paciente['idPaciente'];

$sql_historial = "
SELECT
    idHistorial,
    idPaciente,
    idCita,
    motivoConsulta,
    diagnostico,
    tratamientoAplicado,
    observaciones,
    fechaRegistro
FROM historialclinico
WHERE idPaciente = ?
ORDER BY fechaRegistro DESC
";

$stmt_historial = $conexion->prepare($sql_historial);
$stmt_historial->bind_param("i", $idPaciente);
$stmt_historial->execute();
$result_historial = $stmt_historial->get_result();

$historial = [];
while ($row = $result_historial->fetch_assoc()) {
    $historial[] = $row;
}

echo json_encode($historial);

$stmt_historial->close();
$stmt_p->close();
$conexion->close();
?>