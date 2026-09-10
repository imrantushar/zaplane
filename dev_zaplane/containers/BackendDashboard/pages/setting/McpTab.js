import React, { useCallback, useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { FiCheck, FiCopy, FiTrash2 } from 'react-icons/fi';
import { API, namespace } from '@ZAPUtils/helper';

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
      const res = await API.post(`${namespace}mcp/tokens`, { name, scopes });
      setFreshToken(res.data);
      setName('');
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
  const cliCommand = `claude mcp add --transport http zaplane ${info?.url || ''} --header "Authorization: Bearer YOUR_TOKEN"`;

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

          {freshToken && (
            <div className="rounded-[6px] border border-[var(--zaplane-primary)] bg-[var(--zaplane-second-primary)] p-4">
              <div className="text-[13px] font-semibold text-[var(--zaplane-font-color)]">
                {__('Copy this token now', 'zaplane')}
              </div>
              <p className="mb-2 mt-0.5 text-[12px] text-[var(--zaplane-font-secondary-color)]">
                {__('It is shown once and cannot be recovered. Paste it as the Bearer token in your AI client.', 'zaplane')}
              </p>
              <div className="flex items-center gap-2">
                <code className="flex-1 overflow-x-auto whitespace-nowrap rounded-[4px] bg-[var(--zaplane-background)] px-3 py-2 text-[12px] text-[var(--zaplane-font-color)]">
                  {freshToken.token}
                </code>
                <CopyButton value={freshToken.token} label={__('Copy token', 'zaplane')} />
              </div>
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
            <button
              type="button"
              onClick={issue}
              disabled={issuing || scopes.length === 0}
              className={`mt-4 inline-flex items-center rounded-[4px] px-4 py-2 text-[13px] font-semibold transition-colors ${
                issuing || scopes.length === 0
                  ? 'cursor-not-allowed bg-[var(--zaplane-secondary-color)] text-[var(--zaplane-text-muted)]'
                  : 'cursor-pointer bg-[var(--zaplane-primary)] text-white hover:opacity-90'
              }`}
            >
              {issuing ? __('Issuing…', 'zaplane') : __('Issue token', 'zaplane')}
            </button>
          </div>

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
