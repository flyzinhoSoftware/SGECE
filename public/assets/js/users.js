'use strict';

document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('usersFormModal');
    const form = document.getElementById('users-form');
    const roleSelect = document.getElementById('users-role');
    const roleHidden = document.getElementById('users-role-hidden');
    const schoolFields = document.getElementById('users-school-fields');
    const districtSelect = document.getElementById('users-district');
    const schoolSelect = document.getElementById('users-school');
    const nameInput = document.getElementById('users-name');
    const emailInput = document.getElementById('users-email');
    const emailSuffixInput = document.getElementById('users-email-suffix');
    const emailHelp = document.getElementById('users-email-help');
    const heading = document.getElementById('usersFormModalLabel');
    const submitButton = form ? form.querySelector('button[type="submit"]') : null;
    const districtFilter = document.getElementById('users-district-filter');
    const schoolFilter = document.getElementById('users-school-filter');

    if (
        !modal || !form || !roleSelect || !roleHidden || !schoolFields ||
        !nameInput || !emailInput || !emailSuffixInput
    ) return;

    const adminRole = modal.dataset.adminRole || '';
    const schoolRole = modal.dataset.schoolRole || '';
    const rootRole = modal.dataset.rootRole || '';
    const emailDomain = modal.dataset.emailDomain || 'sgece.gov.mz';
    const canManageRoot = modal.dataset.canManageRoot === 'true';
    const idInput = document.getElementById('users-id');
    let requestedRoot = false;
    let originalName = '';
    let originalEmail = '';
    let originalEmailSuffix = '';
    let emailBase = '';

    function filterSchools(select, district) {
        if (!select) return;
        Array.from(select.options).forEach(function (option) {
            if (!option.dataset.district) return;
            const visible = district === '' || option.dataset.district === district;
            option.hidden = !visible;
            option.disabled = !visible;
            if (!visible && option.selected) option.selected = false;
        });
    }

    function nameEmailBase(value) {
        const parts = value.trim().split(/\s+/).filter(Boolean);
        if (parts.length < 2) return '';

        function normalize(part) {
            return part.normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9]/gi, '')
                .toLowerCase();
        }

        const givenName = normalize(parts[0]);
        const surname = normalize(parts[parts.length - 1]);
        if (!givenName || !surname) return '';

        const maximumPrefixLength = 150 - ('@' + emailDomain).length - 4;
        return (givenName.charAt(0) + surname).slice(0, maximumPrefixLength);
    }

    function randomEmailSuffix() {
        const values = new Uint16Array(1);
        if (window.crypto && window.crypto.getRandomValues) {
            window.crypto.getRandomValues(values);
            return String(values[0] % 10000).padStart(4, '0');
        }
        return String(Math.floor(Math.random() * 10000)).padStart(4, '0');
    }

    function syncEmail() {
        if (nameInput.value === originalName) {
            emailInput.value = originalEmail;
            emailSuffixInput.value = originalEmailSuffix;
            if (emailHelp) {
                emailHelp.textContent =
                    'Gerado pela inicial do nome, sobrenome e quatro dígitos aleatórios.';
            }
            return;
        }

        const nextBase = nameEmailBase(nameInput.value);
        if (!nextBase) {
            emailInput.value = '';
            emailSuffixInput.value = '';
            emailBase = '';
            if (emailHelp) {
                emailHelp.textContent = 'Introduza o nome próprio e pelo menos um sobrenome.';
            }
            return;
        }

        if (nextBase !== emailBase || !/^\d{4}$/.test(emailSuffixInput.value)) {
            emailSuffixInput.value = randomEmailSuffix();
            emailBase = nextBase;
        }
        emailInput.value = nextBase + emailSuffixInput.value + '@' + emailDomain;
        if (emailHelp) {
            emailHelp.textContent =
                'Pré-visualização automática. O servidor confirma a disponibilidade ao guardar.';
        }
    }

    function syncAccessLevel() {
        const hasSchool = Boolean(schoolSelect && schoolSelect.value);
        const accessLevel = hasSchool
            ? schoolRole
            : (canManageRoot && requestedRoot ? rootRole : adminRole);

        roleSelect.value = accessLevel;
        roleHidden.value = accessLevel;
        schoolFields.classList.remove('d-none');
        if (districtSelect) districtSelect.required = false;
        if (schoolSelect) schoolSelect.required = false;
    }

    if (districtFilter && schoolFilter) {
        const applySchoolFilter = function () {
            filterSchools(schoolFilter, districtFilter.value);
        };
        districtFilter.addEventListener('change', applySchoolFilter);
        applySchoolFilter();
    }

    if (districtSelect && schoolSelect) {
        districtSelect.addEventListener('change', function () {
            filterSchools(schoolSelect, districtSelect.value);
            syncAccessLevel();
        });
        schoolSelect.addEventListener('change', syncAccessLevel);
    }

    roleSelect.addEventListener('change', function () {
        requestedRoot = canManageRoot && roleSelect.value === rootRole;
        syncAccessLevel();
    });
    nameInput.addEventListener('input', syncEmail);

    function setEmailBaseline(name, email, suffix) {
        originalName = name;
        originalEmail = email;
        emailBase = nameEmailBase(name);
        const existingSuffix = String(email || '').match(
            /(\d{4})@sgece\.gov\.mz$/i
        );
        originalEmailSuffix = /^\d{4}$/.test(suffix || '')
            ? suffix
            : (existingSuffix ? existingSuffix[1] : '');
        emailSuffixInput.value = originalEmailSuffix;
    }

    function resetForNewUser() {
        form.reset();
        idInput.value = '';
        roleSelect.disabled = !canManageRoot;
        roleSelect.value = adminRole;
        requestedRoot = false;
        roleHidden.value = adminRole;
        setEmailBaseline('', '', '');
        emailInput.value = '';
        heading.textContent = 'Registar utilizador';
        if (submitButton) submitButton.textContent = 'Registar utilizador';
        if (districtSelect) districtSelect.value = '';
        if (schoolSelect) schoolSelect.value = '';
        syncAccessLevel();
    }

    document.querySelectorAll('[data-user-new]').forEach(function (button) {
        button.addEventListener('click', resetForNewUser);
    });

    document.querySelectorAll('[data-user-edit]').forEach(function (button) {
        button.addEventListener('click', function () {
            form.reset();
            idInput.value = button.dataset.id;
            nameInput.value = button.dataset.name;
            emailInput.value = button.dataset.email;
            document.getElementById('users-phone').value = button.dataset.phone;
            const userRole = button.dataset.role;
            requestedRoot = canManageRoot && userRole === rootRole;
            roleSelect.disabled = !canManageRoot || button.dataset.self === 'true';
            roleHidden.value = userRole;
            heading.textContent = 'Editar utilizador';
            if (submitButton) submitButton.textContent = 'Guardar alterações';
            if (districtSelect) districtSelect.value = button.dataset.district || '';
            if (schoolSelect) schoolSelect.value = button.dataset.school || '';
            setEmailBaseline(button.dataset.name, button.dataset.email, '');
            syncAccessLevel();
        });
    });

    modal.addEventListener('hidden.bs.modal', resetForNewUser);

    if (modal.dataset.openOnLoad === 'true') {
        const editingId = idInput.value;
        const isSelf = editingId !== '' && editingId === modal.dataset.currentUserId;
        roleSelect.disabled = !canManageRoot || isSelf;
        requestedRoot = canManageRoot && roleHidden.value === rootRole;
        setEmailBaseline(nameInput.value, emailInput.value, emailSuffixInput.value);
        syncAccessLevel();
        const openModal = function () {
            if (window.bootstrap && window.bootstrap.Modal) {
                window.bootstrap.Modal.getOrCreateInstance(modal).show();
            }
        };
        if (window.bootstrap && window.bootstrap.Modal) {
            openModal();
        } else {
            window.addEventListener('load', openModal, { once: true });
        }
    } else {
        resetForNewUser();
    }
});
