(function () {
    function assignFieldNames(row, mode, index) {
        row.querySelectorAll('[data-field]').forEach((field) => {
            const fieldName = field.dataset.field;
            field.name = mode === 'single'
                ? `historical_immunization[${fieldName}]`
                : `historical_immunizations[${index}][${fieldName}]`;
        });
    }

    function updateRow(row) {
        const status = row.querySelector('[data-history-status]').value;
        const isAdministered = status === 'ADMINISTERED';
        const precision = row.querySelector('[data-date-precision]').value;
        const exactDate = row.querySelector('[data-date-day]');
        const monthDate = row.querySelector('[data-date-month]');
        const yearDate = row.querySelector('[data-date-year]');
        const exactInput = row.querySelector('[data-field="administered_at"]');
        const monthInput = row.querySelector('[data-field="administered_month"]');
        const yearInput = row.querySelector('[data-field="administered_year"]');
        const isExact = isAdministered && precision === 'DAY';
        const isMonth = isAdministered && precision === 'MONTH';
        const isYear = false;

        row.querySelectorAll('[data-history-administered]').forEach((group) => {
            group.hidden = !isAdministered;
            group.querySelectorAll('input, select').forEach((input) => {
                input.disabled = !isAdministered;
            });
        });
        exactDate.hidden = !isExact;
        monthDate.hidden = !isMonth;
        yearDate.hidden = !(isMonth || isYear);
        exactInput.required = isExact;
        exactInput.disabled = !isExact;
        monthInput.required = isMonth;
        monthInput.disabled = !isMonth;
        yearInput.required = isMonth || isYear;
        yearInput.disabled = !(isMonth || isYear);
        row.querySelector('[data-date-precision]').required = isAdministered;
        row.querySelector('[data-date-precision]').disabled = !isAdministered;
        const verified = row.querySelector('[data-history-verified]');
        verified.required = isAdministered;
        verified.disabled = !isAdministered;
        if (!isMonth) monthInput.value = '';
        if (!isExact) {
            exactInput.value = '';
        }
        if (!isMonth && !isYear) yearInput.value = '';

        const hint = row.querySelector('[data-history-status-hint]');
        hint.textContent = status === 'NOT_ADMINISTERED'
            ? 'This dose will remain in the catch-up schedule.'
            : verified.checked
                ? 'Verified records are checked against age and interval rules.'
                : 'Unverified history will be flagged for review.';

        const vaccineSelect = row.querySelector('[data-historical-vaccine]');
        const selectedOption = vaccineSelect.selectedOptions[0];
        const doseCount = Number(selectedOption?.dataset.doseCount || 3);
        const doseSelect = row.querySelector('[data-field="dose_number"]');
        Array.from(doseSelect.options).forEach((option) => {
            if (option.value) option.disabled = Number(option.value) > doseCount;
        });
        if (Number(doseSelect.value) > doseCount) doseSelect.value = '';
    }

    document.querySelectorAll('[data-historical-immunizations]').forEach((container) => {
        const mode = container.dataset.mode;
        const list = container.querySelector('[data-historical-dose-list]');
        const template = container.querySelector('[data-historical-dose-template]');

        const addRow = () => {
            const row = template.content.firstElementChild.cloneNode(true);
            assignFieldNames(row, mode, list.children.length);
            row.querySelector('[data-date-precision]').addEventListener('change', () => updateRow(row));
            row.querySelector('[data-history-status]').addEventListener('change', () => updateRow(row));
            row.querySelector('[data-historical-vaccine]').addEventListener('change', () => updateRow(row));
            row.querySelector('[data-history-verified]').addEventListener('change', () => updateRow(row));
            row.querySelector('[data-remove-historical-dose]').addEventListener('click', () => {
                row.remove();
                Array.from(list.children).forEach((item, index) => assignFieldNames(item, mode, index));
            });
            list.append(row);
            updateRow(row);
        };

        container.querySelector('[data-add-historical-dose]').addEventListener('click', () => {
            if (mode === 'collection' && list.children.length === 0) {
                ['BCG', 'HEPB_BIRTH'].forEach((epiCode) => {
                    addRow();
                    const row = list.lastElementChild;
                    const vaccineSelect = row.querySelector('[data-historical-vaccine]');
                    const option = Array.from(vaccineSelect.options).find((item) => item.dataset.epiCode === epiCode);
                    if (option) {
                        vaccineSelect.value = option.value;
                        row.querySelector('[data-field="dose_number"]').value = '1';
                        row.querySelector('[data-date-precision]').value = 'DAY';
                        const birthDate = document.querySelector('#date_of_birth')?.value;
                        if (birthDate) row.querySelector('[data-field="administered_at"]').value = birthDate;
                    }
                    updateRow(row);
                });
                return;
            }
            addRow();
        });

        const syncBirthDoseDates = () => {
            if (mode !== 'collection') return;
            const birthDate = document.querySelector('#date_of_birth')?.value;
            if (!birthDate) return;
            Array.from(list.children).forEach((row) => {
                const epiCode = row.querySelector('[data-historical-vaccine]')?.selectedOptions[0]?.dataset.epiCode;
                if (epiCode !== 'BCG' && epiCode !== 'HEPB_BIRTH') return;
                const dateInput = row.querySelector('[data-field="administered_at"]');
                if (dateInput) dateInput.value = birthDate;
            });
        };
        document.querySelector('#date_of_birth')?.addEventListener('change', syncBirthDoseDates);
        if (mode === 'single') addRow();
    });
})();
