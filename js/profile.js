/**
 * profile.js
 * Handles the profile page:
 *  - Guards page: redirects to login if no localStorage session_token
 *  - Fetches profile via jQuery AJAX GET  → php/profile.php
 *  - Saves profile via jQuery AJAX POST → php/profile.php
 *  - Handles photo file upload: FileReader → base64 → stored in MongoDB
 *  - Updates sidebar avatar, topbar avatar, and sidebar stats live
 *  - Handles logout (clears localStorage)
 *
 * Session token sent in Authorization header on every request.
 * Profile data (including avatar base64) stored in MongoDB.
 * No PHP sessions used anywhere (requirement).
 */

$(document).ready(function () {

  /* ── 1. Session guard ─────────────────────────────────────────────────── */
  const sessionToken = localStorage.getItem('session_token');
  const username     = localStorage.getItem('username') || 'User';

  if (!sessionToken) {
    window.location.href = 'login.html';
    return;
  }

  /* ── 2. Seed static UI from localStorage ─────────────────────────────── */
  $('#nav-username').text(username);
  $('#profile-username').text(username);
  renderAvatarInitials(username);

  /* ── currentAvatarData: holds base64 string of the chosen photo ─────── */
  /* Starts null; set by FileReader when user picks a file.               */
  /* On save, this value is sent to PHP and stored in MongoDB.            */
  let currentAvatarData = null;

  /* ── Utility: build two-letter initials ──────────────────────────────── */
  function getInitials(name) {
    return name.split(' ').map(w => w[0] || '').join('').toUpperCase().slice(0, 2) || 'U';
  }

  /* ── Utility: show initials in all three avatar elements ─────────────── */
  function renderAvatarInitials(name) {
    const initials = getInitials(name);
    $('#avatar-initials').text(initials);       // sidebar large avatar
    $('#avatar-preview-initials').text(initials); // upload widget preview
    $('#topbar-avatar').text(initials);           // topbar small avatar
  }

  /* ── Utility: show an image src in all three avatar elements ─────────── */
  function renderAvatarImage(src) {
    const makeImg = () => '<img src="' + escapeHtml(src) + '" alt="Avatar" />';
    $('#avatar-circle').html(makeImg());
    $('#avatar-preview').html(makeImg());
    $('#topbar-avatar').html(makeImg());
  }

  /* ── Utility: reset all avatars back to initials ─────────────────────── */
  function clearAvatarImage() {
    const initials = getInitials(username);
    $('#avatar-circle').html('<span id="avatar-initials">' + initials + '</span>');
    $('#avatar-preview').html('<span id="avatar-preview-initials">' + initials + '</span>');
    $('#topbar-avatar').text(initials);
  }

  /* ── Utility: XSS-safe HTML escape ───────────────────────────────────── */
  function escapeHtml(str) {
    return $('<div>').text(str).html();
  }

  /* ── Utility: show alert banner ───────────────────────────────────────── */
  function showAlert(message, type) {
    type = type || 'danger';
    $('#alert-box')
      .removeClass('alert-danger alert-success')
      .addClass('alert-' + type)
      .text(message)
      .fadeIn(200);
    if (type === 'success') {
      setTimeout(function () { $('#alert-box').fadeOut(300); }, 3000);
    }
  }

  /* ── Utility: update sidebar quick-stat cells ────────────────────────── */
  function updateSidebarStats(profile) {
    $('#stat-age').text(profile.age || '—');
    const c = profile.contact || '';
    $('#stat-contact').text(c ? c.slice(0, 8) + (c.length > 8 ? '…' : '') : '—');
  }

  /* ── Utility: save button loading state ──────────────────────────────── */
  function setSaveLoading(loading) {
    const $btn = $('#btn-save-profile');
    if (loading) {
      $btn.prop('disabled', true).html('<span class="spin"></span> Saving…');
    } else {
      $btn.prop('disabled', false).text('Save changes');
    }
  }

  /* ══════════════════════════════════════════════════════════════════════
     FILE UPLOAD — photo avatar
     Uses FileReader API to convert chosen image to base64 string.
     The base64 is stored in currentAvatarData and sent to MongoDB on save.
  ══════════════════════════════════════════════════════════════════════ */

  $('#field-avatar').on('change', function () {
    const file = this.files[0];

    if (!file) return;

    /* Validate: images only */
    if (!file.type.startsWith('image/')) {
      showAlert('Please choose an image file (JPG, PNG, GIF).');
      this.value = '';
      return;
    }

    /* Validate: max 2 MB */
    if (file.size > 2 * 1024 * 1024) {
      showAlert('Image must be smaller than 2 MB.');
      this.value = '';
      return;
    }

    /* Use FileReader to get base64 data URL */
    const reader = new FileReader();

    reader.onload = function (e) {
      currentAvatarData = e.target.result; // full base64 data URL

      /* Instantly preview in all avatar spots */
      renderAvatarImage(currentAvatarData);

      /* Show the Remove button */
      $('#btn-remove-avatar').removeClass('hidden-section');
    };

    reader.readAsDataURL(file);
  });

  /* Remove photo — clears selection and reverts to initials */
  $('#btn-remove-avatar').on('click', function () {
    currentAvatarData = null;
    $('#field-avatar').val('');        // clear file input
    clearAvatarImage();                // back to initials
    $(this).addClass('hidden-section');
  });

  /* ══════════════════════════════════════════════════════════════════════
     LOAD PROFILE — GET request → php/profile.php → MongoDB
  ══════════════════════════════════════════════════════════════════════ */

  function loadProfile() {
    $('#profile-loading').show();
    $('#profile-form-section').hide();

    $.ajax({
      url     : 'php/profile.php',
      type    : 'GET',
      headers : { 'Authorization': 'Bearer ' + sessionToken },
      dataType: 'json',

      success: function (response) {
        $('#profile-loading').hide();
        $('#profile-form-section').fadeIn(280);

        if (response.success) {
          const p = response.profile;

          /* Populate text fields */
          $('#field-age').val(p.age     || '');
          $('#field-dob').val(p.dob     || '');
          $('#field-contact').val(p.contact || '');
          $('#field-address').val(p.address || '');
          $('#field-bio').val(p.bio     || '');

          /* Restore saved avatar (base64 data URL from MongoDB) */
          if (p.avatar) {
            currentAvatarData = p.avatar;
            renderAvatarImage(p.avatar);
            $('#btn-remove-avatar').removeClass('hidden-section');
          }

          updateSidebarStats(p);
        }
      },

      error: function (xhr) {
        $('#profile-loading').hide();

        if (xhr.status === 401) {
          clearSession();
          window.location.href = 'login.html';
          return;
        }

        $('#profile-form-section').fadeIn(280);
        showAlert('Could not load profile. Please refresh.');
      },
    });
  }

  loadProfile();

  /* ══════════════════════════════════════════════════════════════════════
     SAVE PROFILE — POST request → php/profile.php → MongoDB
  ══════════════════════════════════════════════════════════════════════ */

  $('#btn-save-profile').on('click', function (e) {
    e.preventDefault();
    $('#alert-box').fadeOut(100);

    const profileData = {
      age    : $('#field-age').val().trim(),
      dob    : $('#field-dob').val(),
      contact: $('#field-contact').val().trim(),
      address: $('#field-address').val().trim(),
      bio    : $('#field-bio').val().trim(),
      avatar : currentAvatarData || '',   // base64 data URL or empty string
    };

    if (profileData.age && (isNaN(profileData.age) || profileData.age < 1 || profileData.age > 120)) {
      showAlert('Enter a valid age between 1 and 120.');
      return;
    }

    setSaveLoading(true);

    /* jQuery AJAX POST — no form submission (requirement) */
    $.ajax({
      url        : 'php/profile.php',
      type       : 'POST',
      contentType: 'application/json',
      headers    : { 'Authorization': 'Bearer ' + sessionToken },
      data       : JSON.stringify(profileData),
      dataType   : 'json',

      success: function (response) {
        setSaveLoading(false);

        if (response.success) {
          showAlert('Profile saved successfully.', 'success');
          updateSidebarStats(profileData);

          if (profileData.avatar) {
            renderAvatarImage(profileData.avatar);
          } else {
            clearAvatarImage();
          }
        } else {
          showAlert(response.message || 'Save failed. Try again.');
        }
      },

      error: function (xhr) {
        setSaveLoading(false);

        if (xhr.status === 401) {
          clearSession();
          window.location.href = 'login.html';
          return;
        }

        let msg = 'Server error. Please try again.';
        try {
          const r = JSON.parse(xhr.responseText);
          if (r.message) msg = r.message;
        } catch (_) {}
        showAlert(msg);
      },
    });
  });

  /* ── Logout ───────────────────────────────────────────────────────────── */
  $('#btn-logout').on('click', function () {
    clearSession();
    window.location.href = 'login.html';
  });

  function clearSession() {
    localStorage.removeItem('session_token');
    localStorage.removeItem('username');
    localStorage.removeItem('user_id');
  }

});
