import { useState } from "react";
import { __, _n, sprintf } from "@wordpress/i18n";
import { FiExternalLink, FiSearch, FiX, FiPlus, FiBookOpen, FiCheck, FiAlertCircle } from "react-icons/fi";
import { channelOf } from "./channels";
import ZAPToggle from "@ZAPComponents/ZAPToggle";
import { inboxApi } from "./api";

const knowledgeUrl = "admin.php?page=zaplane-knowledge";

const STRICTNESS = [
  { value: "strict", label: __("Strict", "zaplane"), hint: __("Only near-certain matches are answered. Fewer automatic answers, almost never wrong.", "zaplane") },
  { value: "balanced", label: __("Balanced", "zaplane"), hint: __("Clear matches are answered; close ones get a few pages to read.", "zaplane") },
  { value: "broad", label: __("Broad", "zaplane"), hint: __("Answers more often. Keep an eye on 'Didn't help' below.", "zaplane") },
];

const TIER_LABEL = {
  strong: __("Would answer", "zaplane"),
  medium: __("Would suggest pages", "zaplane"),
  none: __("Would pass on", "zaplane"),
};

const REASON = {
  small_talk: __("That looks like small talk, not a question.", "zaplane"),
  too_short: __("Too short to match reliably.", "zaplane"),
  empty_knowledge: __("This knowledge has no entries.", "zaplane"),
  no_match: __("Nothing matched closely enough.", "zaplane"),
};

const Stat = ({ value, label, tone }) => (
  <div className={"zaplane-inbox-stat" + (tone ? " is-" + tone : "")}>
    <strong>{value}</strong>
    <span>{label}</span>
  </div>
);

const MAX_COMMON = 4;
const COMMON_LENGTH = 80;

/**
 * Up to four questions customers can tap instead of typing: buttons in the
 * website chat, Ice Breakers on Messenger, conversation starters on WhatsApp.
 */
const CommonQuestions = ({ questions, onChange, faqs, widgetOn, channels, formChannels, status }) => {
  const list = questions || [];
  const set = (i, v) => onChange(list.map((q, j) => (j === i ? v.slice(0, COMMON_LENGTH) : q)));
  const remove = (i) => onChange(list.filter((_, j) => j !== i));
  const unused = (faqs || []).filter((f) => !list.includes(f) && f.length <= COMMON_LENGTH);

  const where = [
    { slug: "web", label: __("Website chat", "zaplane"), on: widgetOn, state: widgetOn ? "ok" : "off" },
    ...Object.entries(channels || {}).map(([slug, ch]) => {
      const on = !!formChannels?.[slug]?.enabled;
      const st = status?.[slug];
      return { slug, label: ch.label, on, state: !on ? "off" : !st ? "pending" : st.ok ? "ok" : "error", error: st?.error };
    }),
  ];

  return (
    <div className="zaplane-inbox-common">
      <div className="zaplane-inbox-gaps-head">
        <span>
          <span className="zaplane-inbox-field-label">{__("Common questions", "zaplane")}</span>
          <em className="zaplane-inbox-hint">
            {" "}
            {__("Shown before the customer types. Tapping one asks it, so a matching FAQ answers instantly.", "zaplane")}
          </em>
        </span>
        {list.length < MAX_COMMON && unused.length > 0 && (
          <select
            className="zaplane-inbox-select zaplane-inbox-pick"
            value=""
            onChange={(e) => e.target.value && onChange([...list, e.target.value])}
            aria-label={__("Add a question from your FAQs", "zaplane")}
          >
            <option value="">{__("+ Add from FAQs", "zaplane")}</option>
            {unused.map((f) => (
              <option key={f} value={f}>
                {f}
              </option>
            ))}
          </select>
        )}
      </div>

      <ol className="zaplane-inbox-common-list">
        {list.map((q, i) => (
          <li key={i}>
            <span className="zaplane-inbox-common-n">{i + 1}</span>
            <input className="zaplane-inbox-input" value={q} onChange={(e) => set(i, e.target.value)} maxLength={COMMON_LENGTH} />
            <span className="zaplane-inbox-hint">{COMMON_LENGTH - q.length}</span>
            <button type="button" className="zaplane-inbox-icon-btn is-ghost" onClick={() => remove(i)} aria-label={__("Remove", "zaplane")} title={__("Remove", "zaplane")}>
              <FiX />
            </button>
          </li>
        ))}
        {list.length < MAX_COMMON && (
          <li>
            <span className="zaplane-inbox-common-n">{list.length + 1}</span>
            <button type="button" className="zaplane-inbox-small is-ghost" onClick={() => onChange([...list, ""])}>
              <FiPlus />
              {__("Write your own", "zaplane")}
            </button>
          </li>
        )}
      </ol>

      {list.filter((q) => q.trim()).length > 0 && (
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
                  {w.state === "off"
                    ? __("not on", "zaplane")
                    : w.state === "pending"
                      ? __("updates when you save", "zaplane")
                      : w.state === "error"
                        ? __("couldn't update", "zaplane")
                        : ""}
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
        {__("Messenger and WhatsApp show them to people starting a new chat with you, in their apps.", "zaplane")}
      </em>
    </div>
  );
};

/** Business Knowledge: what the Inbox answers from, and whether it answers on its own. */
const KnowledgeSettings = ({ form, setAi, setAnswers, knowledgeKeys, answersData, aiOn, channels }) => {
  const [question, setQuestion] = useState("");
  const [result, setResult] = useState(null);
  const [testing, setTesting] = useState(false);
  const [gaps, setGaps] = useState(answersData?.gaps || []);

  const answers = form.answers || { enabled: false, strictness: "balanced", feedback: true };
  const selected = knowledgeKeys.find((k) => k.key === form.ai.business_key);
  const stats = answersData?.stats || {};
  const rated = (stats.helpful || 0) + (stats.not_helpful || 0);

  const test = async (e) => {
    e.preventDefault();
    if (!question.trim()) return;
    setTesting(true);
    try {
      setResult(await inboxApi.testKnowledge(question));
    } catch (err) {
      setResult({ error: err?.response?.data?.message || __("Couldn't test that question.", "zaplane") });
    } finally {
      setTesting(false);
    }
  };

  return (
    <section className="zaplane-inbox-card" id="zaplane-inbox-knowledge" data-section="knowledge">
      <div className="zaplane-inbox-card-head">
        <div>
          <h3>{__("Knowledge & automatic answers", "zaplane")}</h3>
          <p>
            {__("When a customer asks something your Business Knowledge already answers, send that answer straight away: an FAQ word for word, or the page it came from. No AI needed, so it's instant and free. Anything else goes to the assistant or your team.", "zaplane")}
          </p>
        </div>
        <ZAPToggle checked={!!answers.enabled} onChange={(v) => setAnswers("enabled", v)} label={__("Answer from knowledge first", "zaplane")} />
      </div>

      <div className="zaplane-inbox-grid">
        <label className="zaplane-inbox-field">
          <span>{__("Knowledge", "zaplane")}</span>
          <select className="zaplane-inbox-select" value={form.ai.business_key} onChange={(e) => setAi("business_key", e.target.value)}>
            {!selected && <option value={form.ai.business_key}>{sprintf(__("%s (no entries)", "zaplane"), form.ai.business_key || "default")}</option>}
            {knowledgeKeys.map((k) => (
              <option key={k.key} value={k.key}>
                {k.key} ({k.count})
              </option>
            ))}
          </select>
          <em className="zaplane-inbox-hint">
            {knowledgeKeys.length === 0 ? (
              <>
                {__("Business Knowledge is empty, so there's nothing to answer from.", "zaplane")}{" "}
                <a href={knowledgeUrl}>
                  {__("Add entries", "zaplane")} <FiExternalLink />
                </a>
              </>
            ) : !selected ? (
              <>
                {sprintf(__("“%s” has no entries yet.", "zaplane"), form.ai.business_key)}{" "}
                <a href={knowledgeUrl}>
                  {__("Add some", "zaplane")} <FiExternalLink />
                </a>
              </>
            ) : aiOn ? (
              __("Used by automatic answers and by the AI assistant.", "zaplane")
            ) : (
              sprintf(_n("%d entry to answer from.", "%d entries to answer from.", selected.count, "zaplane"), selected.count)
            )}
          </em>
        </label>

        <div className="zaplane-inbox-field">
          <span>{__("How sure before answering", "zaplane")}</span>
          <span className="zaplane-inbox-segment" role="radiogroup">
            {STRICTNESS.map((s) => (
              <button
                key={s.value}
                type="button"
                role="radio"
                aria-checked={answers.strictness === s.value}
                className={answers.strictness === s.value ? "is-active" : ""}
                onClick={() => setAnswers("strictness", s.value)}
              >
                {s.label}
              </button>
            ))}
          </span>
          <em className="zaplane-inbox-hint">{(STRICTNESS.find((s) => s.value === answers.strictness) || STRICTNESS[1]).hint}</em>
        </div>
      </div>

      <div className="zaplane-inbox-callout">
        {answersData?.embeddings
          ? __("Matching by meaning (semantic search is on), so “can I get my money back?” finds your refund FAQ.", "zaplane")
          : (
            <>
              {__("Matching by words only: FAQs are answered when the question closely matches, and pages are suggested. Turn on semantic search in Business Knowledge to match by meaning.", "zaplane")}{" "}
              <a href={knowledgeUrl}>
                {__("Business Knowledge", "zaplane")} <FiExternalLink />
              </a>
            </>
          )}
      </div>

      <div className="zaplane-inbox-field is-row">
        <span>
          {__("Ask “Did this answer your question?”", "zaplane")}
          <em className="zaplane-inbox-hint">
            {__("Customers get Yes / No buttons. “No” hands the question to the assistant or your team, and adds it to the list below.", "zaplane")}
          </em>
        </span>
        <ZAPToggle checked={!!answers.feedback} onChange={(v) => setAnswers("feedback", v)} size="sm" />
      </div>

      <CommonQuestions
        questions={answers.common_questions}
        onChange={(v) => setAnswers("common_questions", v)}
        faqs={answersData?.faqs}
        widgetOn={!!form.widget?.enabled}
        channels={channels}
        formChannels={form.channels}
        status={answersData?.common}
      />

      <div className="zaplane-inbox-stats" aria-label={__("This week", "zaplane")}>
        <Stat value={stats.answered || 0} label={__("Answered this week", "zaplane")} tone="success" />
        <Stat value={stats.suggested || 0} label={__("Pages suggested", "zaplane")} />
        <Stat value={rated ? Math.round(((stats.helpful || 0) / rated) * 100) + "%" : "—"} label={__("Found it helpful", "zaplane")} />
        <Stat value={(stats.unmatched || 0) + (stats.not_helpful || 0)} label={__("Couldn't answer", "zaplane")} tone={stats.unmatched || stats.not_helpful ? "warning" : ""} />
      </div>

      <form className="zaplane-inbox-kbtest" onSubmit={test}>
        <span className="zaplane-inbox-field-label">{__("Test a question", "zaplane")}</span>
        <div className="zaplane-inbox-copy">
          <input
            className="zaplane-inbox-input"
            value={question}
            onChange={(e) => setQuestion(e.target.value)}
            placeholder={__("e.g. Can I return a dress after a week?", "zaplane")}
          />
          <button type="submit" className="zaplane-inbox-small" disabled={testing || !question.trim()}>
            <FiSearch />
            {testing ? __("Checking…", "zaplane") : __("Test", "zaplane")}
          </button>
        </div>
        {result && (
          <div className={"zaplane-inbox-kbresult is-" + (result.tier || "none")}>
            {result.error ? (
              <span>{result.error}</span>
            ) : (
              <>
                <div className="zaplane-inbox-kbresult-head">
                  <strong>{TIER_LABEL[result.tier]}</strong>
                  {result.tier !== "none" && (
                    <span className="zaplane-inbox-hint">
                      {sprintf(__("%1$s match · %2$d%% sure", "zaplane"), result.method === "semantic" ? __("Meaning", "zaplane") : __("Word", "zaplane"), Math.round((result.confidence || 0) * 100))}
                    </span>
                  )}
                </div>
                {result.tier === "none" ? (
                  <span className="zaplane-inbox-hint">
                    {REASON[result.reason] || ""} {aiOn ? __("The assistant would answer.", "zaplane") : __("Your team would answer.", "zaplane")}
                  </span>
                ) : (
                  <>
                    <div className="zaplane-inbox-kbreply">{result.reply?.body}</div>
                    {(result.reply?.attachments || []).map((a, i) => (
                      <a key={i} className="zaplane-inbox-article" href={a.url} target="_blank" rel="noopener noreferrer">
                        <FiBookOpen />
                        <span>
                          <strong>{a.title}</strong>
                          <em>{a.excerpt}</em>
                        </span>
                      </a>
                    ))}
                  </>
                )}
              </>
            )}
          </div>
        )}
      </form>

      <div className="zaplane-inbox-gaps">
        <div className="zaplane-inbox-gaps-head">
          <span className="zaplane-inbox-field-label">{__("Questions we couldn't answer", "zaplane")}</span>
          <a href={knowledgeUrl} className="zaplane-inbox-small">
            <FiPlus />
            {__("Add FAQs", "zaplane")}
          </a>
        </div>
        {gaps.length === 0 ? (
          <p className="zaplane-inbox-hint">{__("None yet. Questions nothing answered, and answers customers said didn't help, show up here so you can add the missing FAQ.", "zaplane")}</p>
        ) : (
          <ul>
            {gaps.map((g) => (
              <li key={g.key}>
                <span className="min-w-0 flex-1">{g.question}</span>
                {g.count > 1 && <span className="zaplane-inbox-count">{sprintf(__("%d×", "zaplane"), g.count)}</span>}
                <button
                  type="button"
                  className="zaplane-inbox-icon-btn is-ghost"
                  title={__("Dismiss", "zaplane")}
                  aria-label={__("Dismiss", "zaplane")}
                  onClick={async () => setGaps((await inboxApi.dismissGap(g.key)).slice(0, 20))}
                >
                  <FiX />
                </button>
              </li>
            ))}
          </ul>
        )}
      </div>
    </section>
  );
};

export default KnowledgeSettings;
