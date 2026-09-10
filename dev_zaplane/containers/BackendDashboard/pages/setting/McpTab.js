import React, { useCallback, useEffect, useState } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { FiCheck, FiCopy, FiTrash2 } from 'react-icons/fi';
import { API, namespace, route_path } from '@ZAPUtils/helper';

// Mirrors TokenStore::ALL_SCOPES. Ordered least to most dangerous.
const SCOPES = [
  {
    key: 'read',
    label: __('Read', 'zaplane'),
    description: __('Browse apps, workflows, runs and connections.', 'zaplane'),
  },
  {
    key: 'write',
    label: __('Build', 'zaplane'),
    description: __('Create and edit workflows. Implies Read.', 'zaplane'),
  },
  {
    key: 'run',
    label: __('Run', 'zaplane'),
    description: __('Start workflows for real — sends mail, takes payments, posts to other services.', 'zaplane'),
  },
];

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

// Tints come from color-mix, not Tailwind's `/opacity` syntax: that needs a real
// colour to work on, and these are CSS variables.
const tint = (token, pct) => `color-mix(in srgb, var(${token}) ${pct}%, transparent)`;

// Tailwind's bg-[var(--…)] loses to a more specific admin rule inside this app,
// and computes transparent — a white label on a white card. Inline wins.
const PRIMARY = { background: 'var(--zaplane-primary)', color: '#fff' };

const SCOPE_LABELS = {
  read: 'Read',
  write: 'Build',
  run: 'Run for real',
};

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
    {scope}
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
  const [runWorkflows, setRunWorkflows] = useState([]);
  const [expiresDays, setExpiresDays] = useState(0);
  const [scopes, setScopes] = useState(['read', 'write']);
  const [issuing, setIssuing] = useState(false);
  // Shown once, immediately after issuing — the server never returns it again.
  const [freshToken, setFreshToken] = useState(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await API.get(`${namespace}mcp/info`);
      setInfo(res.data);
    } catch (e) {
      setInfo(null);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const toggleScope = key =>
    setScopes(current => (current.includes(key) ? current.filter(s => s !== key) : [...current, key]));

  const issue = async () => {
    setIssuing(true);
    try {
      const res = await API.post(`${namespace}mcp/tokens`, {
        name,
        scopes,
        // Only meaningful with run; an empty list means every workflow.
        workflows: scopes.includes('run') ? runWorkflows : [],
        expires_days: expiresDays,
      });
      setFreshToken(res.data);
      setName('');
      setRunWorkflows([]);
      setExpiresDays(0);
      await load();
    } finally {
      setIssuing(false);
    }
  };

  const revoke = async id => {
    // eslint-disable-next-line no-alert
    if (!window.confirm(__('Revoke this token? Any client using it stops working immediately.', 'zaplane'))) return;
    await API.delete(`${namespace}mcp/tokens/${id}`);
    await load();
  };

  const tokens = info?.tokens || [];
  const [checks, setChecks] = useState(null);
  const [checking, setChecking] = useState(false);
  const [auditCount, setAuditCount] = useState(0);
  const [clients, setClients] = useState([]);
  const [removing, setRemoving] = useState(null);
  const [pending, setPending] = useState([]);
  const [grants, setGrants] = useState({});
  const [deciding, setDeciding] = useState(null);

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
      await load();
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
      await load();
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
  const cliFor = token => `claude mcp add --transport http zaplane ${info?.url || ''} --header "Authorization: Bearer ${token}"`;
  const cliCommand = cliFor('YOUR_TOKEN');

  return (
    <div className="flex flex-col gap-8">
      <div className="mb-1">
        <h3 className="text-[15px] font-semibold text-[var(--zaplane-font-color)]">{__('AI access (MCP)', 'zaplane')}</h3>
        <p className="mt-1 text-[13px] text-[var(--zaplane-font-secondary-color)]">
          {__(
            'Point an AI client — Claude, Cursor, or anything that speaks the Model Context Protocol — at the endpoint below and give it a token. It can then read this site\'s automations and build new ones.',
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
                          {SCOPE_LABELS[scope] || scope}
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

          <div className="rounded-[6px] border border-[var(--zaplane-border-color)] p-4">
            <div className="text-[13px] font-medium text-[var(--zaplane-font-color)]">
              {__('Connecting a client', 'zaplane')}
            </div>

            <div className="mt-3 text-[12px] text-[var(--zaplane-font-secondary-color)]">
              <div className="font-semibold text-[var(--zaplane-font-color)]">
                {__('claude.ai, ChatGPT and other hosted connectors', 'zaplane')}
              </div>
              <ol className="mt-1 list-decimal space-y-1 pl-4">
                <li>{__('Paste the endpoint URL above into the connector. Nothing else is needed — no token.', 'zaplane')}</li>
                <li>{__('It sends you back here to approve the request. Sign in as an administrator.', 'zaplane')}</li>
                <li>{__('Choose what to allow, then Approve. The client appears below as a connected app.', 'zaplane')}</li>
              </ol>
            </div>

            <div className="mt-4 text-[12px] text-[var(--zaplane-font-secondary-color)]">
              <div className="font-semibold text-[var(--zaplane-font-color)]">
                {__('Claude Code, Cursor and other clients you run yourself', 'zaplane')}
              </div>
              <ol className="mt-1 list-decimal space-y-1 pl-4">
                <li>{__('Issue a token below and copy it — it is shown once.', 'zaplane')}</li>
                <li>{__('Add the endpoint URL to the client, sending the token as an Authorization header.', 'zaplane')}</li>
              </ol>
              <div className="mt-2 flex items-center gap-2">
                <code className="flex-1 overflow-x-auto whitespace-nowrap rounded-[4px] border border-[var(--zaplane-border-color)] bg-[var(--zaplane-secondary-color)] px-3 py-2 text-[11px] text-[var(--zaplane-font-color)]">
                  {cliCommand}
                </code>
                <CopyButton value={cliCommand} />
              </div>
            </div>

            <div className="mt-4 text-[12px] text-[var(--zaplane-font-secondary-color)]">
              <div className="font-semibold text-[var(--zaplane-font-color)]">
                {__('Or use a WordPress application password', 'zaplane')}
              </div>
              <p className="mt-1">
                {__(
                  'Make one under Users → Profile → Application Passwords and send it as Basic auth. Nothing to issue here, and you revoke it on that same screen. It can read and build, but never run workflows for real.',
                  'zaplane'
                )}
              </p>
            </div>

            <p className="mt-4 text-[12px] text-[var(--zaplane-text-muted)]">
              {__('However a client connects, it can only do what its scopes allow, and revoking it disconnects it immediately.', 'zaplane')}
            </p>
          </div>

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

          {freshToken && (
            <div className="rounded-[6px] border border-[var(--zaplane-primary)] bg-[var(--zaplane-second-primary)] p-4">
              <div className="text-[13px] font-semibold text-[var(--zaplane-font-color)]">
                {__('Copy this token now', 'zaplane')}
              </div>
              <p className="mb-2 mt-0.5 text-[12px] text-[var(--zaplane-font-secondary-color)]">
                {__('It is shown once and cannot be recovered.', 'zaplane')}
              </p>
              <div className="flex items-center gap-2">
                <code className="flex-1 overflow-x-auto whitespace-nowrap rounded-[4px] bg-[var(--zaplane-background)] px-3 py-2 text-[12px] text-[var(--zaplane-font-color)]">
                  {freshToken.token}
                </code>
                <CopyButton value={freshToken.token} label={__('Copy token', 'zaplane')} />
              </div>

              {/* The instructions live at the top of this panel, but the token
                  appears at the bottom — so repeat what to do with it, here,
                  with the real token already in place rather than a placeholder. */}
              <div className="mt-4 text-[12px] text-[var(--zaplane-font-secondary-color)]">
                {__('For Claude Code or Cursor, run this — it has the token in it already:', 'zaplane')}
              </div>
              <div className="mt-1.5 flex items-center gap-2">
                <code className="flex-1 overflow-x-auto whitespace-nowrap rounded-[4px] bg-[var(--zaplane-background)] px-3 py-2 text-[11px] text-[var(--zaplane-font-color)]">
                  {cliFor(freshToken.token)}
                </code>
                <CopyButton value={cliFor(freshToken.token)} label={__('Copy command', 'zaplane')} />
              </div>

              {/* Connector dialogs ask for the name and the value in separate
                  fields, and pasting the bare token into the value — without the
                  scheme word — is the usual way this goes wrong. */}
              <div className="mt-3 text-[12px] text-[var(--zaplane-font-secondary-color)]">
                {__('For anything else, send this header on every request:', 'zaplane')}
              </div>
              <div className="mt-1.5 flex flex-col gap-1.5">
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
                    Bearer {freshToken.token}
                  </code>
                  <CopyButton value={`Bearer ${freshToken.token}`} label={__('Copy', 'zaplane')} />
                </div>
              </div>
              <p className="mt-1.5 text-[11px] text-[var(--zaplane-text-muted)]">
                {__('The word Bearer is part of the value. Pasting the token on its own is refused.', 'zaplane')}
              </p>

              {(freshToken.scopes || []).includes('run') && (
                <p className="mt-3 text-[12px]" style={{ color: 'var(--zaplane-warning)' }}>
                  {__('This token can run workflows for real — sending mail, taking payments. Treat it like a password.', 'zaplane')}
                </p>
              )}
            </div>
          )}

          <div>
            <div className="mb-3 text-[13px] font-medium text-[var(--zaplane-font-color)]">{__('Issue a token', 'zaplane')}</div>
            <input
              type="text"
              value={name}
              onChange={e => setName(e.target.value)}
              placeholder={__('What is it for? e.g. "Claude desktop"', 'zaplane')}
              className="mb-3 h-9 w-full rounded-[4px] border border-[var(--zaplane-border-color)] bg-[var(--zaplane-background)] px-3 text-[13px] text-[var(--zaplane-font-color)]"
            />
            <div className="flex flex-col gap-2">
              {SCOPES.map(scope => (
                <label key={scope.key} className="flex cursor-pointer items-start gap-2.5">
                  <input
                    type="checkbox"
                    checked={scopes.includes(scope.key)}
                    onChange={() => toggleScope(scope.key)}
                    className="mt-0.5"
                  />
                  <span>
                    <span className="text-[13px] font-medium text-[var(--zaplane-font-color)]">{scope.label}</span>
                    <span className="ml-2 text-[12px] text-[var(--zaplane-font-secondary-color)]">{scope.description}</span>
                  </span>
                </label>
              ))}
            </div>
            {/* `run` over every workflow is a bigger grant than most people mean.
                Naming the ones a client is for turns it into a decision. */}
            {scopes.includes('run') && (info?.workflows || []).length > 0 && (
              <div className="mt-3 rounded-[4px] border border-[var(--zaplane-border-color)] p-3">
                <div className="text-[12px] font-medium text-[var(--zaplane-font-color)]">
                  {__('Which workflows may it run?', 'zaplane')}
                </div>
                <p className="mt-0.5 text-[12px] text-[var(--zaplane-text-muted)]">
                  {runWorkflows.length === 0
                    ? __('Choosing none means every workflow, now and in future.', 'zaplane')
                    : sprintf(
                        /* translators: %d: number of chosen workflows. */
                        _n( 'Limited to %d workflow.', 'Limited to %d workflows.', runWorkflows.length, 'zaplane' ),
                        runWorkflows.length
                      )}
                </p>
                <div className="mt-2 flex max-h-[150px] flex-col gap-1.5 overflow-y-auto">
                  {(info?.workflows || []).map(w => (
                    <label key={w.id} className="flex items-center gap-2 text-[12px] text-[var(--zaplane-font-secondary-color)]">
                      <input
                        type="checkbox"
                        checked={runWorkflows.includes(w.id)}
                        onChange={() =>
                          setRunWorkflows(cur =>
                            cur.includes(w.id) ? cur.filter(x => x !== w.id) : [...cur, w.id]
                          )
                        }
                      />
                      <span className="truncate text-[var(--zaplane-font-color)]">{w.title}</span>
                      <span className="text-[var(--zaplane-text-muted)]">#{w.id}</span>
                    </label>
                  ))}
                </div>
              </div>
            )}

            <div className="mt-3 flex items-center gap-2 text-[12px] text-[var(--zaplane-font-secondary-color)]">
              <span>{__('Expires', 'zaplane')}</span>
              <select
                value={expiresDays}
                onChange={e => setExpiresDays(Number(e.target.value))}
                className="rounded-[4px] border border-[var(--zaplane-border-color)] bg-[var(--zaplane-background)] px-2 py-1 text-[12px] text-[var(--zaplane-font-color)]"
              >
                <option value={0}>{__('never', 'zaplane')}</option>
                <option value={30}>{__('in 30 days', 'zaplane')}</option>
                <option value={90}>{__('in 90 days', 'zaplane')}</option>
                <option value={365}>{__('in a year', 'zaplane')}</option>
              </select>
              {expiresDays === 0 && (
                <span className="text-[var(--zaplane-text-muted)]">
                  {__('— a token pasted into a config outlives the reason for it.', 'zaplane')}
                </span>
              )}
            </div>

            <button
              type="button"
              onClick={issue}
              disabled={issuing || scopes.length === 0}
              style={
                issuing || scopes.length === 0
                  ? { background: 'var(--zaplane-secondary-color)', color: 'var(--zaplane-text-muted)' }
                  : PRIMARY
              }
              className={`mt-4 inline-flex items-center rounded-[4px] px-4 py-2 text-[13px] font-semibold transition-colors ${
                issuing || scopes.length === 0 ? 'cursor-not-allowed' : 'cursor-pointer hover:opacity-90'
              }`}
            >
              {issuing ? __('Issuing…', 'zaplane') : __('Issue token', 'zaplane')}
            </button>
          </div>

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
              <input
                type="checkbox"
                className="mt-[2px]"
                checked={!!info?.alerts}
                onChange={async e => {
                  await API.post(`${namespace}mcp/alerts`, { enabled: e.target.checked });
                  await load();
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

          <div>
            <div className="mb-3 text-[13px] font-medium text-[var(--zaplane-font-color)]">{__('Issued tokens', 'zaplane')}</div>
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
