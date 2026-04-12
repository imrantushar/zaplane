import React from "react";
import { Box, Flex, Text, Icon, Skeleton, SkeletonText } from "@chakra-ui/react";

const StatCard = ({ title, value, icon, isLoading }) => {
    return (
        <Flex
            flex="1"
            bg="white"
            p={6}
            borderRadius="4px"
            boxShadow="sm"
            align="center"
            justify="space-between"
            width="410px"
            height="130px"
        >
            <Box w="100%">
                <Flex justifyContent="space-between" mb={5}>
                    {isLoading ? (
                        <Skeleton height="16px" width="120px" />
                    ) : (
                        <Text className="zaplane-label" fontSize="16px" fontWeight="400">
                            {title}
                        </Text>
                    )}

                    {isLoading ? (
                        <Skeleton boxSize="24px" borderRadius="4px" />
                    ) : (
                        <Icon as={icon} boxSize="24px" />
                    )}
                </Flex>

                {isLoading ? (
                    <Skeleton height="30px" width="80px" />
                ) : (
                    <Text className="zaplane-label" fontSize="30px">
                        {value ?? 0}
                    </Text>
                )}
            </Box>
        </Flex>
    );
};

export default StatCard;