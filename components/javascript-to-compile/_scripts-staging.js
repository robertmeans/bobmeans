setTimeout(function() {
  $("#success-wrap").fadeOut(900);
}, 1500);


$(document).ready(function(){
$("#box-01").hide();
$(".click-box-01").click(function(){

if ($('#box-02').is(':visible')) {
  $("#button-box-02").html('<i class="fas fa-feather-alt" aria-hidden="true"></i>&nbsp;&nbsp; manifesto');
  $("#button-box-01").html('<i class="close-attention fa fa-times-circle" aria-hidden="true"></i> &nbsp;&nbsp; never mind &nbsp;&nbsp; <i class="close-attention fa fa-times-circle" aria-hidden="true"></i>');
  $("#box-02").hide('explode', {pieces: 60}, 1000);
  $("#box-01").delay(1200).slideDown(695);
} else {
    $("#box-01").slideToggle(695);
    
    if ($.trim($("#button-box-01").text()) === 'never mind') {
        $("#button-box-01").html('<i class="far fa-comments" aria-hidden="true"></i>&nbsp;&nbsp; chit chat');
    } else {
        $("#button-box-01").html('<i class="close-attention fa fa-times-circle" aria-hidden="true"></i> &nbsp;&nbsp; never mind &nbsp;&nbsp; <i class="close-attention fa fa-times-circle" aria-hidden="true"></i>');
      }
    }
    return false;
  })
});

$(document).ready(function(){
$("#box-02").hide();
$(".click-box-02").click(function(){

if ($('#box-01').is(':visible')) {
  $("#button-box-01").html('<i class="far fa-comments" aria-hidden="true"></i>&nbsp;&nbsp; chit chat');
  $("#button-box-02").html('<i class="close-attention fa fa-times-circle" aria-hidden="true"></i> &nbsp;&nbsp; close &nbsp;&nbsp; <i class="close-attention fa fa-times-circle" aria-hidden="true"></i>');
  $("#box-01").hide('implode'); 
  $("#box-02").delay(500).slideDown(550);
} else {
    $("#box-02").slideToggle(550);
    
    if ($.trim($("#button-box-02").text()) === 'close') {
        $("#button-box-02").html('<i class="fas fa-feather-alt" aria-hidden="true"></i>&nbsp;&nbsp; manifesto');
    } else {
        $("#button-box-02").html('<i class="close-attention fa fa-times-circle" aria-hidden="true"></i> &nbsp;&nbsp; close &nbsp;&nbsp; <i class="close-attention fa fa-times-circle" aria-hidden="true"></i>');
      }
    }
    return false;
  })
});

$(document).ready(function(){
  $(".click-box-03").click(function(){
    $("#box-01").slideUp(250);
  });
});




// contact form
$(document).ready(function() {
  // $('#cloze').click(function() {
  //   event.preventDefault();
  // });
  $('#send').click(function() {
    event.preventDefault();
    $.ajax({
      dataType: "JSON",
      url: "footer_contact_ajax.php",
      type: "POST",
      data: $('#contactForm').serialize(),
      beforeSend: function(xhr) {
        $('#msg').html('<span>Sending - one moment...</span>');
      },
      success: function(response) {
        // console.log(response);
        if(response) {
          console.log(response);
          if(response['signal'] == 'ok') {
            $('#contactForm').html('<span>Your message was sent successfully.</span><br><button class="click-box-03">Reset</button>');
            // $('#msg').html('<button class="click-box-03">Close</button>');
            // $('#send-success').html('<input name="clozer" id="clozer" class="clozer" value="Close">');
          } else {
            $('#msg').html('<div class="alert alert-warning">' + response['msg'] + '</div>');
          }
        } 
      },
      error: function() {
        $('#msg').html('<div class="alert alert-warning">There was an error between your IP and the server. Please try again later.</div>');
      }, 
      complete: function() {
        // $('#contact').html('<span>Your message was sent successfully.</span>');
        // $('#send-success').html('<input name="clozer" id="clozer" class="clozer" value="Close">');
      }
    })
  });
});

