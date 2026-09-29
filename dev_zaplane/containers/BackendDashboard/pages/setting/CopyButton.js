import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { FiCheck, FiCopy } from 'react-icons/fi';

/**
 * Copy-to-clipboard with a moment of acknowledgement.
 *
 * Silent when the clipboard is unavailable — outside a secure context the
 * write throws, and the value beside it is still selectable by hand.
 */
const CopyButton = ({ value, label }) => {
  const [copied, setCopied] = useState(false);

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(value);
      setCopied(true);
      setTimeout(() => setCopied(false), 1800);
    } catch (e) {
      // Clipboard is blocked outside a secure context; the value stays selectable.
    }
  };

  return (
    <button
      type="button"
      onClick={copy}
      className="inline-flex shrink-0 items-center gap-1.5 rounded-[4px] border border-[var(--zaplane-border-color)] px-2.5 py-1.5 text-[12px] font-medium text-[var(--zaplane-font-secondary-color)] hover:text-[var(--zaplane-primary)]"
    >
      {copied ? <FiCheck size={13} /> : <FiCopy size={13} />}
      {copied ? __('Copied', 'zaplane') : label || __('Copy', 'zaplane')}
    </button>
  );
};

export default CopyButton;
