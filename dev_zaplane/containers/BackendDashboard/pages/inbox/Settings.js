import { useEffect, useMemo, useRef, useState } from "react";
import { __, _n, sprintf } from "@wordpress/i18n";
import { FiMessageCircle, FiCpu, FiShare2, FiZap, FiCopy, FiCheck, FiTrash2, FiExternalLink, FiX, FiUsers, FiGitBranch, FiBookOpen } from "react-icons/fi";
import ZAPToggle from "@ZAPComponents/ZAPToggle";
import { inboxApi } from "./api";
import { channelOf } from "./channels";
import KnowledgeSettings from "./KnowledgeSettings";

const Field = ({ label, help, children, wide }) => (
  <label className={"zaplane-inbox-field" + (wide ? " is-wide" : "")}>
    <span>{label}</span>
    {children}
    {help && <em className="zaplane-inbox-hint">{help}</em>}
  </label>
);

const SECTIONS = [
  { id: "widget", label: __("Website chat", "zaplane"), Icon: FiMessageCircle },
  { id: "knowledge", label: __("Knowledge & answers", "zaplane"), Icon: FiBookOpen },
  { id: "ai", label: __("AI assistant", "zaplane"), Icon: FiCpu },
  { id: "channels", label: __("Social channels", "zaplane"), Icon: FiShare2 },
  { id: "replies", label: __("Saved replies", "zaplane"), Icon: FiZap },
];

// The menu editor needs each question's answer text, which the settings
// endpoint returns alongside (answers.menu), not in the raw settings.
const toForm = (settings, payload) => ({
  ...settings,
  answers: { ...(settings.answers || {}), menu: payload?.answers?.menu || settings.answers?.menu },
  widget: { ...settings.widget, allowed_origins: (settings.widget.allowed_origins || []).join("\n") },
});

const CopyField = ({ value }) => {
  const [copied, setCopied] = useState(false);
  const copy = async () => {
    try {
      await navigator.clipboard.writeText(value);
      setCopied(true);
      window.setTimeout(() => setCopied(false), 1500);
    } catch (e) {
      // Clipboard can be blocked; the field is still selectable.
    }
  };
  return (
    <span className="zaplane-inbox-copy">
      <input className="zaplane-inbox-input" readOnly value={value} onFocus={(e) => e.target.select()} />
      <button type="button" className="zaplane-inbox-small" onClick={copy} aria-label={__("Copy", "zaplane")}>
        {copied ? <FiCheck /> : <FiCopy />}
        {copied ? __("Copied", "zaplane") : __("Copy", "zaplane")}
      </button>
    </span>
  );
};

/** What the widget will look like on the site, updating as settings change. */
const WidgetPreview = ({ widget }) => (
  <div className="zaplane-inbox-preview-site" aria-hidden="true">
    <div className="zaplane-inbox-preview-lines">
      <span />
      <span />
      <span />
    </div>
    <div className={"zaplane-inbox-preview-panel is-" + widget.position} style={{ "--widget": widget.color }}>
      <div className="zaplane-inbox-preview-top">{widget.title || __("Chat with us", "zaplane")}</div>
      <div className="zaplane-inbox-preview-body">
        <span className="zaplane-inbox-preview-bubble">{widget.greeting || "…"}</span>
      </div>
      <div className="zaplane-inbox-preview-input">{__("Type a message…", "zaplane")}</div>
    </div>
    <span className={"zaplane-inbox-preview-launcher is-" + widget.position} style={{ background: widget.color }}>
      <FiMessageCircle />
    </span>
  </div>
);

const ANSWERERS = [
  { value: "assistant", label: __("AI assistant", "zaplane"), Icon: FiCpu },
  { value: "workflows", label: __("Workflows", "zaplane"), Icon: FiGitBranch },
  { value: "team", label: __("Team", "zaplane"), Icon: FiUsers },
];

/** Who takes new conversations on a channel. One answerer per conversation. */
const AnsweredBy = ({ value, onChange, aiReady }) => (
  <div className="zaplane-inbox-field">
    <span>{__("Who answers new conversations", "zaplane")}</span>
    <span className="zaplane-inbox-segment" role="radiogroup">
      {ANSWERERS.map(({ value: v, label, Icon }) => (
        <button key={v} type="button" role="radio" aria-checked={value === v} className={value === v ? "is-active" : ""} onClick={() => onChange(v)}>
          <Icon />
          {label}
        </button>
      ))}
    </span>
    <em className="zaplane-inbox-hint">
      {value === "assistant"
        ? aiReady
          ? __("The assistant replies until someone on your team does. Workflows that reply on this channel skip these customers.", "zaplane")
          : __("The assistant isn't set up yet, so your team answers for now.", "zaplane")
        : value === "workflows"
          ? __("Your workflows reply. The assistant stays quiet, and the team can still step in.", "zaplane")
          : __("Only your team replies. Workflows that reply on this channel skip these customers.", "zaplane")}
    </em>
  </div>
);

const Status = ({ tone, children }) => <span className={"zaplane-inbox-badge is-" + tone}>{children}</span>;

const Settings = ({ onSaved }) => {
  const [data, setData] = useState(null);
  const [form, setForm] = useState(null);
  const [saved, setSaved] = useState(null);
  const [saving, setSaving] = useState(false);
  const [notice, setNotice] = useState(null);
  const [canned, setCanned] = useState([]);
  const [draft, setDraft] = useState({ title: "", shortcut: "", body: "" });
  const [secrets, setSecrets] = useState({});
  const [active, setActive] = useState("widget");
  const pinnedUntil = useRef(0); // a nav click wins over the scroll spy for a moment
  const setSecret = (slug, key, v) => setSecrets({ ...secrets, [slug]: { ...(secrets[slug] || {}), [key]: v } });

  useEffect(() => {
    inboxApi.settings().then((d) => {
      setData(d);
      setForm(toForm(d.settings, d));
      setSaved(toForm(d.settings, d));
    });
    inboxApi.canned().then(setCanned);
  }, []);

  const dirty = useMemo(() => {
    if (!form || !saved) return false;
    const typedSecret = Object.values(secrets).some((v) => Object.values(v || {}).some((x) => (x || "").trim()));
    return typedSecret || JSON.stringify(form) !== JSON.stringify(saved);
  }, [form, saved, secrets]);

  useEffect(() => {
    if (notice?.tone !== "success") return undefined;
    const t = window.setTimeout(() => setNotice(null), 3000);
    return () => window.clearTimeout(t);
  }, [notice]);

  // Keep the section nav in step with scrolling.
  useEffect(() => {
    if (!form || typeof IntersectionObserver === "undefined") return undefined;
    const io = new IntersectionObserver(
      (entries) => {
        const hit = entries.filter((e) => e.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0];
        if (hit && Date.now() > pinnedUntil.current) setActive(hit.target.dataset.section);
      },
      { rootMargin: "-120px 0px -55% 0px" }
    );
    document.querySelectorAll("[data-section]").forEach((el) => io.observe(el));
    return () => io.disconnect();
  }, [!!form]);

  if (!form) {
    return (
      <div className="zaplane-inbox-settings is-loading">
        <div className="zaplane-inbox-card is-skeleton" />
        <div className="zaplane-inbox-card is-skeleton" />
      </div>
    );
  }

  const setWidget = (k, v) => setForm({ ...form, widget: { ...form.widget, [k]: v } });
  const setAi = (k, v) => setForm({ ...form, ai: { ...form.ai, [k]: v } });
  const setAnswers = (k, v) => setForm({ ...form, answers: { ...(form.answers || {}), [k]: v } });
  const setChannel = (slug, k, v) =>
    setForm({ ...form, channels: { ...form.channels, [slug]: { ...form.channels[slug], [k]: v } } });

  const save = async () => {
    setSaving(true);
    setNotice(null);
    try {
      await inboxApi.saveSettings({
        widget: {
          ...form.widget,
          allowed_origins: form.widget.allowed_origins.split(/\s+/).filter(Boolean),
        },
        ai: form.ai,
        answers: form.answers,
        channels: form.channels,
      });
      // Webhook secrets are stored with the integration, not the inbox.
      for (const [slug, values] of Object.entries(secrets)) {
        const clean = Object.fromEntries(Object.entries(values).filter(([, v]) => v.trim()));
        if (Object.keys(clean).length) await inboxApi.webhookConfig(slug, clean);
      }
      setSecrets({});
      // Read back after the secrets too, so "saved" badges are current.
      const fresh = await inboxApi.settings();
      setData(fresh);
      setForm(toForm(fresh.settings, fresh));
      setSaved(toForm(fresh.settings, fresh));
      setNotice({ tone: "success", text: __("Settings saved.", "zaplane") });
      onSaved?.(fresh);
    } catch (e) {
      setNotice({ tone: "error", text: e?.response?.data?.message || __("Settings could not be saved.", "zaplane") });
    } finally {
      setSaving(false);
    }
  };

  const discard = () => {
    setForm(saved);
    setSecrets({});
    setNotice(null);
  };

  const addCanned = async (e) => {
    e.preventDefault();
    if (!draft.title.trim() || !draft.body.trim()) return;
    setCanned(await inboxApi.saveCanned(draft));
    setDraft({ title: "", shortcut: "", body: "" });
  };

  const jump = (id) => {
    pinnedUntil.current = Date.now() + 1200;
    setActive(id);
    document.getElementById("zaplane-inbox-" + id)?.scrollIntoView({ behavior: "smooth", block: "start" });
  };

  const connections = data?.ai_connections || [];
  const knowledgeKeys = data?.knowledge_keys || [];

  const connectionsUrl = "admin.php?page=zaplane-connections";
  const aiReady = form.ai.enabled && form.ai.connection_id;

  const channelState = (slug, ch) => {
    const cfg = form.channels?.[slug] || {};
    if (!cfg.enabled) return { tone: "muted", text: __("Off", "zaplane") };
    if (!cfg.connection_id || !ch.has_app_secret) return { tone: "warning", text: __("Needs setup", "zaplane") };
    return { tone: "success", text: __("Receiving", "zaplane") };
  };

  const navState = {
    widget: form.widget.enabled ? { tone: "success", text: __("On", "zaplane") } : { tone: "muted", text: __("Off", "zaplane") },
    knowledge: form.answers?.enabled
      ? { tone: "success", text: __("On", "zaplane") }
      : { tone: "muted", text: __("Off", "zaplane") },
    ai: aiReady ? { tone: "success", text: __("On", "zaplane") } : form.ai.enabled ? { tone: "warning", text: __("Needs setup", "zaplane") } : { tone: "muted", text: __("Off", "zaplane") },
    channels: (() => {
      const on = Object.entries(data?.channels || {}).filter(([slug]) => form.channels?.[slug]?.enabled).length;
      return on ? { tone: "success", text: sprintf(__("%d on", "zaplane"), on) } : { tone: "muted", text: __("Off", "zaplane") };
    })(),
    replies: { tone: "muted", text: String(canned.length) },
  };

  return (
    <div className="zaplane-inbox-settings">
      <nav className="zaplane-inbox-settings-nav" aria-label={__("Settings sections", "zaplane")}>
        {SECTIONS.map(({ id, label, Icon }) => (
          <button key={id} type="button" className={active === id ? "is-active" : ""} onClick={() => jump(id)} aria-current={active === id ? "true" : undefined}>
            <Icon />
            <span>{label}</span>
            <Status tone={navState[id].tone}>{navState[id].text}</Status>
          </button>
        ))}
      </nav>

      <div className="zaplane-inbox-settings-body">
        <section className="zaplane-inbox-card" id="zaplane-inbox-widget" data-section="widget">
          <div className="zaplane-inbox-card-head">
            <div>
              <h3>{__("Website chat widget", "zaplane")}</h3>
              <p>{__("A chat button on every page of your site. Messages arrive in this inbox.", "zaplane")}</p>
            </div>
            <ZAPToggle checked={!!form.widget.enabled} onChange={(v) => setWidget("enabled", v)} label={__("Show the widget", "zaplane")} />
          </div>
          <div className="zaplane-inbox-split">
            <div className="zaplane-inbox-grid">
              <Field label={__("Title", "zaplane")}>
                <input className="zaplane-inbox-input" value={form.widget.title} onChange={(e) => setWidget("title", e.target.value)} />
              </Field>
              <Field label={__("Greeting", "zaplane")}>
                <input className="zaplane-inbox-input" value={form.widget.greeting} onChange={(e) => setWidget("greeting", e.target.value)} />
              </Field>
              <Field label={__("Colour", "zaplane")}>
                <span className="zaplane-inbox-colorfield">
                  <input type="color" className="zaplane-inbox-color" value={form.widget.color} onChange={(e) => setWidget("color", e.target.value)} aria-label={__("Pick a colour", "zaplane")} />
                  <input
                    className="zaplane-inbox-input"
                    value={form.widget.color}
                    maxLength={7}
                    onChange={(e) => setWidget("color", e.target.value)}
                    aria-label={__("Colour code", "zaplane")}
                  />
                </span>
              </Field>
              <Field label={__("Position", "zaplane")}>
                <span className="zaplane-inbox-segment" role="radiogroup">
                  {[
                    ["left", __("Bottom left", "zaplane")],
                    ["right", __("Bottom right", "zaplane")],
                  ].map(([value, label]) => (
                    <button key={value} type="button" role="radio" aria-checked={form.widget.position === value} className={form.widget.position === value ? "is-active" : ""} onClick={() => setWidget("position", value)}>
                      {label}
                    </button>
                  ))}
                </span>
              </Field>
              <div className="is-wide">
                <AnsweredBy value={form.widget.answered_by || "assistant"} onChange={(v) => setWidget("answered_by", v)} aiReady={!!aiReady} />
              </div>
              <div className="zaplane-inbox-field is-row is-wide">
                <span>{__("Ask visitors for their name and email before the first message", "zaplane")}</span>
                <ZAPToggle checked={!!form.widget.ask_email} onChange={(v) => setWidget("ask_email", v)} size="sm" />
              </div>
              <Field wide label={__("Other websites allowed to use this chat", "zaplane")} help={__("One address per line, like https://shop.example.com. Your own site is always allowed.", "zaplane")}>
                <textarea className="zaplane-inbox-input" rows={3} value={form.widget.allowed_origins} onChange={(e) => setWidget("allowed_origins", e.target.value)} />
              </Field>
            </div>
            <div className="zaplane-inbox-preview-wrap">
              <span className="zaplane-inbox-hint">{__("Preview", "zaplane")}</span>
              <WidgetPreview widget={form.widget} />
            </div>
          </div>
        </section>

        <KnowledgeSettings
          form={form}
          setAi={setAi}
          setAnswers={setAnswers}
          knowledgeKeys={knowledgeKeys}
          answersData={data?.answers}
          aiOn={!!aiReady}
          channels={data?.channels}
        />

        <section className="zaplane-inbox-card" id="zaplane-inbox-ai" data-section="ai">
          <div className="zaplane-inbox-card-head">
            <div>
              <h3>{__("AI assistant", "zaplane")}</h3>
              <p>
                {__("Answers what automatic answers can't, using the knowledge chosen above, and hands conversations to your team when it can't help. It stops for good in a conversation as soon as someone on your team replies.", "zaplane")}
              </p>
            </div>
            <ZAPToggle checked={!!form.ai.enabled} onChange={(v) => setAi("enabled", v)} label={__("Let the assistant answer", "zaplane")} />
          </div>
          {form.ai.enabled && !form.ai.connection_id && (
            <div className="zaplane-inbox-callout is-warning">{__("Choose an AI connection, or the assistant stays quiet.", "zaplane")}</div>
          )}
          <div className="zaplane-inbox-grid">
            <Field
              label={__("AI connection", "zaplane")}
              help={
                connections.length === 0 ? (
                  <>
                    {__("No AI Agent connection yet.", "zaplane")}{" "}
                    <a href={connectionsUrl}>
                      {__("Add one in Connections", "zaplane")} <FiExternalLink />
                    </a>
                  </>
                ) : null
              }
            >
              <select className="zaplane-inbox-select" value={form.ai.connection_id || 0} onChange={(e) => setAi("connection_id", parseInt(e.target.value, 10) || 0)}>
                <option value={0}>{__("Choose a connection", "zaplane")}</option>
                {connections.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name}
                  </option>
                ))}
              </select>
            </Field>
            <Field label={__("Assistant name", "zaplane")}>
              <input className="zaplane-inbox-input" value={form.ai.agent_name} onChange={(e) => setAi("agent_name", e.target.value)} />
            </Field>
            <Field label={__("Business name", "zaplane")} help={__("Leave empty to use your site title.", "zaplane")}>
              <input className="zaplane-inbox-input" value={form.ai.business_name} onChange={(e) => setAi("business_name", e.target.value)} />
            </Field>
          </div>
          {data?.store ? (
            <div className="zaplane-inbox-options">
              <div className="zaplane-inbox-field is-row">
                <span>
                  {__("Help customers find products and show product cards", "zaplane")}
                  <em className="zaplane-inbox-hint">{sprintf(__("Uses %s", "zaplane"), data.store)}</em>
                </span>
                <ZAPToggle checked={!!form.ai.sell} onChange={(v) => setAi("sell", v)} size="sm" />
              </div>
              <div className="zaplane-inbox-field is-row">
                <span>
                  {__("Let the assistant place cash-on-delivery orders", "zaplane")}
                  <em className="zaplane-inbox-hint">
                    {__("Only after the customer confirms the full order; at most 3 per conversation a day. When off, it collects the details and hands over.", "zaplane")}
                  </em>
                </span>
                <ZAPToggle checked={!!form.ai.can_order} disabled={!form.ai.sell} onChange={(v) => setAi("can_order", v)} size="sm" />
              </div>
            </div>
          ) : (
            <div className="zaplane-inbox-callout">{__("Activate StoreEngine or WooCommerce to let the assistant sell.", "zaplane")}</div>
          )}
          <Field
            wide
            label={__("Extra instructions", "zaplane")}
            help={__("Added to the assistant's built-in rules, for example your tone, opening hours or what it must never promise.", "zaplane")}
          >
            <textarea className="zaplane-inbox-input" rows={4} value={form.ai.instructions} onChange={(e) => setAi("instructions", e.target.value)} />
          </Field>
        </section>

        <section className="zaplane-inbox-card" id="zaplane-inbox-channels" data-section="channels">
          <div className="zaplane-inbox-card-head">
            <div>
              <h3>{__("Social channels", "zaplane")}</h3>
              <p>
                {__("Bring Facebook Page messages and WhatsApp chats into this inbox, next to your website chat. Each channel uses a connection from Connections, the same one your workflows use.", "zaplane")}
              </p>
            </div>
          </div>
          <div className="zaplane-inbox-channels">
            {Object.entries(data?.channels || {}).map(([slug, ch]) => {
              const cfg = form.channels?.[slug] || { enabled: false, connection_id: 0 };
              const { Icon, color } = channelOf(slug);
              const state = channelState(slug, ch);
              return (
                <div key={slug} className={"zaplane-inbox-channel" + (cfg.enabled ? " is-on" : "")}>
                  <div className="zaplane-inbox-channel-head">
                    <span className="zaplane-inbox-channel-icon" style={{ color }}>
                      <Icon />
                    </span>
                    <strong>{ch.label}</strong>
                    <Status tone={state.tone}>{state.text}</Status>
                    <ZAPToggle checked={!!cfg.enabled} onChange={(v) => setChannel(slug, "enabled", v)} label={__("Receive in the inbox", "zaplane")} />
                  </div>
                  {cfg.enabled && (
                    <div className="zaplane-inbox-channel-routing">
                      <AnsweredBy value={cfg.answered_by || "assistant"} onChange={(v) => setChannel(slug, "answered_by", v)} aiReady={!!aiReady} />
                      {(ch.workflow_senders || []).length > 0 && (cfg.answered_by || "assistant") !== "workflows" && (
                        <div className="zaplane-inbox-callout is-warning">
                          <strong>
                            {sprintf(
                              _n("%1$d active workflow also replies on %2$s:", "%1$d active workflows also reply on %2$s:", ch.workflow_senders.length, "zaplane"),
                              ch.workflow_senders.length,
                              ch.label
                            )}
                          </strong>{" "}
                          {ch.workflow_senders.map((w, i) => (
                            <span key={w.id}>
                              {i > 0 && ", "}
                              <a href={"admin.php?page=zaplane-workflows&action=edit&id=" + w.id}>{w.name}</a>
                            </span>
                          ))}
                          .{" "}
                          {__("To stop double replies (and double AI costs), they don't run for customers the assistant or your team is already answering.", "zaplane")}{" "}
                          <button type="button" className="zaplane-inbox-link" onClick={() => setChannel(slug, "answered_by", "workflows")}>
                            {__("Let workflows answer instead", "zaplane")}
                          </button>
                        </div>
                      )}
                    </div>
                  )}
                  {cfg.enabled && (
                    <ol className="zaplane-inbox-steps">
                      <li>
                        <Field
                          label={__("Connection", "zaplane")}
                          help={
                            ch.connections.length === 0 ? (
                              <>
                                {sprintf(__("No %s connection yet.", "zaplane"), ch.label)}{" "}
                                <a href={connectionsUrl}>
                                  {__("Add one in Connections", "zaplane")} <FiExternalLink />
                                </a>
                              </>
                            ) : (
                              __("Sends replies with this connection's token.", "zaplane")
                            )
                          }
                        >
                          <select className="zaplane-inbox-select" value={cfg.connection_id || 0} onChange={(e) => setChannel(slug, "connection_id", parseInt(e.target.value, 10) || 0)}>
                            <option value={0}>{__("Choose a connection", "zaplane")}</option>
                            {ch.connections.map((c) => (
                              <option key={c.id} value={c.id}>
                                {c.name}
                              </option>
                            ))}
                          </select>
                        </Field>
                      </li>
                      <li>
                        <Field label={__("Callback URL for Meta", "zaplane")} help={__("Paste this into your Meta app's webhook settings and subscribe to messages.", "zaplane")}>
                          <CopyField value={ch.webhook_url} />
                        </Field>
                      </li>
                      <li>
                        <Field
                          label={__("Verify token", "zaplane")}
                          help={ch.has_verify_token ? __("Saved. Type a new one to replace it.", "zaplane") : __("Make one up and use the same value in Meta.", "zaplane")}
                        >
                          <input
                            className="zaplane-inbox-input"
                            type="password"
                            autoComplete="off"
                            placeholder={ch.has_verify_token ? "••••••••" : ""}
                            value={secrets[slug]?.verify_token || ""}
                            onChange={(e) => setSecret(slug, "verify_token", e.target.value)}
                          />
                        </Field>
                      </li>
                      <li>
                        <Field
                          label={__("App secret", "zaplane")}
                          help={
                            ch.has_app_secret
                              ? __("Saved. Messages are checked against it.", "zaplane")
                              : __("Required: without it the inbox ignores deliveries, because they can't be checked.", "zaplane")
                          }
                        >
                          <input
                            className="zaplane-inbox-input"
                            type="password"
                            autoComplete="off"
                            placeholder={ch.has_app_secret ? "••••••••" : ""}
                            value={secrets[slug]?.app_secret || ""}
                            onChange={(e) => setSecret(slug, "app_secret", e.target.value)}
                          />
                        </Field>
                      </li>
                    </ol>
                  )}
                </div>
              );
            })}
          </div>
        </section>

        <section className="zaplane-inbox-card" id="zaplane-inbox-replies" data-section="replies">
          <div className="zaplane-inbox-card-head">
            <div>
              <h3>{__("Saved replies", "zaplane")}</h3>
              <p>{__("Answers you send often. Type / and the shortcut in the reply box to use one. These save as soon as you add them.", "zaplane")}</p>
            </div>
          </div>
          {canned.length > 0 ? (
            <ul className="zaplane-inbox-canned-list">
              {canned.map((c) => (
                <li key={c.id}>
                  <div className="min-w-0">
                    <strong>{c.title}</strong>
                    {c.shortcut && <code>/{c.shortcut}</code>}
                    <p>{c.body}</p>
                  </div>
                  <button
                    type="button"
                    className="zaplane-inbox-icon-btn is-danger"
                    aria-label={__("Delete", "zaplane") + " " + c.title}
                    title={__("Delete", "zaplane")}
                    onClick={async () => setCanned(await inboxApi.deleteCanned(c.id))}
                  >
                    <FiTrash2 />
                  </button>
                </li>
              ))}
            </ul>
          ) : (
            <div className="zaplane-inbox-callout">{__("No saved replies yet. Add your first one below.", "zaplane")}</div>
          )}
          <form className="zaplane-inbox-grid zaplane-inbox-canned-form" onSubmit={addCanned}>
            <Field label={__("Title", "zaplane")}>
              <input className="zaplane-inbox-input" value={draft.title} placeholder={__("Shipping times", "zaplane")} onChange={(e) => setDraft({ ...draft, title: e.target.value })} />
            </Field>
            <Field label={__("Shortcut", "zaplane")}>
              <span className="zaplane-inbox-prefix">
                <span>/</span>
                <input className="zaplane-inbox-input" value={draft.shortcut} placeholder="shipping" onChange={(e) => setDraft({ ...draft, shortcut: e.target.value })} />
              </span>
            </Field>
            <Field wide label={__("Message", "zaplane")}>
              <textarea className="zaplane-inbox-input" rows={3} value={draft.body} onChange={(e) => setDraft({ ...draft, body: e.target.value })} />
            </Field>
            <div className="is-wide">
              <button type="submit" className="zaplane-inbox-small" disabled={!draft.title.trim() || !draft.body.trim()}>
                {__("Add saved reply", "zaplane")}
              </button>
            </div>
          </form>
        </section>

        <div className={"zaplane-inbox-savebar" + (dirty || notice ? " is-visible" : "")} role="region" aria-label={__("Save", "zaplane")}>
          {notice ? (
            <span className={"zaplane-inbox-savebar-text is-" + notice.tone} role="status">
              {notice.tone === "success" ? <FiCheck /> : <FiX />}
              {notice.text}
            </span>
          ) : (
            <span className="zaplane-inbox-savebar-text">{__("You have unsaved changes.", "zaplane")}</span>
          )}
          <span className="flex items-center gap-2">
            {dirty && (
              <button type="button" className="zaplane-inbox-small" onClick={discard} disabled={saving}>
                {__("Discard", "zaplane")}
              </button>
            )}
            <button type="button" className="zaplane-inbox-send" onClick={save} disabled={saving || !dirty}>
              {saving ? __("Saving…", "zaplane") : __("Save settings", "zaplane")}
            </button>
          </span>
        </div>
      </div>
    </div>
  );
};

export default Settings;
