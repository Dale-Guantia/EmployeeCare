(function () {
    if (window.issueArchiveConfirmationLoaded) return;
    window.issueArchiveConfirmationLoaded = true;
    const pending = new WeakSet();

    document.addEventListener('submit', async function (event) {
        const form = event.target.closest('form[data-issue-archive-action]');
        if (!form) return;
        event.preventDefault();
        if (pending.has(form)) return;
        pending.add(form);
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        const archiving = form.dataset.issueArchiveAction === 'archive';

        try {
            const confirmed = await swal({
                title: archiving ? 'Archive issue?' : 'Reactivate issue?',
                text: archiving
                    ? 'Existing Tickets and Surveys will keep this issue. It will no longer be available for new selections.'
                    : 'This issue will become available for new Ticket and Survey selections again.',
                icon: 'warning',
                content: { element: 'input', attributes: { placeholder: 'Reason (optional)', maxlength: 1000 } },
                buttons: ['Cancel', archiving ? 'Archive' : 'Reactivate']
            });
            if (confirmed !== null && confirmed !== false) {
                const reason = document.createElement('input');
                reason.type = 'hidden';
                reason.name = 'reason';
                reason.value = typeof confirmed === 'string' ? confirmed : '';
                form.appendChild(reason);
                // Native submission preserves the form's POST method and CSRF token
                // without firing another submit event or confirmation dialog.
                form.submit();
                return;
            }
        } catch (error) {
            window.alert('Could not confirm this action. Please refresh the page and try again.');
        }

        pending.delete(form);
        button.disabled = false;
    });
})();
