import React, { useCallback, useState } from 'react';
import './styles.scss';
import { CiSearch } from "react-icons/ci";
import { reactDebounce } from '@ZAPUtils/helper';
export default function Search({
  placeholder = 'Search...',
  defaultValue = '',
  onSearchHandler = () => {},
  custom = '',
  // Instant by default — searches that filter an in-memory list shouldn't lag.
  // Pass a delay (ms) only when each change triggers a network request.
  debounce = 0  
}) {
  const [searchText, setSearchText] = useState(defaultValue);
  const classNames = ['zaplane-search-component', custom && `${custom}`].filter(Boolean).join(' ');
  const emitSearch = useCallback(
    debounce > 0 ? reactDebounce(keyword => onSearchHandler(keyword), debounce) : keyword => onSearchHandler(keyword),
    [onSearchHandler, debounce]
  );
  const searchHandler = searchValue => {
    setSearchText(searchValue);
    emitSearch(searchValue);
  };
  const handleClear = () => {
    if (searchText) {
      setSearchText('');
      onSearchHandler('');
    }
  };
  return <React.Fragment>
			<div className={classNames}>
				<span className="zaplane-search-component__search zaplane-icon zaplane-icon--search">
					<CiSearch />
					</span>
				<input id="search" type="text" className="zaplane-search-component__input zaplane-input" placeholder={placeholder} value={searchText} onChange={e => searchHandler(e.target.value)} />
				{searchText && <button onClick={handleClear} className="zaplane-search-component__control">
						<span className="zaplane-icon zaplane-icon--close-small"></span>
					</button>}
			</div>
		</React.Fragment>;
}