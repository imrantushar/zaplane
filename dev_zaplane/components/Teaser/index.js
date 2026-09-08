import React, { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { FiArrowRight, FiX } from 'react-icons/fi';
import { API, admin_url, namespace } from '@ZAPUtils/helper';

/**
 * A card telling the user about a module they have not switched on.
 *
 * Two modes, both answered server-side by Zaplane\Features\Teasers:
 *
 *   <Teaser screen="workflows" />   what belongs on this page
 *   <Teaser app="knowledge" />      the node just picked belongs to a module
 *                                   this site has not enabled
 *
 * The app form carries `activates`, which turns the call to action into a
 * one-click switch rather than a link — nobody should have to abandon a
 * half-built workflow to go and flip something in Settings. It is also not
 * dismissible, because it describes the node in front of you rather than making
 * a suggestion you might want to file away.
 *
 * Renders nothing when the server has nothing to say, so adding or retiring a
 * teaser needs no change here.
 */
const Teaser = ({ screen, app, onActivated }) => {
  const [teaser, setTeaser] = useState(null);
  const [leaving, setLeaving] = useState(false);
  const [activating, setActivating] = useState(false);

  useEffect(() => {
    let cancelled = false;
    setTeaser(null);
    setLeaving(false);

    if (!screen && !app) return undefined;

    API.get(`${namespace}teasers`, { params: app ? { app } : { screen } })
      .then(res => {
        if (!cancelled && res.data && res.data.key) setTeaser(res.data);
      })
      .catch(() => {
        // Discovery is a nicety; a failure here must never break the page.
      });

    return () => {
      cancelled = true;
    };
  }, [screen, app]);

  if (!teaser || leaving) return null;

  const dismiss = () => {
    setLeaving(true);
    API.post(`${namespace}teasers/${teaser.key}/dismiss`).catch(() => {});
  };

  const activate = () => {
    setActivating(true);
    API.post(`${namespace}modules/${teaser.activates}/activate`)
      // Only the request failing may reset the button. Doing more work inside
      // then() risks a throw here being reported as a failed save: the injected
      // ZaplaneGlobal.settings snapshot is frozen, and writing to it under the
      // bundle's strict mode threw, so the module was switched on server-side
      // while the card claimed nothing had happened. The server is the source of
      // truth and the snapshot is re-read on the next page load, so leave it be.
      .then(
        () => {
          setLeaving(true);
          if (onActivated) onActivated(teaser.activates);
        },
        () => setActivating(false)
      );
  };

  const target = `${admin_url}admin.php?page=zaplane-settings${teaser.cta_panel ? `&tab=${teaser.cta_panel}` : ''}`;

  return (
    <div className={`relative flex items-start gap-4 rounded-lg border border-[var(--zaplane-border-color)] bg-[var(--zaplane-second-primary)] p-4 ${teaser.activates ? 'mb-4' : 'mb-6 pr-10'}`}>
      <div className="min-w-0 flex-1">
        <div className="flex items-center gap-2">
          <span className="rounded-full bg-[var(--zaplane-primary)] px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">
            {teaser.activates ? __('Module off', 'zaplane') : __('Did you know', 'zaplane')}
          </span>
          <span className="text-[14px] font-semibold text-[var(--zaplane-font-color)]">{teaser.title}</span>
        </div>
        <p className="mb-0 mt-1.5 text-[13px] leading-[1.55] text-[var(--zaplane-font-secondary-color)]">{teaser.body}</p>
        {teaser.cta_label && teaser.activates && (
          <button
            type="button"
            onClick={activate}
            disabled={activating}
            className={`mt-3 inline-flex items-center gap-1.5 rounded-[4px] px-3 py-1.5 text-[13px] font-semibold text-white transition-opacity ${
              activating ? 'cursor-wait opacity-60' : 'cursor-pointer hover:opacity-90'
            }`}
            style={{ backgroundColor: 'var(--zaplane-primary)' }}
          >
            {activating ? __('Turning on…', 'zaplane') : teaser.cta_label}
          </button>
        )}

        {teaser.cta_label && !teaser.activates && (
          <a
            href={target}
            className="mt-3 inline-flex items-center gap-1.5 text-[13px] font-semibold text-[var(--zaplane-primary)] no-underline hover:underline"
          >
            {teaser.cta_label}
            <FiArrowRight size={14} />
          </a>
        )}
      </div>

      {/* A page-level suggestion can be put away; a note about the node you are
          configuring cannot, since it would just be wrong to hide it. */}
      {!teaser.activates && (
        <button
          type="button"
          onClick={dismiss}
          aria-label={__('Dismiss', 'zaplane')}
          title={__('Dismiss', 'zaplane')}
          className="absolute right-3 top-3 rounded-[4px] p-1 text-[var(--zaplane-font-secondary-color)] hover:text-[var(--zaplane-font-color)]"
        >
          <FiX size={16} />
        </button>
      )}
    </div>
  );
};

export default Teaser;
