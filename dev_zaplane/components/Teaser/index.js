import React, { useCallback, useEffect, useState } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { FiArrowRight, FiX, FiZap } from 'react-icons/fi';
import { API, admin_url, namespace } from '@ZAPUtils/helper';

/**
 * Surfaces modules this site has not switched on.
 *
 * Two shapes, both answered server-side by Zaplane\Features\Teasers:
 *
 *   <Teaser screen="workflows" />  every module that is off, one card, each with
 *                                 a switch. The module this screen is about
 *                                 leads and is marked.
 *   <Teaser app="knowledge" />     the node just picked belongs to a module that
 *                                 is off. Not dismissible — it describes what is
 *                                 in front of you.
 *
 * Renders nothing when the server has nothing to say, so adding or retiring a
 * module needs no change here.
 */

/**
 * Notice colours.
 *
 * A neutral grey border around a blue-tinted panel reads as muddy, because the
 * two are unrelated hues. Deriving the border and accent from the same primary
 * the background is tinted with keeps the card one colour at different
 * strengths. color-mix is used rather than Tailwind's `/opacity` syntax, which
 * does not work against a CSS custom property.
 */
const TINT = {
  surface: 'color-mix(in srgb, var(--zaplane-primary) 6%, var(--zaplane-background))',
  border: 'color-mix(in srgb, var(--zaplane-primary) 22%, transparent)',
  accent: 'color-mix(in srgb, var(--zaplane-primary) 12%, transparent)',
  rule: 'color-mix(in srgb, var(--zaplane-primary) 14%, transparent)',
};

const Shell = ({ children, onDismiss }) => (
  <div
    className="relative mb-6 overflow-hidden rounded-lg"
    style={{ background: TINT.surface, border: `1px solid ${TINT.border}` }}
  >
    {/* A left accent reads as "notice" without needing a heavier border. */}
    <span aria-hidden="true" className="absolute inset-y-0 left-0 w-[3px]" style={{ background: 'var(--zaplane-primary)' }} />
    <div className="py-4 pl-5 pr-4">{children}</div>
    {onDismiss && (
      <button
        type="button"
        onClick={onDismiss}
        aria-label={__('Dismiss', 'zaplane')}
        title={__('Dismiss', 'zaplane')}
        className="absolute right-3 top-3 rounded-[4px] p-1 text-[var(--zaplane-font-secondary-color)] hover:text-[var(--zaplane-font-color)]"
      >
        <FiX size={16} />
      </button>
    )}
  </div>
);

const ActivateButton = ({ label, busy, onClick, subtle }) => (
  <button
    type="button"
    onClick={onClick}
    disabled={busy}
    className={`inline-flex shrink-0 items-center justify-center rounded-[4px] px-3 py-1.5 text-[12px] font-semibold transition-opacity ${
      busy ? 'cursor-wait opacity-60' : 'cursor-pointer hover:opacity-90'
    }`}
    style={
      subtle
        ? { background: 'transparent', border: `1px solid ${TINT.border}`, color: 'var(--zaplane-primary)' }
        : { background: 'var(--zaplane-primary)', color: '#fff' }
    }
  >
    {busy ? __('Turning on…', 'zaplane') : label}
  </button>
);

const Teaser = ({ screen, app, onActivated }) => {
  const [data, setData] = useState(null);
  const [hidden, setHidden] = useState(false);
  const [busyKey, setBusyKey] = useState(null);
  const [done, setDone] = useState([]);

  const load = useCallback(() => {
    if (!screen && !app) return;

    API.get(`${namespace}teasers`, { params: app ? { app } : { screen } })
      .then(res => setData(res.data && res.data.key ? res.data : null))
      .catch(() => {
        // Discovery is a nicety; a failure here must never break the page.
      });
  }, [screen, app]);

  useEffect(() => {
    setData(null);
    setHidden(false);
    setDone([]);
    load();
  }, [load]);

  if (!data || hidden) return null;

  const activate = key => {
    setBusyKey(key);
    API.post(`${namespace}modules/${key}/activate`).then(
      () => {
        // Nothing else in this handler: window.ZaplaneGlobal.settings is frozen,
        // and writing to it under the bundle's strict mode throws, which a shared
        // catch would then report as a failed save.
        setBusyKey(null);
        setDone(current => [...current, key]);
        if (onActivated) onActivated(key);
      },
      () => setBusyKey(null)
    );
  };

  const dismiss = () => {
    setHidden(true);
    API.post(`${namespace}teasers/${data.key}/dismiss`).catch(() => {});
  };

  /* ------------------------- one node, one module ------------------------- */

  if (data.activates) {
    if (done.includes(data.activates)) return null;

    return (
      <Shell>
        <div className="flex items-start gap-4">
          <div className="min-w-0 flex-1">
            <div className="text-[14px] font-semibold text-[var(--zaplane-font-color)]">{data.title}</div>
            <p className="mb-0 mt-1 text-[13px] leading-[1.55] text-[var(--zaplane-font-secondary-color)]">{data.body}</p>
          </div>
          <ActivateButton label={data.cta_label} busy={busyKey === data.activates} onClick={() => activate(data.activates)} />
        </div>
      </Shell>
    );
  }

  /* ------------------------- every module that is off --------------------- */

  const remaining = (data.modules || []).filter(m => !done.includes(m.key));
  if (!remaining.length) return null;

  return (
    <Shell onDismiss={dismiss}>
      <div className="flex items-center gap-2 pr-6">
        <span
          className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full"
          style={{ background: TINT.accent, color: 'var(--zaplane-primary)' }}
        >
          <FiZap size={13} />
        </span>
        <span className="text-[14px] font-semibold text-[var(--zaplane-font-color)]">{data.title}</span>
        <span className="text-[12px] text-[var(--zaplane-text-muted)]">
          {sprintf(
            /* translators: %d: number of modules that are switched off. */
            _n('%d not turned on', '%d not turned on', remaining.length, 'zaplane'),
            remaining.length
          )}
        </span>
      </div>

      <p className="mb-0 mt-1.5 text-[13px] text-[var(--zaplane-font-secondary-color)]">{data.body}</p>

      <div className="mt-3 flex flex-col" style={{ borderTop: `1px solid ${TINT.rule}` }}>
        {remaining.map(module => (
          <div
            key={module.key}
            className="flex items-start justify-between gap-4 py-3"
            style={{ borderBottom: `1px solid ${TINT.rule}` }}
          >
            <div className="min-w-0">
              <div className="flex items-center gap-2">
                <span className="text-[13px] font-semibold text-[var(--zaplane-font-color)]">{module.title}</span>
                {module.relevant_here && (
                  <span
                    className="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                    style={{ background: TINT.accent, color: 'var(--zaplane-primary)' }}
                  >
                    {__('Useful here', 'zaplane')}
                  </span>
                )}
              </div>
              <p className="mb-0 mt-0.5 text-[12.5px] leading-[1.5] text-[var(--zaplane-font-secondary-color)]">
                {module.description}
              </p>
            </div>
            <ActivateButton
              label={__('Turn on', 'zaplane')}
              busy={busyKey === module.key}
              onClick={() => activate(module.key)}
              subtle
            />
          </div>
        ))}
      </div>

      <a
        href={`${admin_url}admin.php?page=zaplane-settings&tab=modules`}
        className="mt-3 inline-flex items-center gap-1.5 text-[12.5px] font-semibold text-[var(--zaplane-primary)] no-underline hover:underline"
      >
        {__('Manage all modules', 'zaplane')}
        <FiArrowRight size={13} />
      </a>
    </Shell>
  );
};

export default Teaser;
