function openNav() {
  const sideNav = document.getElementById("side-nav");
  const isOpen = sideNav.classList.contains("open");

  if (isOpen) {
    $('#side-nav-bkg').fadeOut(501);
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
  $('#side-nav-bkg').fadeOut(501);
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