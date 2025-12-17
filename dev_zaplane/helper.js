import axios from 'axios';
export const {active_plugin_lists, zenreset_nonce, ajaxurl} = window.ZenResetGlobal;

export const makeRequest = async (
    action,
    payload = {},
    isRaw = false,
    suffix = ''
) => {
    let form_data = new FormData(); // eslint-disable-line
    form_data.append( 'action', `zenreset${ suffix }/${ action }` );
    if ( ! payload.security ) {
        form_data.append( 'security', zenreset_nonce );
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