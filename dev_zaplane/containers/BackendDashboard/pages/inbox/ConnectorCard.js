import { useState } from "react";
import { __, sprintf } from "@wordpress/i18n";
import { FiPlus, FiExternalLink, FiAlertTriangle, FiGitBranch } from "react-icons/fi";
import ZAPToggle from "@ZAPComponents/ZAPToggle";
import RecipeGroupWizard from "@ZAPComponents/RecipeGroupWizard";
import RecipeFlowIcons from "../recipes/RecipeFlowIcons";
import { API, namespace } from "@ZAPUtils/helper";
import { channelOf } from "./channels";

// Server messages arrive HTML-escaped (&quot;…): show them as text.
const plain = (s) => new DOMParser().parseFromString(String(s || ""), "text/html").documentElement.textContent;

const folderUrl = (id) => "admin.php?page=zaplane-folders&action=edit&id=" + id;
const workflowUrl = (id) => "admin.php?page=zaplane-workflows&action=edit&id=" + id;

/** How a channel or source stands, from its workflows. */
export function connectorState(c) {
  if (!c.installs.length) return { tone: "muted", text: __("Not set up", "zaplane"), on: false };
  if (c.receiving && c.delivering) return { tone: "success", text: __("On", "zaplane"), on: true };
  // Half running: the switch shows off, so one click turns both on.
  if (c.receiving) return { tone: "warning", text: __("Replies off", "zaplane"), on: false };
  if (c.delivering) return { tone: "warning", text: __("Not receiving", "zaplane"), on: false };
  return { tone: "muted", text: __("Off", "zaplane"), on: false };
}

/**
 * One Inbox recipe (a channel like Messenger, or a source like comments):
 * set it up, turn its workflows on or off, open them. Everything shown comes
 * from the workflows, so a change made on the Workflows screen shows here.
 */
const ConnectorCard = ({ connector: c, onChanged, children }) => {
  const [wizard, setWizard] = useState(false);
  const [busy, setBusy] = useState(false);
  const [errors, setErrors] = useState([]);
  const state = connectorState(c);
  const { Icon, color } = c.kind === "channel" ? channelOf(c.target) : { Icon: FiGitBranch, color: "#7C3AED" };

  // Pausing stops every copy; turning on starts the first one only, so a
  // duplicate never starts sending replies twice.
  const toggle = async (on) => {
    setBusy(true);
    setErrors([]);
    const targets = on ? [c.installs[0]] : c.installs;
    const failed = [];
    for (const install of targets) {
      try {
        const res = await API.post(namespace + "folders/" + install.folder_id + "/status", { status: on ? "active" : "paused" });
        (res.data?.workflows || []).forEach((w) => w.error && failed.push(w.title + ": " + plain(w.error)));
      } catch (e) {
        failed.push(plain(e?.response?.data?.message) || __("Couldn't change it.", "zaplane"));
      }
    }
    setErrors(failed);
    setBusy(false);
    onChanged();
  };

  return (
    <div className={"zaplane-inbox-connector" + (state.on ? " is-on" : "")}>
      <div className="zaplane-inbox-connector-head">
        <span className="zaplane-inbox-channel-icon" style={{ color }}>
          <Icon />
        </span>
        <span className="min-w-0 flex-1">
          <strong>{c.kind === "channel" ? channelOf(c.target).label : c.title}</strong>
          <span className="zaplane-inbox-sub">
            {c.installs.length
              ? c.kind === "channel" && c.connection
                ? sprintf(__("Using the connection “%s”", "zaplane"), c.connection.name)
                : sprintf(__("Set up in the folder “%s”", "zaplane"), c.installs[0].title)
              : c.description}
          </span>
        </span>
        <span className={"zaplane-inbox-badge is-" + state.tone}>{state.text}</span>
        {c.installs.length > 0 ? (
          <ZAPToggle checked={state.on} disabled={busy} onChange={toggle} label={state.on ? __("Turn off", "zaplane") : __("Turn on", "zaplane")} />
        ) : (
          <button type="button" className="zaplane-inbox-small is-primary" onClick={() => setWizard(true)}>
            <FiPlus />
            {__("Set up", "zaplane")}
          </button>
        )}
      </div>

      {c.installs.length === 0 && (
        <div className="zaplane-inbox-connector-recipe">
          <RecipeFlowIcons icons={c.icons} />
          <span className="zaplane-inbox-hint">{__("Sets up two workflows: one brings messages in, one sends your replies back. Turn them off here any time.", "zaplane")}</span>
        </div>
      )}

      {c.duplicates && (
        <div className="zaplane-inbox-callout is-warning">
          <FiAlertTriangle /> {__("This is set up more than once and running twice, so every reply goes out twice. Turn one copy off or delete it:", "zaplane")}{" "}
          {c.installs.map((i, n) => (
            <span key={i.folder_id}>
              {n > 0 && ", "}
              <a href={folderUrl(i.folder_id)}>{i.title}</a>
            </span>
          ))}
        </div>
      )}

      {errors.length > 0 && (
        <div className="zaplane-inbox-failed">
          {errors.map((e) => (
            <div key={e}>{e}</div>
          ))}
          {errors.some((e) => /needs a connection/i.test(e)) && (
            <div className="zaplane-inbox-hint">
              {__("Add the connection in Connections, then open each workflow and choose it on the steps that ask for it.", "zaplane")}{" "}
              <a href="admin.php?page=zaplane-connections">
                {__("Open Connections", "zaplane")} <FiExternalLink />
              </a>
            </div>
          )}
        </div>
      )}

      {c.installs.length > 0 && (
        <div className="zaplane-inbox-connector-flows">
          {c.installs.flatMap((i) => i.workflows).map((w) => (
            <a key={w.id} href={workflowUrl(w.id)} className="zaplane-inbox-connector-flow">
              <span className={"zaplane-inbox-dot is-" + (w.status === "active" ? "on" : "off")} aria-hidden="true" />
              {w.title}
              <em>{w.status === "active" ? __("Active", "zaplane") : w.status === "paused" ? __("Paused", "zaplane") : __("Draft", "zaplane")}</em>
              <FiExternalLink />
            </a>
          ))}
        </div>
      )}

      {c.installs.length > 0 && children}

      {wizard && (
        <RecipeGroupWizard
          recipe={c.recipe}
          isOpen
          onClose={() => {
            setWizard(false);
            onChanged();
          }}
        />
      )}
    </div>
  );
};

export default ConnectorCard;
