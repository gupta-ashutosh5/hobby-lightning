/**
 * @file
 * Sets up JavaScript functionality for the Fancy Login modals.
 */

/*global jQuery, Drupal, drupalSettings*/

(function ($, Drupal, drupalSettings) {

  "use strict";

  var link = '';
  Drupal.behaviors.upscLogin = {
    attach:function (context) {
      var loginContent  = '<div class = quiz-login-form-wrapper>' +
        '      <h4>' +
        '        Kindly login to proceed further' +
        '      </h4>' +
        '      <form name="emailForm" class="emailForm">' +
        '        <input type="text" placeholder="Enter email address" name="email"/>' +
        '        <input type="password" placeholder="Enter password" name="password"/>' +
        '        <input type="button" value="Login" class="form-login-submit"/>' +
        '      </form>' +
        '      <div id=quiz-login-errors class=quiz-error-wrapper>' +
        '        &nbsp;' +
        '      </div>' +
        '      <div class="login-signup-link">' +
        '        New user? Sign up to view answers.' +
        '      </div>' +
        '    </div>';

      var registerContent = '<div class=quiz-register-form-wrapper>' +
        '        <h3>' +
        '          Sign up' +
        '        </h3>' +
        '        <form id="register-form" name="registerForm" class="registerForm">' +
        '          <input type="text" placeholder="Enter email address" name="email"/>' +
        '          <input type="password" placeholder="Enter password" name="password"/>' +
        '          <input type="password" placeholder="Renter password" name="repassword"/>' +
        '          <input type="button" value="Sign up" class="form-signup-submit"/>' +
        '        </form>' +
        '        <div id=\'quiz-register-errors\' class=quiz-error-wrapper>' +
        '           &nbsp;' +
        '        </div>' +
        '        <div class="signup-login-link">' +
        '          Registered user? Please login to continue.' +
        '        </div>' +
        '      </div>';

      var loginDialog = Drupal.dialog(loginContent, {
        dialogClass: 'confirm-dialog login-dialog',
        resizable: false,
        closeOnEscape: false,
        width:600,
        beforeClose: false,
        close: function (event) {
          $(event.target).remove();
        }
      });

      var registerDialog = Drupal.dialog(registerContent, {
        dialogClass: 'confirm-dialog login-register',
        resizable: false,
        closeOnEscape: false,
        width:600,
        beforeClose: false,
        close: function (event) {
          $(event.target).remove();
        }
      });

      $(".private").on('click',function(e) {
        e.preventDefault();
        link = ($(this).attr('href')) ? $(this).attr('href') : '';
        loginDialog.showModal();
      });

      $(context).on('click', ".form-login-submit", function (e) {
        e.preventDefault();
        Drupal.doLogin($(this).closest('form'), link);
      });

      $(context).on('click', ".form-signup-submit", function (e) {
        e.preventDefault();
        Drupal.doRegister($(this).closest('form'), link);
      });

      $(context).on('click', '.login-signup-link', function (e) {
        e.preventDefault();
        registerDialog.showModal();
        loginDialog.close();
      });

      $(context).on('click', '.signup-login-link', function (e) {
        e.preventDefault();
        loginDialog.showModal();
        registerDialog.close();
      });

      if (drupalSettings.user.uid > 0 && (localStorage.getItem('redirect_url') !== '')) {
        window.location.href = localStorage.getItem('redirect_url');
        localStorage.setItem('redirect_url', '');
      }
    },
  };

  Drupal.doLogin = function (form, redirect_url) {
    var email = $.trim(form.find('[name=email]').val());
    var password = $.trim(form.find('[name=password]').val());

    fetch('/session/token')
      .then(function(response) {
        return response.text();
      })
      .then(function(token) {
        fetch('/user/login?_format=json', {
          method: 'POST',
          credentials: 'include',
          headers: {
            'Content-Type': 'application/hal+json',
            'X-CSRF-Token': token,
            'Accept': 'application/hal+json'
          },
          body: JSON.stringify({
            name: email,
            pass: password
          })
        }).then((response) => {
          response.json().then((data) => {
            if (response.ok) {
              if (redirect_url) {
                window.location.href = redirect_url;
              }
              setTimeout(function () {
                location.reload();
              }, 2000);
            }
            else {
              var errorsDiv = document.getElementById('quiz-login-errors');
              var errorMessage = data.message.trim().split(/\r?\n/);
              console.log(errorMessage);
              errorsDiv.innerText = (typeof errorMessage[1] !== 'undefined')
                ? errorMessage[errorMessage.length-1].split(':')[1].trim()
                : errorMessage;
            }
          });
        });
      }.bind(this));
  };

  Drupal.doRegister= function (form, redirect_url) {
    var email = $.trim(form.find('[name=email]').val());
    var password = $.trim(form.find('[name=password]').val());
    var repassword = $.trim(form.find('[name=repassword]').val());

    if (password !== repassword) {
      var errorsDiv = document.getElementById('quiz-register-errors');
      errorsDiv.innerText = 'Passwords do not match.';
    }
    else {
      fetch('/session/token')
        .then(function(response) {
          return response.text();
        })
        .then(function(token) {
          fetch('/user/register?_format=json', {
            method: 'POST',
            credentials: 'include',
            headers: {
              'Content-Type': 'application/hal+json',
              'X-CSRF-Token': token,
              'Accept': 'application/hal+json'
            },
            body: JSON.stringify({
              _links: {
                type: {
                  href: "http://upscwala-lightning.dd:8083/rest/type/user/user"
                }
              },
              name: {
                value: email
              },
              mail: {
                value: email
              },
              pass: {
                value: password
              }
            })
          }).then((response) => {
            response.json().then((data) => {
              if (response.ok) {
                console.log(data);
                if (data.uid[0].value > 0 && data.name[0].value === email) {
                  Drupal.doLogin(form, redirect_url);
                }
              }
              else {
                errorsDiv = document.getElementById('quiz-register-errors');
                var errorMessage = data.message.trim().split(/\r?\n/);
                errorsDiv.innerText = (typeof errorMessage[1] !== 'undefined')
                  ? errorMessage[errorMessage.length-1].split(':')[1].trim()
                  : errorMessage;
              }
            });
          });
        }.bind(this));
    }

  };
}(jQuery, Drupal, drupalSettings));
