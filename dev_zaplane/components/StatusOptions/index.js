import React, { useEffect, useRef, useState } from 'react';
import PropTypes from 'prop-types';
import { __ } from '@wordpress/i18n';
import { createPortal } from 'react-dom';
import './styles.scss';
import { Icon } from '@chakra-ui/react';
import { FaAngleDown } from 'react-icons/fa6';



const StatusOptions = ( props ) => {
	const {
		value = 'pending',
		options = {},
		onChangeHandler = () => {},
		suffix = '',
	} = props;

	const [ isOpenDropdown, setOpenDropdown ] = useState( false );

	const statusLabel = {
		active: __( 'Active', 'zaplane' ),
		paused: __( 'Paused', 'zaplane' ),
		draft: __( 'Draft', 'zaplane' ),
		inactive: __('inactive', 'zaplane' ),
		trash: __( 'Trash', 'zaplane' ),
		completed: __( 'Approved', 'zaplane' ),
		approved: __( 'Approve', 'zaplane' ),
		processing: __( 'Processing', 'zaplane' ),
		cancel: __( 'Cancel', 'zaplane' ),
		private: __( 'Private', 'zaplane' ),
		future: __( 'Scheduled', 'zaplane' ),
	};

	const menuItemRef = useRef( null );
	const relativeTo = useRef( null );

	const handleClick = ( e ) => {
		if (
			menuItemRef?.current &&
			! menuItemRef?.current?.contains( e.target )
		) {
			setOpenDropdown( false );
		}
	};

	const handleMenuToggle = () => {
		setOpenDropdown( ! isOpenDropdown );
	};

	useEffect( () => {
		document.addEventListener( 'mousedown', handleClick );
		return () => document.removeEventListener( 'mousedown', handleClick );
	}, [] );

	useEffect( () => {
		if ( isOpenDropdown && relativeTo.current ) {
			const rect = relativeTo.current.getBoundingClientRect();
			const x = rect.left + window.pageXOffset;
			const y = rect.top + window.pageYOffset;
			const buttonHeight = relativeTo.current.offsetHeight;
			menuItemRef.current.style.position = 'absolute';
			menuItemRef.current.style.left = `${ x - 50 }px`;
			menuItemRef.current.style.top = `${ y + buttonHeight - 25 }px`;
			document.body.appendChild( menuItemRef.current );
		} else if (
			relativeTo.current &&
			relativeTo.current.parentNode === document.body
		) {
			document.body.removeChild( menuItemRef.current );
		}
	}, [ isOpenDropdown, relativeTo ] );

	return (
		<>
			<button
				className={ `zaplane-dropdown-option zaplane-dropdown-option--${ value } 
				${ suffix && 'zaplane-dropdown-option--' + suffix }` }
				type="button"
				ref={ relativeTo }
				onClick={ handleMenuToggle }
			>
				<span className="label">{ statusLabel[ value ] }</span>
				<span
					className={ `zaplane-icon zaplane-icon--arrow-down` }
				>
					<Icon as={FaAngleDown} />
				</span>
			</button>
			{ createPortal(
				<div
					className="zaplane-dropdown-option__lists"
					ref={ menuItemRef }
				>
					{ isOpenDropdown && (
						<ul className="zaplane-more-status-options">
							{ options.items.map( ( item, index ) => (
								<li
									className={ `zaplane-more-status-options__item zaplane-more-status-options__item--${
										item.value
									} ${ item.value === value && 'active' }` }
									role="presentation"
									onClick={ () => {
										onChangeHandler( item.value );
										setOpenDropdown( false );
									} }
									key={ index }
								>
									{ item.label }
								</li>
							) ) }
						</ul>
					) }
				</div>,
				document.body
			) }
		</>
	);
};


export default StatusOptions;
