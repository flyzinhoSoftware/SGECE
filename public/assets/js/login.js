
document.addEventListener('DOMContentLoaded', function () {
    const passwordInput = document.getElementById('senha');
    const togglePassword = document.getElementById('togglePassword');
    const loginForm = document.getElementById('loginForm');
    const loginButton = document.getElementById('loginButton');

    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function () {
            const isPassword = passwordInput.type === 'password';

            passwordInput.type = isPassword ? 'text' : 'password';

            togglePassword.innerHTML = isPassword
                ? '<i class="bi bi-eye-slash"></i>'
                : '<i class="bi bi-eye"></i>';
        });
    }

    if (loginForm && loginButton) {
        loginForm.addEventListener('submit', function () {
            if (!loginForm.checkValidity()) {
                return;
            }

            loginButton.disabled = true;
            loginButton.querySelector('.button-text').classList.add('d-none');
            loginButton.querySelector('.button-loading').classList.remove('d-none');
        });
    }
});
