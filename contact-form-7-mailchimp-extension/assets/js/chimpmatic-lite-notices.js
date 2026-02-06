/**
 * Admin notices handler.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

'use strict';

(function() {
  document.addEventListener('DOMContentLoaded', function() {

    if (typeof chimpmaticNotices === 'undefined') {
      return;
    }

    document.addEventListener('click', function(event) {
      if (event.target.classList.contains('notice-dismiss')) {
        const noticeElement = event.target.closest('#mce-notice');

        if (noticeElement) {
          event.preventDefault();
          dismissNotice(noticeElement);
        }
      }
    });

    async function dismissNotice(noticeElement) {
      try {
        const response = await fetch(`${chimpmaticNotices.restUrl}/notices/dismiss`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': chimpmaticNotices.restNonce
          }
        });

        const data = await response.json();

        if (response.ok && data.success) {
          noticeElement.style.transition = 'opacity 0.3s ease-out';
          noticeElement.style.opacity = '0';

          setTimeout(() => {
            noticeElement.style.display = 'none';
          }, 300);
        }

      } catch (error) {
        console.error('[ChimpMatic Lite] Dismiss notice error:', error);
      }
    }
  });
})();
