(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    function addFormError(form, message) {
        let box = form.querySelector('.api-form-error');
        if (!box) {
            box = document.createElement('div');
            box.className = 'error-box api-form-error';
            box.setAttribute('role', 'alert');
            form.prepend(box);
        }
        box.textContent = message;
    }

    function fieldNameForApiError(name) {
        if (name === 'inventory') return 'vaccine';
        name = name.replace(/^historical_immunizations\.(\d+)\.(.+)$/, 'historical_immunizations[$1][$2]')
            .replace(/^historical_immunization\.(.+)$/, 'historical_immunization[$1]');
        return name.replace(/^guardian\./, 'guardian_');
    }

    function showValidationErrors(form, errors) {
        let firstStep = null;
        Object.entries(errors).forEach(([apiName, messages]) => {
            const fieldName = fieldNameForApiError(apiName);
            const field = Array.from(form.elements).find((element) => element.name === fieldName);
            const message = Array.isArray(messages) ? messages[0] : String(messages);
            if (!field) {
                addFormError(form, message);
                return;
            }

            field.classList.add('is-invalid');
            const step = field.closest('[data-registration-step]');
            if (step && firstStep === null) firstStep = Number(step.dataset.registrationStep);
            const container = field.closest('label, .field') || field.parentElement;
            const error = document.createElement('small');
            error.className = 'field-error api-field-error';
            error.textContent = message;
            container.append(error);
        });
        if (firstStep !== null) form.showRegistrationStep?.(firstStep);
    }

    function clearApiErrors(form) {
        form.querySelectorAll('.api-form-error, .api-field-error').forEach((error) => error.remove());
        form.querySelectorAll('.is-invalid').forEach((field) => field.classList.remove('is-invalid'));
    }

    function patientPayload(form) {
        const values = new FormData(form);
        const payload = {};
        values.forEach((value, name) => {
            if (!['_token', '_method'].includes(name)
                && !name.startsWith('guardian_')
                && !name.startsWith('historical_immunizations[')) {
                payload[name] = value;
            }
        });
        const barangayText = form.elements.barangay?.selectedOptions[0]?.textContent?.trim() || values.get('barangay') || '';
        const childAddress = [values.get('purok'), values.get('street'), barangayText].filter(Boolean).join(', ');
        const completeChildAddress = childAddress ? `${childAddress}, Tacloban City` : null;
        payload.address = completeChildAddress;
        const placeType = values.get('place_of_birth_type');
        payload.place_of_birth = placeType || null;
        const historicalDoses = historicalDosePayloads(form);
        if (historicalDoses.length) payload.historical_immunizations = historicalDoses;
        const sameAsChildAddress = values.get('guardian_address_same_as_child') === '1';
        payload.guardian = {
            first_name: values.get('guardian_first_name'),
            middle_name: values.get('guardian_middle_name') || null,
            last_name: values.get('guardian_last_name'),
            relationship: values.get('guardian_relationship'),
            contact_number: values.get('guardian_contact_number'),
            alternative_contact_number: values.get('guardian_alternative_contact_number') || null,
            occupation: values.get('guardian_occupation') || null,
            address: sameAsChildAddress ? completeChildAddress : values.get('guardian_address') || null,
        };
        return payload;
    }

    function historicalDosePayloads(form) {
        return Array.from(form.querySelectorAll('[data-historical-dose]')).map((row) => {
            const value = (field) => row.querySelector(`[data-field="${field}"]`)?.value || null;
            const dose = {
                vaccine_id: value('vaccine_id'),
                dose_number: value('dose_number'),
                history_status: value('history_status') || 'ADMINISTERED',
                date_precision: value('date_precision'),
                administered_at: value('administered_at'),
                administered_month: value('administered_month'),
                administered_year: value('administered_year'),
            };
            if (row.querySelector('[data-field="guardian_reported"]')?.checked) dose.guardian_reported = '1';
            if (row.querySelector('[data-field="verified"]')?.checked) dose.verified = '1';

            return dose;
        });
    }

    function historicalImmunizationPayload(form) {
        return { historical_immunization: historicalDosePayloads(form)[0] };
    }

    function immunizationPayload(form) {
        const values = new FormData(form);
        const vaccine = form.querySelector('[name="vaccine"]');
        const vaccineId = vaccine?.selectedOptions[0]?.dataset.vaccineId;
        if (!vaccineId) {
            throw new Error('The selected vaccine is not available in the vaccine catalog.');
        }
        return {
            child_id: values.get('child_id'),
            vaccine_id: vaccineId,
            dose_number: values.get('dose_number'),
            administered_at: values.get('administered_at'),
        };
    }

    function scheduledImmunizationPayload(form) {
        const values = new FormData(form);
        return {
            child_id: values.get('child_id'),
            vaccine_id: values.get('vaccine_id'),
            dose_number: values.get('dose_number'),
            administered_at: values.get('administered_at'),
        };
    }

    document.querySelectorAll('form[data-api-form]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            clearApiErrors(form);

            const submit = form.querySelector('button[type="submit"]');
            const originalText = submit?.textContent;
            if (submit) {
                submit.disabled = true;
                submit.setAttribute('aria-busy', 'true');
                submit.textContent = form.dataset.apiResource === 'patient' ? 'Saving patient…' : 'Saving record…';
            }
            form.setAttribute('aria-busy', 'true');

            try {
                let url = form.dataset.apiUrl;
                const method = form.dataset.apiMethod;
                let payload;
                if (form.dataset.apiResource === 'patient') {
                    payload = patientPayload(form);
                } else if (form.dataset.apiResource === 'historical-immunization') {
                    payload = historicalImmunizationPayload(form);
                } else if (form.dataset.apiResource === 'scheduled-immunization') {
                    payload = scheduledImmunizationPayload(form);
                } else {
                    payload = immunizationPayload(form);
                }
                if (method === 'POST' && typeof payload.child_id !== 'undefined' && url.includes('__child_id__')) {
                    url = url.replace('__child_id__', encodeURIComponent(payload.child_id));
                    delete payload.child_id;
                }

                const response = await fetch(url, {
                    method,
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken || '',
                    },
                    body: JSON.stringify(payload),
                });
                const result = await response.json();
                if (!response.ok) {
                    if (result.errors) {
                        showValidationErrors(form, result.errors);
                    } else {
                        addFormError(form, result.message || 'The request could not be completed. Please try again.');
                    }
                    return;
                }

                const savedId = result.data?.id;
                const successUrl = form.dataset.successUrl.replace('__patient_id__', encodeURIComponent(savedId));
                if (form.dataset.modalSuccess) {
                    const dialog = form.closest('dialog') || document.querySelector('[data-workflow-result-dialog]');
                    if (!dialog) throw new Error('The success confirmation dialog is unavailable.');
                    const formContent = dialog.querySelector('[data-modal-form-content]');
                    if (formContent) formContent.hidden = true;
                    const successPanel = dialog.querySelector('[data-modal-success-panel]');
                    const title = successPanel.querySelector('[data-modal-success-title]');
                    const message = successPanel.querySelector('[data-modal-success-message]');
                    if (title && form.dataset.successTitle) title.textContent = form.dataset.successTitle;
                    if (message && form.dataset.successMessage) message.textContent = form.dataset.successMessage;
                    successPanel.hidden = false;
                    if (form.dataset.modalSuccess === 'patient') {
                        successPanel.querySelector('[data-registered-child-name]').textContent = result.data?.full_name || 'Patient';
                        successPanel.querySelector('[data-registered-baby-book-id]').textContent = result.data?.baby_book_id || '';
                        successPanel.querySelector('[data-registered-child-link]').href = successUrl;
                    }
                    if (!dialog.open) dialog.showModal();
                    window.lucide?.createIcons();
                    return;
                }
                window.location.assign(successUrl);
            } catch (error) {
                if (error instanceof TypeError) {
                    addFormError(form, 'Could not connect to the application API. Check your connection and try again.');
                } else {
                    addFormError(form, error.message);
                }
            } finally {
                form.removeAttribute('aria-busy');
                if (submit) {
                    submit.disabled = false;
                    submit.removeAttribute('aria-busy');
                    submit.textContent = originalText;
                }
            }
        });
    });
})();
