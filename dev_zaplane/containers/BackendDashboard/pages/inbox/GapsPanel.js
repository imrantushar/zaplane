import { useState } from "react";
import { __, _n, sprintf } from "@wordpress/i18n";
import { FiX, FiPlus, FiSearch, FiThumbsDown, FiMessageSquare, FiEdit3, FiCheck } from "react-icons/fi";
import { inboxApi, shortTime } from "./api";

const knowledgeUrl = "admin.php?page=zaplane-knowledge";

/** Inline editor that turns one gap into an FAQ. */
const GapForm = ({ gap, faqs, menu, onDone, onCancel }) => {
  // An answer is on record (it didn't help) or matches now: improve that FAQ.
  const improving = !!gap.answer;
  const [mode, setMode] = useState(improving ? "improve" : "new");
  const [question, setQuestion] = useState(gap.question);
  const [answer, setAnswer] = useState(improving ? gap.answer.content : "");
  const [addToMenu, setAddToMenu] = useState(!improving);
  const grouped = !!menu?.grouped && (menu?.categories || []).length > 0;
  const [category, setCategory] = useState(grouped ? menu.categories[0].id : "");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");

  const save = async (e) => {
    e.preventDefault();
    setBusy(true);
    setError("");
    try {
      const res = await inboxApi.resolveGap(gap.key, {
        question: mode === "improve" ? gap.answer.title : question,
        answer,
        knowledge_id: mode === "improve" ? gap.answer.id : 0,
      });
      onDone(res, addToMenu && mode === "new" ? category || null : false);
    } catch (err) {
      setError(err?.response?.data?.message || __("Couldn't save that.", "zaplane"));
      setBusy(false);
    }
  };

  return (
    <form className="zaplane-inbox-gapform" onSubmit={save}>
      {improving && (
        <span className="zaplane-inbox-segment" role="radiogroup">
          <button type="button" role="radio" aria-checked={mode === "improve"} className={mode === "improve" ? "is-active" : ""} onClick={() => { setMode("improve"); setAnswer(gap.answer.content); }}>
            {__("Improve the answer we sent", "zaplane")}
          </button>
          <button type="button" role="radio" aria-checked={mode === "new"} className={mode === "new" ? "is-active" : ""} onClick={() => { setMode("new"); setAnswer(""); }}>
            {__("Add as a new FAQ", "zaplane")}
          </button>
        </span>
      )}

      {mode === "improve" ? (
        <p className="zaplane-inbox-hint">
          {sprintf(__("Editing the FAQ “%s”. Every customer who gets it will see the new answer.", "zaplane"), gap.answer.title)}
        </p>
      ) : (
        <div className="zaplane-inbox-grid">
          <label className="zaplane-inbox-field">
            <span>{__("Question", "zaplane")}</span>
            <input className="zaplane-inbox-input" value={question} onChange={(e) => setQuestion(e.target.value)} maxLength={200} />
          </label>
          <label className="zaplane-inbox-field">
            <span>{__("Start from an FAQ (optional)", "zaplane")}</span>
            <select className="zaplane-inbox-select" value="" onChange={(e) => { const f = faqs.find((x) => x.id === parseInt(e.target.value, 10)); if (f) setAnswer(f.answer); }}>
              <option value="">{__("— Copy an answer —", "zaplane")}</option>
              {faqs.map((f) => (
                <option key={f.id} value={f.id}>
                  {f.title}
                </option>
              ))}
            </select>
          </label>
        </div>
      )}

      <label className="zaplane-inbox-field">
        <span>
          <FiEdit3 /> {__("Answer", "zaplane")}
        </span>
        <textarea className="zaplane-inbox-input" rows={3} value={answer} onChange={(e) => setAnswer(e.target.value)} placeholder={__("What should customers get when they ask this?", "zaplane")} autoFocus />
      </label>

      {mode === "new" && (
        <div className="zaplane-inbox-gapform-menu">
          <label className="zaplane-inbox-check">
            <input type="checkbox" checked={addToMenu} onChange={(e) => setAddToMenu(e.target.checked)} />
            {__("Also add it to the Quick answers menu", "zaplane")}
          </label>
          {addToMenu && grouped && (
            <select className="zaplane-inbox-select" value={category} onChange={(e) => setCategory(e.target.value)} aria-label={__("Category", "zaplane")}>
              {menu.categories.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.title || __("(untitled)", "zaplane")}
                </option>
              ))}
            </select>
          )}
        </div>
      )}

      {error && <div className="zaplane-inbox-failed">{error}</div>}
      <div className="zaplane-inbox-edit-foot">
        <button type="button" className="zaplane-inbox-small" onClick={onCancel} disabled={busy}>
          {__("Cancel", "zaplane")}
        </button>
        <button type="submit" className="zaplane-inbox-small is-primary" disabled={busy || !answer.trim() || (mode === "new" && !question.trim())}>
          <FiCheck />
          {busy ? __("Saving…", "zaplane") : mode === "improve" ? __("Update answer", "zaplane") : __("Save as FAQ", "zaplane")}
        </button>
      </div>
    </form>
  );
};

/**
 * Questions automatic answers couldn't settle, grouped by meaning, each
 * one fixable in place: answer it (a new FAQ) or improve the answer that
 * didn't help.
 */
const GapsPanel = ({ gaps, setGaps, faqs, onFaqs, menu, onAddToMenu }) => {
  const [open, setOpen] = useState("");
  const [done, setDone] = useState("");

  const finished = (gap) => (res, menuTarget) => {
    setGaps(res.gaps || []);
    if (res.faqs) onFaqs(res.faqs);
    let note = sprintf(__("Saved “%s” to Business Knowledge.", "zaplane"), res.faq.title);
    if (menuTarget !== false) {
      onAddToMenu({ question: res.faq.title.slice(0, 80), knowledge_id: res.faq.id, answer: res.faq.answer }, menuTarget);
      note += " " + __("Added to the menu. Save settings to show it.", "zaplane");
    }
    setDone(note);
    setOpen("");
  };

  return (
    <div className="zaplane-inbox-gaps">
      <div className="zaplane-inbox-gaps-head">
        <span>
          <span className="zaplane-inbox-field-label">{__("Questions we couldn't answer", "zaplane")}</span>
          {gaps.length > 0 && <span className="zaplane-inbox-count">{gaps.length}</span>}
        </span>
        <a href={knowledgeUrl} className="zaplane-inbox-small">
          <FiPlus />
          {__("Business Knowledge", "zaplane")}
        </a>
      </div>

      {done && (
        <p className="zaplane-inbox-success" role="status">
          <FiCheck /> {done}
        </p>
      )}

      {gaps.length === 0 ? (
        <p className="zaplane-inbox-hint">{__("None right now. Questions nothing answered, and answers customers said didn't help, show up here so you can fix them in one step.", "zaplane")}</p>
      ) : (
        <ul>
          {gaps.map((g) => {
            const unhelpful = g.reason === "unhelpful";
            const hasFaq = !!g.answer;
            const others = (g.variants || []).filter((v) => v !== g.question);
            return (
              <li key={g.key} className={"zaplane-inbox-gap" + (open === g.key ? " is-open" : "")}>
                <div className="zaplane-inbox-gap-row">
                  <span className={"zaplane-inbox-gap-icon is-" + g.reason}>{unhelpful ? <FiThumbsDown /> : <FiSearch />}</span>
                  <div className="zaplane-inbox-gap-text">
                    <strong>{g.question}</strong>
                    <span className="zaplane-inbox-hint">
                      {unhelpful
                        ? g.answer
                          ? sprintf(__("Answer didn't help: “%s”", "zaplane"), g.answer.title)
                          : __("Answer didn't help", "zaplane")
                        : g.matched_now
                          ? sprintf(__("Now matches the FAQ “%s”. Check it answers the question.", "zaplane"), g.answer.title)
                          : __("No answer found", "zaplane")}
                      {" · "}
                      {sprintf(_n("asked %d time", "asked %d times", g.count, "zaplane"), g.count)}
                      {g.last_at ? " · " + shortTime(new Date(g.last_at * 1000).toISOString()) : ""}
                      {g.conversation_id > 0 && (
                        <>
                          {" · "}
                          <a href={"admin.php?page=zaplane-inbox&conversation=" + g.conversation_id}>
                            <FiMessageSquare /> {__("View chat", "zaplane")}
                          </a>
                        </>
                      )}
                    </span>
                    {others.length > 0 && (
                      <span className="zaplane-inbox-gap-variants">
                        {__("Also asked as:", "zaplane")} {others.map((v) => "“" + v + "”").join(", ")}
                      </span>
                    )}
                  </div>
                  {open !== g.key && (
                    <button type="button" className="zaplane-inbox-small is-primary" onClick={() => { setOpen(g.key); setDone(""); }}>
                      {hasFaq ? __("Improve answer", "zaplane") : __("Answer it", "zaplane")}
                    </button>
                  )}
                  <button
                    type="button"
                    className="zaplane-inbox-icon-btn is-ghost"
                    title={__("Dismiss", "zaplane")}
                    aria-label={__("Dismiss", "zaplane")}
                    onClick={async () => setGaps((await inboxApi.dismissGap(g.key)).slice(0, 20))}
                  >
                    <FiX />
                  </button>
                </div>
                {open === g.key && <GapForm gap={g} faqs={faqs} menu={menu} onDone={finished(g)} onCancel={() => setOpen("")} />}
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );
};

export default GapsPanel;
