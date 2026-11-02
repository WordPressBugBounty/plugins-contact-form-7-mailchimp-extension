/**
 * @package   contact-form-7-mailchimp-extension
 * @author    hello@chimpmatic.com
 * @copyright 2014-2026 ChimpMatic
 * @license   GPL-3.0+
 */
(function () {
  'use strict';

  const cfg = window.cmaticLiteNotices;
  if (!cfg) return;

	document.addEventListener( 'click', function ( event ) {
		const go = event.target.closest( '[data-cmatic-lite-form-go]' );
		if ( ! go ) {
			return;
		}
		const notice = go.closest( '[data-cmatic-lite-notice]' );
		const picker =
			notice && notice.querySelector( '[data-cmatic-lite-form-picker]' );
		if ( ! picker || ! picker.value ) {
			return;
		}
		const url = new URL( picker.value, window.location.href );
		if (
			url.origin === window.location.origin &&
			url.pathname.indexOf( '/wp-admin/' ) !== -1
		) {
			window.location.assign( url.href );
		}
	} );

  document.addEventListener('click', function (event) {
    const trigger = event.target.closest('[data-cmatic-lite-act]');
    if (!trigger) return;
    const notice = trigger.closest('[data-cmatic-lite-notice]');
    if (!notice) return;
    if (trigger.tagName !== 'A') event.preventDefault();
    const id = notice.getAttribute('data-cmatic-lite-notice');
    notice.remove();
    if (cfg.preview) return;
    fetch(cfg.url, {
      method: 'POST',
      keepalive: true,
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
      body: JSON.stringify({ id: id, act: trigger.getAttribute('data-cmatic-lite-act') })
    }).catch(function () {});
  });
})();
