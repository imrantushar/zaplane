import { useEffect } from "react";
import { useDispatch, useSelector } from "react-redux";
import { useFormikContext } from "formik";
import Select, { components } from "react-select";
import { __, sprintf } from "@wordpress/i18n";
import { FiPlus } from "react-icons/fi";
import { fetchConnectionsByApp } from "@ZAPRedux/Slices/workFlowSlice/actions/connectionsSlice";
import useConnection from "@ZAPHooks/useConnection/useConnection";
import ConnectionDrawer from "@ZAPComponents/ConnectionDrawer";
import { integrations } from "@ZAPUtils/helper";
import "./styles.scss";

/**
 * The account a step uses. A new one is created in the same modal as the
 * editor's "Add connection", and is picked for this step once saved.
 */
const ConnectionSelector = ({ appSlug, values, setFieldValue }) => {
  const dispatch = useDispatch();
  const { appConnections } = useSelector((state) => state.workflows);
  const { errors, setFieldError } = useFormikContext();
  const error = errors?.connection_id;
  const appName = (integrations?.apps?.[appSlug] || integrations?.tools?.[appSlug])?.name || appSlug;

  useEffect(() => {
    if (appSlug) dispatch(fetchConnectionsByApp(appSlug));
  }, [appSlug, dispatch]);

  const connection = useConnection({
    onlyApps: [appSlug],
    onSaved: ({ id }) => {
      dispatch(fetchConnectionsByApp(appSlug));
      setFieldValue("connection_id", String(id));
      if (error) setFieldError("connection_id", undefined);
    },
  });
  const create = () => connection.openForApp(appSlug);

  const options = (appConnections || [])
    .filter((c) => c.app === appSlug)
    .map((c) => ({ label: c.name, value: String(c.id) }));

  // A quiet last row in the list, not a big button inside it.
  const MenuList = (props) => (
    <components.MenuList {...props}>
      {props.children}
      <button
        type="button"
        className="zaplane-connection-select__create"
        onMouseDown={(e) => e.preventDefault()}
        onClick={() => {
          props.selectProps.onMenuClose?.();
          create();
        }}
      >
        <FiPlus aria-hidden="true" />
        {__("Create new connection", "zaplane")}
      </button>
    </components.MenuList>
  );

  return (
    <>
      <div className="zaplane-connection-select">
        <span className="zaplane-connection-select__label">
          {__("Select Connection", "zaplane")}
          <span className="text-[var(--zaplane-danger)] ml-[2px]">*</span>
        </span>
        <Select
          className="zaplane-select"
          classNamePrefix="zaplane-select"
          options={options}
          value={options.find((o) => o.value === values?.connection_id) || null}
          onChange={(val) => {
            setFieldValue("connection_id", val?.value);
            if (error) setFieldError("connection_id", undefined);
          }}
          placeholder={options.length ? __("Select a connection", "zaplane") : sprintf(__("No %s account yet", "zaplane"), appName)}
          noOptionsMessage={() => sprintf(__("No %s accounts yet", "zaplane"), appName)}
          isClearable
          components={{ MenuList }}
        />
        {error && <p className="zaplane-connection-select__error">{error}</p>}
        {!options.length && (
          <p className="zaplane-connection-select__hint">
            {sprintf(__("This step needs a %s account.", "zaplane"), appName)}{" "}
            <button type="button" onClick={create}>
              <FiPlus aria-hidden="true" />
              {__("Add account", "zaplane")}
            </button>
          </p>
        )}
      </div>

      <ConnectionDrawer {...connection} variant="modal" lockedApp />
    </>
  );
};

export default ConnectionSelector;
