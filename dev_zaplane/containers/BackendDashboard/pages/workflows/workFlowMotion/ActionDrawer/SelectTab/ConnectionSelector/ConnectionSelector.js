import { useEffect, useState } from "react";
import { useDispatch, useSelector } from "react-redux";
import Select, { components } from "react-select";
import { fetchConnectionsByApp } from "@ZAPRedux/Slices/workFlowSlice/actions/connectionsSlice";
import ConnectionPopaver from "./ConnectionPopaver";
import { __ } from "@wordpress/i18n";
import { secondPrimaryBtn } from "../../../../../../../../../assets/scss/chakra/recipe";
const ConnectionSelector = ({
  appSlug,
  values,
  setFieldValue
}) => {
  const dispatch = useDispatch();
  const {
    appConnections
  } = useSelector(state => state.workflows);
  const [isPopoverOpen, setPopoverOpen] = useState(false);
  useEffect(() => {
    if (appSlug) {
      dispatch(fetchConnectionsByApp(appSlug));
    }
  }, [appSlug, dispatch]);
  const handleConnected = connection => {
    dispatch(fetchConnectionsByApp(appSlug));
    setFieldValue("connection_id", String(connection?.payload?.id));
  };
  const options = appConnections?.filter(c => c.app === appSlug)?.map(c => ({
    label: c.name,
    value: String(c.id)
  }));

  // Custom MenuList to add "Create New Connection" button inside dropdown
  const CustomMenuList = props => <components.MenuList {...props}>
            {props.children}
            <div className="flex p-2">
                <button style={secondPrimaryBtn} size="sm" width="100%" onClick={() => setPopoverOpen(true)}>
                    {__("Create New Connection", "zaplane")}
                </button>
            </div>
        </components.MenuList>;
  return <>
            <div className="flex flex-col gap-2">
                <span>{__("Select Connection", "zaplane")}</span>
                <Select className="zaplane-select" classNamePrefix="zaplane-select" options={options} value={options?.find(o => o.value === values?.connection_id) || null} onChange={val => setFieldValue("connection_id", val?.value)} placeholder={__("Select a connection", "zaplane")} isClearable components={{
        MenuList: CustomMenuList
      }} />
            </div>

            <ConnectionPopaver appSlug={appSlug} isOpen={isPopoverOpen} onClose={() => setPopoverOpen(false)} onConnected={handleConnected} />
        </>;
};
export default ConnectionSelector;