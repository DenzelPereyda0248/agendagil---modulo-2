<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['idUsuario'])) {
    echo json_encode(["error" => "Sesion no valida"]);
    exit();
}

include("conexion.php");

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(["error" => "Cita invalida"]);
    exit();
}

$idCita = (int) $_GET['id'];
$idUsuario = (int) $_SESSION['idUsuario'];
$rol = $_SESSION['rol'] ?? '';

$sql = "
SELECT
    c.idCita,
    c.fecha,
    c.horaInicio,
    c.horaFin,
    c.estado,
    c.descripcion,
    u.nombre AS paciente,
    t.nombreTratamiento,
    du.nombre AS dentista
FROM citas c
INNER JOIN pacientes p ON c.idPaciente = p.idPaciente
INNER JOIN usuarios u ON p.idUsuario = u.idUsuario
INNER JOIN tratamientos t ON c.idTratamiento = t.idTratamiento
INNER JOIN dentistas d ON c.idDentista = d.idDentista
INNER JOIN usuarios du ON d.idUsuario = du.idUsuario
WHERE c.idCita = ?
";

if ($rol === 'paciente') {
    $sql .= " AND p.idUsuario = ?";
} elseif ($rol === 'dentista') {
    $sql .= " AND d.idUsuario = ?";
} else {
    echo json_encode(["error" => "Acceso denegado"]);
    exit();
}

$stmt = $conexion->prepare($sql);
$stmt->bind_param("ii", $idCita, $idUsuario);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    echo json_encode(["error" => "Error en la consulta: " . $conexion->error]);
    exit();
}

$data = $result->fetch_assoc();

if ($data) {
    echo json_encode($data);
} else {
    echo json_encode(["error" => "Cita no encontrada"]);
}

$stmt->close();
$conexion->close();
?>
