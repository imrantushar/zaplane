import { useEffect, useState } from "react";
import { useDispatch, useSelector } from "react-redux";
import {
    Box,
    Button,
    VStack,
    Text,
    Input,
    Heading,
    Flex,
} from "@chakra-ui/react";
import { __ } from "@wordpress/i18n";
import { FaSlack } from "react-icons/fa";
import Select from "react-select";

import {
    fetchConnections,
    fetchAuthFields,
    initOAuth,
    createTokenConnection,
} from "@ZAPRedux/Slices/connectionsSlice/connectionsSlice";

import WPModal from "@ZAPComponents/Modal/WPModal";
import TopBar from "@ZAPComponents/TopBar";
import { primaryBtn } from "../../../../../assets/scss/chakra/recipe";
import ConnectionTable from "./ConnectionTable";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";
import { formatLabel } from "@ZAPUtils/helper";
import ZAPInput from "@ZAPComponents/ZAPInput";


const Connections = () => {
    const dispatch = useDispatch();

    const { authFields } = useSelector(
        (state) => state.connections || []
    );
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [selectedApp, setSelectedApp] = useState(null);
    const [selectedAuthType, setSelectedAuthType] = useState(null);
    const [credentials, setCredentials] = useState({});
    const [loadingOAuth, setLoadingOAuth] = useState(false);


    useEffect(() => {
        if (!selectedApp) return;

        dispatch(
            fetchAuthFields({
                app: selectedApp.value,
                authType: selectedAuthType || undefined,
            })
        );
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
        <>
            <TopBar
                render={() => (
                    <Box>
                        <ZAPLabel
                            label={__('Flows', 'zaplane')}
                            variant="bold"
                        />
                        <Text className="zaplane-sub-title" color="var(--zaplane-text-muted)">
                            {__("Connections between your apps", "zaplane")}
                        </Text>
                    </Box>
                )}
                rightContent={() => (
                    <Button
                        {...primaryBtn}
                        leftIcon={<FaSlack />}
                        onClick={() => setIsModalOpen(true)}
                    >
                        {__("Create credential", "zaplane")}
                    </Button>
                )}
            />
            <div className="zaplane-page-content">
                <ConnectionTable />
            </div>

            <WPModal
                title={__("Create credential", "zaplane")}
                isOpen={isModalOpen}
                onRequestClose={() => setIsModalOpen(false)}
                size="medium"
            >
                <Box px={4}>
                    <VStack spacing={4} align="stretch">
                        <Text className="zaplane-label">{__("Select an app or service to connect", "zaplane")}</Text>

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
                                className={`${selectedAuthType === key && 'zaplane-button-actve'}`}
                                variant={selectedAuthType === key ? "solid" : "outline"}
                                onClick={() => {
                                    setSelectedAuthType(key);
                                    setCredentials({});
                                }}
                            >
                                {formatLabel(key)}
                            </Button>
                        ))}
                        {authFields?.auth_fields && selectedAuthType && (
                            <VStack spacing={3} align="stretch" pt={3}>
                                {Object.entries(authFields.auth_fields).map(
                                    ([fieldKey, field]) => {
                                        const value = credentials[fieldKey] || "";

                                        return (
                                            <Flex flexDirection="column" gap={"4px"} key={fieldKey}>
                                                <ZAPInput
                                                    label={field.label}
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
                                                    <Text fontSize="sm" mt='7px' className="zaplane-sub-title" color="var(--zaplane-text-muted)">
                                                        {__(field.help, "zaplane")}
                                                    </Text>
                                                )}
                                            </Flex>
                                        );
                                    }
                                )}
                            </VStack>
                        )}

                        {selectedAuthType &&
                            <Button
                                mt="16px"
                                {...primaryBtn}
                                width="220px"
                                onClick={handleConnect}
                                isLoading={loadingOAuth}
                                isDisabled={!selectedAuthType}
                            >
                                {__("Save Connection", "zaplane")}
                            </Button>
                        }

                    </VStack>
                </Box>
            </WPModal>
        </>
    );
};

export default Connections;
