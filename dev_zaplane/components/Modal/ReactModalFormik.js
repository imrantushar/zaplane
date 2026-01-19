import React, { useState } from 'react';
import PropTypes from 'prop-types';
import { Form, Formik } from 'formik';
import Modal from 'react-modal';
import { __ } from '@wordpress/i18n';
import { suffixClassNames, contentClassNames } from './helper';
import { CloseButton, Button } from '@chakra-ui/react';
import { outlineBtn, primaryBtn } from '../../../assets/scss/chakra/recipe';
import OptionMenu from '@QLComponents/OptionMenu';

import './styles.scss';

const propTypes = {
	title: PropTypes.oneOfType( [ PropTypes.object, PropTypes.string ] ),
	isOpen: PropTypes.bool,
	isLoading: PropTypes.bool,
	isLargeAvailable: PropTypes.bool,
	isSmallAvailable: PropTypes.bool,
	isEnabledResizer: PropTypes.bool,
	onAfterOpen: PropTypes.func,
	onRequestClose: PropTypes.func,
	cancelButtonLabel: PropTypes.string,
	submitButtonLabel: PropTypes.string,
	style: PropTypes.object,
	suffix: PropTypes.string,
	size: PropTypes.string,
	formik: PropTypes.object,
	isEnabledFooter: PropTypes.bool,
};

// Need to manage common id - future will be improve here
if ( document.getElementById( 'quizleapwrap' ) ) {
	Modal.setAppElement( '#quizleapwrap' );
} else if ( document.getElementById( 'quizleapModalWrap' ) ) {
	Modal.setAppElement( '#quizleapModalWrap' );
} else if ( document.getElementById( 'quizleapFrontendWrap' ) ) {
	Modal.setAppElement( '#quizleapFrontendWrap' );
}

export default function ReactModalFormik( {
	children,
	suffix = '',
	title = '',
	isOpen = false,
	isSmallAvailable = true,
	isLargeAvailable = true,
	isEnabledFooter = true,
	isEnabledResizer = true,
	onAfterOpen,
	onRequestClose,
	cancelButtonLabel = __( 'Cancel', 'quizleap' ),
	submitButtonLabel = __( 'Update', 'quizleap' ),
	size = 'medium',
	formik = {},
	style = {
		overlay: {
			background: 'rgba(35, 40, 45, 0.62)',
			overflowY: 'scroll',
			display: 'flex',
			justifyContent: 'center',
			zIndex: 9999,
		},
		content: {
			inset: 'unset',
			padding: 0,
			border: 0,
			overflow: 'none',
			marginTop: '50px',
		},
	},
} ) {
	const [ devicePreview, setDevicePreview ] = useState( {
		expandableSize: size,
		activeIconIndex: 3,
	} );

	const iconOptions = [
		'quizleap-icon quizleap-icon--large-screen',
		'quizleap-icon quizleap-icon--medium-screen',
		'quizleap-icon quizleap-icon--small-screen',
		'quizleap-icon quizleap-icon--columns',
	];

	const toggleIcon = ( index ) => {
		setDevicePreview( ( prevState ) => ( {
			...prevState,
			activeIconIndex: index,
			expandableSize: [ 'large', 'medium', 'small' ][ index ],
		} ) );
	};

	const modalStyle =
		devicePreview.expandableSize === 'large'
			? {
					...style,
					content: {
						...style.content,
						marginTop: '30px',
					},
			  }
			: style;
	const showLargeTab = isLargeAvailable
		? [
				{
					type: 'button',
					label: <span>{ __( 'Full screen', 'quizleap' ) }</span>,
					icon: (
						<span className="quizleap-icon quizleap-icon--large-screen" />
					),
					onClick: () => toggleIcon( 0 ),
				},
		  ]
		: [];

	const showSmallTab = isSmallAvailable
		? [
				{
					type: 'button',
					label: <span>{ __( 'Small screen', 'quizleap' ) }</span>,
					icon: (
						<span className="quizleap-icon quizleap-icon--small-screen" />
					),
					onClick: () => toggleIcon( 2 ),
				},
		  ]
		: [];
	const contentLabel =
		typeof title === 'object' ? title.props.children[ 0 ] : title;

	const modalClass = suffixClassNames(
		suffix,
		devicePreview?.expandableSize
	);
	const contentClass = contentClassNames( devicePreview?.expandableSize );

	return (
		<React.Fragment>
			<Modal
				isOpen={ isOpen }
				onAfterOpen={ onAfterOpen }
				onRequestClose={ onRequestClose }
				contentLabel={ contentLabel }
				style={ modalStyle }
				shouldCloseOnOverlayClick={ false }
				ariaHideApp={ true }
			>
				<Formik { ...formik }>
					{ ( { isSubmitting, handleSubmit } ) => (
						<Form>
							<div className={ modalClass }>
								<div className="quizleap-react-modal__head">
									{ title && (
										<h3 className="quizleap-react-modal__head-title">
											{ title }
										</h3>
									) }
									<div className="quizleap-react-modal-buttons">
										{ isEnabledResizer && (
											<OptionMenu
												icon={
													iconOptions[
														devicePreview
															?.activeIconIndex
													]
												}
												options={ [
													...showLargeTab,
													{
														type: 'button',
														label: (
															<span>
																{ __(
																	'Medium screen',
																	'quizleap'
																) }
															</span>
														),
														icon: (
															<span className="quizleap-icon quizleap-icon--medium-screen" />
														),
														onClick: () =>
															toggleIcon( 1 ),
													},
													...showSmallTab,
												] }
											/>
										) }

										<CloseButton
											size="sm"
											onClick={ () => onRequestClose() }
										/>
									</div>
								</div>
								<div className={ contentClass }>
									{ children }
								</div>
								{ isEnabledFooter && (
									<div className="quizleap-react-modal__footer">
										{ cancelButtonLabel && (
											<Button
												{ ...outlineBtn }
												onClick={ () =>
													onRequestClose()
												}
											>
												{ cancelButtonLabel }
											</Button>
										) }
										<Button
											{ ...primaryBtn }
											loading={ isSubmitting }
											onClick={ handleSubmit }
										>
											{ submitButtonLabel }
										</Button>
									</div>
								) }
							</div>
						</Form>
					) }
				</Formik>
			</Modal>
		</React.Fragment>
	);
}

ReactModalFormik.propTypes = propTypes;
