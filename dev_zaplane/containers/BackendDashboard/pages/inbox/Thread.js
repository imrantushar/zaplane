import { useEffect, useRef, useState } from "react";
import { __ } from "@wordpress/i18n";
import { clockTime } from "./api";

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
      </div>
      <div className="zaplane-inbox-bubble">{m.body}</div>
      {m.delivery_status === "failed" && (
        <div className="zaplane-inbox-failed">
          {__("Not delivered", "zaplane")}
          {m.error ? ": " + m.error : ""}
        </div>
      )}
    </div>
  );
};

const Thread = ({ conversation, messages, canned, onSend, sending, onToggleAi }) => {
  const [text, setText] = useState("");
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
          <span className="zaplane-inbox-hint">
            {isNote ? __("Notes are never sent to the customer.", "zaplane") : ""}
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
