import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { useDispatch, useSelector } from 'react-redux';
import {
    deleteCart,
    bulkDeleteCarts,
    setPage,
} from '@ZAPRedux/Slices/abandonedCartSlice/abandonedCartSlice';
import ListTable from '@ZAPComponents/ListTable';
import ZAPLabel from '@ZAPComponents/Labels/ZAPLabel';
import ZAPTooltip from '@ZAPComponents/ZAPTooltip';
import { TrashIcon } from 'lucide-react';

const STATUS_COLORS = {
    draft: '#f59e0b',
    processing: '#3b82f6',
    recovered: '#22c55e',
    lost: '#ef4444',
    opt_out: '#6b7280',
    skipped: '#9ca3af',
};

const StatusBadge = ({ status }) => {
    const color = STATUS_COLORS[status] || '#6b7280';
    return (
        <span
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: '4px',
                padding: '2px 8px',
                borderRadius: '99px',
                fontSize: '11px',
                fontWeight: 600,
                background: `${color}22`,
                color,
                border: `1px solid ${color}44`,
                textTransform: 'capitalize',
            }}
        >
            <span style={{ width: 6, height: 6, borderRadius: '50%', background: color, display: 'inline-block' }} />
            {status?.replace('_', ' ')}
        </span>
    );
};

const CartTable = ({ onRefresh }) => {
    const dispatch = useDispatch();
    const { carts, isLoading, pagination, filters } = useSelector((state) => state.abandonedCart);
    const [deletingId, setDeletingId] = useState(null);

    const recoveryBase = window.ZaplaneGlobal?.admin_url || '';

    const handleDelete = async (id) => {
        if (!window.confirm(__('Delete this cart record?', 'zaplane'))) return;
        setDeletingId(id);
        await dispatch(deleteCart(id));
        setDeletingId(null);
    };

    const handlePageChange = (newPage) => {
        dispatch(setPage(newPage));
        onRefresh && onRefresh(newPage);
    };

    const copyRecoveryLink = (cart) => {
        const siteUrl = window.ZaplaneGlobal?.site_url || '';
        const link = `${siteUrl}?zaplane=1&route=abandoned-cart&checkout_key=${cart.checkout_key}`;
        navigator.clipboard.writeText(link).then(() => {
            alert(__('Recovery link copied!', 'zaplane'));
        });
    };

    const columns = [
        {
            name: <span>{__('Customer', 'zaplane')}</span>,
            cell: (row) => (
                <div className="flex flex-col">
                    <span className="font-medium text-sm">{row.full_name || '—'}</span>
                    <span className="text-xs text-gray-400">{row.email || '—'}</span>
                </div>
            ),
        },
        {
            name: <span>{__('Total', 'zaplane')}</span>,
            cell: (row) => (
                <span className="font-semibold text-sm">
                    {row.currency} {parseFloat(row.total || 0).toFixed(2)}
                </span>
            ),
            textAlign: 'center',
        },
        {
            name: <span>{__('Status', 'zaplane')}</span>,
            cell: (row) => <StatusBadge status={row.status} />,
            textAlign: 'center',
        },
        {
            name: <span>{__('Items', 'zaplane')}</span>,
            cell: (row) => {
                const items = Array.isArray(row.cart?.cart_contents) ? row.cart.cart_contents : [];
                return <span className="text-sm">{items.length} {__('item(s)', 'zaplane')}</span>;
            },
            textAlign: 'center',
        },
        {
            name: <span>{__('Abandoned At', 'zaplane')}</span>,
            cell: (row) => (
                <span className="text-xs text-gray-500">
                    {row.abandoned_at ? new Date(row.abandoned_at).toLocaleString() : '—'}
                </span>
            ),
            textAlign: 'center',
        },
        {
            name: <span>{__('Actions', 'zaplane')}</span>,
            cell: (row) => (
                <div className="flex items-center gap-2 justify-center">
                    {row.status === 'processing' && (
                        <ZAPTooltip content={__('Copy Recovery Link', 'zaplane')}>
                            <button
                                onClick={() => copyRecoveryLink(row)}
                                className="flex px-2 py-1 justify-center items-center rounded border text-xs hover:bg-gray-50"
                                title={__('Copy Recovery Link', 'zaplane')}
                            >
                                🔗
                            </button>
                        </ZAPTooltip>
                    )}
                    <ZAPTooltip content={__('Delete', 'zaplane')}>
                        <button
                            onClick={() => handleDelete(row.id)}
                            disabled={deletingId === row.id}
                            className="flex px-2 py-1 justify-center items-center rounded border text-red-500 hover:bg-red-50 border-red-200 disabled:opacity-40"
                        >
                            <TrashIcon size={14} />
                        </button>
                    </ZAPTooltip>
                </div>
            ),
            textAlign: 'center',
        },
    ];

    return (
        <ListTable
            columns={columns}
            data={carts || []}
            isRowSelectable={false}
            showSubHeader={false}
            showColumnFilter={false}
            showPagination={pagination.total > pagination.per_page}
            noDataText={__('No abandoned carts found.', 'zaplane')}
            totalItems={pagination.total}
            dataFetchingStatus={isLoading}
            suffix="abandoned-carts-table"
            currentPageNumber={pagination.page}
            rowsPerPage={pagination.per_page}
            onChangePage={handlePageChange}
        />
    );
};

export default CartTable;
