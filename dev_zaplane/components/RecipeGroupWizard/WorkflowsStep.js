import { __, _n, sprintf } from "@wordpress/i18n";
import ZAPToggle from "@ZAPComponents/ZAPToggle";
import { Banner, Intro, StepChain, activeSteps } from "./parts";

const onKeys = handler => event => {
  if (event.key === " " || event.key === "Enter") {
    event.preventDefault();
    handler();
  }
};

/**
 * One workflow the recipe can create. The whole card switches it on or off; its
 * options open underneath while it is on, and its steps show what each option
 * adds or leaves out. A recipe of one workflow always creates it, so its card
 * has no switch and its options stay open.
 */
const WorkflowCard = ({ workflow, on, fixed, options, onWorkflow, onOption }) => {
  const toggle = () => onWorkflow(workflow.key, !on);
  const count = activeSteps(workflow, options).length;

  const text = (
    <>
      <div className="zgs-card__text">
        <p className="zgs-card__title">{workflow.title}</p>
        {workflow.description && <p className="zgs-card__desc">{workflow.description}</p>}
      </div>
      {count > 0 && <span className="zgs-pill">{sprintf(_n("%d step", "%d steps", count, "zaplane"), count)}</span>}
    </>
  );

  return (
    <div className={`zgs-card${on ? " is-on" : ""}`}>
      {fixed ? (
        <div className="zgs-card__head">{text}</div>
      ) : (
        <div
          className="zgs-card__head"
          role="switch"
          aria-checked={on}
          aria-label={workflow.title}
          tabIndex={0}
          onClick={toggle}
          onKeyDown={onKeys(toggle)}
        >
          <ZAPToggle decorative checked={on} />
          {text}
        </div>
      )}

      <StepChain workflow={workflow} options={options} />

      {workflow.options.length > 0 && (
        <div className={`zgs-options${on ? " is-open" : ""}`} aria-hidden={!on}>
          <div>
            <div className="zgs-options__list">
              {workflow.options.map(option => {
                const checked = !!options[workflow.key]?.[option.key];
                const adds = (workflow.steps || []).filter(step => step.option === option.key).length;

                return (
                  <button
                    key={option.key}
                    type="button"
                    role="switch"
                    aria-checked={checked}
                    tabIndex={on ? 0 : -1}
                    className="zgs-option"
                    onClick={() => onOption(workflow.key, option.key, !checked)}
                  >
                    <ZAPToggle decorative size="sm" checked={checked} />
                    <span className="zgs-option__label">
                      {option.label}
                      {option.description && <span className="zgs-hint zgs-option__desc">{option.description}</span>}
                    </span>
                    {adds > 0 && (
                      <span className="zgs-option__meta">
                        {sprintf(_n("+%d step", "+%d steps", adds, "zaplane"), adds)}
                      </span>
                    )}
                  </button>
                );
              })}
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

const WorkflowsStep = ({ setup, single, workflows, options, inactiveApps, onWorkflow, onOption, onAll }) => {
  const allOn = setup.workflows.every(workflow => workflows[workflow.key]);

  return (
    <div className="zgs-stack">
      {single ? (
        <Intro
          title={__("Choose the optional steps", "zaplane")}
          text={__("Switch on the steps you want. The ones left off aren't added to the workflow.", "zaplane")}
        />
      ) : (
        <Intro
          title={__("Choose what to set up", "zaplane")}
          text={__("Each one becomes its own workflow in a new folder. Click a card to switch it on or off.", "zaplane")}
          action={
            <button type="button" className="zgs-link" onClick={() => onAll(!allOn)}>
              {allOn ? __("Clear all", "zaplane") : __("Select all", "zaplane")}
            </button>
          }
        />
      )}

      {inactiveApps.length > 0 && (
        <Banner tone="warning">
          {sprintf(
            _n(
              "%s isn't active on this site. The workflows that use it are created, but won't run until it is.",
              "%s aren't active on this site. The workflows that use them are created, but won't run until they are.",
              inactiveApps.length,
              "zaplane"
            ),
            inactiveApps.map(app => app.name).join(", ")
          )}
        </Banner>
      )}

      {setup.workflows.map(workflow => (
        <WorkflowCard
          key={workflow.key}
          workflow={workflow}
          on={single || !!workflows[workflow.key]}
          fixed={single}
          options={options}
          onWorkflow={onWorkflow}
          onOption={onOption}
        />
      ))}
    </div>
  );
};

export default WorkflowsStep;
