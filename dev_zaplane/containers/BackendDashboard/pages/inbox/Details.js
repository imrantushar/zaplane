import { useEffect, useState } from "react";
import { __ } from "@wordpress/i18n";
import ZAPToggle from "@ZAPComponents/ZAPToggle";
import { CHANNEL_LABELS, initials } from "./ConversationList";
import { clockTime } from "./api";

const STATUSES = [
  { value: "open", label: __("Open", "zaplane") },
  { value: "pending", label: __("Pending", "zaplane") },
  { value: "snoozed", label: __("Snoozed", "zaplane") },
  { value: "closed", label: __("Closed", "zaplane") },
];

const Details = ({ conversation, other, team, aiReady, onUpdate }) => {
  const [contact, setContact] = useState({ name: "", email: "", phone: "" });
  const [tagText, setTagText] = useState("");

  useEffect(() => {
    setContact({
      name: conversation?.contact?.name || "",
      email: conversation?.contact?.email || "",
      phone: conversation?.contact?.phone || "",
    });
    setTagText("");
  }, [conversation?.id, conversation?.contact?.email, conversation?.contact?.name, conversation?.contact?.phone]);

  if (!conversation) return <aside className="zaplane-inbox-details" />;

  const saveContact = (field) => {
    if ((conversation.contact?.[field] || "") === contact[field]) return;
    onUpdate({ contact: { [field]: contact[field] } });
  };

  const addTag = (e) => {
    e.preventDefault();
    const tag = tagText.trim();
    if (!tag || conversation.tags.includes(tag)) return setTagText("");
    onUpdate({ tags: [...conversation.tags, tag] });
    setTagText("");
  };

  return (
    <aside className="zaplane-inbox-details" aria-label={__("Details", "zaplane")}>
      <div className="zaplane-inbox-contact">
        <span className="zaplane-inbox-avatar is-lg" aria-hidden="true">
          {conversation.contact?.avatar_url ? <img src={conversation.contact.avatar_url} alt="" /> : initials(conversation.contact?.name)}
        </span>
        <div className="min-w-0">
          <div className="zaplane-inbox-name">{conversation.contact?.name}</div>
          <div className="zaplane-inbox-sub">
            {CHANNEL_LABELS[conversation.channel] || conversation.channel} · {__("since", "zaplane")} {clockTime(conversation.created_at)}
          </div>
        </div>
      </div>

      <section className="zaplane-inbox-section">
        <h3>{__("Conversation", "zaplane")}</h3>
        <label className="zaplane-inbox-field">
          <span>{__("Status", "zaplane")}</span>
          <select className="zaplane-inbox-select" value={conversation.status} onChange={(e) => onUpdate({ status: e.target.value })}>
            {STATUSES.map((s) => (
              <option key={s.value} value={s.value}>
                {s.label}
              </option>
            ))}
          </select>
        </label>
        <label className="zaplane-inbox-field">
          <span>{__("Assigned to", "zaplane")}</span>
          <select
            className="zaplane-inbox-select"
            value={conversation.assignee?.id || 0}
            onChange={(e) => onUpdate({ assignee_id: parseInt(e.target.value, 10) || 0 })}
          >
            <option value={0}>{__("Nobody", "zaplane")}</option>
            {team.map((u) => (
              <option key={u.id} value={u.id}>
                {u.name}
              </option>
            ))}
          </select>
        </label>
        <div className="zaplane-inbox-field is-row">
          <span>
            {__("AI assistant answers", "zaplane")}
            {!aiReady && <em className="zaplane-inbox-hint"> — {__("set up in Settings", "zaplane")}</em>}
          </span>
          <ZAPToggle
            checked={conversation.handler === "bot"}
            disabled={!aiReady && conversation.handler !== "bot"}
            onChange={(on) => onUpdate({ handler: on ? "bot" : "human" })}
            size="sm"
          />
        </div>
        {conversation.handler === "workflow" && (
          <p className="zaplane-inbox-hint">{__("A workflow is answering this conversation.", "zaplane")}</p>
        )}
      </section>

      <section className="zaplane-inbox-section">
        <h3>{__("Contact", "zaplane")}</h3>
        {["name", "email", "phone"].map((field) => (
          <label className="zaplane-inbox-field" key={field}>
            <span>{{ name: __("Name", "zaplane"), email: __("Email", "zaplane"), phone: __("Phone", "zaplane") }[field]}</span>
            <input
              className="zaplane-inbox-input"
              type={field === "email" ? "email" : "text"}
              value={contact[field]}
              onChange={(e) => setContact({ ...contact, [field]: e.target.value })}
              onBlur={() => saveContact(field)}
            />
          </label>
        ))}
      </section>

      <section className="zaplane-inbox-section">
        <h3>{__("Tags", "zaplane")}</h3>
        <div className="zaplane-inbox-tags">
          {conversation.tags.map((tag) => (
            <span key={tag} className="zaplane-inbox-chip">
              {tag}
              <button
                type="button"
                aria-label={__("Remove tag", "zaplane") + " " + tag}
                onClick={() => onUpdate({ tags: conversation.tags.filter((t) => t !== tag) })}
              >
                ×
              </button>
            </span>
          ))}
        </div>
        <form onSubmit={addTag} className="flex gap-2 mt-2">
          <input
            className="zaplane-inbox-input"
            value={tagText}
            onChange={(e) => setTagText(e.target.value)}
            placeholder={__("Add a tag", "zaplane")}
            aria-label={__("Add a tag", "zaplane")}
          />
        </form>
      </section>

      {other.length > 0 && (
        <section className="zaplane-inbox-section">
          <h3>{__("Earlier conversations", "zaplane")}</h3>
          <ul className="zaplane-inbox-history">
            {other.map((o) => (
              <li key={o.id}>
                {CHANNEL_LABELS[o.channel] || o.channel} · {o.status} · {clockTime(o.last_message_at)}
              </li>
            ))}
          </ul>
        </section>
      )}
    </aside>
  );
};

export default Details;
