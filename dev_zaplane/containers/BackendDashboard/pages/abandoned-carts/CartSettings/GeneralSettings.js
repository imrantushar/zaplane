import React from 'react';
import { __ } from '@wordpress/i18n';
import ZAPInput from '@ZAPComponents/ZAPInput';
import ZAPSelect from '@ZAPComponents/ZAPSelect';

const GeneralSettings = ({ settings, onChange, wc_order_statuses, user_roles }) => {
    const orderStatusOptions = (wc_order_statuses || []).map((s) => ({
        value: s.value,
        label: s.label,
    }));

    const userRoleOptions = (user_roles || []).map((r) => ({
        value: r.value,
        label: r.label,
    }));

    const contactStatusOptions = [
        { value: 'transactional', label: __('Transactional', 'zaplane') },
        { value: 'subscribed', label: __('Subscribed', 'zaplane') },
        { value: 'pending', label: __('Pending', 'zaplane') },
    ];

    return (
        <div className="flex flex-col gap-6">
            <div className="flex items-center gap-3">
                <label className="flex items-center gap-2 cursor-pointer">
                    <input
                        type="checkbox"
                        checked={!!settings.status}
                        onChange={(e) => onChange('status', e.target.checked)}
                        className="w-4 h-4"
                    />
                    <span className="zaplane-label font-semibold">
                        {__('Enable Abandoned Cart Tracking', 'zaplane')}
                    </span>
                </label>
            </div>

            <div className="grid grid-cols-3 gap-4">
                <ZAPInput
                    label={__('Cart Off Time (minutes)', 'zaplane')}
                    type="number"
                    value={settings.cart_off_time ?? 30}
                    onChange={(e) => onChange('cart_off_time', parseInt(e.target.value) || 30)}
                    placeholder="30"
                />

                <ZAPInput
                    label={__('Mark as Lost After (minutes)', 'zaplane')}
                    type="number"
                    value={settings.mark_as_lost_after_minutes ?? 10080}
                    onChange={(e) => onChange('mark_as_lost_after_minutes', parseInt(e.target.value) || 10080)}
                    placeholder="10080"
                />

                <ZAPInput
                    label={__('Cool-off Period (minutes)', 'zaplane')}
                    type="number"
                    value={settings.cool_off_period ?? 10080}
                    onChange={(e) => onChange('cool_off_period', parseInt(e.target.value) || 10080)}
                    placeholder="10080"
                />
            </div>

            <div className="grid grid-cols-2 gap-4">
                <ZAPSelect
                    label={__('New Contact Status', 'zaplane')}
                    options={contactStatusOptions}
                    value={settings.status_of_new_contact || 'transactional'}
                    onChange={(selected) => onChange('status_of_new_contact', selected?.value || 'transactional')}
                />

                <ZAPSelect
                    label={__('Mark as Recovered When Order Status Changed To', 'zaplane')}
                    options={orderStatusOptions}
                    value={settings.mark_as_recovered_when_order_status_changed_to || []}
                    onChange={(values) => onChange('mark_as_recovered_when_order_status_changed_to', values)}
                    isMulti
                    placeholder={__('Select statuses…', 'zaplane')}
                />
            </div>

            <ZAPSelect
                label={__('Disable Tracking for These Roles', 'zaplane')}
                options={userRoleOptions}
                value={settings.disabled_user_roles || []}
                onChange={(values) => onChange('disabled_user_roles', values)}
                isMulti
                placeholder={__('Select roles…', 'zaplane')}
            />
        </div>
    );
};

export default GeneralSettings;
