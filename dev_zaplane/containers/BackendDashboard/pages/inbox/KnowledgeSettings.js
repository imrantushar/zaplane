import { useState } from "react";
import { __, _n, sprintf } from "@wordpress/i18n";
import { FiExternalLink, FiSearch, FiBookOpen } from "react-icons/fi";
import MenuEditor from "./MenuEditor";
import GapsPanel from "./GapsPanel";
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

/** Business Knowledge: what the Inbox answers from, and whether it answers on its own. */
const KnowledgeSettings = ({ form, setAi, setAnswers, knowledgeKeys, answersData, aiOn, channels, onAnswersData }) => {
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

      <MenuEditor
        menu={answers.menu}
        onChange={(v) => setAnswers("menu", v)}
        faqs={answersData?.faqs || []}
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

      <GapsPanel
        gaps={gaps}
        setGaps={setGaps}
        faqs={answersData?.faqs || []}
        onFaqs={(faqs) => onAnswersData?.({ faqs })}
        menu={answers.menu}
        onAddToMenu={(entry, categoryId) => {
          const menu = answers.menu || { grouped: false, categories: [], questions: [] };
          const q = { id: "q_" + Math.random().toString(36).slice(2, 10), ...entry };
          if (categoryId && menu.grouped) {
            setAnswers("menu", { ...menu, categories: menu.categories.map((c) => (c.id === categoryId ? { ...c, questions: [...c.questions, q] } : c)) });
          } else {
            setAnswers("menu", { ...menu, questions: [...(menu.questions || []), q] });
          }
        }}
      />
    </section>
  );
};

export default KnowledgeSettings;
