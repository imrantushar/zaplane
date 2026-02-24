import { Text, Icon } from "@chakra-ui/react";
import { __ } from "@wordpress/i18n";
import { useDispatch, useSelector } from "react-redux";
import { useState } from "react";

import ListTable from "@ZAPComponents/ListTable";
import OptionMenu from "@ZAPComponents/OptionMenu";
import StatusOptions from "@ZAPComponents/StatusOptions";

import { FiTrash2, FiRefreshCw, FiEye } from "react-icons/fi";

import {
    deleteConnection,
    testConnection,
    fetchSingleConnection,
    updateConnection,
} from "@ZAPRedux/Slices/connectionsSlice/connectionsSlice";

import ConnectionDetails from "./ConnectionDetails/ConnectionDetails";

const ConnectionTable = () => {
    const dispatch = useDispatch();
    const [detailsOpen, setDetailsOpen] = useState(false);

    const { allConnection = [], isLoading, connection } = useSelector(
        (state) => state.connections
    );
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
                <Text className="zaplane-label">
                    {__("App / Name", "zaplane")}
                </Text>
            ),
            cell: (row) => (
                <Text className="zaplane-label" fontWeight="500">
                    {row.name}
                </Text>
            ),
            columnWidth: "150px",
            textAlign: "start",
        },
        {
            name: (
                <Text className="zaplane-label">
                    {__("Auth Type", "zaplane")}
                </Text>
            ),
            cell: (row) => (
                <Text fontSize="sm">{row.auth_type}</Text>
            ),
            columnWidth: "120px",
            textAlign: "center",
        },
        {
            name: (
                <Text className="zaplane-label">
                    {__("Created At", "zaplane")}
                </Text>
            ),
            cell: (row) => (
                <Text fontSize="sm">{row.created_at || "--"}</Text>
            ),
            columnWidth: "160px",
            textAlign: "center",
        },
        {
            name: (
                <Text className="zaplane-label">
                    {__("Status", "zaplane")}
                </Text>
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
            columnWidth: "170px",
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
            columnWidth: "100px",
            textAlign: "center",
        },
    ];

    return (
        <>
            <ListTable
                columns={columns}
                data={Array.isArray(allConnection) ? allConnection : []}
                isRowSelectable={true}
                showSubHeader={false}
                showColumnFilter={false}
                showPagination={false}
                noDataText={__("No connections found", "zaplane")}
                totalItems={allConnection?.length || 0}
                dataFetchingStatus={isLoading}
                suffix="connection-table"
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
