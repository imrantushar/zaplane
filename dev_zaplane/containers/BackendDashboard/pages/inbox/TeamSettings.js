import { useEffect, useState } from "react";
import { __, sprintf } from "@wordpress/i18n";
import { FiWifi, FiImage, FiX } from "react-icons/fi";
import ZAPToggle from "@ZAPComponents/ZAPToggle";
import { inboxApi } from "./api";

const DAYS = [
  { key: "mon", label: __("Monday", "zaplane") },
  { key: "tue", label: __("Tuesday", "zaplane") },
  { key: "wed", label: __("Wednesday", "zaplane") },
  { key: "thu", label: __("Thursday", "zaplane") },
  { key: "fri", label: __("Friday", "zaplane") },
  { key: "sat", label: __("Saturday", "zaplane") },
  { key: "sun", label: __("Sunday", "zaplane") },
];

const DAY_DEFAULT = { closed: false, open: "09:00", close: "17:00" };

/** A round picture with a fallback letter, as the chat itself draws it. */
export const AgentFace = ({ name, avatar, online }) => (
  <span className={"zaplane-inbox-face" + (online ? " is-online" : "")}>
    {avatar ? <img src={avatar} alt="" /> : <span className="zaplane-inbox-face-letter">{(name || "?").charAt(0).toUpperCase()}</span>}
  </span>
);

/**
 * Pick a picture from the media library, or paste an address. The library is
 * already loaded on this screen; if it somehow is not, the field still works.
 */
export const PicturePicker = ({ value, onChange, label }) => {
  const choose = () => {
    const media = window.wp?.media;
    if (!media) return;
    const frame = media({ title: label, library: { type: "image" }, multiple: false });
    frame.on("select", () => {
      const picked = frame.state().get("selection").first().toJSON();
      onChange({ url: picked.sizes?.thumbnail?.url || picked.url, id: picked.id });
    });
    frame.open();
  };

  return (
    <span className="zaplane-inbox-picture">
      <input
        className="zaplane-inbox-input"
        value={value || ""}
        placeholder="https://…"
        onChange={(e) => onChange({ url: e.target.value, id: 0 })}
        aria-label={label}
      />
      {window.wp?.media && (
        <button type="button" className="zaplane-inbox-small" onClick={choose}>
          <FiImage />
          {__("Choose", "zaplane")}
        </button>
      )}
      {value && (
        <button type="button" className="zaplane-inbox-icon-btn is-ghost" onClick={() => onChange({ url: "", id: 0 })} aria-label={__("Remove the picture", "zaplane")}>
          <FiX />
        </button>
      )}
    </span>
  );
};

/**
 * Who answers, when, and how fast the chat hears about it: the team's chat
 * identities, opening hours, and the realtime server.
 */
const TeamSettings = ({ form, setWidget, setHours, setRealtime, Field, realtimeStatus }) => {
  const [agents, setAgents] = useState([]);
  const [busy, setBusy] = useState(0);
  const hours = form.hours || {};
  const days = hours.days || {};

  useEffect(() => {
    inboxApi.agents().then((res) => setAgents(res.agents || [])).catch(() => setAgents([]));
  }, []);

  const saveAgent = async (id, patch) => {
    setAgents((list) => list.map((a) => (a.id === id ? { ...a, ...patch } : a)));
    setBusy(id);
    try {
      const res = await inboxApi.saveAgent(id, patch);
      setAgents((list) => list.map((a) => (a.id === id ? res.agent : a)));
    } finally {
      setBusy(0);
    }
  };

  const setDay = (key, patch) =>
    setHours("days", { ...days, [key]: { ...DAY_DEFAULT, ...(days[key] || {}), ...patch } });

  return (
    <>
      <section className="zaplane-inbox-card" id="zaplane-inbox-team" data-section="team">
        <div className="zaplane-inbox-card-head">
          <div>
            <h3>{__("Who answers", "zaplane")}</h3>
            <p>{__("The name and picture each person shows in the chat. Visitors never see the account name unless you leave it as it is.", "zaplane")}</p>
          </div>
          <ZAPToggle
            checked={!!form.widget.show_team}
            onChange={(v) => setWidget("show_team", v)}
            label={__("Show the team in the chat header", "zaplane")}
          />
        </div>

        <div className="zaplane-inbox-agents">
          {agents.length === 0 && <p className="zaplane-inbox-hint">{__("Loading the team…", "zaplane")}</p>}
          {agents.map((agent) => (
            <div className={"zaplane-inbox-agent" + (busy === agent.id ? " is-busy" : "")} key={agent.id}>
              <AgentFace name={agent.name} avatar={agent.avatar} online={agent.online} />
              <div className="zaplane-inbox-agent-fields">
                <div className="zaplane-inbox-agent-row">
                  <Field label={__("Name in the chat", "zaplane")}>
                    <input
                      className="zaplane-inbox-input"
                      value={agent.name || ""}
                      placeholder={agent.user_name}
                      onChange={(e) => setAgents((l) => l.map((a) => (a.id === agent.id ? { ...a, name: e.target.value } : a)))}
                      onBlur={(e) => saveAgent(agent.id, { name: e.target.value })}
                    />
                  </Field>
                  <Field label={__("Role", "zaplane")} help={__("Shown under their name, e.g. Support.", "zaplane")}>
                    <input
                      className="zaplane-inbox-input"
                      value={agent.title || ""}
                      onChange={(e) => setAgents((l) => l.map((a) => (a.id === agent.id ? { ...a, title: e.target.value } : a)))}
                      onBlur={(e) => saveAgent(agent.id, { title: e.target.value })}
                    />
                  </Field>
                </div>
                <Field label={__("Picture", "zaplane")} wide help={__("Leave empty to use their profile picture.", "zaplane")}>
                  <PicturePicker
                    value={agent.avatar}
                    label={__("Choose a picture", "zaplane")}
                    onChange={({ url, id }) => saveAgent(agent.id, { avatar: url, avatar_id: id })}
                  />
                </Field>
              </div>
              <div className="zaplane-inbox-agent-state">
                <span className={"zaplane-inbox-badge is-" + (agent.online ? "success" : "muted")}>
                  {agent.online ? __("At their desk", "zaplane") : agent.away ? __("Away", "zaplane") : __("Not in the inbox", "zaplane")}
                </span>
                <label className="zaplane-inbox-check">
                  <input type="checkbox" checked={!!agent.hidden} onChange={(e) => saveAgent(agent.id, { hidden: e.target.checked })} />
                  {__("Hide from the chat", "zaplane")}
                </label>
              </div>
            </div>
          ))}
        </div>
      </section>

      <section className="zaplane-inbox-card" id="zaplane-inbox-hours" data-section="hours">
        <div className="zaplane-inbox-card-head">
          <div>
            <h3>{__("Opening hours", "zaplane")}</h3>
            <p>{__("Outside these hours the chat says you are closed, however many inboxes are open. Inside them it shows whether anyone is actually there.", "zaplane")}</p>
          </div>
          <ZAPToggle checked={!!hours.enabled} onChange={(v) => setHours("enabled", v)} label={__("Keep opening hours", "zaplane")} />
        </div>

        {hours.enabled && (
          <div className="zaplane-inbox-hours">
            {DAYS.map(({ key, label }) => {
              const day = { ...DAY_DEFAULT, ...(days[key] || {}) };
              return (
                <div className={"zaplane-inbox-hours-row" + (day.closed ? " is-closed" : "")} key={key}>
                  <span className="zaplane-inbox-hours-day">{label}</span>
                  <label className="zaplane-inbox-check">
                    <input type="checkbox" checked={!day.closed} onChange={(e) => setDay(key, { closed: !e.target.checked })} />
                    {day.closed ? __("Closed", "zaplane") : __("Open", "zaplane")}
                  </label>
                  <input
                    type="time"
                    className="zaplane-inbox-input"
                    value={day.open}
                    disabled={day.closed}
                    onChange={(e) => setDay(key, { open: e.target.value })}
                    aria-label={sprintf(__("%s opens at", "zaplane"), label)}
                  />
                  <span className="zaplane-inbox-hours-to">{__("to", "zaplane")}</span>
                  <input
                    type="time"
                    className="zaplane-inbox-input"
                    value={day.close}
                    disabled={day.closed}
                    onChange={(e) => setDay(key, { close: e.target.value })}
                    aria-label={sprintf(__("%s closes at", "zaplane"), label)}
                  />
                </div>
              );
            })}
          </div>
        )}

        <div className="zaplane-inbox-grid">
          <Field label={__("How fast you usually reply", "zaplane")} help={__("Shown in the chat header, e.g. “in a few minutes”.", "zaplane")}>
            <input className="zaplane-inbox-input" value={hours.reply_time || ""} onChange={(e) => setHours("reply_time", e.target.value)} placeholder={__("in a few minutes", "zaplane")} />
          </Field>
          <Field label={__("What to say when nobody is there", "zaplane")} wide>
            <input
              className="zaplane-inbox-input"
              value={hours.away_message || ""}
              onChange={(e) => setHours("away_message", e.target.value)}
              placeholder={__("We're away right now — leave a message and we'll get back to you.", "zaplane")}
            />
          </Field>
        </div>
      </section>

      <section className="zaplane-inbox-card" id="zaplane-inbox-realtime" data-section="realtime">
        <div className="zaplane-inbox-card-head">
          <div>
            <h3>{__("Instant delivery", "zaplane")}</h3>
            <p>{__("A small server keeps chats open so replies appear at once. Without it the chat checks every few seconds instead — everything still works, just a little behind.", "zaplane")}</p>
          </div>
          <ZAPToggle checked={!!form.realtime?.enabled} onChange={(v) => setRealtime("enabled", v)} label={__("Use the realtime server", "zaplane")} />
        </div>

        <div className="zaplane-inbox-realtime">
          <span className={"zaplane-inbox-badge is-" + (realtimeStatus?.running ? "success" : "muted")}>
            <FiWifi />
            {realtimeStatus?.running ? __("Server running", "zaplane") : __("Server not running", "zaplane")}
          </span>
          {realtimeStatus?.running && realtimeStatus.connections !== undefined && (
            <span className="zaplane-inbox-hint">{sprintf(__("%d chat(s) connected", "zaplane"), realtimeStatus.connections)}</span>
          )}
        </div>

        <div className="zaplane-inbox-grid">
          <Field
            label={__("Address the browser connects to", "zaplane")}
            wide
            help={__("Start the server with: wp zaplane socket start --port=8088. On a site served over https, put it behind your web server and give the wss:// address here, e.g. wss://example.com/ws.", "zaplane")}
          >
            <input
              className="zaplane-inbox-input"
              value={form.realtime?.public_url || ""}
              onChange={(e) => setRealtime("public_url", e.target.value)}
              placeholder="wss://example.com/ws"
            />
          </Field>
        </div>

        {realtimeStatus?.last_error && (
          <p className="zaplane-inbox-failed">
            {sprintf(__("The last message could not be handed to the server: %s", "zaplane"), realtimeStatus.last_error)}
          </p>
        )}
      </section>
    </>
  );
};

export default TeamSettings;
