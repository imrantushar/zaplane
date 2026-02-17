import React from 'react';
import SortableColumns from './SortableColumns';
import { Box, Flex } from '@chakra-ui/react';

const TableSubHeader = ({
	subHeaderComponent,
	setTempCopyColumns,
	tempCopyColumns,
	showColumnFilter,
	checkedChange,
	setCopyColumns,
	copyColumns,
	suffix,
}) => {
	return (
		<Flex className="zaplane-table__sub-header" justifyContent={'space-between'} width={'100%'}>
			<Flex justifyContent={'space-between'} className="zaplane-table__sub-header-left" width={'100%'}>
				{subHeaderComponent && subHeaderComponent}
			</Flex>

			{showColumnFilter ? (
				<Box className="zaplane-table__sub-header-right">
					<SortableColumns
						setTempCopyColumns={setTempCopyColumns}
						tempCopyColumns={tempCopyColumns}
						showColumnFilter={showColumnFilter}
						checkedChange={checkedChange}
						setCopyColumns={setCopyColumns}
						copyColumns={copyColumns}
						suffix={suffix}
					/>
				</Box>
			) : (
				null
			)}
		</Flex>
	);
};

export default TableSubHeader;
