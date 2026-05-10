import React from 'react';
import { __ } from '@wordpress/i18n';
import { useSelector } from 'react-redux';

const statCards = [
    { key: 'recovered_revenue', label: __('Recovered', 'zaplane'), color: '#22c55e' },
    { key: 'processing_revenue', label: __('Processing', 'zaplane'), color: '#3b82f6' },
    { key: 'lost_revenue', label: __('Lost', 'zaplane'), color: '#ef4444' },
    { key: 'draft_revenue', label: __('Draft', 'zaplane'), color: '#f59e0b' },
    { key: 'recovery_rate', label: __('Recovery Rate', 'zaplane'), color: '#8b5cf6' },
];

const ReportCards = () => {
    const { report, isReportLoading } = useSelector((state) => state.abandonedCart);

    if (isReportLoading) {
        return (
            <div className="grid grid-cols-2 gap-4 mb-6" style={{ gridTemplateColumns: 'repeat(5, 1fr)' }}>
                {statCards.map((card) => (
                    <div key={card.key} className="bg-white rounded-lg border p-4 animate-pulse">
                        <div className="h-4 bg-gray-200 rounded mb-2" />
                        <div className="h-6 bg-gray-200 rounded" />
                    </div>
                ))}
            </div>
        );
    }

    const summary = report?.summary || {};
    const totals = report?.totals || {};

    return (
        <div className="grid gap-4 mb-6" style={{ gridTemplateColumns: 'repeat(5, 1fr)' }}>
            {statCards.map((card) => {
                const data = summary[card.key] || {};
                const isRate = card.key === 'recovery_rate';

                return (
                    <div
                        key={card.key}
                        className="bg-white rounded-lg border p-4"
                        style={{ borderLeft: `4px solid ${card.color}` }}
                    >
                        <div className="text-sm text-gray-500 mb-1">{card.label}</div>
                        {isRate ? (
                            <div className="text-xl font-semibold" style={{ color: card.color }}>
                                {data.formatted || '0%'}
                            </div>
                        ) : (
                            <>
                                <div className="text-xl font-semibold" style={{ color: card.color }}>
                                    {data.formatted || '0.00'}
                                </div>
                                <div className="text-xs text-gray-400">
                                    {data.orders || 0} {__('orders', 'zaplane')}
                                </div>
                            </>
                        )}
                    </div>
                );
            })}
        </div>
    );
};

export default ReportCards;
