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
	// While the chat is closed, the page reports itself this often; the answer
	// says whether a new message is waiting (the team may write first).
	var PRESENCE_EVERY = 20000;
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
		// The first fetch (history) is done: later messages are new.
		loaded: false,
		presenceTimer: null,
		ask: null,
		readUpto: 0,
		fresh: 0,
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
		var query = [];
		if ( state.lastId ) {
			query.push( 'after_id=' + state.lastId );
		}
		if ( state.open ) {
			query.push( 'seen=1' );
		}
		return request( 'GET', 'messages' + ( query.length ? '?' + query.join( '&' ) : '' ) ).then( function ( res ) {
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
			if ( ! state.loaded ) {
				state.readUpto = res.data.read_upto || 0;
			}
			state.fresh = 0;
			( res.data.messages || [] ).forEach( addMessage );
			state.loaded = true;
			if ( state.fresh ) {
				announce( state.fresh );
			}
			setWaiting( !! res.data.waiting );
			showContact( res.data.ask_contact || null );
		} );
	}

	/**
	 * Tell the site which page this is (the team's Visitors list) and learn
	 * whether a message is waiting; fetch it if so.
	 */
	function presence() {
		if ( ! state.token || document.visibilityState === 'hidden' ) {
			return Promise.resolve();
		}
		return request( 'POST', 'presence', {
			page_url: window.location.href,
			page_title: document.title,
			referrer: document.referrer,
		} ).then( function ( res ) {
			if ( res.ok && res.data.latest_id > state.lastId ) {
				return poll();
			}
		} );
	}

	/** New message(s) from the business: a chime, and the chat opens (or a badge). */
	function announce( count ) {
		chime();
		if ( state.open ) {
			return;
		}
		if ( cfg.autoOpen ) {
			toggle( true, true );
			return;
		}
		state.unread += count;
		badge.textContent = String( state.unread );
		badge.hidden = false;
		launcher.classList.add( 'zpi-ping' );
	}

	function schedulePresence() {
		window.clearTimeout( state.presenceTimer );
		if ( ! state.token || document.visibilityState === 'hidden' ) {
			return;
		}
		state.presenceTimer = window.setTimeout( function () {
			// An open chat polls the thread already; the page is still reported.
			presence().then( schedulePresence, schedulePresence );
		}, PRESENCE_EVERY );
	}

	/* A soft two-note chime, made on the fly: no sound file to load. Browsers
	 * only allow sound after the visitor has interacted with the page, so the
	 * audio is unlocked on their first tap or key press. */
	var audio = null;
	function unlockAudio() {
		if ( audio || ! cfg.sound ) {
			return;
		}
		var Ctx = window.AudioContext || window.webkitAudioContext;
		if ( Ctx ) {
			try {
				audio = new Ctx();
			} catch ( e ) {
				audio = null;
			}
		}
	}
	[ 'pointerdown', 'keydown', 'touchstart' ].forEach( function ( type ) {
		document.addEventListener( type, unlockAudio, { once: true, passive: true, capture: true } );
	} );
	function chime() {
		if ( ! cfg.sound || ! audio ) {
			return;
		}
		try {
			if ( audio.state === 'suspended' ) {
				audio.resume();
			}
			var now = audio.currentTime;
			[ [ 880, 0 ], [ 1318.5, 0.12 ] ].forEach( function ( note ) {
				var osc = audio.createOscillator();
				var gain = audio.createGain();
				osc.type = 'sine';
				osc.frequency.value = note[ 0 ];
				gain.gain.setValueAtTime( 0.0001, now + note[ 1 ] );
				gain.gain.exponentialRampToValueAtTime( 0.18, now + note[ 1 ] + 0.02 );
				gain.gain.exponentialRampToValueAtTime( 0.0001, now + note[ 1 ] + 0.35 );
				osc.connect( gain );
				gain.connect( audio.destination );
				osc.start( now + note[ 1 ] );
				osc.stop( now + note[ 1 ] + 0.4 );
			} );
		} catch ( e ) {
			// Sound is a nicety only.
		}
	}

	function schedule() {
		window.clearTimeout( state.timer );
		if ( ! state.token || document.visibilityState === 'hidden' ) {
			return;
		}
		// Closed: the presence report finds new messages, no need to poll.
		if ( ! state.open ) {
			return;
		}
		state.timer = window.setTimeout( function () {
			poll().then( schedule, schedule );
		}, state.waiting ? WAITING_POLL : OPEN_POLL );
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

	// "How can we reach you?" — shown once a person will answer and we have
	// no (confirmed) email; see showContact().
	var contactCard = el( 'div', 'zpi-contact' );
	contactCard.hidden = true;
	form.appendChild( contactCard );
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

		// Something new from the business (not history, not a redraw): the
		// poll that brought it announces it once.
		if ( ! mine && ( state.loaded || m.id > state.readUpto ) && ! state.reloading && ! m.deleted ) {
			state.fresh++;
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
			if ( a.option_label ) {
				info.appendChild( el( 'div', 'zpi-card-option', a.option_label ) );
			}
			var price = el( 'div', 'zpi-card-price', a.price_text || '' );
			if ( a.compare_text ) {
				price.appendChild( el( 's', 'zpi-card-was', a.compare_text ) );
			}
			info.appendChild( price );
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

	/**
	 * The contact card: name + email, then (when codes are on) the code
	 * that was emailed. `ask` comes from the server on every poll; null
	 * hides the card.
	 */
	function showContact( ask ) {
		var key = ask ? JSON.stringify( ask ) : '';
		if ( key === state.ask || contactCard.contains( document.activeElement ) ) {
			return;
		}
		state.ask = key;
		contactCard.textContent = '';
		contactCard.hidden = ! ask;
		if ( ! ask ) {
			return;
		}
		if ( ask.pending ) {
			codeStep( ask.pending );
		} else {
			detailsStep( ask );
		}
		scrollDown();
	}

	function cardError( msg ) {
		var e = contactCard.querySelector( '.zpi-contact-error' );
		e.textContent = msg || t.error || 'Something went wrong.';
		e.hidden = false;
	}

	function cardButton( label, cls, onClick ) {
		var b = el( 'button', cls, label );
		b.type = 'button';
		b.addEventListener( 'click', onClick );
		return b;
	}

	function contactRequest( body, btn ) {
		btn.disabled = true;
		return request( 'POST', 'contact', body ).then( function ( res ) {
			btn.disabled = false;
			if ( ! res.ok ) {
				cardError( res.data && res.data.message );
				return null;
			}
			return res.data;
		}, function () {
			btn.disabled = false;
			cardError();
			return null;
		} );
	}

	function detailsStep( ask ) {
		contactCard.appendChild( el( 'div', 'zpi-contact-title', t.contactTitle || 'How can we reach you?' ) );
		contactCard.appendChild( el( 'div', 'zpi-contact-hint', t.contactHint || '' ) );
		var name = null;
		if ( ask.name ) {
			name = el( 'input', 'zpi-input' );
			name.type = 'text';
			name.autocomplete = 'name';
			name.placeholder = t.name || 'Your name';
			name.setAttribute( 'aria-label', t.name || 'Your name' );
			name.value = nameInput.value;
			contactCard.appendChild( name );
		}
		var email = el( 'input', 'zpi-input' );
		email.type = 'email';
		email.autocomplete = 'email';
		email.required = true;
		email.placeholder = 'you@example.com';
		email.setAttribute( 'aria-label', t.email || 'Your email' );
		email.value = ask.email || emailInput.value;
		contactCard.appendChild( email );
		var err = el( 'div', 'zpi-contact-error' );
		err.hidden = true;
		contactCard.appendChild( err );

		var actions = el( 'div', 'zpi-contact-actions' );
		var save = cardButton( t.contactSave || 'Save', 'zpi-contact-save', function () {
			if ( ! email.value.trim() || ! email.checkValidity() ) {
				cardError( t.email || 'Your email' );
				email.focus();
				return;
			}
			contactRequest( { name: name ? name.value.trim() : '', email: email.value.trim() }, save ).then( function ( data ) {
				if ( ! data ) {
					return;
				}
				state.ask = null;
				contactCard.textContent = '';
				if ( data.status === 'code_sent' ) {
					state.ask = 'pending:' + data.email;
					codeStep( data.email );
				} else {
					done();
				}
			} );
		} );
		var skip = cardButton( t.contactSkip || 'Not now', 'zpi-contact-skip', function () {
			contactRequest( { skip: true }, skip ).then( function ( data ) {
				if ( data ) {
					contactCard.hidden = true;
				}
			} );
		} );
		actions.appendChild( skip );
		actions.appendChild( save );
		contactCard.appendChild( actions );
		[ name, email ].forEach( function ( input ) {
			if ( input ) {
				input.addEventListener( 'keydown', function ( e ) {
					if ( e.key === 'Enter' ) {
						e.preventDefault();
						save.click();
					}
				} );
			}
		} );
	}

	function codeStep( address ) {
		contactCard.hidden = false;
		contactCard.appendChild( el( 'div', 'zpi-contact-title', ( t.codeTitle || 'Enter the code we sent to %s' ).replace( '%s', address ) ) );
		var code = el( 'input', 'zpi-input zpi-code' );
		code.type = 'text';
		code.inputMode = 'numeric';
		code.autocomplete = 'one-time-code';
		code.maxLength = 6;
		code.placeholder = '••••••';
		code.setAttribute( 'aria-label', t.codeLabel || 'Verification code' );
		contactCard.appendChild( code );
		var err = el( 'div', 'zpi-contact-error' );
		err.hidden = true;
		contactCard.appendChild( err );

		var actions = el( 'div', 'zpi-contact-actions' );
		var links = el( 'div', 'zpi-contact-links' );
		var resend = cardButton( t.codeResend || 'Send a new code', 'zpi-contact-link', function () {
			contactRequest( { email: address }, resend ).then( function ( data ) {
				if ( data ) {
					err.hidden = true;
					code.value = '';
					code.focus();
				}
			} );
		} );
		var change = cardButton( t.codeChange || 'Change email', 'zpi-contact-link', function () {
			state.ask = null;
			contactCard.textContent = '';
			detailsStep( { name: false, email: '' } );
		} );
		links.appendChild( resend );
		links.appendChild( change );
		var verify = cardButton( t.codeVerify || 'Confirm', 'zpi-contact-save', function () {
			if ( code.value.replace( /\D/g, '' ).length !== 6 ) {
				code.focus();
				return;
			}
			contactRequest( { code: code.value }, verify ).then( function ( data ) {
				if ( data ) {
					done();
				}
			} );
		} );
		actions.appendChild( links );
		actions.appendChild( verify );
		contactCard.appendChild( actions );
		code.addEventListener( 'input', function () {
			code.value = code.value.replace( /\D/g, '' ).slice( 0, 6 );
			if ( code.value.length === 6 ) {
				verify.click();
			}
		} );
		window.setTimeout( function () {
			code.focus();
		}, 50 );
	}

	function done() {
		contactCard.textContent = '';
		contactCard.appendChild( el( 'div', 'zpi-contact-done', t.contactSaved || 'Thanks!' ) );
		state.ask = '';
		window.setTimeout( function () {
			contactCard.hidden = true;
			contactCard.textContent = '';
		}, 4000 );
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

	function toggle( open, byMessage ) {
		state.open = open;
		launcher.classList.remove( 'zpi-ping' );
		panel.hidden = ! open;
		root.classList.toggle( 'zpi-is-open', open );
		launcher.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		launcher.setAttribute( 'aria-label', open ? t.close || 'Close chat' : t.open || 'Open chat' );
		if ( open ) {
			state.unread = 0;
			badge.hidden = true;
			details.hidden = ! ( cfg.askEmail && ! state.lastId && ! ( state.known && state.known.email ) );
			poll().then( schedule, schedule );
			// Opened by a new message: don't pull the keyboard up on a phone.
			if ( ! byMessage ) {
				window.setTimeout( function () {
					text.focus();
				}, 50 );
			}
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
				if ( ! state.presenceTimer ) {
					schedulePresence();
				}
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
			presence().then( schedulePresence, schedulePresence );
		} else {
			window.clearTimeout( state.timer );
			window.clearTimeout( state.presenceTimer );
		}
	} );

	function startPresence() {
		presence().then( schedulePresence, schedulePresence );
	}

	function mount() {
		document.body.appendChild( root );
		var start = state.token ? Promise.resolve( ( state.started = true ) ) : cfg.visitors ? ensureSession() : Promise.resolve( false );
		start.then( function () {
			if ( ! state.token ) {
				return;
			}
			poll().then( function () {
				schedule();
				startPresence();
			}, startPresence );
		} );
		// A "continue the chat" link from an email lands with #zaplane-chat.
		if ( window.location.hash === '#zaplane-chat' ) {
			toggle( true );
		}
	}

	if ( document.body ) {
		mount();
	} else {
		document.addEventListener( 'DOMContentLoaded', mount );
	}
} )();
