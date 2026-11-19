(function () {
    document.querySelectorAll('[data-icon-picker]').forEach(function (picker) {
        const input = picker.querySelector('input');
        const preview = picker.querySelector('[data-icon-preview]');

        function updatePreview() {
            const value = input.value.trim();
            preview.className = /^(?:fa|fas|far|fab) fa-[a-z0-9-]+$/.test(value) ? value : '';
        }

        input.addEventListener('input', updatePreview);
        input.addEventListener('change', function () {
            // Preserve the previous picker's short-name normalization.
            if (/^fa-[a-z0-9-]+$/.test(input.value.trim())) {
                input.value = 'fas ' + input.value.trim();
            }
            updatePreview();
        });
        updatePreview();

        const optionsNode = picker.querySelector('[data-icon-options]');
        if (!optionsNode || !optionsNode.textContent) return;
        const options = Object.entries(JSON.parse(optionsNode.textContent));
        const library = picker.querySelector('[data-icon-library]');
        const search = picker.querySelector('[data-icon-search]');
        const grid = picker.querySelector('[data-icon-grid]');
        const previous = picker.querySelector('[data-icon-previous]');
        const next = picker.querySelector('[data-icon-next]');
        const pageLabel = picker.querySelector('[data-icon-page]');
        const pageSize = 60;
        let matches = options;
        let page = 0;

        function render() {
            grid.replaceChildren();
            const totalPages = Math.max(1, Math.ceil(matches.length / pageSize));
            page = Math.min(page, totalPages - 1);
            matches.slice(page * pageSize, (page + 1) * pageSize).forEach(function ([value, label]) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'btn btn-outline-secondary icon-library-choice';
                button.title = label + ' (' + value + ')';
                button.setAttribute('aria-label', label + ' (' + value + ')');
                button.setAttribute('aria-pressed', String(input.value === value));
                const icon = document.createElement('i');
                icon.className = value;
                icon.setAttribute('aria-hidden', 'true');
                button.appendChild(icon);
                button.addEventListener('click', function () {
                    input.value = value;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    library.open = false;
                    library.querySelector('summary').focus();
                });
                grid.appendChild(button);
            });
            pageLabel.textContent = matches.length ? (page + 1) + ' / ' + totalPages + ' (' + matches.length + ' icons)' : 'No matching icons';
            previous.disabled = page === 0;
            next.disabled = page + 1 >= totalPages;
        }

        search.addEventListener('input', function () {
            const term = search.value.trim().toLowerCase();
            matches = options.filter(([value, label]) => (label + ' ' + value).toLowerCase().includes(term));
            page = 0;
            render();
        });
        previous.addEventListener('click', function () { if (page > 0) { page--; render(); } });
        next.addEventListener('click', function () { if ((page + 1) * pageSize < matches.length) { page++; render(); } });
        library.addEventListener('toggle', function () { if (library.open) { render(); search.focus(); } });
    });
})();
