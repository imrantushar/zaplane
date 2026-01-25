import { Box, HStack, Text } from '@chakra-ui/react';
import { __, sprintf } from '@wordpress/i18n';
import ZAPTable from '@ZAPComponents/Table';
import { getDuration } from '@ZAPUtils/helper';


const RecentLogs = ({ data }) => {

    const isSuccess = (status) => status === "completed";
    return (
        <Box width='100%'>
            <Text className="zaplane-heading" marginBottom="16px">{__('Recent Logs', 'zaplane')}</Text>
            <ZAPTable
                data={data.slice(0, 5)}
                rowKey="id"
                variant="outline"
                size="sm"
                columns={[
                    {
                        label: "CREATED AT",
                        key: "started_at",
                        render: (row) => {
                            sprintf(
                                __('Start: %s', 'zapplane'),
                                row.started_at || __('--', 'zapplane')
                            )
                        },
                    },
                    {
                        label: "DURATION / SIZE",
                        key: "duration",

                        render: (row) => (
                            <Text fontSize="sm"> {sprintf(
                                __('%s', 'zaplane'),
                                getDuration(row.started_at, row.finished_at)
                            )}</Text>
                        ),
                    },
                    {
                        label: "STATUS",
                        key: "status",
                        render: (row) => (
                            <HStack spacing={2}>
                                <Box
                                    w="8px"
                                    h="8px"
                                    borderRadius="full"
                                    bg={isSuccess(row.status) ? "green.500" : "red.500"}
                                />
                                <Text fontSize="sm">
                                    {sprintf(
                                        __('Status: %s', 'zapplane'),
                                        isSuccess(row.status)
                                            ? __('Success', 'zapplane')
                                            : __('Failed', 'zapplane')
                                    )}

                                </Text>
                            </HStack>
                        ),
                    },
                ]}
            />
        </Box>
    );
};

export default RecentLogs;