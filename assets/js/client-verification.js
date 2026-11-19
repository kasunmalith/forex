(() => {
  'use strict';
  document.addEventListener('change', (event) => {
    const input = event.target.closest('input[type="file"][name="document"]');
    if (!input) return;
    const output = input.closest('.kyc-file-picker')?.querySelector('[data-kyc-file-name]');
    const file = input.files?.[0];
    if (output) output.textContent = file ? file.name + ' · ' + (file.type || 'unknown type') + ' · ' + (file.size / 1048576).toFixed(2) + ' MB' : 'No file selected';
  });
  document.addEventListener('submit', async (event) => {
    const form = event.target.closest('form[data-kyc-ajax]');
    if (!form) return;
    event.preventDefault();
    const button = form.querySelector('button[type="submit"]');
    const message = form.querySelector('[data-kyc-message]');
    if (button) button.disabled = true;
    if (message) message.textContent = 'Saving…';
    try {
      const response = await fetch(form.action, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: new FormData(form), cache: 'no-store' });
      if (response.url.includes('/login')) { location.assign(response.url); return; }
      const payload = await response.json();
      if (!response.ok || !payload.success) throw new Error(payload.message || 'The verification update could not be saved.');
      if (message) message.textContent = payload.message || 'Saved.';
      location.reload();
    } catch (error) {
      if (message) message.textContent = error.message || 'The request could not be completed. Please try again.';
      if (button) button.disabled = false;
    }
  });
})();
