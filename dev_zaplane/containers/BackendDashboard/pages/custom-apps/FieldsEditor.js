import React from 'react';
import { __ } from '@wordpress/i18n';
import { TextInput, SelectInput, RepeatableList } from './parts';

// The field types the node config renderer understands.
const FIELD_TYPES = [
	'text',
	'textarea',
	'expression',
	'number',
	'email',
	'url',
	'select',
	'password',
	'json',
	'checkbox',
].map((t) => ({ value: t, label: t }));

/**
 * Edits a list of field-schema entries ({ key, label, type, required }) — the
 * same shape the workflow node config form consumes.
 */
const FieldsEditor = ({ fields = [], onChange }) => (
	<RepeatableList
		items={fields}
		onChange={onChange}
		addLabel={__('Add field', 'zaplane')}
		template={{ key: '', label: '', type: 'text', required: false }}
		renderRow={(field, update) => (
			<div className="grid grid-cols-2 gap-3">
				<TextInput
					label={__('Key', 'zaplane')}
					value={field.key}
					onChange={(v) => update({ key: v })}
					placeholder="title"
				/>
				<TextInput
					label={__('Label', 'zaplane')}
					value={field.label}
					onChange={(v) => update({ label: v })}
					placeholder="Title"
				/>
				<SelectInput
					label={__('Type', 'zaplane')}
					value={field.type}
					onChange={(v) => update({ type: v })}
					options={FIELD_TYPES}
				/>
				<label
					className="mt-6 flex items-center gap-2 text-sm"
					style={{ color: 'var(--zaplane-font-secondary-color)' }}
				>
					<input
						type="checkbox"
						checked={!!field.required}
						onChange={(e) => update({ required: e.target.checked })}
					/>
					{__('Required', 'zaplane')}
				</label>
			</div>
		)}
	/>
);

export default FieldsEditor;
