(function () {
    if (window.priorityActionsLoaded) return;
    window.priorityActionsLoaded = true;

    function notify(type, message) {
        if (typeof Noty === 'function') {
            const span = document.createElement('span');
            span.textContent = message;
            new Noty({ type: type, text: span.innerHTML.replace(/\n/g, '<br>'), layout: 'topRight',
                theme: 'backstrap', timeout: type === 'error' ? false : 2500,
                closeWith: ['click', 'button'] }).show();
        } else {
            swal({ title: type === 'error' ? 'Action refused' : 'Record updated', text: message, icon: type });
        }
    }

    async function request(url, method, reason = '') {
        const response = await fetch(url, {
            method: method,
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json'
            },
            body: method === 'GET' ? undefined : JSON.stringify({ reason: reason })
        });
        const result = await response.json();
        if (!response.ok) {
            if (response.status === 422 && result?.errors) {
                throw new Error(Object.values(result.errors).flat().join('\n'));
            }
            throw new Error(response.status === 403
                ? 'You do not have permission to perform this action.'
                : 'Could not complete this action. Refresh the page and try again.');
        }
        if (result && result.error) throw new Error(result.error[0]);
        return result;
    }

    document.addEventListener('click', async function (event) {
        const button = event.target.closest('[data-priority-action]');
        if (!button || button.disabled) return;
        event.preventDefault();
        button.disabled = true;
        const action = button.dataset.priorityAction;
        const label = button.dataset.recordLabel || 'priority';

        try {
            let text = action === 'archive'
                ? 'Replace or explicitly clear active Issue defaults first. Historical Tickets will keep this priority. It will no longer be available for new assignments.'
                : 'This priority will become available for new assignments again.';
            if (action === 'delete') {
                const counts = await request(button.dataset.impactUrl, 'GET');
                const references = Object.entries(counts).filter(entry => entry[1] > 0)
                    .map(entry => entry[1] + ' ' + entry[0].replace(/_/g, ' '));
                if (references.length) {
                    throw new Error('Cannot delete: ' + references.join(', ')
                        + ' still reference this ' + label + '. Archive it to preserve history.');
                }
                text = 'Only an unused, unprotected ' + label + ' can be deleted. Historical references will never be cleared. This cannot be undone. Eligibility is checked again when deleting.';
            }
            const confirmed = await swal({
                title: action === 'delete' ? 'Delete ' + label + ' permanently?' : (action === 'archive' ? 'Archive priority?' : 'Reactivate priority?'),
                text: text,
                icon: 'warning',
                content: { element: 'input', attributes: { placeholder: 'Reason (optional)', maxlength: 1000 } },
                buttons: ['Cancel', action === 'delete' ? 'Delete permanently' : (action === 'archive' ? 'Archive' : 'Reactivate')],
                dangerMode: action === 'delete'
            });
            if (confirmed === null || confirmed === false) return;

            const result = await request(button.dataset.actionUrl, action === 'delete' ? 'DELETE' : 'POST', typeof confirmed === 'string' ? confirmed : '');
            if (action === 'delete' && result !== 1 && result !== '1') {
                throw new Error('The priority could not be deleted. Refresh the page and try again.');
            }
            const table = window.crud?.tables?.[button.dataset.tableId] || window.crud?.table;
            if (table) {
                if (action === 'delete' && table.rows().count() === 1) table.page('previous');
                table.draw(false);
                notify('success', action === 'delete' ? 'Unused ' + label + ' deleted.' : result.message);
            } else {
                window.location.assign(button.dataset.returnUrl);
            }
        } catch (error) {
            notify('error', error.message);
        } finally {
            button.disabled = false;
        }
    });
})();
