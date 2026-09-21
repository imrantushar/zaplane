import { useEffect, useState } from "react";
import { __, sprintf } from "@wordpress/i18n";
import { FiX, FiShoppingBag, FiChevronDown, FiMail, FiPhone, FiCopy, FiCheck, FiMessageCircle, FiUser, FiTag, FiClock } from "react-icons/fi";
import ZAPToggle from "@ZAPComponents/ZAPToggle";
import { Avatar, CHANNEL_LABELS } from "./channels";
import { clockTime } from "./api";
import OrderForm from "./OrderForm";

const STATUSES = [
  { value: "open", label: __("Open", "zaplane") },
  { value: "pending", label: __("Pending", "zaplane") },
  { value: "snoozed", label: __("Snoozed", "zaplane") },
  { value: "closed", label: __("Closed", "zaplane") },
];

/** A details block whose body folds away; each remembers its state per browser. */
const Section = ({ id, title, Icon, summary, children, aside, defaultOpen = false }) => {
  const key = "zaplane-inbox-section-" + id;
  const [open, setOpen] = useState(() => {
    try {
      const saved = window.localStorage.getItem(key);
      return saved === null ? defaultOpen : saved === "1";
    } catch (e) {
      return defaultOpen;
    }
  });
  const toggle = () => {
    setOpen((o) => {
      try {
        window.localStorage.setItem(key, o ? "0" : "1");
      } catch (e) {
        // Convenience only.
      }
      return !o;
    });
  };
  return (
    <section className={"zaplane-inbox-section" + (open ? " is-open" : "")}>
      <button type="button" className="zaplane-inbox-section-head" onClick={toggle} aria-expanded={open}>
        <span className="zaplane-inbox-section-icon">{Icon && <Icon />}</span>
        <span className="zaplane-inbox-section-text">
          <h3>{title}</h3>
          {!open && summary && <span className="zaplane-inbox-section-summary">{summary}</span>}
        </span>
        {aside}
        <FiChevronDown className="zaplane-inbox-chevron" />
      </button>
      {open && <div className="zaplane-inbox-section-body">{children}</div>}
    </section>
  );
};

const CopyChip = ({ Icon, value }) => {
  const [done, setDone] = useState(false);
  if (!value) return null;
  return (
    <button
      type="button"
      className="zaplane-inbox-copychip"
      title={__("Copy", "zaplane")}
      onClick={async () => {
        try {
          await navigator.clipboard.writeText(value);
          setDone(true);
          window.setTimeout(() => setDone(false), 1200);
        } catch (e) {
          // Clipboard can be blocked.
        }
      }}
    >
      <Icon />
      <span>{value}</span>
      {done ? <FiCheck className="is-done" /> : <FiCopy className="is-copy" />}
    </button>
  );
};

const Details = ({ conversation, other, team, aiReady, onUpdate, store, onPlaceOrder, onClose }) => {
  const [contact, setContact] = useState({ name: "", email: "", phone: "" });
  const [tagText, setTagText] = useState("");
  const [ordering, setOrdering] = useState(false);
  const [placed, setPlaced] = useState(null);

  useEffect(() => {
    setContact({
      name: conversation?.contact?.name || "",
      email: conversation?.contact?.email || "",
      phone: conversation?.contact?.phone || "",
    });
    setTagText("");
  }, [conversation?.id]);

  useEffect(() => {
    setOrdering(false);
    setPlaced(null);
  }, [conversation?.id]);

  useEffect(() => {
    setContact({
      name: conversation?.contact?.name || "",
      email: conversation?.contact?.email || "",
      phone: conversation?.contact?.phone || "",
    });
  }, [conversation?.id, conversation?.contact?.email, conversation?.contact?.name, conversation?.contact?.phone]);

  if (!conversation) return null;

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
        <Avatar contact={conversation.contact} channel={conversation.channel} size="lg" />
        <div className="min-w-0 flex-1">
          <div className="zaplane-inbox-name">{conversation.contact?.name}</div>
          <div className="zaplane-inbox-sub">
            {CHANNEL_LABELS[conversation.channel] || conversation.channel} · {__("since", "zaplane")} {clockTime(conversation.created_at)}
          </div>
        </div>
        <button type="button" className="zaplane-inbox-icon-btn is-close" onClick={onClose} aria-label={__("Close details", "zaplane")}>
          <FiX />
        </button>
      </div>
      {(conversation.contact?.email || conversation.contact?.phone) && (
        <div className="zaplane-inbox-copychips">
          <CopyChip Icon={FiMail} value={conversation.contact?.email} />
          <CopyChip Icon={FiPhone} value={conversation.contact?.phone} />
        </div>
      )}

      <Section
        id="conversation"
        Icon={FiMessageCircle}
        defaultOpen
        title={__("Conversation", "zaplane")}
        summary={[
          (STATUSES.find((x) => x.value === conversation.status) || {}).label,
          conversation.assignee?.name || __("Unassigned", "zaplane"),
          conversation.handler === "bot" ? __("Assistant on", "zaplane") : null,
        ]
          .filter(Boolean)
          .join(" · ")}
      >
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
      </Section>

      {store && (
        <Section
          id="orders"
          Icon={FiShoppingBag}
          title={__("Orders", "zaplane")}
          summary={
            (conversation.orders || []).length
              ? sprintf(__("Last: #%1$s · %2$s", "zaplane"), conversation.orders[conversation.orders.length - 1].number, conversation.orders[conversation.orders.length - 1].total)
              : __("No orders yet", "zaplane")
          }
          aside={(conversation.orders || []).length ? <span className="zaplane-inbox-count">{conversation.orders.length}</span> : null}
        >
          {placed && (
            <p className="zaplane-inbox-success" role="status">
              {__("Order", "zaplane")} #{placed.number} · {placed.total_text}{" "}
              <a href={placed.admin_url} target="_blank" rel="noopener noreferrer">
                {__("Open order", "zaplane")}
              </a>
            </p>
          )}
          {(conversation.orders || []).length > 0 && (
            <ul className="zaplane-inbox-history">
              {conversation.orders
                .slice()
                .reverse()
                .map((o) => (
                  <li key={o.id}>
                    #{o.number} · {o.total} · {o.placed_by === "ai" ? __("by the assistant", "zaplane") : o.placed_by === "workflow" ? __("by a workflow", "zaplane") : __("by the team", "zaplane")}
                  </li>
                ))}
            </ul>
          )}
          {ordering ? (
            <OrderForm
              conversation={conversation}
              onCancel={() => setOrdering(false)}
              onPlace={async (data) => {
                const res = await onPlaceOrder(data);
                setPlaced(res.order);
                setOrdering(false);
              }}
            />
          ) : (
            <button type="button" className="zaplane-inbox-small is-block" onClick={() => setOrdering(true)}>
              <FiShoppingBag />
              {__("Create an order", "zaplane")} ({store})
            </button>
          )}
        </Section>
      )}

      <Section
        id="contact"
        Icon={FiUser}
        title={__("Contact", "zaplane")}
        summary={[conversation.contact?.email, conversation.contact?.phone].filter(Boolean).join(" · ") || __("No email or phone yet", "zaplane")}
      >
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
      </Section>

      <Section
        id="tags"
        Icon={FiTag}
        title={__("Tags", "zaplane")}
        summary={conversation.tags.length ? conversation.tags.join(", ") : __("No tags", "zaplane")}
        aside={conversation.tags.length ? <span className="zaplane-inbox-count">{conversation.tags.length}</span> : null}
      >
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
      </Section>

      {other.length > 0 && (
        <Section
          id="history"
          Icon={FiClock}
          title={__("Earlier conversations", "zaplane")}
          summary={sprintf(__("Last on %s", "zaplane"), clockTime(other[0].last_message_at))}
          aside={<span className="zaplane-inbox-count">{other.length}</span>}
        >
          <ul className="zaplane-inbox-history">
            {other.map((o) => (
              <li key={o.id}>
                {CHANNEL_LABELS[o.channel] || o.channel} · {o.status} · {clockTime(o.last_message_at)}
              </li>
            ))}
          </ul>
        </Section>
      )}
    </aside>
  );
};

export default Details;
