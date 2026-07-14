function openNav() {
  const sideNav = document.getElementById("side-nav");
  const isOpen = sideNav.classList.contains("open");

  if (isOpen) {
    $('#side-nav-bkg').fadeOut(151);
    sideNav.classList.remove("open");
    $('.top-nav').removeClass('acty');
  } else {
    $('#side-nav-bkg').fadeIn(0);
    sideNav.classList.add("open");
    $('.top-nav').addClass('acty');
  }
}

function closeNav() {
  const sideNav = document.getElementById("side-nav");
  sideNav.classList.remove("open");
  $('#side-nav-bkg').fadeOut(151);
  $('.top-nav').removeClass('acty');
}

function close_navigation_first() {
  const sideNav = document.getElementById("side-nav");

  if (sideNav.classList.contains("open")) {
    closeNav();
  }
}

$(document).ready(function() {
  $("#side-nav-bkg").hide();

  $(".top-nav").on("click", function(e) {
    e.stopPropagation();
    openNav();
  });

  $(".nav-active").on("click", function(e) {
    e.preventDefault();
    e.stopPropagation();
  });

  $("#side-nav").on("click", function(e) {
    e.stopPropagation();
  });
});

$(document).on("click", function() {
  const sideNav = document.getElementById("side-nav");

  if (sideNav.classList.contains("open")) {
    closeNav();
  }
});








document.addEventListener('DOMContentLoaded', function () {
  const cadence = document.getElementById('cadence');

  const dueMonthWrap = document.getElementById('due-month-wrap');
  const dueMonthInput = document.getElementById('due_month_of_year');

  const renewalTermWrap = document.getElementById('renewal-term-wrap');
  const renewalTermInput = document.getElementById('renewal_term_months');

  const actualDueDateWrap = document.getElementById('actual-due-date-wrap');
  const actualDueDateInput = document.getElementById('actual_due_date');

  const dueDayWrap = document.getElementById('due-day-wrap');
  const dueDayInput = document.getElementById('due_day_of_month');

  const defaultAmountWrap = document.getElementById('default-amount-wrap');
  const defaultAmountInput = document.getElementById('default_amount');

  const customDueWrap = document.getElementById('custom-due-events-wrap');
  const customDueContainer = document.getElementById('custom-due-events');
  const addCustomDueButton = document.getElementById('add-custom-due-event');
  const customDueTemplate = document.getElementById('custom-due-event-template');

  function syncCadenceFields() {
    const value = cadence.value;

    if (value === 'monthly') {
      if (dueMonthWrap) dueMonthWrap.style.display = 'none';
      if (dueMonthInput) {
        dueMonthInput.value = '';
        dueMonthInput.required = false;
      }

      if (renewalTermWrap) renewalTermWrap.style.display = '';
      if (renewalTermInput) {
        renewalTermInput.value = '1';
        renewalTermInput.required = true;
      }

      if (actualDueDateWrap) actualDueDateWrap.style.display = '';
      if (actualDueDateInput) actualDueDateInput.required = true;

      if (dueDayWrap) dueDayWrap.style.display = '';
      if (dueDayInput) dueDayInput.required = true;

      if (defaultAmountWrap) defaultAmountWrap.style.display = '';
      if (defaultAmountInput) defaultAmountInput.required = true;

      if (customDueWrap) customDueWrap.style.display = 'none';

    } else if (value === 'annual') {
      if (dueMonthWrap) dueMonthWrap.style.display = '';
      if (dueMonthInput) dueMonthInput.required = false;

      if (renewalTermWrap) renewalTermWrap.style.display = '';
      if (renewalTermInput) {
        renewalTermInput.value = '12';
        renewalTermInput.required = true;
      }

      if (actualDueDateWrap) actualDueDateWrap.style.display = '';
      if (actualDueDateInput) actualDueDateInput.required = true;

      if (dueDayWrap) dueDayWrap.style.display = '';
      if (dueDayInput) dueDayInput.required = true;

      if (defaultAmountWrap) defaultAmountWrap.style.display = '';
      if (defaultAmountInput) defaultAmountInput.required = true;

      if (customDueWrap) customDueWrap.style.display = 'none';

    } else if (value === 'custom') {
      if (dueMonthWrap) dueMonthWrap.style.display = 'none';
      if (dueMonthInput) {
        dueMonthInput.value = '';
        dueMonthInput.required = false;
      }

      if (renewalTermWrap) renewalTermWrap.style.display = 'none';
      if (renewalTermInput) {
        renewalTermInput.required = false;
        renewalTermInput.value = '';
      }

      if (actualDueDateWrap) actualDueDateWrap.style.display = 'none';
      if (actualDueDateInput) {
        actualDueDateInput.required = false;
        actualDueDateInput.value = '';
      }

      if (dueDayWrap) dueDayWrap.style.display = 'none';
      if (dueDayInput) {
        dueDayInput.required = false;
        dueDayInput.value = '';
      }

      if (defaultAmountWrap) defaultAmountWrap.style.display = 'none';
      if (defaultAmountInput) {
        defaultAmountInput.required = false;
        defaultAmountInput.value = '';
      }

      if (customDueWrap) customDueWrap.style.display = '';

    } else {
      if (dueMonthWrap) dueMonthWrap.style.display = '';
      if (renewalTermWrap) renewalTermWrap.style.display = '';
      if (actualDueDateWrap) actualDueDateWrap.style.display = '';
      if (dueDayWrap) dueDayWrap.style.display = '';
      if (defaultAmountWrap) defaultAmountWrap.style.display = '';
      if (customDueWrap) customDueWrap.style.display = 'none';
    }
  }

  function bindRemoveButtons() {
    const buttons = document.querySelectorAll('.remove-custom-due-event');

    buttons.forEach(function (button) {
      button.onclick = function () {
        const rows = document.querySelectorAll('.custom-due-event');

        if (rows.length > 1) {
          const row = button.closest('.custom-due-event');
          if (row) {
            row.remove();
          }
        }
      };
    });
  }

  if (addCustomDueButton && customDueTemplate && customDueContainer) {
    addCustomDueButton.addEventListener('click', function () {
      const clone = customDueTemplate.content.cloneNode(true);
      customDueContainer.appendChild(clone);
      bindRemoveButtons();
    });
  }

  if (cadence) {
    cadence.addEventListener('change', syncCadenceFields);
    syncCadenceFields();
  }

  bindRemoveButtons();
});




document.addEventListener('DOMContentLoaded', function () {
  const amountInput = document.getElementById('adjustment_amount');

  function formatAmount(value) {
    const numeric = value.replace(/,/g, '').trim();
    if (numeric === '' || isNaN(numeric)) {
      return value;
    }

    return Number(numeric).toLocaleString('en-US', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
  }

  function unformatAmount(value) {
    return value.replace(/,/g, '').trim();
  }

  if (amountInput) {
    amountInput.addEventListener('blur', function () {
      amountInput.value = formatAmount(amountInput.value);
    });

    amountInput.addEventListener('focus', function () {
      amountInput.value = unformatAmount(amountInput.value);
    });

    amountInput.form?.addEventListener('submit', function () {
      amountInput.value = unformatAmount(amountInput.value);
    });

    if (amountInput.value.trim() !== '') {
      amountInput.value = formatAmount(amountInput.value);
    }
  }
});

document.addEventListener('DOMContentLoaded', function () {
  const amountInput = document.getElementById('new_actual_amount');

  function formatAmount(value) {
    const numeric = value.replace(/,/g, '').trim();
    if (numeric === '' || isNaN(numeric)) {
      return value;
    }

    return Number(numeric).toLocaleString('en-US', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
  }

  function unformatAmount(value) {
    return value.replace(/,/g, '').trim();
  }

  if (amountInput) {
    amountInput.addEventListener('blur', function () {
      amountInput.value = formatAmount(amountInput.value);
    });

    amountInput.addEventListener('focus', function () {
      amountInput.value = unformatAmount(amountInput.value);
    });

    amountInput.form?.addEventListener('submit', function () {
      amountInput.value = unformatAmount(amountInput.value);
    });

    if (amountInput.value.trim() !== '') {
      amountInput.value = formatAmount(amountInput.value);
    }
  }
});

/* homepage bill search */
document.addEventListener('DOMContentLoaded', function () {
  const input = document.getElementById('bill-search');
  const resultsWrap = document.getElementById('bill-search-results');
  const emptyMessage = document.getElementById('bill-search-empty');

  if (!input || !resultsWrap) return;

  const results = Array.from(
    resultsWrap.querySelectorAll('.bill-search-result')
  );

  function normalize(value) {
    return value.toLowerCase().trim();
  }

  function updateResults() {
    const query = normalize(input.value);
    let visibleCount = 0;

    if (query.length < 2) {
      resultsWrap.hidden = true;

      results.forEach(function (result) {
        result.hidden = true;
      });

      if (emptyMessage) {
        emptyMessage.hidden = true;
      }

      return;
    }

    resultsWrap.hidden = false;

    results.forEach(function (result) {
      const searchText = result.dataset.search || '';
      const isMatch = searchText.includes(query);

      result.hidden = !isMatch;

      if (isMatch) {
        visibleCount++;
      }
    });

    if (emptyMessage) {
      emptyMessage.hidden = visibleCount > 0;
    }
  }

  input.addEventListener('input', updateResults);
});