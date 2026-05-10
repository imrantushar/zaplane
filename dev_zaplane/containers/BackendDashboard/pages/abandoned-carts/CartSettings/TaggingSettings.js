import React from 'react';
import { __ } from '@wordpress/i18n';
import GemCRMAsyncSelect from '@ZAPComponents/GemCRMAsyncSelect';

const TaggingSettings = ({ settings, onChange }) => {
    return (
        <div className="flex flex-col gap-6">
            <div>
                <h3 className="text-sm font-semibold text-gray-700 mb-3">
                    {__('When Cart is Abandoned (Processing)', 'zaplane')}
                </h3>
                <div className="grid grid-cols-2 gap-4">
                    <GemCRMAsyncSelect
                        endpoint="tags"
                        label={__('Apply Tags', 'zaplane')}
                        value={settings.abandoned_tags || []}
                        onChange={(values) => onChange('abandoned_tags', values)}
                        placeholder={__('Search tags…', 'zaplane')}
                    />
                    <GemCRMAsyncSelect
                        endpoint="lists"
                        label={__('Apply to Lists', 'zaplane')}
                        value={settings.abandoned_list || []}
                        onChange={(values) => onChange('abandoned_list', values)}
                        placeholder={__('Search lists…', 'zaplane')}
                    />
                </div>
            </div>

            <hr className="border-gray-200" />

            <div>
                <h3 className="text-sm font-semibold text-gray-700 mb-3">
                    {__('When Cart is Lost', 'zaplane')}
                </h3>
                <div className="grid grid-cols-2 gap-4">
                    <GemCRMAsyncSelect
                        endpoint="tags"
                        label={__('Apply Tags', 'zaplane')}
                        value={settings.lost_tags || []}
                        onChange={(values) => onChange('lost_tags', values)}
                        placeholder={__('Search tags…', 'zaplane')}
                    />
                    <GemCRMAsyncSelect
                        endpoint="lists"
                        label={__('Apply to Lists', 'zaplane')}
                        value={settings.lost_list || []}
                        onChange={(values) => onChange('lost_list', values)}
                        placeholder={__('Search lists…', 'zaplane')}
                    />
                </div>
            </div>

            <div className="bg-blue-50 border border-blue-200 rounded p-3 text-sm text-blue-700">
                {__('When a cart is recovered, the abandoned tags and lists will be automatically removed from the GemCRM contact.', 'zaplane')}
            </div>
        </div>
    );
};

export default TaggingSettings;
