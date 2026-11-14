'use strict';

document.addEventListener('DOMContentLoaded', function () {

    const sidebar = document.getElementById('adminSidebar');
    const toggle = document.getElementById('sidebarToggle');
    const close = document.getElementById('sidebarClose');
    const overlay = document.getElementById('sidebarOverlay');

    function openSidebar() {
        if (!sidebar || !overlay) return;

        sidebar.classList.add('open');
        overlay.classList.add('show');

        if (toggle) {
            toggle.setAttribute('aria-expanded', 'true');
        }
    }

    function closeSidebar() {
        if (!sidebar || !overlay) return;

        sidebar.classList.remove('open');
        overlay.classList.remove('show');

        if (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
        }
    }

    if (toggle) {
        toggle.addEventListener('click', function () {
            if (sidebar && sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    if (close) {
        close.addEventListener('click', closeSidebar);
    }

    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 991) {
            closeSidebar();
        }
    });

});