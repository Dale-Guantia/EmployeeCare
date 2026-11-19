(function () {
    'use strict';
    document.querySelectorAll('[data-global-report-filters]').forEach(form => {
        const period = form.querySelector('[name="period"]');
        const half = form.querySelector('[data-report-half]');
        const custom = form.querySelector('[data-report-custom]');
        function sync() {
            half.hidden = period.value !== 'half';
            custom.hidden = period.value !== 'custom';
            custom.querySelectorAll('input').forEach(input => { input.required = period.value === 'custom'; });
        }
        period.addEventListener('change', () => {
            sync();
            if (period.value !== 'custom') form.requestSubmit();
        });
        form.querySelector('[name="half"]').addEventListener('change', () => form.requestSubmit());
        const department = form.querySelector('[name="department_id"]');
        const division = form.querySelector('[name="division_id"]');
        if (department && division) {
            department.addEventListener('change', () => {
                division.value = '';
                division.disabled = !department.value;
                form.requestSubmit();
            });
            division.addEventListener('change', () => form.requestSubmit());
        }
        sync();
    });
})();
