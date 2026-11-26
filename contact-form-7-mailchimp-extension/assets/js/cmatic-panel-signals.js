( function () {
	'use strict';

	const config = window.cmaticPanelSignals;
	if ( ! config || ! config.url ) {
		return;
	}

	function send( event ) {
		try {
			fetch( config.url, {
				method: 'POST',
				credentials: 'same-origin',
				keepalive: true,
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': config.nonce,
				},
				body: JSON.stringify( { event } ),
			} ).catch( () => {} );
		} catch {}
	}

	function slug( value ) {
		return String( value || '' )
			.toLowerCase()
			.replace( /[^a-z0-9]+/g, '_' )
			.replace( /^_+|_+$/g, '' )
			.slice( 0, 40 );
	}

	function linkEvent( anchor ) {
		let url;
		try {
			url = new URL( anchor.href, window.location.href );
		} catch {
			return '';
		}
		if ( ! /(^|\.)chimpmatic\.com$/.test( url.hostname ) ) {
			return '';
		}
		const content =
			slug( url.searchParams.get( 'utm_content' ) ) ||
			slug( url.pathname.split( '/' ).filter( Boolean ).pop() ) ||
			'home';
		const kind =
			'docs' === url.searchParams.get( 'utm_campaign' ) ||
			anchor.classList.contains( 'helping-field' ) ||
			anchor.classList.contains( 'cmatic-help-icon' ) ||
			/_help$/.test( content )
				? 'help'
				: 'link';
		return kind + '_' + content;
	}

	document.addEventListener(
		'click',
		( event ) => {
			const anchor = event.target.closest
				? event.target.closest( 'a[href]' )
				: null;
			if ( ! anchor ) {
				return;
			}
			const name = linkEvent( anchor );
			if ( name ) {
				send( name );
			}
		},
		true
	);

	document.addEventListener( 'click', ( event ) => {
		const choice = event.target.closest
			? event.target.closest( '[data-provider-choice]' )
			: null;
		const name = choice ? slug( choice.dataset.providerChoice ) : '';
		if ( name ) {
			send( 'provider_picked_' + name );
		}
	} );

	let panelSeen = false;
	function panelVisible() {
		const panel = document.getElementById( config.panel );
		return !! panel && ! panel.hidden && null !== panel.offsetParent;
	}
	let promoSeen = false;
	function checkPanel() {
		if ( ! panelSeen && panelVisible() ) {
			panelSeen = true;
			send( 'panel_opened' );
		}
		if ( ! promoSeen && config.promo ) {
			const promo = document.querySelector( config.promo );
			if ( promo && null !== promo.offsetParent ) {
				promoSeen = true;
				send( 'promo_seen' );
			}
		}
	}

	let modalOpen = false;
	function checkModal() {
		const modal = document.getElementById( 'cmatic-test-modal' );
		const open =
			!! modal && modal.classList.contains( 'cmatic-modal--active' );
		if ( open && ! modalOpen ) {
			send( 'test_opened' );
		}
		modalOpen = open;
	}

	function watch() {
		checkPanel();
		const panel = document.getElementById( config.panel );
		if ( panel ) {
			new MutationObserver( checkPanel ).observe( panel, {
				attributes: true,
				attributeFilter: [ 'hidden', 'style', 'class' ],
			} );
		}
		const tab = document.getElementById( config.panel + '-tab' );
		if ( tab ) {
			tab.addEventListener( 'click', () =>
				window.setTimeout( checkPanel, 50 )
			);
		}
		const modal = document.getElementById( 'cmatic-test-modal' );
		if ( modal ) {
			new MutationObserver( checkModal ).observe( modal, {
				attributes: true,
				attributeFilter: [ 'class' ],
			} );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', watch );
	} else {
		watch();
	}
} )();
