/**
 * Zaplane Inbox — website chat widget.
 *
 * Plain script, no framework: it loads on every page of the site, so it has to
 * be small. Messages are rendered with textContent only — nothing a visitor or
 * the team writes is ever parsed as HTML.
 */
( function () {
	'use strict';

	var cfg = window.ZaplaneInbox;
	if ( ! cfg || ! cfg.rest || document.getElementById( 'zpi-root' ) ) {
		return;
	}

	var STORE_KEY = 'zaplane_inbox_visitor';
	var OPEN_POLL = 3000;
	// While an answer is on its way, look sooner.
	var WAITING_POLL = 1200;
	var IDLE_POLL = 20000;
	var t = cfg.i18n || {};

	var state = {
		token: read( STORE_KEY ),
		open: false,
		lastId: 0,
		unread: 0,
		started: false,
		waiting: false,
		busy: false,
		timer: null,
		known: null,
		revision: null,
		reloading: false,
	};

	function read( key ) {
		try {
			return window.localStorage.getItem( key ) || '';
		} catch ( e ) {
			return '';
		}
	}

	function write( key, value ) {
		try {
			if ( value ) {
				window.localStorage.setItem( key, value );
			} else {
				window.localStorage.removeItem( key );
			}
		} catch ( e ) {}
	}

	function el( tag, cls, text ) {
		var node = document.createElement( tag );
		if ( cls ) {
			node.className = cls;
		}
		if ( text ) {
			node.textContent = text;
		}
		return node;
	}

	/* ---------------------------------------------------------------- *
	 * Network
	 * ---------------------------------------------------------------- */

	function request( method, path, body, withNonce ) {
		var headers = { Accept: 'application/json' };
		if ( body ) {
			headers[ 'Content-Type' ] = 'application/json';
		}
		if ( state.token ) {
			headers[ 'X-Zaplane-Visitor' ] = state.token;
		}
		if ( withNonce !== false && cfg.nonce ) {
			headers[ 'X-WP-Nonce' ] = cfg.nonce;
		}

		return window
			.fetch( cfg.rest + path, {
				method: method,
				headers: headers,
				credentials: 'same-origin',
				body: body ? JSON.stringify( body ) : undefined,
			} )
			.then( function ( res ) {
				return res.json().then(
					function ( data ) {
						return { ok: res.ok, status: res.status, data: data };
					},
					function () {
						return { ok: res.ok, status: res.status, data: {} };
					}
				);
			} )
			.then( function ( out ) {
				// A cached page can carry a stale nonce; the chat still works
				// anonymously without it.
				if ( ! out.ok && withNonce !== false && cfg.nonce && out.data && out.data.code === 'rest_cookie_invalid_nonce' ) {
					cfg.nonce = '';
					return request( method, path, body, false );
				}
				return out;
			} );
	}

	function ensureSession() {
		if ( state.started ) {
			return Promise.resolve( true );
		}
		return request( 'POST', 'session' ).then( function ( res ) {
			if ( ! res.ok || ! res.data.token ) {
				return false;
			}
			state.token = res.data.token;
			state.known = res.data.visitor || null;
			state.started = true;
			write( STORE_KEY, state.token );
			return true;
		} );
	}

	function poll() {
		if ( ! state.token ) {
			return Promise.resolve();
		}
		return request( 'GET', 'messages' + ( state.lastId ? '?after_id=' + state.lastId : '' ) ).then( function ( res ) {
			if ( res.status === 401 ) {
				// The token no longer verifies (signed out, salts rotated). Start over.
				state.token = '';
				state.started = false;
				write( STORE_KEY, '' );
				return;
			}
			if ( ! res.ok ) {
				return;
			}
			// A message was edited or deleted since we last looked: redraw the
			// thread from scratch rather than patching individual bubbles.
			var revision = typeof res.data.revision === 'number' ? res.data.revision : null;
			if ( revision !== null && state.revision !== null && revision !== state.revision && state.lastId ) {
				state.revision = revision;
				clearMessages();
				state.reloading = true;
				return poll().then( function () {
					state.reloading = false;
				} );
			}
			if ( revision !== null ) {
				state.revision = revision;
			}
			( res.data.messages || [] ).forEach( addMessage );
			setWaiting( !! res.data.waiting );
		} );
	}

	function schedule() {
		window.clearTimeout( state.timer );
		if ( ! state.token || document.visibilityState === 'hidden' ) {
			return;
		}
		state.timer = window.setTimeout( function () {
			poll().then( schedule, schedule );
		}, state.open ? ( state.waiting ? WAITING_POLL : OPEN_POLL ) : IDLE_POLL );
	}

	/* ---------------------------------------------------------------- *
	 * UI
	 * ---------------------------------------------------------------- */

	var root = el( 'div', 'zpi-root zpi-' + ( cfg.position === 'left' ? 'left' : 'right' ) );
	root.id = 'zpi-root';
	root.style.setProperty( '--zpi-color', cfg.color || '#006BFF' );

	var launcher = el( 'button', 'zpi-launcher' );
	launcher.type = 'button';
	launcher.setAttribute( 'aria-label', t.open || 'Open chat' );
	launcher.setAttribute( 'aria-expanded', 'false' );
	launcher.innerHTML =
		'<svg class="zpi-icon-chat" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H9l-5 4v-4H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z" fill="currentColor"/></svg>' +
		'<svg class="zpi-icon-close" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>';
	var badge = el( 'span', 'zpi-badge' );
	badge.hidden = true;
	launcher.appendChild( badge );

	var panel = el( 'div', 'zpi-panel' );
	panel.setAttribute( 'role', 'dialog' );
	panel.setAttribute( 'aria-label', cfg.title || 'Chat' );
	panel.hidden = true;

	var header = el( 'div', 'zpi-header' );
	var titleWrap = el( 'div', 'zpi-titles' );
	titleWrap.appendChild( el( 'div', 'zpi-title', cfg.title || '' ) );
	if ( cfg.aiLabel ) {
		titleWrap.appendChild( el( 'div', 'zpi-subtitle', cfg.aiLabel ) );
	}
	var closeBtn = el( 'button', 'zpi-close' );
	closeBtn.type = 'button';
	closeBtn.setAttribute( 'aria-label', t.close || 'Close chat' );
	closeBtn.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>';
	header.appendChild( titleWrap );
	header.appendChild( closeBtn );

	var list = el( 'div', 'zpi-messages' );
	list.setAttribute( 'aria-live', 'polite' );

	var greeting = el( 'div', 'zpi-msg zpi-them' );
	greeting.appendChild( el( 'div', 'zpi-bubble', cfg.greeting || '' ) );

	// Quick answers: tap instead of typing. Grouped menus show categories
	// first; a category opens its questions in place, without a message.
	var menu = cfg.menu || { grouped: false, categories: [], questions: cfg.questions || [] };
	var hasMenu = ( menu.grouped && menu.categories.length ) || ( menu.questions && menu.questions.length );

	function askText( question ) {
		text.value = question;
		form.requestSubmit ? form.requestSubmit() : form.dispatchEvent( new Event( 'submit', { cancelable: true } ) );
	}

	/**
	 * Fill `box` with the menu's first level, or one category's questions.
	 * `after` are extra items (e.g. "Talk to a person") at the end.
	 */
	function renderMenu( box, itemClass, onPick, after, category ) {
		Array.prototype.slice.call( box.querySelectorAll( '.' + itemClass.split( ' ' )[0] ) ).forEach( function ( n ) {
			n.parentNode.removeChild( n );
		} );
		var add = function ( label, cls, handler ) {
			var b = el( 'button', itemClass + ( cls ? ' ' + cls : '' ), label );
			b.type = 'button';
			b.addEventListener( 'click', handler );
			box.appendChild( b );
		};
		if ( category ) {
			add( '← ' + ( t.allTopics || 'All topics' ), 'is-back', function () {
				renderMenu( box, itemClass, onPick, after, null );
			} );
			category.questions.forEach( function ( q ) {
				add( q, '', function () { onPick( q ); } );
			} );
		} else if ( menu.grouped && menu.categories.length ) {
			menu.categories.forEach( function ( c ) {
				add( c.title, 'is-category', function () {
					renderMenu( box, itemClass, onPick, after, c );
				} );
			} );
		} else {
			( menu.questions || [] ).forEach( function ( q ) {
				add( q, '', function () { onPick( q ); } );
			} );
		}
		( after || [] ).forEach( function ( label ) {
			add( label, 'is-person', function () { onPick( label ); } );
		} );
	}

	var starters = null;
	if ( hasMenu ) {
		starters = el( 'div', 'zpi-starters' );
		renderMenu( starters, 'zpi-starter', askText, [], null );
		greeting.appendChild( starters );
	}

	if ( cfg.greeting || starters ) {
		list.appendChild( greeting );
	}

	var typing = el( 'div', 'zpi-msg zpi-them zpi-typing' );
	typing.appendChild( el( 'div', 'zpi-bubble', t.typing || '…' ) );
	typing.hidden = true;

	var form = el( 'form', 'zpi-form' );
	var details = el( 'div', 'zpi-details' );
	var nameInput = el( 'input', 'zpi-input' );
	nameInput.type = 'text';
	nameInput.placeholder = t.name || 'Your name';
	nameInput.setAttribute( 'aria-label', t.name || 'Your name' );
	nameInput.autocomplete = 'name';
	var emailInput = el( 'input', 'zpi-input' );
	emailInput.type = 'email';
	emailInput.placeholder = t.email || 'Your email';
	emailInput.setAttribute( 'aria-label', t.email || 'Your email' );
	emailInput.autocomplete = 'email';
	details.appendChild( nameInput );
	details.appendChild( emailInput );
	details.hidden = true;

	var row = el( 'div', 'zpi-row' );
	var text = el( 'textarea', 'zpi-text' );
	text.rows = 1;
	text.placeholder = t.placeholder || 'Type your message…';
	text.setAttribute( 'aria-label', t.placeholder || 'Message' );
	text.maxLength = 4000;
	var send = el( 'button', 'zpi-send' );
	send.type = 'submit';
	send.setAttribute( 'aria-label', t.send || 'Send' );
	send.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 11.5 21 3l-8.5 18-2.2-7.3L3 11.5z" fill="currentColor"/></svg>';
	// The same menu stays one tap away for the whole chat.
	var askBtn = null;
	var askMenu = null;
	if ( hasMenu || cfg.autoAnswers ) {
		askBtn = el( 'button', 'zpi-ask' );
		askBtn.type = 'button';
		askBtn.setAttribute( 'aria-label', t.commonQuestions || 'Common questions' );
		askBtn.setAttribute( 'aria-expanded', 'false' );
		askBtn.title = t.commonQuestions || 'Common questions';
		askBtn.textContent = '?';
		askMenu = el( 'div', 'zpi-ask-menu' );
		askMenu.hidden = true;
		askMenu.setAttribute( 'role', 'menu' );
		askMenu.appendChild( el( 'div', 'zpi-ask-title', t.commonQuestions || 'Common questions' ) );
		var closeAsk = function ( question ) {
			askMenu.hidden = true;
			askBtn.setAttribute( 'aria-expanded', 'false' );
			askText( question );
		};
		var askAfter = cfg.autoAnswers && t.talkToPerson ? [ t.talkToPerson ] : [];
		askBtn.addEventListener( 'click', function () {
			if ( askMenu.hidden ) {
				renderMenu( askMenu, 'zpi-ask-item', closeAsk, askAfter, null );
			}
			askMenu.hidden = ! askMenu.hidden;
			askBtn.setAttribute( 'aria-expanded', askMenu.hidden ? 'false' : 'true' );
		} );
		row.appendChild( askBtn );
	}
	row.appendChild( text );
	row.appendChild( send );

	var error = el( 'div', 'zpi-error' );
	error.hidden = true;

	form.appendChild( details );
	if ( askMenu ) {
		form.appendChild( askMenu );
	}
	form.appendChild( row );
	form.appendChild( error );

	panel.appendChild( header );
	panel.appendChild( list );
	list.appendChild( typing );
	panel.appendChild( form );

	root.appendChild( panel );
	root.appendChild( launcher );

	function time( iso ) {
		if ( ! iso ) {
			return '';
		}
		var d = new Date( iso );
		return isNaN( d ) ? '' : d.toLocaleTimeString( [], { hour: 'numeric', minute: '2-digit' } );
	}

	function clearMessages() {
		Array.prototype.slice.call( list.querySelectorAll( '.zpi-msg:not(.zpi-typing)' ) ).forEach( function ( node ) {
			if ( node !== typing ) {
				node.parentNode.removeChild( node );
			}
		} );
		state.lastId = 0;
	}

	function addMessage( m ) {
		if ( ! m || m.id <= state.lastId ) {
			return;
		}
		if ( starters && starters.parentNode ) {
			starters.parentNode.removeChild( starters );
		}
		state.lastId = m.id;

		var mine = m.sender_type === 'contact';
		var item = el( 'div', 'zpi-msg ' + ( mine ? 'zpi-me' : 'zpi-them' ) );
		if ( ! mine && m.sender_name ) {
			item.appendChild( el( 'div', 'zpi-name', m.sender_name ) );
		}
		if ( m.reply_to && ! m.deleted ) {
			var quote = el( 'div', 'zpi-quote' );
			quote.appendChild( el( 'strong', '', m.reply_to.sender_name || '' ) );
			quote.appendChild( el( 'span', '', m.reply_to.excerpt || '…' ) );
			item.appendChild( quote );
		}
		if ( m.deleted ) {
			item.appendChild( el( 'div', 'zpi-bubble zpi-deleted', t.deleted || 'Message deleted' ) );
		} else if ( m.body ) {
			item.appendChild( el( 'div', 'zpi-bubble', m.body ) );
		}
		( m.attachments || [] ).forEach( function ( a ) {
			var node = attachment( a );
			if ( node ) {
				item.appendChild( node );
			}
		} );
		// Tappable answers ("Did this answer your question?"). Only the newest
		// message keeps them; tapping one sends it like a typed reply.
		Array.prototype.slice.call( list.querySelectorAll( '.zpi-qr, .zpi-qr-prompt' ) ).forEach( function ( node ) {
			node.parentNode.removeChild( node );
		} );
		if ( ! mine && ! m.deleted && m.quick_replies && m.quick_replies.length ) {
			if ( m.quick_prompt ) {
				item.appendChild( el( 'div', 'zpi-qr-prompt', m.quick_prompt ) );
			}
			var qr = el( 'div', 'zpi-qr' );
			m.quick_replies.forEach( function ( label ) {
				var btn = el( 'button', 'zpi-qr-btn' + ( label === t.talkToPerson ? ' zpi-qr-person' : '' ), label );
				btn.type = 'button';
				btn.addEventListener( 'click', function () {
					text.value = label;
					form.requestSubmit ? form.requestSubmit() : form.dispatchEvent( new Event( 'submit', { cancelable: true } ) );
				} );
				qr.appendChild( btn );
			} );
			item.appendChild( qr );
		}
		item.appendChild( el( 'div', 'zpi-time', time( m.created_at ) + ( m.edited && ! m.deleted ? ' · ' + ( t.edited || 'edited' ) : '' ) ) );
		list.insertBefore( item, typing );

		if ( ! mine && ! state.open && ! state.reloading ) {
			state.unread++;
			badge.textContent = String( state.unread );
			badge.hidden = false;
		}
		scrollDown();
	}

	function safeUrl( url ) {
		return /^https?:\/\//i.test( url || '' ) ? url : '';
	}

	/** A product card, an image, or a link to a file. Never raw HTML. */
	function attachment( a ) {
		if ( ! a ) {
			return null;
		}
		var url = safeUrl( a.url );

		if ( a.type === 'product' ) {
			var card = el( url ? 'a' : 'div', 'zpi-card' );
			if ( url ) {
				card.href = url;
				card.target = '_blank';
				card.rel = 'noopener';
			}
			var img = safeUrl( a.image );
			if ( img ) {
				var pic = el( 'img', 'zpi-card-img' );
				pic.src = img;
				pic.alt = '';
				pic.loading = 'lazy';
				card.appendChild( pic );
			}
			var info = el( 'div', 'zpi-card-info' );
			info.appendChild( el( 'div', 'zpi-card-name', a.name || '' ) );
			info.appendChild( el( 'div', 'zpi-card-price', a.price_text || '' ) );
			if ( a.in_stock === false ) {
				info.appendChild( el( 'div', 'zpi-card-stock', t.outOfStock || 'Out of stock' ) );
			}
			if ( url ) {
				info.appendChild( el( 'span', 'zpi-card-cta', t.viewProduct || 'View product' ) );
			}
			card.appendChild( info );
			return card;
		}

		if ( a.type === 'article' && url ) {
			var article = el( 'a', 'zpi-article' );
			article.href = url;
			article.target = '_blank';
			article.rel = 'noopener';
			var thumb = safeUrl( a.image );
			if ( thumb ) {
				var tImg = el( 'img', 'zpi-article-img' );
				tImg.src = thumb;
				tImg.alt = '';
				tImg.loading = 'lazy';
				article.appendChild( tImg );
			}
			var aInfo = el( 'div', 'zpi-article-info' );
			aInfo.appendChild( el( 'div', 'zpi-article-title', a.title || '' ) );
			if ( a.excerpt ) {
				aInfo.appendChild( el( 'div', 'zpi-article-excerpt', a.excerpt ) );
			}
			aInfo.appendChild( el( 'span', 'zpi-card-cta', t.readMore || 'Read more' ) );
			article.appendChild( aInfo );
			return article;
		}

		if ( a.type === 'image' && url ) {
			var link = el( 'a', 'zpi-image' );
			link.href = url;
			link.target = '_blank';
			link.rel = 'noopener';
			var image = el( 'img' );
			image.src = url;
			image.alt = '';
			image.loading = 'lazy';
			link.appendChild( image );
			return link;
		}

		if ( url ) {
			var file = el( 'a', 'zpi-file', a.filename || t.attachment || 'Attachment' );
			file.href = url;
			file.target = '_blank';
			file.rel = 'noopener';
			return file;
		}
		return null;
	}

	function setWaiting( on ) {
		state.waiting = on;
		typing.hidden = ! on;
		if ( on ) {
			scrollDown();
		}
	}

	function scrollDown() {
		list.scrollTop = list.scrollHeight;
	}

	function showError( msg ) {
		error.textContent = msg || t.error || 'Something went wrong.';
		error.hidden = false;
	}

	function toggle( open ) {
		state.open = open;
		panel.hidden = ! open;
		root.classList.toggle( 'zpi-is-open', open );
		launcher.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		launcher.setAttribute( 'aria-label', open ? t.close || 'Close chat' : t.open || 'Open chat' );
		if ( open ) {
			state.unread = 0;
			badge.hidden = true;
			details.hidden = ! ( cfg.askEmail && ! state.lastId && ! ( state.known && state.known.email ) );
			poll().then( schedule, schedule );
			window.setTimeout( function () {
				text.focus();
			}, 50 );
			scrollDown();
		} else {
			schedule();
			launcher.focus();
		}
	}

	launcher.addEventListener( 'click', function () {
		toggle( ! state.open );
	} );
	closeBtn.addEventListener( 'click', function () {
		toggle( false );
	} );
	panel.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Escape' ) {
			toggle( false );
		}
	} );
	text.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Enter' && ! e.shiftKey ) {
			e.preventDefault();
			form.requestSubmit ? form.requestSubmit() : form.dispatchEvent( new Event( 'submit', { cancelable: true } ) );
		}
	} );
	text.addEventListener( 'input', function () {
		text.style.height = 'auto';
		text.style.height = Math.min( text.scrollHeight, 120 ) + 'px';
	} );

	form.addEventListener( 'submit', function ( e ) {
		e.preventDefault();
		var body = text.value.trim();
		if ( ! body || state.busy ) {
			return;
		}
		state.busy = true;
		send.disabled = true;
		error.hidden = true;

		ensureSession()
			.then( function ( ok ) {
				if ( ! ok ) {
					throw new Error();
				}
				return request( 'POST', 'messages', {
					body: body,
					name: nameInput.value.trim(),
					email: emailInput.value.trim(),
					page_url: window.location.href,
				} );
			} )
			.then( function ( res ) {
				if ( ! res.ok ) {
					showError( res.data && res.data.message );
					return;
				}
				text.value = '';
				text.style.height = 'auto';
				details.hidden = true;
				addMessage( res.data.message );
				// Something will answer on its own (knowledge or the assistant):
				// show it's on the way. The next poll says when it's done.
				if ( cfg.aiName || cfg.autoAnswers ) {
					setWaiting( true );
				}
				schedule();
			} )
			.catch( function () {
				showError();
			} )
			.then( function () {
				state.busy = false;
				send.disabled = false;
			} );
	} );

	document.addEventListener( 'visibilitychange', function () {
		if ( document.visibilityState === 'visible' ) {
			poll().then( schedule, schedule );
		} else {
			window.clearTimeout( state.timer );
		}
	} );

	function mount() {
		document.body.appendChild( root );
		if ( state.token ) {
			state.started = true;
			poll().then( schedule, schedule );
		}
	}

	if ( document.body ) {
		mount();
	} else {
		document.addEventListener( 'DOMContentLoaded', mount );
	}
} )();
