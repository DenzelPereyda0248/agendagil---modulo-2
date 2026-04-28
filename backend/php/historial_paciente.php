<?php
session_start();

// Verificar si el usuario está autenticado
if(!isset($_SESSION['idUsuario'])){
    echo json_encode(["error" => "Sesión no válida"]);
    exit();
}

// Verificar que el usuario es paciente
if($_SESSION['rol'] !== 'paciente'){
    echo json_encode(["error" => "Acceso denegado"]);
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
    echo json_encode(["error" => "Paciente no encontrado"]);
    exit();
}

$paciente = $result_p->fetch_assoc();
$idPaciente = $paciente['idPaciente'];

// Obtener historial clínico del paciente
$sql = "
SELECT
    hc.motivoConsulta,
    hc.diagnostico,
    hc.tratamientoAplicado,
    hc.observaciones,
    hc.fechaRegistro,
    c.fecha AS fechaCita,
    t.nombreTratamiento,
    d.nombre AS nombreDentista
FROM historialclinico hc
LEFT JOIN citas c ON hc.idCita = c.idCita
LEFT JOIN tratamientos t ON c.idTratamiento = t.idTratamiento
LEFT JOIN dentistas dt ON c.idDentista = dt.idDentista
LEFT JOIN usuarios d ON dt.idUsuario = d.idUsuario
WHERE hc.idPaciente = ?
ORDER BY hc.fechaRegistro DESC";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $idPaciente);
$stmt->execute();
$result = $stmt->get_result();

$historial = [];
while($row = $result->fetch_assoc()){
    $historial[] = $row;
}

echo json_encode($historial);

$stmt->close();
$conexion->close();
?>