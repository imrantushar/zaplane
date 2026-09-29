/**
 * Parse the portable cURL request form used by API documentation tools.
 * This intentionally tokenises shell quoting without executing a shell.
 *
 * @param {string} command cURL command text.
 * @return {Object} Request manifest fragment.
 */
export const parseCurl = ( command ) => {
	const tokens = [];
	const source = String( command || '' ).replace( /\\\r?\n/g, ' ' );
	const tokenPattern = /"((?:\\.|[^"])*)"|'([^']*)'|(\S+)/g;
	let match;

	while ( ( match = tokenPattern.exec( source ) ) ) {
		tokens.push( match[ 1 ] ?? match[ 2 ] ?? match[ 3 ] );
	}

	if ( ! tokens.length || tokens[ 0 ].toLowerCase() !== 'curl' ) {
		throw new Error( 'Please paste a cURL command starting with curl.' );
	}

	let method = '';
	let url = '';
	let body;
	let bodyIsJson = false;
	let formData = false;
	let get = false;
	const headers = {};

	const addHeader = ( value ) => {
		const separator = value.indexOf( ':' );
		if ( separator < 1 ) {
			return;
		}
		const name = value.slice( 0, separator ).trim();
		headers[ name ] = value.slice( separator + 1 ).trim();
		if (
			name.toLowerCase() === 'content-type' &&
			headers[ name ].includes( 'json' )
		) {
			bodyIsJson = true;
		}
	};

	for ( let i = 1; i < tokens.length; i += 1 ) {
		const token = tokens[ i ];
		const next = () => {
			i += 1;
			return tokens[ i ] || '';
		};

		if ( token === '-X' || token === '--request' ) {
			method = next().toUpperCase();
		} else if ( token === '-H' || token === '--header' ) {
			addHeader( next() );
		} else if (
			token === '-d' ||
			token === '--data' ||
			token === '--data-raw' ||
			token === '--data-binary'
		) {
			body = next();
			if ( ! method ) {
				method = 'POST';
			}
		} else if (
			token === '--data-urlencode' ||
			token === '-F' ||
			token === '--form'
		) {
			body = next();
			formData = true;
			if ( ! method ) {
				method = 'POST';
			}
		} else if ( token === '-G' || token === '--get' ) {
			get = true;
			method = 'GET';
		} else if ( token === '--url' ) {
			url = next();
		} else if (
			! token.startsWith( '-' ) &&
			/^https?:\/\//i.test( token )
		) {
			url = token;
		} else if ( token === '--user' || token === '-u' ) {
			const credentials = next().split( ':' );
			headers.Authorization = `Basic ${ window.btoa(
				`${ credentials[ 0 ] || '' }:${ credentials
					.slice( 1 )
					.join( ':' ) }`
			) }`;
		}
	}

	if ( ! url ) {
		throw new Error( 'The cURL command does not contain an HTTP URL.' );
	}
	if ( get && body ) {
		const joiner = url.includes( '?' ) ? '&' : '?';
		url += `${ joiner }${ body }`;
		body = undefined;
	}

	if ( bodyIsJson && body ) {
		try {
			body = JSON.parse( body );
		} catch ( error ) {
			throw new Error( 'The cURL JSON body is invalid.' );
		}
	} else if ( formData && body ) {
		const params = new URLSearchParams( body );
		body = Object.fromEntries( params.entries() );
	}

	return {
		method: method || ( body ? 'POST' : 'GET' ),
		url,
		headers,
		...( body !== undefined ? { body } : {} ),
	};
};
