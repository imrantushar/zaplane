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
} from "@chakra-ui/react";
import { __ } from "@wordpress/i18n";
import { FaSlack } from "react-icons/fa";
import { FiTrash2, FiRefreshCw } from "react-icons/fi";
import Select from "react-select";

import {
    fetchConnections,
    initOAuth,
    testConnection,
    deleteConnection,
} from "@ZAPRedux/Slices/connectionsSlice/connectionsSlice";

import WPModal from "@ZAPComponents/Modal/WPModal";

const Connections = () => {
    const dispatch = useDispatch();

    const connections = useSelector(
        (state) => state.connections?.list || []
    );

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [selectedApp, setSelectedApp] = useState(null);
    const [loadingOAuth, setLoadingOAuth] = useState(false);

    // Load connections
    useEffect(() => {
        dispatch(fetchConnections());
    }, [dispatch]);

    // Start OAuth
    const connectionAuth = async () => {
        if (!selectedApp) return;

        try {
            setLoadingOAuth(true);

            const res = await dispatch(
                initOAuth({
                    app: selectedApp.value,
                    name: selectedApp.label, // ✅ correct name
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
                        setSelectedApp(null);
                    }
                }
            };

            window.addEventListener("message", handler);
        } catch (e) {
            console.error("OAuth failed", e);
        } finally {
            setLoadingOAuth(false);
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
                    <Text color="gray.500">
                        {__("No connections found", "zaplane")}
                    </Text>
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
                                        <Text fontWeight="medium">
                                            {conn.name}
                                        </Text>
                                        <Badge
                                            colorScheme={
                                                conn.status === "active"
                                                    ? "green"
                                                    : "gray"
                                            }
                                        >
                                            {conn.status}
                                        </Badge>
                                    </Box>
                                </HStack>

                                <HStack spacing={2}>
                                    <IconButton
                                        size="sm"
                                        icon={<FiRefreshCw />}
                                        aria-label="Test connection"
                                        onClick={() =>
                                            dispatch(testConnection(conn.id))
                                        }
                                    />
                                    <IconButton
                                        size="sm"
                                        colorScheme="red"
                                        icon={<FiTrash2 />}
                                        aria-label="Delete connection"
                                        onClick={() =>
                                            dispatch(deleteConnection(conn.id))
                                        }
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
                        <Text>
                            {__(
                                "Select an app or service to connect",
                                "zaplane"
                            )}
                        </Text>

                        <Select
                            value={selectedApp}
                            onChange={setSelectedApp}
                            options={[
                                {
                                    value: "slack",
                                    label: "Slack OAuth2 API",
                                },
                            ]}
                        />

                        <Button
                            width="220px"
                            colorScheme="blue"
                            onClick={connectionAuth}
                            isLoading={loadingOAuth}
                            isDisabled={!selectedApp}
                        >
                            {__("Connect My Account", "zaplane")}
                        </Button>
                    </VStack>
                </Box>
            </WPModal>
        </Box>
    );
};

export default Connections;
