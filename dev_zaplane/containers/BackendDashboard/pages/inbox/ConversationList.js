import { __ } from "@wordpress/i18n";
import Search from "@ZAPComponents/Search";
import { shortTime } from "./api";

const STATUS_TABS = [
  { value: "open", label: __("Open", "zaplane") },
  { value: "pending", label: __("Pending", "zaplane") },
  { value: "closed", label: __("Closed", "zaplane") },
  { value: "all", label: __("All", "zaplane") },
];

const ASSIGNEE_FILTERS = [
  { value: "any", label: __("Everyone", "zaplane") },
  { value: "me", label: __("Mine", "zaplane") },
  { value: "unassigned", label: __("Unassigned", "zaplane") },
  { value: "bot", label: __("With assistant", "zaplane") },
];

export const CHANNEL_LABELS = {
  web: __("Website", "zaplane"),
  messenger: __("Messenger", "zaplane"),
  whatsapp: __("WhatsApp", "zaplane"),
  instagram: __("Instagram", "zaplane"),
  email: __("Email", "zaplane"),
  telegram: __("Telegram", "zaplane"),
};

export function initials(name) {
  return (name || "?")
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0].toUpperCase())
    .join("");
}

const ConversationList = ({ items, counts, filters, setFilters, activeId, onSelect, loading }) => (
  <aside className="zaplane-inbox-list" aria-label={__("Conversations", "zaplane")}>
    <div className="zaplane-inbox-list-head">
      <div className="zaplane-inbox-tabs" role="tablist">
        {STATUS_TABS.map((t) => (
          <button
            key={t.value}
            type="button"
            role="tab"
            aria-selected={filters.status === t.value}
            className={"zaplane-inbox-tab" + (filters.status === t.value ? " is-active" : "")}
            onClick={() => setFilters({ ...filters, status: t.value })}
          >
            {t.label}
            {t.value !== "all" && counts?.[t.value] > 0 && <span className="zaplane-inbox-count">{counts[t.value]}</span>}
          </button>
        ))}
      </div>
      <div className="flex items-center gap-2">
        <div className="flex-1 min-w-0">
          <Search
            placeholder={__("Search name, email, message…", "zaplane")}
            onSearchHandler={(v) => setFilters((f) => ({ ...f, search: v }))}
            debounce={400}
          />
        </div>
        <select
          className="zaplane-inbox-select"
          aria-label={__("Filter by assignee", "zaplane")}
          value={filters.assignee}
          onChange={(e) => setFilters({ ...filters, assignee: e.target.value })}
        >
          {ASSIGNEE_FILTERS.map((a) => (
            <option key={a.value} value={a.value}>
              {a.label}
            </option>
          ))}
        </select>
      </div>
    </div>

    <ul className="zaplane-inbox-items">
      {!loading && items.length === 0 && (
        <li className="zaplane-inbox-empty">
          {filters.search
            ? __("No conversations match your search.", "zaplane")
            : __("No conversations here yet. Messages from your chat widget will show up in this list.", "zaplane")}
        </li>
      )}
      {items.map((c) => (
        <li key={c.id}>
          <button
            type="button"
            className={"zaplane-inbox-item" + (c.id === activeId ? " is-active" : "") + (c.unread_count > 0 ? " is-unread" : "")}
            onClick={() => onSelect(c.id)}
          >
            <span className="zaplane-inbox-avatar" aria-hidden="true">
              {c.contact?.avatar_url ? <img src={c.contact.avatar_url} alt="" /> : initials(c.contact?.name)}
            </span>
            <span className="zaplane-inbox-item-body">
              <span className="zaplane-inbox-item-top">
                <span className="zaplane-inbox-name">{c.contact?.name}</span>
                <span className="zaplane-inbox-time">{shortTime(c.last_message_at)}</span>
              </span>
              <span className="zaplane-inbox-preview">{c.last_message_preview || "—"}</span>
              <span className="zaplane-inbox-meta">
                <span className="zaplane-inbox-chip">{CHANNEL_LABELS[c.channel] || c.channel}</span>
                {c.handler === "bot" && <span className="zaplane-inbox-chip is-ai">{__("Assistant", "zaplane")}</span>}
                {c.assignee && <span className="zaplane-inbox-chip">{c.assignee.name}</span>}
                {c.unread_count > 0 && <span className="zaplane-inbox-unread">{c.unread_count}</span>}
              </span>
            </span>
          </button>
        </li>
      ))}
    </ul>
  </aside>
);

export default ConversationList;
