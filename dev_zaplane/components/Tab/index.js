import { Tabs } from "@chakra-ui/react";

const ZAPTab = ({ value, tabs, onChange }) => {
    return (
        <Tabs.Root
            value={value}
            onValueChange={(e) => onChange?.(e.value)}
        >
            <Tabs.List mb={4}>
                {tabs.map(tab => (
                    <Tabs.Trigger key={tab.value} value={tab.value}>
                        {tab.label}
                    </Tabs.Trigger>
                ))}
            </Tabs.List>

            {tabs.map(tab => (
                <Tabs.Content p='0' key={tab.value} value={tab.value}>
                    {tab.content}
                </Tabs.Content>
            ))}
        </Tabs.Root>
    );
};

export default ZAPTab;
