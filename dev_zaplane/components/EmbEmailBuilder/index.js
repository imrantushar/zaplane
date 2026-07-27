import React, { useMemo } from 'react';
import { EmailEditor, treeFromHtml } from '@kodezen/editor';

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

    return (
        <EmailEditor
            initialTree={ initialTree }
            mergeTags={ CONTACT_MERGE_TAGS }
            header
            height={ height }
            onChange={ ( { tree, html } ) => onChange?.( { tree, html } ) }
        />
    );
}
