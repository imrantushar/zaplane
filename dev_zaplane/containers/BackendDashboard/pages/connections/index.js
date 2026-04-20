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
  return <PageLayout title="Connections" heading="Connections" actions={<button style={primaryBtn} onClick={openDrawer}>
                    {__("Create credential", "zaplane")}
                </button>}>
            <ConnectionTable />

            <ZAPDrawer open={isDrawerOpen} onClose={closeDrawer} title={__(drawerTitle, "zaplane")} size="md" placement="end" closeOnOverlayClick arrowClose={drawerStep === "configure"} arrowOnClick={goBack} footer={drawerStep === "configure" && selectedAuthType ? () => <button style={{...primaryBtn, width:"220px"}} onClick={saveConnection} disabled={!selectedAuthType}>
                                {__("Save Connection", "zaplane")}
                            </button> : null}>
                {drawerStep === "select" && <DrawerItemList list={appList} setSelectedItem={selectApp} />}

                {drawerStep === "configure" && <div>
                        <div align="stretch" className="flex flex-col gap-4">
                            {Object.keys(authTypes).length > 1 && <div direction="column" gap={2} className="flex">
                                    <span>
                                        {__("Select Auth Type", 'zaplane')}
                                    </span>
                                    {Object.keys(authTypes).map(key => <button key={key} variant={selectedAuthType === key ? "solid" : "outline"} onClick={() => selectAuthType(key)}>
                                            {formatLabel(key)}
                                        </button>)}
                                </div>}

                            {authFields?.auth_fields && selectedAuthType ? <div align="stretch" className="flex flex-col gap-3 pt-2">
                                    {Object.entries(authFields.auth_fields).map(([fieldKey, field]) => <div direction="column" gap="4px" key={fieldKey} className="flex">
                                            <ZAPInput label={field.label} type={field.type === "password" ? "password" : "text"} placeholder={field.placeholder || ""} value={credentials[fieldKey] || ""} onChange={e => updateCredential(fieldKey, e.target.value)} />

                                            {field.help && <span className="zaplane-sub-title text-[sm] mt-[7px] text-var(--zaplane-text-muted)">
                                                    {__(field.help, "zaplane")}
                                                </span>}
                                        </div>)}
                                </div> : <ZAPLoading />}
                        </div>
                    </div>}
            </ZAPDrawer>
        </PageLayout>;
};
export default Connections;