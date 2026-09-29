import { __, _n, sprintf } from "@wordpress/i18n";
import { FiCheck } from "react-icons/fi";

/**
 * The setup's left side: its steps, which can be revisited, and a running count
 * of what the answers so far will create.
 */
const Rail = ({ steps, index, done, canJump, onJump, chosen, totalSteps }) => (
  <aside className="zgs-rail">
    <div>
      <p className="zgs-rail__eyebrow">{__("Setup", "zaplane")}</p>
      <ol className="zgs-steps">
        {steps.map((step, i) => {
          const complete = done || i < index;
          const current = !done && i === index;

          return (
            <li key={step.key} className={complete ? "is-done" : undefined}>
              <button
                type="button"
                className={`zgs-step${complete ? " is-done" : ""}${step.alert ? " has-alert" : ""}`}
                aria-current={current ? "step" : undefined}
                disabled={done || !canJump(i)}
                onClick={() => onJump(i)}
              >
                <span className="zgs-step__dot">{complete ? <FiCheck size={13} aria-hidden="true" /> : i + 1}</span>
                <span>
                  <span className="zgs-step__label">{step.label}</span>
                  {step.hint && <span className="zgs-step__hint">{step.hint}</span>}
                </span>
              </button>
            </li>
          );
        })}
      </ol>
    </div>

    <div className="zgs-rail__summary" aria-live="polite">
      <p className="zgs-rail__caption">{done ? __("Created", "zaplane") : __("Will create", "zaplane")}</p>
      {/* Keyed by the count, so each change plays its entrance again. */}
      <p key={chosen.length} className="zgs-rail__count">
        {sprintf(_n("%d workflow", "%d workflows", chosen.length, "zaplane"), chosen.length)}
      </p>
      <p className="zgs-rail__caption">
        {sprintf(_n("%d step in all", "%d steps in all", totalSteps, "zaplane"), totalSteps)}
      </p>
      {chosen.length > 0 && (
        <ul className="zgs-rail__list">
          {chosen.map(workflow => (
            <li key={workflow.key}>
              <FiCheck size={12} aria-hidden="true" />
              <span>{workflow.title}</span>
            </li>
          ))}
        </ul>
      )}
    </div>
  </aside>
);

export default Rail;
