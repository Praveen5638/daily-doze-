(function () {
  'use strict';

  var body = document.body;

  document.addEventListener('click', function (event) {
    var menu = event.target.closest('[data-admin-menu]');
    if (menu) {
      body.classList.toggle('sidebar-open');
      event.preventDefault();
      return;
    }

    if (body.classList.contains('sidebar-open') && !event.target.closest('.admin-sidebar')) {
      body.classList.remove('sidebar-open');
    }

    var notif = event.target.closest('[data-notif-toggle]');
    var panel = document.querySelector('[data-notif-panel]');
    if (notif && panel) {
      panel.classList.toggle('is-open');
      event.preventDefault();
      return;
    }
    if (panel && !event.target.closest('.notif-wrap')) {
      panel.classList.remove('is-open');
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    body.classList.remove('sidebar-open');
    var panel = document.querySelector('[data-notif-panel]');
    if (panel) panel.classList.remove('is-open');
  });

  document.addEventListener('submit', function (event) {
    var form = event.target;
    var message = form.getAttribute('data-confirm');
    if (message && !window.confirm(message)) {
      event.preventDefault();
      return;
    }
    var button = form.querySelector('button[type="submit"]');
    if (button && !form.hasAttribute('data-no-loading')) {
      button.classList.add('is-loading');
      button.setAttribute('disabled', 'disabled');
    }
  });

  document.querySelectorAll('[data-auto-submit]').forEach(function (input) {
    input.addEventListener('change', function () {
      if (input.form) input.form.requestSubmit ? input.form.requestSubmit() : input.form.submit();
    });
  });

  document.querySelectorAll('[data-print]').forEach(function (button) {
    button.addEventListener('click', function () {
      window.print();
    });
  });

  document.querySelectorAll('[data-preview-input]').forEach(function (input) {
    var target = document.querySelector(input.getAttribute('data-preview-input'));
    if (!target) return;
    input.addEventListener('change', function () {
      var file = input.files && input.files[0];
      if (!file) return;
      var url = URL.createObjectURL(file);
      if (target.tagName === 'IMG') {
        target.src = url;
        target.hidden = false;
      }
    });
  });
})();
