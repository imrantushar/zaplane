import React from 'react';
import { __ } from '@wordpress/i18n';
import ZAPSelect from '@ZAPComponents/ZAPSelect';

const TaggingSettings = ({ settings, onChange, gemcrm_tags, gemcrm_lists }) => {
    const tagOptions = (gemcrm_tags || []).map((t) => ({ value: t.id, label: t.title }));
    const listOptions = (gemcrm_lists || []).map((l) => ({ value: l.id, label: l.title }));

    const hasGemCrm = tagOptions.length > 0 || listOptions.length > 0;

    if (!hasGemCrm) {
        return (
            <div className="bg-yellow-50 border border-yellow-200 rounded p-4 text-sm text-yellow-800">
                {__('GemCRM plugin is not active or has no tags/lists. Install and activate GemCRM to configure tagging.', 'zaplane')}
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-6">
            <div>
                <h3 className="text-sm font-semibold text-gray-700 mb-3">
                    {__('When Cart is Abandoned (Processing)', 'zaplane')}
                </h3>
                <div className="grid grid-cols-2 gap-4">
                    <ZAPSelect
                        label={__('Apply Tags', 'zaplane')}
                        options={tagOptions}
                        value={settings.abandoned_tags || []}
                        onChange={(values) => onChange('abandoned_tags', values)}
                        isMulti
                        placeholder={__('Select tags…', 'zaplane')}
                    />
                    <ZAPSelect
                        label={__('Apply to Lists', 'zaplane')}
                        options={listOptions}
                        value={settings.abandoned_list || []}
                        onChange={(values) => onChange('abandoned_list', values)}
                        isMulti
                        placeholder={__('Select lists…', 'zaplane')}
                    />
                </div>
            </div>

            <hr className="border-gray-200" />

            <div>
                <h3 className="text-sm font-semibold text-gray-700 mb-3">
                    {__('When Cart is Lost', 'zaplane')}
                </h3>
                <div className="grid grid-cols-2 gap-4">
                    <ZAPSelect
                        label={__('Apply Tags', 'zaplane')}
                        options={tagOptions}
                        value={settings.lost_tags || []}
                        onChange={(values) => onChange('lost_tags', values)}
                        isMulti
                        placeholder={__('Select tags…', 'zaplane')}
                    />
                    <ZAPSelect
                        label={__('Apply to Lists', 'zaplane')}
                        options={listOptions}
                        value={settings.lost_list || []}
                        onChange={(values) => onChange('lost_list', values)}
                        isMulti
                        placeholder={__('Select lists…', 'zaplane')}
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
