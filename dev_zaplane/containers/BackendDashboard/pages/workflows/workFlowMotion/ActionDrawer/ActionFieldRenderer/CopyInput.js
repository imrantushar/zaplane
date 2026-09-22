import React from 'react';
import { useDispatch } from 'react-redux';
import { __ } from '@wordpress/i18n';
import { Copy } from 'lucide-react';
import { showNotification } from '@ZAPRedux/Slices/notificationSlice/notificationSlice';

const CopyInput = ( { label, value, help } ) => {
	const dispatch = useDispatch();

	const handleCopy = async ( e ) => {
		e.preventDefault();
		const text = value || '';

		try {
			if ( window.navigator.clipboard?.writeText ) {
				await window.navigator.clipboard.writeText( text );
			} else {
				const textarea = document.createElement( 'textarea' );
				textarea.value = text;
				textarea.setAttribute( 'readonly', '' );
				textarea.style.position = 'fixed';
				textarea.style.opacity = '0';
				document.body.appendChild( textarea );
				textarea.select();
				const copied = document.execCommand( 'copy' );
				document.body.removeChild( textarea );

				if ( ! copied ) {
					throw new Error( 'Clipboard copy command was rejected.' );
				}
			}

			dispatch(
				showNotification( {
					message: __( 'Copied to clipboard!', 'zaplane' ),
					isShow: true,
					type: 'success',
				} )
			);
		} catch {
			dispatch(
				showNotification( {
					message: __(
						'Copy failed. Please select and copy the URL manually.',
						'zaplane'
					),
					isShow: true,
					type: 'error',
				} )
			);
		}
	};

	return (
		<div className="flex flex-col gap-2">
			{ label && <span className="zaplane-label">{ label }</span> }
			<div className="flex items-stretch overflow-hidden">
				<input
					type="text"
					className="zaplane-input "
					value={ value || '' }
					readOnly
				/>
				<button
					type="button"
					onClick={ handleCopy }
					className="px-3 bg-[var(--zaplane-background)] !border-y-0 !border-r-0 !border-l border-solid border-[var(--zaplane-border-color)] cursor-pointer flex items-center justify-center hover:bg-[var(--zaplane-secondary-color)] transition-colors"
					title={ __( 'Copy to clipboard', 'zaplane' ) }
				>
					<Copy
						size={ 16 }
						className="text-[var(--zaplane-font-secondary-color)]"
					/>
				</button>
			</div>
			{ help && (
				<p className="text-[var(--zaplane-font-secondary-color)] text-xs mt-1">
					{ help }
				</p>
			) }
		</div>
	);
};

export default CopyInput;
