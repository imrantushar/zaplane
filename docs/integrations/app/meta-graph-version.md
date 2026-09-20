# Meta Graph API Version

Both Meta integrations — **Facebook Messenger** and **WhatsApp** — call
`graph.facebook.com`, and both ask one place which version to use.

| | |
|---|---|
| **Version Zaplane calls** | `v26.0` (released 29 July 2026) |
| **Set in** | `Zaplane\Framework\Classes\MetaGraph::DEFAULT_VERSION` |
| **Endpoints used** | `/me`, `/me/messages` (Messenger); `/{phone_number_id}`, `/{phone_number_id}/messages` (WhatsApp) |

You do not need to configure anything. The **API Version** field on a connection
is an override for the rare case where Meta asks you to pin a specific version.

---

## Overriding the version

**Per connection** — fill the **API Version** field when editing a connection.
`v25.0` and `25.0` are both accepted; the field is case-insensitive and trimmed.
Leave it blank to follow the default.

Anything that isn't a version string — a typo, a stray path, an empty value —
falls back to the default rather than building a URL Meta would reject.

**Site-wide, in code** — for pinning every Meta call without editing a connection:

```php
add_filter( 'zaplane_meta_graph_version', function ( $version ) {
	return 'v25.0';
} );
```

The filter also receives the connection's own value as its second argument, so a
per-connection pin can still win if you want it to.

---

## How Meta versions work

Meta supports a Graph API version for roughly **two years** from its release,
then serves calls on a newer one. Two things follow from that:

- **Pinning an old version delays a change, it does not exempt you from it.**
  Meta routinely extends a release's restrictions to every supported version
  about 90 days after launch. For v26.0 that date is **27 October 2026**.
- **A stale default eventually stops being honoured.** Zaplane previously called
  `v19.0`, which reached end of life in early 2026.

Upgrading is normally a one-line change to `MetaGraph::DEFAULT_VERSION`, because
the endpoints these integrations use — sending a message, reading the connected
account — are the most stable part of the Graph API and have not changed across
recent releases. Check the [changelog](https://developers.facebook.com/docs/graph-api/changelog/)
for the version you're moving to before bumping it.

---

## Checking which version a connection uses

**Test Connection** on a Messenger or WhatsApp connection calls the Graph API for
real. If the version were wrong, the failure would name the path or version; an
expired token reports `Invalid OAuth access token` (code 190) instead.

---

## Resources

- [Graph API versions and their availability windows](https://developers.facebook.com/docs/graph-api/changelog/versions/)
- [Graph API changelog](https://developers.facebook.com/docs/graph-api/changelog/)
- [Versioning guide](https://developers.facebook.com/docs/graph-api/guides/versioning/)
