import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import PageLayout from '@ZAPComponents/PageLayout';
import ZAPTab from '@ZAPComponents/Tab';
import CartList from './CartList';
import CartSettings from './CartSettings';

const AbandonedCarts = () => {
    const [activeTab, setActiveTab] = useState('carts');

    const tabs = [
        {
            value: 'carts',
            label: __('Abandoned Carts', 'zaplane'),
            content: <CartList />,
        },
        {
            value: 'settings',
            label: __('Settings', 'zaplane'),
            content: <CartSettings />,
        },
    ];

    return (
        <PageLayout
            title={__('Abandoned Carts', 'zaplane')}
            heading={__('Abandoned Carts', 'zaplane')}
        >
            <ZAPTab
                value={activeTab}
                tabs={tabs}
                onChange={setActiveTab}
            />
        </PageLayout>
    );
};

export default AbandonedCarts;
