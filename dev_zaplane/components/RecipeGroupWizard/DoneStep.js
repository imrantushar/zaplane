import { __, _n, sprintf } from "@wordpress/i18n";
import { FiArrowRight, FiCheck } from "react-icons/fi";

const DoneStep = ({ result, onOpenWorkflow }) => {
  const workflows = result?.workflows || [];
  const notOn = workflows.filter(workflow => workflow.error).length;

  return (
    <div className="zgs-done">
      <div className="zgs-done__hero">
        <span className="zgs-done__icon">
          <FiCheck size={26} aria-hidden="true" />
        </span>
        <div>
          <p className="zgs-done__title">
            {sprintf(_n("Created %d workflow", "Created %d workflows", workflows.length, "zaplane"), workflows.length)}
          </p>
          <p className="zgs-done__text">
            {result.folder
              ? sprintf(__("In the folder “%s”.", "zaplane"), result.folder.title)
              : __("It's with your other workflows.", "zaplane")}
            {notOn > 0 && ` ${sprintf(_n("%d couldn't be turned on yet.", "%d couldn't be turned on yet.", notOn, "zaplane"), notOn)}`}
          </p>
        </div>
      </div>

      <div className="zgs-summary">
        {workflows.map((workflow, i) => {
          const active = workflow.status === "active";

          return (
            <div key={workflow.id} className="zgs-result" style={{ animationDelay: `${Math.min(i, 8) * 50}ms` }}>
              <div className="zgs-result__main">
                <p className="zgs-summary__title">{workflow.title}</p>
                {workflow.error && <p className="zgs-result__error">{workflow.error}</p>}
              </div>
              <span className={`zgs-status zgs-status--${active ? "active" : "draft"}`}>
                {active ? __("Active", "zaplane") : __("Draft", "zaplane")}
              </span>
              <button type="button" className="zgs-btn zgs-btn--ghost zgs-btn--small" onClick={() => onOpenWorkflow(workflow.id)}>
                {__("Open", "zaplane")}
                <FiArrowRight className="zgs-btn__next" size={13} aria-hidden="true" />
              </button>
            </div>
          );
        })}
      </div>
    </div>
  );
};

export default DoneStep;
