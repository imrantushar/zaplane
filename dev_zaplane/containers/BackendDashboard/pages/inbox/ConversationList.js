import { __ } from "@wordpress/i18n";
import { FiInbox, FiUser, FiCpu, FiSearch, FiChevronsLeft, FiChevronsRight } from "react-icons/fi";
import Search from "@ZAPComponents/Search";
import { shortTime } from "./api";
import { Avatar, channelOf } from "./channels";

export { CHANNEL_LABELS, initials } from "./channels";

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

const Skeleton = () => (
  <>
    {[0, 1, 2, 3].map((i) => (
      <li key={i} className="zaplane-inbox-skeleton" aria-hidden="true">
        <span className="is-circle" />
        <span className="is-lines">
          <span />
          <span />
        </span>
      </li>
    ))}
  </>
);

const Empty = ({ filters }) => {
  const filtered = filters.search || filters.assignee !== "any";
  return (
    <li className="zaplane-inbox-empty">
      <span className="zaplane-inbox-empty-icon">{filtered ? <FiSearch /> : <FiInbox />}</span>
      <strong>{filtered ? __("Nothing matches", "zaplane") : __("You're all caught up", "zaplane")}</strong>
      <span>
        {filtered
          ? __("Try another search or filter.", "zaplane")
          : __("New messages from your website chat and connected channels show up here.", "zaplane")}
      </span>
    </li>
  );
};

const STATUS_TITLES = {
  open: __("Open", "zaplane"),
  pending: __("Pending", "zaplane"),
  closed: __("Closed", "zaplane"),
  all: __("All conversations", "zaplane"),
};

/** Just the avatars: enough to spot who's waiting, with the thread given the room. */
const Rail = ({ items, counts, filters, activeId, onSelect, onToggleCollapsed }) => (
  <aside className="zaplane-inbox-list is-rail" aria-label={__("Conversations", "zaplane")}>
    <div className="zaplane-inbox-rail-head">
      <button
        type="button"
        className="zaplane-inbox-icon-btn"
        onClick={onToggleCollapsed}
        aria-label={__("Expand conversation list", "zaplane")}
        title={__("Expand conversation list", "zaplane")}
      >
        <FiChevronsRight />
      </button>
      {filters.status !== "all" && counts?.[filters.status] > 0 && (
        <span className="zaplane-inbox-count" title={STATUS_TITLES[filters.status]}>
          {counts[filters.status]}
        </span>
      )}
    </div>
    <ul className="zaplane-inbox-items">
      {items.map((c) => (
        <li key={c.id}>
          <button
            type="button"
            className={"zaplane-inbox-rail-item" + (c.id === activeId ? " is-active" : "")}
            onClick={() => onSelect(c.id)}
            title={(c.contact?.name || "") + (c.last_message_preview ? " — " + c.last_message_preview : "")}
            aria-label={c.contact?.name}
            aria-current={c.id === activeId ? "true" : undefined}
          >
            <Avatar contact={c.contact} channel={c.channel} channelLabel={c.channel_label} />
            {c.unread_count > 0 && <span className="zaplane-inbox-rail-unread">{c.unread_count > 9 ? "9+" : c.unread_count}</span>}
          </button>
        </li>
      ))}
    </ul>
  </aside>
);

const ConversationList = (props) => {
  const { items, counts, filters, setFilters, activeId, onSelect, loading, collapsed, onToggleCollapsed } = props;
  if (collapsed) return <Rail {...props} />;
  return (
  <aside className="zaplane-inbox-list" aria-label={__("Conversations", "zaplane")}>
    <div className="zaplane-inbox-list-head">
      <div className="zaplane-inbox-list-title">
        <strong>{__("Conversations", "zaplane")}</strong>
        <button
          type="button"
          className="zaplane-inbox-icon-btn is-ghost"
          onClick={onToggleCollapsed}
          aria-label={__("Collapse conversation list", "zaplane")}
          title={__("Collapse conversation list", "zaplane")}
        >
          <FiChevronsLeft />
        </button>
      </div>
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
      <div className="zaplane-inbox-list-tools">
        <div className="zaplane-inbox-search">
          <Search
            placeholder={__("Search…", "zaplane")}
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
      {loading && items.length === 0 && <Skeleton />}
      {!loading && items.length === 0 && <Empty filters={filters} />}
      {items.map((c) => {
        const channel = channelOf(c.channel, c.channel_label);
        return (
          <li key={c.id}>
            <button
              type="button"
              className={"zaplane-inbox-item" + (c.id === activeId ? " is-active" : "") + (c.unread_count > 0 ? " is-unread" : "")}
              onClick={() => onSelect(c.id)}
              aria-current={c.id === activeId ? "true" : undefined}
            >
              <Avatar contact={c.contact} channel={c.channel} channelLabel={c.channel_label} />
              <span className="zaplane-inbox-item-body">
                <span className="zaplane-inbox-item-top">
                  <span className="zaplane-inbox-name">{c.contact?.name}</span>
                  <span className="zaplane-inbox-time">{shortTime(c.last_message_at)}</span>
                </span>
                <span className="zaplane-inbox-item-bottom">
                  <span className="zaplane-inbox-preview">{c.last_message_preview || "—"}</span>
                  {c.unread_count > 0 && <span className="zaplane-inbox-unread">{c.unread_count}</span>}
                </span>
                <span className="zaplane-inbox-meta">
                  <span className="zaplane-inbox-chip is-channel">
                    <channel.Icon style={{ color: channel.color }} />
                    {channel.label}
                  </span>
                  {c.handler === "bot" && (
                    <span className="zaplane-inbox-chip is-ai">
                      <FiCpu />
                      {__("Assistant", "zaplane")}
                    </span>
                  )}
                  {c.assignee && (
                    <span className="zaplane-inbox-chip" title={__("Assigned to", "zaplane") + " " + c.assignee.name}>
                      <FiUser />
                      {c.assignee.name}
                    </span>
                  )}
                </span>
              </span>
            </button>
          </li>
        );
      })}
    </ul>
  </aside>
  );
};

export default ConversationList;
