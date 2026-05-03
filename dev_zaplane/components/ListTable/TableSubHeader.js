import React from 'react';
import SortableColumns from './SortableColumns';
const TableSubHeader = ({
  subHeaderComponent,
  setTempCopyColumns,
  tempCopyColumns,
  showColumnFilter,
  checkedChange,
  setCopyColumns,
  copyColumns,
  suffix
}) => {
  return <div width={'100%'} className="zaplane-table__sub-header flex justify-between">
			<div width={'100%'} className="zaplane-table__sub-header-left flex justify-between">
				{subHeaderComponent && subHeaderComponent}
			</div>

			{showColumnFilter ? <div>
					<SortableColumns setTempCopyColumns={setTempCopyColumns} tempCopyColumns={tempCopyColumns} showColumnFilter={showColumnFilter} checkedChange={checkedChange} setCopyColumns={setCopyColumns} copyColumns={copyColumns} suffix={suffix} />
				</div> : null}
		</div>;
};
export default TableSubHeader;