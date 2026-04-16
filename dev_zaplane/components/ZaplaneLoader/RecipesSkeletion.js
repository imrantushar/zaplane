import React from "react";
import {
    Box,
    Flex,
    Skeleton,
    SkeletonText,
    SimpleGrid,
} from "@chakra-ui/react";

const RecipesSkeleton = () => {
    return (
        <Box bg="#F9FAFB" minH="100vh" p={6}>
            <Flex justify="space-between" align="center" mb={8}>
                <Skeleton height="20px" width="100px" />
                <Flex gap={3}>
                    <Skeleton height="36px" width="110px" borderRadius="md" />
                    <Skeleton height="36px" width="80px" borderRadius="md" />
                </Flex>
            </Flex>


            <Box className="zaplane-page-content">
                <Flex justify="space-between" align="center" mb={6}>
                    <Skeleton height="28px" width="140px" />
                    <Skeleton height="40px" width="150px" borderRadius="md" />
                </Flex>
                <SimpleGrid
                    columns={{ base: 1, md: 2, lg: 3 }}
                    gap='16px'
                    spacing={6}
                >
                    {[...Array(6)].map((_, i) => (
                        <Box
                            key={i}
                            bg="white"
                            border="1px solid"
                            borderColor="gray.200"
                            borderRadius="lg"
                            p={5}
                            boxShadow="sm"
                        >

                            <Flex justify="space-between" align="center" mb={4}>
                                <Flex align="center" gap={3}>
                                    <Skeleton boxSize="22px" />
                                    <Skeleton height="16px" width="90px" />
                                </Flex>

                                <Skeleton boxSize="26px" borderRadius="md" />
                            </Flex>

                            <SkeletonText noOfLines={1} width="120px" />

                            <Flex justify="flex-end" mt={5}>
                                <Skeleton boxSize="18px" />
                            </Flex>
                        </Box>
                    ))}
                </SimpleGrid>
            </Box>
        </Box>
    );
};

export default RecipesSkeleton;