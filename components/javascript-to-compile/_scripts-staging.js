// $.fx.speeds.slow = 1500; // 'slow' now means 1.5 seconds
// $(document).ready(function() {
//    window.setTimeout("fadeMyDiv();", 3000); //call fade in 3 seconds
//  }
// )
// function fadeMyDiv() {
//    // $("#success-fade").hide('slow');
//    $('#success-fade').slideUp('slow');
// }

// validate general contact
function validateEmail(emailStr) {
var emailPat=/^(.+)@(.+)$/
var specialChars="\\(\\)<>@,;:\\\\\\\"\\.\\[\\]"
var validChars="\[^\\s" + specialChars + "\]"
var quotedUser="(\"[^\"]*\")"
var ipDomainPat=/^\[(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})\]$/
var atom=validChars + '+'
var word="(" + atom + "|" + quotedUser + ")"
var userPat=new RegExp("^" + word + "(\\." + word + ")*$")
var domainPat=new RegExp("^" + atom + "(\\." + atom +")*$")
var matchArray=emailStr.match(emailPat)
if (document.forms[0].email.value == "")
      {
      alert("\nThe e-mail field is blank.\n\nPlease enter your e-mail address.")
      document.forms[0].email.focus()
      return false
}
if (matchArray==null) {
  /* Too many/few @'s or something; basically, this address doesn't
     even fit the general mould of a valid e-mail address. */
  alert("_____________________________\n\nYour e-mail address seems incorrect. Please check the following\n\n1. Did you include the \"@\" and the \" . \" (dot)?\n2. Did you include anything other than a \"@\" & \" . \"?\n\nPlease re-enter your e-mail address.\n_____________________________")
  document.forms[0].email.select();
    document.forms[0].email.focus();
  return false
}
var user=matchArray[1]
var domain=matchArray[2]
if (user.match(userPat)==null) {
    // user is not valid
    alert("_____________________________\n\nThe username does not seem to be valid.\n\nPlease check the following:\n\n1. That you entered your e-mail address correctly.\n\nThank you.\n_____________________________")
    document.forms[0].email.select();
    document.forms[0].email.focus();
    return false
}
var IPArray=domain.match(ipDomainPat)
if (IPArray!=null) {
    // this is an IP address
    for (var i=1;i<=4;i++) {
      if (IPArray[i]>255) {
          alert("_____________________________\n\nThe destination IP address you entered is invalid.\n\nPlease check your e-mail address and make the necessary corrections.\n\nThank you.\n_____________________________")
          document.forms[0].email.select();
      document.forms[0].email.focus();
    return false
      }
    }
    return true
}
var domainArray=domain.match(domainPat)
if (domainArray==null) {
  alert("_____________________________\n\nAre you making stuff up now?\n\nThe e-mail address portion of this form is not something to scoff at.\n\nI've been placed here in  your computer to make sure your information is valid. You\nneed to enter your real e-mail address or successfully fake me out to proceed.\n\nThank you.\n_____________________________")
  document.forms[0].email.select();
  document.forms[0].email.focus();
    return false
}
var atomPat=new RegExp(atom,"g")
var domArr=domain.match(atomPat)
var len=domArr.length
if (domArr[domArr.length-1].length<2 ||
    domArr[domArr.length-1].length>3) {
   // the address must end in a two letter or three letter word.
   alert("_____________________________\n\nYour e-mail address must end in a three-letter domain, or two letter country.\n\n_____________________________")
   document.forms[0].email.select();
   document.forms[0].email.focus();
   return false
}
if (len<2) {
   var errStr="_____________________________\n\nYour e-mail address is missing either a username, a hostname or a domain.\nAn e-mail address should include these three basic components:\n\n1. A username - (e.g., YOURNAME@yahoo.com, YOURNAME@mho.net)\n2. A host - (e.g., yourname@YAHOO.com, yourname@MHO.net)\n3. A domain - (e.g., yourname@yahoo.COM, yourname@mho.NET)\n\nPlease make the necessary corrections and press \"Send\".\n-- Thank you, The unforgiving script validating this e-mail field.\n\n_____________________________"
   alert(errStr)
   document.forms[0].email.select();
   document.forms[0].email.focus();
   return false
}
return true;
}

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

