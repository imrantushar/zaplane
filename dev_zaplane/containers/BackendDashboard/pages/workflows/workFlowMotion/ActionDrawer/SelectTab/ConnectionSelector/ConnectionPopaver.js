import React, { useEffect, useState } from 'react';
import WPPopover from "@ZAPComponents/Popaver/WPPopover";
import { __ } from "@wordpress/i18n";
import { useDispatch, useSelector } from 'react-redux';
import { createTokenConnection, fetchAuthFields, initOAuth } from '@ZAPRedux/Slices/connectionsSlice/connectionsSlice';
import { showNotification } from '@ZAPRedux/Slices/notificationSlice/notificationSlice';
import { primaryBtn } from '../../../../../../../../../assets/scss/chakra/recipe';
import './styles.scss';
import { formatLabel } from '@ZAPUtils/helper';
import ZAPInput from '@ZAPComponents/ZAPInput';
import ZAPSelect from '@ZAPComponents/ZAPSelect';
const ConnectionPopaver = props => {
  const {
    isOpen,
    onClose,
    appSlug,
    onConnected
  } = props;
  const dispatch = useDispatch();
  const {
    authFields
  } = useSelector(state => state.connections || []);
  const [loadingOAuth, setLoadingOAuth] = useState(false);
  const [selectedAuthType, setSelectedAuthType] = useState(null);
  const [credentials, setCredentials] = useState({});
  useEffect(() => {
    dispatch(fetchAuthFields({
      app: appSlug,
      authType: selectedAuthType || undefined
    }));
  }, [selectedAuthType, dispatch]);

  // Resolve the real auth type from the integration instead of assuming OAuth —
  // e.g. AI is api_key (handled by WordPress core / your own key), never OAuth.
  useEffect(() => {
    if (!authFields || selectedAuthType) return;
    const types = Object.keys(authFields?.available_auth_types || {});
    let resolved = null;
    if (types.length === 1) resolved = types[0];
    else if (types.includes("oauth2")) resolved = "oauth2";
    else if (authFields?.auth_type) resolved = authFields.auth_type;
    if (resolved) setSelectedAuthType(resolved);
  }, [authFields, selectedAuthType]);
  const handleConnect = async () => {
    if (!selectedAuthType) return;

    const fields = authFields?.auth_fields || {};
    const valueOf = key => credentials[key] ?? fields[key]?.default ?? "";
    const isVisible = field => {
      if (!field.depends_on) return true;
      return Object.entries(field.depends_on).every(([k, v]) => {
        const cur = valueOf(k);
        return Array.isArray(v) ? v.includes(cur) : cur === v;
      });
    };
    const missing = Object.entries(fields)
      .filter(([, field]) => field.required && isVisible(field))
      .filter(([fieldKey]) => !String(valueOf(fieldKey)).trim())
      .map(([, field]) => field.label);

    if (missing.length > 0) {
      dispatch(showNotification({
        message: `${missing.join(', ')} ${missing.length > 1 ? 'are' : 'is'} required.`,
        isShow: true,
        type: "error"
      }));
      return;
    }

    setLoadingOAuth(true);
    if (selectedAuthType === "oauth2") {
      try {
        const res = await dispatch(initOAuth({
          app: appSlug,
          name: appSlug,
          credentials
        })).unwrap();
        const popup = window.open(res.auth_url, "oauth_popup", "width=600,height=700");
        const handler = event => {
          if (event.data?.type === "zaplane_oauth_callback") {
            window.removeEventListener("message", handler);
            popup?.close();
            if (event.data.data?.success) {
              onConnected?.(res);
              onClose();
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
      dispatch(createTokenConnection({
        app: appSlug,
        name: appSlug,
        authType: selectedAuthType,
        credentials
      })).then(action => {
        if (action.type === "connections/createTokenConnection/fulfilled") {
          setCredentials({});
          onClose();
          onConnected?.(action);
        }
        setLoadingOAuth(false);
      });
    }
  };
  const authTypes = authFields?.available_auth_types || {};
  return <WPPopover isOpen={isOpen} onClose={onClose} title={__("Create New Connection", 'zaplane')} prefix='connection-popaver'>
            <div className="flex bg-[var(--zaplane-secondary-color)]/50 p-1.5 rounded-xl gap-2 mb-8">
                {Object.keys(authTypes).map(key => (
                    <button 
                        key={key} 
                        className={`flex-1 py-2.5 px-4 rounded-lg font-medium transition-all duration-200 ${
                            selectedAuthType === key 
                            ? "bg-[var(--zaplane-second-primary)] text-[var(--zaplane-primary)] shadow-sm" 
                            : "bg-[var(--zaplane-background)] text-[var(--zaplane-font-secondary-color)] border border-[var(--zaplane-border-color)] hover:bg-[var(--zaplane-secondary-color)]"
                        }`}
                        onClick={() => {
                            setSelectedAuthType(key);
                            setCredentials({});
                        }}
                    >
                        {formatLabel(key)}
                    </button>
                ))}
            </div>

            {authFields?.auth_fields && selectedAuthType && (
                <div className="flex flex-col gap-6 pt-2">
                    {(() => {
                        const fields = authFields.auth_fields;
                        const valueOf = key => credentials[key] ?? fields[key]?.default ?? "";
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
                                            onChange={opt => setCredentials(prev => ({
                                                ...prev,
                                                [fieldKey]: opt?.value ?? ""
                                            }))}
                                        />
                                    ) : (
                                        <ZAPInput
                                            label={field.label}
                                            isRequired={field.required}
                                            type={field.type === "password" ? "password" : "text"}
                                            placeholder={field.placeholder || ""}
                                            value={credentials[fieldKey] || ""}
                                            onChange={e => setCredentials(prev => ({
                                                ...prev,
                                                [fieldKey]: e.target.value
                                            }))}
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
            )}

            {selectedAuthType && (
                <button 
                    onClick={handleConnect} 
                    className="w-full mt-10 bg-[var(--zaplane-primary)] hover:opacity-90 text-white font-semibold py-3 px-6 rounded-lg transition-all shadow-md active:scale-[0.98] disabled:opacity-50"
                    disabled={loadingOAuth}
                >
                    {loadingOAuth 
                        ? (selectedAuthType === "oauth2" ? __("Connecting...", "zaplane") : __("Saving...", "zaplane"))
                        : (selectedAuthType === "oauth2" ? __("Connect with OAuth", "zaplane") : __("Save Connection", "zaplane"))
                    }
                </button>
            )}
        </WPPopover>;
};
export default ConnectionPopaver;