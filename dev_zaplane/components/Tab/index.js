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
            <TabList className="flex space-x-1 mb-4 border-b border-gray-200">
                {tabs.map((tab) => (
                    <Tab as={Fragment} key={tab.value}>
                        {({ selected }) => (
                            <button
                                className={`
                                    py-2 px-4 text-sm font-medium focus:outline-none 
                                    ${selected 
                                        ? 'border-b-2 border-blue-600 text-blue-600' 
                                        : 'text-gray-500 hover:text-gray-700 hover:border-gray-300 border-b-2 border-transparent'}
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
