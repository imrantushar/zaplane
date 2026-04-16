import React from "react";
import {
    Box,
    Flex,
    Skeleton,
    SkeletonText,
    SimpleGrid,
} from "@chakra-ui/react";

const FolderSkeleton = () => {
    return (
        <Box p={6}>
            <Flex justify="space-between" align="center" mb={6}>
                <Skeleton height="24px" width="120px" />
                <Skeleton height="40px" width="140px" borderRadius="md" />
            </Flex>

            <div className="zaplane-page-content">
                <Flex justify="space-between" align="center" mb={6}>
                    <Skeleton height="28px" width="140px" />
                    <Skeleton height="40px" width="150px" borderRadius="md" />
                </Flex>
                <SimpleGrid columns={{ base: 1, md: 2, lg: 3 }} gap='16px' spacing={6}>
                    {[...Array(6)].map((_, i) => (
                        <Box
                            key={i}
                            border="1px solid"
                            borderColor="gray.200"
                            borderRadius="lg"
                            p={4}
                        >
                            <Flex justify="space-between" align="center" mb={4}>
                                <Flex align="center" gap={3}>
                                    <Skeleton boxSize="20px" />
                                    <Skeleton height="16px" width="80px" />
                                </Flex>

                                <Skeleton boxSize="24px" borderRadius="md" />
                            </Flex>

                            <SkeletonText noOfLines={1} width="100px" />

                            <Flex justify="flex-end" mt={4}>
                                <Skeleton boxSize="18px" />
                            </Flex>
                        </Box>
                    ))}
                </SimpleGrid>
            </div>
        </Box>
    );
};

export default FolderSkeleton;