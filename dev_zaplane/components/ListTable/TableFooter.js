import React from 'react';
import { __, sprintf } from '@wordpress/i18n';

import Pagination from '@ZAPComponents/Pagination';
import Select from 'react-select';

const options = [
	{ value: '10', label: __('10', 'zaplane') },
	{ value: '15', label: __('15', 'zaplane') },
	{ value: '20', label: __('20', 'zaplane') },
	{ value: '25', label: __('25', 'zaplane') },
	{ value: '30', label: __('30', 'zaplane') },
];

const TableFooter = ({
	data,
	totalItems,
	paginationPerPageChange,
	rowsPerPage,
	onChangePage,
	currentPageNumber,
}) => {

	React.useEffect(() => {
		const selector = document.getElementById('zaplane-table-row-per-page-selector');
		if (!selector) return;
		selector.childNodes.forEach(function (element) {
			if (element.className.includes('zaplane-select__control')) {
				return element.id = 'zaplane-table-row-per-page-selector-control';
			}
			return element;
		})
		const selectorControl = document.getElementById('zaplane-table-row-per-page-selector-control');
		if (!selectorControl) return;
		selectorControl.childNodes.forEach(function (element) {
			if (element.className.includes('zaplane-select__indicators')) {
				return element.id = 'zaplane-table-row-per-page-selector-indicators';
			}
			return element;
		})
		const selectorIndicators = document.getElementById('zaplane-table-row-per-page-selector-indicators');
		if (!selectorIndicators) return;
		selectorIndicators.removeChild(selectorIndicators.childNodes[0]);
		selectorIndicators.childNodes[0].style.padding = '0 8px 0 0';
		selectorControl.childNodes[0].style.padding = '2px 0px 0px 8px';
	}, [])

	return (
		<div className="zaplane-table__footer">
			<p className="zaplane-table__footer-label">
				{ /* eslint-disable-next-line */}
				{sprintf(
					// translators: %s: totalItems
					__('Showing result %s out of %s', 'zaplane'),
					data?.length,
					totalItems
				)}
			</p>

			<div className="zaplane-table__footer-right">
				<div className="zaplane-table__footer-pagination-per-page">
					<p className="zaplane-table__footer-label">
						{__('Rows per page', 'zaplane')}
					</p>

					<Select
						id='zaplane-table-row-per-page-selector'
						menuPlacement='top'
						className='zaplane-select zaplane-select--65'
						classNamePrefix='zaplane-select'
						options={options}
						value={
							rowsPerPage
								? options.find(
									(item) =>
										Number(item.value) ===
										Number(rowsPerPage)
								)
								: options[0]
						}
						onChange={paginationPerPageChange}
					/>
				</div>

				<div className="zaplane-table__footer-pagination-pages icons">
					<Pagination
						totalItems={totalItems}
						fetchHandler={onChangePage}
						currentPageNumber={currentPageNumber}
						rowsPerPage={rowsPerPage}
					/>
				</div>
			</div>
		</div>
	);
};

export default TableFooter;
