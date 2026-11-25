document.addEventListener('DOMContentLoaded', () => {
    const searchTimers = new WeakMap();

    document.querySelectorAll('form[method="GET"]').forEach((form) => {
        form.querySelectorAll('select, input[type="date"]').forEach((control) => {
            control.addEventListener('change', () => form.requestSubmit());
        });

        form.querySelectorAll('input[type="search"]').forEach((control) => {
            const submitSearch = () => {
                window.clearTimeout(searchTimers.get(control));
                searchTimers.set(control, window.setTimeout(() => form.requestSubmit(), 350));
            };

            control.addEventListener('input', submitSearch);
            control.addEventListener('search', submitSearch);
        });
    });
});
