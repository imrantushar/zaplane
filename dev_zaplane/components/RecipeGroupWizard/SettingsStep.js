import { __ } from "@wordpress/i18n";
import { Intro, formatValue } from "./parts";

/** A slider sits under the number when the range is small enough to drag through. */
const hasSlider = value =>
  value.type === "number" && value.min != null && value.max != null && value.max > value.min && value.max - value.min <= 1000;

const ValueCard = ({ value, answer, error, users, onChange }) => {
  const id = `zgs-value-${value.key}`;
  const slider = hasSlider(value);
  const number = Number(answer);
  const position = Number.isFinite(number) ? Math.min(Math.max(number, value.min), value.max) : value.min;
  const fill = slider ? ((position - value.min) / (value.max - value.min)) * 100 : 0;

  return (
    <div className={`zgs-value${error ? " has-error" : ""}`}>
      <div>
        <label htmlFor={id} className="zgs-value__label">
          {value.label}
        </label>
        {value.description && <p className="zgs-value__desc">{value.description}</p>}
        {users.length > 0 && (
          <div className="zgs-chips" aria-label={__("Used by", "zaplane")}>
            {users.map(title => (
              <span key={title} className="zgs-chip">
                {title}
              </span>
            ))}
          </div>
        )}
      </div>

      <div className="zgs-value__control">
        <div className="zgs-input">
          <input
            id={id}
            type={value.type === "number" ? "number" : "text"}
            value={answer ?? ""}
            min={value.min ?? undefined}
            max={value.max ?? undefined}
            aria-invalid={!!error}
            onChange={e => onChange(value.key, e.target.value)}
          />
          {value.suffix && <span className="zgs-input__suffix">{value.suffix}</span>}
        </div>
        {slider && (
          <>
            <input
              type="range"
              className="zgs-range"
              min={value.min}
              max={value.max}
              value={position}
              style={{ "--zgs-fill": `${fill}%` }}
              tabIndex={-1}
              aria-hidden="true"
              onChange={e => onChange(value.key, e.target.value)}
            />
            <div className="zgs-range__scale" aria-hidden="true">
              <span>{formatValue(value, value.min)}</span>
              <span>{formatValue(value, value.max)}</span>
            </div>
          </>
        )}
      </div>

      {error && <p className="zgs-value__error">{error}</p>}
    </div>
  );
};

const SettingsStep = ({ values, answers, errors, usedBy, onChange }) => (
  <div className="zgs-stack">
    <Intro
      title={__("Adjust the settings", "zaplane")}
      text={__("They are written into the steps that use them. You can still change them in each workflow later.", "zaplane")}
    />

    {values.map(value => (
      <ValueCard
        key={value.key}
        value={value}
        answer={answers[value.key]}
        error={errors[value.key]}
        users={usedBy(value)}
        onChange={onChange}
      />
    ))}
  </div>
);

export default SettingsStep;
