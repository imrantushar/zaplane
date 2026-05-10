import React from 'react';
import { __ } from '@wordpress/i18n';
import ZAPInput from '@ZAPComponents/ZAPInput';

const GDPRSettings = ({ settings, onChange }) => {
    return (
        <div className="flex flex-col gap-6">
            <div className="flex items-center gap-3">
                <label className="flex items-center gap-2 cursor-pointer">
                    <input
                        type="checkbox"
                        checked={!!settings.gdpr_consent_in_woo_checkout_page}
                        onChange={(e) => onChange('gdpr_consent_in_woo_checkout_page', e.target.checked)}
                        className="w-4 h-4"
                    />
                    <span className="zaplane-label font-semibold">
                        {__('Show GDPR Consent Checkbox on Checkout', 'zaplane')}
                    </span>
                </label>
            </div>

            {settings.gdpr_consent_in_woo_checkout_page && (
                <ZAPInput
                    label={__('GDPR Consent Message', 'zaplane')}
                    type="textarea"
                    value={settings.gdpr_msg || ''}
                    onChange={(e) => onChange('gdpr_msg', e.target.value)}
                    placeholder={__('By continuing, you agree that we may save your cart data.', 'zaplane')}
                />
            )}

            <div
                className="bg-blue-50 border border-blue-200 rounded p-3 text-sm text-blue-700"
            >
                {__('When enabled, a consent checkbox will appear on the WooCommerce checkout page. Cart data will only be captured if the customer checks the box.', 'zaplane')}
            </div>
        </div>
    );
};

export default GDPRSettings;
