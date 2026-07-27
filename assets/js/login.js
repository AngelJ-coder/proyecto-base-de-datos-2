document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('formLogin');
    const email = document.getElementById('email');
    const password = document.getElementById('password');
    const errorEmail = document.getElementById('error-email');
    const errorPassword = document.getElementById('error-password');
    const togglePassword = document.getElementById('togglePassword');
    const btnSubmit = document.getElementById('btnSubmit');

    const regexEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    function marcarError(input, spanError, mensaje) {
        input.classList.add('input-invalido');
        spanError.textContent = mensaje;
    }

    function limpiarError(input, spanError) {
        input.classList.remove('input-invalido');
        spanError.textContent = '';
    }

    function validarEmail() {
        const valor = email.value.trim();
        if (valor === '') {
            marcarError(email, errorEmail, 'El correo es obligatorio.');
            return false;
        }
        if (!regexEmail.test(valor)) {
            marcarError(email, errorEmail, 'Ingresa un correo válido.');
            return false;
        }
        limpiarError(email, errorEmail);
        return true;
    }

    function validarPassword() {
        const valor = password.value;
        if (valor === '') {
            marcarError(password, errorPassword, 'La contraseña es obligatoria.');
            return false;
        }
        if (valor.length < 4) {
            marcarError(password, errorPassword, 'La contraseña es demasiado corta.');
            return false;
        }
        limpiarError(password, errorPassword);
        return true;
    }

    // Validación en tiempo real
    email.addEventListener('input', validarEmail);
    password.addEventListener('input', validarPassword);

    // Mostrar / ocultar contraseña
    togglePassword.addEventListener('click', function () {
        const tipoActual = password.getAttribute('type');
        const nuevoTipo = tipoActual === 'password' ? 'text' : 'password';
        password.setAttribute('type', nuevoTipo);
        togglePassword.textContent = nuevoTipo === 'password' ? '👁' : '🙈';
    });

    // Validación antes de enviar el formulario
    form.addEventListener('submit', function (e) {
        const emailValido = validarEmail();
        const passwordValido = validarPassword();

        if (!emailValido || !passwordValido) {
            e.preventDefault();
            return;
        }

        // Evitar doble envío
        btnSubmit.disabled = true;
        btnSubmit.textContent = 'Ingresando...';
    });
});