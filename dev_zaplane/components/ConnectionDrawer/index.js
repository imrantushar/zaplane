import { __ } from "@wordpress/i18n";
import ZAPInput from "@ZAPComponents/ZAPInput";
import ZAPSelect from "@ZAPComponents/ZAPSelect";
import ZAPDrawer from "@ZAPComponents/Drawer";
import WPModal from "@ZAPComponents/Modal/WPModal";
import "./styles.scss";
import ZAPLoading from "@ZAPComponents/Loading";
import SearchableDrawerList from "@ZAPComponents/SearchableDrawerList";
import { formatLabel } from "@ZAPUtils/helper";

/**
 * Create or edit a connection: pick the app, then its credentials (or sign
 * in with OAuth). Driven by the useConnection() hook, so the Connections
 * page and the workflow editor show the same form.
 */
const ConnectionDrawer = ({
    isDrawerOpen,
    drawerStep,
    selectedAuthType,
    credentials,
    loadingOAuth,
    authFields,
    authTypes,
    appList,
    drawerTitle,
    closeDrawer,
    selectApp,
    goBack,
    selectAuthType,
    updateCredential,
    saveConnection,
    editingConnectionId,
    search,
    setDrawerSearch,
    searchList,
    // Opened for one app (from a step): no way back to the app list.
    lockedApp = false,
    // "drawer" (the Connections page) or "modal" (over the workflow editor).
    variant = "drawer",
}) => {
    const footer = drawerStep === "configure" && selectedAuthType ? (
            <div className="flex items-center justify-end gap-3">
                <button
                    className="px-6 py-2 border border-[var(--zaplane-border-color)] rounded-lg text-[var(--zaplane-font-color)] font-medium hover:bg-[var(--zaplane-secondary-color)] transition-colors"
                    onClick={closeDrawer}
                >
                    {__("Cancel", "zaplane")}
                </button>
                <button
                    className="bg-[var(--zaplane-primary)] hover:opacity-90 text-white font-medium py-2 px-6 rounded-lg transition-all shadow-md active:scale-95 disabled:opacity-50"
                    onClick={saveConnection}
                    disabled={!selectedAuthType || loadingOAuth}
                >
                    {loadingOAuth
                        ? __("Connecting...", "zaplane")
                        : editingConnectionId
                            ? __("Update Connection", "zaplane")
                            : __("Save Connection", "zaplane")}
                </button>
            </div>
        ) : null;

    const body = (
        <>
        {drawerStep === "select" &&
            <SearchableDrawerList
                search={search}
                setSearch={setDrawerSearch}
                searchList={searchList}
                list={appList}
                onSelect={selectApp}
            />}

        {drawerStep === "configure" && (
            <div className="flex flex-col gap-8">
                {!editingConnectionId && Object.keys(authTypes).length > 1 && (
                    <div className="flex bg-[var(--zaplane-secondary-color)]/50 p-1.5 rounded-xl gap-2">
                        {Object.keys(authTypes).map(key => (
                            <button
                                key={key}
                                className={`flex-1 py-2.5 px-4 rounded-lg font-medium transition-all duration-200 ${selectedAuthType === key
                                    ? "bg-[var(--zaplane-second-primary)] text-[var(--zaplane-primary)] shadow-sm"
                                    : "bg-[var(--zaplane-background)] text-[var(--zaplane-font-secondary-color)] border border-[var(--zaplane-border-color)] hover:bg-[var(--zaplane-secondary-color)]"
                                    }`}
                                onClick={() => selectAuthType(key)}
                            >
                                {formatLabel(key)}
                            </button>
                        ))}
                    </div>
                )}

                {editingConnectionId && (
                    <p className="text-[13px] text-[var(--zaplane-text-muted)] leading-relaxed">
                        {__("For security, existing credentials aren't shown here. Re-enter all fields below to update this connection.", "zaplane")}
                    </p>
                )}

                {authFields?.auth_fields && selectedAuthType ? (
                    <div className="flex flex-col gap-6">
                        {(() => {
                            const fields = authFields.auth_fields;
                            // A field's value falls back to its declared default so
                            // depends_on and selects reflect the effective state even
                            // before the user touches anything.
                            const valueOf = key =>
                                credentials[key] ?? fields[key]?.default ?? "";
                            // depends_on: show only when every dependency matches. A
                            // dependency value may be a single value or a list of
                            // accepted values (e.g. api_key for anthropic|openai).
                            const isVisible = field => {
                                if (!field.depends_on) return true;
                                return Object.entries(field.depends_on).every(([k, v]) => {
                                    const cur = valueOf(k);
                                    return Array.isArray(v) ? v.includes(cur) : cur === v;
                                });
                            };
                            return Object.entries(fields)
                                .filter(([, field]) => isVisible(field))
                                .map(([fieldKey, field]) => (
                                    <div key={fieldKey} className="flex flex-col gap-2">
                                        {field.type === "select" ? (
                                            <ZAPSelect
                                                label={field.label}
                                                isRequired={field.required}
                                                options={field.options || []}
                                                value={valueOf(fieldKey)}
                                                placeholder={field.placeholder || ""}
                                                onChange={opt =>
                                                    updateCredential(fieldKey, opt?.value ?? "")
                                                }
                                            />
                                        ) : (
                                            <ZAPInput
                                                label={field.label}
                                                type={field.type === "password" ? "password" : "text"}
                                                placeholder={field.placeholder || ""}
                                                value={credentials[fieldKey] || ""}
                                                onChange={e => updateCredential(fieldKey, e.target.value)}
                                            />
                                        )}
                                        {field.help && (
                                            <span className="text-[13px] text-[var(--zaplane-text-muted)] leading-relaxed mt-0.5">
                                                {__(field.help, "zaplane")}
                                            </span>
                                        )}
                                    </div>
                                ));
                        })()}
                    </div>
                ) : (
                    <div className="py-12"><ZAPLoading /></div>
                )}
            </div>
        )}
        </>
    );

    if (variant === "modal") {
        return (
            <WPModal
                title={__(drawerTitle, "zaplane")}
                isOpen={isDrawerOpen}
                onRequestClose={closeDrawer}
                shouldCloseOnClickOutside={false}
                size="medium"
                suffix="connection"
            >
                <div className="zaplane-connection-modal">
                    {drawerStep === "configure" && !lockedApp && (
                        <button type="button" className="zaplane-connection-modal__back" onClick={goBack}>
                            ← {__("Choose another app", "zaplane")}
                        </button>
                    )}
                    <div className="zaplane-connection-modal__body">{body}</div>
                    {footer && <div className="zaplane-connection-modal__footer">{footer}</div>}
                </div>
            </WPModal>
        );
    }

    return (
        <ZAPDrawer
            open={isDrawerOpen}
            onClose={closeDrawer}
            title={__(drawerTitle, "zaplane")}
            size="md"
            placement="end"
            closeOnOverlayClick
            arrowClose={drawerStep === "configure" && !lockedApp}
            arrowOnClick={goBack}
            footer={footer}
        >
            {body}
        </ZAPDrawer>
    );
};

export default ConnectionDrawer;
