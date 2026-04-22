import React, { useEffect, useState } from 'react';
import WPPopover from "@ZAPComponents/Popaver/WPPopover";
import { __ } from "@wordpress/i18n";
import { useDispatch, useSelector } from 'react-redux';
import { createTokenConnection, fetchAuthFields, initOAuth } from '@ZAPRedux/Slices/connectionsSlice/connectionsSlice';
import { primaryBtn } from '../../../../../../../../../assets/scss/chakra/recipe';
import './styles.scss';
import { formatLabel } from '@ZAPUtils/helper';
import ZAPInput from '@ZAPComponents/ZAPInput';
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
  const [selectedAuthType, setSelectedAuthType] = useState("oauth2");
  const [credentials, setCredentials] = useState({});
  useEffect(() => {
    dispatch(fetchAuthFields({
      app: appSlug,
      authType: selectedAuthType || undefined
    }));
  }, [selectedAuthType, dispatch]);
  const handleConnect = async () => {
    if (!selectedAuthType) return;
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
            window.removeEventallConnectionener("message", handler);
            popup?.close();
            if (event.data.data?.success) {
              onConnected?.(res);
              onclose();
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
            <div className="flex bg-gray-50/50 p-1.5 rounded-xl gap-2 mb-8">
                {Object.keys(authTypes).map(key => (
                    <button 
                        key={key} 
                        className={`flex-1 py-2.5 px-4 rounded-lg font-medium transition-all duration-200 ${
                            selectedAuthType === key 
                            ? "bg-[var(--zaplane-second-primary)] text-[var(--zaplane-primary)] shadow-sm" 
                            : "bg-white text-gray-500 border border-gray-100 hover:bg-gray-50"
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
                    {Object.entries(authFields.auth_fields).map(([fieldKey, field]) => {
                        const value = credentials[fieldKey] || "";
                        return (
                            <div key={fieldKey} className="flex flex-col gap-2">
                                <ZAPInput 
                                    label={field.label} 
                                    type={field.type === "password" ? "password" : "text"} 
                                    placeholder={field.placeholder || ""} 
                                    value={value} 
                                    onChange={e => setCredentials(prev => ({
                                        ...prev,
                                        [fieldKey]: e.target.value
                                    }))} 
                                />
                                {field.help && (
                                    <span className="text-[13px] text-[var(--zaplane-text-muted)] leading-relaxed mt-0.5">
                                        {__(field.help, "zaplane")}
                                    </span>
                                )}
                            </div>
                        );
                    })}
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