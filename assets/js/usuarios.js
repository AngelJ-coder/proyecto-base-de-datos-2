document.addEventListener('DOMContentLoaded', function () {

    const tabs = document.querySelectorAll('.rol-tab');
    const rolInput = document.getElementById('rolSelect');

    const paneles = {
        administrador: document.getElementById('campo-administrador'),
        coordinador: document.getElementById('campo-coordinador'),
        tutor: document.getElementById('campo-tutor'),
        estudiante: document.getElementById('campo-estudiante')
    };

    if (!rolInput) return;

    function mostrarRol(rol) {
        // Actualiza el valor real que se envía en el formulario
        rolInput.value = rol;

        // Marca visualmente la pestaña activa
        tabs.forEach(tab => {
            tab.classList.toggle('activo', tab.dataset.rol === rol);
        });

        // Muestra solo el panel de campos correspondiente al rol
        Object.entries(paneles).forEach(([key, panel]) => {
            if (!panel) return;
            panel.classList.toggle('visible', key === rol);
        });
    }

    tabs.forEach(tab => {
        tab.addEventListener('click', () => mostrarRol(tab.dataset.rol));
    });

    // Si el formulario ya trae un rol preseleccionado (ej. al editar), respétalo
    if (rolInput.value) {
        mostrarRol(rolInput.value);
    }
});