import React, { useMemo } from 'react';
import { __ } from '@wordpress/i18n';
import { RichTextEditor } from '@kodezen/email-builder-editor';

/**
 * Simple rich-text field for the GemCRM "Send Email" action body (HTML in /
 * HTML out). Uses the EMB lib's lightweight richtext editor — a Quill-like
 * surface with a persistent toolbar and merge-tag support, NOT the full
 * drag-and-drop builder (that lives on the dedicated Email Templates page).
 *
 * The stored value is an HTML string. Workflow variables ({{trigger.*}}) are
 * interpolated by Zaplane at run time; GemCRM contact tags ({{contact.*}}) are
 * left literal by the resolver and filled per-recipient by GemCRM's sender.
 */

// Flatten Zaplane workflow variables into the editor's [{ tag, label }] shape.
const workflowVarsToMergeTags = ( workflowVariables ) => {
    const tags    = [];
    const context = workflowVariables?.context || {};

    [ 'workflow', 'wp' ].forEach( ( key ) => {
        const group = context[ key ];
        if ( ! group ) return;
        const prefix = group.prefix || key;
        ( group.variables || [] ).forEach( ( v ) => {
            tags.push( {
                tag: `{{${ prefix }.${ v.key }}}`,
                label: `${ group.label || key } · ${ v.label || v.key }`,
            } );
        } );
    } );

    ( workflowVariables?.data || [] ).forEach( ( item ) => {
        const prefix = item.node_id;
        ( item.variables || [] ).forEach( ( v ) => {
            tags.push( {
                tag: `{{${ prefix }.${ v.key }}}`,
                label: `${ item.label || prefix } · ${ v.label || v.key }`,
            } );
        } );
    } );

    return tags;
};

export default function RichTextField( {
    field,
    value,
    setFieldValue,
    workflowVariables,
} ) {
    const mergeTags = useMemo( () => {
        const contactTags = ( field?.merge_tags || [] ).map( ( t ) => ( {
            tag: t.value || t.tag,
            label: t.label,
        } ) );
        return [ ...contactTags, ...workflowVarsToMergeTags( workflowVariables ) ];
    }, [ field?.merge_tags, workflowVariables ] );

    return (
        <div>
            { field?.label && (
                <label className="zaplane-label">
                    { __( field.label, 'zaplane' ) }
                    { field.required && (
                        <span style={ { color: 'red', marginLeft: '2px' } }>*</span>
                    ) }
                </label>
            ) }
            <RichTextEditor
                value={ typeof value === 'string' ? value : '' }
                mergeTags={ mergeTags }
                onChange={ ( { html } ) => setFieldValue( field.key, html ) }
            />
        </div>
    );
}
