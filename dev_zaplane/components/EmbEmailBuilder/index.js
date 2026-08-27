import React, { useMemo } from 'react';
import { EmailEditor, treeFromHtml } from '@kodezen/editor';
import './styles.scss';

/**
 * Full drag-and-drop email builder (EMB) used on the dedicated Email Templates
 * page. The editor's JSON tree is the single source of truth — persisted as a
 * JSON string in the template's `content` column and rendered to HTML
 * server-side at send time.
 *
 * Contact merge tags ({{contact.*}}) are offered so templates can be
 * personalized; they're left literal by Zaplane's resolver and filled
 * per-recipient by GemCRM's sender.
 */
const CONTACT_MERGE_TAGS = [
    { tag: '{{contact.first_name}}', label: 'First Name' },
    { tag: '{{contact.last_name}}', label: 'Last Name' },
    { tag: '{{contact.email}}', label: 'Email' },
    { tag: '{{contact.phone}}', label: 'Phone' },
    { tag: '{{unsubscribe_link}}', label: 'Unsubscribe Link' },
    { tag: '{{update_preferences_link}}', label: 'Update Preferences Link' },
];

const toInitialTree = ( source ) => {
    if ( ! source ) return undefined;
    if ( typeof source === 'object' && source.root ) return source;
    if ( typeof source === 'string' ) {
        try {
            const tree = JSON.parse( source );
            if ( tree && tree.root ) return tree;
        } catch ( e ) {
            /* not JSON → treat as legacy HTML */
        }
        return treeFromHtml( source );
    }
    return undefined;
};

export default function EmbEmailBuilder( {
    source,
    onChange,
    height = 640,
} ) {
    const initialTree = useMemo( () => toInitialTree( source ), [ source ] );

    // The email builder is a third-party editor. Its shell chrome (header,
    // undo/redo, left panel, navigator, block search/tiles, canvas empty-state
    // + quick-add menu) is theme-aware: `suffix="zaplane"` adds a
    // `.emb-embed-root--zaplane` class the library reads `--emb-*` custom
    // properties from, mapped to the app's live palette (light AND dark) in
    // styles.scss. IMPORTANT: don't wrap this in `.zaplane-force-light` —
    // that pins `--zaplane-*` to their light values unconditionally, which
    // would poison the mapping below for every descendant (including this
    // one), silently forcing the shell light no matter the app's theme. The
    // email-content canvas itself (the white "page" being composed) and its
    // block settings/Inspector panel still hardcode light colors — the email
    // being composed is a light document, so that surface intentionally stays
    // light regardless of app theme (`.emb-canvas-page` pins that in the
    // library, independent of this wrapper).
    return (
        <div
            style={ { background: 'var(--zaplane-background)', color: 'var(--zaplane-font-color)', borderRadius: '8px' } }
        >
            <EmailEditor
                initialTree={ initialTree }
                mergeTags={ CONTACT_MERGE_TAGS }
                header
                height={ height }
                suffix="zaplane"
                onChange={ ( { tree, html } ) => onChange?.( { tree, html } ) }
            />
        </div>
    );
}
