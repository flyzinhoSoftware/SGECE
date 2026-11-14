'use strict';

document.addEventListener('DOMContentLoaded', function () {
    const yearModal = document.getElementById('academicYearModal');
    const classModal = document.getElementById('academicClassModal');
    const disciplineModal = document.getElementById('academicDisciplineModal');
    const districtFilter = document.getElementById('academic-district-filter');
    const schoolFilter = document.getElementById('academic-school-filter');
    const levelFilter = document.getElementById('academic-level-filter');
    const classFilter = document.getElementById('academic-class-filter');

    function resetForm(modal, newTitle) {
        if (!modal) return null;
        const form = modal.querySelector('form');
        if (form) form.reset();
        const heading = modal.querySelector('.modal-title');
        if (heading) heading.textContent = newTitle;
        return form;
    }

    function filterOptions(select, key, value) {
        if (!select) return;
        Array.from(select.options).forEach(function (option) {
            if (!option.dataset[key]) return;
            const visible = value === '' || option.dataset[key] === value;
            option.hidden = !visible;
            option.disabled = !visible;
            if (!visible && option.selected) option.selected = false;
        });
    }

    if (districtFilter && schoolFilter) {
        const filterSchools = function () {
            filterOptions(schoolFilter, 'district', districtFilter.value);
        };
        districtFilter.addEventListener('change', filterSchools);
        filterSchools();
    }

    if (levelFilter && classFilter) {
        const filterClasses = function () {
            filterOptions(classFilter, 'level', levelFilter.value);
        };
        levelFilter.addEventListener('change', filterClasses);
        filterClasses();
    }

    const classLevel = document.getElementById('academic-class-level');
    const classDisciplines = document.getElementById('academic-class-disciplines');
    if (classLevel && classDisciplines) {
        const filterDisciplines = function () {
            filterOptions(classDisciplines, 'level', classLevel.value);
        };
        classLevel.addEventListener('change', filterDisciplines);
        filterDisciplines();
    }

    document.querySelectorAll('[data-academic-new="year"]').forEach(function (button) {
        button.addEventListener('click', function () {
            resetForm(yearModal, 'Novo ano lectivo');
        });
    });

    document.querySelectorAll('[data-academic-edit-year]').forEach(function (button) {
        button.addEventListener('click', function () {
            resetForm(yearModal, 'Editar ano lectivo');
            document.getElementById('academic-year-id').value = button.dataset.id;
            document.getElementById('academic-year-value').value = button.dataset.year;
            document.getElementById('academic-year-start').value = button.dataset.start;
            document.getElementById('academic-year-end').value = button.dataset.end;
            document.getElementById('academic-year-status').value = button.dataset.status;
        });
    });

    document.querySelectorAll('[data-academic-new="class"]').forEach(function (button) {
        button.addEventListener('click', function () {
            resetForm(classModal, 'Nova classe');
            if (classLevel && classDisciplines) {
                Array.from(classDisciplines.options).forEach(function (option) {
                    option.selected = false;
                });
                filterOptions(classDisciplines, 'level', classLevel.value);
            }
        });
    });

    document.querySelectorAll('[data-academic-edit-class]').forEach(function (button) {
        button.addEventListener('click', function () {
            resetForm(classModal, 'Editar classe');
            document.getElementById('academic-class-id').value = button.dataset.id;
            classLevel.value = button.dataset.level;
            document.getElementById('academic-class-code').value = button.dataset.code;
            document.getElementById('academic-class-name').value = button.dataset.name;
            document.getElementById('academic-class-order').value = button.dataset.order;
            document.getElementById('academic-class-status').value = button.dataset.status;
            const selectedIds = (button.dataset.disciplineIds || '').split(',').filter(Boolean);
            Array.from(classDisciplines.options).forEach(function (option) {
                option.selected = selectedIds.includes(option.value);
            });
            filterOptions(classDisciplines, 'level', classLevel.value);
        });
    });

    document.querySelectorAll('[data-academic-new="discipline"]').forEach(function (button) {
        button.addEventListener('click', function () {
            resetForm(disciplineModal, 'Nova disciplina');
        });
    });

    document.querySelectorAll('[data-academic-edit-discipline]').forEach(function (button) {
        button.addEventListener('click', function () {
            resetForm(disciplineModal, 'Editar disciplina');
            document.getElementById('academic-discipline-id').value = button.dataset.id;
            document.getElementById('academic-discipline-level').value = button.dataset.level;
            document.getElementById('academic-discipline-code').value = button.dataset.code;
            document.getElementById('academic-discipline-name').value = button.dataset.name;
            document.getElementById('academic-discipline-status').value = button.dataset.status;
        });
    });

    [
        [yearModal, 'academicYearModalLabel', 'Novo ano lectivo'],
        [classModal, 'academicClassModalLabel', 'Nova classe'],
        [disciplineModal, 'academicDisciplineModalLabel', 'Nova disciplina']
    ].forEach(function (entry) {
        if (!entry[0]) return;
        entry[0].addEventListener('hidden.bs.modal', function () {
            const form = entry[0].querySelector('form');
            if (form) form.reset();
            const heading = document.getElementById(entry[1]);
            if (heading) heading.textContent = entry[2];
            if (entry[0] === classModal && classDisciplines) {
                Array.from(classDisciplines.options).forEach(function (option) {
                    option.selected = false;
                });
                filterOptions(classDisciplines, 'level', classLevel.value);
            }
        });
    });
});
