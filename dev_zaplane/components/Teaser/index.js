import React, { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { FiArrowRight, FiX } from 'react-icons/fi';
import { API, admin_url, namespace } from '@ZAPUtils/helper';

/**
 * A dismissible card telling the user about a module they are not using.
 *
 * Which teaser belongs on which screen, and whether it is still worth showing,
 * is decided server-side — see Zaplane\Features\Teasers. This renders whatever
 * comes back and stays out of the way when nothing does, so adding a teaser
 * later needs no change here.
 */
const Teaser = ({ screen }) => {
  const [teaser, setTeaser] = useState(null);
  const [leaving, setLeaving] = useState(false);

  useEffect(() => {
    let cancelled = false;

    API.get(`${namespace}teasers`, { params: { screen } })
      .then(res => {
        if (!cancelled && res.data && res.data.key) setTeaser(res.data);
      })
      .catch(() => {
        // Discovery is a nicety; a failure here must never break the page.
      });

    return () => {
      cancelled = true;
    };
  }, [screen]);

  if (!teaser || leaving) return null;

  const dismiss = () => {
    setLeaving(true);
    API.post(`${namespace}teasers/${teaser.key}/dismiss`).catch(() => {});
  };

  const target = `${admin_url}admin.php?page=zaplane-settings${teaser.cta_panel ? `&tab=${teaser.cta_panel}` : ''}`;

  return (
    <div className="relative mb-6 flex items-start gap-4 rounded-lg border border-[var(--zaplane-border-color)] bg-[var(--zaplane-second-primary)] p-4 pr-10">
      <div className="min-w-0 flex-1">
        <div className="flex items-center gap-2">
          <span className="rounded-full bg-[var(--zaplane-primary)] px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">
            {__('Did you know', 'zaplane')}
          </span>
          <span className="text-[14px] font-semibold text-[var(--zaplane-font-color)]">{teaser.title}</span>
        </div>
        <p className="mb-0 mt-1.5 text-[13px] leading-[1.55] text-[var(--zaplane-font-secondary-color)]">{teaser.body}</p>
        {teaser.cta_label && (
          <a
            href={target}
            className="mt-3 inline-flex items-center gap-1.5 text-[13px] font-semibold text-[var(--zaplane-primary)] no-underline hover:underline"
          >
            {teaser.cta_label}
            <FiArrowRight size={14} />
          </a>
        )}
      </div>
      <button
        type="button"
        onClick={dismiss}
        aria-label={__('Dismiss', 'zaplane')}
        title={__('Dismiss', 'zaplane')}
        className="absolute right-3 top-3 rounded-[4px] p-1 text-[var(--zaplane-font-secondary-color)] hover:text-[var(--zaplane-font-color)]"
      >
        <FiX size={16} />
      </button>
    </div>
  );
};

export default Teaser;
