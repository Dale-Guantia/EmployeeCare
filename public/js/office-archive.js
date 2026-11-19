(function () {
    if (window.officeArchiveConfirmationLoaded) return;
    window.officeArchiveConfirmationLoaded = true;
    const pending = new WeakSet();

    function escapeText(message) {
        const span = document.createElement('span');
        span.textContent = message;
        return span.innerHTML;
    }

    function notify(type, message) {
        if (typeof Noty !== 'function') {
            window.alert(message);
            return;
        }
        new Noty({
            type: type,
            text: escapeText(message).replace(/\n/g, '<br>'),
            layout: 'topRight',
            theme: 'backstrap',
            timeout: type === 'error' ? false : 2500,
            closeWith: ['click', 'button']
        }).show();
    }

    document.addEventListener('submit', async function (event) {
        const form = event.target.closest('form[data-office-archive-action]');
        if (!form) return;
        event.preventDefault();
        if (pending.has(form)) return;
        pending.add(form);
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        const archiving = form.dataset.officeArchiveAction === 'archive';
        const label = form.dataset.officeLabel;
        let succeeded = false;

        try {
            const confirmed = await swal({
                title: (archiving ? 'Archive ' : 'Reactivate ') + label + '?',
                text: archiving
                    ? 'Historical records will be kept. Active users, unresolved tickets, and pending transfers must be reassigned or completed first. '
                        + (label === 'department' ? 'Its divisions and issues will become unavailable for new selections.' : 'Its issues will become unavailable for new selections.')
                    : 'This record will become available again after its parent and configuration checks pass. Individually archived divisions and issues will stay archived. '
                        + (form.dataset.reactivationSummary || ''),
                icon: 'warning',
                content: { element: 'input', attributes: { placeholder: 'Reason (optional)', maxlength: 1000 } },
                buttons: ['Cancel', archiving ? 'Archive' : 'Reactivate']
            });
            if (confirmed === null || confirmed === false) return;

            const body = new FormData(form);
            body.append('reason', typeof confirmed === 'string' ? confirmed : '');

            const response = await fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: body
            });
            const result = await response.json();
            if (!response.ok) {
                const messages = response.status === 422 && result.errors
                    ? Object.values(result.errors).flat().filter(message => typeof message === 'string') : [];
                const message = messages.length ? messages.join('\n')
                    : (response.status === 403 ? 'You do not have permission to update this office.'
                        : (response.status === 419 ? 'Your session has expired. Refresh the page and try again.'
                            : 'Could not update this office. Refresh the page and try again.'));
                notify('error', message);
                return;
            }
            if (typeof result.message !== 'string') {
                throw new Error('Unexpected response');
            }

            const table = window.crud?.tables?.[form.dataset.tableId] || window.crud?.table;
            if (table) {
                notify('success', result.message);
                table.draw(false);
            } else {
                try {
                    window.localStorage.setItem('backpack_alerts', JSON.stringify({ success: [escapeText(result.message)] }));
                } catch (error) {
                    notify('success', result.message);
                }
                window.location.assign(form.dataset.returnUrl);
            }
            succeeded = true;
        } catch (error) {
            notify('error', 'Could not complete this action. Refresh the page and try again.');
        } finally {
            if (!succeeded) {
                pending.delete(form);
                button.disabled = false;
            }
        }
    });
})();
