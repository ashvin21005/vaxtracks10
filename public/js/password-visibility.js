(function () {
    const eyePath = 'M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z';
    const pupilPath = 'M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z';
    const slashPath = 'M3 3l18 18';

    function createIcon(showing) {
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('stroke', 'currentColor');
        svg.setAttribute('stroke-width', '1.8');
        svg.setAttribute('stroke-linecap', 'round');
        svg.setAttribute('stroke-linejoin', 'round');
        svg.setAttribute('aria-hidden', 'true');
        svg.setAttribute('focusable', 'false');

        [eyePath, pupilPath, ...(showing ? [slashPath] : [])].forEach(function (pathData) {
            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('d', pathData);
            svg.appendChild(path);
        });

        return svg;
    }

    document.querySelectorAll('input[type="password"]').forEach(function (input) {
        const wrapper = document.createElement('span');
        wrapper.className = 'password-input-wrapper';
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'password-visibility-toggle';
        toggle.setAttribute('aria-label', 'Show password');
        toggle.setAttribute('aria-pressed', 'false');
        toggle.appendChild(createIcon(false));

        toggle.addEventListener('click', function () {
            const showing = input.type === 'password';
            input.type = showing ? 'text' : 'password';
            toggle.setAttribute('aria-label', showing ? 'Hide password' : 'Show password');
            toggle.setAttribute('aria-pressed', String(showing));
            toggle.replaceChildren(createIcon(showing));
        });

        wrapper.appendChild(toggle);
    });
})();
