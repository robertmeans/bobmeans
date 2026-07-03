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
  const renewalTermInput = document.getElementById('renewal_term_months');

  function syncCadenceFields() {
    const value = cadence.value;

    if (value === 'monthly') {
      dueMonthWrap.style.display = 'none';
      dueMonthInput.value = '';
      renewalTermInput.value = '1';
    } else if (value === 'annual') {
      dueMonthWrap.style.display = '';
      renewalTermInput.value = '12';
    } else {
      dueMonthWrap.style.display = '';
    }
  }

  if (cadence && dueMonthWrap && dueMonthInput && renewalTermInput) {
    cadence.addEventListener('change', syncCadenceFields);
    syncCadenceFields();
  }
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

