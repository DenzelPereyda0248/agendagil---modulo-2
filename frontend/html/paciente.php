<?php
session_start();

// Verificar si el usuario está autenticado
if(!isset($_SESSION['idUsuario'])){
    header("Location: inicioSesion.php");
    exit();
}

// Verificar que el usuario es paciente
if($_SESSION['rol'] !== 'paciente'){
    header("Location: inicioSesion.php?error=Acceso_denegado");
    exit();
}

include("../php/conexion.php");

// Obtener paciente y su dentista asignado
$sql_paciente = "
SELECT
    p.idPaciente,
    p.idDentistaAsignado,
    u.nombre AS nombreDentistaAsignado
FROM pacientes p
LEFT JOIN dentistas d ON p.idDentistaAsignado = d.idDentista
LEFT JOIN usuarios u ON d.idUsuario = u.idUsuario
WHERE p.idUsuario = ?
";
$stmt_p = $conexion->prepare($sql_paciente);
$stmt_p->bind_param("i", $_SESSION['idUsuario']);
$stmt_p->execute();
$result_p = $stmt_p->get_result();

if ($result_p->num_rows > 0) {
    $paciente = $result_p->fetch_assoc();
    $idPaciente = $paciente['idPaciente'];
    $idDentistaAsignado = $paciente['idDentistaAsignado'] ?? null;
    $nombreDentistaAsignado = $paciente['nombreDentistaAsignado'] ?? null;
} else {
    die("Paciente no encontrado");
}

if (!$idDentistaAsignado) {
    $sql_primer_dentista = "
    SELECT
        d.idDentista,
        u.nombre AS nombreDentistaAsignado
    FROM dentistas d
    INNER JOIN usuarios u ON d.idUsuario = u.idUsuario
    ORDER BY d.idDentista ASC
    LIMIT 1
    ";
    $result_primer_dentista = $conexion->query($sql_primer_dentista);

    if ($result_primer_dentista && $primerDentista = $result_primer_dentista->fetch_assoc()) {
        $idDentistaAsignado = $primerDentista['idDentista'];
        $nombreDentistaAsignado = $primerDentista['nombreDentistaAsignado'];

        $stmt_actualizar_dentista = $conexion->prepare("UPDATE pacientes SET idDentistaAsignado = ? WHERE idPaciente = ?");
        $stmt_actualizar_dentista->bind_param("ii", $idDentistaAsignado, $idPaciente);
        $stmt_actualizar_dentista->execute();
        $stmt_actualizar_dentista->close();
    }
}

// Obtener tratamientos disponibles
$sql_tratamientos = "SELECT idTratamiento, nombreTratamiento FROM tratamientos ORDER BY nombreTratamiento";
$result_tratamientos = $conexion->query($sql_tratamientos);

$sql = "
SELECT 
    idCita,
    fecha,
    horaInicio,
    horaFin,
    estado,
    idTratamiento
FROM citas
WHERE idPaciente = ?
ORDER BY fecha, horaInicio
";

// Preparar la consulta
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $idPaciente);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    die("Error en la consulta: " . $conexion->error);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis citas - Golden Tooth</title>
    <link rel="stylesheet" href="../css/paciente.css">
</head>
<body>

<header class="header">
    <h1>Golden Tooth</h1>
    <div class="user">
        <span><?php echo $_SESSION['nombre']; ?></span>
        <button class="logout" onclick="location.href='../php/logout.php'">Cerrar sesión</button>
    </div>
</header>

<main class="container">
    <h2>Mis citas</h2>

    <div class="grid">

        <!-- Solicitar cita -->
        <div class="card">
            <h3>Solicitar Cita</h3>

            <label>Dentista asignado</label>
            <input type="text" id="dentistaAsignadoDisplay" readonly value="<?php echo htmlspecialchars($nombreDentistaAsignado ?: 'Aun no tienes un dentista asignado'); ?>">
            <p class="availability-legend">
                <span class="legend-pill legend-priority">Horario con tu dentista asignado</span>
                <span class="legend-pill legend-other">Horario con otro dentista disponible</span>
            </p>

            <label>Seleccionar fecha</label>
            <div class="date-picker-container">
                <input type="text" id="fechaCitaDisplay" readonly placeholder="Haz click para seleccionar fecha" onclick="mostrarPanelFechas()">
                <input type="hidden" id="fechaCita">
                <div id="panelFechas" class="date-panel" style="display: none;">
                    <div class="date-panel-header">
                        <h4>Seleccionar Fecha</h4>
                        <button type="button" onclick="ocultarPanelFechas()">×</button>
                    </div>
                    <div id="fechasDisponibles" class="date-options">
                        <!-- Las fechas se generarán aquí dinámicamente -->
                    </div>
                </div>
            </div>

            <label>Tratamiento</label>
            <div class="date-picker-container">
                <input type="text" id="tratamientoCitaDisplay" readonly placeholder="Haz click para seleccionar tratamiento" onclick="mostrarPanelTratamientos()">
                <input type="hidden" id="tratamientoCita">
                <div id="panelTratamientos" class="date-panel" style="display: none;">
                    <div class="date-panel-header">
                        <h4>Seleccionar Tratamiento</h4>
                        <button type="button" onclick="ocultarPanelTratamientos()">×</button>
                    </div>
                    <div id="tratamientosDisponibles" class="date-options">
                        <?php while($row = $result_tratamientos->fetch_assoc()): ?>
                            <button type="button" class="date-option" onclick="seleccionarTratamiento('<?php echo $row['idTratamiento']; ?>', '<?php echo htmlspecialchars($row['nombreTratamiento'], ENT_QUOTES); ?>')">
                                <div class="dia-semana"><?php echo htmlspecialchars($row['nombreTratamiento']); ?></div>
                            </button>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

            <label>Hora Inicio</label>
            <div class="date-picker-container">
                <input type="text" id="horaInicioCitaDisplay" readonly placeholder="Selecciona fecha primero" onclick="mostrarPanelHoras()">
                <input type="hidden" id="horaInicioCita">
                <div id="panelHoras" class="date-panel" style="display: none;">
                    <div class="date-panel-header">
                        <h4>Seleccionar Hora</h4>
                        <button type="button" onclick="ocultarPanelHoras()">×</button>
                    </div>
                    <div id="horasDisponibles" class="date-options">
                        <!-- Las horas se generarán aquí dinámicamente -->
                    </div>
                </div>
            </div>

            <label>Hora Fin</label>
            <select id="horaFinCita">
                <option value="">-- Se actualiza automáticamente --</option>
            </select>

            <label>Motivo de la cita</label>
            <textarea id="motivoCita" placeholder="Describe tu consulta..."></textarea>

            <button class="primary" onclick="confirmarCita()">Confirmar Cita</button>
        </div>

        <!-- Tabla citas -->
        <div class="card">
            <h3>Mis Citas Agendadas</h3>

            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Hora Inicio</th>
                        <th>Hora Fin</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['fecha']; ?></td>
                        <td><?php echo $row['horaInicio']; ?></td>
                        <td><?php echo $row['horaFin']; ?></td>
                        <td><span class="status <?php echo strtolower($row['estado']); ?>"><?php echo $row['estado']; ?></span></td>
                        <td>
                            <button class="view" onclick="verDetalle(<?php echo $row['idCita']; ?>)">Ver mas</button>
                            <?php if ($row['estado'] === 'Pendiente' || $row['estado'] === 'Confirmada'): ?>
                            <button class="icon cancel-btn" onclick="cancelarCita(<?php echo $row['idCita']; ?>)">❌</button>
                            <?php endif; ?>
                            <button class="icon">🦷</button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Historial -->
        <div class="card full">
            <h3>Historial Odontológico</h3>
            <button class="primary" onclick="consultarHistorial()">Consultar</button>
        </div>

    </div>
</main>

<div id="modalDetalle" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Detalle de Cita</h3>
        <p><strong>Paciente:</strong> <span id="detallePaciente"></span></p>
        <p><strong>Dentista:</strong> <span id="detalleDentista"></span></p>
        <p><strong>Tratamiento:</strong> <span id="detalleTratamiento"></span></p>
        <p><strong>Descripción:</strong> <span id="detalleDescripcion"></span></p>
        <p><strong>Fecha:</strong> <span id="detalleFecha"></span></p>
        <p><strong>Hora:</strong> <span id="detalleHora"></span></p>
        <p><strong>Estado:</strong> <span id="detalleEstado"></span></p>
    </div>
</div>

<div id="modalHistorial" class="modal">
    <div class="modal-content">
        <span class="close-historial">&times;</span>
        <h3>Historial Odontológico</h3>
        <p id="historialPacienteLabel"></p>
        <div class="history-container">
            <div id="historialRecords"></div>
            <p id="historialEmpty">No se encontraron registros de historial para este paciente.</p>
        </div>
    </div>
</div>

<script>
const pacienteIdActual = <?php echo json_encode($idPaciente); ?>;
// Función para actualizar la hora fin automáticamente
function actualizarHoraFin() {
    const horaInicio = document.getElementById('horaInicioCita').value;
    const horaFinSelect = document.getElementById('horaFinCita');
    
    if (!horaInicio) {
        horaFinSelect.innerHTML = '<option value="">-- Se actualiza automáticamente --</option>';
        return;
    }

    // Dividir la hora inicio
    const [horas, minutos] = horaInicio.split(':');
    let horasNum = parseInt(horas);
    
    // Calcular la hora fin (una hora después)
    let horaFinNum = horasNum + 1;
    
    // Validar que no supere las 8PM (20:00)
    if (horaFinNum > 20) {
        alert('No puedes seleccionar esta hora ya que la cita se extendería después de las 8 PM');
        document.getElementById('horaInicioCita').value = '';
        horaFinSelect.innerHTML = '<option value="">-- Se actualiza automáticamente --</option>';
        return;
    }
    
    // Formatear la hora fin
    const horaFinFormato = String(horaFinNum).padStart(2, '0') + ':' + minutos;
    
    // Actualizar el select de hora fin
    let horaFinTexto = '';
    horaFinTexto = formatearHoraEs(horaFinFormato);
    horaFinSelect.innerHTML = '<option value="' + horaFinFormato + '">' + horaFinTexto + '</option>';
}

// Función para confirmar cita
function confirmarCita() {
    enviarSolicitudCita();
}

function enviarSolicitudCita(opcion = '') {
    const fecha = document.getElementById('fechaCita').value;
    const fechaDisplay = document.getElementById('fechaCitaDisplay').value;
    const tratamiento = document.getElementById('tratamientoCita').value;
    const horaInicio = document.getElementById('horaInicioCita').value;
    const horaFin = document.getElementById('horaFinCita').value;
    const motivo = document.getElementById('motivoCita').value;

    if (!fecha || !fechaDisplay || !tratamiento || !horaInicio || !horaFin || !motivo) {
        alert('Por favor completa todos los campos');
        return;
    }

    // Crear FormData para enviar los datos
    const formData = new FormData();
    formData.append('fecha', fecha);
    formData.append('tratamiento', tratamiento);
    formData.append('horaInicio', horaInicio);
    formData.append('horaFin', horaFin);
    formData.append('motivo', motivo);
    if (opcion) {
        formData.append('opcion', opcion);
    }

    // Enviar la petición AJAX
    fetch('../php/agendar_cita.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            // Limpiar el formulario
            document.getElementById('fechaCita').value = '';
            document.getElementById('fechaCitaDisplay').value = '';
            document.getElementById('tratamientoCita').value = '';
            document.getElementById('horaInicioCita').value = '';
            document.getElementById('horaInicioCitaDisplay').value = '';
            document.getElementById('horaFinCita').innerHTML = '<option value="">-- Se actualiza automáticamente --</option>';
            document.getElementById('motivoCita').value = '';
            // Recargar la página para mostrar la nueva cita
            location.reload();
        } else if (data.requiresDecision) {
            manejarDecisionAgendado(data);
        } else {
            alert(data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al procesar la solicitud');
    });
}

// Función para formatear fechas en español
function manejarDecisionAgendado(data) {
    if (data.decisionType === 'otro_dentista_o_siguiente_hora') {
        const mensaje = `${data.message}\n\nPresiona "Aceptar" para asignar otro dentista disponible.${data.nextAvailableLabel ? '\nPresiona "Cancelar" para agendar con tu dentista asignado en la siguiente hora disponible: ' + data.nextAvailableLabel : ''}`;
        const deseaOtroDentista = confirm(mensaje);

        if (deseaOtroDentista) {
            enviarSolicitudCita('otro_dentista');
            return;
        }

        if (data.nextAvailableLabel) {
            enviarSolicitudCita('siguiente_hora');
            return;
        }

        alert('No se encontro una siguiente hora disponible con tu dentista asignado. Intenta con otra fecha.');
        return;
    }

    if (data.decisionType === 'otro_dentista_o_otro_dia') {
        const deseaOtroDentista = confirm(`${data.message}\n\nPresiona "Aceptar" para asignar otro dentista disponible.\nPresiona "Cancelar" para seleccionar otro dia.`);

        if (deseaOtroDentista) {
            enviarSolicitudCita('otro_dentista');
            return;
        }

        alert('Selecciona otra fecha para continuar con tu dentista asignado.');
        return;
    }

    alert(data.message || 'No fue posible agendar la cita.');
}

function formatearFechaEs(dateString) {
    if (!dateString) return '-';

    const diasSemana = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    const meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    let year, month, day;
    const fechaParte = dateString.split('T')[0];
    const partes = fechaParte.split('-');
    if (partes.length === 3) {
        year = parseInt(partes[0], 10);
        month = parseInt(partes[1], 10) - 1;
        day = parseInt(partes[2], 10);
    }

    const fecha = (typeof year === 'number' && !Number.isNaN(year))
        ? new Date(year, month, day)
        : new Date(dateString);

    if (Number.isNaN(fecha.getTime())) return dateString;

    const anio = fecha.getFullYear();
    const diaSemana = diasSemana[fecha.getDay()];
    const diaMes = fecha.getDate();
    const mes = meses[fecha.getMonth()];

    return `${diaSemana}, ${diaMes} de ${mes} de ${anio}`;
}

function formatearHoraEs(timeString) {
    if (!timeString) return '-';

    const partes = timeString.split(':');
    let horas = parseInt(partes[0], 10);
    const minutos = partes[1] || '00';
    if (Number.isNaN(horas)) return timeString;

    const periodo = horas >= 12 ? 'PM' : 'AM';
    if (horas === 0) horas = 12;
    if (horas > 12) horas -= 12;

    return `${horas}:${minutos} ${periodo}`;
}

function formatearFechaLocalParaInput(fecha) {
    const anio = fecha.getFullYear();
    const mes = String(fecha.getMonth() + 1).padStart(2, '0');
    const dia = String(fecha.getDate()).padStart(2, '0');
    return `${anio}-${mes}-${dia}`;
}

// Función para mostrar el panel de fechas
function mostrarPanelFechas() {
    const panel = document.getElementById('panelFechas');
    const contenedorFechas = document.getElementById('fechasDisponibles');

    // Limpiar fechas anteriores
    contenedorFechas.innerHTML = '';

    // Generar fechas desde hoy hasta 10 días después
    const hoy = new Date();
    const diasSemana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    for (let i = 0; i <= 10; i++) {
        const fecha = new Date(hoy);
        fecha.setDate(hoy.getDate() + i);
        fecha.setHours(12, 0, 0, 0);

        const diaSemana = diasSemana[fecha.getDay()];
        const diaMes = fecha.getDate();
        const mes = meses[fecha.getMonth()];
        const anio = fecha.getFullYear();

        // Formatear fecha para el input hidden (YYYY-MM-DD)
        const fechaFormato = formatearFechaLocalParaInput(fecha);

        // Crear elemento de fecha
        const fechaElement = document.createElement('button');
        fechaElement.type = 'button';
        fechaElement.className = 'date-option';
        fechaElement.setAttribute('data-fecha', fechaFormato);
        fechaElement.onclick = () => seleccionarFecha(fechaFormato, `${diaSemana}, ${diaMes} de ${mes} ${anio}`);

        fechaElement.innerHTML = `
            <div class="dia-semana">${diaSemana}</div>
            <div class="dia-mes">${diaMes} de ${mes} ${anio}</div>
        `;

        contenedorFechas.appendChild(fechaElement);
    }

    panel.style.display = 'block';
}

// Función para ocultar el panel de fechas
function ocultarPanelFechas() {
    document.getElementById('panelFechas').style.display = 'none';
}

// Función para seleccionar una fecha
function seleccionarFecha(fecha, textoDisplay) {
    document.getElementById('fechaCita').value = fecha;
    document.getElementById('fechaCitaDisplay').value = textoDisplay;
    cargarHorasDisponibles(fecha);
    ocultarPanelFechas();
}

function cargarHorasDisponibles(fecha) {
    const horaInicioDisplay = document.getElementById('horaInicioCitaDisplay');
    const horaInicioHidden = document.getElementById('horaInicioCita');
    const horaFinSelect = document.getElementById('horaFinCita');
    const horasDisponibles = document.getElementById('horasDisponibles');

    horaInicioHidden.value = '';
    horaInicioDisplay.value = '-- Selecciona hora inicio --';
    horaFinSelect.innerHTML = '<option value="">-- Se actualiza automáticamente --</option>';
    horasDisponibles.innerHTML = '<div class="date-option">Cargando horarios...</div>';

    fetch('../php/horas_disponibles.php?fecha=' + encodeURIComponent(fecha))
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                horasDisponibles.innerHTML = `<div class="date-option">${data.error}</div>`;
                return;
            }

            if (!data.length) {
                horasDisponibles.innerHTML = '<div class="date-option">No hay horarios disponibles</div>';
                return;
            }

            horasDisponibles.innerHTML = '';
            data.forEach(item => {
                const option = document.createElement('button');
                option.type = 'button';
                option.className = item.prioritarioDisponible ? 'date-option priority-slot' : 'date-option other-slot';
                option.textContent = item.label ? item.label : formatearHoraEs(item.horaInicio);
                option.onclick = () => seleccionarHora(item.horaInicio, option.textContent);
                horasDisponibles.appendChild(option);
            });
        })
        .catch(error => {
            console.error('Error al cargar horas disponibles:', error);
            horasDisponibles.innerHTML = '<div class="date-option">Error cargando horarios</div>';
        });
}

function seleccionarHora(hora, textoDisplay) {
    document.getElementById('horaInicioCita').value = hora;
    document.getElementById('horaInicioCitaDisplay').value = textoDisplay;
    actualizarHoraFin();
    ocultarPanelHoras();
}

function mostrarPanelHoras() {
    const fecha = document.getElementById('fechaCita').value;
    if (!fecha) {
        alert('Primero selecciona una fecha');
        return;
    }
    document.getElementById('panelHoras').style.display = 'block';
}

function ocultarPanelHoras() {
    document.getElementById('panelHoras').style.display = 'none';
}

function seleccionarTratamiento(id, nombre) {
    document.getElementById('tratamientoCita').value = id;
    document.getElementById('tratamientoCitaDisplay').value = nombre;
    ocultarPanelTratamientos();
}

function mostrarPanelTratamientos() {
    document.getElementById('panelTratamientos').style.display = 'block';
}

function ocultarPanelTratamientos() {
    document.getElementById('panelTratamientos').style.display = 'none';
}

// Cerrar panel al hacer click fuera
document.addEventListener('click', function(event) {
    const panelFechas = document.getElementById('panelFechas');
    const inputFecha = document.getElementById('fechaCitaDisplay');
    const panelHoras = document.getElementById('panelHoras');
    const inputHora = document.getElementById('horaInicioCitaDisplay');

    if (!panelFechas.contains(event.target) && event.target !== inputFecha) {
        ocultarPanelFechas();
    }

    if (!panelHoras.contains(event.target) && event.target !== inputHora) {
        ocultarPanelHoras();
    }

    const panelTratamientos = document.getElementById('panelTratamientos');
    const inputTratamiento = document.getElementById('tratamientoCitaDisplay');
    if (panelTratamientos && !panelTratamientos.contains(event.target) && event.target !== inputTratamiento) {
        ocultarPanelTratamientos();
    }
});

function consultarHistorial() {
    fetch('../php/historial_paciente.php')
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                return;
            }

            const historialRecords = document.getElementById('historialRecords');
            const historialEmpty = document.getElementById('historialEmpty');
            const historialLabel = document.getElementById('historialPacienteLabel');

            historialRecords.innerHTML = '';
            historialLabel.textContent = 'Historial del paciente #' + pacienteIdActual;

            if (!data.length) {
                historialEmpty.style.display = 'block';
                document.getElementById('modalHistorial').style.display = 'block';
                return;
            }

            historialEmpty.style.display = 'none';
            data.forEach((item, index) => {
                const recordDiv = document.createElement('div');
                recordDiv.className = 'history-record';
                recordDiv.innerHTML = `
                    <div class="history-record-header">
                        <h4>Registro #${index + 1}</h4>
                    </div>
                    <div class="history-dashboard">
                        <!-- Fila 1: Fecha de cita, Dentista -->
                        <div class="dashboard-row">
                            <div class="dashboard-card">
                                <div class="card-label">FECHA DE CITA</div>
                                <div class="card-value">${formatearFechaEs(item.fechaCita)}</div>
                            </div>
                            <div class="dashboard-card">
                                <div class="card-label">DENTISTA</div>
                                <div class="card-value">${item.nombreDentista || '-'}</div>
                            </div>
                        </div>

                        <!-- Fila 2: Tratamiento, Diagnóstico -->
                        <div class="dashboard-row">
                            <div class="dashboard-card">
                                <div class="card-label">TRATAMIENTO</div>
                                <div class="card-value">${item.nombreTratamiento || '-'}</div>
                            </div>
                            <div class="dashboard-card">
                                <div class="card-label">DIAGNÓSTICO</div>
                                <div class="card-value">${item.diagnostico || '-'}</div>
                            </div>
                        </div>

                        <!-- Fila 3: Motivo de consulta (ancho completo) -->
                        <div class="dashboard-row">
                            <div class="dashboard-card full-width">
                                <div class="card-label">MOTIVO DE CONSULTA</div>
                                <div class="card-value">${item.motivoConsulta || '-'}</div>
                            </div>
                        </div>

                        <!-- Fila 4: Tratamiento aplicado (ancho completo) -->
                        <div class="dashboard-row">
                            <div class="dashboard-card full-width">
                                <div class="card-label">TRATAMIENTO APLICADO</div>
                                <div class="card-value">${item.tratamientoAplicado || '-'}</div>
                            </div>
                        </div>

                        <!-- Fila 5: Observaciones (ancho completo) -->
                        <div class="dashboard-row">
                            <div class="dashboard-card full-width">
                                <div class="card-label">OBSERVACIONES</div>
                                <div class="card-value">${item.observaciones || '-'}</div>
                            </div>
                        </div>
                    </div>
                `;
                historialRecords.appendChild(recordDiv);
            });

            document.getElementById('modalHistorial').style.display = 'block';
        })
        .catch(error => {
            console.error('Error al cargar historial:', error);
            alert('Error al cargar el historial odontológico');
        });
}

function verDetalle(idCita) {
    fetch('../php/detalle_cita.php?id=' + idCita)
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            alert(data.error);
            return;
        }
        document.getElementById('detallePaciente').textContent = data.paciente;
        document.getElementById('detalleDentista').textContent = data.dentista;
        document.getElementById('detalleTratamiento').textContent = data.nombreTratamiento;
        document.getElementById('detalleDescripcion').textContent = data.descripcion;
        document.getElementById('detalleFecha').textContent = formatearFechaEs(data.fecha);
        document.getElementById('detalleHora').textContent = data.horaInicio + ' - ' + data.horaFin;
        document.getElementById('detalleEstado').textContent = data.estado;
        document.getElementById('modalDetalle').style.display = 'block';
    })
    .catch(error => console.error('Error:', error));
}

function cancelarCita(idCita) {
    if (!confirm('¿Está seguro de que desea cancelar esta cita? Esta acción no se puede deshacer.')) {
        return;
    }

    const formData = new FormData();
    formData.append('idCita', idCita);

    fetch('../php/cancelar_cita.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload(); // Recargar la página para actualizar la lista de citas
        } else {
            alert(data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al cancelar la cita');
    });
}

function formatearFechasCitasAgendadas() {
    const rows = document.querySelectorAll('main .card table tbody tr');
    rows.forEach(row => {
        const fechaCell = row.querySelector('td:first-child');
        if (fechaCell) {
            fechaCell.textContent = formatearFechaEs(fechaCell.textContent.trim());
        }
    });
}

formatearFechasCitasAgendadas();

// Cerrar modal
document.querySelector('.close').onclick = function() {
    document.getElementById('modalDetalle').style.display = 'none';
}

document.querySelector('.close-historial').onclick = function() {
    document.getElementById('modalHistorial').style.display = 'none';
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

</body>
</html>
