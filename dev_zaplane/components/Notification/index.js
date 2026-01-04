import React, { useEffect, useRef } from 'react';
import { useSelector, useDispatch } from 'react-redux';
import { __ } from '@wordpress/i18n';
import { createPortal } from 'react-dom';
import { Button } from '@chakra-ui/react';
import { showNotification } from '@ZAPRedux/Slices/notificationSlice/notificationSlice';

const iconType = ( type ) => {
	switch ( type ) {
		case 'error':
			return 'badge-alert';
		case 'info':
			return 'info';
		case 'warning':
			return 'circle-alert';
		default:
			return 'round-checkmark';
	}
};

const Notification = () => {
	const notificationRef = useRef( null );
	const targetElement = document.querySelector('#zaplane-app') 
	const notification = useSelector( ( state ) => state.notification );
	const dispatch = useDispatch();
	console.log({notification});

	const isShowNotification =
		notification?.showNotification || notification?.isShow;

	useEffect( () => {
		if ( isShowNotification && targetElement ) {
			notificationRef.current.style.position = 'fixed';
			notificationRef.current.style.left = `50%`; // Adjust left position as needed
			notificationRef.current.style.transform = 'translateX(-50%)';
			notificationRef.current.style.bottom = `50px`; // Place it at the bottom
			document.body.appendChild( notificationRef.current );
		} else if (
			notificationRef.current &&
			notificationRef.current.parentNode === document.body
		) {
			document.body.removeChild( notificationRef.current );
		}

		return () => {
			if (
				notificationRef.current &&
				notificationRef.current.parentNode === document.body
			) {
				document.body.removeChild( notificationRef?.current );
			}
		};
	}, [ isShowNotification ] );

	useEffect( () => {
		if ( isShowNotification ) {
			const timeout_id = setTimeout( () => {
				closeHandler();
			}, 6000 );

			return () => clearTimeout( timeout_id );
		}
	}, [ isShowNotification ] );

	const closeHandler = () => {
		dispatch(
			showNotification( {
				message: '',
				isShow: false,
			} )
		);
	};
	return (
		<>
			{ isShowNotification &&
				createPortal(
					<div
						className={ `zaplaness-notification ${
							notification.type &&
							`zaplaness-notification--${ notification.type }`
						}` }
						ref={ notificationRef }
					>
						<div className="zaplaness-notification__message">
							<span
								className={ `zaplaness-icon zaplaness-icon--${ iconType(
									notification.type
								) } has-zaplaness-blue-bg` }
								aria-hidden={ true }
							/>
							{ notification.isHtml ? (
								<div
									dangerouslySetInnerHTML={ {
										__html: notification.message,
									} }
								/>
							) : (
								notification.message
							) }
						</div>
						<Button
							bg="transparent"
							onClick={ closeHandler }
							aria-label={ __(
								'Close notification',
								'zaplaness'
							) }
						>
							<span className="zaplaness-icon zaplaness-icon--close has-zaplaness-blue-bg" />
						</Button>
					</div>,
					document.body
				) }
		</>
	);
};

export default Notification;
