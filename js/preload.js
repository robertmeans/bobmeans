function recaptchaCallback() {
    $('#confirm').addClass('display');
    $('#send').removeAttr('disabled');
    $('#send').removeClass('display');
	};

$(window).on('load', function() {
    $(".preload").delay(250).fadeOut(500);
    });
