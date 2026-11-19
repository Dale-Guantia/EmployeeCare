(function () {
    function initialize() {
        const department = document.querySelector('[data-report-department]');
        const division = document.querySelector('[data-report-division]');
        if (!department || !division) return;
        function navigate(departmentChanged) {
            const url = new URL(window.location.href);
            if (department.value) url.searchParams.set('department_id', department.value);
            else url.searchParams.delete('department_id');
            if (!departmentChanged && division.value) url.searchParams.set('division_id', division.value);
            else url.searchParams.delete('division_id');
            window.location.assign(url.toString());
        }
        department.addEventListener('change', () => navigate(true));
        division.addEventListener('change', () => navigate(false));
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize);
    else initialize();
})();
