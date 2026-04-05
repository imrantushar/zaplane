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
    Image,
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
import { outlineBtn, primaryBtn } from "../../../../../assets/scss/chakra/recipe";
import ConnectionTable from "./ConnectionTable";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";
import { formatLabel, plugin_root_url } from "@ZAPUtils/helper";
import ZAPInput from "@ZAPComponents/ZAPInput";
import { IoIosArrowForward } from "react-icons/io";
import SubTopBar from "@ZAPComponents/SubTopBar";
import { FiHelpCircle } from "react-icons/fi";


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
                leftContent={() => (
                    <>
                        <Flex height='40px' width='40px' borderRadius='20px' gap='10px' background='var(--zaplane-second-primary)' alignItems='center' justifyContent='center'>
                            <Image
                                src={`${plugin_root_url}assets/images/zaplane.svg`}
                                boxSize="20px"
                            />
                        </Flex>
                        <IoIosArrowForward />
                        <ZAPLabel
                            as="h2"
                            color="var(--zapplane-font-color)"
                            type="subtitle"
                            fontWeight="medium"
                            label={__('Connections ', 'zaplane')}
                        />
                        {/* <Box>
                            <ZAPLabel
                                label={__('Flows', 'zaplane')}
                                variant="bold"
                            />
                            <Text className="zaplane-sub-title" color="var(--zaplane-text-muted)">
                                {__("Connections between your apps", "zaplane")}
                            </Text>
                        </Box> */}
                    </>

                )}
                rightContent={() => (
                    <Flex gap={3} alignItems="center">
                        <Button
                            {...outlineBtn}
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--zaplane-font-color)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><g transform="scale(0.9) translate(1.5,1.5)"><path d="M11 6a13 13 0 0 0 8.4-2.8A1 1 0 0 1 21 4v12a1 1 0 0 1-1.6.8A13 13 0 0 0 11 14H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z"></path><path d="M6 14a12 12 0 0 0 2.4 7.2 2 2 0 0 0 3.2-2.4A8 8 0 0 1 10 14"></path><path d="M8 6v8"></path></g></svg>
                            {__("What's New")}
                        </Button>
                        <Button
                            {...outlineBtn}
                            onClick={() => {
                                window.open('https://zaplane.com/', '_blank');
                            }}
                        >
                            <FiHelpCircle color='var(--zaplane-font-color)' />
                            {__("Help")}
                        </Button>
                    </Flex>
                )}
            />
            <SubTopBar heading={__("Dashboard", "zaplane")}>
                <Button
                    {...primaryBtn}
                    leftIcon={<FaSlack />}
                    onClick={() => setIsModalOpen(true)}
                >
                    {__("Create credential", "zaplane")}
                </Button>
            </SubTopBar>
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
