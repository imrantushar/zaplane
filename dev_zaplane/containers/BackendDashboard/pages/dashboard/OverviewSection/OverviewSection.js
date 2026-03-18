import React from "react";
import { Box, Flex, Text, Icon } from "@chakra-ui/react";
import { FiLayers, FiPlayCircle, FiActivity } from "react-icons/fi";

const StatCard = ({ title, value, icon }) => {
    return (
        <Flex
            flex="1"
            bg="white"
            p={6}
            borderRadius="12px"
            boxShadow="sm"
            align="center"
            justify="space-between"
            width='410px'
            height='130px'
        >
            <Box w='100%'>
                <Flex justifyContent='space-between' mb={5}>
                    <Text className="zaplane-label">
                        {title}
                    </Text>
                    <Icon as={icon} boxSize="24px" color="gray.400" />
                </Flex>
                <Text className="zaplane-label">
                    {value}
                </Text>
            </Box>


        </Flex>
    );
};

const OverviewSection = () => {
    return (
        <Flex gap="24px" flexWrap="wrap">
            <StatCard title="Total Flows" value="12" icon={FiLayers} />
            <StatCard title="Total Executions" value="87" icon={FiPlayCircle} />
            <StatCard title="Active Flows" value="6" icon={FiActivity} />
        </Flex>
    );
};

export default OverviewSection;