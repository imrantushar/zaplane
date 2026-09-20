import { __, sprintf } from "@wordpress/i18n";
import { FiAlertTriangle, FiChevronRight, FiInfo } from "react-icons/fi";
import { plugin_root_url } from "@ZAPUtils/helper";
import { isAbsoluteIcon, resolveIconFilename } from "@ZAPComponents/ZAPIconGroup/ZAPIconGroup";

/** An app's icon: a bundled SVG, an absolute URL, or a glyph from the icon font. */
export const StepIcon = ({ icon, size = 14 }) => {
  const name = icon ? resolveIconFilename(icon) : "";

  if (!name) {
    return <span className="zgs-icon zgs-icon--empty" aria-hidden="true" />;
  }

  if (isAbsoluteIcon(name) || name.endsWith(".svg")) {
    const src = isAbsoluteIcon(name) ? name : `${plugin_root_url}assets/images/icons/${name}`;
    return <img className="zgs-icon" src={src} alt="" style={{ width: size, height: size }} />;
  }

  return (
    <span
      className={`zgs-icon zaplane-icon zaplane-icon--${name}`}
      style={{ width: size, height: size, fontSize: size }}
      aria-hidden="true"
    />
  );
};

export const Banner = ({ tone = "info", children }) => (
  <div className={`zgs-banner zgs-banner--${tone}`} role={tone === "danger" ? "alert" : undefined}>
    {tone === "info" ? <FiInfo size={15} aria-hidden="true" /> : <FiAlertTriangle size={15} aria-hidden="true" />}
    <div>{children}</div>
  </div>
);

export const Intro = ({ title, text, action = null }) => (
  <div className="zgs-intro">
    <div>
      <h3 className="zgs-intro__title">{title}</h3>
      {text && <p className="zgs-intro__text">{text}</p>}
    </div>
    {action}
  </div>
);

/** Whether a step will be created: it is always there, or its option is on. */
export const isStepOn = (step, workflowKey, options) => !step.option || !!options[workflowKey]?.[step.option];

export const activeSteps = (workflow, options) =>
  (workflow.steps || []).filter(step => isStepOn(step, workflow.key, options));

/**
 * The steps a run goes through, as a row of chips. A step whose option is off is
 * struck through, or left out entirely with `onlyOn`.
 */
export const StepChain = ({ workflow, options, onlyOn = false }) => {
  const steps = (workflow.steps || []).filter(step => !onlyOn || isStepOn(step, workflow.key, options));

  if (!steps.length) {
    return null;
  }

  return (
    <ol className="zgs-chain" aria-label={sprintf(__("Steps of %s", "zaplane"), workflow.title)}>
      {steps.map((step, i) => {
        const on = isStepOn(step, workflow.key, options);

        return (
          <li key={step.id} className={`zgs-chain__step${step.trigger ? " is-trigger" : ""}${on ? "" : " is-off"}`}>
            {i > 0 && <FiChevronRight className="zgs-chain__sep" size={13} aria-hidden="true" />}
            <span className="zgs-chain__chip">
              <StepIcon icon={step.icon} />
              <span>{step.name}</span>
              {!on && <span className="screen-reader-text">{__("(left out)", "zaplane")}</span>}
            </span>
          </li>
        );
      })}
    </ol>
  );
};

/** A value as the review shows it, with its unit. */
export const formatValue = (value, raw) => {
  const text = String(raw ?? "");
  if (!value.suffix) return text;
  return value.suffix === "%" ? `${text}%` : `${text} ${value.suffix}`;
};

/**
 * Why a setup value would be refused, or null. Mirrors the server's check in
 * RecipeGroupBuilder, so the setup can say so before anything is sent.
 */
export const valueError = (value, raw) => {
  const text = String(raw ?? "").trim();

  if (value.type !== "number") {
    return value.required && text === "" ? sprintf(__("%s is required.", "zaplane"), value.label) : null;
  }

  if (text === "" || Number.isNaN(Number(text))) {
    return sprintf(__("%s must be a number.", "zaplane"), value.label);
  }

  const number = Number(text);
  const { min, max } = value;

  if ((min != null && number < min) || (max != null && number > max)) {
    if (min != null && max != null) {
      return sprintf(__("%1$s must be between %2$s and %3$s.", "zaplane"), value.label, min, max);
    }
    return min != null
      ? sprintf(__("%1$s must be at least %2$s.", "zaplane"), value.label, min)
      : sprintf(__("%1$s must be at most %2$s.", "zaplane"), value.label, max);
  }

  return null;
};
