import React, { useMemo, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import CopyButton from './CopyButton';

/**
 * Installing this site into a particular AI client.
 *
 * The generic version of this screen — "paste the URL, or send a bearer token" —
 * is true for every client and useful for none. What someone actually needs is
 * the file their client reads, at the path it reads it from, with this site's
 * address and their own credential already in it.
 *
 * So this writes that file. Where a client signs in through the browser the
 * config carries no secret and there is nothing to protect; where it cannot, the
 * credential made on this screen is dropped straight in, which is the step
 * people otherwise do by hand and get wrong.
 */

// The name the server appears under inside the client. Short, because the model
// sees it on every tool call.
const KEY = 'zaplane';

// claude.ai accepts the connector name and URL as query parameters and opens
// its add-connector dialog with both filled in.
const hostedDeepLink = (name, url) =>
  `https://claude.ai/customize/connectors?modal=add-custom-connector&connectorName=${encodeURIComponent(name)}&connectorUrl=${encodeURIComponent(url)}`;

const OSES = [
  { id: 'mac', name: __('macOS', 'zaplane') },
  { id: 'windows', name: __('Windows', 'zaplane') },
  { id: 'linux', name: __('Linux', 'zaplane') },
];

const guessOs = () => {
  const ua = (typeof navigator === 'undefined' ? '' : navigator.userAgent) || '';
  if (/Windows/i.test(ua)) return 'windows';
  if (/Mac OS X|Macintosh/i.test(ua)) return 'mac';
  return 'linux';
};

const pretty = value => JSON.stringify(value, null, 2);

/**
 * A desktop client that speaks stdio reaches an HTTP server through a bridge.
 *
 * The header goes through the environment rather than straight into args: a
 * value with a space in it does not survive being split back apart, and every
 * Basic credential has one.
 */
const bridgeConfig = (url, header) => ({
  mcpServers: {
    [KEY]: header
      ? {
          command: 'npx',
          args: ['-y', 'mcp-remote', url, '--header', 'Authorization:${ZAPLANE_AUTH}'],
          env: { ZAPLANE_AUTH: header },
        }
      : {
          command: 'npx',
          args: ['-y', 'mcp-remote', url],
        },
  },
});

/** A client that speaks HTTP itself needs no bridge. */
const httpConfig = (url, header) => ({
  mcpServers: {
    [KEY]: header ? { type: 'http', url, headers: { Authorization: header } } : { type: 'http', url },
  },
});

const CLIENTS = ({ url, header, reachable }) => [
  {
    id: 'claude-desktop',
    name: 'Claude Desktop',
    needsCredential: true,
    file: {
      name: 'claude_desktop_config.json',
      paths: {
        mac: '~/Library/Application Support/Claude/claude_desktop_config.json',
        windows: '%APPDATA%\\Claude\\claude_desktop_config.json',
        linux: '~/.config/Claude/claude_desktop_config.json',
      },
      body: pretty(bridgeConfig(url, header)),
    },
    steps: [
      __('Quit Claude Desktop before editing the file — it reads the config at launch.', 'zaplane'),
      __('Put the file below at the path shown. If one is already there, merge the mcpServers entry into it rather than replacing the file.', 'zaplane'),
      __('Start Claude Desktop. Zaplane appears under the tools icon.', 'zaplane'),
    ],
    note: __('The bridge runs through npx, so Node has to be installed on this machine. Nothing is sent anywhere except this site.', 'zaplane'),
  },
  {
    id: 'claude-code',
    name: 'Claude Code',
    snippet: {
      label: __('Run this in your terminal', 'zaplane'),
      code: header
        ? `claude mcp add --transport http ${KEY} ${url} --header "Authorization: ${header}"`
        : `claude mcp add --transport http ${KEY} ${url}`,
    },
    steps: header
      ? [
          __('Run the command below. It carries the credential, so there is no sign-in step.', 'zaplane'),
          __('Ask it to list your workflows to check it took.', 'zaplane'),
        ]
      : [
          __('Run the command below. No credential — it signs in on its own.', 'zaplane'),
          __('Use any Zaplane tool. Your browser opens once, to approve access.', 'zaplane'),
        ],
    note: header
      ? null
      : __('It will report "failed to connect" until you have approved it. That is expected: it authenticates on first use, not when you add it.', 'zaplane'),
  },
  {
    id: 'cursor',
    name: 'Cursor',
    file: {
      name: 'mcp.json',
      paths: {
        mac: '~/.cursor/mcp.json',
        windows: '%USERPROFILE%\\.cursor\\mcp.json',
        linux: '~/.cursor/mcp.json',
      },
      body: pretty(httpConfig(url, header)),
    },
    steps: [
      __('Put the file below at the path shown, or paste it into Settings → MCP → Add new server.', 'zaplane'),
      __('Cursor picks it up without a restart.', 'zaplane'),
      header
        ? __('No approval window — the credential is in the file.', 'zaplane')
        : __('Approve access in the browser window it opens.', 'zaplane'),
    ],
  },
  {
    id: 'claude-ai',
    name: 'Claude.ai',
    hosted: true,
    oneClick: {
      href: hostedDeepLink('Zaplane', url),
      label: __('Connect to Claude', 'zaplane'),
      hint: __('Claude opens with the name and URL already filled in. Click Add, then approve access when it sends you back here.', 'zaplane'),
    },
    steps: [
      __('Open claude.ai → Settings → Connectors → Add custom connector.', 'zaplane'),
      __('Paste the endpoint URL and save.', 'zaplane'),
      __('Claude opens a window to this site. Sign in as an administrator and approve.', 'zaplane'),
    ],
    unreachable: __('This one runs on somebody else’s servers, so it has to reach this address from the internet — and it cannot. Nothing below will connect until the site is published.', 'zaplane'),
  },
  {
    id: 'chatgpt',
    name: 'ChatGPT',
    hosted: true,
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
    unreachable: __('This one runs on somebody else’s servers, so it has to reach this address from the internet — and it cannot. Nothing below will connect until the site is published.', 'zaplane'),
  },
  {
    id: 'other',
    name: __('Other', 'zaplane'),
    snippet: {
      label: __('Most clients take a configuration like this', 'zaplane'),
      code: pretty(httpConfig(url, header || 'Bearer YOUR_TOKEN')),
    },
    steps: [
      __('Give the client the endpoint URL below.', 'zaplane'),
      __('If it can sign in through a browser, it needs nothing else.', 'zaplane'),
      __('If it cannot, make a credential above and it appears in the configuration.', 'zaplane'),
    ],
  },
];

const download = (filename, body) => {
  const blob = new Blob([body], { type: 'application/json' });
  const href = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = href;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(href);
};

const Block = ({ label, code, children }) => (
  <>
    <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
      <div className="text-[12px] text-[var(--zaplane-font-secondary-color)]">{label}</div>
      <div className="flex items-center gap-1.5">
        {children}
        <CopyButton value={code} label={__('Copy', 'zaplane')} />
      </div>
    </div>
    <pre className="m-0 mt-1.5 overflow-x-auto rounded-[4px] border border-[var(--zaplane-border-color)] bg-[var(--zaplane-secondary-color)] px-3 py-2 text-[11px] text-[var(--zaplane-font-color)]">
      {code}
    </pre>
  </>
);

const ConnectClient = ({ url, header, reachable = true, canCreate = false, onCreate = null, creating = false }) => {
  const [active, setActive] = useState('claude-desktop');
  const [os, setOs] = useState(guessOs);

  const clients = useMemo(() => CLIENTS({ url: url || '', header, reachable }), [url, header, reachable]);
  const client = clients.find(c => c.id === active) || clients[0];

  const blocked = client.hosted && !reachable;

  return (
    <div className="rounded-[6px] border border-[var(--zaplane-border-color)] p-4">
      <div className="text-[13px] font-medium text-[var(--zaplane-font-color)]">
        {__('Install this site into a client', 'zaplane')}
      </div>
      <p className="mt-1 text-[12px] text-[var(--zaplane-font-secondary-color)]">
        {header
          ? __('The configuration below has this site’s address and your new credential already in it. Save it where your client reads it.', 'zaplane')
          : __('Pick your client for the file it reads and where to put it. Signing in through the browser is the better route where the client supports it — nothing to copy, and you can revoke it here later.', 'zaplane')}
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
        {blocked && (
          <p
            className="mb-3 rounded-[4px] px-3 py-2 text-[12px]"
            style={{
              background: 'color-mix(in srgb, var(--zaplane-warning) 10%, transparent)',
              color: 'var(--zaplane-font-color)',
            }}
          >
            {client.unreachable}
          </p>
        )}

        {/* A file that should carry a credential and doesn't is the moment to
            offer one, rather than sending someone to another part of the page
            and back. */}
        {client.needsCredential && !header && canCreate && (
          <div className="mb-3 flex flex-wrap items-center gap-2 rounded-[4px] border border-dashed border-[var(--zaplane-border-color)] px-3 py-2.5">
            <span className="flex-1 text-[12px] text-[var(--zaplane-font-secondary-color)]">
              {__('This client cannot sign in through a browser, so it needs a credential in the file.', 'zaplane')}
            </span>
            <button
              type="button"
              onClick={() => onCreate(client.name)}
              disabled={creating}
              style={{ background: 'var(--zaplane-primary)', color: '#fff' }}
              className="shrink-0 rounded-[4px] px-3 py-1.5 text-[12px] font-semibold disabled:opacity-60"
            >
              {creating ? __('Opening…', 'zaplane') : __('Make one now', 'zaplane')}
            </button>
          </div>
        )}

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

        {client.file && (
          <>
            <div className="mt-3 flex flex-wrap items-center gap-2">
              <span className="text-[12px] text-[var(--zaplane-font-secondary-color)]">{__('Path on', 'zaplane')}</span>
              {OSES.map(o => (
                <button
                  key={o.id}
                  type="button"
                  onClick={() => setOs(o.id)}
                  className={`rounded-full border border-solid px-2.5 py-0.5 text-[11px] ${
                    o.id === os
                      ? 'cursor-default border-[var(--zaplane-primary)] text-[var(--zaplane-primary)]'
                      : 'cursor-pointer border-[var(--zaplane-border-color)] bg-transparent text-[var(--zaplane-font-secondary-color)]'
                  }`}
                >
                  {o.name}
                </button>
              ))}
            </div>
            <div className="mt-1.5 flex items-center gap-2">
              <code className="flex-1 overflow-x-auto whitespace-nowrap rounded-[4px] bg-[var(--zaplane-background)] px-3 py-2 text-[11px] text-[var(--zaplane-font-color)]">
                {client.file.paths[os]}
              </code>
              <CopyButton value={client.file.paths[os]} label={__('Copy', 'zaplane')} />
            </div>

            <Block
              label={sprintf(
                /* translators: %s: a configuration file name. */
                __('Contents of %s', 'zaplane'),
                client.file.name
              )}
              code={client.file.body}
            >
              <button
                type="button"
                onClick={() => download(client.file.name, client.file.body)}
                className="cursor-pointer rounded-[4px] border border-solid border-[var(--zaplane-border-color)] bg-transparent px-2 py-1 text-[11px] text-[var(--zaplane-font-secondary-color)]"
              >
                {__('Download', 'zaplane')}
              </button>
            </Block>
          </>
        )}

        {client.snippet && <Block label={client.snippet.label} code={client.snippet.code} />}

        {client.note && <p className="mt-2 text-[12px] text-[var(--zaplane-text-muted)]">{client.note}</p>}
      </div>
    </div>
  );
};

export default ConnectClient;
