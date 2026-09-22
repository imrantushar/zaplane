import { useCallback, useEffect, useState } from "react";
import { __, _n, sprintf } from "@wordpress/i18n";
import { FiMonitor, FiSmartphone, FiTablet, FiMessageSquare, FiSend, FiExternalLink, FiUsers, FiX } from "react-icons/fi";
import { inboxApi, safeUrl, visiblePoll } from "./api";
import { Avatar } from "./channels";

const POLL = 8000;

const DEVICES = {
  mobile: { Icon: FiSmartphone, label: __("Phone", "zaplane") },
  tablet: { Icon: FiTablet, label: __("Tablet", "zaplane") },
  desktop: { Icon: FiMonitor, label: __("Computer", "zaplane") },
};

/** "4 min", "1 h 12 min": how long they've been on the site. */
function since(iso) {
  const mins = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 60000));
  if (mins < 1) return __("just now", "zaplane");
  if (mins < 60) return sprintf(__("%d min", "zaplane"), mins);
  return sprintf(__("%1$d h %2$d min", "zaplane"), Math.floor(mins / 60), mins % 60);
}

const host = (url) => {
  try {
    return new URL(url).host.replace(/^www\./, "");
  } catch (e) {
    return "";
  }
};

const path = (url) => {
  try {
    const u = new URL(url);
    return u.pathname + u.search;
  } catch (e) {
    return url;
  }
};

const Composer = ({ visitor, onSent, onCancel }) => {
  const [text, setText] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const send = async (e) => {
    e.preventDefault();
    if (!text.trim()) return;
    setBusy(true);
    setError("");
    try {
      const res = await inboxApi.messageVisitor(visitor.visitor_id, text.trim());
      onSent(res.conversation);
    } catch (err) {
      setError(err?.response?.data?.message || __("The message could not be sent.", "zaplane"));
      setBusy(false);
    }
  };
  return (
    <form className="zaplane-inbox-visitor-compose" onSubmit={send}>
      <input
        className="zaplane-inbox-input"
        autoFocus
        value={text}
        onChange={(e) => setText(e.target.value)}
        onKeyDown={(e) => e.key === "Escape" && onCancel()}
        placeholder={__("Hi! Can I help you find anything?", "zaplane")}
        aria-label={__("Message", "zaplane")}
        maxLength={4000}
      />
      <button type="submit" className="zaplane-inbox-small is-primary" disabled={busy || !text.trim()}>
        <FiSend />
        {busy ? __("Sending…", "zaplane") : __("Send", "zaplane")}
      </button>
      <button type="button" className="zaplane-inbox-icon-btn is-ghost" onClick={onCancel} aria-label={__("Cancel", "zaplane")}>
        <FiX />
      </button>
      {error && <div className="zaplane-inbox-failed">{error}</div>}
    </form>
  );
};

/**
 * Everyone on the site right now: where they are, how long they've been
 * browsing, and a way to say hello. Their chat opens (with a chime) on the
 * first message.
 */
const Visitors = ({ onOpenConversation, onCount, onOpenSettings }) => {
  const [data, setData] = useState({ loading: true, enabled: true, visitors: [] });
  const [composing, setComposing] = useState("");

  const load = useCallback(async () => {
    const res = await inboxApi.visitors();
    setData({ loading: false, enabled: res.enabled, visitors: res.visitors || [] });
    onCount?.(res.count || 0);
  }, [onCount]);

  useEffect(() => {
    load().catch(() => setData((d) => ({ ...d, loading: false })));
    return visiblePoll(load, POLL);
  }, [load]);

  if (!data.loading && !data.enabled) {
    return (
      <div className="zaplane-inbox-visitors is-empty">
        <FiUsers />
        <strong>{__("Live visitors are off", "zaplane")}</strong>
        <p>{__("Turn on “Show live visitors” in the website chat settings to see who is on your site.", "zaplane")}</p>
        <button type="button" className="zaplane-inbox-small is-primary" onClick={onOpenSettings}>
          {__("Open settings", "zaplane")}
        </button>
      </div>
    );
  }

  return (
    <div className="zaplane-inbox-visitors">
      <div className="zaplane-inbox-visitors-head">
        <span className="zaplane-inbox-live-dot" aria-hidden="true" />
        <strong>{sprintf(_n("%d visitor on your site now", "%d visitors on your site now", data.visitors.length, "zaplane"), data.visitors.length)}</strong>
        <span className="zaplane-inbox-hint">{__("Updates every few seconds. Send a message and their chat opens with a sound.", "zaplane")}</span>
      </div>

      {data.loading ? (
        <p className="zaplane-inbox-hint">{__("Loading…", "zaplane")}</p>
      ) : data.visitors.length === 0 ? (
        <div className="zaplane-inbox-visitors is-empty is-inline">
          <FiUsers />
          <strong>{__("Nobody is browsing right now", "zaplane")}</strong>
          <p>{__("Visitors show up here while they have a page of your site open.", "zaplane")}</p>
        </div>
      ) : (
        <ul className="zaplane-inbox-visitor-list">
          {data.visitors.map((v) => {
            const device = DEVICES[v.device];
            const name = v.name || sprintf(__("Visitor %s", "zaplane"), v.visitor_id.slice(-4).toUpperCase());
            const url = safeUrl(v.page_url);
            return (
              <li key={v.visitor_id} className={composing === v.visitor_id ? "is-composing" : ""}>
                <div className="zaplane-inbox-visitor">
                  <Avatar contact={{ id: v.visitor_id, name }} channel="web" />
                  <div className="zaplane-inbox-visitor-who">
                    <strong>
                      {name}
                      {v.signed_in && <span className="zaplane-inbox-chip">{__("Signed in", "zaplane")}</span>}
                    </strong>
                    <span className="zaplane-inbox-sub">{v.email || __("No email yet", "zaplane")}</span>
                  </div>
                  <div className="zaplane-inbox-visitor-page">
                    {url ? (
                      <a href={url} target="_blank" rel="noopener noreferrer" title={v.page_url}>
                        <strong>{v.page_title || path(v.page_url)}</strong>
                        <FiExternalLink />
                      </a>
                    ) : (
                      <strong>{v.page_title}</strong>
                    )}
                    <span className="zaplane-inbox-sub">{path(v.page_url)}</span>
                  </div>
                  <div className="zaplane-inbox-visitor-meta">
                    <span title={__("Time on site", "zaplane")}>{since(v.first_seen_at)}</span>
                    <span>{sprintf(_n("%d page", "%d pages", v.page_views, "zaplane"), v.page_views)}</span>
                    {device && (
                      <span title={device.label}>
                        <device.Icon aria-label={device.label} />
                      </span>
                    )}
                    {v.referrer && <span title={v.referrer}>{sprintf(__("from %s", "zaplane"), host(v.referrer))}</span>}
                  </div>
                  <div className="zaplane-inbox-visitor-actions">
                    {v.conversation_id > 0 && (
                      <button type="button" className="zaplane-inbox-small" onClick={() => onOpenConversation(v.conversation_id)}>
                        <FiMessageSquare />
                        {__("Open chat", "zaplane")}
                        {v.unread > 0 && <span className="zaplane-inbox-count">{v.unread}</span>}
                      </button>
                    )}
                    {composing !== v.visitor_id && (
                      <button type="button" className="zaplane-inbox-small is-primary" onClick={() => setComposing(v.visitor_id)}>
                        <FiSend />
                        {v.conversation_id > 0 ? __("Message", "zaplane") : __("Start chat", "zaplane")}
                      </button>
                    )}
                  </div>
                </div>
                {composing === v.visitor_id && (
                  <Composer
                    visitor={v}
                    onCancel={() => setComposing("")}
                    onSent={(conversation) => {
                      setComposing("");
                      onOpenConversation(conversation.id);
                    }}
                  />
                )}
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );
};

export default Visitors;
