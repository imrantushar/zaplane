import { createSlice } from '@reduxjs/toolkit';

const notificationSlice = createSlice( {
	name: 'notification',
	initialState: {
		message: '',
		isShow: false,
		type: '',
		isHtml: false,
		linkTo: false,
	},
	reducers: {
		showNotification: (
			state,
			{
				payload: {
					message = null,
					isShow = false,
					type = 'success',
					isHtml = false,
					linkTo = false
				},
			}
		) => {
			let messageRef = message;
			if ( messageRef instanceof Error ) {
				message = messageRef.message;
				type = 'error';

				// noinspection JSUnresolvedReference
				// If Axios error and server sends error details.
				if ( messageRef.isAxiosError ) {
					if ( messageRef?.response?.data?.message ) {
						if (
							messageRef.response.data?.additional_errors &&
							messageRef.response.data?.additional_errors.length
						) {
							message = [
								{
									code: messageRef.response.data.code,
									message: messageRef.response.data.message,
									data: messageRef.response.data.data,
								},
								...messageRef.response.data?.additional_errors,
							];
						} else {
							message = messageRef.response.data.message;
						}
					} else if ( messageRef?.response?.data?.data ) {
						message = messageRef.response.data.data;
					}
				}

				messageRef = message;
			}

			if (
				'object' === typeof messageRef &&
				! Array.isArray( messageRef )
			) {
				if ( messageRef?.message ) {
					message = messageRef.message;
				} else if ( messageRef?.response?.data?.data ) {
					message = messageRef.response.data.data;
				} else if ( messageRef?.data?.message ) {
					message = messageRef.data.message;
				}
			}

			if (
				Array.isArray( message ) &&
				message[ 0 ].code &&
				message[ 0 ].message
			) {
				if ( message.length === 1 ) {
					message = message[ 0 ].message;
				} else {
					const errors = [ ...message ];
					message =
						'<ul class="community-m-0" style="padding-left:1rem; margin: 0;">';
					for ( const err of errors ) {
						message += `<li id="error-${ err.code }">${ err.message }</li>`;
					}

					isHtml = true;
					message += '</ul>';
				}
			} else if ( message?.error?.message ) {
				message = message?.error.message;
			}

			if (
				'string' === typeof message &&
				message.includes( '<' ) &&
				( message.includes( '</' ) || message.includes( '/>' ) )
			) {
				isHtml = true;
			}

			state.message = message;
			state.isShow = isShow;
			state.type = type;
			state.isHtml = isHtml;
			state.linkTo = linkTo;
		},
	},
} );

export default notificationSlice.reducer;
export const { showNotification } = notificationSlice.actions;
