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

const Connections = () => {
    const dispatch = useDispatch();
    const connections = useSelector((state) => state.connections?.list || []);
    const authFields = useSelector((state) => state.connections?.authFields || {});

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [selectedApp, setSelectedApp] = useState(null);
    const [selectedAuthType, setSelectedAuthType] = useState(null);
    const [credentials, setCredentials] = useState({});
    const [loadingOAuth, setLoadingOAuth] = useState(false);
    const [loadingFields, setLoadingFields] = useState(false);

    // Load connections
    useEffect(() => {
        dispatch(fetchConnections());
    }, [dispatch]);

    // Fetch auth fields when app or auth type changes
    useEffect(() => {
        if (!selectedApp) return;

        setLoadingFields(true);
        const type = selectedAuthType || undefined;

        dispatch(fetchAuthFields({ app: selectedApp.value, authType: type }))
            .unwrap()
            .then((data) => {
                if (!selectedAuthType && data.auth_type === "both") {
                    setSelectedAuthType("api_key");
                } else if (!selectedAuthType) {
                    setSelectedAuthType(data.auth_type);
                }
            })
            .finally(() => setLoadingFields(false));
    }, [selectedApp, selectedAuthType, dispatch]);

    // Update nested credentials
    const handleChangeCredential = (path, value) => {
        setCredentials((prev) => {
            const updated = { ...prev };
            let temp = updated;
            path.forEach((k, i) => {
                if (i === path.length - 1) {
                    temp[k] = value;
                } else {
                    temp[k] = temp[k] || {};
                    temp = temp[k];
                }
            });
            return updated;
        });
    };

    // Recursive renderer for fields
    const renderFields = (fields, path = [], level = 0) => {
        return Object.entries(fields).map(([key, field]) => {
            const currentPath = [...path, key];

            // If field is nested object (no type), render its children recursively
            if (typeof field === "object" && !field.type) {
                return (
                    <Box key={currentPath.join(".")} pl={level * 6} mb={2} borderLeft={level ? "2px solid #eee" : undefined}>
                        <Text fontWeight="bold" mb={1}>
                            {key} (Level {level + 1})
                        </Text>
                        {renderFields(field, currentPath, level + 1)}
                    </Box>
                );
            }

            // Normal input field
            const value = currentPath.reduce((acc, k) => acc?.[k] ?? "", credentials);

            return (
                <Box key={currentPath.join(".")} pl={level * 6} mb={2}>
                    <Text>{field.label} (Level {level + 1})</Text>
                    <Input
                        type={field.type === "password" ? "password" : "text"}
                        placeholder={field.placeholder || ""}
                        required={field.required}
                        value={value}
                        onChange={(e) => handleChangeCredential(currentPath, e.target.value)}
                    />
                    {field.help && (
                        <Text fontSize="xs" color="gray.500">
                            {field.help}
                        </Text>
                    )}
                </Box>
            );
        });
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

    return (
        <Box p={6} borderWidth="1px" borderRadius="md" boxShadow="sm">
            <VStack align="stretch" spacing={6}>
                {/* Header */}
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

                {/* Connections List */}
                {connections.length === 0 ? (
                    <Text color="gray.500">{__("No connections found", "zaplane")}</Text>
                ) : (
                    <VStack align="stretch" spacing={3}>
                        {connections.map((conn) => (
                            <Flex
                                key={conn.id}
                                p={4}
                                borderWidth="1px"
                                borderRadius="md"
                                justify="space-between"
                                align="center"
                            >
                                <HStack spacing={3}>
                                    <FaSlack color="#4A154B" />
                                    <Box>
                                        <Text fontWeight="medium">{conn.name}</Text>
                                        <Badge colorScheme={conn.status === "active" ? "green" : "gray"}>
                                            {conn.status}
                                        </Badge>
                                    </Box>
                                </HStack>

                                <HStack spacing={2}>
                                    <IconButton
                                        size="sm"
                                        icon={<FiRefreshCw />}
                                        aria-label="Test connection"
                                        onClick={() => dispatch(testConnection(conn.id))}
                                    />
                                    <IconButton
                                        size="sm"
                                        colorScheme="red"
                                        icon={<FiTrash2 />}
                                        aria-label="Delete connection"
                                        onClick={() => dispatch(deleteConnection(conn.id))}
                                    />
                                </HStack>
                            </Flex>
                        ))}
                    </VStack>
                )}
            </VStack>

            {/* Create Credential Modal */}
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

                        {/* Auth Type Selector */}
                        {authFields && Object.keys(authFields).length > 0 && (
                            <VStack align="stretch" spacing={2}>
                                {Object.keys(authFields).length > 1 && (
                                    <Text fontWeight="bold">{__("Authentication Method", "zaplane")}</Text>
                                )}
                                {Object.keys(authFields).map((key) => (
                                    <Button
                                        key={key}
                                        variant={selectedAuthType === key ? "solid" : "outline"}
                                        onClick={() => setSelectedAuthType(key)}
                                    >
                                        {key}
                                    </Button>
                                ))}
                            </VStack>
                        )}

                        {/* Dynamic Fields */}
                        {loadingFields ? <Spinner /> : selectedAuthType && authFields && renderFields(authFields)}

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
