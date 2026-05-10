import React, { useEffect, useCallback } from 'react';
import { __ } from '@wordpress/i18n';
import { useDispatch, useSelector } from 'react-redux';
import { fetchCarts, fetchReport } from '@ZAPRedux/Slices/abandonedCartSlice/abandonedCartSlice';
import ReportCards from './ReportCards';
import CartFilters from './CartFilters';
import CartTable from './CartTable';

const CartList = () => {
    const dispatch = useDispatch();
    const filters = useSelector((state) => state.abandonedCart.filters);

    const loadData = useCallback((pageOverride) => {
        const params = {
            ...filters,
            page: pageOverride || filters.page,
        };
        dispatch(fetchCarts(params));
        dispatch(fetchReport({ date_from: filters.date_from, date_to: filters.date_to }));
    }, [dispatch, filters]);

    useEffect(() => {
        loadData();
    }, [filters]);

    return (
        <div>
            <ReportCards />
            <CartFilters />
            <CartTable onRefresh={loadData} />
        </div>
    );
};

export default CartList;
