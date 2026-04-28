<?php
session_start();
include("conexion.php");

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['idUsuario'])) {
    echo json_encode(["error" => "Usuario no autenticado"]);
    exit();
}

if (($_SESSION['rol'] ?? '') !== 'paciente') {
    echo json_encode(["error" => "Acceso denegado"]);
    exit();
}

if (!isset($_GET['fecha']) || empty($_GET['fecha'])) {
    echo json_encode(["error" => "Fecha requerida"]);
    exit();
}

function obtenerPaciente(mysqli $conexion, int $idUsuario): ?array
{
    $sql = "SELECT idPaciente, idDentistaAsignado FROM pacientes WHERE idUsuario = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $idUsuario);
    $stmt->execute();
    $result = $stmt->get_result();
    $paciente = $result->fetch_assoc() ?: null;
    $stmt->close();

    return $paciente;
}

function obtenerDentistaAsignado(mysqli $conexion, int $idPaciente): ?array
{
    $sql = "
    SELECT
        d.idDentista,
        u.nombre AS nombreDentista
    FROM pacientes p
    INNER JOIN dentistas d ON p.idDentistaAsignado = d.idDentista
    INNER JOIN usuarios u ON d.idUsuario = u.idUsuario
    WHERE p.idPaciente = ?
    LIMIT 1";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $idPaciente);
    $stmt->execute();
    $result = $stmt->get_result();
    $dentista = $result->fetch_assoc() ?: null;
    $stmt->close();

    return $dentista;
}

function asignarDentistaPorDefecto(mysqli $conexion, int $idPaciente): ?array
{
    $sql = "
    SELECT
        d.idDentista,
        u.nombre AS nombreDentista
    FROM dentistas d
    INNER JOIN usuarios u ON d.idUsuario = u.idUsuario
    ORDER BY d.idDentista ASC
    LIMIT 1";

    $result = $conexion->query($sql);
    $dentista = $result ? ($result->fetch_assoc() ?: null) : null;

    if (!$dentista) {
        return null;
    }

    $stmt = $conexion->prepare("UPDATE pacientes SET idDentistaAsignado = ? WHERE idPaciente = ?");
    $idDentista = (int) $dentista['idDentista'];
    $stmt->bind_param("ii", $idDentista, $idPaciente);
    $stmt->execute();
    $stmt->close();

    return $dentista;
}

function estaDentistaDisponible(mysqli $conexion, int $idDentista, string $fecha, string $horaInicio, string $horaFin): bool
{
    $sql = "
    SELECT d.idDentista
    FROM dentistas d
    WHERE d.idDentista = ?
    AND NOT EXISTS (
        SELECT 1
        FROM citas c
        WHERE c.idDentista = d.idDentista
        AND c.fecha = ?
        AND UPPER(c.estado) IN ('CONFIRMADA', 'PENDIENTE')
        AND c.horaInicio < ?
        AND c.horaFin > ?
    )
    LIMIT 1";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("isss", $idDentista, $fecha, $horaFin, $horaInicio);
    $stmt->execute();
    $result = $stmt->get_result();
    $disponible = $result->num_rows > 0;
    $stmt->close();

    if (!$disponible) {
        return false;
    }

    $sqlTotal = "
    SELECT COUNT(*) AS total
    FROM citas
    WHERE idDentista = ?
    AND fecha = ?
    AND UPPER(estado) IN ('CONFIRMADA', 'PENDIENTE')";

    $stmtTotal = $conexion->prepare($sqlTotal);
    $stmtTotal->bind_param("is", $idDentista, $fecha);
    $stmtTotal->execute();
    $resultTotal = $stmtTotal->get_result();
    $rowTotal = $resultTotal->fetch_assoc();
    $stmtTotal->close();

    return ((int) ($rowTotal['total'] ?? 0)) < 8;
}

function existeDentistaDisponible(mysqli $conexion, string $fecha, string $horaInicio, string $horaFin): bool
{
    $sql = "
    SELECT d.idDentista
    FROM dentistas d
    WHERE NOT EXISTS (
        SELECT 1
        FROM citas c
        WHERE c.idDentista = d.idDentista
        AND c.fecha = ?
        AND UPPER(c.estado) IN ('CONFIRMADA', 'PENDIENTE')
        AND c.horaInicio < ?
        AND c.horaFin > ?
    )
    AND (
        SELECT COUNT(*)
        FROM citas c2
        WHERE c2.idDentista = d.idDentista
        AND c2.fecha = ?
        AND UPPER(c2.estado) IN ('CONFIRMADA', 'PENDIENTE')
    ) < 8
    LIMIT 1";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("ssss", $fecha, $horaFin, $horaInicio, $fecha);
    $stmt->execute();
    $result = $stmt->get_result();
    $disponible = $result->num_rows > 0;
    $stmt->close();

    return $disponible;
}

$paciente = obtenerPaciente($conexion, (int) $_SESSION['idUsuario']);
if (!$paciente) {
    echo json_encode(["error" => "Paciente no encontrado"]);
    $conexion->close();
    exit();
}

$dentistaPrioritario = obtenerDentistaAsignado($conexion, (int) $paciente['idPaciente']);
if (!$dentistaPrioritario) {
    $dentistaPrioritario = asignarDentistaPorDefecto($conexion, (int) $paciente['idPaciente']);
}
$fecha = $_GET['fecha'];

$horas = [
    '09:00', '10:00', '11:00', '12:00', '13:00',
    '14:00', '15:00', '16:00', '17:00', '18:00', '19:00'
];

$disponibles = [];

foreach ($horas as $horaInicio) {
    $horaFin = date('H:i', strtotime($horaInicio . ' +1 hour'));

    if (!existeDentistaDisponible($conexion, $fecha, $horaInicio, $horaFin)) {
        continue;
    }

    $label = date('g:i A', strtotime($horaInicio));
    $prioritarioDisponible = false;

    if ($dentistaPrioritario) {
        $prioritarioDisponible = estaDentistaDisponible(
            $conexion,
            (int) $dentistaPrioritario['idDentista'],
            $fecha,
            $horaInicio,
            $horaFin
        );

        if ($prioritarioDisponible) {
            $label .= ' - Tu dentista asignado';
        } else {
            $label .= ' - Otro dentista disponible';
        }
    } else {
        $label .= ' - Dentista disponible';
    }

    $disponibles[] = [
        'horaInicio' => $horaInicio,
        'label' => $label,
        'prioritarioDisponible' => $prioritarioDisponible
    ];
}

$conexion->close();
echo json_encode($disponibles);
?>
