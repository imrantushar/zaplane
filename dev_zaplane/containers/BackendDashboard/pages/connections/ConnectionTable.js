import { Text, Icon, Box, Flex } from "@chakra-ui/react";
import { __ } from "@wordpress/i18n";
import { useDispatch, useSelector } from "react-redux";
import { useEffect, useState } from "react";

import ListTable from "@ZAPComponents/ListTable";
import OptionMenu from "@ZAPComponents/OptionMenu";
import StatusOptions from "@ZAPComponents/StatusOptions";

import { FiTrash2, FiRefreshCw, FiEye } from "react-icons/fi";

import {
    deleteConnection,
    testConnection,
    fetchSingleConnection,
    updateConnection,
    fetchConnections,
} from "@ZAPRedux/Slices/connectionsSlice/connectionsSlice";

import ConnectionDetails from "./ConnectionDetails/ConnectionDetails";
import { formatDateTime } from "@ZAPUtils/helper";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";
import { TableArrow } from "@ZAPUtils/icons";

const ConnectionTable = () => {
    const dispatch = useDispatch();
    const [detailsOpen, setDetailsOpen] = useState(false);

    const { allConnection = [], isLoading, connection, currentPage, perPage,totalItems } = useSelector(
        (state) => state.connections
    );
    const [loading, setLoading] = useState(allConnection.length === 0);
    const handleRefresh = async (page = 1, per_page = 10) => {
        setLoading(true)
        await dispatch(fetchConnections({ page, per_page }));
        setLoading(false)
    };

    useEffect(() => {
        handleRefresh()
    }, []);

    const handlePageChange = (newPage) => {
        handleRefresh(newPage, perPage)
    };

    const handlePerPageChange = (itemsPerPage) => {
        handleRefresh(currentPage, itemsPerPage)
    };
    const handleStatusChange = (row, newStatus) => {
        if (!row?.id || !newStatus) return;

        dispatch(
            updateConnection({
                id: row.id,
                payload: { status: newStatus },
            })
        );
    };

    const openDetails = (row) => {
        dispatch(fetchSingleConnection(row.id));
        setDetailsOpen(true);
    };

    const columns = [
        {
            name: (
                <Flex gap="2px" alignItems='center' >
                    <Text className="zaplane-label">
                        {__("App / Name", "zaplane")}
                    </Text>
                    <Icon as={TableArrow} />
                </Flex>
            ),
            cell: (row) => (
                <Text className="zaplane-label" fontWeight="400" textOverflow="ellipsis">
                    {row.name}
                </Text>
            ),
            // columnWidth: "150px",
            textAlign: "start",
        },
        {
            name: (
                <Flex gap="2px" alignItems='center' justifyContent="center" >
                    <Text className="zaplane-label">
                        {__("Auth Type", "zaplane")}
                    </Text>
                    <Icon as={TableArrow} />
                </Flex>
            ),
            cell: (row) => (
                <ZAPLabel label={row.auth_type} type={"simple"} />
            ),
            // columnWidth: "120px",
            textAlign: "center",
        },
        {
            name: (
                <Flex gap="2px" alignItems='center' justifyContent="center" ml='-32px'>
                    <Text className="zaplane-label">
                        {__("Created At", "zaplane")}
                    </Text>
                    <Icon as={TableArrow} />
                </Flex>
            ),
            cell: (row) => {
                const { date, time } = formatDateTime(row.created_at);

                return (
                    <Box textAlign="center">
                        <ZAPLabel label={date} type={"simple"} />
                        <Text className="zaplane-sub-title" ml='-50px' color="var(--zaplane-text-muted)">
                            {__(time, 'zaplane')}
                        </Text>
                    </Box>
                );
            },
            // columnWidth: "160px",
            textAlign: "center",
        },
        {
            name: (
                <Flex gap="2px" alignItems='center' justifyContent="center" ml='-32px'>
                    <Text className="zaplane-label">
                        {__("Updated At", "zaplane")}
                    </Text>
                    <Icon as={TableArrow} />
                </Flex>
            ),
            cell: (row) => {
                const { date, time } = formatDateTime(row.updated_at);

                return (
                    <Box textAlign="center">
                        <ZAPLabel label={date} type={"simple"} />
                        <Text className="zaplane-sub-title" ml='-50px' color="var(--zaplane-text-muted)">
                            {__(time, 'zaplane')}
                        </Text>
                    </Box>
                );
            },
            // columnWidth: "160px",
            textAlign: "center",
        },
        {
            name: (
                <Flex gap="2px" alignItems='center' justifyContent="center" ml='-32px'>
                    <Text className="zaplane-label">
                        {__("Status", "zaplane")}
                    </Text>
                    <Icon as={TableArrow} />
                </Flex>
            ),
            cell: (row) => (
                <StatusOptions
                    value={row?.status}
                    options={{
                        items: [
                            { label: "Active", value: "active" },
                            { label: "Inactive", value: "inactive" },
                        ],
                    }}
                    onChangeHandler={(newStatus) =>
                        handleStatusChange(row, newStatus)
                    }
                />
            ),
            // columnWidth: "170px",
            textAlign: "center",
        },
        {
            name: (
                <Text className="zaplane-label">
                    {__("Action", "zaplane")}
                </Text>
            ),
            cell: (row) => (
                <OptionMenu
                    options={[
                        {
                            label: __("Test", "zaplane"),
                            icon: <Icon as={FiRefreshCw} />,
                            type: "button",
                            onClick: () => dispatch(testConnection(row.id)),
                        },
                        {
                            label: __("Details", "zaplane"),
                            icon: <Icon as={FiEye} />,
                            type: "button",
                            onClick: () => openDetails(row),
                        },
                        {
                            label: __("Delete", "zaplane"),
                            icon: <Icon as={FiTrash2} />,
                            type: "button",
                            suffix: "trash",
                            hasBorder: false,
                            onClick: () => {
                                if (
                                    window.confirm(
                                        __("Are you sure you want to permanently delete ?", "zaplane")
                                    )
                                ) {
                                    dispatch(deleteConnection(row.id));
                                }
                            },
                        },
                    ]}
                />
            ),
            // columnWidth: "100px",
            textAlign: "center",
        },
    ];
    return (
        <>
            <ListTable
                columns={columns}
                data={allConnection}
                isRowSelectable={true}
                showSubHeader={false}
                showColumnFilter={false}
                showPagination={ allConnection.length >= 10 }
                noDataText={__("No connections found", "zaplane")}
                totalItems={totalItems}
                dataFetchingStatus={loading}
                suffix="connection-table"
                currentPageNumber={currentPage}
                perPage={perPage}
                onChangePage={handlePageChange}
                onChangeItemsPerPage={handlePerPageChange}
            />

            <ConnectionDetails
                isOpen={detailsOpen}
                onClose={() => setDetailsOpen(false)}
                connection={connection}
            />
        </>
    );
};

export default ConnectionTable;
