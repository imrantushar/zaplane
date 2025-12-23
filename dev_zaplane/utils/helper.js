import { useLocation } from 'react-router-dom';
import { __ } from '@wordpress/i18n';
import axios from 'axios';
import { showNotification } from '@ZAPRedux/Slices/notificationSlice/notificationSlice';

export const {
	// plugin_root_url,
	nonce,
	ajaxurl,
	menu,
	route_path,
	rest_url,
	admin_url,
	namespace,
	zaplane_nonce,
	user_id,
	is_plain_permalink,
} = window?.ZaplaneGlobal;

export const reactDebounce = (callback, wait) => {
	let timeout;
	return (...args) => {
		clearTimeout(timeout);
		timeout = setTimeout(function () {
			callback.apply(this, args);
		}, wait);
	};
};
export const useQuery = () => {
	return new URLSearchParams(useLocation().search);
};

export const handleSliceError = ( thunkAPI, error ) => {
	thunkAPI.dispatch(
		showNotification( {
			message:
				error?.response.data.message ??
				error?.response?.message ??
				error?.message,
			isShow: true,
			type: 'error',
		} )
	);
	// }
	return thunkAPI.rejectWithValue( error.message );
};


export const handleSliceSuccess = ( thunkAPI, message ) => {
	thunkAPI.dispatch(
		showNotification( {
			message: __( message, 'zaplane' ),
			isShow: true,
			type: 'success',
		} )
	);
};

export const API = axios.create( {
	baseURL: rest_url,
	headers: {
		'content-type': 'application/json',
		'X-WP-Nonce': nonce,
		'Cache-Control': 'no-cache', 
	},
} );
export const makeRequest = async (
    action,
    payload = {},
    isRaw = false,
    suffix = ''
) => {
    let form_data = new FormData(); // eslint-disable-line
    form_data.append( 'action', `zaplane${ suffix }/${ action }` );
    if ( ! payload.security ) {
        form_data.append( 'security', zaplane_nonce );
    }
    Object.entries( payload ).forEach( ( [ key, value ] ) => {
        if ( ! isRaw && typeof value === 'object' && value !== null ) {
            form_data.append( key, JSON.stringify( value ) );
        } else {
            form_data.append( key, value );
        }
    } );
    const response = await axios.post( ajaxurl, form_data );
    const {
        data: { success, data },
    } = response;

    if ( ! success ) {
        processAjaxError( data, response );
    }

    return data;
};
