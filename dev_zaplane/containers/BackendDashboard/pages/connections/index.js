import { useEffect, useState } from "react";
import { useDispatch, useSelector } from "react-redux";
import {
    Box,
    Button,
    VStack,
    HStack,
    Text,
    Badge,
    Flex,
    Input,
    Spinner,
} from "@chakra-ui/react";
import { __ } from "@wordpress/i18n";
import { FaSlack } from "react-icons/fa";
import {
    FiTrash2, FiRefreshCw, FiEye, FiLink,
    FiLock,
    FiCalendar,
    FiClock,
    FiCheckCircle,
    FiEdit,
    FiPlay
} from "react-icons/fi";
import Select from "react-select";

import {
    fetchConnections,
    fetchAuthFields,
    initOAuth,
    createTokenConnection,
    testConnection,
    deleteConnection,
    fetchSingleConnection,
    updateConnection,
} from "@ZAPRedux/Slices/connectionsSlice/connectionsSlice";

import WPModal from "@ZAPComponents/Modal/WPModal";
import ZAPText from "@ZAPComponents/Text";
import ZAPTable from "@ZAPComponents/Table";

const statusOptions = [
    { value: "active", label: "Active" },
    { value: "inactive", label: "Inactive" },
];

const Connections = () => {
    const dispatch = useDispatch();

    const connections = useSelector(
        (state) => state.connections?.list || []
    );
    const singleData = useSelector(
        (state) => state.connections?.singleData
    );

    const { authFields } = useSelector(
        (state) => state.connections
    );

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [detailsOpen, setDetailsOpen] = useState(false);

    const [selectedApp, setSelectedApp] = useState(null);
    const [selectedAuthType, setSelectedAuthType] = useState(null);
    const [credentials, setCredentials] = useState({});
    const [loadingOAuth, setLoadingOAuth] = useState(false);

    useEffect(() => {
        dispatch(fetchConnections());
    }, [dispatch]);

    useEffect(() => {
        if (!selectedApp) return;

        dispatch(
            fetchAuthFields({
                app: selectedApp.value,
                authType: selectedAuthType || undefined,
            })
        );
    }, [selectedApp, selectedAuthType, dispatch]);

    const handleStatusChange = (row, selected) => {
        dispatch(
            updateConnection({
                id: row.id,
                payload: { status: selected.value },
            })
        );
    };
    const openDetails = (row) => {
        dispatch(fetchSingleConnection(row.id));
        setDetailsOpen(true);
    };
    const handleConnect = async () => {
        if (!selectedApp || !selectedAuthType) return;

        if (selectedAuthType === "oauth2") {
            try {
                setLoadingOAuth(true);

                const res = await dispatch(
                    initOAuth({
                        app: selectedApp.value,
                        name: selectedApp.label,
                        credentials,
                    })
                ).unwrap();

                const popup = window.open(
                    res.auth_url,
                    "oauth_popup",
                    "width=600,height=700"
                );

                const handler = (event) => {
                    if (event.data?.type === "zaplane_oauth_callback") {
                        window.removeEventListener("message", handler);
                        popup?.close();

                        if (event.data.data?.success) {
                            dispatch(fetchConnections());
                            setIsModalOpen(false);
                        }
                    }
                };

                window.addEventListener("message", handler);
            } catch (e) {
                console.error(e);
            } finally {
                setLoadingOAuth(false);
            }
        } else {
            await dispatch(
                createTokenConnection({
                    app: selectedApp.value,
                    name: selectedApp.label,
                    authType: selectedAuthType,
                    credentials,
                })
            );
            dispatch(fetchConnections());
            setIsModalOpen(false);
        }
    };

    const authTypes = authFields?.available_auth_types || {};

    return (
        <Box p={6} borderWidth="1px" borderRadius="md">
            <VStack align="stretch" spacing={6}>
                <HStack justify="space-between">
                    <Text fontSize="xl" fontWeight="bold">
                        {__("Connections", "zaplane")}
                    </Text>

                    <Button
                        leftIcon={<FaSlack />}
                        variant="outline"
                        onClick={() => setIsModalOpen(true)}
                    >
                        {__("Create credential", "zaplane")}
                    </Button>
                </HStack>

                <ZAPTable
                    data={connections}
                    rowKey="id"
                    size="sm"
                    columns={[
                        {
                            label: "APP / NAME",
                            key: "name",
                            render: (row) => (
                                <HStack>
                                    {row.app === "slack" && <FaSlack />}
                                    <Text>{row.name}</Text>
                                </HStack>
                            ),
                        },
                        {
                            label: "AUTH TYPE",
                            key: "auth_type",
                            render: (row) => row.auth_type,
                        },
                        {
                            label: "CREATED AT",
                            key: "created_at",
                            render: (row) => row.created_at || "--",
                        },
                        {
                            label: "STATUS",
                            key: "status",
                            render: (row) => (
                                <Box w="140px">
                                    <Select
                                        options={statusOptions}
                                        value={statusOptions.find(
                                            (o) => o.value === row.status
                                        )}
                                        onChange={(s) =>
                                            handleStatusChange(row, s)
                                        }
                                        isSearchable={false}
                                    />
                                </Box>
                            ),
                        },
                    ]}
                    actionsRenderer={(row) => (
                        <> <Button
                            size="xs"
                            onClick={() => dispatch(testConnection(row.id))}
                            leftIcon={<FiRefreshCw />}
                        >
                            Test
                        </Button>
                            <Button
                                size="xs"
                                onClick={() => openDetails(row)}
                                leftIcon={<FiEye />}
                            >
                                Details
                            </Button>
                            <Button
                                size="xs"
                                colorScheme="red"
                                leftIcon={<FiTrash2 />}
                                onClick={() =>
                                    dispatch(deleteConnection(row.id))
                                }
                            >
                                Delete
                            </Button>
                        </>
                    )}
                />
            </VStack>
            <WPModal
                title={__("Connection Details", "zaplane")}
                isOpen={detailsOpen}
                onRequestClose={() => setDetailsOpen(false)}
            >
                {!singleData ? (
                    <Flex justify="center" align="center" py={12}>
                        <Spinner size="lg" />
                    </Flex>
                ) : (
                    <Box>
                        {/* Top Card */}
                        <Box
                            p={5}
                            borderRadius="lg"
                            bg="white"
                            borderWidth="1px"
                            mb={5}
                            boxShadow="sm"
                        >
                            <Flex justify="space-between" align="center">
                                <Box>
                                    <ZAPText fontSize="xl" fontWeight="semibold">
                                        {singleData.name}
                                    </ZAPText>
                                    <ZAPText fontSize="sm" color="gray.500">
                                        {singleData.app} connection
                                    </ZAPText>
                                </Box>

                                <Badge
                                    px={4}
                                    py={1.5}
                                    fontSize="sm"
                                    borderRadius="full"
                                    colorScheme={
                                        singleData.status === "active"
                                            ? "green"
                                            : "gray"
                                    }
                                    textTransform="capitalize"
                                >
                                    {singleData.status}
                                </Badge>
                            </Flex>
                        </Box>
                        <Flex gap={4} wrap="wrap">
                            <Box
                                flex="1 1 45%"
                                p={4}
                                borderRadius="lg"
                                borderWidth="1px"
                                bg="gray.50"
                            >
                                <ZAPText fontSize="xs" color="gray.500">
                                    AUTH TYPE
                                </ZAPText>
                                <ZAPText fontSize="md" fontWeight="medium">
                                    {singleData.auth_type}
                                </ZAPText>
                            </Box>

                            <Box
                                flex="1 1 45%"
                                p={4}
                                borderRadius="lg"
                                borderWidth="1px"
                                bg="gray.50"
                            >
                                <ZAPText fontSize="xs" color="gray.500">
                                    CREATED AT
                                </ZAPText>
                                <ZAPText fontSize="md" fontWeight="medium">
                                    {singleData.created_at}
                                </ZAPText>
                            </Box>

                            <Box
                                flex="1 1 45%"
                                p={4}
                                borderRadius="lg"
                                borderWidth="1px"
                                bg="gray.50"
                            >
                                <ZAPText fontSize="xs" color="gray.500">
                                    LAST USED
                                </ZAPText>
                                <Text fontSize="md" fontWeight="medium">
                                    {singleData.last_used_at || "--"}
                                </Text>
                            </Box>

                            <Box
                                flex="1 1 45%"
                                p={4}
                                borderRadius="lg"
                                borderWidth="1px"
                                bg="gray.50"
                            >
                                <ZAPText fontSize="xs" color="gray.500">
                                    LAST TESTED
                                </ZAPText>
                                <ZAPText fontSize="md" fontWeight="medium">
                                    {singleData.last_tested_at || "--"}
                                </ZAPText>
                            </Box>
                        </Flex>
                    </Box>
                )}
            </WPModal>
            <WPModal
                title={__("Create credential", "zaplane")}
                isOpen={isModalOpen}
                onRequestClose={() => setIsModalOpen(false)}
                size="medium"
            >
                <Box px={4}>
                    <VStack spacing={4} align="stretch">
                        <Text>{__("Select an app or service to connect", "zaplane")}</Text>

                        <Select
                            value={selectedApp}
                            onChange={(val) => {
                                setSelectedApp(val);
                                setSelectedAuthType(null);
                                setCredentials({});
                            }}
                            options={[{ value: "slack", label: "Slack" }]}
                        />
                        {Object.keys(authTypes).map((key) => (
                            <Button
                                key={key}
                                variant={selectedAuthType === key ? "solid" : "outline"}
                                colorScheme="blue"
                                onClick={() => {
                                    setSelectedAuthType(key);
                                    setCredentials({});
                                }}
                            >
                                {key}
                            </Button>
                        ))}
                        {authFields?.auth_fields && selectedAuthType && (
                            <VStack spacing={3} align="stretch" pt={3}>
                                {Object.entries(authFields.auth_fields).map(
                                    ([fieldKey, field]) => {
                                        const value = credentials[fieldKey] || "";

                                        return (
                                            <Box key={fieldKey}>
                                                <ZAPText fontWeight="bold">{field.label}</ZAPText>
                                                <Input
                                                    type={field.type === "password" ? "password" : "text"}
                                                    placeholder={field.placeholder || ""}
                                                    value={value}
                                                    onChange={(e) =>
                                                        setCredentials((prev) => ({
                                                            ...prev,
                                                            [fieldKey]: e.target.value,
                                                        }))
                                                    }
                                                />
                                                {field.help && (
                                                    <ZAPText fontSize="sm" color="gray.500">
                                                        {field.help}
                                                    </ZAPText>
                                                )}
                                            </Box>
                                        );
                                    }
                                )}
                            </VStack>
                        )}



                        <Button
                            width="220px"
                            colorScheme="blue"
                            onClick={handleConnect}
                            isLoading={loadingOAuth}
                            isDisabled={!selectedApp || !selectedAuthType}
                        >
                            {selectedAuthType === "oauth2"
                                ? __("Connect with OAuth", "zaplane")
                                : __("Save Connection", "zaplane")}
                        </Button>
                    </VStack>
                </Box>
            </WPModal>
        </Box>
    );
};

export default Connections;
