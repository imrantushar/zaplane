import React, { useEffect, useCallback, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { useDispatch, useSelector } from 'react-redux';
import {
    fetchFeedback,
    fetchFeedbackSummary,
    setFilters,
    setPage,
} from '@ZAPRedux/Slices/feedbackSlice/feedbackSlice';
import PageLayout from '@ZAPComponents/PageLayout';
import ListTable from '@ZAPComponents/ListTable';
import ZAPLabel from '@ZAPComponents/Labels/ZAPLabel';

const STAR_COLOR = '#f5a623';

const Stars = ({ rating }) => (
    <span style={{ color: STAR_COLOR, fontSize: 16, letterSpacing: 1 }}>
        {'★'.repeat(rating)}
        <span style={{ color: '#ddd' }}>{'★'.repeat(5 - rating)}</span>
    </span>
);

const SummaryCards = () => {
    const { summary, isSummaryLoading } = useSelector((s) => s.feedback);

    if (isSummaryLoading || !summary) {
        return (
            <div className="grid gap-4 mb-6" style={{ gridTemplateColumns: 'repeat(4, 1fr)' }}>
                {[...Array(4)].map((_, i) => (
                    <div key={i} className="bg-white rounded-lg border p-4 animate-pulse">
                        <div className="h-4 bg-gray-200 rounded mb-2" />
                        <div className="h-6 bg-gray-200 rounded" />
                    </div>
                ))}
            </div>
        );
    }

    const avg = summary.average ?? 0;

    const cards = [
        { label: __('Total Feedback', 'zaplane'), value: summary.total ?? 0, color: '#6c63ff', suffix: '' },
        { label: __('Average Rating', 'zaplane'), value: avg, color: STAR_COLOR, suffix: ' / 5' },
        { label: __('5-Star Reviews', 'zaplane'), value: summary.by_star?.[5] ?? 0, color: '#22c55e', suffix: '' },
        { label: __('1–2 Star Reviews', 'zaplane'), value: (summary.by_star?.[1] ?? 0) + (summary.by_star?.[2] ?? 0), color: '#ef4444', suffix: '' },
    ];

    return (
        <div className="grid gap-4 mb-6" style={{ gridTemplateColumns: 'repeat(4, 1fr)' }}>
            {cards.map((card) => (
                <div
                    key={card.label}
                    className="bg-white rounded-lg border p-4"
                    style={{ borderLeft: `4px solid ${card.color}` }}
                >
                    <div className="text-sm text-gray-500 mb-1">{card.label}</div>
                    <div className="text-2xl font-bold" style={{ color: card.color }}>
                        {card.value}{card.suffix}
                    </div>
                </div>
            ))}
        </div>
    );
};

const RatingFilter = ({ value, onChange }) => {
    const options = [
        { label: __('All Ratings', 'zaplane'), value: 0 },
        { label: '★★★★★', value: 5 },
        { label: '★★★★', value: 4 },
        { label: '★★★', value: 3 },
        { label: '★★', value: 2 },
        { label: '★', value: 1 },
    ];

    return (
        <div className="mb-4 flex items-center gap-3">
            <span className="text-sm font-medium text-gray-600">{__('Filter by rating:', 'zaplane')}</span>
            <div className="flex gap-2">
                {options.map((opt) => (
                    <button
                        key={opt.value}
                        onClick={() => onChange(opt.value)}
                        className="px-3 py-1 rounded-full text-sm border transition-all"
                        style={{
                            background: value === opt.value ? '#6c63ff' : '#fff',
                            color: value === opt.value ? '#fff' : '#444',
                            borderColor: value === opt.value ? '#6c63ff' : '#e2e8f0',
                            fontWeight: value === opt.value ? 600 : 400,
                        }}
                    >
                        {opt.label}
                    </button>
                ))}
            </div>
        </div>
    );
};

const FeedbackPage = () => {
    const dispatch = useDispatch();
    const { items, isLoading, pagination, filters } = useSelector((s) => s.feedback);

    const load = useCallback((pageOverride) => {
        const params = { ...filters, page: pageOverride ?? filters.page };
        dispatch(fetchFeedback(params));
        dispatch(fetchFeedbackSummary());
    }, [dispatch, filters]);

    useEffect(() => { load(); }, [filters]);

    const handleRatingFilter = (rating) => {
        dispatch(setFilters({ rating }));
    };

    const handlePageChange = (newPage) => {
        dispatch(setPage(newPage));
        load(newPage);
    };

    const columns = [
        {
            name: <span>{__('Order', 'zaplane')}</span>,
            cell: (row) => (
                <span className="font-mono text-sm text-gray-700">#{row.order_id}</span>
            ),
        },
        {
            name: <span>{__('Rating', 'zaplane')}</span>,
            cell: (row) => <Stars rating={row.rating} />,
            textAlign: 'center',
        },
        {
            name: <span>{__('Comment', 'zaplane')}</span>,
            cell: (row) => (
                <span className="text-sm text-gray-600 italic">
                    {row.comment || <span className="text-gray-300">—</span>}
                </span>
            ),
        },
        {
            name: <span>{__('Customer', 'zaplane')}</span>,
            cell: (row) => (
                <span className="text-sm text-gray-500">{row.email || '—'}</span>
            ),
            textAlign: 'center',
        },
        {
            name: <span>{__('Date', 'zaplane')}</span>,
            cell: (row) => (
                <span className="text-xs text-gray-400">
                    {row.created_at ? new Date(row.created_at).toLocaleDateString() : '—'}
                </span>
            ),
            textAlign: 'center',
        },
    ];

    return (
        <PageLayout title={__('Feedback', 'zaplane')} heading={__('Customer Feedback', 'zaplane')}>
            <SummaryCards />
            <RatingFilter value={filters.rating} onChange={handleRatingFilter} />
            <ListTable
                columns={columns}
                data={items}
                isRowSelectable={false}
                showSubHeader={false}
                showColumnFilter={false}
                showPagination={pagination.total > pagination.per_page}
                noDataText={__('No feedback submitted yet.', 'zaplane')}
                totalItems={pagination.total}
                dataFetchingStatus={isLoading}
                suffix="feedback-table"
                currentPageNumber={pagination.page}
                rowsPerPage={pagination.per_page}
                onChangePage={handlePageChange}
            />
        </PageLayout>
    );
};

export default FeedbackPage;
