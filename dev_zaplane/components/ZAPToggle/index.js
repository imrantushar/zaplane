import React from 'react';

/**
 * An on/off switch.
 *
 * The track colour is set inline so it beats Tailwind preflight's transparent
 * background-color on [type=button], which outranks the bg-[...] utility.
 */
const ZAPToggle = ({ checked, onChange, label, disabled = false, size = 'md' }) => {
  const small = size === 'sm';

  return (
    <button
      type="button"
      role="switch"
      aria-checked={!!checked}
      aria-label={label}
      disabled={disabled}
      onClick={() => onChange(!checked)}
      style={{
        backgroundColor: checked ? 'var(--zaplane-primary)' : 'var(--zaplane-border-color)',
        opacity: disabled ? 0.5 : 1,
        cursor: disabled ? 'not-allowed' : 'pointer',
      }}
      className={`relative inline-flex ${small ? 'h-5 w-9' : 'h-6 w-11'} shrink-0 items-center rounded-full transition-colors`}
    >
      <span
        className={`inline-block ${small ? 'h-[14px] w-[14px]' : 'h-[18px] w-[18px]'} transform rounded-full bg-white transition-transform ${
          checked ? (small ? 'translate-x-[19px]' : 'translate-x-[23px]') : 'translate-x-[3px]'
        }`}
        style={{ boxShadow: '0 1px 3px rgba(0,0,0,0.4)' }}
      />
    </button>
  );
};

export default ZAPToggle;
