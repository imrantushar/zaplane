import { useEffect, useRef, useState } from "react";
import { __ } from "@wordpress/i18n";
import { clockTime, safeUrl } from "./api";
import ProductPicker from "./ProductPicker";

const Attachment = ({ a }) => {
  const url = safeUrl(a.url);
  if (a.type === "product") {
    return (
      <a className="zaplane-inbox-product" href={url || undefined} target="_blank" rel="noopener noreferrer">
        {safeUrl(a.image) && <img src={a.image} alt="" />}
        <span>
          <strong>{a.name}</strong>
          <span className="zaplane-inbox-sub">
            {a.price_text}
            {a.in_stock === false && " · " + __("Out of stock", "zaplane")}
          </span>
        </span>
      </a>
    );
  }
  if (a.type === "image" && url) {
    return (
      <a className="zaplane-inbox-image" href={url} target="_blank" rel="noopener noreferrer">
        <img src={url} alt="" />
      </a>
    );
  }
  if (url) {
    return (
      <a className="zaplane-inbox-link" href={url} target="_blank" rel="noopener noreferrer">
        {a.filename || __("Attachment", "zaplane")}
      </a>
    );
  }
  return <span className="zaplane-inbox-chip">{(a.type || __("file", "zaplane")) + " · " + __("open it in the channel's app", "zaplane")}</span>;
};

const Message = ({ m }) => {
  if (m.sender_type === "system") {
    return <div className="zaplane-inbox-system">{m.body}</div>;
  }
  const mine = m.direction === "out";
  return (
    <div className={"zaplane-inbox-msg " + (mine ? "is-out" : "is-in") + (m.is_note ? " is-note" : "")}>
      <div className="zaplane-inbox-msg-meta">
        <span>{m.is_note ? __("Private note", "zaplane") + " · " + m.sender_name : m.sender_name || __("Customer", "zaplane")}</span>
        <span>{clockTime(m.created_at)}</span>
        {mine && !m.is_note && ["delivered", "read"].includes(m.delivery_status) && (
          <span>{m.delivery_status === "read" ? __("Read", "zaplane") : __("Delivered", "zaplane")}</span>
        )}
      </div>
      {m.body && <div className="zaplane-inbox-bubble">{m.body}</div>}
      {(m.attachments || []).map((a, i) => (
        <Attachment key={i} a={a} />
      ))}
      {m.delivery_status === "failed" && (
        <div className="zaplane-inbox-failed">
          {__("Not delivered", "zaplane")}
          {m.error ? ": " + m.error : ""}
        </div>
      )}
    </div>
  );
};

const Thread = ({ conversation, messages, canned, onSend, onSendProduct, sending, onToggleAi, hasStore }) => {
  const [text, setText] = useState("");
  const [picking, setPicking] = useState(false);
  const [isNote, setIsNote] = useState(false);
  const [error, setError] = useState("");
  const listRef = useRef(null);
  const lastId = messages.length ? messages[messages.length - 1].id : 0;

  useEffect(() => {
    if (listRef.current) listRef.current.scrollTop = listRef.current.scrollHeight;
  }, [lastId, conversation?.id]);

  useEffect(() => {
    setText("");
    setIsNote(false);
    setError("");
    setPicking(false);
  }, [conversation?.id]);

  if (!conversation) {
    return (
      <section className="zaplane-inbox-thread is-empty">
        <p>{__("Pick a conversation to read and reply.", "zaplane")}</p>
      </section>
    );
  }

  // "/shortcut" at the start of the box expands a saved reply.
  const slash = text.startsWith("/") ? text.slice(1).toLowerCase() : null;
  const suggestions =
    slash === null
      ? []
      : canned.filter((c) => (c.shortcut || "").startsWith(slash) || c.title.toLowerCase().includes(slash)).slice(0, 6);

  const submit = async (e) => {
    e?.preventDefault();
    const body = text.trim();
    if (!body || sending) return;
    setError("");
    try {
      await onSend(body, isNote);
      setText("");
    } catch (err) {
      setError(err?.response?.data?.message || __("The message could not be sent.", "zaplane"));
    }
  };

  return (
    <section className="zaplane-inbox-thread" aria-label={__("Conversation", "zaplane")}>
      {conversation.handler === "bot" && (
        <div className="zaplane-inbox-banner">
          <span>{__("The assistant is answering this conversation. Reply yourself to take it over.", "zaplane")}</span>
          <button type="button" className="zaplane-inbox-link" onClick={() => onToggleAi(false)}>
            {__("Take over now", "zaplane")}
          </button>
        </div>
      )}

      <div className="zaplane-inbox-messages" ref={listRef}>
        {messages.map((m) => (
          <Message key={m.id} m={m} />
        ))}
      </div>

      <form className={"zaplane-inbox-composer" + (isNote ? " is-note" : "")} onSubmit={submit}>
        <div className="zaplane-inbox-mode" role="tablist">
          <button type="button" role="tab" aria-selected={!isNote} className={!isNote ? "is-active" : ""} onClick={() => setIsNote(false)}>
            {__("Reply", "zaplane")}
          </button>
          <button type="button" role="tab" aria-selected={isNote} className={isNote ? "is-active" : ""} onClick={() => setIsNote(true)}>
            {__("Private note", "zaplane")}
          </button>
        </div>

        {picking && (
          <ProductPicker
            actionLabel={__("Send", "zaplane")}
            onClose={() => setPicking(false)}
            onPick={async (p) => {
              setError("");
              try {
                await onSendProduct(p.id, text.trim());
                setText("");
                setPicking(false);
              } catch (err) {
                setError(err?.response?.data?.message || __("The product could not be sent.", "zaplane"));
              }
            }}
          />
        )}

        {suggestions.length > 0 && (
          <ul className="zaplane-inbox-canned" role="listbox">
            {suggestions.map((c) => (
              <li key={c.id}>
                <button type="button" onClick={() => setText(c.body)}>
                  <strong>{c.shortcut ? "/" + c.shortcut : c.title}</strong>
                  <span>{c.body}</span>
                </button>
              </li>
            ))}
          </ul>
        )}

        <textarea
          value={text}
          onChange={(e) => setText(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === "Enter" && (e.metaKey || e.ctrlKey)) submit(e);
          }}
          rows={3}
          aria-label={isNote ? __("Private note", "zaplane") : __("Reply", "zaplane")}
          placeholder={
            isNote
              ? __("Only your team sees notes.", "zaplane")
              : __("Type a reply… Start with / to use a saved reply. Ctrl+Enter sends.", "zaplane")
          }
        />
        {error && <div className="zaplane-inbox-failed">{error}</div>}
        <div className="zaplane-inbox-composer-foot">
          <span className="flex items-center gap-3">
            {!isNote && hasStore && (
              <button type="button" className="zaplane-inbox-small" onClick={() => setPicking((v) => !v)} aria-expanded={picking}>
                {__("Send a product", "zaplane")}
              </button>
            )}
            <span className="zaplane-inbox-hint">{isNote ? __("Notes are never sent to the customer.", "zaplane") : ""}</span>
          </span>
          <button type="submit" className="zaplane-inbox-send" disabled={sending || !text.trim()}>
            {sending ? __("Sending…", "zaplane") : isNote ? __("Add note", "zaplane") : __("Send", "zaplane")}
          </button>
        </div>
      </form>
    </section>
  );
};

export default Thread;
