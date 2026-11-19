(() => {
    'use strict';
    const form = document.querySelector('[data-contact-form]');
    if (!form) return;
    const status = document.querySelector('[data-contact-status]');
    const fields = [...form.querySelectorAll('input[name],select[name],textarea[name]')];
    const clearErrors = () => {
        form.querySelectorAll('[data-field-error]').forEach((node) => { node.textContent = ''; node.hidden = true; });
        fields.forEach((field) => field.removeAttribute('aria-invalid'));
    };
    form.addEventListener('input', (event) => {
        const field = event.target;
        const error = form.querySelector(`[data-field-error="${CSS.escape(field.name)}"]`);
        if (error) { error.hidden = true; error.textContent = ''; field.removeAttribute('aria-invalid'); }
    });
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearErrors();
        status.hidden = true;
        if (!form.reportValidity()) return;
        const submit = form.querySelector('[type="submit"]');
        submit.disabled = true;
        submit.textContent = 'Sending…';
        try {
            const response = await fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const payload = await response.json();
            status.textContent = payload.message || (payload.success ? 'Thank you. Your message has been received.' : 'Please review the form and try again.');
            status.className = `contact-form-status ${payload.success ? 'is-success' : 'is-error'}`;
            status.hidden = false;
            if (payload.errors) {
                Object.entries(payload.errors).forEach(([name, message]) => {
                    const field = form.elements.namedItem(name);
                    const error = form.querySelector(`[data-field-error="${CSS.escape(name)}"]`);
                    if (field) field.setAttribute('aria-invalid', 'true');
                    if (error) { error.textContent = message; error.hidden = false; }
                });
            }
            if (payload.success) form.reset();
        } catch {
            status.textContent = 'The enquiry service is not reachable right now. Please try again later.';
            status.className = 'contact-form-status is-error';
            status.hidden = false;
        } finally {
            submit.disabled = false;
            submit.innerHTML = 'Send enquiry <span aria-hidden="true">↗</span>';
        }
    });
})();
