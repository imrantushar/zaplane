import { __, _n, sprintf } from "@wordpress/i18n";
import { FiFileText, FiFolder } from "react-icons/fi";
import ZAPToggle from "@ZAPComponents/ZAPToggle";
import { Banner, Intro, StepChain, activeSteps, formatValue } from "./parts";

const ReviewStep = ({ setup, single, chosen, options, valuesInUse, values, inactiveApps, folderTitle, onFolderTitle, activate, onActivate }) => (
  <div className="zgs-stack zgs-stack--loose">
    <Intro title={__("Review and create", "zaplane")} text={__("Check what will be created, then create it.", "zaplane")} />

    <div className="zgs-field">
      <label className="zgs-field__label" htmlFor="zgs-folder-title">
        {single ? __("Workflow name", "zaplane") : __("Folder name", "zaplane")}
      </label>
      <div className="zgs-input">
        {single ? <FiFileText size={15} aria-hidden="true" /> : <FiFolder size={15} aria-hidden="true" />}
        <input id="zgs-folder-title" type="text" value={folderTitle} onChange={e => onFolderTitle(e.target.value)} />
      </div>
      {setup.folders.length > 0 && (
        <Banner tone="info">
          {sprintf(__("Already set up in “%s”. This creates another folder.", "zaplane"), setup.folders[0].title)}
        </Banner>
      )}
    </div>

    <div className="zgs-field">
      <p className="zgs-field__label">
        {sprintf(_n("%d workflow", "%d workflows", chosen.length, "zaplane"), chosen.length)}
      </p>
      <div className="zgs-summary">
        {chosen.map(workflow => {
          const count = activeSteps(workflow, options).length;
          const without = workflow.options.filter(option => !options[workflow.key]?.[option.key]);

          return (
            <div key={workflow.key} className="zgs-summary__row">
              <div className="zgs-summary__head">
                <p className="zgs-summary__title">{workflow.title}</p>
                {count > 0 && <span className="zgs-pill">{sprintf(_n("%d step", "%d steps", count, "zaplane"), count)}</span>}
              </div>
              <StepChain workflow={workflow} options={options} onlyOn />
              {without.length > 0 && (
                <p className="zgs-hint">{sprintf(__("Without: %s", "zaplane"), without.map(option => option.label).join(", "))}</p>
              )}
            </div>
          );
        })}
      </div>
    </div>

    {valuesInUse.length > 0 && (
      <div className="zgs-field">
        <p className="zgs-field__label">{__("Settings", "zaplane")}</p>
        <div className="zgs-chips">
          {valuesInUse.map(value => (
            <span key={value.key} className="zgs-chip">
              {value.label}
              <strong>{formatValue(value, values[value.key])}</strong>
            </span>
          ))}
        </div>
      </div>
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

    <button type="button" role="switch" aria-checked={activate} className="zgs-toggle-row" onClick={() => onActivate(!activate)}>
      <ZAPToggle decorative checked={activate} />
      <span>
        <span className="zgs-card__title">
          {single
            ? __("Turn the workflow on after creating it", "zaplane")
            : __("Turn the workflows on after creating them", "zaplane")}
        </span>
        <span className="zgs-card__desc">
          {single
            ? __("It's checked first. If it can't run yet, it stays a draft, and says why.", "zaplane")
            : __("Each one is checked first. One that can't run yet stays a draft, and says why.", "zaplane")}
        </span>
      </span>
    </button>
  </div>
);

export default ReviewStep;
