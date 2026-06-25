import React, { useCallback } from 'react';
import AsyncSelect from 'react-select/async';
import { __ } from '@wordpress/i18n';

const { rest_url, nonce } = window?.ZaplaneGlobal || {};

const fetchGemCRMOptions = async (endpoint, inputValue) => {
    const params = new URLSearchParams({
        page: 1,
        per_page: 20,
        order: 'desc',
        order_by: 'created_at',
    });
    if (inputValue) {
        params.set('search', inputValue);
    }

    const url = `${rest_url}gemcrm/v1/${endpoint}?${params.toString()}`;

    try {
        const res = await fetch(url, {
            headers: {
                'X-WP-Nonce': nonce,
                'Content-Type': 'application/json',
            },
        });
        if (!res.ok) return [];
        const data = await res.json();
        const records = data?.records || [];
        return records.map((r) => ({ value: r.id, label: r.title }));
    } catch {
        return [];
    }
};

const GemCRMAsyncSelect = ({
    endpoint,
    label,
    value = [],
    onChange,
    placeholder,
}) => {
    const loadOptions = useCallback(
        (inputValue) => fetchGemCRMOptions(endpoint, inputValue),
        [endpoint]
    );

    const selectedOptions = Array.isArray(value)
        ? value.map((v) =>
              typeof v === 'object' ? v : { value: v, label: `#${v}` }
          )
        : [];

    const handleChange = (selected) => {
        onChange?.(selected ? selected.map((o) => o.value) : []);
    };

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
            {label && (
                <span className="zaplane-label font-[600] text-[0.875rem]">
                    {__(label, 'zaplane')}
                </span>
            )}
            <AsyncSelect
                isMulti
                cacheOptions
                defaultOptions
                loadOptions={loadOptions}
                value={selectedOptions}
                onChange={handleChange}
                placeholder={placeholder || __('Search…', 'zaplane')}
                noOptionsMessage={() => __('No results', 'zaplane')}
                loadingMessage={() => __('Loading…', 'zaplane')}
                styles={{
                    control: (base) => ({
                        ...base,
                        minHeight: '38px',
                        fontSize: '13px',
                        borderColor: '#e2e8f0',
                        boxShadow: 'none',
                        '&:hover': { borderColor: '#cbd5e1' },
                    }),
                    menu: (base) => ({ ...base, fontSize: '13px', zIndex: 9999 }),
                    option: (base, state) => ({
                        ...base,
                        backgroundColor: state.isSelected
                            ? 'var(--zaplane-primary, #6366f1)'
                            : state.isFocused
                            ? '#f1f5f9'
                            : 'white',
                        color: state.isSelected ? 'white' : '#1e293b',
                    }),
                    multiValue: (base) => ({
                        ...base,
                        backgroundColor: '#e0e7ff',
                        borderRadius: '4px',
                    }),
                    multiValueLabel: (base) => ({
                        ...base,
                        color: '#4338ca',
                        fontSize: '12px',
                    }),
                    multiValueRemove: (base) => ({
                        ...base,
                        color: '#6366f1',
                        '&:hover': { backgroundColor: '#c7d2fe', color: '#4338ca' },
                    }),
                }}
            />
        </div>
    );
};

export default GemCRMAsyncSelect;
