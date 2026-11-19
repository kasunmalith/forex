(() => {
    'use strict';
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.passwordToggle);
            if (!input) return;
            const showing = input.type === 'password';
            input.type = showing ? 'text' : 'password';
            button.textContent = showing ? 'Hide' : 'Show';
            button.setAttribute('aria-label', `${showing ? 'Hide' : 'Show'} password`);
            button.setAttribute('aria-pressed', String(showing));
        });
    });
    document.querySelectorAll('[data-auth-form]').forEach((form) => {
        const password = form.elements.namedItem('password');
        const confirmation = form.elements.namedItem('password_confirmation');
        const validateMatch = () => {
            if (password && confirmation) confirmation.setCustomValidity(confirmation.value && password.value !== confirmation.value ? 'The passwords do not match.' : '');
        };
        password?.addEventListener('input', validateMatch);
        confirmation?.addEventListener('input', validateMatch);
        const firstInvalid = form.querySelector('[aria-invalid="true"]');
        if (firstInvalid) firstInvalid.focus({ preventScroll: true });
        form.addEventListener('submit', (event) => {
            validateMatch();
            if (!form.reportValidity()) { event.preventDefault(); return; }
            const submit = form.querySelector('[type="submit"]');
            if (submit) {
                submit.disabled = true;
                submit.textContent = `${submit.dataset.submitLabel || 'Working'}…`;
            }
        });
    });
})();
