import { useEffect, useState } from "react";
import { __ } from "@wordpress/i18n";
import ZAPToggle from "@ZAPComponents/ZAPToggle";
import { primaryBtn, outlineBtn } from "../../../../../assets/scss/chakra/recipe";
import { inboxApi } from "./api";

const Field = ({ label, help, children }) => (
  <label className="zaplane-inbox-field">
    <span>{label}</span>
    {children}
    {help && <em className="zaplane-inbox-hint">{help}</em>}
  </label>
);

const Settings = ({ onSaved }) => {
  const [data, setData] = useState(null);
  const [form, setForm] = useState(null);
  const [saving, setSaving] = useState(false);
  const [notice, setNotice] = useState("");
  const [canned, setCanned] = useState([]);
  const [draft, setDraft] = useState({ title: "", shortcut: "", body: "" });

  useEffect(() => {
    inboxApi.settings().then((d) => {
      setData(d);
      setForm({
        ...d.settings,
        widget: { ...d.settings.widget, allowed_origins: (d.settings.widget.allowed_origins || []).join("\n") },
      });
    });
    inboxApi.canned().then(setCanned);
  }, []);

  if (!form) return <p className="p-6">{__("Loading…", "zaplane")}</p>;

  const setWidget = (k, v) => setForm({ ...form, widget: { ...form.widget, [k]: v } });
  const setAi = (k, v) => setForm({ ...form, ai: { ...form.ai, [k]: v } });

  const save = async () => {
    setSaving(true);
    setNotice("");
    try {
      const res = await inboxApi.saveSettings({
        widget: {
          ...form.widget,
          allowed_origins: form.widget.allowed_origins.split(/\s+/).filter(Boolean),
        },
        ai: form.ai,
      });
      setData(res);
      setNotice(__("Settings saved.", "zaplane"));
      onSaved?.(res);
    } catch (e) {
      setNotice(e?.response?.data?.message || __("Settings could not be saved.", "zaplane"));
    } finally {
      setSaving(false);
    }
  };

  const addCanned = async (e) => {
    e.preventDefault();
    if (!draft.title.trim() || !draft.body.trim()) return;
    setCanned(await inboxApi.saveCanned(draft));
    setDraft({ title: "", shortcut: "", body: "" });
  };

  const connections = data?.ai_connections || [];
  const connectionsUrl = "admin.php?page=zaplane-connections";

  return (
    <div className="zaplane-inbox-settings">
      <section className="zaplane-inbox-card">
        <div className="zaplane-inbox-card-head">
          <div>
            <h3>{__("Website chat widget", "zaplane")}</h3>
            <p>{__("A chat button on every page of your site. Messages arrive in this inbox.", "zaplane")}</p>
          </div>
          <ZAPToggle checked={!!form.widget.enabled} onChange={(v) => setWidget("enabled", v)} label={__("Show the widget", "zaplane")} />
        </div>
        <div className="zaplane-inbox-grid">
          <Field label={__("Title", "zaplane")}>
            <input className="zaplane-inbox-input" value={form.widget.title} onChange={(e) => setWidget("title", e.target.value)} />
          </Field>
          <Field label={__("Greeting", "zaplane")}>
            <input className="zaplane-inbox-input" value={form.widget.greeting} onChange={(e) => setWidget("greeting", e.target.value)} />
          </Field>
          <Field label={__("Colour", "zaplane")}>
            <input type="color" className="zaplane-inbox-color" value={form.widget.color} onChange={(e) => setWidget("color", e.target.value)} />
          </Field>
          <Field label={__("Position", "zaplane")}>
            <select className="zaplane-inbox-select" value={form.widget.position} onChange={(e) => setWidget("position", e.target.value)}>
              <option value="right">{__("Bottom right", "zaplane")}</option>
              <option value="left">{__("Bottom left", "zaplane")}</option>
            </select>
          </Field>
        </div>
        <div className="zaplane-inbox-field is-row">
          <span>{__("Ask visitors for their name and email before the first message", "zaplane")}</span>
          <ZAPToggle checked={!!form.widget.ask_email} onChange={(v) => setWidget("ask_email", v)} size="sm" />
        </div>
        <Field
          label={__("Other websites allowed to use this chat", "zaplane")}
          help={__("One address per line, like https://shop.example.com. Your own site is always allowed.", "zaplane")}
        >
          <textarea className="zaplane-inbox-input" rows={2} value={form.widget.allowed_origins} onChange={(e) => setWidget("allowed_origins", e.target.value)} />
        </Field>
      </section>

      <section className="zaplane-inbox-card">
        <div className="zaplane-inbox-card-head">
          <div>
            <h3>{__("AI assistant", "zaplane")}</h3>
            <p>
              {__("Answers new conversations from your Business Knowledge and hands them to your team when it can't help. It stops for good in a conversation as soon as someone on your team replies.", "zaplane")}
            </p>
          </div>
          <ZAPToggle checked={!!form.ai.enabled} onChange={(v) => setAi("enabled", v)} label={__("Let the assistant answer", "zaplane")} />
        </div>
        <div className="zaplane-inbox-grid">
          <Field
            label={__("AI connection", "zaplane")}
            help={
              connections.length === 0 ? (
                <>
                  {__("No AI Agent connection yet.", "zaplane")} <a href={connectionsUrl}>{__("Add one in Connections", "zaplane")}</a>
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
          <Field label={__("Knowledge to answer from", "zaplane")} help={__("The Business Key used in Business Knowledge.", "zaplane")}>
            <input className="zaplane-inbox-input" value={form.ai.business_key} onChange={(e) => setAi("business_key", e.target.value)} />
          </Field>
          <Field label={__("Assistant name", "zaplane")}>
            <input className="zaplane-inbox-input" value={form.ai.agent_name} onChange={(e) => setAi("agent_name", e.target.value)} />
          </Field>
          <Field label={__("Business name", "zaplane")} help={__("Leave empty to use your site title.", "zaplane")}>
            <input className="zaplane-inbox-input" value={form.ai.business_name} onChange={(e) => setAi("business_name", e.target.value)} />
          </Field>
        </div>
        <Field
          label={__("Extra instructions", "zaplane")}
          help={__("Added to the assistant's built-in rules, for example your tone, opening hours or what it must never promise.", "zaplane")}
        >
          <textarea className="zaplane-inbox-input" rows={4} value={form.ai.instructions} onChange={(e) => setAi("instructions", e.target.value)} />
        </Field>
      </section>

      <div className="flex items-center gap-3">
        <button type="button" style={primaryBtn} onClick={save} disabled={saving}>
          {saving ? __("Saving…", "zaplane") : __("Save settings", "zaplane")}
        </button>
        {notice && <span className="zaplane-inbox-hint" role="status">{notice}</span>}
      </div>

      <section className="zaplane-inbox-card">
        <div className="zaplane-inbox-card-head">
          <div>
            <h3>{__("Saved replies", "zaplane")}</h3>
            <p>{__("Answers you send often. Type / and the shortcut in the reply box to use one.", "zaplane")}</p>
          </div>
        </div>
        <ul className="zaplane-inbox-canned-list">
          {canned.map((c) => (
            <li key={c.id}>
              <div className="min-w-0">
                <strong>{c.title}</strong>
                {c.shortcut && <code>/{c.shortcut}</code>}
                <p>{c.body}</p>
              </div>
              <button type="button" style={outlineBtn} onClick={async () => setCanned(await inboxApi.deleteCanned(c.id))}>
                {__("Delete", "zaplane")}
              </button>
            </li>
          ))}
        </ul>
        <form className="zaplane-inbox-grid" onSubmit={addCanned}>
          <Field label={__("Title", "zaplane")}>
            <input className="zaplane-inbox-input" value={draft.title} onChange={(e) => setDraft({ ...draft, title: e.target.value })} />
          </Field>
          <Field label={__("Shortcut", "zaplane")}>
            <input className="zaplane-inbox-input" value={draft.shortcut} placeholder="shipping" onChange={(e) => setDraft({ ...draft, shortcut: e.target.value })} />
          </Field>
          <Field label={__("Message", "zaplane")}>
            <textarea className="zaplane-inbox-input" rows={2} value={draft.body} onChange={(e) => setDraft({ ...draft, body: e.target.value })} />
          </Field>
          <div className="flex items-end">
            <button type="submit" style={outlineBtn}>
              {__("Add saved reply", "zaplane")}
            </button>
          </div>
        </form>
      </section>
    </div>
  );
};

export default Settings;
