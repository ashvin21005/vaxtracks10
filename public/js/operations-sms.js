(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const statusClasses = ['status-good', 'status-warning', 'status-danger', 'status-neutral'];
    const feedback = document.querySelector('[data-sms-feedback]');
    const bulkForm = document.querySelector('[data-sms-bulk-send-form]');
    const bulkButton = bulkForm?.querySelector('[data-sms-bulk-send-button]');

    function announce(message) {
        if (feedback) feedback.textContent = message;
    }

    function updateSummaryCount(previousStatus, nextStatus) {
        if (previousStatus === nextStatus) return;

        [previousStatus, nextStatus].forEach((status, index) => {
            if (!['SENT', 'PENDING', 'FAILED'].includes(status)) return;
            const count = document.querySelector(`[data-sms-count="${status.toLowerCase()}"]`);
            if (!count) return;

            const value = Number.parseInt(count.textContent, 10) || 0;
            count.textContent = String(Math.max(0, value + (index === 0 ? -1 : 1)));
        });
    }

    function updateNextAction(row) {
        const nextNotification = Array.from(row.querySelectorAll('[data-status-pill][data-notification-id]'))
            .filter((pill) => pill.dataset.notificationId && ['PENDING', 'FAILED'].includes(pill.dataset.status))
            .sort((first, second) => Number(second.dataset.reminderDays) - Number(first.dataset.reminderDays))[0];
        const checkbox = row.querySelector('[data-sms-bulk-checkbox]');
        const nextForm = row.querySelector('[data-sms-next-form]');
        const nextButton = nextForm?.querySelector('button[type="submit"]');

        if (nextButton) {
            nextButton.disabled = !nextNotification;
            nextButton.textContent = nextNotification?.dataset.status === 'FAILED'
                ? 'Retry Next SMS'
                : 'Mark Next Sent';
        }
        if (nextForm && nextNotification) {
            nextForm.action = nextNotification.dataset.status === 'FAILED'
                ? nextNotification.dataset.resendUrl
                : nextNotification.dataset.sendUrl;
        }
        if (checkbox) {
            checkbox.checked = false;
            checkbox.disabled = !nextNotification;
            checkbox.value = nextNotification?.dataset.notificationId || '';
        }
    }

    function updateNotification(notification) {
        const id = String(notification.id);
        const badge = Array.from(document.querySelectorAll('[data-status-pill]'))
            .find((item) => item.dataset.notificationId === id);
        if (!badge) return;

        const previousStatus = badge.dataset.status;
        badge.dataset.status = notification.status;
        badge.classList.remove(...statusClasses);
        badge.classList.add({
            SENT: 'status-good',
            PENDING: 'status-warning',
            FAILED: 'status-danger',
        }[notification.status] || 'status-neutral');
        badge.textContent = `${notification.reminder_days}d: ${notification.status}`;
        updateSummaryCount(previousStatus, notification.status);

        const stage = badge.closest('[data-sms-stage]');
        const failureReason = stage?.querySelector('[data-failure-reason]');
        if (failureReason) {
            failureReason.textContent = notification.failure_reason || '';
            failureReason.hidden = !notification.failure_reason;
        }

        const sendForm = stage?.querySelector('[data-sms-send-form]');
        if (sendForm) {
            sendForm.action = notification.status === 'FAILED'
                ? badge.dataset.resendUrl
                : badge.dataset.sendUrl;
        }

        const row = badge.closest('[data-sms-row]');
        if (row) updateNextAction(row);
    }

    async function sendJson(form, method, payload) {
        const options = {
            method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
        };
        if (payload) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(payload);
        }

        const response = await fetch(form.action, options);
        const result = await response.json().catch(() => null);
        if (!response.ok || !result) {
            const validationMessage = result?.errors
                ? Object.values(result.errors).flat()[0]
                : null;
            throw new Error(validationMessage || result?.message || 'The SMS request failed. Please try again.');
        }

        return result;
    }

    function updateBulkButton() {
        if (!bulkButton) return;
        bulkButton.disabled = !Array.from(document.querySelectorAll('[data-sms-bulk-checkbox]'))
            .some((checkbox) => checkbox.checked && !checkbox.disabled);
    }

    document.querySelectorAll('[data-sms-bulk-checkbox]').forEach((checkbox) => {
        checkbox.addEventListener('change', updateBulkButton);
    });

    document.querySelectorAll('form[data-sms-send-form]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const submitter = event.submitter;
            if (submitter) submitter.disabled = true;
            form.setAttribute('aria-busy', 'true');

            try {
                const result = await sendJson(form, 'POST');
                updateNotification(result.notification);
                announce(result.message);
            } catch (error) {
                announce(error.message);
            } finally {
                form.removeAttribute('aria-busy');
                if (submitter) submitter.disabled = false;
                const row = form.closest('[data-sms-row]');
                if (row) updateNextAction(row);
            }
        });
    });

    document.querySelectorAll('form[data-sms-update-form]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const submitter = event.submitter;
            const formData = new FormData(form);
            if (submitter?.name) formData.set(submitter.name, submitter.value);
            const payload = Object.fromEntries(
                Array.from(formData.entries()).filter(([name]) => !['_token', '_method'].includes(name))
            );
            if (!payload.status) {
                payload.status = form.querySelector('button[name="status"]')?.value || 'SENT';
            }

            if (submitter) submitter.disabled = true;
            form.setAttribute('aria-busy', 'true');
            try {
                const result = await sendJson(form, 'PATCH', payload);
                updateNotification(result.notification);
                announce(result.message);
            } catch (error) {
                announce(error.message);
            } finally {
                form.removeAttribute('aria-busy');
                if (submitter) submitter.disabled = false;
                const row = form.closest('[data-sms-row]');
                if (row) updateNextAction(row);
                updateBulkButton();
            }
        });
    });

    bulkForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const notificationIds = new FormData(bulkForm).getAll('notification_ids[]');
        if (notificationIds.length === 0) {
            announce('Select at least one vaccination visit with an unsent reminder.');
            return;
        }

        if (bulkButton) bulkButton.disabled = true;
        bulkForm.setAttribute('aria-busy', 'true');
        try {
            const result = await sendJson(bulkForm, 'POST', { notification_ids: notificationIds });
            result.notifications.forEach(updateNotification);
            announce(result.message);
        } catch (error) {
            announce(error.message);
        } finally {
            bulkForm.removeAttribute('aria-busy');
            updateBulkButton();
        }
    });

    updateBulkButton();
})();
