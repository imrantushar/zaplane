import React, { useState, useEffect, useRef } from 'react';
import { __ } from '@wordpress/i18n';
import TableSubHeader from './TableSubHeader';
import TableHeader from './TableHeader';
import TableBody from './TableBody';
import TableFooter from './TableFooter';
import _ from 'lodash';

import CustomTableMessage from '@ZAPComponents/Oops/CustomTableMessage';
import TableSkeleton from '@ZAPComponents/ZaplaneLoader/TableSkeleton';
const ListTable = props => {
  const {
    columns = [],
    data = [],
    isRowSelectable = true,
    getSelectRowValue,
    showSubHeader = false,
    subHeaderComponent,
    showColumnFilter = true,
    showPagination = false,
    onChangePage,
    onChangeItemsPerPage,
    suffix = '',
    noDataText = __('Please, create data to see the available list here.', 'zaplane'),
    totalItems = 0,
    dataFetchingStatus = false,
    resetSelected = false,
    currentPageNumber = 1,
    rowsPerPage = 10,
    Button = false,
    hoverAction = false,
    interactive = true,
    striped = false,
    showColumnBorder = false
  } = props;
  const bodyRef = useRef(null);

  const [loadingHeight, setLoadingHeight] = useState('0px');
  const [copyDataArr, setCopyDataArr] = useState([]);
  const [copyColumns, setCopyColumns] = useState(columns?.map((copyColumn, index) => ({
    ...copyColumn,
    visible: true,
    id: `column-${index}`
  })));
  const shouldRerender = _.isEqual(data, copyDataArr);
  const [visibleColumn, setVisibleColumn] = useState(copyColumns?.filter(copyColumn => copyColumn.visible));
  const [tempCopyColumns, setTempCopyColumns] = useState([...copyColumns]);
  const [showSlider, setShowSlider] = useState(false);
  const isCheckboxColumnVisible = visibleColumn.length > 0 && isRowSelectable;
  const selectRowChange = ({
    row,
    select
  }) => {
    const updatedDataArr = copyDataArr.map(dataItem => {
      if (dataItem.rowId === row.rowId) {
        return {
          ...dataItem,
          select
        };
      }
      return dataItem;
    });
    setCopyDataArr(updatedDataArr);
  };
  const selectAllRow = changes => setCopyDataArr(prev => prev.map(prevData => ({
    ...prevData,
    select: changes
  })));
  const checkedChange = ({
    id,
    visible
  }) => {
    let updatedColumns;
    if (id === 'reset') {
      updatedColumns = tempCopyColumns.map(column => ({
        ...column,
        visible: true
      }));
    } else {
      updatedColumns = tempCopyColumns.map(column => {
        if (column.id === id) {
          return {
            ...column,
            visible
          };
        }
        return column;
      });
    }
    setTempCopyColumns(updatedColumns);
  };
  const paginationPerPageChange = option => {
    setLoadingHeight(`${bodyRef.current.offsetHeight}px`);
    onChangeItemsPerPage(Number(option.value), 1);
  };

  // Reset copyDataArr if shouldRerender is false
  useEffect(() => {
    if (!shouldRerender) {
      setCopyDataArr(data?.map((row, index) => ({
        ...row,
        rowId: `row-${index}`,
        select: false
      })));
    }
  }, [shouldRerender, data]);

  //side effect
  useEffect(() => {
    setCopyDataArr(data && data?.map((row, index) => ({
      ...row,
      rowId: `row-${index}`,
      select: false
    })));
  }, [data?.length]);
  useEffect(() => {
    if (0 === data.length || false === data) {
      setLoadingHeight('400px');
    } else if (bodyRef.current.offsetHeight < 100) {
      setLoadingHeight(`100px`);
    } else {
      setLoadingHeight(`${bodyRef.current.offsetHeight}px`);
    }
  }, [data?.length, bodyRef, rowsPerPage]);
  useEffect(() => {
    const visibleColumns = copyColumns.filter(item => item.visible === true);
    function handleResponsiveSlideShow() {
      if (window.innerWidth < 1280 || visibleColumns.length > 6) {
        setShowSlider(true);
      } else {
        setShowSlider(false);
      }
    }

    // Add event listener for window resize
    window.addEventListener('resize', handleResponsiveSlideShow);

    // Initial check on component mount
    handleResponsiveSlideShow();

    // Clean up the event listener when the component unmounts
    return () => {
      window.removeEventListener('resize', handleResponsiveSlideShow);
    };
  }, [window.innerWidth, copyColumns]);
  useEffect(() => {
    setVisibleColumn(copyColumns?.filter(copyColumn => copyColumn.visible));
    setLoadingHeight(false);
  }, [copyColumns]);

  // get Local storage data every time page refresh
  useEffect(() => {
    const localColumns = JSON.parse(localStorage.getItem(suffix));
    if (localColumns) {
      const mergeColumns = copyColumns.reduce((acc, column) => {
        localColumns.forEach(item => {
          if (item.id === column.id) {
            acc.push({
              ...item,
              cell: column.cell
            });
          }
        });
        return acc;
      }, []);
      const sortColumn = localColumns.reduce((acc, column) => {
        mergeColumns.forEach(item => {
          if (column.id === item.id) {
            acc.push({
              ...item
            });
          }
        });
        return acc;
      }, []);
      setTempCopyColumns(sortColumn);
      setCopyColumns(sortColumn);
    }
  }, []);

  // current select row
  useEffect(() => {
    if (typeof getSelectRowValue === 'function') {
      getSelectRowValue(copyDataArr && copyDataArr?.filter(copyRow => copyRow.select));
    }
  }, [copyDataArr]);

  // reset selected
  useEffect(() => {
    if (resetSelected && copyDataArr) {
      setCopyDataArr(copyDataArr && copyDataArr?.map(row => ({
        ...row,
        select: false
      })));
    }
  }, [resetSelected]);
  const showPaginationData = showPagination && copyDataArr?.length > 0;
  const classes = ['zaplane-table', suffix && 'zaplane-table--' + suffix].filter(Boolean).join(" ");
  const isLoading = dataFetchingStatus;
  return <div className={classes}>
			{showSubHeader && <TableSubHeader subHeaderComponent={subHeaderComponent} setTempCopyColumns={setTempCopyColumns} tempCopyColumns={tempCopyColumns} showColumnFilter={showColumnFilter} checkedChange={checkedChange} setCopyColumns={setCopyColumns} copyColumns={copyColumns} suffix={suffix} />}

			{isLoading ? <div style={{minHeight: loadingHeight}} className="flex items-center justify-center w-full" ref={bodyRef}>
					<TableSkeleton />
				</div> : <>
					{copyDataArr.length === 0 ? <>
							<table className="min-w-full">
								<TableHeader data={data} visibleColumn={visibleColumn} copyDataArr={copyDataArr} selectAllRow={selectAllRow} isCheckboxColumnVisible={isCheckboxColumnVisible} />
							</table>
		
							<div ref={bodyRef}>
								<CustomTableMessage title={__('No Data Available!!!', 'zaplane')} subText={noDataText} />
							</div>
						</> : <div className="overflow-x-auto">
							<table className="min-w-full border-collapse">
								<TableHeader data={data} visibleColumn={visibleColumn} copyDataArr={copyDataArr} selectAllRow={selectAllRow} isCheckboxColumnVisible={isCheckboxColumnVisible} />
		
								<TableBody dataFetchingStatus={dataFetchingStatus} copyDataArr={copyDataArr} visibleColumn={visibleColumn} isCheckboxColumnVisible={isCheckboxColumnVisible} selectRowChange={selectRowChange} noDataText={noDataText} button={Button} hoverAction={hoverAction} loadingHeight={loadingHeight} bodyRef={bodyRef} />
							</table>
						</div>}
				</>}

			{showPaginationData && <TableFooter data={data} totalItems={totalItems} paginationPerPageChange={paginationPerPageChange} rowsPerPage={rowsPerPage} onChangePage={onChangePage} currentPageNumber={currentPageNumber} />}
		</div>;
};
export default ListTable;