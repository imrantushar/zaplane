import React, { useEffect, useState } from 'react';
import { HiChevronDoubleLeft, HiChevronDoubleRight } from 'react-icons/hi';
import { IoIosArrowBack, IoIosArrowForward } from 'react-icons/io';
import { __, sprintf } from '@wordpress/i18n';
import './styles.scss';

const Pagination = ({
  totalItems = 0,
  currentPageNumber = 1,
  fetchHandler = () => {},
  rowsPerPage = 10
}) => {
  const totalPages = Math.ceil(totalItems / rowsPerPage) || 0;
  const safeCurrentPage = Math.min(
    Math.max(Number(currentPageNumber) || 1, 1),
    totalPages || 1
  );
  const [page, setPage] = useState(safeCurrentPage);

  useEffect(() => {
    setPage(safeCurrentPage);
  }, [safeCurrentPage]);

  const handlePageChange = newPage => {
    if (!totalPages) {
      return;
    }
    const nextPage = Math.min(Math.max(newPage, 1), totalPages);
    setPage(nextPage);
    fetchHandler(nextPage, rowsPerPage);
  };
  const PageButton = ({ pageNumber }) => (
    <li>
      <button
        type="button"
        className={`zaplane-pagination-list__item ${pageNumber === page ? 'zaplane-pagination-list__item-active' : ''}`}
        aria-label={sprintf(
          // translators: %d: page number.
          __("Page %d", "zaplane"),
          pageNumber
        )}
        aria-current={pageNumber === page ? "page" : undefined}
        onClick={() => handlePageChange(pageNumber)}
      >
        {pageNumber}
      </button>
    </li>
  );

  const Dots = ({ id }) => (
    <li key={id} aria-hidden="true">
      <span className="zaplane-pagination-list__item zaplane-pagination-list__item-dots">...</span>
    </li>
  );

  const renderPageNumbers = () => {
    if (totalPages <= 5) {
      return Array.from({ length: totalPages }, (_, index) => (
        <PageButton key={index + 1} pageNumber={index + 1} />
      ));
    }

    const pages = [];
    if (page <= 3) {
      [1, 2, 3].forEach(pageNumber => pages.push(
        <PageButton key={pageNumber} pageNumber={pageNumber} />
      ));
      pages.push(<Dots key="dots-end" id="dots-end" />);
      pages.push(<PageButton key={totalPages} pageNumber={totalPages} />);
    } else if (page >= totalPages - 2) {
      pages.push(<PageButton key={1} pageNumber={1} />);
      pages.push(<Dots key="dots-start" id="dots-start" />);
      [totalPages - 2, totalPages - 1, totalPages].forEach(pageNumber => pages.push(
        <PageButton key={pageNumber} pageNumber={pageNumber} />
      ));
    } else {
      pages.push(<PageButton key={1} pageNumber={1} />);
      pages.push(<Dots key="dots-start" id="dots-start" />);
      [page - 1, page, page + 1].forEach(pageNumber => pages.push(
        <PageButton key={pageNumber} pageNumber={pageNumber} />
      ));
      pages.push(<Dots key="dots-end" id="dots-end" />);
      pages.push(<PageButton key={totalPages} pageNumber={totalPages} />);
    }
    return pages;
  };

  if (!totalPages) {
    return null;
  }

  const canGoPrev = page > 1;
  const canGoNext = page < totalPages;

  return (
    <nav className="zaplane-pagination" aria-label={__("Pagination", "zaplane")}>
      <button type="button" disabled={!canGoPrev} aria-label={__("First page", "zaplane")} onClick={() => handlePageChange(1)}>
        <HiChevronDoubleLeft aria-hidden="true" style={{ width: "16px", height: "16px" }} />
      </button>
      <button type="button" disabled={!canGoPrev} aria-label={__("Previous page", "zaplane")} onClick={() => handlePageChange(page - 1)}>
        <IoIosArrowBack aria-hidden="true" style={{ width: "16px", height: "16px" }} />
      </button>
      <ul className="zaplane-pagination-list">
        {renderPageNumbers()}
      </ul>
      <button type="button" disabled={!canGoNext} aria-label={__("Next page", "zaplane")} onClick={() => handlePageChange(page + 1)}>
        <IoIosArrowForward aria-hidden="true" style={{ width: "16px", height: "16px" }} />
      </button>
      <button type="button" disabled={!canGoNext} aria-label={__("Last page", "zaplane")} onClick={() => handlePageChange(totalPages)}>
        <HiChevronDoubleRight aria-hidden="true" style={{ width: "16px", height: "16px" }} />
      </button>
    </nav>
  );
};

export default Pagination;
