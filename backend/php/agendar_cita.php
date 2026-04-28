<?php
session_start();
include("conexion.php");

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['idUsuario'])) {
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit();
}

if (($_SESSION['rol'] ?? '') !== 'paciente') {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit();
}

if (
    !isset($_POST['fecha']) ||
    !isset($_POST['tratamiento']) ||
    !isset($_POST['horaInicio']) ||
    !isset($_POST['horaFin']) ||
    !isset($_POST['motivo'])
) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
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

function contarCitasActivasDentista(mysqli $conexion, int $idDentista, string $fecha): int
{
    $sql = "
    SELECT COUNT(*) AS total
    FROM citas
    WHERE idDentista = ?
    AND fecha = ?
    AND UPPER(estado) IN ('CONFIRMADA', 'PENDIENTE')";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("is", $idDentista, $fecha);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return (int) ($row['total'] ?? 0);
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

    return contarCitasActivasDentista($conexion, $idDentista, $fecha) < 8;
}

function buscarDentistaDisponible(mysqli $conexion, string $fecha, string $horaInicio, string $horaFin, ?int $dentistaExcluir = null): ?array
{
    $sql = "
    SELECT
        d.idDentista,
        u.nombre AS nombreDentista
    FROM dentistas d
    INNER JOIN usuarios u ON d.idUsuario = u.idUsuario
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
    ) < 8";

    if ($dentistaExcluir !== null) {
        $sql .= " AND d.idDentista <> ?";
    }

    $sql .= " ORDER BY d.idDentista LIMIT 1";

    $stmt = $conexion->prepare($sql);

    if ($dentistaExcluir !== null) {
        $stmt->bind_param("ssssi", $fecha, $horaFin, $horaInicio, $fecha, $dentistaExcluir);
    } else {
        $stmt->bind_param("ssss", $fecha, $horaFin, $horaInicio, $fecha);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $dentista = $result->fetch_assoc() ?: null;
    $stmt->close();

    return $dentista;
}

function buscarSiguienteHoraDisponible(mysqli $conexion, int $idDentista, string $fecha, string $horaSolicitada): ?array
{
    $horas = [
        '09:00', '10:00', '11:00', '12:00', '13:00',
        '14:00', '15:00', '16:00', '17:00', '18:00', '19:00'
    ];

    $indiceActual = array_search(substr($horaSolicitada, 0, 5), $horas, true);
    if ($indiceActual === false) {
        $indiceActual = -1;
    }

    for ($i = $indiceActual + 1; $i < count($horas); $i++) {
        $horaInicio = $horas[$i];
        $horaFin = date('H:i', strtotime($horaInicio . ' +1 hour'));

        if (estaDentistaDisponible($conexion, $idDentista, $fecha, $horaInicio, $horaFin)) {
            return [
                'horaInicio' => $horaInicio,
                'horaFin' => $horaFin,
                'label' => date('g:i A', strtotime($horaInicio))
            ];
        }
    }

    return null;
}

function insertarCita(
    mysqli $conexion,
    int $idPaciente,
    int $idDentista,
    int $idTratamiento,
    string $motivo,
    string $fecha,
    string $diaSemana,
    string $horaInicio,
    string $horaFin
): bool {
    $sql = "
    INSERT INTO citas (
        idPaciente,
        idDentista,
        idTratamiento,
        descripcion,
        fecha,
        diaSemana,
        horaInicio,
        horaFin,
        estado
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente')";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("iiisssss", $idPaciente, $idDentista, $idTratamiento, $motivo, $fecha, $diaSemana, $horaInicio, $horaFin);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

$paciente = obtenerPaciente($conexion, (int) $_SESSION['idUsuario']);
if (!$paciente) {
    echo json_encode(['success' => false, 'message' => 'Paciente no encontrado']);
    $conexion->close();
    exit();
}

$idPaciente = (int) $paciente['idPaciente'];
$fecha = $_POST['fecha'];
$idTratamiento = (int) $_POST['tratamiento'];
$horaInicio = $_POST['horaInicio'];
$horaFin = $_POST['horaFin'];
$motivo = trim($_POST['motivo']);
$opcion = $_POST['opcion'] ?? '';

$diasSemana = ['Domingo', 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado'];
$diaSemana = $diasSemana[(int) date('w', strtotime($fecha))];

$dentistaPrioritario = obtenerDentistaAsignado($conexion, $idPaciente);
if (!$dentistaPrioritario) {
    $dentistaPrioritario = asignarDentistaPorDefecto($conexion, $idPaciente);
}

if (!$dentistaPrioritario) {
    $dentistaDisponible = buscarDentistaDisponible($conexion, $fecha, $horaInicio, $horaFin);

    if (!$dentistaDisponible) {
        echo json_encode([
            'success' => false,
            'message' => 'No hay dentistas disponibles en ese horario. Intenta otra fecha u hora.'
        ]);
        $conexion->close();
        exit();
    }

    $insertado = insertarCita(
        $conexion,
        $idPaciente,
        (int) $dentistaDisponible['idDentista'],
        $idTratamiento,
        $motivo,
        $fecha,
        $diaSemana,
        $horaInicio,
        $horaFin
    );

    echo json_encode([
        'success' => $insertado,
        'message' => $insertado
            ? 'Cita agendada correctamente con ' . $dentistaDisponible['nombreDentista'] . '.'
            : 'Error al agendar la cita: ' . $conexion->error
    ]);
    $conexion->close();
    exit();
}

$idDentistaPrioritario = (int) $dentistaPrioritario['idDentista'];
$nombreDentistaPrioritario = $dentistaPrioritario['nombreDentista'];
$totalCitasDentista = contarCitasActivasDentista($conexion, $idDentistaPrioritario, $fecha);
$estaDisponible = $totalCitasDentista < 8
    ? estaDentistaDisponible($conexion, $idDentistaPrioritario, $fecha, $horaInicio, $horaFin)
    : false;

if ($opcion === 'otro_dentista') {
    $otroDentista = buscarDentistaDisponible($conexion, $fecha, $horaInicio, $horaFin, $idDentistaPrioritario);

    if (!$otroDentista) {
        echo json_encode([
            'success' => false,
            'message' => 'No hay otro dentista disponible en ese horario. Intenta con otra fecha u hora.'
        ]);
        $conexion->close();
        exit();
    }

    $insertado = insertarCita(
        $conexion,
        $idPaciente,
        (int) $otroDentista['idDentista'],
        $idTratamiento,
        $motivo,
        $fecha,
        $diaSemana,
        $horaInicio,
        $horaFin
    );

    echo json_encode([
        'success' => $insertado,
        'message' => $insertado
            ? 'Cita agendada correctamente con ' . $otroDentista['nombreDentista'] . '.'
            : 'Error al agendar la cita: ' . $conexion->error
    ]);
    $conexion->close();
    exit();
}

if ($opcion === 'siguiente_hora') {
    $siguienteHora = buscarSiguienteHoraDisponible($conexion, $idDentistaPrioritario, $fecha, $horaInicio);

    if (!$siguienteHora) {
        echo json_encode([
            'success' => false,
            'message' => 'Tu dentista asignado no tiene una siguiente hora disponible ese dia. Intenta con otra fecha.'
        ]);
        $conexion->close();
        exit();
    }

    $insertado = insertarCita(
        $conexion,
        $idPaciente,
        $idDentistaPrioritario,
        $idTratamiento,
        $motivo,
        $fecha,
        $diaSemana,
        $siguienteHora['horaInicio'],
        $siguienteHora['horaFin']
    );

    echo json_encode([
        'success' => $insertado,
        'message' => $insertado
            ? 'Cita agendada correctamente con ' . $nombreDentistaPrioritario . ' a las ' . $siguienteHora['label'] . '.'
            : 'Error al agendar la cita: ' . $conexion->error
    ]);
    $conexion->close();
    exit();
}

if ($estaDisponible) {
    $insertado = insertarCita(
        $conexion,
        $idPaciente,
        $idDentistaPrioritario,
        $idTratamiento,
        $motivo,
        $fecha,
        $diaSemana,
        $horaInicio,
        $horaFin
    );

    echo json_encode([
        'success' => $insertado,
        'message' => $insertado
            ? 'Cita agendada correctamente con tu dentista asignado: ' . $nombreDentistaPrioritario . '.'
            : 'Error al agendar la cita: ' . $conexion->error
    ]);
    $conexion->close();
    exit();
}

if ($totalCitasDentista >= 8) {
    echo json_encode([
        'success' => false,
        'requiresDecision' => true,
        'decisionType' => 'otro_dentista_o_otro_dia',
        'message' => 'Tu dentista asignado, ' . $nombreDentistaPrioritario . ', ya tiene 8 citas asignadas y esta totalmente ocupado el dia ' . $fecha . '.'
    ]);
    $conexion->close();
    exit();
}

$siguienteHora = buscarSiguienteHoraDisponible($conexion, $idDentistaPrioritario, $fecha, $horaInicio);

echo json_encode([
    'success' => false,
    'requiresDecision' => true,
    'decisionType' => 'otro_dentista_o_siguiente_hora',
    'message' => 'Tu dentista asignado, ' . $nombreDentistaPrioritario . ', no esta disponible en el horario solicitado.',
    'nextAvailableHour' => $siguienteHora['horaInicio'] ?? null,
    'nextAvailableLabel' => $siguienteHora['label'] ?? null
]);

$conexion->close();
?>
