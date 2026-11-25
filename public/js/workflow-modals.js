(function () {
    const confirmationDialog = document.querySelector('[data-confirmation-dialog]');
    const confirmationMessage = confirmationDialog?.querySelector('[data-confirmation-message]');
    const confirmButton = confirmationDialog?.querySelector('[data-confirmation-submit]');
    let pendingForm = null;

    document.addEventListener('click', (event) => {
        const confirmationTrigger = event.target.closest('[data-confirmation-trigger]');
        if (confirmationTrigger) {
            const form = confirmationTrigger.closest('form');
            if (!form || !confirmationDialog || !confirmationMessage || !confirmButton) return;
            pendingForm = form;
            confirmationMessage.textContent = form.dataset.confirmMessage;
            confirmButton.textContent = form.dataset.confirmLabel || 'Confirm vaccination';
            confirmationDialog.showModal();
            return;
        }

        const openButton = event.target.closest('[data-dialog-open]');
        if (openButton) {
            const dialog = document.querySelector(openButton.dataset.dialogOpen);
            if (dialog && !dialog.open) dialog.showModal();
            return;
        }

        const closeButton = event.target.closest('[data-dialog-close]');
        if (closeButton) closeButton.closest('dialog')?.close();
    });

    document.addEventListener('click', (event) => {
        if (event.target.matches('[data-modal-done]')) {
            event.target.closest('dialog')?.close();
            const url = new URL(window.location.href);
            url.searchParams.delete('register');
            url.searchParams.delete('record');
            window.location.assign(url);
        }
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirmMessage) return;
        if (form.dataset.confirmedOnce === 'true') {
            delete form.dataset.confirmedOnce;
            return;
        }

        if (!confirmationDialog || !confirmationMessage || !confirmButton) return;
        event.preventDefault();
        pendingForm = form;
        confirmationMessage.textContent = form.dataset.confirmMessage;
        confirmButton.textContent = form.dataset.confirmLabel || 'Confirm';
        confirmationDialog.showModal();
    }, true);

    confirmButton?.addEventListener('click', () => {
        if (!pendingForm) return;
        const form = pendingForm;
        pendingForm = null;
        form.dataset.confirmedOnce = 'true';
        confirmationDialog.close();
        form.requestSubmit();
    });

    confirmationDialog?.addEventListener('close', () => {
        pendingForm = null;
    });

    document.querySelectorAll('dialog.workflow-dialog').forEach((dialog) => {
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        });
    });

    const registrationDialog = document.querySelector('[data-registration-dialog]');
    if (registrationDialog && (new URLSearchParams(window.location.search).has('register') || document.querySelector('.api-field-error'))) {
        registrationDialog.showModal();
    }
    const recordDialog = document.querySelector('[data-record-dialog]');
    if (recordDialog && new URLSearchParams(window.location.search).has('record')) {
        recordDialog.showModal();
    }
})();
