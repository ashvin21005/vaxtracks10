(function () {
    const form = document.querySelector('#patient-registration-dialog form');
    if (!form) return;

    const steps = Array.from(form.querySelectorAll('[data-registration-step]'));
    const markers = Array.from(form.querySelectorAll('[data-step-marker]'));
    const previous = form.querySelector('[data-registration-previous]');
    const next = form.querySelector('[data-registration-next]');
    const submit = form.querySelector('[data-registration-submit]');
    const stepLabel = form.querySelector('[data-registration-step-label]');
    const preview = form.querySelector('[data-schedule-preview]');
    const previewRows = form.querySelector('[data-schedule-preview-rows]');
    let activeStep = 0;

    const formatDate = (date) => date.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });

    const addDays = (date, days) => {
        const result = new Date(date);
        result.setDate(result.getDate() + days);
        return result;
    };

    const formatAge = (birthDate) => {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        let years = today.getFullYear() - birthDate.getFullYear();
        let months = today.getMonth() - birthDate.getMonth();
        let days = today.getDate() - birthDate.getDate();
        if (days < 0) {
            months -= 1;
            days += new Date(today.getFullYear(), today.getMonth(), 0).getDate();
        }
        if (months < 0) {
            years -= 1;
            months += 12;
        }
        const weeks = Math.floor(days / 7);

        return `${years}y ${months}m ${weeks}w`;
    };

    const appendPreviewRow = (vaccine, dose, targetAge, proposedDate, status) => {
        const row = document.createElement('tr');
        [vaccine, `Dose ${dose}`, targetAge, proposedDate, status].forEach((value) => {
            const cell = document.createElement('td');
            cell.textContent = value;
            row.append(cell);
        });
        previewRows.append(row);
    };

    const renderSchedulePreview = () => {
        const dobValue = form.elements.date_of_birth?.value;
        previewRows.replaceChildren();
        if (!dobValue) {
            const row = document.createElement('tr');
            row.innerHTML = '<td colspan="5">Enter a date of birth to preview the schedule.</td>';
            previewRows.append(row);
            return;
        }

        const dob = new Date(`${dobValue}T00:00:00`);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const protocols = JSON.parse(preview.dataset.epiProtocols || '{}');
        const histories = new Map();

        form.querySelectorAll('[data-historical-dose]').forEach((row) => {
            const option = row.querySelector('[data-historical-vaccine]')?.selectedOptions[0];
            const code = option?.dataset.epiCode;
            const dose = Number(row.querySelector('[data-field="dose_number"]')?.value);
            if (!code || !dose) return;
            if (!histories.has(code)) histories.set(code, new Map());
            histories.get(code).set(dose, {
                status: row.querySelector('[data-history-status]')?.value || 'ADMINISTERED',
                verified: row.querySelector('[data-field="verified"]')?.checked === true,
                precision: row.querySelector('[data-field="date_precision"]')?.value || '',
                date: row.querySelector('[data-field="administered_at"]')?.value || '',
            });
        });

        Object.entries(protocols).forEach(([code, protocol]) => {
            let previousActualDate = null;
            let previousTarget = null;
            let reviewRequired = false;
            protocol.doses.forEach((minimumAgeDays, index) => {
                const dose = index + 1;
                const history = histories.get(code)?.get(dose);
                const targetAge = `${minimumAgeDays} days`;
                if (reviewRequired || history?.status === 'UNKNOWN' || (history?.status === 'ADMINISTERED' && (!history.verified || history.precision !== 'DAY'))) {
                    appendPreviewRow(protocol.name, dose, targetAge, '—', 'For Review');
                    reviewRequired = true;
                    return;
                }

                if (history?.status === 'ADMINISTERED' && history.date) {
                    const actualDate = new Date(`${history.date}T00:00:00`);
                    const minimumAgeDate = addDays(dob, minimumAgeDays);
                    const interval = Number(protocol.interval_days[index - 1] || 0);
                    const intervalDate = previousActualDate && interval ? addDays(previousActualDate, interval) : null;
                    if (actualDate < minimumAgeDate || (index > 0 && !previousActualDate) || (intervalDate && actualDate < intervalDate)) {
                        appendPreviewRow(protocol.name, dose, targetAge, '—', 'For Review');
                        reviewRequired = true;
                        return;
                    }
                    appendPreviewRow(protocol.name, dose, targetAge, formatDate(actualDate), 'Completed');
                    previousActualDate = actualDate;
                    previousTarget = actualDate;
                    return;
                }

                const ageDue = addDays(dob, minimumAgeDays);
                const interval = Number(protocol.interval_days[index - 1] || 0);
                const intervalBase = previousActualDate || previousTarget;
                const intervalDue = intervalBase && interval ? addDays(intervalBase, interval) : null;
                const dueDate = intervalDue && intervalDue > ageDue ? intervalDue : ageDue;
                const proposed = dueDate < today ? today : dueDate;
                appendPreviewRow(protocol.name, dose, targetAge, formatDate(proposed), proposed <= today ? 'Due / catch-up' : 'Pending');
                previousTarget = proposed;
                previousActualDate = null;
            });
        });
    };

    const updateReview = () => {
        const childName = [form.elements.first_name?.value, form.elements.middle_name?.value, form.elements.last_name?.value].filter(Boolean).join(' ');
        const dobValue = form.elements.date_of_birth?.value;
        const guardianName = [form.elements.guardian_first_name?.value, form.elements.guardian_middle_name?.value, form.elements.guardian_last_name?.value].filter(Boolean).join(' ');
        const childAddressParts = [form.elements.purok?.value, form.elements.street?.value, form.elements.barangay?.selectedOptions[0]?.textContent?.trim()].filter(Boolean);
        const childAddress = childAddressParts.length ? `${childAddressParts.join(', ')}, Tacloban City` : 'Address not entered';
        const birthDate = dobValue ? new Date(`${dobValue}T00:00:00`) : null;
        form.querySelector('[data-review-child]').textContent = `${childName || 'Name not entered'}${birthDate ? ` · born ${formatDate(birthDate)} · ${formatAge(birthDate)}` : ''}`;
        form.querySelector('[data-review-guardian]').textContent = guardianName || 'Guardian details not entered';
        form.querySelector('[data-review-contact]').textContent = form.elements.guardian_contact_number?.value || 'Contact not entered';
        form.querySelector('[data-review-address]').textContent = childAddress;

        const historyItems = [];
        form.querySelectorAll('[data-historical-dose]').forEach((row) => {
            const vaccine = row.querySelector('[data-historical-vaccine]')?.selectedOptions[0]?.textContent?.trim();
            const dose = row.querySelector('[data-field="dose_number"]')?.value;
            if (!vaccine || !dose) return;
            const precision = row.querySelector('[data-field="date_precision"]')?.value;
            let dateText = 'date not entered';
            if (precision === 'DAY') dateText = row.querySelector('[data-field="administered_at"]')?.value || 'date not entered';
            if (precision === 'MONTH') {
                const month = row.querySelector('[data-field="administered_month"]')?.selectedOptions[0]?.textContent;
                const year = row.querySelector('[data-field="administered_year"]')?.value;
                dateText = month && year ? `${month} ${year}` : 'month/year not entered';
            }
            historyItems.push(`${vaccine} - Dose ${dose} - ${dateText}`);
        });
        form.querySelector('[data-review-history]').textContent = historyItems.length ? historyItems.join(' | ') : 'No vaccination history entered';
        renderSchedulePreview();
    };

    const seedBirthDoses = () => {
        const historySection = document.querySelector('[data-registration-step="1"] [data-historical-immunizations]');
        if (!historySection) return;
        const list = historySection.querySelector('[data-historical-dose-list]');
        if (!list || list.children.length > 0) return;

        const addButton = historySection.querySelector('[data-add-historical-dose]');
        if (!addButton) return;

        addButton.click();

        const birthDate = form.elements.date_of_birth?.value;
        if (!birthDate) return;
        historySection.querySelectorAll('[data-historical-dose]').forEach((row) => {
            const epiCode = row.querySelector('[data-historical-vaccine]')?.selectedOptions[0]?.dataset.epiCode;
            if (epiCode !== 'BCG' && epiCode !== 'HEPB_BIRTH') return;
            row.querySelector('[data-field="dose_number"]').value = '1';
            row.querySelector('[data-date-precision]').value = 'DAY';
            row.querySelector('[data-field="administered_at"]').value = birthDate;
        });
    };

    const showStep = (index) => {
        activeStep = index;
        steps.forEach((step, stepIndex) => { step.hidden = stepIndex !== activeStep; });
        markers.forEach((marker, markerIndex) => {
            if (markerIndex === activeStep) marker.setAttribute('aria-current', 'step');
            else marker.removeAttribute('aria-current');
        });
        if (activeStep === 0) {
            previous.hidden = true;
            previous.style.display = 'none';
        } else {
            previous.hidden = false;
            previous.style.display = '';
        }

        if (activeStep === steps.length - 1) {
            next.hidden = true;
            next.style.display = 'none';
            submit.hidden = false;
            submit.style.display = '';
        } else {
            next.hidden = false;
            next.style.display = '';
            submit.hidden = true;
            submit.style.display = 'none';
        }
        stepLabel.textContent = `Step ${activeStep + 1} of ${steps.length}`;
        if (activeStep === 1) seedBirthDoses();
        if (activeStep === steps.length - 1) updateReview();
    };

    const validateStep = () => {
        if (activeStep === 1 && form.querySelectorAll('[data-historical-dose]').length === 0) {
            const addButton = form.querySelector('[data-add-historical-dose]');
            addButton?.focus();
            window.alert('Add at least one vaccination history dose before continuing.');
            return false;
        }
        const controls = steps[activeStep].querySelectorAll('input, select, textarea');
        for (const control of controls) {
            if (control.disabled || control.type === 'hidden' || control.checkValidity()) continue;
            control.reportValidity();
            return false;
        }
        return true;
    };

    form.showRegistrationStep = showStep;

    next.addEventListener('click', () => {
        if (validateStep()) showStep(Math.min(activeStep + 1, steps.length - 1));
    });
    previous.addEventListener('click', () => showStep(Math.max(activeStep - 1, 0)));
    form.addEventListener('input', () => {
        if (activeStep === steps.length - 1) updateReview();
    });
    form.addEventListener('change', () => {
        if (activeStep === steps.length - 1) updateReview();
    });
    showStep(0);
})();