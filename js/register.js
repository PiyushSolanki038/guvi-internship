/**
 * register.js
 * Handles the registration form:
 *  - Real-time field validation
 *  - jQuery AJAX POST to php/register.php (no form submission — requirement)
 *  - Redirects to login.html on success
 */

$(document).ready(function () {

  const $form     = $('#register-form');
  const $btnSubmit = $('#btn-register');
  const $alertBox  = $('#alert-box');

  /* ── Alert helpers ────────────────────────────────────────────────────── */
  function showAlert(message, type) {
    type = type || 'danger';
    $alertBox
      .removeClass('alert-danger alert-success')
      .addClass('alert-' + type)
      .text(message)
      .fadeIn(200);
  }

  function hideAlert() { $alertBox.fadeOut(150); }

  /* ── Button loading state ─────────────────────────────────────────────── */
  function setLoading(loading) {
    if (loading) {
      $btnSubmit.prop('disabled', true).html('<span class="spin"></span> Creating account…');
    } else {
      $btnSubmit.prop('disabled', false).text('Create account');
    }
  }

  /* ── Real-time validation ─────────────────────────────────────────────── */

  $('#username').on('input', function () {
    if ($(this).val().trim().length >= 3) {
      $(this).removeClass('is-invalid').addClass('is-valid');
    } else {
      $(this).removeClass('is-valid').addClass('is-invalid');
    }
  });

  $('#email').on('input', function () {
    const ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test($(this).val().trim());
    $(this).toggleClass('is-valid', ok).toggleClass('is-invalid', !ok);
  });

  $('#password').on('input', function () {
    const ok = $(this).val().length >= 6;
    $(this).toggleClass('is-valid', ok).toggleClass('is-invalid', !ok);
    $('#confirm-password').trigger('input');
  });

  $('#confirm-password').on('input', function () {
    const ok = $(this).val() === $('#password').val() && $(this).val().length > 0;
    $(this).toggleClass('is-valid', ok).toggleClass('is-invalid', !ok);
  });

  /* ── Submit handler ───────────────────────────────────────────────────── */
  $btnSubmit.on('click', function (e) {
    e.preventDefault();
    hideAlert();

    const username        = $('#username').val().trim();
    const email           = $('#email').val().trim();
    const password        = $('#password').val();
    const confirmPassword = $('#confirm-password').val();

    if (!username || !email || !password || !confirmPassword) {
      showAlert('Please fill in all fields.');
      return;
    }
    if (username.length < 3) {
      showAlert('Name must be at least 3 characters.');
      return;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      showAlert('Enter a valid email address.');
      return;
    }
    if (password.length < 6) {
      showAlert('Password must be at least 6 characters.');
      return;
    }
    if (password !== confirmPassword) {
      showAlert('Passwords do not match.');
      return;
    }

    setLoading(true);

    /* jQuery AJAX POST — strictly no form submission (requirement) */
    $.ajax({
      url        : 'php/register.php',
      type       : 'POST',
      contentType: 'application/json',
      data       : JSON.stringify({ username, email, password }),
      dataType   : 'json',

      success: function (response) {
        setLoading(false);
        if (response.success) {
          showAlert('Account created! Redirecting to login…', 'success');
          setTimeout(function () {
            window.location.href = 'login.html';
          }, 1400);
        } else {
          showAlert(response.message || 'Registration failed.');
        }
      },

      error: function (xhr) {
        setLoading(false);
        let msg = 'Server error. Please try again.';
        try {
          const r = JSON.parse(xhr.responseText);
          if (r.message) msg = r.message;
        } catch (_) {}
        showAlert(msg);
      },
    });
  });

  /* Enter key support */
  $form.on('keydown', function (e) {
    if (e.key === 'Enter') $btnSubmit.trigger('click');
  });

});
