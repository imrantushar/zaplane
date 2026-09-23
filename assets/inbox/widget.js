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

	var TEASER_KEY = 'zaplane_inbox_teaser';
	// Messages written before the visitor gave their name and email; kept for
	// the tab so a reload doesn't lose them.
	var HELD_KEY = 'zaplane_inbox_held';

	var state = {
		token: read( STORE_KEY ),
		// Who is answering right now: names, pictures, online or away.
		team: cfg.team || null,
		teaserTimer: null,
		teaserShown: false,
		socket: null,
		socketOk: false,
		socketTries: 0,
		socketTimer: null,
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
		// Does the next message wait for name + email? null until the site says.
		needsContact: null,
		// Those waiting messages: [{ body, row }].
		held: [],
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

	/** The first agent shown, or the assistant: whoever fronts the chat. */
	function face() {
		var team = state.team || {};
		var agent = ( team.agents || [] )[ 0 ];
		if ( agent ) {
			return agent;
		}
		if ( team.assistant ) {
			return team.assistant;
		}
		return cfg.aiName ? { name: cfg.aiName, avatar: cfg.aiAvatar || '', title: t.aiAssistant || '' } : null;
	}

	/** A round picture, or the sender's initial when there is none. */
	function avatar( person, cls ) {
		var name = ( person && person.name ) || '';
		var url = person && person.avatar;
		if ( url && /^https?:\/\//i.test( url ) ) {
			var img = el( 'img', cls || 'zpi-avatar' );
			img.src = url;
			img.alt = '';
			img.loading = 'lazy';
			return img;
		}
		var initial = el( 'span', ( cls || 'zpi-avatar' ) + ' is-letter', ( name || '?' ).charAt( 0 ).toUpperCase() );
		return initial;
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
			if ( typeof res.data.needs_contact === 'boolean' ) {
				state.needsContact = res.data.needs_contact;
			}
			state.started = true;
			write( STORE_KEY, state.token );
			applyTeam( res.data.team );
			connectSocket();
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
			if ( typeof res.data.needs_contact === 'boolean' ) {
				state.needsContact = res.data.needs_contact;
			}
			// While a message waits for details, that card is the one shown.
			if ( ! state.held.length ) {
				showContact( res.data.ask_contact || null );
			}
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
			if ( ! res.ok ) {
				return;
			}
			applyTeam( res.data.team );
			if ( res.data.latest_id > state.lastId ) {
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
		// While the socket is up a reply arrives the moment it is written, so
		// the poll drops back to a slow safety net: a connection can die
		// quietly, and a missed message is worse than a wasted request.
		var every = state.waiting ? WAITING_POLL : OPEN_POLL;
		if ( state.socketOk && ! state.waiting ) {
			every = ( cfg.socket && cfg.socket.fallbackPoll ) || 30000;
		}
		state.timer = window.setTimeout( function () {
			poll().then( schedule, schedule );
		}, every );
	}

	/* ---------------------------------------------------------------- *
	 * Realtime
	 *
	 * The socket only ever says "there is something new"; the chat then reads
	 * it the usual way. So a dropped connection costs a few seconds, never a
	 * message, and a site with no server running behaves exactly as before.
	 * ---------------------------------------------------------------- */

	function connectSocket() {
		if ( ! cfg.socket || ! cfg.socket.url || ! state.token || state.socket || ! window.WebSocket ) {
			return;
		}

		var url = cfg.socket.url + ( cfg.socket.url.indexOf( '?' ) === -1 ? '?' : '&' ) + 'visitor=' + encodeURIComponent( state.token );
		var socket;
		try {
			socket = new window.WebSocket( url );
		} catch ( e ) {
			return;
		}
		state.socket = socket;

		socket.onopen = function () {
			state.socketOk = true;
			state.socketTries = 0;
			schedule();
		};

		socket.onmessage = function ( event ) {
			var data;
			try {
				data = JSON.parse( event.data );
			} catch ( e ) {
				return;
			}
			if ( ! data || 'event' !== data.type ) {
				return;
			}
			if ( 'message' === data.event ) {
				poll().then( schedule, schedule );
			} else if ( 'team' === data.event ) {
				presence();
			}
		};

		socket.onclose = function () {
			state.socket = null;
			state.socketOk = false;
			// Back to polling at the usual pace while we try again.
			schedule();
			retrySocket();
		};

		socket.onerror = function () {
			// onclose follows, which is where the retry lives.
			state.socketOk = false;
		};
	}

	/** Try again, slower each time, and give up after a few minutes of failure. */
	function retrySocket() {
		if ( state.socketTries >= 6 ) {
			return;
		}
		window.clearTimeout( state.socketTimer );
		var wait = Math.min( 30000, 1000 * Math.pow( 2, state.socketTries ) );
		state.socketTries++;
		state.socketTimer = window.setTimeout( connectSocket, wait );
	}

	function closeSocket() {
		window.clearTimeout( state.socketTimer );
		if ( state.socket ) {
			var socket = state.socket;
			state.socket = null;
			state.socketOk = false;
			socket.onclose = null;
			try {
				socket.close();
			} catch ( e ) {}
		}
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
	// The faces of whoever is answering, so the chat has someone in it before
	// a word is said.
	var faces = el( 'div', 'zpi-faces' );
	var titleWrap = el( 'div', 'zpi-titles' );
	titleWrap.appendChild( el( 'div', 'zpi-title', cfg.title || '' ) );
	var statusLine = el( 'div', 'zpi-status' );
	var statusDot = el( 'span', 'zpi-status-dot' );
	var statusText = el( 'span', 'zpi-status-text' );
	statusLine.appendChild( statusDot );
	statusLine.appendChild( statusText );
	titleWrap.appendChild( statusLine );
	var closeBtn = el( 'button', 'zpi-close' );
	closeBtn.type = 'button';
	closeBtn.setAttribute( 'aria-label', t.close || 'Close chat' );
	closeBtn.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>';
	header.appendChild( faces );
	header.appendChild( titleWrap );
	header.appendChild( closeBtn );

	/**
	 * Redraw the header from what the server last said about the team: who is
	 * there, and whether anyone is.
	 */
	function applyTeam( team ) {
		if ( team ) {
			state.team = team;
		}
		var current = state.team || {};
		var people = ( current.agents || [] ).slice( 0, 3 );
		if ( ! people.length && current.assistant ) {
			people = [ current.assistant ];
		}

		faces.textContent = '';
		if ( cfg.showTeam !== false ) {
			people.forEach( function ( person ) {
				var wrap = el( 'span', 'zpi-face' + ( person.online ? ' is-online' : '' ) );
				wrap.title = person.title ? person.name + ' · ' + person.title : person.name;
				wrap.appendChild( avatar( person, 'zpi-face-img' ) );
				faces.appendChild( wrap );
			} );
		}
		faces.hidden = ! faces.childNodes.length;

		var online = !! current.online;
		var label = online ? ( t.online || "We're online" ) : ( current.message || t.offline || 'Away' );
		// An assistant that answers on its own is never "away", whatever the
		// team is doing.
		if ( ! online && ( cfg.aiName || cfg.autoAnswers ) ) {
			label = current.message || ( t.offline || 'Away' );
		}
		if ( online && current.reply_time ) {
			label = ( t.replyTime || 'Typically replies %s' ).replace( '%s', current.reply_time );
		}
		dressGreeting();
		statusText.textContent = label;
		statusLine.classList.toggle( 'is-online', online );
		statusDot.hidden = false;
		root.classList.toggle( 'zpi-team-offline', ! online );
	}

	var list = el( 'div', 'zpi-messages' );
	list.setAttribute( 'aria-live', 'polite' );

	// The opening line, signed like any other message from the team.
	var greeting = el( 'div', 'zpi-msg zpi-them' );
	var greetingFace = el( 'span', 'zpi-avatar is-spacer' );
	var greetingBody = el( 'div', 'zpi-msg-body' );
	greetingBody.appendChild( el( 'div', 'zpi-bubble', cfg.greeting || '' ) );
	greeting.appendChild( greetingFace );
	greeting.appendChild( greetingBody );

	/** Put whoever fronts the chat beside the greeting, once we know them. */
	function dressGreeting() {
		var person = face();
		if ( ! person ) {
			return;
		}
		var picture = avatar( person );
		greeting.replaceChild( picture, greetingFace );
		greetingFace = picture;
	}

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
		greetingBody.appendChild( starters );
	}

	if ( cfg.greeting || starters ) {
		list.appendChild( greeting );
	}

	var typing = el( 'div', 'zpi-msg zpi-them zpi-typing' );
	var typingBubble = el( 'div', 'zpi-bubble' );
	typingBubble.setAttribute( 'role', 'status' );
	typingBubble.setAttribute( 'aria-label', t.typing || 'Typing…' );
	for ( var d = 0; d < 3; d++ ) {
		typingBubble.appendChild( el( 'span', 'zpi-dot' ) );
	}
	typing.appendChild( typingBubble );
	typing.hidden = true;

	var form = el( 'form', 'zpi-form' );
	// What the visitor typed into the details card, reused if it's shown again.
	var given = { name: '', email: '' };

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
	if ( askMenu ) {
		form.appendChild( askMenu );
	}
	form.appendChild( row );
	form.appendChild( error );

	panel.appendChild( header );
	panel.appendChild( list );
	list.appendChild( typing );
	panel.appendChild( form );

	/* ---------------------------------------------------------------- *
	 * The message that opens by itself
	 * ---------------------------------------------------------------- */

	// A small card above the launcher after a while on the page: the way a
	// person in a shop looks up when you have been browsing a minute. It is
	// not a message — nothing is stored, and the team never sees it — until
	// the visitor answers.
	var teaser = el( 'div', 'zpi-teaser' );
	teaser.hidden = true;
	var teaserBody = el( 'button', 'zpi-teaser-open' );
	teaserBody.type = 'button';
	var teaserClose = el( 'button', 'zpi-teaser-close' );
	teaserClose.type = 'button';
	teaserClose.setAttribute( 'aria-label', t.dismiss || 'Dismiss' );
	teaserClose.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/></svg>';
	teaser.appendChild( teaserBody );
	teaser.appendChild( teaserClose );

	/** Has this visitor already been greeted, as often as the settings allow? */
	function teaserSeen() {
		var repeat = ( cfg.proactive && cfg.proactive.repeat ) || 'session';
		if ( 'always' === repeat ) {
			return false;
		}
		if ( 'session' === repeat ) {
			try {
				return '1' === window.sessionStorage.getItem( TEASER_KEY );
			} catch ( e ) {
				return false;
			}
		}
		var last = parseInt( read( TEASER_KEY ), 10 );
		return !! last && Date.now() - last < 86400000;
	}

	function rememberTeaser() {
		var repeat = ( cfg.proactive && cfg.proactive.repeat ) || 'session';
		try {
			if ( 'session' === repeat ) {
				window.sessionStorage.setItem( TEASER_KEY, '1' );
			} else if ( 'day' === repeat ) {
				write( TEASER_KEY, String( Date.now() ) );
			}
		} catch ( e ) {
			// Remembering is a nicety; showing it twice is not a fault.
		}
	}

	function showTeaser() {
		if ( state.open || state.teaserShown || state.lastId ) {
			return;
		}
		// "Only when somebody can answer" means exactly that: no cheerful
		// hello at 3am when the reply will not come until Monday.
		if ( cfg.proactive.whenOnline && ! ( state.team && state.team.online ) ) {
			return;
		}

		var person = face();
		state.teaserShown = true;
		rememberTeaser();

		teaserBody.textContent = '';
		if ( person ) {
			teaserBody.appendChild( avatar( person, 'zpi-teaser-avatar' ) );
		}
		var lines = el( 'span', 'zpi-teaser-lines' );
		if ( person && person.name ) {
			lines.appendChild( el( 'span', 'zpi-teaser-name', person.name ) );
		}
		lines.appendChild( el( 'span', 'zpi-teaser-text', cfg.proactive.message ) );
		teaserBody.appendChild( lines );

		teaser.hidden = false;
		root.classList.add( 'zpi-has-teaser' );
		// Requesting the frame first lets the entry animation run.
		window.requestAnimationFrame( function () {
			teaser.classList.add( 'is-in' );
		} );
		chime();
	}

	function hideTeaser() {
		teaser.classList.remove( 'is-in' );
		root.classList.remove( 'zpi-has-teaser' );
		window.setTimeout( function () {
			teaser.hidden = true;
		}, 200 );
	}

	teaserClose.addEventListener( 'click', function ( e ) {
		e.stopPropagation();
		hideTeaser();
	} );

	teaserBody.addEventListener( 'click', function () {
		hideTeaser();
		toggle( true );
		// The greeting they were shown becomes the first thing in the thread,
		// so opening the chat doesn't lose what drew them in.
		seedGreeting( cfg.proactive.message );
	} );

	/** Put a message in the thread that only this browser has seen. */
	function seedGreeting( body ) {
		if ( ! body || state.lastId || list.querySelector( '.zpi-seeded' ) ) {
			return;
		}
		var person = face() || {};
		var row = el( 'div', 'zpi-msg zpi-them zpi-seeded' );
		row.appendChild( avatar( person ) );
		var col = el( 'div', 'zpi-msg-body' );
		if ( person.name ) {
			col.appendChild( el( 'div', 'zpi-name', person.name ) );
		}
		col.appendChild( el( 'div', 'zpi-bubble', body ) );
		row.appendChild( col );
		list.insertBefore( row, typing );
		state.lastSender = '';
		scrollDown();
	}

	function startTeaser() {
		if ( ! cfg.proactive || ! cfg.proactive.message || teaserSeen() ) {
			return;
		}
		window.clearTimeout( state.teaserTimer );
		state.teaserTimer = window.setTimeout( showTeaser, Math.max( 1, cfg.proactive.delay ) * 1000 );
	}

	root.appendChild( teaser );
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
		Array.prototype.slice.call( list.querySelectorAll( '.zpi-msg:not(.zpi-typing):not(.zpi-held)' ) ).forEach( function ( node ) {
			if ( node !== typing ) {
				node.parentNode.removeChild( node );
			}
		} );
		state.lastId = 0;
		state.lastSender = '';
	}

	/** What marks one run of messages from the same sender. */
	function senderKey( m ) {
		return ( m.sender_kind || m.sender_type || '' ) + '|' + ( m.sender_name || '' );
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
		var row = el( 'div', 'zpi-msg ' + ( mine ? 'zpi-me' : 'zpi-them' ) );
		// Everything after the picture goes in this column.
		var item = row;

		if ( ! mine ) {
			// The sender is named and pictured once at the top of their run,
			// not over every bubble.
			var grouped = state.lastSender === senderKey( m );
			if ( grouped ) {
				row.className += ' is-grouped';
				row.appendChild( el( 'span', 'zpi-avatar is-spacer' ) );
			} else {
				row.appendChild( avatar( { name: m.sender_name || '', avatar: m.sender_avatar || '' } ) );
			}

			item = el( 'div', 'zpi-msg-body' );
			row.appendChild( item );

			if ( ! grouped && m.sender_name ) {
				var who = el( 'div', 'zpi-name', m.sender_name );
				if ( 'ai' === m.sender_kind ) {
					who.appendChild( el( 'span', 'zpi-tag', t.aiTag || 'AI' ) );
				}
				item.appendChild( who );
			}
			state.lastSender = senderKey( m );
		} else {
			state.lastSender = 'me';
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
		list.insertBefore( row, state.held.length ? state.held[0].row : typing );

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

	function contactRequest( body, btn, onError ) {
		btn.disabled = true;
		return request( 'POST', 'contact', body ).then( function ( res ) {
			btn.disabled = false;
			if ( ! res.ok ) {
				cardError( res.data && res.data.message );
				if ( onError ) {
					onError( res.data );
				}
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
			name.value = given.name;
			contactCard.appendChild( name );
		}
		var email = el( 'input', 'zpi-input' );
		email.type = 'email';
		email.autocomplete = 'email';
		email.required = true;
		email.placeholder = 'you@example.com';
		email.setAttribute( 'aria-label', t.email || 'Your email' );
		email.value = ask.email || given.email;
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
		if ( open ) {
			window.clearTimeout( state.teaserTimer );
			if ( ! teaser.hidden ) {
				hideTeaser();
			}
		}
		panel.hidden = ! open;
		root.classList.toggle( 'zpi-is-open', open );
		launcher.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		launcher.setAttribute( 'aria-label', open ? t.close || 'Close chat' : t.open || 'Open chat' );
		if ( open ) {
			state.unread = 0;
			badge.hidden = true;
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
	/** The send arrow lights up once there is something to send. */
	function syncSend() {
		root.classList.toggle( 'zpi-has-text', !! text.value.trim() );
	}
	text.addEventListener( 'input', function () {
		text.style.height = 'auto';
		text.style.height = Math.min( text.scrollHeight, 120 ) + 'px';
		syncSend();
	} );

	form.addEventListener( 'submit', function ( e ) {
		e.preventDefault();
		var body = text.value.trim();
		if ( ! body || state.busy ) {
			return;
		}
		error.hidden = true;
		text.value = '';
		text.style.height = 'auto';
		syncSend();

		// Name and email first: the message waits in the chat until then.
		if ( needsDetails() ) {
			hold( body );
			return;
		}
		deliver( body, null );
	} );

	function needsDetails() {
		if ( state.held.length ) {
			return true;
		}
		if ( typeof state.needsContact === 'boolean' ) {
			return state.needsContact;
		}
		return !! cfg.askEmail && ! ( state.known && state.known.email );
	}

	/**
	 * Send one message. `details` ({ name, email, code? }) goes with the
	 * first held message. Resolves true once it's in, false if it wasn't
	 * (the message is then held, or the error shown).
	 */
	function deliver( body, details ) {
		state.busy = true;
		send.disabled = true;

		return ensureSession()
			.then( function ( ok ) {
				if ( ! ok ) {
					throw new Error();
				}
				var payload = { body: body, page_url: window.location.href };
				if ( details ) {
					payload.name = details.name;
					payload.email = details.email;
					if ( details.code ) {
						payload.code = details.code;
					}
				}
				return request( 'POST', 'messages', payload );
			} )
			.then( function ( res ) {
				if ( ! res.ok ) {
					var need = res.data && res.data.data && res.data.data.need;
					if ( need && ! details ) {
						// The site wants details after all: hold it and ask.
						state.needsContact = true;
						hold( body );
						return false;
					}
					if ( details ) {
						return res;
					}
					text.value = body;
					syncSend();
					showError( res.data && res.data.message );
					return false;
				}
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
				return true;
			} )
			.catch( function () {
				if ( details ) {
					return { ok: false, data: {} };
				}
				text.value = body;
				syncSend();
				showError();
				return false;
			} )
			.then( function ( out ) {
				state.busy = false;
				send.disabled = false;
				return out;
			} );
	}

	/* ---------------------------------------------------------------- *
	 * Messages waiting for name + email
	 * ---------------------------------------------------------------- */

	function hold( body, restoring ) {
		if ( starters && starters.parentNode ) {
			starters.parentNode.removeChild( starters );
		}
		var row = el( 'div', 'zpi-msg zpi-me zpi-held' );
		row.appendChild( el( 'div', 'zpi-bubble', body ) );
		row.appendChild( el( 'div', 'zpi-time zpi-held-note', t.heldNote || 'Not sent yet' ) );
		list.insertBefore( row, typing );
		state.held.push( { body: body, row: row } );
		if ( ! restoring ) {
			saveHeld();
		}
		scrollDown();
		if ( state.held.length === 1 || contactCard.hidden ) {
			detailsForHeld();
		}
	}

	function saveHeld() {
		try {
			window.sessionStorage.setItem( HELD_KEY, JSON.stringify( state.held.map( function ( h ) {
				return h.body;
			} ) ) );
		} catch ( e ) {}
	}

	function restoreHeld() {
		var bodies = [];
		try {
			bodies = JSON.parse( window.sessionStorage.getItem( HELD_KEY ) || '[]' ) || [];
		} catch ( e ) {}
		if ( ! bodies.length ) {
			return;
		}
		// The site may no longer need details (they gave them in another tab).
		if ( state.needsContact === false ) {
			bodies.forEach( function ( b ) {
				deliverQueued( b );
			} );
			saveHeldBodies( [] );
			return;
		}
		bodies.forEach( function ( b ) {
			hold( b, true );
		} );
	}

	function saveHeldBodies( bodies ) {
		try {
			window.sessionStorage.setItem( HELD_KEY, JSON.stringify( bodies ) );
		} catch ( e ) {}
	}

	var queue = Promise.resolve();
	function deliverQueued( body ) {
		queue = queue.then( function () {
			return deliver( body, null );
		} );
		return queue;
	}

	function holdCard() {
		state.ask = 'held';
		contactCard.textContent = '';
		contactCard.hidden = false;
		contactCard.classList.add( 'is-held' );
	}

	/** "Where should we reply?" — name and email for the waiting message. */
	function detailsForHeld( prefill, message ) {
		holdCard();
		contactCard.appendChild( el( 'div', 'zpi-contact-title', t.heldTitle || 'Where should we reply?' ) );
		contactCard.appendChild( el( 'div', 'zpi-contact-hint', t.heldHint || 'Add your name and email and your message goes straight to our team.' ) );
		var name = el( 'input', 'zpi-input' );
		name.type = 'text';
		name.autocomplete = 'name';
		name.required = true;
		name.placeholder = t.name || 'Your name';
		name.setAttribute( 'aria-label', t.name || 'Your name' );
		name.value = ( prefill && prefill.name ) || given.name || ( state.known && state.known.name ) || '';
		var email = el( 'input', 'zpi-input' );
		email.type = 'email';
		email.autocomplete = 'email';
		email.required = true;
		email.placeholder = t.emailShort || 'you@example.com';
		email.setAttribute( 'aria-label', t.emailLabel || 'Your email' );
		email.value = ( prefill && prefill.email ) || given.email || '';
		contactCard.appendChild( name );
		contactCard.appendChild( email );
		var err = el( 'div', 'zpi-contact-error' );
		err.hidden = true;
		contactCard.appendChild( err );
		var fix = el( 'div', 'zpi-contact-links' );
		fix.hidden = true;
		contactCard.appendChild( fix );

		var actions = el( 'div', 'zpi-contact-actions' );
		var go = cardButton( t.heldSend || 'Send message', 'zpi-contact-save', function () {
			given.name = name.value.trim();
			given.email = email.value.trim();
			if ( ! given.name ) {
				cardError( t.nameRequired || 'Please enter your name.' );
				name.focus();
				return;
			}
			if ( ! given.email || ! email.checkValidity() ) {
				cardError( t.emailInvalid || 'Please enter a valid email address.' );
				email.focus();
				return;
			}
			err.hidden = true;
			fix.hidden = true;
			ensureSession().then( function ( ok ) {
				if ( ! ok ) {
					cardError();
					return null;
				}
				return contactRequest( { prechat: true, name: given.name, email: given.email }, go, function ( data ) {
					// "Did you mean …@gmail.com?" — one tap to use it.
					var suggestion = data && data.data && data.data.suggestion;
					if ( suggestion ) {
						fix.textContent = '';
						fix.appendChild( cardButton( ( t.useSuggestion || 'Use %s' ).replace( '%s', suggestion ), 'zpi-contact-link', function () {
							email.value = suggestion;
							err.hidden = true;
							fix.hidden = true;
							go.click();
						} ) );
						fix.hidden = false;
					}
				} );
			} ).then( function ( data ) {
				if ( ! data ) {
					return;
				}
				var details = { name: given.name, email: data.email || given.email };
				if ( data.status === 'code_sent' ) {
					codeForHeld( details );
				} else {
					sendHeld( details );
				}
			} );
		} );
		actions.appendChild( go );
		contactCard.appendChild( actions );
		[ name, email ].forEach( function ( input ) {
			input.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Enter' ) {
					e.preventDefault();
					go.click();
				}
			} );
		} );
		if ( message ) {
			cardError( message );
		}
		scrollDown();
		window.setTimeout( function () {
			( name.value ? email : name ).focus();
		}, 50 );
	}

	/** Two-step: the code we emailed, then the message goes. */
	function codeForHeld( details, message ) {
		holdCard();
		contactCard.appendChild( el( 'div', 'zpi-contact-title', ( t.codeTitle || 'Enter the code we sent to %s' ).replace( '%s', details.email ) ) );
		contactCard.appendChild( el( 'div', 'zpi-contact-hint', t.heldCodeHint || 'Your message is sent as soon as you confirm.' ) );
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
			contactRequest( { prechat: true, name: details.name, email: details.email }, resend ).then( function ( data ) {
				if ( data ) {
					err.hidden = true;
					code.value = '';
					code.focus();
				}
			} );
		} );
		var change = cardButton( t.codeChange || 'Change email', 'zpi-contact-link', function () {
			detailsForHeld( details );
		} );
		links.appendChild( resend );
		links.appendChild( change );
		var verify = cardButton( t.heldConfirm || 'Confirm & send', 'zpi-contact-save', function () {
			if ( code.value.replace( /\D/g, '' ).length !== 6 ) {
				code.focus();
				return;
			}
			verify.disabled = true;
			sendHeld( { name: details.name, email: details.email, code: code.value } ).then( function () {
				verify.disabled = false;
			} );
		} );
		actions.appendChild( links );
		actions.appendChild( verify );
		contactCard.appendChild( actions );
		code.addEventListener( 'input', function () {
			code.value = code.value.replace( /\D/g, '' ).slice( 0, 6 );
			if ( code.value.length === 6 && ! verify.disabled ) {
				verify.click();
			}
		} );
		if ( message ) {
			cardError( message );
		}
		scrollDown();
		window.setTimeout( function () {
			code.focus();
		}, 50 );
	}

	/**
	 * Send what was held: the first message carries the details (and code),
	 * the rest follow in order.
	 */
	function sendHeld( details ) {
		var first = state.held[0];
		if ( ! first ) {
			return Promise.resolve();
		}
		return deliver( first.body, details ).then( function ( out ) {
			if ( out !== true ) {
				var data = ( out && out.data ) || {};
				var need = data.data && data.data.need;
				if ( need === 'code' && details && details.code ) {
					codeForHeld( details, data.message );
				} else {
					detailsForHeld( details, data.message || t.error );
				}
				return;
			}
			removeHeld( first );
			state.needsContact = false;
			state.known = { name: details ? details.name : '', email: details ? details.email : '' };
			contactCard.classList.remove( 'is-held' );
			done();
			var rest = state.held.slice();
			rest.forEach( function ( h ) {
				removeHeld( h );
				deliverQueued( h.body );
			} );
			saveHeld();
		} );
	}

	function removeHeld( h ) {
		if ( h.row.parentNode ) {
			h.row.parentNode.removeChild( h.row );
		}
		state.held = state.held.filter( function ( x ) {
			return x !== h;
		} );
	}

	document.addEventListener( 'visibilitychange', function () {
		if ( document.visibilityState === 'visible' ) {
			poll().then( schedule, schedule );
			presence().then( schedulePresence, schedulePresence );
			connectSocket();
		} else {
			window.clearTimeout( state.timer );
			window.clearTimeout( state.presenceTimer );
			window.clearTimeout( state.teaserTimer );
			// A backgrounded tab holding a socket open helps nobody; the next
			// poll catches up when they come back.
			closeSocket();
		}
	} );

	window.addEventListener( 'pagehide', closeSocket );

	function startPresence() {
		presence().then( schedulePresence, schedulePresence );
	}

	function mount() {
		document.body.appendChild( root );
		applyTeam( cfg.team );
		if ( cfg.proactive ) {
			startTeaser();
		}
		var start = state.token ? Promise.resolve( ( state.started = true ) ) : cfg.visitors ? ensureSession() : Promise.resolve( false );
		start.then( function () {
			if ( ! state.token ) {
				return;
			}
			connectSocket();
			poll().then( function () {
				schedule();
				startPresence();
				restoreHeld();
			}, startPresence );
		} ).then( function () {
			if ( ! state.token ) {
				restoreHeld();
			}
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
