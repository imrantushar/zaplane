import React, { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { useDispatch, useSelector } from 'react-redux';
import {
    fetchSettings,
    saveSettings,
} from '@ZAPRedux/Slices/abandonedCartSlice/abandonedCartSlice';
import ZAPTab from '@ZAPComponents/Tab';
import GeneralSettings from './GeneralSettings';
import GDPRSettings from './GDPRSettings';
import TaggingSettings from './TaggingSettings';

const CartSettings = () => {
    const dispatch = useDispatch();
    const {
        settings: savedSettings,
        isSettingsLoading,
        isSaving,
        wc_order_statuses,
        user_roles,
    } = useSelector((state) => state.abandonedCart);

    const [localSettings, setLocalSettings] = useState(null);
    const [activeTab, setActiveTab] = useState('general');

    useEffect(() => {
        dispatch(fetchSettings());
    }, [dispatch]);

    useEffect(() => {
        if (savedSettings) {
            setLocalSettings({ ...savedSettings });
        } else if (!isSettingsLoading && !savedSettings) {
            setLocalSettings({
                status: false,
                cart_off_time: 30,
                mark_as_lost_after_minutes: 10080,
                cool_off_period: 10080,
                status_of_new_contact: 'transactional',
                mark_as_recovered_when_order_status_changed_to: ['processing', 'completed'],
                gdpr_consent_in_woo_checkout_page: false,
                gdpr_msg: 'By continuing, you agree that we may save your cart data.',
                disabled_user_roles: [],
                abandoned_list: [],
                abandoned_tags: [],
                lost_list: [],
                lost_tags: [],
            });
        }
    }, [savedSettings, isSettingsLoading]);

    const handleChange = (key, value) => {
        setLocalSettings((prev) => ({ ...prev, [key]: value }));
    };

    const handleSave = () => {
        if (localSettings) {
            dispatch(saveSettings(localSettings));
        }
    };

    if (isSettingsLoading || !localSettings) {
        return (
            <div className="flex items-center justify-center py-12 text-gray-400">
                {__('Loading settings…', 'zaplane')}
            </div>
        );
    }

    const tabs = [
        {
            value: 'general',
            label: __('General', 'zaplane'),
            content: (
                <GeneralSettings
                    settings={localSettings}
                    onChange={handleChange}
                    wc_order_statuses={wc_order_statuses}
                    user_roles={user_roles}
                />
            ),
        },
        {
            value: 'gdpr',
            label: __('GDPR', 'zaplane'),
            content: (
                <GDPRSettings
                    settings={localSettings}
                    onChange={handleChange}
                />
            ),
        },
        {
            value: 'tagging',
            label: __('Tagging & Lists', 'zaplane'),
            content: (
                <TaggingSettings
                    settings={localSettings}
                    onChange={handleChange}
                />
            ),
        },
    ];

    return (
        <div>
            <ZAPTab
                value={activeTab}
                tabs={tabs}
                onChange={setActiveTab}
            />

            <div className="flex justify-end mt-6">
                <button
                    onClick={handleSave}
                    disabled={isSaving}
                    className="px-5 py-2 rounded text-white text-sm font-medium disabled:opacity-50"
                    style={{ background: 'var(--zaplane-primary, #6366f1)' }}
                >
                    {isSaving ? __('Saving…', 'zaplane') : __('Save Settings', 'zaplane')}
                </button>
            </div>
        </div>
    );
};

export default CartSettings;
