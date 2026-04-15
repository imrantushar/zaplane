// Connections.jsx

import { Box, Button, VStack, Text, Flex } from "@chakra-ui/react";
import { __ } from "@wordpress/i18n";
import { FaSlack } from "react-icons/fa";
import { IoIosArrowForward } from "react-icons/io";

import TopBar from "@ZAPComponents/TopBar";
import SubTopBar from "@ZAPComponents/SubTopBar";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";
import ZAPInput from "@ZAPComponents/ZAPInput";
import ConnectionTable from "./ConnectionTable";
import { primaryBtn } from "../../../../../assets/scss/chakra/recipe";
import { formatLabel, plugin_root_url } from "@ZAPUtils/helper";
import ZAPDrawer from "@ZAPComponents/Drawer";
import DrawerItemList from "../workflows/workFlowMotion/ActionDrawer/DrawerItemList";
import useConnection from "@ZAPHooks/useConnection/useConnection";
import ZAPLoading from "@ZAPComponents/Loading";


const Connections = () => {
    const {
        isDrawerOpen,
        drawerStep,
        selectedAuthType,
        credentials,
        loadingOAuth,
        authFields,
        authTypes,
        appList,
        drawerTitle,
        openDrawer,
        closeDrawer,
        selectApp,
        goBack,
        selectAuthType,
        updateCredential,
        saveConnection,
        loading
    } = useConnection();

    return (
        <>
            <TopBar
                leftContent={() => (
                    <>
                        <Flex height="40px" width="40px" borderRadius="20px" gap="10px"
                            background="var(--zaplane-second-primary)" alignItems="center" justifyContent="center">
                            <img src={`${plugin_root_url}assets/images/zaplane.svg`} />
                        </Flex>
                        <IoIosArrowForward />
                        <ZAPLabel as="h2" color="var(--zapplane-font-color)"
                            type="subtitle" fontWeight="medium" label={__("Connections", "zaplane")} />
                    </>
                )}
            />

            <SubTopBar heading={__("Dashboard", "zaplane")}>
                <Button {...primaryBtn} leftIcon={<FaSlack />} onClick={openDrawer}>
                    {__("Create credential", "zaplane")}
                </Button>
            </SubTopBar>

            <div className="zaplane-page-content">
                <ConnectionTable />
            </div>

            <ZAPDrawer
                open={isDrawerOpen}
                onClose={closeDrawer}
                title={__(drawerTitle, "zaplane")}
                size="md"
                placement="end"
                closeOnOverlayClick
                arrowClose={drawerStep === "configure"}
                arrowOnClick={goBack}
                footer={
                    drawerStep === "configure" && selectedAuthType
                        ? () => (
                            <Button {...primaryBtn} width="220px"
                                onClick={saveConnection}
                                isLoading={loadingOAuth}
                                isDisabled={!selectedAuthType}>
                                {__("Save Connection", "zaplane")}
                            </Button>
                        )
                        : null
                }
            >
                {drawerStep === "select" && (
                    <DrawerItemList list={appList} setSelectedItem={selectApp} />
                )}

                {drawerStep === "configure" && (
                    <Box>
                        <VStack spacing={4} align="stretch">
                            {Object.keys(authTypes).length > 1 && (
                                <Flex direction="column" gap={2}>
                                    <Text className="zaplane-label">
                                        {__("Select Auth Type", 'zaplane')}
                                    </Text>
                                    {Object.keys(authTypes).map((key) => (
                                        <Button
                                            key={key}
                                            className={selectedAuthType === key ? "zaplane-button-active" : ""}
                                            variant={selectedAuthType === key ? "solid" : "outline"}
                                            onClick={() => selectAuthType(key)}
                                        >
                                            {formatLabel(key)}
                                        </Button>
                                    ))}
                                </Flex>
                            )}

                            {authFields?.auth_fields && selectedAuthType ? (
                                <VStack spacing={3} align="stretch" pt={2}>
                                    {Object.entries(authFields.auth_fields).map(([fieldKey, field]) => (
                                        <Flex direction="column" gap="4px" key={fieldKey}>
                                            <ZAPInput
                                                label={field.label}
                                                type={field.type === "password" ? "password" : "text"}
                                                placeholder={field.placeholder || ""}
                                                value={credentials[fieldKey] || ""}
                                                onChange={(e) => updateCredential(fieldKey, e.target.value)}
                                            />

                                            {field.help && (
                                                <Text
                                                    fontSize="sm"
                                                    mt="7px"
                                                    className="zaplane-sub-title"
                                                    color="var(--zaplane-text-muted)"
                                                >
                                                    {__(field.help, "zaplane")}
                                                </Text>
                                            )}
                                        </Flex>
                                    ))}
                                </VStack>
                            ) : (
                                <ZAPLoading />
                            )}
                        </VStack>
                    </Box>
                )}
            </ZAPDrawer>
        </>
    );
};

export default Connections;