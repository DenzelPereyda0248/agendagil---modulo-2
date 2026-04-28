function verCita(id) {
    fetch("../php/detalle_cita.php?id=" + id)
    .then(res => res.json())
    .then(data => {

        document.getElementById("detalleCita").innerHTML = `
            <p><b>Paciente:</b> ${data.paciente}</p>
            <p><b>Dentista:</b> ${data.dentista}</p>
            <p><b>Tratamiento:</b> ${data.nombreTratamiento}</p>
            <p><b>Fecha:</b> ${data.fecha}</p>
            <p><b>Hora:</b> ${data.hora}</p>
            <p><b>Estado:</b> ${data.estado}</p>
            <p><b>Descripción:</b> ${data.descripcion}</p>
        `;

        document.getElementById("modal").style.display = "block";
    });
}

function cerrarModal() {
    document.getElementById("modal").style.display = "none";
}