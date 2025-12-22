import React from 'react';
import PropTypes from 'prop-types';
import { Modal } from '@wordpress/components';
import './styles.scss'

const propTypes = {
	title: PropTypes.string,
	isOpen: PropTypes.bool,
	onRequestClose: PropTypes.func,
	isFullScreen: PropTypes.bool,
	disableFooter: PropTypes.bool,
	shouldCloseOnClickOutside: PropTypes.bool,
	buttonUpdate: PropTypes.object,
	suffix: PropTypes.string,
	size: PropTypes.string,
};

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

WPModal.propTypes = propTypes;
