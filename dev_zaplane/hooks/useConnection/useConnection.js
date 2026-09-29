import { useEffect, useMemo, useState, useCallback } from "react";
import { useDispatch, useSelector } from "react-redux";

import {
    fetchConnections,
    fetchAuthFields,
    initOAuth,
    createTokenConnection,
    updateConnection,
    resetAuthFields,
} from "@ZAPRedux/Slices/connectionsSlice/connectionsSlice";
import { integrations } from "@ZAPUtils/helper";
import { showNotification } from "@ZAPRedux/Slices/notificationSlice/notificationSlice";

const INITIAL_STATE = {
    drawerStep: "select",
    selectedApp: null,
    selectedAuthType: null,
    credentials: {},
    search: "",
    editingConnectionId: null,
};

const useConnection = () => {
    const dispatch = useDispatch();
    const { authFields, loading } = useSelector((state) => state.connections || []);
    const [isDrawerOpen, setIsDrawerOpen] = useState(false);
    const [loadingOAuth, setLoadingOAuth] = useState(false);
    const [{ drawerStep, selectedApp, selectedAuthType, credentials, search, editingConnectionId }, setDrawerState] =
        useState(INITIAL_STATE);


    const patchState = useCallback(
        (patch) => setDrawerState((prev) => ({ ...prev, ...patch })),
        []
    );

    const resetDrawer = useCallback(() => {
        setDrawerState(INITIAL_STATE);
        dispatch(resetAuthFields());
    }, [dispatch]);



    const appList = useMemo(
        () =>
            Object.values(integrations.apps)
                .filter((app) => app.requires_connection === true)
                .map((app) => ({
                    id: app.slug,
                    name: app.name,
                    icon: app.icon ?? null,
                    type: "app",
                })),
        []
    );

    const searchList = useMemo(() => {
        if (!search) return [];
        return appList.filter((app) =>
            app.name.toLowerCase().includes(search.toLowerCase())
        );
    }, [appList, search]);

    const setDrawerSearch = useCallback(
        (value) => patchState({ search: value }),
        [patchState]
    );


    useEffect(() => {
        if (!selectedApp) return;
        dispatch(
            fetchAuthFields({
                app: selectedApp.id,
                authType: selectedAuthType || undefined,
            })
        );
    }, [selectedApp, selectedAuthType, dispatch]);


    useEffect(() => {
        if (!authFields || selectedAuthType) return;

        const types = Object.keys(authFields?.available_auth_types || {});
        let resolved = null;

        if (types.length === 1) resolved = types[0];
        else if (types.includes("oauth2")) resolved = "oauth2";
        else if (authFields?.auth_type) resolved = authFields.auth_type;

        if (resolved) patchState({ selectedAuthType: resolved });
    }, [authFields]);


    const openDrawer = useCallback(() => setIsDrawerOpen(true), []);

    const closeDrawer = useCallback(() => {
        setIsDrawerOpen(false);
        resetDrawer();
    }, [resetDrawer]);

    const selectApp = useCallback(
        (item) => {
            resetDrawer();
            patchState({ drawerStep: "configure", selectedApp: item });
        },
        [resetDrawer, patchState]
    );

    // Re-enter credentials for an existing connection (e.g. a rotated/expired
    // token) instead of deleting and recreating it. We never prefill the
    // secret fields — the API never returns decrypted credentials — so the
    // user re-enters the full credential set, same as on create.
    const startEdit = useCallback(
        (connection) => {
            resetDrawer();
            const app = integrations.apps?.[connection.app];
            setIsDrawerOpen(true);
            patchState({
                drawerStep: "configure",
                selectedApp: {
                    id: connection.app,
                    name: app?.name || connection.name,
                    icon: connection.icon || app?.icon || null,
                },
                selectedAuthType: connection.auth_type,
                editingConnectionId: connection.id,
            });
        },
        [resetDrawer, patchState]
    );

    const goBack = useCallback(() => resetDrawer(), [resetDrawer]);

    const selectAuthType = useCallback(
        (key) => patchState({ selectedAuthType: key, credentials: {} }),
        [patchState]
    );

    const updateCredential = useCallback(
        (fieldKey, value) =>
            patchState({ credentials: { ...credentials, [fieldKey]: value } }),
        [credentials, patchState]
    );


    const openOAuthPopup = useCallback((authUrl) => {
        return new Promise((resolve, reject) => {
            const width = 600;
            const height = 700;
            const left = window.screenX + (window.outerWidth - width) / 2;
            const top = window.screenY + (window.outerHeight - height) / 2;

            const popup = window.open(
                authUrl,
                "oauth_popup",
                `width=${width},height=${height},left=${left},top=${top}`
            );

            if (!popup) {
                reject(new Error("Popup was blocked by the browser."));
                return;
            }

            const handler = (event) => {
                if (event.data?.type !== "zaplane_oauth_callback") return;

                window.removeEventListener("message", handler);
                popup.close();

                event.data.data?.success
                    ? resolve(event.data.data)
                    : reject(new Error(event.data.data?.message || "OAuth authentication failed."));
            };

            window.addEventListener("message", handler);
        });
    }, []);


    const saveConnection = useCallback(async () => {
        if (!selectedApp || !selectedAuthType) return;
        if (loadingOAuth) return;


        if (selectedAuthType === "oauth2") {
            try {
                setLoadingOAuth(true);

                const res = await dispatch(
                    initOAuth({
                        app: selectedApp.id,
                        name: selectedApp.name,
                        icon:selectedApp.icon,
                        credentials,
                    })
                ).unwrap();

                await openOAuthPopup(res.auth_url);
                dispatch(fetchConnections());
                closeDrawer();
            } catch (e) {
                console.error("OAuth error:", e);
                dispatch(
                    showNotification({
                        message: e.message || "OAuth authentication failed.",
                        isShow: true,
                        type: "error",
                    })
                );
            } finally {
                setLoadingOAuth(false);
            }
        } else if (editingConnectionId) {
            const result = await dispatch(
                updateConnection({
                    id: editingConnectionId,
                    payload: { credentials },
                })
            );
            if (result.type === "connections/updateConnection/fulfilled") {
                dispatch(fetchConnections());
                closeDrawer();
            }
        } else {
            const result = await dispatch(
                createTokenConnection({
                    app: selectedApp.id,
                    name: selectedApp.name,
                    icon:selectedApp.icon,
                    authType: selectedAuthType,
                    credentials,
                })
            );
            if (result.type === "connections/createTokenConnection/fulfilled") {
                dispatch(fetchConnections());
                closeDrawer();
            }
        }
    }, [selectedApp, selectedAuthType, credentials, editingConnectionId, dispatch, openOAuthPopup, closeDrawer, loadingOAuth]);


    const authTypes = authFields?.available_auth_types || {};

    const drawerTitle =
        drawerStep === "select"
            ? "Select an app"
            : editingConnectionId
                ? `Update ${selectedApp?.name ?? ""} credentials`
                : selectedApp?.name ?? "Configure connection";

    return {
        isDrawerOpen,
        drawerStep,
        selectedApp,
        selectedAuthType,
        credentials,
        loadingOAuth,
        authFields,
        authTypes,
        appList,
        drawerTitle,
        loading,
        editingConnectionId,
        openDrawer,
        closeDrawer,
        selectApp,
        startEdit,
        goBack,
        selectAuthType,
        updateCredential,
        saveConnection,
        search,
        searchList,
        setDrawerSearch,
    };
};

export default useConnection;