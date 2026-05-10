import React from 'react';
import { __ } from '@wordpress/i18n';
import { useDispatch, useSelector } from 'react-redux';
import { setFilters } from '@ZAPRedux/Slices/abandonedCartSlice/abandonedCartSlice';
import ZAPInput from '@ZAPComponents/ZAPInput';
import ZAPSelect from '@ZAPComponents/ZAPSelect';
import ZAPDatePicker from '@ZAPComponents/ZAPDatePicker';

const STATUS_OPTIONS = [
    { value: '', label: __('All Statuses', 'zaplane') },
    { value: 'draft', label: __('Draft', 'zaplane') },
    { value: 'processing', label: __('Processing', 'zaplane') },
    { value: 'recovered', label: __('Recovered', 'zaplane') },
    { value: 'lost', label: __('Lost', 'zaplane') },
    { value: 'opt_out', label: __('Opt Out', 'zaplane') },
    { value: 'skipped', label: __('Skipped', 'zaplane') },
];

const CartFilters = () => {
    const dispatch = useDispatch();
    const filters = useSelector((state) => state.abandonedCart.filters);

    const handleChange = (key, value) => {
        dispatch(setFilters({ [key]: value }));
    };

    return (
        <div className="flex flex-wrap gap-3 mb-4 items-end">
            <div style={{ minWidth: '200px' }}>
                <ZAPInput
                    label={__('Search', 'zaplane')}
                    placeholder={__('Name or email…', 'zaplane')}
                    value={filters.search}
                    onChange={(e) => handleChange('search', e.target.value)}
                />
            </div>

            <div style={{ minWidth: '170px' }}>
                <ZAPSelect
                    label={__('Status', 'zaplane')}
                    options={STATUS_OPTIONS}
                    value={filters.status}
                    onChange={(selected) => handleChange('status', selected?.value ?? '')}
                    isClearable
                    placeholder={__('All Statuses', 'zaplane')}
                />
            </div>

            <div style={{ minWidth: '160px' }}>
                <ZAPDatePicker
                    label={__('From', 'zaplane')}
                    value={filters.date_from}
                    onChange={(date) => {
                        const fmt = date ? date.toISOString().split('T')[0] : '';
                        handleChange('date_from', fmt);
                    }}
                    placeholder="yyyy-MM-dd"
                />
            </div>

            <div style={{ minWidth: '160px' }}>
                <ZAPDatePicker
                    label={__('To', 'zaplane')}
                    value={filters.date_to}
                    onChange={(date) => {
                        const fmt = date ? date.toISOString().split('T')[0] : '';
                        handleChange('date_to', fmt);
                    }}
                    placeholder="yyyy-MM-dd"
                />
            </div>
        </div>
    );
};

export default CartFilters;
