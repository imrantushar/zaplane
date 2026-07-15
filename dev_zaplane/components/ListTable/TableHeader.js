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
					<th style={{ width: '40px' }} className="px-4 py-3 zaplane-label border-[var(--zaplane-border-color)]">
						<input 
							type="checkbox" 
							className="h-4 w-4 rounded border-[var(--zaplane-border-color)]"
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
						className="px-4 py-3 zaplane-label"
					>
						{column?.name}
					</th>
				))}
			</tr>
		</thead>
	);
};

export default TableHeader;
