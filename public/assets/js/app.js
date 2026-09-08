/**
 * Listening Community SPC (LC-SPC) - Core Client Script
 */

document.addEventListener('DOMContentLoaded', () => {
  // Auto-dismiss or allow dismissing alert boxes
  document.querySelectorAll('.alert[data-dismissible="true"]').forEach((alert) => {
    alert.style.cursor = 'pointer';
    alert.addEventListener('click', () => {
      alert.style.transition = 'opacity 0.3s ease';
      alert.style.opacity = '0';
      setTimeout(() => alert.remove(), 300);
    });
  });
});

/**
 * Fetch helper with automatic CSRF token inclusion for mutating requests
 */
window.lcFetch = async function(url, options = {}) {
  const method = (options.method || 'GET').toUpperCase();
  options.headers = options.headers || {};

  if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(method)) {
    const metaCsrf = document.querySelector('meta[name="csrf-token"]');
    if (metaCsrf) {
      options.headers['X-CSRF-TOKEN'] = metaCsrf.getAttribute('content');
    }
  }

  return fetch(url, options);
};
