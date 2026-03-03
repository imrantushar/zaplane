import React from 'react';
import { Table, Checkbox } from '@chakra-ui/react';

const TableHeader = ({
	visibleColumn,
	isCheckboxColumnVisible,
	copyDataArr,
	selectAllRow,
	data,
}) => {
	const isCheckboxChecked =
		data?.length > 0 &&
		copyDataArr?.length > 0 &&
		copyDataArr?.every((row) => row.select);

	return (
		<Table.Header bg="var(--zaplane-secondary-color)">
			<Table.Row>
				{isCheckboxColumnVisible && (
					<Table.ColumnHeader width="40px">
						<Checkbox.Root
							size="sm"
							mt="0.5"
							aria-label="Select row"
							checked={isCheckboxChecked}
							onCheckedChange={selectAllRow}
						>
							<Checkbox.HiddenInput />
							<Checkbox.Control />
						</Checkbox.Root>
					</Table.ColumnHeader>
				)}

				{visibleColumn?.map((column, index) => (
					<Table.ColumnHeader 
						key={index} 
						minW={column?.columnWidth ? column?.columnWidth : "auto"} 
						maxW={column?.columnWidth ? column?.columnWidth : "auto"} 
						w={column?.columnWidth ? column?.columnWidth : "auto"} 
						textAlign={column?.textAlign ? column?.textAlign : "center"}
						whiteSpace="nowrap"
					>
						{column?.name}
					</Table.ColumnHeader>
				))}
			</Table.Row>
		</Table.Header>
	);
};

export default TableHeader;
