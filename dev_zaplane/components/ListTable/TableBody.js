import React from 'react';
import { __ } from '@wordpress/i18n';


const TableBody = ({
	copyDataArr,
	visibleColumn,
	isCheckboxColumnVisible,
	selectRowChange,
	bodyRef,
}) => {
	return (
		<tbody ref={bodyRef}>
			{copyDataArr.length > 0 && copyDataArr.map((row, rowIndex) => (
				<tr
					key={rowIndex}
					role="group"
					className="border-b border-[var(--zaplane-border-color)] hover:bg-[var(--zaplane-secondary-color)] transition-colors"
				>
					{isCheckboxColumnVisible && (
						<td style={{ width: '40px' }} className="px-4 py-3">
							<input 
								type="checkbox" 
								className="h-4 w-4 rounded border-[var(--zaplane-border-color)] text-blue-600 focus:ring-blue-600"
								checked={row.select}
								onChange={(e) =>
									selectRowChange({
										row,
										select: e.target.checked,
									})
								}
							/>
						</td>
					)}

					{visibleColumn.map(
						(column, columnIndex) => (
							<td
								key={columnIndex}
								style={{
									textAlign: column?.textAlign || 'center',
									width: column?.width,
								}}
								className="px-4 py-3 text-sm"
							>
								{column?.cell(row, rowIndex)}
							</td>
						)
					)}
				</tr>
			))}
		</tbody>
	);
};

export default TableBody;
