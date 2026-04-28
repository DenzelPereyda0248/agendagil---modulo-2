<?php
session_start();
include("conexion.php");

// Verificar si el usuario está autenticado
if(!isset($_SESSION['idUsuario'])){
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit();
}

// Verificar que el usuario es paciente
if($_SESSION['rol'] !== 'paciente'){
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit();
}

// Obtener idPaciente del usuario logueado
$sql_paciente = "SELECT idPaciente FROM pacientes WHERE idUsuario = ?";
$stmt_p = $conexion->prepare($sql_paciente);
$stmt_p->bind_param("i", $_SESSION['idUsuario']);
$stmt_p->execute();
$result_p = $stmt_p->get_result();

if ($result_p->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Paciente no encontrado']);
    exit();
}

$paciente = $result_p->fetch_assoc();
$idPaciente = $paciente['idPaciente'];

// Verificar que se recibió el ID de la cita
if (!isset($_POST['idCita']) || empty($_POST['idCita'])) {
    echo json_encode(['success' => false, 'message' => 'ID de cita requerido']);
    exit();
}

$idCita = $_POST['idCita'];

// Verificar que la cita pertenece al paciente y está en estado cancelable
$sql_verificar = "SELECT estado FROM citas WHERE idCita = ? AND idPaciente = ?";
$stmt_verificar = $conexion->prepare($sql_verificar);
$stmt_verificar->bind_param("ii", $idCita, $idPaciente);
$stmt_verificar->execute();
$result_verificar = $stmt_verificar->get_result();

if ($result_verificar->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Cita no encontrada o no pertenece al paciente']);
    exit();
}

$cita = $result_verificar->fetch_assoc();

// Solo permitir cancelar citas pendientes o confirmadas
if ($cita['estado'] !== 'Pendiente' && $cita['estado'] !== 'Confirmada') {
    echo json_encode(['success' => false, 'message' => 'Solo se pueden cancelar citas pendientes o confirmadas']);
    exit();
}

// Eliminar la cita
$sql_delete = "DELETE FROM citas WHERE idCita = ? AND idPaciente = ?";
$stmt_delete = $conexion->prepare($sql_delete);
$stmt_delete->bind_param("ii", $idCita, $idPaciente);

if ($stmt_delete->execute()) {
    echo json_encode(['success' => true, 'message' => 'Cita cancelada correctamente']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al cancelar la cita: ' . $conexion->error]);
}

$stmt_delete->close();
$stmt_verificar->close();
$stmt_p->close();
$conexion->close();
?>