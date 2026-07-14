/* modal for deleting scheduled overrides on bill_details.php page */ 
document.addEventListener('DOMContentLoaded', function () {
  const modal = document.getElementById('app-modal');
  if (!modal) return;

  const form = document.getElementById('app-modal-form');
  const titleEl = document.getElementById('app-modal-title');
  const leadEl = document.getElementById('app-modal-lead');
  const detailsEl = document.getElementById('app-modal-details');
  const warningEl = document.getElementById('app-modal-warning');
  const noteEl = document.getElementById('app-modal-note');
  const errorEl = document.getElementById('app-modal-error');
  const confirmBtn = document.getElementById('app-modal-confirm');
  const hiddenFieldsEl = document.getElementById('app-modal-hidden-fields');

  function showText(el, text) {
    if (!el) return;

    if (text && text.trim() !== '') {
      el.textContent = text;
      el.style.display = '';
    } else {
      el.textContent = '';
      el.style.display = 'none';
    }
  }

  function labelFromKey(key) {
    return key
      .replace(/^modalDetail/, '')
      .replace(/([A-Z])/g, ' $1')
      .replace(/^./, function (letter) {
        return letter.toUpperCase();
      })
      .trim();
  }

  function clearModal() {
    titleEl.textContent = '';
    showText(leadEl, '');
    showText(warningEl, '');
    showText(noteEl, '');
    showText(errorEl, '');

    detailsEl.innerHTML = '';
    detailsEl.style.display = 'none';

    hiddenFieldsEl.innerHTML = '';

    form.setAttribute('method', 'post');
    form.setAttribute('action', '');

    confirmBtn.textContent = 'Confirm';
    confirmBtn.disabled = false;

    modal.classList.remove(
      'app-modal--warning',
      'app-modal--danger',
      'app-modal--success',
      'app-modal--form'
    );
  }

  function addHiddenInput(name, value) {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    hiddenFieldsEl.appendChild(input);
  }

  function openModal(trigger) {
    clearModal();

    titleEl.textContent = trigger.dataset.modalTitle || 'Confirm Action';

    showText(leadEl, trigger.dataset.modalLead || '');
    showText(warningEl, trigger.dataset.modalWarning || '');
    showText(noteEl, trigger.dataset.modalNote || '');

    confirmBtn.textContent = trigger.dataset.modalConfirmText || 'Confirm';

    if (trigger.dataset.modalAction) {
      form.setAttribute('action', trigger.dataset.modalAction);
    }

    if (trigger.dataset.modalMethod) {
      form.setAttribute('method', trigger.dataset.modalMethod);
    }

     form.dataset.ajax = trigger.dataset.modalAjax=== '1' ? '1' : '0';

    if (trigger.dataset.modalVariant) {
      modal.classList.add('app-modal--' + trigger.dataset.modalVariant);
    }

    Object.keys(trigger.dataset).forEach(function (key) {
      if (key.indexOf('modalHidden') === 0) {
        const inputName = key
          .replace(/^modalHidden/, '')
          .replace(/([A-Z])/g, '_$1')
          .replace(/^_/, '')
          .toLowerCase();

        addHiddenInput(inputName, trigger.dataset[key]);
      }
    });

    Object.keys(trigger.dataset).forEach(function (key) {
      if (key.indexOf('modalDetail') === 0) {
        const value = trigger.dataset[key];

        if (!value || value.trim() === '') {
          return;
        }

        const row = document.createElement('div');

        const label = document.createElement('span');
        label.textContent = labelFromKey(key) + ': ';

        const strong = document.createElement('strong');
        strong.textContent = value;

        row.appendChild(label);
        row.appendChild(strong);

        detailsEl.appendChild(row);
      }
    });

    if (detailsEl.children.length > 0) {
      detailsEl.style.display = '';
    }

    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }

  function closeModal() {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    clearModal();
  }

  document.querySelectorAll('.js-app-modal-trigger').forEach(function (trigger) {
    trigger.addEventListener('click', function () {
      openModal(trigger);
    });
  });

  document.querySelectorAll('[data-modal-close]').forEach(function (closer) {
    closer.addEventListener('click', closeModal);
  });

  document.addEventListener('keydown', function (event) {
    if (
      event.key === 'Escape' &&
      modal.classList.contains('is-open')
    ) {
      closeModal();
    }
  });

  /* process early on billing_projection.php */
  form.addEventListener('submit', function (event) {
    if (form.dataset.ajax !== '1') {
      return;
    }

    event.preventDefault();

    confirmBtn.disabled = true;
    showText(errorEl, '');

    const formData = new FormData(form);

    fetch(form.getAttribute('action') || window.location.href, {
      method: form.getAttribute('method') || 'POST',
      body: formData,
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
      .then(function (response) {
        return response.json();
      })
      .then(function (data) {
        if (data.success && data.redirect) {
          window.location.href = data.redirect;
          return;
        }

        showText(
          errorEl,
          data.message || 'Unable to complete this action.'
        );

        confirmBtn.disabled = false;
      })
      .catch(function () {
        showText(
          errorEl,
          'Something went wrong while trying to complete this action.'
        );

        confirmBtn.disabled = false;
      });
  });
});

