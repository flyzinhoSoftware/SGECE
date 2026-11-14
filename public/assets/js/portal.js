'use strict';

/**
 * SGECE — Portal Público
 */

document.addEventListener('DOMContentLoaded', function () {

    const consultationForm = document.getElementById('consultationForm');
    const consultButton = document.getElementById('consultButton');

    /*
     * Submissão do formulário.
     * A consulta é processada pelo PortalController.
     */
    if (consultationForm && consultButton) {

        consultationForm.addEventListener('submit', function (event) {

            if (!consultationForm.checkValidity()) {
                event.preventDefault();
                consultationForm.reportValidity();
                return;
            }

            const codeInput = document.getElementById('codigo_aluno');

            if (codeInput) {
                codeInput.value = codeInput.value.trim();

                if (codeInput.value.length < 3) {
                    event.preventDefault();
                    codeInput.focus();
                    codeInput.setCustomValidity(
                        'Introduza um código de aluno válido.'
                    );
                    codeInput.reportValidity();
                    return;
                }

                codeInput.setCustomValidity('');
            }

            const buttonText = consultButton.querySelector('.button-text');
            const buttonLoading = consultButton.querySelector('.button-loading');

            consultButton.disabled = true;

            if (buttonText) {
                buttonText.classList.add('d-none');
            }

            if (buttonLoading) {
                buttonLoading.classList.remove('d-none');
            }
        });
    }


    /*
     * Limpa mensagens de validação quando o utilizador altera o código.
     */
    const codeInput = document.getElementById('codigo_aluno');

    if (codeInput) {
        codeInput.addEventListener('input', function () {
            this.setCustomValidity('');
        });
    }


    /*
     * Fecha a navegação móvel depois de seleccionar um link interno.
     */
    const navigationLinks = document.querySelectorAll(
        '.navbar-nav .nav-link[href^="#"]'
    );

    const navigationMenu = document.getElementById('mainNavigation');

    navigationLinks.forEach(function (link) {

        link.addEventListener('click', function () {

            if (
                navigationMenu &&
                navigationMenu.classList.contains('show') &&
                window.bootstrap
            ) {
                const collapse = bootstrap.Collapse.getOrCreateInstance(
                    navigationMenu
                );

                collapse.hide();
            }
        });
    });


    /*
     * Actualiza o ano do rodapé, caso exista um elemento dinâmico.
     */
    const currentYear = document.getElementById('currentYear');

    if (currentYear) {
        currentYear.textContent = new Date().getFullYear();
    }

});