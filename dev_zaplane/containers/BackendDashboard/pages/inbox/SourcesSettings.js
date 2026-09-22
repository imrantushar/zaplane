import { useEffect, useState } from "react";
import { __, _n, sprintf } from "@wordpress/i18n";
import { FiGitBranch, FiPlus, FiTrash2, FiRotateCcw, FiAlertTriangle } from "react-icons/fi";
import { API, namespace } from "@ZAPUtils/helper";
import RecipeGroupWizard from "@ZAPComponents/RecipeGroupWizard";
import RecipeFlowIcons from "../recipes/RecipeFlowIcons";

const WHO = [
  { value: "team", label: __("Team", "zaplane") },
  { value: "workflows", label: __("Workflows", "zaplane") },
  { value: "assistant", label: __("Assistant (public replies)", "zaplane") },
];

/**
 * Anything a workflow can read can reach the inbox: comments, forms,
 * tickets… A recipe tagged "inbox" sets up both directions (messages in,
 * replies out). Nothing here knows about any particular source.
 */
const SourcesSettings = ({ sources, onChange, removed, onRemove, onRestore }) => {
  const [recipes, setRecipes] = useState({ loading: true, items: [] });
  const [wizard, setWizard] = useState(null);

  useEffect(() => {
    API.get(namespace + "recipes", { params: { tag: "inbox", per_page: 50 } })
      .then((res) => setRecipes({ loading: false, items: res.data?.data || [] }))
      .catch(() => setRecipes({ loading: false, items: [] }));
  }, []);

  const setWho = (slug, value) => onChange(sources.map((s) => (s.slug === slug ? { ...s, answered_by: value } : s)));

  return (
    <>
      {sources.length > 0 && (
        <ul className="zaplane-inbox-sources">
          {sources.map((s) => {
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
                <label className="zaplane-inbox-field is-inline">
                  <span>{__("Answered by", "zaplane")}</span>
                  <select className="zaplane-inbox-select" value={s.answered_by} disabled={gone} onChange={(e) => setWho(s.slug, e.target.value)}>
                    {WHO.map((w) => (
                      <option key={w.value} value={w.value}>
                        {w.label}
                      </option>
                    ))}
                  </select>
                </label>
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
      )}

      <div className="zaplane-inbox-field-label">{__("Add a source", "zaplane")}</div>
      {recipes.loading ? (
        <p className="zaplane-inbox-hint">{__("Loading…", "zaplane")}</p>
      ) : recipes.items.length === 0 ? (
        <p className="zaplane-inbox-hint">{__("No recipes are tagged for the Inbox yet.", "zaplane")}</p>
      ) : (
        <ul className="zaplane-inbox-source-recipes">
          {recipes.items.map((r) => (
            <li key={r.id}>
              <RecipeFlowIcons icons={r.integration_icons} />
              <span className="min-w-0 flex-1">
                <strong>{r.title}</strong>
                <span className="zaplane-inbox-sub">{r.description}</span>
              </span>
              <button type="button" className="zaplane-inbox-small is-primary" onClick={() => setWizard(r)}>
                <FiPlus />
                {__("Set up", "zaplane")}
              </button>
            </li>
          ))}
        </ul>
      )}
      <p className="zaplane-inbox-hint">
        {__("Or build your own: in any workflow, use “Inbox: Add Incoming Message” to bring messages in, and start another on “Inbox: Reply to Deliver” to post replies back.", "zaplane")}{" "}
        <a href="admin.php?page=zaplane-recipes">{__("Browse all recipes", "zaplane")}</a>
      </p>

      {wizard && <RecipeGroupWizard recipe={wizard} isOpen onClose={() => setWizard(null)} />}
    </>
  );
};

export default SourcesSettings;
