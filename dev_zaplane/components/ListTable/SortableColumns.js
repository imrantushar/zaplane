import React, { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
// import { sortableContainer, sortableElement } from 'react-sortable-hoc';
// import { arrayMoveImmutable } from 'array-move';
// import ReactModal from '@Components/Modal/ReactModal';

// const SortableColumnItem = sortableElement(
// 	( { copyColumn, itemIndex, checkedChange } ) => {
// 		return (
// 			<div
// 				className="zaplane-table__filter-checked-item"
// 				key={ `column-${ itemIndex }` }
// 			>
// 				<span className="zaplane-table-filter-item-left">
// 					<span className="zaplane-icon zaplane-icon--move" />
// 					<span className="zaplane-table-filter-item-title">
// 						{ copyColumn.name }
// 					</span>
// 				</span>
// 				<input
// 					id={ copyColumn.name }
// 					type="checkbox"
// 					checked={ copyColumn.visible }
// 					onChange={ ( event ) =>
// 						checkedChange( {
// 							id: copyColumn.id,
// 							visible: event.target.checked,
// 						} )
// 					}
// 				/>
// 			</div>
// 		);
// 	}
// );

// const SortableColumnItemContainer = sortableContainer( ( props ) => {
// 	return (
// 		<ul className="zaplane-table__filter-items">{ props.children }</ul>
// 	);
// } );

const SortableColumns = ({
  setTempCopyColumns,
  tempCopyColumns,
  showColumnFilter,
  checkedChange,
  setCopyColumns,
  copyColumns,
  suffix
}) => {
  const [modalOpen, isModalOpen] = useState(false);
  const [defaultColumns, setDefaultColumns] = useState([]);
  useEffect(() => {
    setDefaultColumns([...tempCopyColumns]);
  }, []);
  const onRequestClose = () => {
    isModalOpen(false);
  };

  // const onSortEnd = ( { oldIndex, newIndex } ) => {
  // 	const sortedColumn = arrayMoveImmutable(
  // 		tempCopyColumns,
  // 		oldIndex,
  // 		newIndex
  // 	);
  // 	setTempCopyColumns( sortedColumn );
  // };

  const handleToggle = () => {
    isModalOpen(!modalOpen);
  };
  const handleReset = () => {
    setTempCopyColumns([...defaultColumns]);
  };
  return <React.Fragment>
			<div className="zaplane-table__sub-header-filter-icon">
				<button onClick={handleToggle} className="border text-[var(--zaplane-font-color)] border-[var(--zaplane-border-color)] bg-transparent hover:bg-[var(--zaplane-secondary-color)]">
					<span className="zaplane-icon zaplane-icon--columns" />
					{__('Columns', 'zaplane')}
				</button>
			</div>

			{/* <ReactModal
    isOpen={ modalOpen }
    onRequestClose={ onRequestClose }
    title={ __( 'Columns', 'zaplane' ) }
    suffix="email"
    size="medium"
    >
    <Box
    	minWidth="520px"
    	p="16px"
    	className="zaplane-table__filter-modal"
    >
    	<Flex
    		justifyContent="space-between"
    		alignItems="center"
    		gap="2"
    	>
    		<Text
    			color="var(--zaplane-font-color)"
    			fontSize="sm"
    			fontWeight="medium"
    		>
    			{ __( 'Columns', 'zaplane' ) }
    		</Text>
    			<Button
    			onClick={ handleReset }
    			bg="transparent"
    			padding="0"
    			height="auto"
    			color="var(--zaplane-font-color)"
    		>
    			{ __( 'Reset', 'zaplane' ) }
    		</Button>
    	</Flex>
    		{ showColumnFilter && (
    		<SortableColumnItemContainer onSortEnd={ onSortEnd }>
    			{ tempCopyColumns?.map( ( item, index ) => (
    				<SortableColumnItem
    					key={ `column-${ index }` }
    					index={ index }
    					copyColumn={ item }
    					itemIndex={ index }
    					checkedChange={ checkedChange }
    				/>
    			) ) }
    		</SortableColumnItemContainer>
    	) }
    		<Flex justifyContent="flex-end" gap="2">
    		<Button
    			color="var(--zaplane-font-color)"
    			borderColor="var(--zaplane-border-color)"
    			borderWidth="1px"
    			bg="transparent"
    			_hover={ {
    				bg: 'var(--zaplane-secondary-color)',
    			} }
    			onClick={ () => {
    				setTempCopyColumns( [ ...copyColumns ] );
    				isModalOpen( false );
    			} }
    			type="button"
    		>
    			{ __( 'Cancel', 'zaplane' ) }
    		</Button>
    			<Button
    			bg="var(--zaplane-primary)"
    			onClick={ () => {
    				setCopyColumns( tempCopyColumns );
    				localStorage.setItem(
    					suffix,
    					JSON.stringify( tempCopyColumns )
    				);
    				isModalOpen( false );
    			} }
    		>
    			{ __( 'Apply', 'zaplane' ) }
    		</Button>
    	</Flex>
    </Box>
    </ReactModal> */}
		</React.Fragment>;
};
export default SortableColumns;