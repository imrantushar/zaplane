import { __, _n, sprintf } from "@wordpress/i18n";
import { FiAlertTriangle, FiExternalLink, FiLink } from "react-icons/fi";
import { integrations, route_path } from "@ZAPUtils/helper";

/** The steps whose app needs an account but has none linked. */
export const stepsMissingConnection = (nodes = []) =>
  nodes.filter((node) => {
    const app = integrations?.apps?.[node?.data?.app] || integrations?.tools?.[node?.data?.app];
    return app?.requires_connection === true && !node?.data?.connection_id;
  });

/**
 * Above the canvas while a step needs a connection: the workflow can't be
 * turned on until each is linked. Click a step to open it and pick one.
 */
const MissingConnections = ({ nodes, status, onOpenStep }) => {
  const missing = stepsMissingConnection(nodes);
  if (!missing.length) return null;

  const appName = (node) =>
    (integrations?.apps?.[node.data.app] || integrations?.tools?.[node.data.app])?.name || node.data.app;
  const stepName = (node) => node.data.name || node.data.label || node.data.event;

  return (
    <div className={"zaplane-missing-connections" + (status === "active" ? " is-live" : "")} role="alert">
      <FiAlertTriangle className="zaplane-missing-connections__icon" aria-hidden="true" />
      <div className="zaplane-missing-connections__text">
        <strong>
          {status === "active"
            ? sprintf(
                _n("This workflow is on, but %d step can't run: it has no connection.", "This workflow is on, but %d steps can't run: they have no connection.", missing.length, "zaplane"),
                missing.length
              )
            : sprintf(
                _n("%d step needs a connection before this workflow can be turned on.", "%d steps need a connection before this workflow can be turned on.", missing.length, "zaplane"),
                missing.length
              )}
        </strong>
        <span className="zaplane-missing-connections__steps">
          {missing.map((node) => (
            <button key={node.id} type="button" onClick={() => onOpenStep(node)} title={__("Open this step and choose a connection", "zaplane")}>
              <FiLink aria-hidden="true" />
              {stepName(node)}
              <em>{appName(node)}</em>
            </button>
          ))}
        </span>
      </div>
      <a className="zaplane-missing-connections__add" href={`${route_path}admin.php?page=zaplane-connections`} target="_blank" rel="noopener noreferrer">
        {__("Set up a connection", "zaplane")}
        <FiExternalLink aria-hidden="true" />
      </a>
    </div>
  );
};

export default MissingConnections;
