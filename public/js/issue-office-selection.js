(function () {
    function initialize() {
        const config = document.querySelector('[data-issue-office-options]');
        if (!config || config.dataset.initialized) return;
        config.setAttribute('data-initialized', '1');
        const data = JSON.parse(config.textContent);
        const department = document.querySelector('select[name="department_id"]');
        const division = document.querySelector('select[name="division_id"]');
        if (!department || !division) return;
        function refresh() {
            const selected = division.value;
            const options = data.divisions.filter(item => String(item.department_id) === department.value);
            const enabled = Boolean(department.value);
            const retainedNull = String(data.retainedNullDepartment || '') === department.value && Boolean(data.retainedNullDepartment);
            division.required = enabled && options.length > 0 && !retainedNull;
            const placeholder = !enabled ? 'Select a department first' : (options.length ? '- Select Division -' : 'No divisions — optional');
            const retained = options.some(item => String(item.id) === selected) ? selected : '';
            if (division.tomselect) {
                division.tomselect.clear(true);
                division.tomselect.clearOptions();
                division.tomselect.addOption(options.map(item => ({ value: String(item.id), text: item.division_name })));
                division.tomselect.settings.placeholder = placeholder;
                division.tomselect.setValue(retained, true);
                enabled ? division.tomselect.enable() : division.tomselect.disable();
            } else {
                division.replaceChildren();
                const empty = document.createElement('option');
                empty.value = '';
                empty.textContent = placeholder;
                division.appendChild(empty);
                options.forEach(item => {
                    const option = document.createElement('option');
                    option.value = String(item.id);
                    option.textContent = item.division_name;
                    division.appendChild(option);
                });
                division.value = retained;
            }
            division.disabled = !enabled;
        }
        department.addEventListener('change', refresh);
        refresh();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize);
    else initialize();
})();
