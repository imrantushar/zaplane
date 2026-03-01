import React, { useEffect, useState } from 'react';
import WPPopover from "@ZAPComponents/Popaver/WPPopover";
import { __ } from "@wordpress/i18n";
import { useDispatch, useSelector } from 'react-redux';
import { createTokenConnection, fetchAuthFields, fetchConnections, initOAuth } from '@ZAPRedux/Slices/connectionsSlice/connectionsSlice';
import { Button, Flex, Input, Text, VStack } from '@chakra-ui/react';
import { primaryBtn } from '../../../../../../../../../assets/scss/chakra/recipe';
import ZAPLabel from '@ZAPComponents/Labels/ZAPLabel';
import './styles.scss'
import { formatLabel } from '@ZAPUtils/helper';

const ConnectionPopaver = (props) => {
    const { isOpen, onClose, appSlug, selectedIntegration } = props
    const dispatch = useDispatch()
    const { authFields } = useSelector(
        (state) => state.connections || []
    )
    const [loadingOAuth, setLoadingOAuth] = useState(false);
    const [selectedAuthType, setSelectedAuthType] = useState(null);
    const [credentials, setCredentials] = useState({});
    useEffect(() => {
        dispatch(
            fetchAuthFields({
                app: appSlug,
                authType: selectedAuthType || undefined,
            })
        );
    }, [selectedAuthType, dispatch]);
    const handleConnect = async () => {
        if (!selectedAuthType) return;

        if (selectedAuthType === "oauth2") {
            try {
                setLoadingOAuth(true);

                const res = await dispatch(
                    initOAuth({
                        app: appSlug,
                        name: appSlug,
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
                        window.removeEventallConnectionener("message", handler);
                        popup?.close();

                        if (event.data.data?.success) {
                            dispatch(fetchConnections());
                            setIsModalOpen(false);
                        }
                    }
                };

                window.addEventallConnectionener("message", handler);
            } catch (e) {
                console.error(e);
            } finally {
                setLoadingOAuth(false);
            }
        } else {
            await dispatch(
                createTokenConnection({
                    app: appSlug,
                    name: appSlug,
                    authType: selectedAuthType,
                    credentials,
                })
            );
        }
    };

    const authTypes = authFields?.available_auth_types || {};
    return (
        <WPPopover isOpen={isOpen} onClose={onClose} title={__("Create New Connection", 'zaplane')}
            prefix='connection-popaver'>
            <Flex gap='24px'>
                {Object.keys(authTypes).map((key) => (
                    <Button
                       width='45%'
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
            </Flex>
            {authFields?.auth_fields && selectedAuthType && (
                <VStack spacing={3} align="stretch" pt={3}>
                    {Object.entries(authFields.auth_fields).map(
                        ([fieldKey, field]) => {
                            const value = credentials[fieldKey] || "";

                            return (
                                <Flex flexDirection="column" gap={"4px"} key={fieldKey}>
                                    <ZAPLabel label={field.label} type={"simple"} />
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
                                        <Text fontSize="sm" m='7px 0' className="zaplane-sub-title" color="var(--zaplane-text-muted)">

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
                    {...primaryBtn}
                    width="220px"
                    onClick={handleConnect}
                    isLoading={loadingOAuth}

                >
                    {selectedAuthType === "oauth2"
                        ? __("Connect with OAuth", "zaplane")
                        : __("Save Connection", "zaplane")}
                </Button>
            }
        </WPPopover>
    );
};

export default ConnectionPopaver;