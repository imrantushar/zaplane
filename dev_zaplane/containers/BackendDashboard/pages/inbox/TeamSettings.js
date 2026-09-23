import { useEffect, useRef, useState } from "react";
import { __, sprintf } from "@wordpress/i18n";
import { FiWifi, FiImage, FiX, FiSearch, FiUserPlus, FiTrash2, FiClock } from "react-icons/fi";
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

/** A week of opening times: the company's, or one agent's own. */
const DaysEditor = ({ days, onChange }) => {
  const setDay = (key, patch) => onChange({ ...days, [key]: { ...DAY_DEFAULT, ...(days[key] || {}), ...patch } });
  return (
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
  );
};

/** Search the site's users and add one to the chat team. */
const AgentPicker = ({ onAdd }) => {
  const [search, setSearch] = useState("");
  const [open, setOpen] = useState(false);
  const [results, setResults] = useState([]);
  const [loading, setLoading] = useState(false);
  const boxRef = useRef(null);

  useEffect(() => {
    if (!open) return undefined;
    setLoading(true);
    const timer = window.setTimeout(() => {
      inboxApi
        .agentCandidates(search)
        .then((res) => setResults(res.users || []))
        .catch(() => setResults([]))
        .finally(() => setLoading(false));
    }, 250);
    return () => window.clearTimeout(timer);
  }, [search, open]);

  useEffect(() => {
    const close = (e) => boxRef.current && !boxRef.current.contains(e.target) && setOpen(false);
    document.addEventListener("mousedown", close);
    return () => document.removeEventListener("mousedown", close);
  }, []);

  return (
    <div className="zaplane-inbox-picker" ref={boxRef}>
      <span className="zaplane-inbox-picker-field">
        <FiSearch aria-hidden="true" />
        <input
          className="zaplane-inbox-input"
          value={search}
          placeholder={__("Add a person — search by name or email", "zaplane")}
          onFocus={() => setOpen(true)}
          onChange={(e) => {
            setSearch(e.target.value);
            setOpen(true);
          }}
          aria-label={__("Search users to add", "zaplane")}
        />
      </span>
      {open && (
        <div className="zaplane-inbox-picker-list" role="listbox">
          {loading && results.length === 0 && <div className="zaplane-inbox-picker-empty">{__("Searching…", "zaplane")}</div>}
          {!loading && results.length === 0 && <div className="zaplane-inbox-picker-empty">{__("No users found.", "zaplane")}</div>}
          {results.map((user) => (
            <button
              type="button"
              role="option"
              aria-selected={user.member}
              key={user.id}
              className={"zaplane-inbox-picker-item" + (user.member ? " is-member" : "")}
              disabled={user.member}
              onClick={() => {
                onAdd(user.id);
                setOpen(false);
                setSearch("");
              }}
            >
              <AgentFace name={user.name} avatar={user.avatar} />
              <span className="zaplane-inbox-picker-who">
                <strong>{user.name}</strong>
                <em>
                  {user.email}
                  {user.role ? " · " + user.role : ""}
                </em>
              </span>
              <span className="zaplane-inbox-picker-action">{user.member ? __("Added", "zaplane") : <FiUserPlus aria-label={__("Add", "zaplane")} />}</span>
            </button>
          ))}
        </div>
      )}
    </div>
  );
};

/**
 * Who answers, when, and how fast the chat hears about it: the team's chat
 * identities, opening hours, and the realtime server.
 */
const TeamSettings = ({ form, setWidget, setHours, setRealtime, Field, realtimeStatus }) => {
  const [agents, setAgents] = useState([]);
  const [busy, setBusy] = useState(0);
  const [error, setError] = useState("");
  const hours = form.hours || {};
  const scheduleTimers = useRef({});
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

  const addAgent = async (userId) => {
    setError("");
    try {
      const res = await inboxApi.addAgent(userId);
      setAgents(res.agents || []);
    } catch (e) {
      setError(e?.response?.data?.message || __("That person couldn't be added.", "zaplane"));
    }
  };

  const removeAgent = async (agent) => {
    setError("");
    setBusy(agent.id);
    try {
      const res = await inboxApi.removeAgent(agent.id);
      setAgents(res.agents || []);
    } catch (e) {
      setError(e?.response?.data?.message || __("That person couldn't be removed.", "zaplane"));
    } finally {
      setBusy(0);
    }
  };

  const implicit = agents.length > 0 && agents[0].implicit;

  return (
    <>
      <section className="zaplane-inbox-card" id="zaplane-inbox-team" data-section="team">
        <div className="zaplane-inbox-card-head">
          <div>
            <h3>{__("Who answers", "zaplane")}</h3>
            <p>{__("Pick the people who answer the chat — any user on the site. Those who can't manage the site get a “Chat Inbox” menu with just the conversations. Each shows the name and picture you set here, and works the company's hours or their own.", "zaplane")}</p>
          </div>
          <ZAPToggle
            checked={!!form.widget.show_team}
            onChange={(v) => setWidget("show_team", v)}
            label={__("Show the team in the chat header", "zaplane")}
          />
        </div>

        <AgentPicker onAdd={addAgent} />
        {implicit && (
          <p className="zaplane-inbox-hint zaplane-inbox-team-note">
            {__("Nobody picked yet, so everyone who manages the site answers. Adding a person keeps them and adds the new one.", "zaplane")}
          </p>
        )}
        {error && <p className="zaplane-inbox-failed">{error}</p>}

        <div className="zaplane-inbox-agents">
          {agents.length === 0 && <p className="zaplane-inbox-hint">{__("Loading the team…", "zaplane")}</p>}
          {agents.map((agent) => (
            <div className={"zaplane-inbox-agent" + (busy === agent.id ? " is-busy" : "")} key={agent.id}>
              <AgentFace name={agent.name} avatar={agent.avatar} online={agent.online} />
              <div className="zaplane-inbox-agent-fields">
                <div className="zaplane-inbox-agent-who">
                  <strong>{agent.user_name}</strong>
                  {agent.email && <em>{agent.email}</em>}
                </div>
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
                <div className="zaplane-inbox-agent-hours">
                  <span className="zaplane-inbox-agent-hours-label">
                    <FiClock aria-hidden="true" />
                    {__("Available", "zaplane")}
                  </span>
                  <span className="zaplane-inbox-segment" role="radiogroup">
                    {[
                      ["company", __("Company hours", "zaplane")],
                      ["custom", __("Own hours", "zaplane")],
                    ].map(([mode, label]) => (
                      <button
                        key={mode}
                        type="button"
                        role="radio"
                        aria-checked={(agent.schedule?.mode || "company") === mode}
                        className={(agent.schedule?.mode || "company") === mode ? "is-active" : ""}
                        onClick={() => saveAgent(agent.id, { schedule: { ...(agent.schedule || {}), mode } })}
                      >
                        {label}
                      </button>
                    ))}
                  </span>
                  {agent.schedule?.mode !== "custom" && (
                    <span className="zaplane-inbox-hint">
                      {hours.enabled ? __("Follows the company opening hours.", "zaplane") : __("Any time — the company keeps no opening hours.", "zaplane")}
                    </span>
                  )}
                </div>
                {agent.schedule?.mode === "custom" && (
                  <DaysEditor
                    days={agent.schedule.days || {}}
                    onChange={(next) => {
                      setAgents((l) => l.map((a) => (a.id === agent.id ? { ...a, schedule: { ...a.schedule, days: next } } : a)));
                      window.clearTimeout(scheduleTimers.current[agent.id]);
                      scheduleTimers.current[agent.id] = window.setTimeout(() => saveAgent(agent.id, { schedule: { mode: "custom", days: next } }), 600);
                    }}
                  />
                )}
              </div>
              <div className="zaplane-inbox-agent-state">
                <span className={"zaplane-inbox-badge is-" + (agent.online ? "success" : "muted")}>
                  {agent.online
                    ? __("At their desk", "zaplane")
                    : agent.away
                      ? __("Away", "zaplane")
                      : agent.on_duty === false
                        ? __("Off hours", "zaplane")
                        : __("Not in the inbox", "zaplane")}
                </span>
                <label className="zaplane-inbox-check">
                  <input type="checkbox" checked={!!agent.hidden} onChange={(e) => saveAgent(agent.id, { hidden: e.target.checked })} />
                  {__("Hide from the chat", "zaplane")}
                </label>
                {!agent.implicit && agents.length > 1 && (
                  <button type="button" className="zaplane-inbox-small is-danger" onClick={() => removeAgent(agent)}>
                    <FiTrash2 />
                    {__("Remove", "zaplane")}
                  </button>
                )}
              </div>
            </div>
          ))}
        </div>
      </section>

      <section className="zaplane-inbox-card" id="zaplane-inbox-hours" data-section="hours">
        <div className="zaplane-inbox-card-head">
          <div>
            <h3>{__("Company opening hours", "zaplane")}</h3>
            <p>{__("Everyone on “Company hours” answers during these. The chat is open while anyone is on duty (company or their own hours) and says you're closed — with when you're back — when nobody is.", "zaplane")}</p>
          </div>
          <ZAPToggle checked={!!hours.enabled} onChange={(v) => setHours("enabled", v)} label={__("Keep opening hours", "zaplane")} />
        </div>

        {hours.enabled && <DaysEditor days={days} onChange={(next) => setHours("days", next)} />}

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
