import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import CopyButton from './CopyButton';

/**
 * How to point a particular AI client at this site.
 *
 * The generic version of this screen — "paste the URL, or send a bearer token" —
 * is true for every client and useful for none. What someone actually needs is
 * the three steps for the one client in front of them, and where possible a link
 * that fills the form in for them.
 */

// claude.ai accepts the connector name and URL as query parameters and opens
// its add-connector dialog with both filled in.
const claudeDeepLink = (name, url) =>
  `https://claude.ai/customize/connectors?modal=add-custom-connector&connectorName=${encodeURIComponent(name)}&connectorUrl=${encodeURIComponent(url)}`;

const configJson = url =>
  JSON.stringify({ mcpServers: { zaplane: { type: 'http', url } } }, null, 2);

const configJsonWithToken = url =>
  JSON.stringify(
    { mcpServers: { zaplane: { type: 'http', url, headers: { Authorization: 'Bearer YOUR_TOKEN' } } } },
    null,
    2
  );

const CLIENTS = url => [
  {
    id: 'claude-ai',
    name: 'Claude.ai',
    oneClick: {
      href: claudeDeepLink('Zaplane', url),
      label: __('Connect to Claude', 'zaplane'),
      hint: __('Claude opens with the name and URL already filled in. Click Add, then approve access when it sends you back here.', 'zaplane'),
    },
    steps: [
      __('Open claude.ai → Settings → Connectors → Add custom connector.', 'zaplane'),
      __('Paste the endpoint URL and save.', 'zaplane'),
      __('Claude opens a window to this site. Sign in as an administrator and approve.', 'zaplane'),
    ],
  },
  {
    id: 'chatgpt',
    name: 'ChatGPT',
    oneClick: {
      href: 'https://chatgpt.com/plugins#settings/Connectors?create-connector=true',
      label: __('Open ChatGPT connectors', 'zaplane'),
      hint: __('ChatGPT cannot be given the URL in advance, so keep this page open to copy it.', 'zaplane'),
    },
    steps: [
      __('Open ChatGPT → Settings → Connectors → Create.', 'zaplane'),
      __('Paste the endpoint URL as the MCP server URL and save.', 'zaplane'),
      __('Approve access in the window that opens, signed in as an administrator.', 'zaplane'),
    ],
  },
  {
    id: 'claude-code',
    name: 'Claude Code',
    snippet: { label: __('Run this in your terminal', 'zaplane'), code: `claude mcp add --transport http zaplane ${url}` },
    steps: [
      __('Run the command below. No token — it signs in on its own.', 'zaplane'),
      __('Use any Zaplane tool. Your browser opens once, to approve access.', 'zaplane'),
    ],
    note: __('It will report "failed to connect" until you have approved it. That is expected: it authenticates on first use, not when you add it.', 'zaplane'),
  },
  {
    id: 'cursor',
    name: 'Cursor',
    snippet: { label: __('Add to ~/.cursor/mcp.json', 'zaplane'), code: configJson(url) },
    steps: [
      __('Open Cursor → Settings → MCP → Add new server.', 'zaplane'),
      __('Paste the configuration below.', 'zaplane'),
      __('Approve access in the browser window it opens.', 'zaplane'),
    ],
  },
  {
    id: 'other',
    name: __('Other', 'zaplane'),
    snippet: { label: __('Most clients take a configuration like this', 'zaplane'), code: configJsonWithToken(url) },
    steps: [
      __('Give the client the endpoint URL below.', 'zaplane'),
      __('If it can sign in through a browser, it needs nothing else.', 'zaplane'),
      __('If it cannot, issue a token below and send it as an Authorization header.', 'zaplane'),
    ],
  },
];

const ConnectClient = ({ url }) => {
  const clients = CLIENTS(url || '');
  const [active, setActive] = useState(clients[0].id);
  const client = clients.find(c => c.id === active) || clients[0];

  return (
    <div className="rounded-[6px] border border-[var(--zaplane-border-color)] p-4">
      <div className="text-[13px] font-medium text-[var(--zaplane-font-color)]">
        {__('Connect an AI client', 'zaplane')}
      </div>
      <p className="mt-1 text-[12px] text-[var(--zaplane-font-secondary-color)]">
        {__('Pick your client for its exact steps. Signing in through the browser is the better route where the client supports it — nothing to copy, and you can revoke it here later.', 'zaplane')}
      </p>

      <div className="mt-3 flex flex-wrap gap-1 border-b border-[var(--zaplane-border-color)]">
        {clients.map(c => (
          <button
            key={c.id}
            type="button"
            onClick={() => setActive(c.id)}
            className={`-mb-[1px] border-0 border-b-2 border-solid bg-transparent px-2.5 pb-2 text-[12px] font-medium ${
              c.id === active
                ? 'cursor-default border-[var(--zaplane-primary)] text-[var(--zaplane-primary)]'
                : 'cursor-pointer border-transparent text-[var(--zaplane-font-secondary-color)] hover:text-[var(--zaplane-font-color)]'
            }`}
          >
            {c.name}
          </button>
        ))}
      </div>

      <div className="mt-3">
        {client.oneClick && (
          <>
            <a
              href={client.oneClick.href}
              target="_blank"
              rel="noreferrer noopener"
              style={{ background: 'var(--zaplane-primary)', color: '#fff' }}
              className="inline-flex items-center rounded-[4px] px-3 py-1.5 text-[12px] font-semibold no-underline"
            >
              {client.oneClick.label} ↗
            </a>
            <p className="mt-1.5 text-[12px] text-[var(--zaplane-text-muted)]">{client.oneClick.hint}</p>
            <div className="mt-3 text-[12px] font-medium text-[var(--zaplane-font-color)]">
              {__('Or set it up by hand', 'zaplane')}
            </div>
          </>
        )}

        <ol className="mt-1 list-decimal space-y-1 pl-4 text-[12px] text-[var(--zaplane-font-secondary-color)]">
          {client.steps.map(s => (
            <li key={s}>{s}</li>
          ))}
        </ol>

        {client.snippet && (
          <>
            <div className="mt-3 text-[12px] text-[var(--zaplane-font-secondary-color)]">{client.snippet.label}</div>
            <div className="mt-1.5 flex items-start gap-2">
              <pre className="m-0 flex-1 overflow-x-auto rounded-[4px] border border-[var(--zaplane-border-color)] bg-[var(--zaplane-secondary-color)] px-3 py-2 text-[11px] text-[var(--zaplane-font-color)]">
                {client.snippet.code}
              </pre>
              <CopyButton value={client.snippet.code} label={__('Copy', 'zaplane')} />
            </div>
          </>
        )}

        {client.note && <p className="mt-2 text-[12px] text-[var(--zaplane-text-muted)]">{client.note}</p>}

      </div>
    </div>
  );
};

export default ConnectClient;
