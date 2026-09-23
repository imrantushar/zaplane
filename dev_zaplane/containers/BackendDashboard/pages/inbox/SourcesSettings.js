import { __, _n, sprintf } from "@wordpress/i18n";
import { FiGitBranch, FiTrash2, FiRotateCcw, FiAlertTriangle } from "react-icons/fi";
import ConnectorCard from "./ConnectorCard";

const WHO = [
  { value: "team", label: __("Team", "zaplane") },
  { value: "workflows", label: __("Workflows", "zaplane") },
  { value: "assistant", label: __("Assistant (public replies)", "zaplane") },
];

/** "Answered by" for one source. */
const Who = ({ source, disabled, onChange }) => (
  <label className="zaplane-inbox-field is-inline">
    <span>{__("Answered by", "zaplane")}</span>
    <select className="zaplane-inbox-select" value={source.answered_by} disabled={disabled} onChange={(e) => onChange(e.target.value)}>
      {WHO.map((w) => (
        <option key={w.value} value={w.value}>
          {w.label}
        </option>
      ))}
    </select>
  </label>
);

/**
 * Anything a workflow can read can reach the inbox: comments, forms,
 * tickets… A recipe tagged "inbox" sets up both directions (messages in,
 * replies out). Nothing here knows about any particular source.
 */
const SourcesSettings = ({ connectors, onConnectorsChanged, sources, onChange, removed, onRemove, onRestore }) => {
  const setWho = (slug, value) => onChange(sources.map((s) => (s.slug === slug ? { ...s, answered_by: value } : s)));
  const bySlug = Object.fromEntries(sources.map((s) => [s.slug, s]));
  const covered = new Set(connectors.map((c) => c.target));
  const others = sources.filter((s) => !covered.has(s.slug));

  return (
    <>
      <div className="zaplane-inbox-channels">
        {connectors.map((c) => (
          <ConnectorCard key={c.id} connector={c} onChanged={onConnectorsChanged}>
            {bySlug[c.target] && (
              <div className="zaplane-inbox-source-routing">
                <Who source={bySlug[c.target]} onChange={(v) => setWho(c.target, v)} />
                <span className="zaplane-inbox-hint">
                  {sprintf(_n("%d conversation", "%d conversations", bySlug[c.target].conversations, "zaplane"), bySlug[c.target].conversations)}
                  {bySlug[c.target].answered_by === "assistant" && " · " + __("The assistant's replies are public here.", "zaplane")}
                </span>
              </div>
            )}
          </ConnectorCard>
        ))}
      </div>

      {others.length > 0 && (
        <>
          <div className="zaplane-inbox-field-label">{__("Your own sources", "zaplane")}</div>
          <ul className="zaplane-inbox-sources">
            {others.map((s) => {
              const gone = removed.includes(s.slug);
              return (
                <li key={s.slug} className={gone ? "is-removed" : ""}>
                  <span className="zaplane-inbox-source-icon">
                    <FiGitBranch />
                  </span>
                  <span className="min-w-0 flex-1">
                    <strong>{s.label}</strong>
                    <span className="zaplane-inbox-sub">
                      <code>{s.slug}</code> · {sprintf(_n("%d conversation", "%d conversations", s.conversations, "zaplane"), s.conversations)}
                    </span>
                    {!s.delivers && (
                      <span className="zaplane-inbox-hint is-warning">
                        <FiAlertTriangle /> {__("No active workflow posts replies back. Turn on its “Reply to Deliver” workflow, or replies will fail.", "zaplane")}
                      </span>
                    )}
                  </span>
                  <Who source={s} disabled={gone} onChange={(v) => setWho(s.slug, v)} />
                  {gone ? (
                    <button type="button" className="zaplane-inbox-small" onClick={() => onRestore(s.slug)}>
                      <FiRotateCcw />
                      {__("Keep", "zaplane")}
                    </button>
                  ) : (
                    <button
                      type="button"
                      className="zaplane-inbox-icon-btn is-ghost"
                      title={__("Remove from the list (conversations stay; it comes back if its workflow sends again)", "zaplane")}
                      aria-label={sprintf(__("Remove %s", "zaplane"), s.label)}
                      onClick={() => onRemove(s.slug)}
                    >
                      <FiTrash2 />
                    </button>
                  )}
                </li>
              );
            })}
          </ul>
        </>
      )}

      <p className="zaplane-inbox-hint">
        {__("Or build your own: in any workflow, use “Inbox: Add Incoming Message” to bring messages in, and start another on “Inbox: Reply to Deliver” to post replies back.", "zaplane")}{" "}
        <a href="admin.php?page=zaplane-recipes">{__("Browse all recipes", "zaplane")}</a>
      </p>
    </>
  );
};

export default SourcesSettings;
