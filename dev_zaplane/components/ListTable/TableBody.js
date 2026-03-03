import React from 'react';
import { __ } from '@wordpress/i18n';
import { Table, Checkbox } from '@chakra-ui/react';

const TableBody = ({
	copyDataArr,
	visibleColumn,
	isCheckboxColumnVisible,
	selectRowChange,
	bodyRef,
}) => {
	return (
		<Table.Body ref={bodyRef}>
			{copyDataArr.length > 0 && copyDataArr.map((row, rowIndex) => (
				<Table.Row
					key={rowIndex}
					role="group"
					borderBottomWidth="1px" borderColor="var(--zaplane-border-color)"
				>
					{isCheckboxColumnVisible && (
						<Table.Cell width="40px">
							<Checkbox.Root
								size="sm"
								mt="0.5"
								aria-label="Select row"
								checked={row.select}
								onCheckedChange={(changes) =>
									selectRowChange({
										row,
										select: changes.checked,
									})
								}
							>
								<Checkbox.HiddenInput />
								<Checkbox.Control />
							</Checkbox.Root>
						</Table.Cell>
					)}

					{visibleColumn.map(
						(column, columnIndex) => (
							<Table.Cell
								key={columnIndex}
								position="relative"
								textAlign={column?.textAlign ? column?.textAlign : "center"}
								width={column?.width}
							>
								{column?.cell(row, rowIndex)}
							</Table.Cell>
						)
					)}
				</Table.Row>
			))}
		</Table.Body>
	);
};

export default TableBody;
