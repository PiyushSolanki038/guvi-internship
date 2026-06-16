/**
 * login.js
 * Handles the login form:
 *  - Guards already-logged-in users (redirects to profile)
 *  - Real-time field validation
 *  - jQuery AJAX POST to php/login.php (no form submission — requirement)
 *  - Saves session_token, username, user_id to localStorage (requirement)
 *  - No PHP sessions used (requirement)
 */

$(document).ready(function () {

  /* Redirect if session already exists in localStorage */
  if (localStorage.getItem('session_token')) {
    window.location.href = 'profile.html';
    return;
  }

  const $form      = $('#login-form');
  const $btnSubmit = $('#btn-login');
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
      $btnSubmit.prop('disabled', true).html('<span class="spin"></span> Signing in…');
    } else {
      $btnSubmit.prop('disabled', false).text('Sign in');
    }
  }

  /* ── Real-time validation ─────────────────────────────────────────────── */
  $('#login-email').on('input', function () {
    const ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test($(this).val().trim());
    $(this).toggleClass('is-valid', ok).toggleClass('is-invalid', !ok);
  });

  $('#login-password').on('input', function () {
    const ok = $(this).val().length >= 6;
    $(this).toggleClass('is-valid', ok).toggleClass('is-invalid', !ok);
  });

  /* ── Submit handler ───────────────────────────────────────────────────── */
  $btnSubmit.on('click', function (e) {
    e.preventDefault();
    hideAlert();

    const email    = $('#login-email').val().trim();
    const password = $('#login-password').val();

    if (!email || !password) {
      showAlert('Please enter your email and password.');
      return;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      showAlert('Enter a valid email address.');
      return;
    }

    setLoading(true);

    /* jQuery AJAX POST — no form submission (requirement) */
    $.ajax({
      url        : 'php/login.php',
      type       : 'POST',
      contentType: 'application/json',
      data       : JSON.stringify({ email, password }),
      dataType   : 'json',

      success: function (response) {
        setLoading(false);
        if (response.success) {
          /* Save session to localStorage only — no PHP session (requirement) */
          localStorage.setItem('session_token', response.session_token);
          localStorage.setItem('username',      response.username);
          localStorage.setItem('user_id',       response.user_id);

          showAlert('Login successful! Redirecting…', 'success');
          setTimeout(function () {
            window.location.href = 'profile.html';
          }, 900);
        } else {
          showAlert(response.message || 'Invalid credentials.');
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
