import { useEffect, useState } from "react";
import { useDispatch, useSelector } from "react-redux";
import {
    Box,
    Button,
    VStack,
    HStack,
    Text,
    Badge,
    IconButton,
    Flex,
    Input,
    Spinner,
} from "@chakra-ui/react";
import { __ } from "@wordpress/i18n";
import { FaSlack } from "react-icons/fa";
import { FiTrash2, FiRefreshCw } from "react-icons/fi";
import Select from "react-select";

import {
    fetchConnections,
    fetchAuthFields,
    initOAuth,
    createTokenConnection,
    testConnection,
    deleteConnection,
} from "@ZAPRedux/Slices/connectionsSlice/connectionsSlice";

import WPModal from "@ZAPComponents/Modal/WPModal";
import ZAPText from "@ZAPComponents/Text";
import ZAPTable from "@ZAPComponents/Table";

const Connections = () => {
    const dispatch = useDispatch();
    const connections = useSelector((state) => state.connections?.list || []);
    const { authFields, isLoading } = useSelector((state) => state.connections);

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [selectedApp, setSelectedApp] = useState(null);
    const [selectedAuthType, setSelectedAuthType] = useState(null);
    const [credentials, setCredentials] = useState({});
    const [loadingOAuth, setLoadingOAuth] = useState(false);
    const [loadingFields, setLoadingFields] = useState(false);
    useEffect(() => {
        dispatch(fetchConnections());
    }, [dispatch]);

    useEffect(() => {
        if (!selectedApp) return;

        setLoadingFields(true);
        const type = selectedAuthType || undefined;

        dispatch(fetchAuthFields({ app: selectedApp.value, authType: type }))
        // .unwrap()
        // .then((data) => {
        //     if (!selectedAuthType && data.auth_type === "both") {
        //         setSelectedAuthType("api_key");
        //     } else if (!selectedAuthType) {
        //         setSelectedAuthType(data.auth_type);
        //     }
        // })
        // .finally(() => setLoadingFields(false));
    }, [selectedApp, selectedAuthType, dispatch]);

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

                const popup = window.open(res.auth_url, "oauth_popup", "width=600,height=700");

                const handler = (event) => {
                    if (event.data?.type === "zaplane_oauth_callback") {
                        window.removeEventListener("message", handler);
                        popup?.close();

                        if (event.data.data?.success) {
                            dispatch(fetchConnections());
                            setIsModalOpen(false);
                            setSelectedApp(null);
                            setSelectedAuthType(null);
                            setCredentials({});
                        }
                    }
                };

                window.addEventListener("message", handler);
            } catch (e) {
                console.error("OAuth failed", e);
            } finally {
                setLoadingOAuth(false);
            }
        } else {
            try {
                await dispatch(
                    createTokenConnection({
                        app: selectedApp.value,
                        name: selectedApp.label,
                        authType: selectedAuthType,
                        credentials,
                    })
                ).unwrap();

                dispatch(fetchConnections());
                setIsModalOpen(false);
                setSelectedApp(null);
                setSelectedAuthType(null);
                setCredentials({});
            } catch (e) {
                console.error("Token connection failed", e);
            }
        }
    };
    const authTypes = authFields?.available_auth_types || {};
    console.log(authFields, 'fileddddd auth')
    return (
        <Box p={6} borderWidth="1px" borderRadius="md" boxShadow="sm">
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

                {connections.length === 0 ? (
                    <Text color="gray.500">{__("No connections found", "zaplane")}</Text>
                ) : (
                    <ZAPTable
                        data={connections}
                        rowKey="id"
                        variant="outline"
                        size="sm"
                        columns={[
                            {
                                label: "APP / NAME",
                                key: "name",
                                render: (row) => (
                                    <HStack spacing={3}>
                                        {row.app === "slack" && <FaSlack color="#4A154B" />}
                                        <Text fontSize="sm" fontWeight="medium">
                                            {row.name}
                                        </Text>
                                    </HStack>
                                ),
                            },
                            {
                                label: "AUTH TYPE",
                                key: "auth_type",
                                render: (row) => <Text fontSize="sm">{row.auth_type || "--"}</Text>,
                            },
                            {
                                label: "STATUS",
                                key: "status",
                                render: (row) => (
                                    <Badge colorScheme={row.status === "active" ? "green" : "gray"}>
                                        {row.status}
                                    </Badge>
                                ),
                            },
                            {
                                label: "LAST USED",
                                key: "last_used_at",
                                render: (row) => <Text fontSize="sm">{row.last_used_at || "--"}</Text>,
                            },
                            {
                                label: "LAST TESTED",
                                key: "last_tested_at",
                                render: (row) => <Text fontSize="sm">{row.last_tested_at || "--"}</Text>,
                            },
                            {
                                label: "LAST TEST STATUS",
                                key: "last_test_status",
                                render: (row) => (
                                    <Badge colorScheme={row.last_test_status === "success" ? "green" : "red"}>
                                        {row.last_test_status || "--"}
                                    </Badge>
                                ),
                            },
                            {
                                label: "CREATED AT",
                                key: "created_at",
                                render: (row) => <Text fontSize="sm">{row.created_at || "--"}</Text>,
                            },
                        ]}
                        actionsRenderer={(row) => (
                            <>
                                <Button
                                    size="xs"
                                    variant="outline"
                                    leftIcon={<FiRefreshCw />}
                                    onClick={() => dispatch(testConnection(row.id))}
                                >
                                    Test
                                </Button>

                                <Button
                                    size="xs"
                                    colorScheme="red"
                                    leftIcon={<FiTrash2 />}
                                    onClick={() => {
                                        const confirmDelete = window.confirm(
                                            "Are you sure you want to delete this connection? This action cannot be undone."
                                        );
                                        if (confirmDelete) {
                                            dispatch(deleteConnection(row.id));
                                        }
                                    }}
                                >
                                    Delete
                                </Button>
                            </>
                        )}
                    />
                )}
            </VStack>
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
