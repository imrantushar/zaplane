import { __ } from "@wordpress/i18n";
import { FaSlack } from "react-icons/fa";
import ZAPInput from "@ZAPComponents/ZAPInput";
import ConnectionTable from "./ConnectionTable";
import { primaryBtn } from "../../../../../assets/scss/chakra/recipe";
import { formatLabel } from "@ZAPUtils/helper";
import ZAPDrawer from "@ZAPComponents/Drawer";
import useConnection from "@ZAPHooks/useConnection/useConnection";
import ZAPLoading from "@ZAPComponents/Loading";
import PageLayout from "@ZAPComponents/PageLayout";
import DrawerItemList from "@ZAPComponents/SearchableDrawerList/DrawerItemList";
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
  return <PageLayout 
            title="Connections" 
            heading="Connections" 
            actions={
                <button 
                    className="bg-[var(--zaplane-primary)] hover:opacity-90 text-white font-medium py-2 px-6 rounded-lg transition-all shadow-sm active:scale-95" 
                    onClick={openDrawer}
                >
                    {__("Create credential", "zaplane")}
                </button>
            }
        >
            <ConnectionTable />

            <ZAPDrawer 
                open={isDrawerOpen} 
                onClose={closeDrawer} 
                title={__(drawerTitle, "zaplane")} 
                size="md" 
                placement="end" 
                closeOnOverlayClick 
                arrowClose={drawerStep === "configure"} 
                arrowOnClick={goBack} 
                footer={drawerStep === "configure" && selectedAuthType ? (
                    <div className="flex items-center justify-end gap-3">
                        <button 
                            className="px-6 py-2 border border-[var(--zaplane-border-color)] rounded-lg text-[var(--zaplane-font-color)] font-medium hover:bg-gray-50 transition-colors" 
                            onClick={closeDrawer}
                        >
                            {__("Cancel", "zaplane")}
                        </button>
                        <button 
                            className="bg-[var(--zaplane-primary)] hover:opacity-90 text-white font-medium py-2 px-6 rounded-lg transition-all shadow-md active:scale-95 disabled:opacity-50"
                            onClick={saveConnection} 
                            disabled={!selectedAuthType}
                        >
                            {__("Save Connection", "zaplane")}
                        </button>
                    </div>
                ) : null}
            >
                {drawerStep === "select" && <div className="space-y-4 px-1"><DrawerItemList list={appList} setSelectedItem={selectApp} /></div>}

                {drawerStep === "configure" && (
                    <div className="flex flex-col gap-8">
                        {Object.keys(authTypes).length > 1 && (
                            <div className="flex bg-gray-50/50 p-1.5 rounded-xl gap-2">
                                {Object.keys(authTypes).map(key => (
                                    <button 
                                        key={key} 
                                        className={`flex-1 py-2.5 px-4 rounded-lg font-medium transition-all duration-200 ${
                                            selectedAuthType === key 
                                            ? "bg-[var(--zaplane-second-primary)] text-[var(--zaplane-primary)] shadow-sm" 
                                            : "bg-white text-gray-500 border border-gray-100 hover:bg-gray-50"
                                        }`}
                                        onClick={() => selectAuthType(key)}
                                    >
                                        {formatLabel(key)}
                                    </button>
                                ))}
                            </div>
                        )}

                        {authFields?.auth_fields && selectedAuthType ? (
                            <div className="flex flex-col gap-6">
                                {Object.entries(authFields.auth_fields).map(([fieldKey, field]) => (
                                    <div key={fieldKey} className="flex flex-col gap-2">
                                        <ZAPInput 
                                            label={field.label} 
                                            type={field.type === "password" ? "password" : "text"} 
                                            placeholder={field.placeholder || ""} 
                                            value={credentials[fieldKey] || ""} 
                                            onChange={e => updateCredential(fieldKey, e.target.value)} 
                                        />
                                        {field.help && (
                                            <span className="text-[13px] text-[var(--zaplane-text-muted)] leading-relaxed mt-0.5">
                                                {__(field.help, "zaplane")}
                                            </span>
                                        )}
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="py-12"><ZAPLoading /></div>
                        )}
                    </div>
                )}
            </ZAPDrawer>
        </PageLayout>;
};
export default Connections;