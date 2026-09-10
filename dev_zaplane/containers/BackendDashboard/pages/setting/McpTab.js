import React, { useCallback, useEffect, useState } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { FiTrash2 } from 'react-icons/fi';
import CopyButton from './CopyButton';
import ConnectClient from './ConnectClient';
import { API, namespace, route_path } from '@ZAPUtils/helper';

// Mirrors TokenStore::ALL_SCOPES. Ordered least to most dangerous.
const SCOPE_LABEL = {
  read: __('Read', 'zaplane'),
  write: __('Build', 'zaplane'),
  run: __('Run for real', 'zaplane'),
};

// Tailwind's bg-[var(--…)] loses to a more specific admin rule inside this app
// and computes transparent — a white label on a white card. Inline wins.
const tint = (token, pct) => `color-mix(in srgb, var(${token}) ${pct}%, transparent)`;

// Core hands back a username and a password; a client wants them as one
// Basic-auth header. Encoding it here saves everyone a base64 detour.
const basicHeader = ({ user, password }) =>
  `Basic ${btoa(`${user}:${password}`)}`;

// A REST failure carries a readable message; an outage carries none. Say the
// specific thing when there is one, because "something went wrong" sends people
// looking in the wrong place.
const failureText = (e, fallback) => {
  const said = e?.response?.data?.message;
  return said ? `${fallback} ${said}` : fallback;
};

const PRIMARY = { background: 'var(--zaplane-primary)', color: '#fff' };

const STATUS = {
  ok:   { mark: '\u2713', color: 'var(--zaplane-success, #12b76a)' },
  warn: { mark: '\u26a0', color: 'var(--zaplane-warning)' },
  fail: { mark: '\u2715', color: 'var(--zaplane-danger)' },
  skip: { mark: '\u2013', color: 'var(--zaplane-text-muted)' },
};

const ScopePill = ({ scope }) => (
  <span
    className="rounded-full px-2 py-0.5 text-[11px] font-medium"
    style={
      scope === 'run'
        ? { background: tint('--zaplane-warning', 15), color: 'var(--zaplane-warning)' }
        : { background: 'var(--zaplane-second-primary)', color: 'var(--zaplane-primary)' }
    }
  >
    {SCOPE_LABEL[scope] || scope}
  </span>
);

/**
 * Configuration only. Whether the module is on is decided on the Modules tab —
 * this panel is not rendered at all until it is.
 */
const McpTab = () => {
  const [info, setInfo] = useState(null);
  const [loading, setLoading] = useState(true);
  const [name, setName] = useState('');

  /**
   * The whole panel is hidden while `loading`, so refetching after a change used
   * to blank it and rebuild it — a setting that makes the screen vanish and come
   * back reads as one that did not save. Only the first read shows that state;
   * afterwards the panel stays put and the values change underneath it.
   */
  const load = useCallback(async ({ quiet = false } = {}) => {
    if (!quiet) setLoading(true);
    try {
      const res = await API.get(`${namespace}mcp/info`);
      setInfo(res.data);
    } catch (e) {
      if (!quiet) setInfo(null);
    } finally {
      if (!quiet) setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);


  const revoke = async id => {
    // eslint-disable-next-line no-alert
    if (!window.confirm(__('Revoke this token? Any client using it stops working immediately.', 'zaplane'))) return;
    await API.delete(`${namespace}mcp/tokens/${id}`);
    await load({ quiet: true });
  };

  const tokens = info?.tokens || [];
  const [checks, setChecks] = useState(null);
  const [checking, setChecking] = useState(false);
  const [auditCount, setAuditCount] = useState(0);
  const [label, setLabel] = useState('');
  const [connecting, setConnecting] = useState(false);
  const [freshCredential, setFreshCredential] = useState(null);
  const [notice, setNotice] = useState(null);
  const [savingAlerts, setSavingAlerts] = useState(false);
  const [clients, setClients] = useState([]);
  const [removing, setRemoving] = useState(null);
  const [pending, setPending] = useState([]);
  const [grants, setGrants] = useState({});
  const [deciding, setDeciding] = useState(null);

  // Core returns from its consent screen with the credential on the query
  // string. Read it once, show it, and take it out of the address bar — a
  // password in browser history is a password written down.
  //
  // It can also return having done nothing: declined, or refused because this
  // account is not allowed one. Those arrive as an address and no more, so
  // without saying something here the screen looks like it was never left.
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const password = params.get('password');
    const user = params.get('user_login');
    const outcome = params.get('zaplane_connect');

    if (password && user) {
      setFreshCredential({ user, password });
    } else if (params.get('error') === 'disabled') {
      setNotice({
        tone: 'warn',
        text: __(
          'WordPress will not issue an application password for this account. Ask whoever administers the site, or connect a client that can sign in through the browser.',
          'zaplane'
        ),
      });
    } else if (outcome === 'cancelled') {
      setNotice({ tone: 'quiet', text: __('Cancelled — no credential was made.', 'zaplane') });
    } else if (outcome === 'done') {
      // Approved, but nothing came back with it.
      setNotice({
        tone: 'warn',
        text: __('WordPress did not hand back a credential. Check Users → Profile before trying again, in case one was made anyway.', 'zaplane'),
      });
    }

    if (password || outcome || params.get('error')) {
      ['password', 'user_login', 'site_url', 'zaplane_connect', 'error'].forEach(k => params.delete(k));
      const rest = params.toString();
      window.history.replaceState({}, '', window.location.pathname + (rest ? `?${rest}` : '') + window.location.hash);
    }
  }, []);

  // The label comes from the field on this screen, or from whichever client the
  // installer was showing — a credential called "Zaplane" tells you nothing when
  // there are three of them.
  const startConnect = async (forLabel = null) => {
    setConnecting(true);
    setNotice(null);
    try {
      // Come back to this panel, not whichever one the page opens on: the
      // credential arrives on the query string and only this screen knows to
      // take it out of the address bar.
      const back = new URL(window.location.href);
      back.hash = '';
      back.searchParams.set('tab', 'mcp');

      const res = await API.post(`${namespace}mcp/connections/authorize-url`, {
        label: typeof forLabel === 'string' && forLabel ? forLabel : label,
        return_url: back.toString(),
      });
      if (res.data?.url) window.location.href = res.data.url;
    } catch (e) {
      setNotice({ tone: 'warn', text: failureText(e, __('Could not start the approval.', 'zaplane')) });
    } finally {
      setConnecting(false);
    }
  };

  const revokeConnection = async uuid => {
    setNotice(null);
    try {
      await API.delete(`${namespace}mcp/connections/${uuid}`);
      await load({ quiet: true });
    } catch (e) {
      setNotice({ tone: 'warn', text: failureText(e, __('Could not revoke that credential.', 'zaplane')) });
    }
  };

  const loadAudit = useCallback(async () => {
    try {
      const res = await API.get(`${namespace}mcp/audit`, { params: { per_page: 1 } });
      setAuditCount(res.data?.total || 0);
    } catch (e) {
      setAuditCount(0);
    }
  }, []);

  useEffect(() => {
    loadAudit();
  }, [loadAudit]);

  const loadClients = useCallback(async () => {
    try {
      const res = await API.get(`${namespace}mcp/clients`);
      setClients(res.data?.clients || []);
    } catch (e) {
      setClients([]);
    }
  }, []);

  useEffect(() => {
    loadClients();
  }, [loadClients]);

  const removeClient = async client => {
    const warning = client.token_count > 0
      ? __('Remove %s? Its token stops working immediately and it will have to sign in again.', 'zaplane')
      : __('Remove %s? It will have to register again before it can connect.', 'zaplane');

    // eslint-disable-next-line no-alert
    if (!window.confirm(warning.replace('%s', client.client_name))) return;

    setRemoving(client.client_id);
    try {
      await API.delete(`${namespace}mcp/clients/${client.client_id}`);
      await loadClients();
      await load({ quiet: true });
    } finally {
      setRemoving(null);
    }
  };

  const loadPending = useCallback(async () => {
    try {
      const res = await API.get(`${namespace}mcp/pending`);
      const rows = (res.data?.pending || []).filter(p => p.status === 'waiting');
      setPending(rows);
      // Everything asked for is pre-ticked except run, which has to be a choice.
      setGrants(g => {
        const next = { ...g };
        rows.forEach(p => {
          if (!next[p.id]) next[p.id] = p.scopes.filter(s => s !== 'run');
        });
        return next;
      });
    } catch (e) {
      setPending([]);
    }
  }, []);

  useEffect(() => {
    loadPending();
    // Someone may be sitting on the waiting page right now.
    const t = setInterval(loadPending, 10000);
    return () => clearInterval(t);
  }, [loadPending]);

  const decide = async (id, decision) => {
    setDeciding(id);
    try {
      await API.post(`${namespace}mcp/pending/${id}`, { decision, scopes: grants[id] || [] });
      await loadPending();
      await load({ quiet: true });
    } finally {
      setDeciding(null);
    }
  };

  const toggleGrant = (id, scope) =>
    setGrants(g => {
      const have = g[id] || [];
      return { ...g, [id]: have.includes(scope) ? have.filter(s => s !== scope) : [...have, scope] };
    });

  const runChecks = async () => {
    setChecking(true);
    try {
      const res = await API.get(`${namespace}mcp/diagnostics`);
      setChecks(res.data?.checks || []);
    } catch (e) {
      setChecks([{ key: 'error', status: 'fail', label: __('Could not run the check', 'zaplane'), fix: e?.message || '' }]);
    }
    setChecking(false);
  };

  return (
    <div className="flex flex-col gap-8">
      <div className="mb-1">
        <h3 className="text-[15px] font-semibold text-[var(--zaplane-font-color)]">{__('AI access (MCP)', 'zaplane')}</h3>
        <p className="mt-1 text-[13px] text-[var(--zaplane-font-secondary-color)]">
          {__(
            'Point an AI client — Claude, Cursor, or anything that speaks the Model Context Protocol — at the endpoint below. It can then read this site\'s automations and build new ones, within whatever you allow it.',
            'zaplane'
          )}
        </p>
      </div>

      {loading && <p className="text-[13px] text-[var(--zaplane-text-muted)]">{__('Loading…', 'zaplane')}</p>}

      {!loading && info && (
        <>
          <div>
            <div className="mb-2 text-[13px] font-medium text-[var(--zaplane-font-color)]">{__('Endpoint URL', 'zaplane')}</div>
            <div className="flex items-center gap-2">
              <code className="flex-1 overflow-x-auto whitespace-nowrap rounded-[4px] border border-[var(--zaplane-border-color)] bg-[var(--zaplane-secondary-color)] px-3 py-2 text-[12px] text-[var(--zaplane-font-color)]">
                {info.url}
              </code>
              <CopyButton value={info.url} />
            </div>
            <p className="mt-2 text-[12px] text-[var(--zaplane-text-muted)]">
              {__('Protocol', 'zaplane')} {info.protocol} · {info.tool_count} {__('tools', 'zaplane')}
            </p>

            {/* A connector run by someone else resolves this address from their
                servers. On a development hostname it never arrives, and all the
                connector can say is that it could not sign in — so say it here,
                where the cause is visible. */}
            {info.reachable === false && (
              <p
                className="mt-3 rounded-[4px] px-3 py-2 text-[12px] text-[var(--zaplane-font-color)]"
                style={{
                  background: tint('--zaplane-warning', 12),
                  border: `1px solid ${tint('--zaplane-warning', 45)}`,
                }}
              >
                {__(
                  'This address is not reachable from the internet, so claude.ai and ChatGPT cannot connect to it — their servers look it up from outside your machine. Clients running on this computer, such as Claude Code or Cursor, work fine.',
                  'zaplane'
                )}
              </p>
            )}
          </div>

          {pending.length > 0 && (
            <div
              className="rounded-[6px] p-4"
              style={{ background: tint('--zaplane-primary', 6), border: `1px solid ${tint('--zaplane-primary', 30)}` }}
            >
              <div className="text-[13px] font-medium text-[var(--zaplane-font-color)]">
                {__('Waiting for your approval', 'zaplane')}
              </div>
              <p className="mt-1 text-[12px] text-[var(--zaplane-font-secondary-color)]">
                {__('Someone who cannot manage this site has asked to connect an AI client. It acts as them, and only within what you allow here.', 'zaplane')}
              </p>

              <div className="mt-3 flex flex-col gap-3">
                {pending.map(p => (
                  <div key={p.id} className="rounded-[4px] border border-[var(--zaplane-border-color)] bg-[var(--zaplane-background)] p-3">
                    <div className="text-[13px] text-[var(--zaplane-font-color)]">
                      <strong>{p.client_name}</strong>
                      {p.user_name ? ` ${__('for', 'zaplane')} ${p.user_name}` : ''}
                    </div>
                    <div className="mt-2 flex flex-wrap items-center gap-3">
                      {p.scopes.map(scope => (
                        <label key={scope} className="flex items-center gap-1.5 text-[12px] text-[var(--zaplane-font-secondary-color)]">
                          <input
                            type="checkbox"
                            checked={(grants[p.id] || []).includes(scope)}
                            onChange={() => toggleGrant(p.id, scope)}
                          />
                          {SCOPE_LABEL[scope] || scope}
                        </label>
                      ))}
                    </div>
                    <div className="mt-3 flex gap-2">
                      <button
                        type="button"
                        disabled={deciding === p.id}
                        onClick={() => decide(p.id, 'approve')}
                        style={PRIMARY}
                        className="rounded-[4px] px-3 py-1.5 text-[12px] font-semibold disabled:opacity-60"
                      >
                        {__('Allow', 'zaplane')}
                      </button>
                      <button
                        type="button"
                        disabled={deciding === p.id}
                        onClick={() => decide(p.id, 'deny')}
                        className="rounded-[4px] border border-[var(--zaplane-border-color)] px-3 py-1.5 text-[12px] text-[var(--zaplane-font-color)] disabled:opacity-60"
                      >
                        {__('Refuse', 'zaplane')}
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          <ConnectClient
            url={info.url}
            header={freshCredential ? basicHeader(freshCredential) : null}
            reachable={info.reachable !== false}
            canCreate={!!info.app_passwords_available}
            creating={connecting}
            onCreate={startConnect}
          />

          {/* A client that cannot connect reports the symptom from outside —
              "could not reach", "could not register" — which says nothing about
              the cause. These checks run from the site, where it is visible. */}
          <div className="rounded-[6px] border border-[var(--zaplane-border-color)] p-4">
            <div className="flex items-start justify-between gap-4">
              <div>
                <div className="text-[13px] font-medium text-[var(--zaplane-font-color)]">
                  {__('A client cannot connect?', 'zaplane')}
                </div>
                <p className="mt-1 text-[12px] text-[var(--zaplane-font-secondary-color)]">
                  {__('An AI client can only tell you it failed, not why. This checks the same things it does, from here.', 'zaplane')}
                </p>
              </div>
              <button
                type="button"
                onClick={runChecks}
                disabled={checking}
                className="shrink-0 rounded-[4px] border border-[var(--zaplane-border-color)] px-3 py-1.5 text-[12px] font-medium text-[var(--zaplane-font-color)] hover:bg-[var(--zaplane-secondary-color)] disabled:opacity-60"
              >
                {checking ? __('Checking…', 'zaplane') : __('Run check', 'zaplane')}
              </button>
            </div>

            {checks && (
              <ul className="mt-3 flex flex-col gap-2.5">
                {checks.map(c => (
                  <li key={c.key} className="flex gap-2.5 text-[12px]">
                    <span className="mt-[3px] shrink-0" style={{ color: STATUS[c.status]?.color }}>
                      {STATUS[c.status]?.mark || '•'}
                    </span>
                    <span>
                      <span className="text-[var(--zaplane-font-color)]">{c.label}</span>
                      {c.fix ? (
                        <span className="mt-0.5 block text-[var(--zaplane-font-secondary-color)]">{c.fix}</span>
                      ) : null}
                    </span>
                  </li>
                ))}
              </ul>
            )}
          </div>

          {notice && (
            <div
              className="flex items-start gap-2 rounded-[6px] px-3 py-2.5 text-[12px]"
              style={
                notice.tone === 'warn'
                  ? {
                      background: tint('--zaplane-warning', 10),
                      border: `1px solid ${tint('--zaplane-warning', 35)}`,
                      color: 'var(--zaplane-font-color)',
                    }
                  : {
                      background: 'var(--zaplane-background)',
                      border: '1px solid var(--zaplane-border-color)',
                      color: 'var(--zaplane-font-secondary-color)',
                    }
              }
            >
              <span className="flex-1">{notice.text}</span>
              <button
                type="button"
                onClick={() => setNotice(null)}
                className="shrink-0 text-[var(--zaplane-text-muted)]"
                aria-label={__('Dismiss', 'zaplane')}
              >
                &times;
              </button>
            </div>
          )}

          {freshCredential && (
            <div
              className="rounded-[6px] p-4"
              style={{ background: tint('--zaplane-primary', 6), border: `1px solid ${tint('--zaplane-primary', 30)}` }}
            >
              <div className="text-[13px] font-semibold text-[var(--zaplane-font-color)]">
                {__('Copy this now', 'zaplane')}
              </div>
              <p className="mb-2 mt-0.5 text-[12px] text-[var(--zaplane-font-secondary-color)]">
                {__('WordPress shows an application password once. It is not stored here and cannot be shown again — revoke it and make another if you lose it.', 'zaplane')}
              </p>

              <div className="flex flex-col gap-1.5">
                <div className="flex items-center gap-2">
                  <span className="w-[52px] shrink-0 text-[11px] text-[var(--zaplane-text-muted)]">{__('Name', 'zaplane')}</span>
                  <code className="flex-1 rounded-[4px] bg-[var(--zaplane-background)] px-3 py-2 text-[11px] text-[var(--zaplane-font-color)]">
                    Authorization
                  </code>
                  <CopyButton value="Authorization" label={__('Copy', 'zaplane')} />
                </div>
                <div className="flex items-center gap-2">
                  <span className="w-[52px] shrink-0 text-[11px] text-[var(--zaplane-text-muted)]">{__('Value', 'zaplane')}</span>
                  <code className="flex-1 overflow-x-auto whitespace-nowrap rounded-[4px] bg-[var(--zaplane-background)] px-3 py-2 text-[11px] text-[var(--zaplane-font-color)]">
                    {basicHeader(freshCredential)}
                  </code>
                  <CopyButton value={basicHeader(freshCredential)} label={__('Copy', 'zaplane')} />
                </div>
              </div>

              <p className="mt-2 text-[11px] text-[var(--zaplane-text-muted)]">
                {__('Basic, not Bearer — this is a WordPress username and application password, not a token.', 'zaplane')}
              </p>
            </div>
          )}

          {info.app_passwords_available ? (
            <div>
              <div className="mb-1 text-[13px] font-medium text-[var(--zaplane-font-color)]">
                {__('Create a credential to paste', 'zaplane')}
              </div>
              <p className="mb-3 text-[12px] text-[var(--zaplane-font-secondary-color)]">
                {__('For a client that cannot sign in through a browser. WordPress asks you to approve it and hands over the credential — Zaplane never stores one. Zaplane will let it read and build, but never run a workflow for real.', 'zaplane')}
              </p>
              <div className="flex items-center gap-2">
                <input
                  type="text"
                  value={label}
                  onChange={e => setLabel(e.target.value)}
                  placeholder={__('What is it for? e.g. "Claude desktop"', 'zaplane')}
                  className="flex-1 rounded-[4px] border border-[var(--zaplane-border-color)] bg-[var(--zaplane-background)] px-3 py-2 text-[13px] text-[var(--zaplane-font-color)]"
                />
                <button
                  type="button"
                  onClick={startConnect}
                  disabled={connecting}
                  style={PRIMARY}
                  className="shrink-0 rounded-[4px] px-4 py-2 text-[13px] font-semibold disabled:opacity-60"
                >
                  {connecting ? __('Opening…', 'zaplane') : __('Create', 'zaplane')}
                </button>
              </div>
            </div>
          ) : (
            <p className="text-[12px] text-[var(--zaplane-text-muted)]">
              {__('Application passwords are switched off on this site, so there is no pasteable credential to make. Clients that can sign in through a browser still work.', 'zaplane')}
            </p>
          )}

          {/* The trail itself lives on the Logs screen, which has the room for
              it. This is only the pointer, plus enough to know whether looking
              is worth it. */}
          <div className="rounded-[6px] border border-[var(--zaplane-border-color)] p-4">
            <div className="flex items-center justify-between gap-4">
              <div>
                <div className="text-[13px] font-medium text-[var(--zaplane-font-color)]">
                  {__('Activity', 'zaplane')}
                </div>
                <p className="mt-1 text-[12px] text-[var(--zaplane-font-secondary-color)]">
                  {auditCount > 0
                    ? sprintf(
                        /* translators: %d: number of recorded calls. */
                        _n(
                          '%d call recorded — what each client did, and on whose account. Arguments are never recorded.',
                          '%d calls recorded — what each client did, and on whose account. Arguments are never recorded.',
                          auditCount,
                          'zaplane'
                        ),
                        auditCount
                      )
                    : __('Nothing recorded yet. Every tool call a client makes is logged here, arguments excluded.', 'zaplane')}
                </p>
              </div>
              <a
                href={`${route_path}admin.php?page=zaplane-logs`}
                className="shrink-0 rounded-[4px] border border-[var(--zaplane-border-color)] px-3 py-1.5 text-[12px] font-medium text-[var(--zaplane-font-color)] hover:bg-[var(--zaplane-secondary-color)]"
              >
                {__('View activity', 'zaplane')}
              </a>
            </div>

            <label className="mt-3 flex items-start gap-2 border-t border-[var(--zaplane-border-color)] pt-3 text-[12px] text-[var(--zaplane-font-secondary-color)]">
              {/* Reads a value that arrives after the first render, so without
                  the guard it paints unticked and then flips — which on a slow
                  reload looks exactly like a setting that did not save. */}
              <input
                type="checkbox"
                className="mt-[2px]"
                checked={!!info?.alerts}
                disabled={savingAlerts || !info}
                onChange={async e => {
                  const enabled = e.target.checked;
                  setSavingAlerts(true);
                  setInfo(prev => (prev ? { ...prev, alerts: enabled } : prev));
                  try {
                    await API.post(`${namespace}mcp/alerts`, { enabled });
                    await load({ quiet: true });
                  } catch (err) {
                    setInfo(prev => (prev ? { ...prev, alerts: !enabled } : prev));
                    setNotice({ tone: 'warn', text: failureText(err, __('Could not change that setting.', 'zaplane')) });
                  } finally {
                    setSavingAlerts(false);
                  }
                }}
              />
              <span>
                {__('Email me the first time a client runs a workflow for real, and if one is refused repeatedly.', 'zaplane')}
              </span>
            </label>
          </div>

          {clients.length > 0 && (
            <div>
              <div className="mb-1 text-[13px] font-medium text-[var(--zaplane-font-color)]">
                {__('Registered clients', 'zaplane')}
              </div>
              <p className="mb-3 text-[12px] text-[var(--zaplane-text-muted)]">
                {__('Apps that signed themselves up. Registering is not access on its own — approving one is. Removing a client also revokes what it holds.', 'zaplane')}
              </p>
              <div className="flex flex-col divide-y divide-[var(--zaplane-border-color)]">
                {clients.map(c => (
                  <div key={c.client_id} className="flex items-center justify-between gap-4 py-3 first:pt-0">
                    <div className="min-w-0">
                      <div className="truncate text-[14px] font-medium text-[var(--zaplane-font-color)]">{c.client_name}</div>
                      <div className="mt-0.5 text-[12px] text-[var(--zaplane-text-muted)]">
                        {c.token_count > 0
                          ? __('connected', 'zaplane')
                          : __('registered, never approved', 'zaplane')}
                        {c.created_at ? ` · ${c.created_at}` : ''}
                      </div>
                    </div>
                    <button
                      type="button"
                      disabled={removing === c.client_id}
                      onClick={() => removeClient(c)}
                      title={__('Remove', 'zaplane')}
                      className="shrink-0 rounded-[4px] p-2 text-[var(--zaplane-font-secondary-color)] hover:text-[var(--zaplane-danger)] disabled:opacity-50"
                    >
                      <FiTrash2 size={15} />
                    </button>
                  </div>
                ))}
              </div>
            </div>
          )}

          {(info.connections || []).length > 0 && (
            <div>
              <div className="mb-1 text-[13px] font-medium text-[var(--zaplane-font-color)]">
                {__('Pasted credentials', 'zaplane')}
              </div>
              <p className="mb-3 text-[12px] text-[var(--zaplane-text-muted)]">
                {__('WordPress application passwords made here. They are yours — a colleague sees their own, on their profile. Each is a sign-in to your whole account, not only to Zaplane, so revoke any you have stopped using.', 'zaplane')}
              </p>
              <div className="flex flex-col divide-y divide-[var(--zaplane-border-color)]">
                {info.connections.map(c => (
                  <div key={c.uuid} className="flex items-center justify-between gap-4 py-3 first:pt-0">
                    <div className="min-w-0">
                      <div className="truncate text-[14px] font-medium text-[var(--zaplane-font-color)]">
                        {c.name.replace(/^Zaplane\s*–\s*/, '') || c.name}
                      </div>
                      <div className="mt-1 flex flex-wrap items-center gap-1.5">
                        {(c.scopes || []).map(scope => (
                          <ScopePill key={scope} scope={scope} />
                        ))}
                        <span className="text-[12px] text-[var(--zaplane-text-muted)]">
                          {c.last_used
                            ? `${__('last used', 'zaplane')} ${new Date(c.last_used * 1000).toLocaleDateString()}`
                            : __('never used', 'zaplane')}
                        </span>
                      </div>
                    </div>
                    <button
                      type="button"
                      onClick={() => revokeConnection(c.uuid)}
                      title={__('Revoke', 'zaplane')}
                      className="shrink-0 rounded-[4px] p-2 text-[var(--zaplane-font-secondary-color)] hover:text-[var(--zaplane-danger)]"
                    >
                      <FiTrash2 size={15} />
                    </button>
                  </div>
                ))}
              </div>
            </div>
          )}

          <div>
            <div className="mb-1 text-[13px] font-medium text-[var(--zaplane-font-color)]">{__('Signed-in clients', 'zaplane')}</div>
            <p className="mb-3 text-[12px] text-[var(--zaplane-text-muted)]">
              {__('Clients that signed in through the browser. Each acts as the person who approved it.', 'zaplane')}
            </p>
            {tokens.length === 0 ? (
              <p className="text-[13px] text-[var(--zaplane-text-muted)]">{__('No tokens yet.', 'zaplane')}</p>
            ) : (
              <div className="flex flex-col divide-y divide-[var(--zaplane-border-color)]">
                {tokens.map(token => (
                  <div key={token.id} className="flex items-center justify-between gap-4 py-3 first:pt-0">
                    <div className="min-w-0">
                      <div className="truncate text-[14px] font-medium text-[var(--zaplane-font-color)]">{token.name}</div>
                      <div className="mt-1 flex flex-wrap items-center gap-1.5">
                        {token.scopes.map(scope => (
                          <ScopePill key={scope} scope={scope} />
                        ))}
                        {/* A row nobody remembers creating is confusing. Say when
                            it arrived through a connector's own sign-in instead. */}
                        {token.expires_at > 0 ? (
                          <span className="text-[12px] text-[var(--zaplane-text-muted)]">
                            {__('expires', 'zaplane')} {new Date(token.expires_at * 1000).toLocaleDateString()}
                          </span>
                        ) : null}
                        {(token.workflows || []).length > 0 ? (
                          <span className="text-[12px] text-[var(--zaplane-text-muted)]">
                            {sprintf(
                              /* translators: %d: number of workflows the token is limited to. */
                              _n( 'run limited to %d workflow', 'run limited to %d workflows', token.workflows.length, 'zaplane' ),
                              token.workflows.length
                            )}
                          </span>
                        ) : null}
                        {token.client_id ? (
                          <span className="rounded-full bg-[var(--zaplane-second-primary)] px-2 py-0.5 text-[11px] font-medium text-[var(--zaplane-font-secondary-color)]">
                            {__('connected app', 'zaplane')}
                          </span>
                        ) : null}
                        <span className="text-[12px] text-[var(--zaplane-text-muted)]">
                          {token.last_used_at
                            ? `${__('last used', 'zaplane')} ${token.last_used_at}`
                            : __('never used', 'zaplane')}
                        </span>
                      </div>
                    </div>
                    <button
                      type="button"
                      onClick={() => revoke(token.id)}
                      title={__('Revoke', 'zaplane')}
                      className="shrink-0 rounded-[4px] p-2 text-[var(--zaplane-font-secondary-color)] hover:text-[var(--zaplane-danger)]"
                    >
                      <FiTrash2 size={15} />
                    </button>
                  </div>
                ))}
              </div>
            )}
          </div>
        </>
      )}
    </div>
  );
};

export default McpTab;
