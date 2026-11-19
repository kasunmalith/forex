(() => {
  'use strict';

  const header = document.querySelector('[data-site-header]');
  const mobileMenu = document.querySelector('[data-mobile-menu]');
  const openMenuButton = document.querySelector('[data-menu-open]');
  const closeMenuButton = document.querySelector('[data-menu-close]');

  const updateHeader = () => {
    if (header) header.classList.toggle('is-scrolled', window.scrollY > 10);
  };
  updateHeader();
  window.addEventListener('scroll', updateHeader, { passive: true });

  const dropdowns = [...document.querySelectorAll('[data-dropdown]')];
  const closeDropdown = (dropdown, restoreFocus = false) => {
    const trigger = dropdown.querySelector('[data-dropdown-trigger]');
    const panel = trigger ? document.getElementById(trigger.getAttribute('aria-controls')) : null;
    if (!trigger || !panel) return;
    trigger.setAttribute('aria-expanded', 'false');
    panel.hidden = true;
    if (restoreFocus) trigger.focus();
  };
  const openDropdown = (dropdown) => {
    dropdowns.forEach((item) => {
      if (item !== dropdown) closeDropdown(item);
    });
    const trigger = dropdown.querySelector('[data-dropdown-trigger]');
    const panel = trigger ? document.getElementById(trigger.getAttribute('aria-controls')) : null;
    if (!trigger || !panel) return;
    trigger.setAttribute('aria-expanded', 'true');
    panel.hidden = false;
  };

  dropdowns.forEach((dropdown) => {
    const trigger = dropdown.querySelector('[data-dropdown-trigger]');
    const panel = trigger ? document.getElementById(trigger.getAttribute('aria-controls')) : null;
    if (!trigger || !panel) return;
    trigger.addEventListener('click', () => openDropdown(dropdown));
    dropdown.addEventListener('pointerenter', (event) => {
      if (event.pointerType === 'mouse') openDropdown(dropdown);
    });
    dropdown.addEventListener('pointerleave', (event) => {
      if (event.pointerType === 'mouse' && !dropdown.contains(document.activeElement)) closeDropdown(dropdown);
    });
    dropdown.addEventListener('focusout', () => {
      window.setTimeout(() => {
        if (!dropdown.contains(document.activeElement)) closeDropdown(dropdown);
      }, 0);
    });
    trigger.addEventListener('keydown', (event) => {
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        openDropdown(dropdown);
        const links = [...panel.querySelectorAll('a')];
        links[event.key === 'ArrowUp' ? links.length - 1 : 0]?.focus();
      } else if (event.key === 'Escape') {
        closeDropdown(dropdown, true);
      }
    });
    panel.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        closeDropdown(dropdown, true);
      }
    });
  });

  document.addEventListener('pointerdown', (event) => {
    dropdowns.forEach((dropdown) => {
      if (!dropdown.contains(event.target)) closeDropdown(dropdown);
    });
  });

  if (mobileMenu && openMenuButton) {
    const closeMobileMenu = () => {
      if (mobileMenu.open) mobileMenu.close();
    };
    openMenuButton.addEventListener('click', () => {
      if (typeof mobileMenu.showModal === 'function') mobileMenu.showModal();
      else mobileMenu.setAttribute('open', '');
      openMenuButton.setAttribute('aria-expanded', 'true');
      document.body.classList.add('nav-open');
    });
    closeMenuButton?.addEventListener('click', closeMobileMenu);
    mobileMenu.addEventListener('click', (event) => {
      if (event.target === mobileMenu) closeMobileMenu();
      if (event.target.closest('a')) closeMobileMenu();
    });
    mobileMenu.addEventListener('close', () => {
      openMenuButton.setAttribute('aria-expanded', 'false');
      document.body.classList.remove('nav-open');
      openMenuButton.focus();
    });
  }

  document.addEventListener('click', (event) => {
    const dismiss = event.target.closest('[data-dismiss-alert]');
    if (dismiss) dismiss.closest('[data-alert]')?.remove();

    const passwordToggle = event.target.closest('[data-password-toggle]');
    if (passwordToggle) {
      const field = document.getElementById(passwordToggle.dataset.passwordToggle);
      if (field) {
        const show = field.type === 'password';
        field.type = show ? 'text' : 'password';
        passwordToggle.setAttribute('aria-pressed', String(show));
        passwordToggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        passwordToggle.textContent = show ? 'Hide' : 'Show';
      }
    }

    const modalTrigger = event.target.closest('[data-modal-open]');
    if (modalTrigger) {
      const selector = modalTrigger.getAttribute('data-modal-open');
      const dialog = selector ? document.querySelector(selector) : null;
      if (dialog instanceof HTMLDialogElement && typeof dialog.showModal === 'function') {
        dialog._returnFocus = modalTrigger;
        dialog.showModal();
      }
    }

    const modalClose = event.target.closest('[data-modal-close]');
    if (modalClose) modalClose.closest('dialog')?.close();

    const modalConfirm = event.target.closest('[data-modal-confirm]');
    if (modalConfirm) {
      const dialog = modalConfirm.closest('dialog');
      dialog?.dispatchEvent(new CustomEvent('modalconfirm', { bubbles: true }));
      dialog?.close();
    }

    const toastTrigger = event.target.closest('[data-toast]');
    if (toastTrigger) {
      const type = toastTrigger.dataset.toast === 'warning' ? 'warning' : 'success';
      window.ForexUI.toast({
        type,
        title: type === 'warning' ? 'Simulated values' : 'Saved',
        message: type === 'warning' ? 'This preview uses static demo values.' : 'This is a local interface preview.',
      });
    }

    const retry = event.target.closest('[data-retry]');
    if (retry) retry.dispatchEvent(new CustomEvent('forex:retry', { bubbles: true }));
  });

  document.querySelectorAll('dialog.modal').forEach((dialog) => {
    dialog.addEventListener('click', (event) => {
      if (event.target === dialog) dialog.close();
    });
    dialog.addEventListener('close', () => dialog._returnFocus?.focus());
  });

  document.addEventListener('submit', (event) => {
    if (event.target.matches('[data-demo-form]')) {
      event.preventDefault();
      window.ForexUI.toast({
        type: 'success',
        title: 'Preview only',
        message: 'This form did not send or save any data.',
      });
    }
  });

  window.ForexUI = {
    toast({ type = 'info', title = '', message = '', duration = 5000 } = {}) {
      const region = document.querySelector('[data-toast-region]');
      if (!region) return;
      const safeType = ['success', 'warning', 'error', 'info'].includes(type) ? type : 'info';
      const toast = document.createElement('div');
      toast.className = `alert alert-${safeType}`;
      toast.setAttribute('role', safeType === 'error' ? 'alert' : 'status');
      toast.innerHTML = '<span class="icon alert-icon" aria-hidden="true"></span><div><h3 class="alert-title"></h3><p class="alert-message"></p></div><button class="alert-dismiss" type="button" aria-label="Dismiss notification" data-toast-dismiss>×</button>';
      toast.querySelector('.alert-title').textContent = title;
      toast.querySelector('.alert-message').textContent = message;
      toast.querySelector('[data-toast-dismiss]').addEventListener('click', () => toast.remove());
      region.append(toast);
      window.setTimeout(() => toast.remove(), Math.max(1200, duration));
    },
  };
})();
