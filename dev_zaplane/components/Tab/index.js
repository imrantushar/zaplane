import { Tab, TabGroup, TabList, TabPanel, TabPanels } from '@headlessui/react';
import React, { Fragment } from 'react';

const ZAPTab = ({ value, tabs, onChange }) => {
    // Determine the selected index from the 'value' prop
    const selectedIndex = tabs.findIndex(t => t.value === value);

    const handleTabChange = (index) => {
        if (onChange && tabs[index]) {
            onChange(tabs[index].value);
        }
    };

    return (
        <TabGroup selectedIndex={selectedIndex >= 0 ? selectedIndex : 0} onChange={handleTabChange}>
            <TabList className="flex mb-4 border-b border-[var(--zaplane-border-color)]">
                {tabs.map((tab, index) => (
                    <Tab as={Fragment} key={tab.value}>
                        {({ selected }) => (
                            <button
                                className={`
                                    py-2.5 ${index === 0 ? 'pr-[12px]' : 'px-[12px]'} text-sm font-medium border-b-2 transition-colors focus:outline-none
                                    ${selected
                                        ? 'border-[var(--zaplane-primary)] text-[var(--zaplane-font-color)]'
                                        : 'border-transparent text-[var(--zaplane-font-secondary-color)] hover:text-[var(--zaplane-font-color)]'}
                                `}
                            >
                                {tab.label}
                            </button>
                        )}
                    </Tab>
                ))}
            </TabList>
            <TabPanels>
                {tabs.map((tab) => (
                    <TabPanel key={tab.value} className="p-0 outline-none">
                        {tab.content}
                    </TabPanel>
                ))}
            </TabPanels>
        </TabGroup>
    );
};

export default ZAPTab;
