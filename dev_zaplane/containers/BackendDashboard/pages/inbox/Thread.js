import { Fragment, useEffect, useRef, useState } from "react";
import { __, sprintf } from "@wordpress/i18n";
import { FiArrowLeft, FiCheck, FiRotateCcw, FiSidebar, FiShoppingBag, FiSend, FiCpu, FiMessageSquare, FiLock, FiCornerUpLeft, FiCopy, FiEdit2, FiTrash2, FiX, FiGitBranch, FiPause, FiBookOpen, FiThumbsUp, FiThumbsDown } from "react-icons/fi";
import { safeUrl } from "./api";
import ProductPicker from "./ProductPicker";
import { Avatar, channelOf, dayLabel, hourTime } from "./channels";

const Attachment = ({ a }) => {
  const url = safeUrl(a.url);
  if (a.type === "product") {
    return (
      <a className="zaplane-inbox-product" href={url || undefined} target="_blank" rel="noopener noreferrer">
        <span className="zaplane-inbox-product-img">{safeUrl(a.image) ? <img src={a.image} alt="" /> : <FiShoppingBag />}</span>
        <span className="zaplane-inbox-product-body">
          <strong>{a.name}</strong>
          {a.option_label && <span className="zaplane-inbox-product-option">{a.option_label}</span>}
          <span className="zaplane-inbox-product-price">
            {a.price_text}
            {a.compare_text && <s>{a.compare_text}</s>}
          </span>
          {a.in_stock === false && <span className="zaplane-inbox-product-stock">{__("Out of stock", "zaplane")}</span>}
          {url && <span className="zaplane-inbox-product-cta">{__("View product", "zaplane")} ↗</span>}
        </span>
      </a>
    );
  }
  if (a.type === "article" && url) {
    return (
      <a className="zaplane-inbox-article" href={url} target="_blank" rel="noopener noreferrer">
        {safeUrl(a.image) ? <img src={a.image} alt="" /> : <FiBookOpen />}
        <span>
          <strong>{a.title}</strong>
          {a.excerpt && <em>{a.excerpt}</em>}
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

const STATUS_LABELS = {
  open: __("Open", "zaplane"),
  pending: __("Pending", "zaplane"),
  snoozed: __("Snoozed", "zaplane"),
  closed: __("Resolved", "zaplane"),
};

// Messages from the same side within this long read as one run: the name and
// time show once, and the customer's avatar sits beside the last bubble.
const GROUP_MS = 5 * 60 * 1000;

const sameRun = (a, b) =>
  !!a &&
  !!b &&
  a.sender_type !== "system" &&
  b.sender_type !== "system" &&
  a.direction === b.direction &&
  !!a.is_note === !!b.is_note &&
  (a.sender_name || "") === (b.sender_name || "") &&
  Math.abs(new Date(b.created_at) - new Date(a.created_at)) < GROUP_MS;

/** The quoted message above a reply; clicking it jumps to the original. */
const Quote = ({ q, onJump }) => (
  <button type="button" className="zaplane-inbox-quote" onClick={() => onJump?.(q.id)} title={__("Go to the original message", "zaplane")}>
    <strong>{q.sender_name || __("Customer", "zaplane")}</strong>
    <span>{q.excerpt || __("Message deleted", "zaplane")}</span>
  </button>
);

const Message = ({ m, contact, first, last, onReply, onEdit, onDelete, onJump, onUseText }) => {
  const [mode, setMode] = useState(""); // "", "edit", "confirm"
  const [draft, setDraft] = useState(m.body);
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState("");
  const [copied, setCopied] = useState(false);

  if (m.sender_type === "system") {
    return <div className="zaplane-inbox-system">{m.body}</div>;
  }
  const mine = m.direction === "out";
  const meta = m.meta || {};
  const byWorkflow = m.sender_type === "workflow";
  const held = byWorkflow && !!meta.held;
  const byAi = m.sender_type === "ai" || (byWorkflow && !!meta.by_ai);
  const byKnowledge = m.sender_type === "auto";
  // Offer edit/delete on the team's own replies; when the channel can't do it,
  // the buttons stay visible but disabled, with the reason as their tooltip.
  const showChange = mine && !m.deleted && (m.can_change || m.sender_type === "agent");

  const run = async (fn) => {
    setBusy(true);
    setErr("");
    try {
      await fn();
      setMode("");
    } catch (e) {
      setErr(e?.response?.data?.message || __("That didn't work. Please try again.", "zaplane"));
    } finally {
      setBusy(false);
    }
  };

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(m.body || "");
      setCopied(true);
      window.setTimeout(() => setCopied(false), 1200);
    } catch (e) {
      // Clipboard can be blocked.
    }
  };

  return (
    <div
      id={"zaplane-msg-" + m.id}
      className={"zaplane-inbox-row " + (mine ? "is-out" : "is-in") + (first ? " is-first" : "") + (last ? " is-last" : "") + (mode ? " is-busy" : "")}
    >
      {!mine && <span className="zaplane-inbox-row-avatar">{last && <Avatar contact={contact} size="sm" />}</span>}
      <div className={"zaplane-inbox-msg " + (mine ? "is-out" : "is-in") + (m.is_note ? " is-note" : "") + (byAi ? " is-ai" : "") + (byKnowledge ? " is-knowledge" : "") + (m.deleted ? " is-deleted" : "")}>
        {first && (
          <div className="zaplane-inbox-msg-meta">
            <span className="zaplane-inbox-msg-who">
              {m.is_note && !held && <FiLock />}
              {held && <FiPause />}
              {byWorkflow && !held && <FiGitBranch />}
              {byKnowledge && <FiBookOpen />}
              {byAi && <FiCpu />}
              {held ? (
                __("Held reply", "zaplane") + " · "
              ) : m.is_note ? (
                __("Private note", "zaplane") + " · "
              ) : null}
              {byWorkflow && meta.workflow_id ? (
                <a href={"admin.php?page=zaplane-workflows&action=edit&id=" + meta.workflow_id} target="_blank" rel="noopener noreferrer">
                  {m.sender_name}
                </a>
              ) : (
                m.sender_name || (m.is_note ? "" : contact?.name) || __("Customer", "zaplane")
              )}
            </span>
            <span>{hourTime(m.created_at)}</span>
          </div>
        )}

        {m.reply_to && !m.deleted && <Quote q={m.reply_to} onJump={onJump} />}

        {mode === "edit" ? (
          <form
            className="zaplane-inbox-edit"
            onSubmit={(e) => {
              e.preventDefault();
              if (draft.trim() && draft.trim() !== m.body) run(() => onEdit(m.id, draft.trim()));
              else setMode("");
            }}
          >
            <textarea
              autoFocus
              value={draft}
              rows={Math.min(10, Math.max(3, draft.split("\n").length + Math.ceil(draft.length / 60)))}
              onChange={(e) => setDraft(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === "Escape") setMode("");
                if (e.key === "Enter" && (e.metaKey || e.ctrlKey)) e.currentTarget.form.requestSubmit();
              }}
              aria-label={__("Edit message", "zaplane")}
            />
            <span className="zaplane-inbox-edit-foot">
              <span className="zaplane-inbox-hint">{__("Esc to cancel", "zaplane")}</span>
              <button type="button" className="zaplane-inbox-small" onClick={() => setMode("")} disabled={busy}>
                {__("Cancel", "zaplane")}
              </button>
              <button type="submit" className="zaplane-inbox-small is-primary" disabled={busy || !draft.trim()}>
                {busy ? __("Saving…", "zaplane") : __("Save", "zaplane")}
              </button>
            </span>
          </form>
        ) : m.deleted ? (
          <div className="zaplane-inbox-bubble is-deleted">
            <FiTrash2 />
            {__("Message deleted", "zaplane")}
          </div>
        ) : (
          m.body && (
            <div className="zaplane-inbox-bubble-wrap">
              <div className="zaplane-inbox-bubble" title={hourTime(m.created_at)}>
                {m.body}
              </div>
              {!mode && (
                <div className="zaplane-inbox-actions" role="toolbar" aria-label={__("Message actions", "zaplane")}>
                  <button type="button" onClick={() => onReply(m)} title={__("Reply", "zaplane")} aria-label={__("Reply", "zaplane")}>
                    <FiCornerUpLeft />
                  </button>
                  <button type="button" onClick={copy} title={copied ? __("Copied", "zaplane") : __("Copy text", "zaplane")} aria-label={__("Copy text", "zaplane")}>
                    {copied ? <FiCheck /> : <FiCopy />}
                  </button>
                  {showChange && (
                    <>
                      <button
                        type="button"
                        disabled={!m.can_change}
                        onClick={() => {
                          setDraft(m.body);
                          setMode("edit");
                        }}
                        title={m.can_change ? __("Edit", "zaplane") : m.change_note}
                        aria-label={__("Edit", "zaplane")}
                      >
                        <FiEdit2 />
                      </button>
                      <button
                        type="button"
                        className="is-danger"
                        disabled={!m.can_change}
                        onClick={() => setMode("confirm")}
                        title={m.can_change ? __("Delete", "zaplane") : m.change_note}
                        aria-label={__("Delete", "zaplane")}
                      >
                        <FiTrash2 />
                      </button>
                    </>
                  )}
                </div>
              )}
            </div>
          )
        )}

        {(m.attachments || []).map((a, i) => (
          <Attachment key={i} a={a} />
        ))}

        {byKnowledge && (m.quick_replies || []).length > 0 && (
          <div className="zaplane-inbox-qr" aria-label={__("Buttons the customer sees", "zaplane")}>
            {m.quick_prompt && <em>{m.quick_prompt}</em>}
            {m.quick_replies.map((q) => (
              <span key={q} className={meta.feedback && q === (meta.feedback === "yes" ? m.quick_replies[0] : m.quick_replies[1]) ? "is-picked" : ""}>
                {q}
              </span>
            ))}
          </div>
        )}
        {byKnowledge && meta.feedback && (
          <span className={"zaplane-inbox-feedback is-" + meta.feedback}>
            {meta.feedback === "yes" ? <FiThumbsUp /> : <FiThumbsDown />}
            {meta.feedback === "yes" ? __("Customer found this helpful", "zaplane") : __("Customer said this didn't help", "zaplane")}
          </span>
        )}

        {held && !m.deleted && (
          <div className="zaplane-inbox-held">
            <span>{__("Not sent: this conversation isn't answered by workflows.", "zaplane")}</span>
            <button type="button" className="zaplane-inbox-small" onClick={() => onUseText(m.body)}>
              <FiCornerUpLeft />
              {__("Use as reply", "zaplane")}
            </button>
          </div>
        )}

        {mode === "confirm" && (
          <div className="zaplane-inbox-confirm" role="alertdialog" aria-label={__("Delete message", "zaplane")}>
            <span>{m.is_note ? __("Delete this note?", "zaplane") : __("Delete for everyone? The customer will see “Message deleted”.", "zaplane")}</span>
            <button type="button" className="zaplane-inbox-small" onClick={() => setMode("")} disabled={busy}>
              {__("Cancel", "zaplane")}
            </button>
            <button type="button" className="zaplane-inbox-small is-danger" onClick={() => run(() => onDelete(m.id))} disabled={busy}>
              {busy ? __("Deleting…", "zaplane") : __("Delete", "zaplane")}
            </button>
          </div>
        )}

        {err && <div className="zaplane-inbox-failed">{err}</div>}

        {(m.edited && !m.deleted) || (last && mine && !m.is_note && ["delivered", "read"].includes(m.delivery_status)) ? (
          <span className="zaplane-inbox-msg-foot">
            {m.edited && !m.deleted && <span className="zaplane-inbox-edited">{__("Edited", "zaplane")}</span>}
            {last && mine && !m.is_note && ["delivered", "read"].includes(m.delivery_status) && (
              <span className={"zaplane-inbox-receipt is-" + m.delivery_status}>
                <FiCheck />
                {m.delivery_status === "read" && <FiCheck />}
                {m.delivery_status === "read" ? __("Read", "zaplane") : __("Delivered", "zaplane")}
              </span>
            )}
          </span>
        ) : null}
        {m.delivery_status === "failed" && (
          <div className="zaplane-inbox-failed">
            {__("Not delivered", "zaplane")}
            {m.error ? ": " + m.error : ""}
          </div>
        )}
      </div>
    </div>
  );
};

const Thread = ({ conversation, messages, canned, onSend, onSendProduct, onEditMessage, onDeleteMessage, sending, onToggleAi, hasStore, onUpdate, onBack, detailsOpen, onToggleDetails }) => {
  const [text, setText] = useState("");
  const [replyTo, setReplyTo] = useState(null);
  const inputRef = useRef(null);
  const [picking, setPicking] = useState(false);
  const [isNote, setIsNote] = useState(false);
  const [activeSuggestion, setActiveSuggestion] = useState(0);
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
    setReplyTo(null);
  }, [conversation?.id]);

  if (!conversation) {
    return (
      <section className="zaplane-inbox-thread is-empty">
        <span className="zaplane-inbox-empty-icon is-lg">
          <FiMessageSquare />
        </span>
        <strong>{__("No conversation selected", "zaplane")}</strong>
        <p>{__("Pick a conversation from the list to read it and reply.", "zaplane")}</p>
      </section>
    );
  }

  const channel = channelOf(conversation.channel);

  // A reply to the customer can't quote a private note, so quoting one
  // switches the box to a note.
  const startReply = (m) => {
    setReplyTo(m);
    if (m.is_note) setIsNote(true);
    inputRef.current?.focus();
  };

  const setMode = (note) => {
    setIsNote(note);
    if (!note && replyTo?.is_note) setReplyTo(null);
  };

  const jumpTo = (id) => {
    const el = document.getElementById("zaplane-msg-" + id);
    if (!el) return;
    el.scrollIntoView({ behavior: "smooth", block: "center" });
    el.classList.add("is-flash");
    window.setTimeout(() => el.classList.remove("is-flash"), 1200);
  };

  // "/shortcut" at the start of the box expands a saved reply.
  const slash = text.startsWith("/") ? text.slice(1).toLowerCase() : null;
  const suggestions =
    slash === null
      ? []
      : canned
          .filter((c) => (c.shortcut || "").toLowerCase().startsWith(slash) || (c.title || "").toLowerCase().includes(slash))
          .slice(0, 6);
  const highlighted = Math.min(activeSuggestion, Math.max(0, suggestions.length - 1));

  // {name} / {first_name} become the customer's name.
  const applyCanned = (c) => {
    const full = (conversation.contact?.name || "").trim();
    const known = full && !/^visitor\b/i.test(full);
    const first = known ? full.split(/\s+/)[0] : "";
    setText(
      (c.body || "")
        .replace(/\{\s*first_name\s*\}/gi, first)
        .replace(/\{\s*name\s*\}/gi, known ? full : "")
        .replace(/[ \t]+([,.!?])/g, "$1")
    );
    setActiveSuggestion(0);
    inputRef.current?.focus();
  };

  const submit = async (e) => {
    e?.preventDefault();
    const body = text.trim();
    if (!body || sending) return;
    setError("");
    try {
      await onSend(body, isNote, replyTo?.id || 0);
      setText("");
      setReplyTo(null);
    } catch (err) {
      setError(err?.response?.data?.message || __("The message could not be sent.", "zaplane"));
    }
  };

  return (
    <section className="zaplane-inbox-thread" aria-label={__("Conversation", "zaplane")}>
      <header className="zaplane-inbox-thread-head">
        <button type="button" className="zaplane-inbox-icon-btn is-back" onClick={onBack} aria-label={__("Back to conversations", "zaplane")}>
          <FiArrowLeft />
        </button>
        <Avatar contact={conversation.contact} channel={conversation.channel} />
        <div className="zaplane-inbox-thread-title">
          <strong>{conversation.contact?.name}</strong>
          <span className="zaplane-inbox-sub">
            {channel.label}
            {conversation.contact?.email ? " · " + conversation.contact.email : ""}
          </span>
        </div>
        <div className="zaplane-inbox-thread-actions">
          <span className={"zaplane-inbox-status is-" + conversation.status}>{STATUS_LABELS[conversation.status] || conversation.status}</span>
          {conversation.status === "closed" ? (
            <button type="button" className="zaplane-inbox-small" onClick={() => onUpdate({ status: "open" })}>
              <FiRotateCcw />
              {__("Reopen", "zaplane")}
            </button>
          ) : (
            <button type="button" className="zaplane-inbox-small is-primary" onClick={() => onUpdate({ status: "closed" })}>
              <FiCheck />
              {__("Resolve", "zaplane")}
            </button>
          )}
          <button
            type="button"
            className={"zaplane-inbox-icon-btn" + (detailsOpen ? " is-active" : "")}
            onClick={onToggleDetails}
            aria-pressed={detailsOpen}
            aria-label={detailsOpen ? __("Hide details", "zaplane") : __("Show details", "zaplane")}
            title={detailsOpen ? __("Hide details", "zaplane") : __("Show details", "zaplane")}
          >
            <FiSidebar />
          </button>
        </div>
      </header>

      {conversation.handler === "bot" && (
        <div className="zaplane-inbox-banner">
          <FiCpu />
          <span>{__("The assistant is answering this conversation. Reply yourself to take it over.", "zaplane")}</span>
          <button type="button" className="zaplane-inbox-link" onClick={() => onToggleAi(false)}>
            {__("Take over now", "zaplane")}
          </button>
        </div>
      )}

      <div className="zaplane-inbox-messages" ref={listRef}>
        {messages.map((m, i) => {
          const day = dayLabel(m.created_at);
          const showDay = i === 0 || day !== dayLabel(messages[i - 1].created_at);
          return (
            <Fragment key={m.id}>
              {showDay && day && (
                <div className="zaplane-inbox-day" role="separator">
                  <span>{day}</span>
                </div>
              )}
              <Message
                m={m}
                contact={conversation.contact}
                first={showDay || !sameRun(messages[i - 1], m)}
                last={i === messages.length - 1 || !sameRun(m, messages[i + 1]) || dayLabel(messages[i + 1].created_at) !== day}
                onReply={startReply}
                onEdit={onEditMessage}
                onDelete={onDeleteMessage}
                onJump={jumpTo}
                onUseText={(body) => {
                  setMode(false);
                  setText(body);
                  inputRef.current?.focus();
                }}
              />
            </Fragment>
          );
        })}
      </div>

      <form className={"zaplane-inbox-composer" + (isNote ? " is-note" : "")} onSubmit={submit}>
        <div className="zaplane-inbox-mode" role="tablist">
          <button type="button" role="tab" aria-selected={!isNote} className={!isNote ? "is-active" : ""} onClick={() => setMode(false)}>
            <FiCornerUpLeft />
            {__("Reply", "zaplane")}
          </button>
          <button type="button" role="tab" aria-selected={isNote} className={isNote ? "is-active is-note" : ""} onClick={() => setMode(true)}>
            <FiLock />
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
                await onSendProduct(p.id, text.trim(), p.option_id || 0);
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
            {suggestions.map((c, i) => (
              <li key={c.id} role="option" aria-selected={i === highlighted}>
                <button
                  type="button"
                  className={i === highlighted ? "is-active" : ""}
                  onMouseEnter={() => setActiveSuggestion(i)}
                  onMouseDown={(e) => e.preventDefault()}
                  onClick={() => applyCanned(c)}
                >
                  <strong>{c.shortcut ? "/" + c.shortcut : c.title}</strong>
                  <span>{c.body}</span>
                </button>
              </li>
            ))}
          </ul>
        )}

        <div className="zaplane-inbox-composer-box">
          {replyTo && (
            <div className="zaplane-inbox-replying">
              <FiCornerUpLeft />
              <span className="min-w-0 flex-1">
                <strong>
                  {sprintf(__("Replying to %s", "zaplane"), replyTo.direction === "out" ? replyTo.sender_name || __("your team", "zaplane") : conversation.contact?.name || __("the customer", "zaplane"))}
                </strong>
                <span>{replyTo.body}</span>
              </span>
              <button type="button" className="zaplane-inbox-icon-btn is-ghost" onClick={() => setReplyTo(null)} aria-label={__("Cancel reply", "zaplane")}>
                <FiX />
              </button>
            </div>
          )}
          <textarea
            ref={inputRef}
            value={text}
            onChange={(e) => {
              setText(e.target.value);
              setActiveSuggestion(0);
            }}
            onKeyDown={(e) => {
              // Saved-reply list open: arrows move, Enter/Tab picks, Esc closes.
              if (suggestions.length > 0 && !e.metaKey && !e.ctrlKey) {
                if (e.key === "ArrowDown" || e.key === "ArrowUp") {
                  e.preventDefault();
                  const step = e.key === "ArrowDown" ? 1 : -1;
                  setActiveSuggestion((highlighted + step + suggestions.length) % suggestions.length);
                  return;
                }
                if ((e.key === "Enter" && !e.shiftKey) || e.key === "Tab") {
                  e.preventDefault();
                  applyCanned(suggestions[highlighted]);
                  return;
                }
                if (e.key === "Escape") {
                  e.preventDefault();
                  setText(text.slice(1));
                  return;
                }
              }
              if (e.key === "Enter" && (e.metaKey || e.ctrlKey)) submit(e);
              if (e.key === "Escape" && replyTo) setReplyTo(null);
            }}
            rows={3}
            aria-label={isNote ? __("Private note", "zaplane") : __("Reply", "zaplane")}
            placeholder={
              isNote
                ? __("Write a note only your team can see…", "zaplane")
                : sprintf(__("Reply to %s… Type / for a saved reply.", "zaplane"), conversation.contact?.name || __("the customer", "zaplane"))
            }
          />
          <div className="zaplane-inbox-composer-foot">
            <span className="flex items-center gap-2">
              {!isNote && hasStore && (
                <button type="button" className="zaplane-inbox-small is-ghost" onClick={() => setPicking((v) => !v)} aria-expanded={picking}>
                  <FiShoppingBag />
                  {__("Product", "zaplane")}
                </button>
              )}
              <span className="zaplane-inbox-hint">
                {isNote ? __("Never sent to the customer.", "zaplane") : <kbd>{navigator.platform?.includes("Mac") ? "⌘" : "Ctrl"} ↵</kbd>}
              </span>
            </span>
            <button type="submit" className={"zaplane-inbox-send" + (isNote ? " is-note" : "")} disabled={sending || !text.trim()}>
              {!sending && (isNote ? <FiLock /> : <FiSend />)}
              {sending ? __("Sending…", "zaplane") : isNote ? __("Add note", "zaplane") : __("Send", "zaplane")}
            </button>
          </div>
        </div>
        {error && <div className="zaplane-inbox-failed">{error}</div>}
      </form>
    </section>
  );
};

export default Thread;
