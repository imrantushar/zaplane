import { useState } from "react";
import { __, _n, sprintf } from "@wordpress/i18n";
import { FiX, FiPlus, FiChevronUp, FiChevronDown, FiCheck, FiAlertCircle, FiAlertTriangle, FiEdit3, FiFolder } from "react-icons/fi";
import ZAPToggle from "@ZAPComponents/ZAPToggle";
import { channelOf } from "./channels";

const MAX_CATEGORIES = 10;
const MAX_PER_CATEGORY = 8;
const MAX_FLAT = 10;
const CATEGORY_LENGTH = 24;
const QUESTION_LENGTH = 80;

const uid = (p) => p + "_" + Math.random().toString(36).slice(2, 10);
const move = (list, i, d) => {
  const j = i + d;
  if (j < 0 || j >= list.length) return list;
  const next = list.slice();
  [next[i], next[j]] = [next[j], next[i]];
  return next;
};

/** One question and its answer: linked to an FAQ, or written here. */
const QuestionRow = ({ q, index, count, faqs, onChange, onMove, onRemove }) => {
  const [open, setOpen] = useState(!q.question || q.missing);
  const linked = faqs.find((f) => f.id === q.knowledge_id);
  const hasAnswer = !!(q.answer && q.answer.trim());

  return (
    <li className={"zaplane-inbox-mq" + (open ? " is-open" : "")}>
      <div className="zaplane-inbox-mq-head">
        <span className="zaplane-inbox-common-n">{index + 1}</span>
        <input
          className="zaplane-inbox-input"
          value={q.question}
          maxLength={QUESTION_LENGTH}
          placeholder={__("A question customers ask", "zaplane")}
          onChange={(e) => onChange({ ...q, question: e.target.value })}
        />
        <button
          type="button"
          className={"zaplane-inbox-mq-answer" + (hasAnswer ? " is-ok" : " is-missing")}
          onClick={() => setOpen((o) => !o)}
          aria-expanded={open}
          title={hasAnswer ? __("Edit the answer", "zaplane") : __("This question has no answer yet", "zaplane")}
        >
          {hasAnswer ? <FiCheck /> : <FiAlertTriangle />}
          {hasAnswer ? __("Answer", "zaplane") : __("No answer", "zaplane")}
        </button>
        <span className="zaplane-inbox-mq-tools">
          <button type="button" className="zaplane-inbox-icon-btn is-ghost" disabled={index === 0} onClick={() => onMove(-1)} aria-label={__("Move up", "zaplane")}>
            <FiChevronUp />
          </button>
          <button type="button" className="zaplane-inbox-icon-btn is-ghost" disabled={index === count - 1} onClick={() => onMove(1)} aria-label={__("Move down", "zaplane")}>
            <FiChevronDown />
          </button>
          <button type="button" className="zaplane-inbox-icon-btn is-ghost is-danger" onClick={onRemove} aria-label={__("Remove", "zaplane")}>
            <FiX />
          </button>
        </span>
      </div>

      {open && (
        <div className="zaplane-inbox-mq-body">
          <label className="zaplane-inbox-field">
            <span>{__("Answer from an FAQ", "zaplane")}</span>
            <select
              className="zaplane-inbox-select"
              value={linked ? linked.id : 0}
              onChange={(e) => {
                const f = faqs.find((x) => x.id === parseInt(e.target.value, 10));
                onChange(f ? { ...q, knowledge_id: f.id, answer: f.answer, question: q.question || f.title.slice(0, QUESTION_LENGTH) } : { ...q, knowledge_id: 0 });
              }}
            >
              <option value={0}>{q.knowledge_id && !linked ? __("Answer written here", "zaplane") : __("— Write the answer below —", "zaplane")}</option>
              {faqs.map((f) => (
                <option key={f.id} value={f.id}>
                  {f.title}
                </option>
              ))}
            </select>
          </label>
          <label className="zaplane-inbox-field">
            <span>
              <FiEdit3 /> {__("Answer", "zaplane")}
            </span>
            <textarea
              className="zaplane-inbox-input"
              rows={3}
              value={q.answer || ""}
              placeholder={__("What customers get when they tap this question.", "zaplane")}
              onChange={(e) => onChange({ ...q, answer: e.target.value })}
            />
            <em className="zaplane-inbox-hint">
              {linked
                ? __("Saved to this FAQ in Business Knowledge, so the AI assistant uses it too.", "zaplane")
                : __("Saved as a new FAQ in Business Knowledge when you save.", "zaplane")}
            </em>
          </label>
        </div>
      )}
    </li>
  );
};

const QuestionList = ({ questions, max, faqs, onChange }) => (
  <ol className="zaplane-inbox-mq-list">
    {questions.map((q, i) => (
      <QuestionRow
        key={q.id}
        q={q}
        index={i}
        count={questions.length}
        faqs={faqs}
        onChange={(next) => onChange(questions.map((x) => (x.id === q.id ? next : x)))}
        onMove={(d) => onChange(move(questions, i, d))}
        onRemove={() => onChange(questions.filter((x) => x.id !== q.id))}
      />
    ))}
    {questions.length < max && (
      <li>
        <button type="button" className="zaplane-inbox-small is-ghost" onClick={() => onChange([...questions, { id: uid("q"), question: "", knowledge_id: 0, answer: "" }])}>
          <FiPlus />
          {__("Add a question", "zaplane")}
        </button>
      </li>
    )}
  </ol>
);

/**
 * The quick-answers menu: questions customers tap instead of typing, each
 * with its own answer, optionally grouped into categories.
 */
const MenuEditor = ({ menu, onChange, faqs, widgetOn, channels, formChannels, status }) => {
  const m = menu || { grouped: false, categories: [], questions: [] };
  const set = (patch) => onChange({ ...m, ...patch });
  const setCategory = (id, patch) => set({ categories: m.categories.map((c) => (c.id === id ? { ...c, ...patch } : c)) });

  const all = m.grouped ? m.categories.flatMap((c) => c.questions) : m.questions;
  const missing = all.filter((q) => q.question.trim() && !(q.answer || "").trim()).length;
  const topCount = m.grouped ? m.categories.length : m.questions.length;

  const where = [
    { slug: "web", label: __("Website chat", "zaplane"), state: widgetOn ? "ok" : "off" },
    ...Object.entries(channels || {}).map(([slug, ch]) => {
      const st = status?.[slug];
      return { slug, label: ch.label, state: !formChannels?.[slug]?.enabled ? "off" : !st ? "pending" : st.ok ? "ok" : "error", error: st?.error };
    }),
  ];

  return (
    <div className="zaplane-inbox-common">
      <div className="zaplane-inbox-gaps-head">
        <span>
          <span className="zaplane-inbox-field-label">{__("Quick answers menu", "zaplane")}</span>
          <em className="zaplane-inbox-hint">
            {" "}
            {__("Questions customers tap instead of typing. Each one sends its own answer.", "zaplane")}
          </em>
        </span>
        <ZAPToggle checked={!!m.grouped} onChange={(v) => set({ grouped: v })} label={__("Group into categories", "zaplane")} size="sm" />
      </div>

      {missing > 0 && (
        <div className="zaplane-inbox-callout is-warning">
          <FiAlertTriangle />{" "}
          {sprintf(_n("%d question has no answer. Tapping it goes to automatic matching, then your team.", "%d questions have no answer. Tapping them goes to automatic matching, then your team.", missing, "zaplane"), missing)}
        </div>
      )}

      {m.grouped ? (
        <div className="zaplane-inbox-mcats">
          {m.categories.map((c, i) => (
            <div key={c.id} className="zaplane-inbox-mcat">
              <div className="zaplane-inbox-mcat-head">
                <FiFolder />
                <input
                  className="zaplane-inbox-input"
                  value={c.title}
                  maxLength={CATEGORY_LENGTH}
                  placeholder={__("Category, e.g. 🚚 Delivery", "zaplane")}
                  onChange={(e) => setCategory(c.id, { title: e.target.value })}
                />
                <span className="zaplane-inbox-hint">{sprintf(__("%1$d/%2$d questions", "zaplane"), c.questions.length, MAX_PER_CATEGORY)}</span>
                <span className="zaplane-inbox-mq-tools">
                  <button type="button" className="zaplane-inbox-icon-btn is-ghost" disabled={i === 0} onClick={() => set({ categories: move(m.categories, i, -1) })} aria-label={__("Move up", "zaplane")}>
                    <FiChevronUp />
                  </button>
                  <button type="button" className="zaplane-inbox-icon-btn is-ghost" disabled={i === m.categories.length - 1} onClick={() => set({ categories: move(m.categories, i, 1) })} aria-label={__("Move down", "zaplane")}>
                    <FiChevronDown />
                  </button>
                  <button type="button" className="zaplane-inbox-icon-btn is-ghost is-danger" onClick={() => set({ categories: m.categories.filter((x) => x.id !== c.id) })} aria-label={__("Remove category", "zaplane")}>
                    <FiX />
                  </button>
                </span>
              </div>
              <QuestionList questions={c.questions} max={MAX_PER_CATEGORY} faqs={faqs} onChange={(questions) => setCategory(c.id, { questions })} />
            </div>
          ))}
          {m.categories.length < MAX_CATEGORIES && (
            <button
              type="button"
              className="zaplane-inbox-small"
              onClick={() =>
                set({
                  categories: [
                    ...m.categories,
                    // The first category takes the flat questions, so nothing is lost.
                    { id: uid("c"), title: "", questions: m.categories.length === 0 ? m.questions : [] },
                  ],
                })
              }
            >
              <FiPlus />
              {__("Add a category", "zaplane")}
            </button>
          )}
        </div>
      ) : (
        <QuestionList questions={m.questions} max={MAX_FLAT} faqs={faqs} onChange={(questions) => set({ questions })} />
      )}

      {topCount > 0 && (
        <div className="zaplane-inbox-where">
          {where.map((w) => {
            const { Icon, color } = channelOf(w.slug);
            return (
              <span key={w.slug} className={"zaplane-inbox-where-item is-" + w.state} title={w.error || ""}>
                <Icon style={{ color }} />
                {w.label}
                {w.state === "ok" && <FiCheck />}
                {w.state === "error" && <FiAlertCircle />}
                <em>
                  {w.state === "off" ? __("not on", "zaplane") : w.state === "pending" ? __("updates when you save", "zaplane") : w.state === "error" ? __("couldn't update", "zaplane") : ""}
                </em>
              </span>
            );
          })}
        </div>
      )}
      {where.some((w) => w.state === "error") && (
        <div className="zaplane-inbox-callout is-warning">
          {where
            .filter((w) => w.state === "error")
            .map((w) => w.label + ": " + w.error)
            .join(" · ")}
        </div>
      )}
      <em className="zaplane-inbox-hint">
        {m.grouped
          ? __("Customers pick a category, then a question. Messenger and WhatsApp show the first 4 categories to people starting a new chat.", "zaplane")
          : __("Messenger and WhatsApp show the first 4 questions to people starting a new chat.", "zaplane")}
      </em>
    </div>
  );
};

export default MenuEditor;
