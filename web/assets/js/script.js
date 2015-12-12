// Initialize Home Page Masonry
var $masonryContainer = $('#content').imagesLoaded(function(){
    $(this).masonry({
      itemSelector: '.item'
      // ,isAnimated: true
    });
});

// Load more masonry recipes on request
var masonryPage = 2;
$('#more-recipes-button').on('click', function() {
  $.ajax({
    url: 'getmorephotorecipes/' + masonryPage,
    success: function(newElements) {
      if ($(newElements).is('div')) {
        var $newElems = $(newElements).css({opacity: 0});
        $newElems.imagesLoaded(function() {
          $newElems.animate({opacity: 1});
          $masonryContainer.append($newElems).masonry('appended', $newElems, true);
        });
        masonryPage++;
      } else {
        // Hide more button if we have no more results
        $('#more-recipes-button').hide();
      }
    }
  });
});

// This keeps the footer in the footer
var bumpIt = function() {
    $('body').css('margin-bottom', $('.footer').height()+50);
  },
  didResize = false;
bumpIt();

$(window).resize(function() {
  didResize = true;
});
setInterval(function() {
  if(didResize) {
    didResize = false;
    bumpIt();
  }
}, 250);

//Select menu onchange
$("#collapsed-navbar").change(function () {
  window.location = $(this).val();
});

// On scroll down fix navbar for SM and wider widths
$(document).on("scroll", function() {
  if ($('#header').is(':visible')) {
    if ($(document).scrollTop() > 125) {
      $('nav.navbar').addClass('navbar-fixed-top');
      $('body').css('padding-top','58px');
    } else {
      $('nav.navbar').removeClass('navbar-fixed-top');
      $('body').css('padding-top','inherit');
    }
  }
});

// If the page is loaded on mobile, just set fixed navbar immediately
if (!$('#header').is(':visible')) {
  $('nav.navbar').addClass('navbar-fixed-top');
  $('body').css('padding-top','58px');
}

// Bind resize event to set or remove mobile XS nav fixed
$(window).resize(function(){
  if (!$('#header').is(':visible')) {
    $('nav.navbar').addClass('navbar-fixed-top');
    $('body').css('padding-top','58px');
  } else if ($(document).scrollTop() > 125) {
    $('nav.navbar').addClass('navbar-fixed-top');
    $('body').css('padding-top','58px');
  } else if ($(document).scrollTop() < 125) {
    $('nav.navbar').removeClass('navbar-fixed-top');
    $('body').css('padding-top','inherit');
  }
})

// Authentication
var user = (function($) {
  var googleConfig = {
    clientid: "408301341875-h17ftskvns7uug8csuvctkc43av4v0v3.apps.googleusercontent.com",
    cookiepolicy: "single_host_origin",
    scope: "https://www.googleapis.com/auth/plus.login https://www.googleapis.com/auth/plus.profile.emails.read",
    callback: googleSigninCallback
  }

  // Recipes API call to register/login user
  function userLogin (service, me) {
console.log('calling server login')
    // me[csrfTokenName] = csrfHash;
    // $.ajax({
    //   type: 'POST',
    //   url: baseUrl + 'user/login/'+service,
    //   data: me,
    //   success: function(returnData) {
    //     if(returnData === 1) {
    //       window.location.reload();
    //       } else {
    //         showMessage('There was a registration error, please try again later.');
    //       }
    //   },
    //   error: function(e) {
    //     showMessage('There was a registration error, please try again later.');
    //     }
    //   });
  }

  // Google login callback
  var called = false;
  function googleSigninCallback(r) {
    // Hack to prevent gapi from calling the callback twice
    if(called !== false) {
      return;
    };
    called = true;

    if (r.status.signed_in) {
      gapi.client.load('plus','v1', function() {
        var request = gapi.client.plus.people.get({'userId': 'me'});
        request.execute(function(googleProfile) {
          googleProfile.expiresIn = r.expires_in;
          userLogin('google', googleProfile);
        });
      });
    };
  }

  // Public
  return {
    googleLogin: function() {
      gapi.auth.signIn(googleConfig);
    },
    facebookLogin: function() {
      FB.login(function(r) {
        if(r.status === 'connected') {
          FB.api('/me', function(me) {
            if(me !== undefined) {
              me.expiresIn = r.authResponse.expiresIn;
              userLogin('facebook', me);
            }
          });
        }
      }, {scope: 'email'});
    }
  }
})(jQuery);

// Bind Facebook login
$('#fb-login-link').on('click',function(){
  user.facebookLogin();
});

// Bind Google login
$('#google-login-link').on('click',function(){
  user.googleLogin();
});
