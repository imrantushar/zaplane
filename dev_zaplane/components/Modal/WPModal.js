import React from 'react';
import { Modal } from '@wordpress/components';
import './styles.scss'



export default function WPModal( {
	children,
	suffix = '',
	title = '',
	isOpen = false,
	onRequestClose,
	isFullScreen = false,
	shouldCloseOnClickOutside = false,
	size = 'medium',
} ) {
	return (
		<React.Fragment>
			{ isOpen && (
				<Modal
					title={ title }
					onRequestClose={ onRequestClose }
					contentLabel={ title }
					isFullScreen={ isFullScreen }
					shouldCloseOnClickOutside={ shouldCloseOnClickOutside }
					className={ `zaplane-wp-modal zaplane-wp-modal--${ size } ${
						suffix && ' zaplane-wp-modal--' + suffix
					}` }
				>
					<div className="zaplane-wp-modal__content">
						{ children }
					</div>
				</Modal>
			) }
		</React.Fragment>
	);
}

