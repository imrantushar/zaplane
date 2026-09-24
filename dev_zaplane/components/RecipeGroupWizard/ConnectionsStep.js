import { useEffect, useState } from "react";
import { __ } from "@wordpress/i18n";
import Select from "react-select";
import { FiCheck, FiExternalLink } from "react-icons/fi";
import { API, namespace, route_path } from "@ZAPUtils/helper";
import { Intro, StepIcon } from "./parts";

const ConnectionCard = ({ app, value, onChange, usedBy }) => {
  const [connections, setConnections] = useState([]);

  const load = () =>
    API.get(`${namespace}connections`, { params: { app: app.slug } })
      .then(res => {
        const rows = res?.data?.data || res?.data || [];
        setConnections(Array.isArray(rows) ? rows.filter(row => row.app === app.slug) : []);
      })
      .catch(() => setConnections([]));

  // A connection added in another tab shows up once this tab is back in front.
  useEffect(() => {
    load();
    window.addEventListener("focus", load);
    return () => window.removeEventListener("focus", load);
  }, [app.slug]);

  const choices = connections.map(connection => ({ value: String(connection.id), label: connection.name }));

  return (
    <div className={`zgs-connection${value ? " is-linked" : ""}`}>
      <div className="zgs-connection__head">
        <span className="zgs-connection__icon">
          <StepIcon icon={app.icon} size={20} />
          {value && (
            <span className="zgs-connection__badge">
              <FiCheck size={10} aria-hidden="true" />
            </span>
          )}
        </span>
        <div className="zgs-connection__text">
          <p className="zgs-value__label">{app.name}</p>
          <div className="zgs-chips">
            {usedBy.map(title => (
              <span key={title} className="zgs-chip">
                {title}
              </span>
            ))}
          </div>
        </div>
        <button
          type="button"
          className="zgs-link"
          onClick={() => window.open(`${route_path}admin.php?page=zaplane-connections`, "_blank", "noopener")}
        >
          {__("Add connection", "zaplane")}
          <FiExternalLink size={12} aria-hidden="true" />
        </button>
      </div>

      <Select
        className="zaplane-select"
        classNamePrefix="zaplane-select"
        options={choices}
        value={choices.find(choice => choice.value === value) || null}
        onChange={choice => onChange(choice?.value || "")}
        placeholder={__("Select a connection", "zaplane")}
        noOptionsMessage={() => __("No connections yet", "zaplane")}
        isClearable
        menuPortalTarget={document.body}
        styles={{ menuPortal: base => ({ ...base, zIndex: 100000 }) }}
      />

      {!value && (
        <p className="zgs-hint">
          {__("You can skip this. The workflows that need it stay drafts until you link one.", "zaplane")}
        </p>
      )}
    </div>
  );
};

const ConnectionsStep = ({ apps, connections, onChange, usedBy }) => (
  <div className="zgs-stack">
    <Intro
      title={__("Connect your accounts", "zaplane")}
      text={__("Pick the account each app should use. A connection added in another tab shows up here when you come back.", "zaplane")}
    />

    {apps.map(app => (
      <ConnectionCard
        key={app.slug}
        app={app}
        value={connections[app.slug] || ""}
        onChange={id => onChange(app.slug, id)}
        usedBy={usedBy(app)}
      />
    ))}
  </div>
);

export default ConnectionsStep;
