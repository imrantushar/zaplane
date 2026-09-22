import React, { useState, useEffect } from 'react';
import { HiChevronDoubleLeft, HiChevronDoubleRight } from 'react-icons/hi';
import { IoIosArrowBack, IoIosArrowForward } from 'react-icons/io';
import './styles.scss';
const Pagination = ({
  totalItems = 0,
  currentPageNumber = 1,
  fetchHandler = () => {},
  rowsPerPage = 10
}) => {
  const [page, setPage] = useState(currentPageNumber ? currentPageNumber : 1);
  const totalPages = Math.ceil(totalItems / rowsPerPage) || 0;
  const safeCurrentPage = Math.min(
    Math.max(Number(currentPageNumber) || 1, 1),
    totalPages || 1
  );
  const pageNumbers = Array.from({
    length: totalPages
  }, (_, index) => index + 1);
  const canGoNext = page < pageNumbers.length;
  const canGoPrev = page > 1;
  useEffect(() => {
    setPage(safeCurrentPage);
  }, [safeCurrentPage]);
  const handlePageChange = newPage => {
    setPage(newPage);
    fetchHandler(newPage, rowsPerPage);
  };
  const renderPageNumbers = () => {
    const renderedPages = [];
    if (pageNumbers.length <= 5) {
      // when total number of pages is less than or equal to 5, render all the page numbers
      pageNumbers.forEach((item, index) => {
        const pageItem = <li className={`zaplane-pagination-list__item ${item === page ? 'zaplane-pagination-list__item-active' : ''}`} key={index} onClick={() => {
          handlePageChange(item);
        }} role="presentation">
						{item}
					</li>;
        renderedPages.push(pageItem);
      });
    } else if (page <= 3) {
      // when current page is within the first three pages
      for (let i = 1; i <= 3; i++) {
        const pageItem = <li className={`zaplane-pagination-list__item ${i === page ? 'zaplane-pagination-list__item-active' : ''}`} key={i} onClick={() => {
          handlePageChange(i);
        }} role="presentation">
						{i}
					</li>;
        renderedPages.push(pageItem);
      }
      renderedPages.push(<li className="zaplane-pagination-list__item zaplane-pagination-list__item-dots" key="dots">
					...
				</li>);
      const lastPageItem = <li className="zaplane-pagination-list__item" key={totalPages} onClick={() => {
        handlePageChange(totalPages);
      }} role="presentation">
					{totalPages}
				</li>;
      renderedPages.push(lastPageItem);
    } else if (page >= totalPages - 2) {
      // when current page is within the last three pages
      const firstPageItem = <li className="zaplane-pagination-list__item" key={1} onClick={() => {
        handlePageChange(1);
      }} role="presentation">
					{1}
				</li>;
      renderedPages.push(firstPageItem);
      renderedPages.push(<li className="zaplane-pagination-list__item zaplane-pagination-list__item-dots" key="dots">
					...
				</li>);
      for (let i = totalPages - 2; i <= totalPages; i++) {
        const pageItem = <li className={`zaplane-pagination-list__item ${i === page ? 'zaplane-pagination-list__item-active' : ''}`} key={i} onClick={() => {
          handlePageChange(i);
        }} role="presentation">
						{i}
					</li>;
        renderedPages.push(pageItem);
      }
    } else {
      // If the current page is in the middle range
      const firstPageItem = <li className="zaplane-pagination-list__item" key={1} onClick={() => {
        handlePageChange(1);
      }} role="presentation">
					{1}
				</li>;
      renderedPages.push(firstPageItem);
      renderedPages.push(<li className="zaplane-pagination-list__item zaplane-pagination-list__item-dots" key="dots-start">
					...
				</li>);
      for (let i = page - 1; i <= page + 1; i++) {
        const pageItem = <li className={`zaplane-pagination-list__item ${i === page ? 'zaplane-pagination-list__item-active' : ''}`} key={i} onClick={() => {
          handlePageChange(i);
        }} role="presentation">
						{i}
					</li>;
        renderedPages.push(pageItem);
      }
      renderedPages.push(<li className="zaplane-pagination-list__item zaplane-pagination-list__item-dots" key="dots-end">
					...
				</li>);
      const lastPageItem = <li className="zaplane-pagination-list__item" key={totalPages} onClick={() => {
        handlePageChange(totalPages);
      }} role="presentation">
					{totalPages}
				</li>;
      renderedPages.push(lastPageItem);
    }
    return renderedPages;
  };
  return <div className="zaplane-pagination">
			{pageNumbers.length > 0 && <>
					<button disabled={!canGoPrev} aria-label="First page" borderWidth="1px" _hover={{
        bg: 'var(--zaplane-secondary-color)'
      }} height="auto" minWidth="auto" padding="6px" onClick={() => {
        handlePageChange(1);
      }} className="text-[var(--zaplane-font-color)] border-[var(--zaplane-border-color)] bg-transparent disabled:opacity-40 disabled:cursor-not-allowed">
						<IoIosArrowBack style={{width:"16px", height:"16px"}} />
					</button>
					<button disabled={!canGoPrev} aria-label="Previous page" borderWidth="1px" _hover={{
        bg: 'var(--zaplane-secondary-color)'
      }} height="auto" minWidth="auto" padding="6px" onClick={() => {
        handlePageChange(page - 1);
      }} className="text-[var(--zaplane-font-color)] border-[var(--zaplane-border-color)] bg-transparent disabled:opacity-40 disabled:cursor-not-allowed">
						<HiChevronDoubleLeft style={{width:"16px", height:"16px"}} />
					</button>
				</>}
			{pageNumbers.length > 0 && <ul className="zaplane-pagination-list">
					{renderPageNumbers()}
				</ul>}
			{pageNumbers.length > 0 && <>
					<button disabled={!canGoNext} aria-label="Next page" borderWidth="1px" _hover={{
        bg: 'var(--zaplane-secondary-color)'
      }} height="auto" minWidth="auto" padding="6px" onClick={() => {
        handlePageChange(page + 1);
      }} className="text-[var(--zaplane-font-color)] border-[var(--zaplane-border-color)] bg-transparent disabled:opacity-40 disabled:cursor-not-allowed">
						<IoIosArrowForward style={{width:"16px", height:"16px"}} />
					</button>
					<button disabled={!canGoNext} aria-label="Last page" borderWidth="1px" _hover={{
        bg: 'var(--zaplane-secondary-color)'
      }} height="auto" minWidth="auto" padding="6px" onClick={() => {
        handlePageChange(pageNumbers.length);
      }} className="text-[var(--zaplane-font-color)] border-[var(--zaplane-border-color)] bg-transparent disabled:opacity-40 disabled:cursor-not-allowed">
						<HiChevronDoubleRight style={{width:"16px", height:"16px"}} />
					</button>
				</>}
		</div>;
};
export default Pagination;