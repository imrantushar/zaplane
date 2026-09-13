import React from 'react';
import PropTypes from 'prop-types';
import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';



const Button = ( {
	label = '',
	size = 'md',
	className = '',
	preset = 'purple',
	onClick = () => {},
	type = 'button',
	link = '#',
	isLoading = false,
	loadingLabel = null,
	icon = null,
	iconPosition = null,
	isCircle = false,
	border = null,
	isDisabled = false,
	borderRadius = null,
	style = {},
	suffix = '',
	target = '',
	id,
	prefix = '',
} ) => {
	const buttonClasses = [ `zaplane-btn zaplane-btn--${ size }` ];

	if ( preset ) {
		buttonClasses.push( 'zaplane-btn--preset-' + preset );
	}
	if ( iconPosition ) {
		buttonClasses.push( 'zaplane-btn--icon-' + iconPosition );
	}
	if ( isCircle ) {
		buttonClasses.push( 'zaplane-btn--circle' );
	}
	if ( border ) {
		buttonClasses.push( 'zaplane-btn--border-' + border );
	}
	if ( borderRadius ) {
		buttonClasses.push( 'zaplane-btn--border-' + borderRadius );
	}
	if ( isDisabled ) {
		buttonClasses.push( 'zaplane-btn--disabled' );
	}
	if ( suffix ) {
		buttonClasses.push( 'zaplane-btn--' + suffix );
	}
	if ( prefix ) {
		buttonClasses.push( 'zaplane-btn--' + prefix );
	}

	if ( 'link' === type ) {
		const targetAttribute = target ? { target } : {};
		return (
			<a
				href={ link }
				className={ className ? className : buttonClasses.join( ' ' ) }
				type={ type }
				onClick={ onClick }
				disabled={ isDisabled || isLoading }
				style={ style }
				rel="noreferrer"
				id={ id }
				{ ...targetAttribute }
			>
				{ iconPosition !== 'right' && icon }

				{ isLoading ? (
					<>
						<Spinner />
						{ loadingLabel && (
							<span className="zaplane-btn--label">
								{ loadingLabel }
							</span>
						) }
					</>
				) : (
					<>
						{ label && (
							<span className="zaplane-btn--label">
								{ label }
							</span>
						) }
					</>
				) }
				{ iconPosition === 'right' && icon }
			</a>
		);
	}

	return (
		<button
			className={ className ? className : buttonClasses.join( ' ' ) }
			type={ type }
			onClick={ onClick }
			disabled={ isDisabled || isLoading }
			style={ style }
			id={ id }
		>
			{ iconPosition !== 'right' && ! isLoading && icon }

			{ isLoading ? (
				<>
					<Spinner />
					{ loadingLabel && (
						<span className="zaplane-btn--label">
							{ loadingLabel }
						</span>
					) }
				</>
			) : (
				<>
					{ label && (
						<span className="zaplane-btn--label">{ label }</span>
					) }
				</>
			) }
			{ iconPosition === 'right' && ! isLoading && icon }
		</button>
	);
};


export default Button;
