import { __ } from "@wordpress/i18n";

const ZAPCheckbox = ({
  label,
  checked,
  onChange,
  isRequired = false,
  containerStyle
}) => {
  return (
    <div style={{ display: "flex", flexDirection: "column", gap: "8px", ...containerStyle }}>
      <label className="flex items-center gap-2 cursor-pointer w-fit">
        <input
          type="checkbox"
          className="zaplane-checkbox"
          checked={!!checked}
          onChange={(e) => onChange?.(e.target.checked)}
        />
        {label && (
          <span className="zaplane-label">
            {__(label, "zaplane")}
            {isRequired && <span style={{ color: "red", marginLeft: "2px" }}>*</span>}
          </span>
        )}
      </label>
    </div>
  );
};

export default ZAPCheckbox;
