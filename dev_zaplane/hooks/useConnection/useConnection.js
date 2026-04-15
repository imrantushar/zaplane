import { useEffect, useMemo, useState, useCallback } from "react";
import { useDispatch, useSelector } from "react-redux";

import {
    fetchConnections,
    fetchAuthFields,
    initOAuth,
    createTokenConnection,
    resetAuthFields,
} from "@ZAPRedux/Slices/connectionsSlice/connectionsSlice";
import { integrations } from "@ZAPUtils/helper";

const INITIAL_STATE = {
    drawerStep: "select",
    selectedApp: null,
    selectedAuthType: null,
    credentials: {},
};

const useConnection = () => {
    const dispatch = useDispatch();
    const { authFields, loading } = useSelector((state) => state.connections || []);
    const [isDrawerOpen, setIsDrawerOpen] = useState(false);
    const [loadingOAuth, setLoadingOAuth] = useState(false);
    const [{ drawerStep, selectedApp, selectedAuthType, credentials }, setDrawerState] =
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
                    : reject(new Error("OAuth authentication failed."));
            };

            window.addEventListener("message", handler);
        });
    }, []);


    const saveConnection = useCallback(async () => {
        if (!selectedApp || !selectedAuthType) return;
        

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
            } finally {
                setLoadingOAuth(false);
            }
        } else {
            await dispatch(
                createTokenConnection({
                    app: selectedApp.id,
                    name: selectedApp.name,
                    icon:selectedApp.icon,
                    authType: selectedAuthType,
                    credentials,
                })
            );
            dispatch(fetchConnections());
            closeDrawer();
        }
    }, [selectedApp, selectedAuthType, credentials, dispatch, openOAuthPopup, closeDrawer]);


    const authTypes = authFields?.available_auth_types || {};

    const drawerTitle =
        drawerStep === "select"
            ? "Select an app"
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
        openDrawer,
        closeDrawer,
        selectApp,
        goBack,
        selectAuthType,
        updateCredential,
        saveConnection,
    };
};

export default useConnection;