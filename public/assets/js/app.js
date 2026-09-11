/**
 * LC-SPC Design System Generic Interactions
 * Vanilla, dependency-free UI behaviors:
 * 1. Dismissible alerts
 * 2. Mobile navigation drawer toggle
 * 3. Accessible modal controller
 * 4. Accessible password visibility toggle
 * 5. Login form double-submit prevention
 */

(function () {
  'use strict';

  // 1. Dismissible Alerts
  function initDismissibleAlerts() {
    document.addEventListener('click', function (event) {
      var closeBtn = event.target.closest('[data-dismiss="alert"], .alert-close');
      if (closeBtn) {
        var alertBox = closeBtn.closest('.alert');
        if (alertBox) {
          alertBox.style.opacity = '0';
          alertBox.style.transition = 'opacity 150ms ease-out, max-height 150ms ease-out';
          setTimeout(function () {
            if (alertBox.parentNode) {
              alertBox.parentNode.removeChild(alertBox);
            }
          }, 150);
        }
      }
    });
  }

  // 2. Mobile Navigation Toggle for Admin Layout
  function initMobileNavigation() {
    var toggleBtn = document.querySelector('.admin-sidebar-toggle');
    var sidebar = document.querySelector('.admin-sidebar');
    if (!toggleBtn || !sidebar) return;

    var backdrop = document.createElement('div');
    backdrop.className = 'admin-sidebar-backdrop sr-only';
    backdrop.style.position = 'fixed';
    backdrop.style.inset = '0';
    backdrop.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
    backdrop.style.zIndex = '99';
    backdrop.style.display = 'none';
    document.body.appendChild(backdrop);

    function openSidebar() {
      sidebar.classList.add('is-open');
      backdrop.style.display = 'block';
      backdrop.classList.remove('sr-only');
      toggleBtn.setAttribute('aria-expanded', 'true');
    }

    function closeSidebar() {
      sidebar.classList.remove('is-open');
      backdrop.style.display = 'none';
      backdrop.classList.add('sr-only');
      toggleBtn.setAttribute('aria-expanded', 'false');
    }

    toggleBtn.addEventListener('click', function () {
      if (sidebar.classList.contains('is-open')) {
        closeSidebar();
      } else {
        openSidebar();
      }
    });

    var closeBtn = sidebar.querySelector('.admin-sidebar-close');
    if (closeBtn) {
      closeBtn.addEventListener('click', closeSidebar);
    }

    backdrop.addEventListener('click', closeSidebar);

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && sidebar.classList.contains('is-open')) {
        closeSidebar();
        toggleBtn.focus();
      }
    });
  }

  // 3. Accessible Modal Dialogs
  function initModals() {
    var lastFocusedElement = null;

    document.addEventListener('click', function (event) {
      var openTrigger = event.target.closest('[data-modal-target]');
      if (openTrigger) {
        var targetId = openTrigger.getAttribute('data-modal-target');
        var modal = document.querySelector(targetId);
        if (modal) {
          lastFocusedElement = openTrigger;
          modal.classList.add('is-active');
          modal.setAttribute('aria-hidden', 'false');
          var focusable = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
          if (focusable) focusable.focus();
        }
      }

      var closeTrigger = event.target.closest('[data-modal-close]');
      if (closeTrigger) {
        var activeModal = closeTrigger.closest('.modal');
        if (activeModal) {
          activeModal.classList.remove('is-active');
          activeModal.setAttribute('aria-hidden', 'true');
          if (lastFocusedElement) {
            lastFocusedElement.focus();
            lastFocusedElement = null;
          }
        }
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        var activeModal = document.querySelector('.modal.is-active');
        if (activeModal) {
          activeModal.classList.remove('is-active');
          activeModal.setAttribute('aria-hidden', 'true');
          if (lastFocusedElement) {
            lastFocusedElement.focus();
            lastFocusedElement = null;
          }
        }
      }
    });
  }

  // 4. Accessible Password Visibility Toggle (Safely scoped to #togglePasswordBtn)
  function initPasswordToggle() {
    var toggleBtn = document.getElementById('togglePasswordBtn');
    if (!toggleBtn) return;

    var passwordInput = document.getElementById('login-password');
    if (!passwordInput) return;

    var eyeIcon = toggleBtn.querySelector('.icon-eye');
    var eyeOffIcon = toggleBtn.querySelector('.icon-eye-off');

    toggleBtn.addEventListener('click', function (e) {
      e.preventDefault();
      var isPassword = passwordInput.getAttribute('type') === 'password';

      if (isPassword) {
        passwordInput.setAttribute('type', 'text');
        toggleBtn.setAttribute('aria-label', 'Hide password');
        toggleBtn.setAttribute('aria-pressed', 'true');
        if (eyeIcon) eyeIcon.style.display = 'none';
        if (eyeOffIcon) eyeOffIcon.style.display = 'block';
      } else {
        passwordInput.setAttribute('type', 'password');
        toggleBtn.setAttribute('aria-label', 'Show password');
        toggleBtn.setAttribute('aria-pressed', 'false');
        if (eyeIcon) eyeIcon.style.display = 'block';
        if (eyeOffIcon) eyeOffIcon.style.display = 'none';
      }

      passwordInput.focus();
    });
  }

  // 5. Login Form Double-Submit Prevention (Safely scoped to #adminLoginForm)
  function initLoginFormDoubleSubmit() {
    var loginForm = document.getElementById('adminLoginForm');
    if (!loginForm) return;

    loginForm.addEventListener('submit', function () {
      if (typeof loginForm.checkValidity === 'function' && !loginForm.checkValidity()) {
        return;
      }

      var submitBtn = document.getElementById('loginSubmitBtn');
      if (submitBtn && !submitBtn.disabled) {
        var btnText = submitBtn.querySelector('.btn-text');
        var btnSpinner = submitBtn.querySelector('.btn-spinner');

        setTimeout(function () {
          submitBtn.disabled = true;
          if (btnText) btnText.style.display = 'none';
          if (btnSpinner) btnSpinner.style.display = 'inline-flex';
        }, 0);
      }
    });
  }

  // Initialize on DOMContentLoaded
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initDismissibleAlerts();
      initMobileNavigation();
      initModals();
      initPasswordToggle();
      initLoginFormDoubleSubmit();
    });
  } else {
    initDismissibleAlerts();
    initMobileNavigation();
    initModals();
    initPasswordToggle();
    initLoginFormDoubleSubmit();
  }
})();
