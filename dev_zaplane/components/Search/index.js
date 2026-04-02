import React, { useCallback, useState } from 'react';
import './styles.scss';
import { CiSearch } from "react-icons/ci";
import { Icon } from '@chakra-ui/react';
import { reactDebounce } from '@ZAPUtils/helper';



export default function Search( {
	placeholder = 'Search...',
	defaultValue = '',
	onSearchHandler = () => {},
	custom = '',
} ) {
	const [ searchText, setSearchText ] = useState( defaultValue );

	const classNames = [ 'zaplane-search-component', custom && `${ custom }` ]
		.filter( Boolean )
		.join( ' ' );

	const debouncedAPICall = useCallback(
		reactDebounce( ( keyword ) => {
			onSearchHandler( keyword );
		}, 1000 ),
		[ onSearchHandler ]
	);

	const searchHandler = ( searchValue ) => {
		setSearchText( searchValue );
		debouncedAPICall( searchValue );
	};

	const handleClear = () => {
		if ( searchText ) {
			setSearchText( '' );
			onSearchHandler( '' );
		}
	};

	return (
		<React.Fragment>
			<div className={ classNames }>
				<span className="zaplane-search-component__search zaplane-icon zaplane-icon--search" >
					<Icon as={CiSearch}></Icon>
					</span>
				<input
					id="search"
					type="text"
					className="zaplane-search-component__input zaplane-input"
					placeholder={ placeholder }
					value={ searchText }
					onChange={ ( e ) => searchHandler( e.target.value ) }
				/>
				{ searchText && (
					<button
						onClick={ handleClear }
						className="zaplane-search-component__control"
					>
						<span className="zaplane-icon zaplane-icon--close-small"></span>
					</button>
				) }
			</div>
		</React.Fragment>
	);
}


