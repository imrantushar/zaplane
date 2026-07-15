import React, { useEffect, useRef, useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { __ } from '@wordpress/i18n';
import { FiDroplet, FiGrid } from 'react-icons/fi';
import PageLayout from '@ZAPComponents/PageLayout';
import { getSettings, saveSettings } from '@ZAPRedux/Slices/settingSlice/settingSlice';
import { fetchAdminMenuItems } from '@ZAPRedux/Slices/menuSlice/menuSlice';
import { applyThemePalettes, useThemeMode } from '@ZAPUtils/theme';

// The customizable palette variables, in display order (mirrors PHP palette_keys()).
const PALETTE_FIELDS = [
  ['--zaplane-primary', __('Primary', 'zaplane')],
  ['--zaplane-second-primary', __('Primary (soft)', 'zaplane')],
  ['--zaplane-secondary', __('Secondary', 'zaplane')],
  ['--zaplane-secondary-color', __('Surface', 'zaplane')],
  ['--zaplane-background', __('Background', 'zaplane')],
  ['--zaplane-body-background', __('Body background', 'zaplane')],
  ['--zaplane-border-color', __('Border', 'zaplane')],
  ['--zaplane-font-color', __('Text', 'zaplane')],
  ['--zaplane-font-secondary-color', __('Text (secondary)', 'zaplane')],
  ['--zaplane-text-muted', __('Text (muted)', 'zaplane')],
  ['--zaplane-placeholder', __('Placeholder', 'zaplane')],
  ['--zaplane-success', __('Success', 'zaplane')],
  ['--zaplane-warning', __('Warning', 'zaplane')],
  ['--zaplane-danger', __('Danger', 'zaplane')],
  ['--zaplane-gray', __('Gray', 'zaplane')],
];

const FEATURE_FIELDS = [
  {
    key: 'custom_apps',
    title: __('Custom Apps', 'zaplane'),
    description: __('Build and manage your own integration apps. When off, the Custom Apps menu is hidden.', 'zaplane'),
  },
  {
    key: 'knowledge',
    title: __('Business Knowledge', 'zaplane'),
    description: __('Store knowledge sources for the AI agent. When off, the Business Knowledge menu is hidden.', 'zaplane'),
  },
];

const TABS = [
  { key: 'appearance', label: __('Appearance', 'zaplane'), icon: FiDroplet },
  { key: 'modules', label: __('Modules', 'zaplane'), icon: FiGrid },
];

const clone = obj => JSON.parse(JSON.stringify(obj));

const Toggle = ({ checked, onChange }) => (
  <button
    type="button"
    role="switch"
    aria-checked={checked}
    onClick={() => onChange(!checked)}
    // Track color set inline so it beats Tailwind preflight's transparent
    // background-color on [type=button] (which outranks the bg-[...] utility).
    style={{ backgroundColor: checked ? 'var(--zaplane-primary)' : 'var(--zaplane-border-color)' }}
    className="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors"
  >
    <span
      className={`inline-block h-[18px] w-[18px] transform rounded-full bg-white transition-transform ${checked ? 'translate-x-[23px]' : 'translate-x-[3px]'}`}
      style={{ boxShadow: '0 1px 3px rgba(0,0,0,0.4)' }}
    />
  </button>
);

const ColorRow = ({ label, value, onChange }) => (
  <div className="flex items-center justify-between gap-3 py-2">
    <span className="text-[13px] text-[var(--zaplane-font-color)]">{label}</span>
    <div className="flex items-center gap-2">
      <input
        type="text"
        value={value || ''}
        onChange={e => onChange(e.target.value)}
        className="h-8 w-[92px] rounded-[4px] border border-[var(--zaplane-border-color)] bg-[var(--zaplane-background)] px-2 text-[12px] uppercase text-[var(--zaplane-font-color)]"
      />
      <label className="relative h-8 w-8 shrink-0 overflow-hidden rounded-[4px] border border-[var(--zaplane-border-color)]" style={{ background: value }}>
        <input
          type="color"
          value={/^#[0-9a-fA-F]{6}$/.test(value || '') ? value : '#000000'}
          onChange={e => onChange(e.target.value.toUpperCase())}
          className="absolute inset-0 h-full w-full cursor-pointer opacity-0"
        />
      </label>
    </div>
  </div>
);

const SectionTitle = ({ title, description }) => (
  <div className="mb-4">
    <h3 className="text-[15px] font-semibold text-[var(--zaplane-font-color)]">{title}</h3>
    {description && <p className="mt-1 text-[13px] text-[var(--zaplane-font-secondary-color)]">{description}</p>}
  </div>
);

const AppearanceTab = ({ form, activeMode, setActiveMode, setDefaultMode, paletteTab, setPaletteTab, setColor }) => {
  const modes = [
    { value: 'light', label: __('Light', 'zaplane') },
    { value: 'dark', label: __('Dark', 'zaplane') },
  ];
  return (
    <div className="flex flex-col gap-8">
      <div>
        <SectionTitle title={__('Theme', 'zaplane')} description={__('Choose the default mode. Anyone can switch it from the topbar.', 'zaplane')} />
        <div className="flex flex-wrap items-center gap-6">
          <div>
            <div className="mb-2 text-[12px] font-medium text-[var(--zaplane-font-secondary-color)]">{__('Default mode', 'zaplane')}</div>
            <div className="inline-flex rounded-[6px] border border-[var(--zaplane-border-color)] p-1">
              {modes.map(m => (
                <button
                  key={m.value}
                  onClick={() => setDefaultMode(m.value)}
                  className={`px-4 py-1.5 rounded-[4px] text-[13px] font-medium transition-colors ${form.theme.default_mode === m.value ? 'bg-[var(--zaplane-primary)] text-white' : 'text-[var(--zaplane-font-secondary-color)]'}`}
                >
                  {m.label}
                </button>
              ))}
            </div>
          </div>
          <div>
            <div className="mb-2 text-[12px] font-medium text-[var(--zaplane-font-secondary-color)]">{__('Preview', 'zaplane')}</div>
            <div className="inline-flex gap-2">
              {modes.map(m => (
                <button
                  key={m.value}
                  onClick={() => setActiveMode(m.value)}
                  className={`px-3 py-1.5 rounded-full text-[12px] font-medium border ${activeMode === m.value ? 'border-[var(--zaplane-primary)] text-[var(--zaplane-primary)]' : 'border-[var(--zaplane-border-color)] text-[var(--zaplane-font-secondary-color)]'}`}
                >
                  {m.label}
                </button>
              ))}
            </div>
          </div>
        </div>
      </div>

      <div>
        <SectionTitle title={__('Colors', 'zaplane')} description={__('Customize the palette for light and dark mode. Changes preview instantly.', 'zaplane')} />
        <div className="mb-4 inline-flex rounded-[6px] border border-[var(--zaplane-border-color)] p-1">
          {['light', 'dark'].map(variant => (
            <button
              key={variant}
              onClick={() => setPaletteTab(variant)}
              className={`px-4 py-1.5 rounded-[4px] text-[13px] font-medium transition-colors ${paletteTab === variant ? 'bg-[var(--zaplane-primary)] text-white' : 'text-[var(--zaplane-font-secondary-color)]'}`}
            >
              {variant === 'light' ? __('Light', 'zaplane') : __('Dark', 'zaplane')}
            </button>
          ))}
        </div>
        <div className="grid grid-cols-1 gap-x-10 md:grid-cols-2">
          {PALETTE_FIELDS.map(([varName, label]) => (
            <ColorRow
              key={varName}
              label={label}
              value={form.theme?.[paletteTab]?.[varName]}
              onChange={val => setColor(paletteTab, varName, val)}
            />
          ))}
        </div>
      </div>
    </div>
  );
};

const ModulesTab = ({ form, setFeature }) => (
  <div>
    <SectionTitle title={__('Modules', 'zaplane')} description={__('Turn optional features on or off. Disabled modules are removed from the menu.', 'zaplane')} />
    <div className="flex flex-col divide-y divide-[var(--zaplane-border-color)]">
      {FEATURE_FIELDS.map(f => (
        <div key={f.key} className="flex items-center justify-between gap-4 py-4 first:pt-0">
          <div>
            <div className="text-[14px] font-medium text-[var(--zaplane-font-color)]">{f.title}</div>
            <div className="mt-0.5 text-[13px] text-[var(--zaplane-font-secondary-color)]">{f.description}</div>
          </div>
          <Toggle checked={!!form.features?.[f.key]} onChange={val => setFeature(f.key, val)} />
        </div>
      ))}
    </div>
  </div>
);

const Setting = () => {
  const dispatch = useDispatch();
  const { data, saving } = useSelector(state => state.setting);
  const { mode: activeMode, set: setActiveMode } = useThemeMode();
  const [form, setForm] = useState(data ? clone(data) : null);
  const [activeTab, setActiveTab] = useState('appearance');
  const [paletteTab, setPaletteTab] = useState('light');
  const savedRef = useRef(data);

  useEffect(() => {
    dispatch(getSettings());
  }, [dispatch]);

  useEffect(() => {
    if (data) {
      setForm(clone(data));
      savedRef.current = data;
    }
  }, [data]);

  // Live preview while editing; revert unsaved edits on unmount.
  useEffect(() => {
    if (form?.theme) applyThemePalettes(form.theme);
  }, [form?.theme]);

  useEffect(() => () => {
    if (savedRef.current?.theme) applyThemePalettes(savedRef.current.theme);
  }, []);

  const setFeature = (key, val) => setForm(f => ({ ...f, features: { ...f.features, [key]: val } }));
  const setDefaultMode = mode => setForm(f => ({ ...f, theme: { ...f.theme, default_mode: mode } }));
  const setColor = (variant, varName, value) =>
    setForm(f => ({ ...f, theme: { ...f.theme, [variant]: { ...f.theme[variant], [varName]: value } } }));

  const handleSave = async () => {
    if (!form) return;
    const res = await dispatch(saveSettings(form));
    if (!res?.error) {
      savedRef.current = res.payload;
      if (window.ZaplaneGlobal) window.ZaplaneGlobal.settings = res.payload;
      applyThemePalettes(res.payload.theme);
      // Refresh the sidebar so toggled modules appear/disappear without a reload.
      dispatch(fetchAdminMenuItems());
    }
  };

  // Only surface the branded, enabled save button when there are unsaved edits.
  const isDirty = !!form && !!data && JSON.stringify(form) !== JSON.stringify(data);
  const canSave = isDirty && !saving;

  const saveButton = (
    <button
      onClick={handleSave}
      disabled={!canSave}
      className={`inline-flex items-center gap-2 rounded-[4px] px-4 py-2 text-[13px] font-semibold transition-colors ${
        canSave
          ? 'bg-[var(--zaplane-primary)] text-white hover:opacity-90 cursor-pointer'
          : 'bg-[var(--zaplane-secondary-color)] text-[var(--zaplane-text-muted)] cursor-not-allowed'
      }`}
    >
      {saving ? __('Saving…', 'zaplane') : __('Save changes', 'zaplane')}
    </button>
  );

  return (
    <PageLayout title={__('Settings', 'zaplane')} heading={__('Settings', 'zaplane')} actions={saveButton}>
      {form && (
        <div className="flex flex-col gap-6 md:flex-row">
          {/* Left sidebar tabs */}
          <nav className="w-full shrink-0 rounded-lg border border-[var(--zaplane-border-color)] bg-[var(--zaplane-background)] p-2 md:w-[220px]">
            <div className="flex flex-row gap-1 md:flex-col">
              {TABS.map(tab => {
                const Icon = tab.icon;
                const active = activeTab === tab.key;
                return (
                  <button
                    key={tab.key}
                    onClick={() => setActiveTab(tab.key)}
                    className={`flex items-center gap-2.5 rounded-[6px] px-3 py-2.5 text-left text-[14px] font-medium transition-colors ${active ? 'bg-[var(--zaplane-second-primary)] text-[var(--zaplane-primary)]' : 'text-[var(--zaplane-font-secondary-color)] hover:bg-[var(--zaplane-secondary-color)]'}`}
                  >
                    <Icon size={16} />
                    {tab.label}
                  </button>
                );
              })}
            </div>
          </nav>

          {/* Content */}
          <div className="max-w-[820px] flex-1 rounded-lg border border-[var(--zaplane-border-color)] bg-[var(--zaplane-background)] p-6">
            {activeTab === 'appearance' ? (
              <AppearanceTab
                form={form}
                activeMode={activeMode}
                setActiveMode={setActiveMode}
                setDefaultMode={setDefaultMode}
                paletteTab={paletteTab}
                setPaletteTab={setPaletteTab}
                setColor={setColor}
              />
            ) : (
              <ModulesTab form={form} setFeature={setFeature} />
            )}
          </div>
        </div>
      )}
    </PageLayout>
  );
};

export default Setting;
