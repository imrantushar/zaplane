import { useState } from "react";
import { __, _n, sprintf } from "@wordpress/i18n";
import { FiAlertTriangle, FiArrowRight, FiChevronDown, FiChevronUp, FiPlus } from "react-icons/fi";
import { integrations, plugin_root_url, route_path } from "@ZAPUtils/helper";

const appOf = (node) => integrations?.apps?.[node?.data?.app] || integrations?.tools?.[node?.data?.app];

/** The steps whose app needs an account but has none linked. */
export const stepsMissingConnection = (nodes = []) =>
  nodes.filter((node) => appOf(node)?.requires_connection === true && !node?.data?.connection_id);

const AppMark = ({ node }) => {
  const icon = node.data.icon || appOf(node)?.icon;
  if (typeof icon === "string" && icon.endsWith(".svg")) {
    return <img src={icon.includes("http") ? icon : `${plugin_root_url}assets/images/icons/${icon}`} alt="" />;
  }
  return <span>{(appOf(node)?.name || node.data.app || "?").charAt(0)}</span>;
};

/**
 * A card over the canvas while a step needs a connection: the workflow
 * can't be turned on until each is linked. Each step opens its panel to
 * pick an account; the card folds away to a small pill.
 */
// Folded stays folded for that workflow (a per-browser convenience).
const foldKey = () => "zaplane_needs_connection_folded_" + (new URLSearchParams(window.location.search).get("id") || "new");
const readFolded = () => {
  try {
    return window.localStorage.getItem(foldKey()) === "1";
  } catch (e) {
    return false;
  }
};

const MissingConnections = ({ nodes, status, onOpenStep }) => {
  const [folded, setFoldedState] = useState(readFolded);
  const setFolded = (value) => {
    setFoldedState(value);
    try {
      window.localStorage.setItem(foldKey(), value ? "1" : "0");
    } catch (e) {
      // Remembering is a nicety only.
    }
  };
  const missing = stepsMissingConnection(nodes);
  if (!missing.length) return null;

  const live = status === "active";
  const title = live
    ? sprintf(_n("%d step can't run: it has no connection", "%d steps can't run: they have no connection", missing.length, "zaplane"), missing.length)
    : sprintf(_n("Connect %d step to turn this workflow on", "Connect %d steps to turn this workflow on", missing.length, "zaplane"), missing.length);

  if (folded) {
    return (
      <button type="button" className={"zaplane-needs-connection is-folded" + (live ? " is-live" : "")} onClick={() => setFolded(false)} aria-expanded="false">
        <FiAlertTriangle aria-hidden="true" />
        {title}
        <FiChevronDown aria-hidden="true" />
      </button>
    );
  }

  return (
    <div className={"zaplane-needs-connection" + (live ? " is-live" : "")} role="alert">
      <div className="zaplane-needs-connection__head">
        <span className="zaplane-needs-connection__badge" aria-hidden="true">
          <FiAlertTriangle />
        </span>
        <div className="zaplane-needs-connection__titles">
          <strong>{title}</strong>
          <span>
            {live
              ? __("The workflow is on, but these steps are skipped until each has an account.", "zaplane")
              : __("Pick an account for each step below, then turn the workflow on.", "zaplane")}
          </span>
        </div>
        <a className="zaplane-needs-connection__add" href={`${route_path}admin.php?page=zaplane-connections`} target="_blank" rel="noopener noreferrer">
          <FiPlus aria-hidden="true" />
          {__("Add connection", "zaplane")}
        </a>
        <button type="button" className="zaplane-needs-connection__fold" onClick={() => setFolded(true)} aria-label={__("Hide", "zaplane")} title={__("Hide", "zaplane")}>
          <FiChevronUp />
        </button>
      </div>

      <ul className="zaplane-needs-connection__steps">
        {missing.map((node) => (
          <li key={node.id}>
            <button type="button" onClick={() => onOpenStep(node)}>
              <span className="zaplane-needs-connection__mark">
                <AppMark node={node} />
              </span>
              <span className="zaplane-needs-connection__step">
                <strong>{node.data.name || node.data.label || node.data.event}</strong>
                <em>{appOf(node)?.name || node.data.app}</em>
              </span>
              <span className="zaplane-needs-connection__choose">
                {__("Choose account", "zaplane")}
                <FiArrowRight aria-hidden="true" />
              </span>
            </button>
          </li>
        ))}
      </ul>
    </div>
  );
};

export default MissingConnections;
