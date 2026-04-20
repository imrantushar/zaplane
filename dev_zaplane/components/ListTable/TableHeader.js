import React from 'react';


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
		<thead style={{ background: 'var(--zaplane-secondary-color)' }}>
			<tr>
				{isCheckboxColumnVisible && (
					<th style={{ width: '40px' }} className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap border-b border-gray-200">
						<input 
							type="checkbox" 
							className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-600"
							checked={isCheckboxChecked}
							onChange={(e) => selectAllRow(e.target.checked)}
						/>	
					</th>
				)}

				{visibleColumn?.map((column, index) => (
					<th 
						key={index}
						style={{
							minWidth: column?.columnWidth || 'auto',
							textAlign: column?.textAlign || 'center',
							whiteSpace: 'nowrap',
						}}
						className="px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider border-b border-gray-200"
					>
						{column?.name}
					</th>
				))}
			</tr>
		</thead>
	);
};

export default TableHeader;
