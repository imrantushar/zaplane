import React, { useEffect, useRef, useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { __ } from '@wordpress/i18n';
import { FiCpu, FiDroplet, FiGrid } from 'react-icons/fi';
import PageLayout from '@ZAPComponents/PageLayout';
import Toggle from '@ZAPComponents/ZAPToggle';
import { getSettings, saveSettings } from '@ZAPRedux/Slices/settingSlice/settingSlice';
import { fetchAdminMenuItems } from '@ZAPRedux/Slices/menuSlice/menuSlice';
import { applyThemePalettes, useThemeMode } from '@ZAPUtils/theme';
import McpTab from './McpTab';
import { API, namespace, useQuery } from '@ZAPUtils/helper';

// Palette rows and defaults come from the server — see GET /zaplane/v1/palette.
// They used to be duplicated here by hand and had drifted from the PHP, so the
// editor was missing tokens and "Reset to default" wrote stale colours.
const BASE_TABS = [
  { key: 'appearance', label: __('Appearance', 'zaplane'), icon: FiDroplet },
  { key: 'modules', label: __('Modules', 'zaplane'), icon: FiGrid },
];

// A module's settings panel is a page like any other page a module owns: it only
// exists while the module is on, the same rule that hides its admin menu entry.
const PANEL_ICONS = { mcp: FiCpu };

const panelTabs = (modules, features) =>
  modules
    .filter(m => m.panel && !!features?.[m.key])
    .map(m => ({
      key: m.panel,
      label: m.panel_label || m.title,
      icon: PANEL_ICONS[m.panel] || FiCpu,
    }));

const clone = obj => JSON.parse(JSON.stringify(obj));

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

const AppearanceTab = ({ form, paletteFields, paletteTab, setPaletteTab, setActiveMode, setColor, resetPalette }) => {
  const modes = [
    { value: 'light', label: __('Light', 'zaplane') },
    { value: 'dark', label: __('Dark', 'zaplane') },
  ];
  return (
    <div className="flex flex-col gap-8">
      <div>
        <SectionTitle title={__('Colors', 'zaplane')} description={__('Pick a mode to edit its palette — the app previews it live as you change colors.', 'zaplane')} />
        <div className="mb-4 flex items-center justify-between gap-3">
          <div className="inline-flex rounded-[6px] border border-[var(--zaplane-border-color)] p-1">
            {['light', 'dark'].map(variant => (
              <button
                key={variant}
                onClick={() => { setPaletteTab(variant); setActiveMode(variant); }}
                className={`px-4 py-1.5 rounded-[4px] text-[13px] font-medium transition-colors ${paletteTab === variant ? 'bg-[var(--zaplane-primary)] text-white' : 'text-[var(--zaplane-font-secondary-color)]'}`}
              >
                {variant === 'light' ? __('Light', 'zaplane') : __('Dark', 'zaplane')}
              </button>
            ))}
          </div>
          <button
            type="button"
            onClick={() => resetPalette(paletteTab)}
            className="text-[13px] font-medium text-[var(--zaplane-font-secondary-color)] hover:text-[var(--zaplane-primary)] hover:underline"
          >
            {__('Reset to default', 'zaplane')}
          </button>
        </div>
        <div className="grid grid-cols-1 gap-x-10 md:grid-cols-2">
          {paletteFields.map(field => (
            <ColorRow
              key={field.key}
              label={field.label}
              value={form.theme?.[paletteTab]?.[field.key]}
              onChange={val => setColor(paletteTab, field.key, val)}
            />
          ))}
        </div>
      </div>
    </div>
  );
};

const ModulesTab = ({ form, modules, setFeature, setActiveTab }) => (
  <div>
    <SectionTitle title={__('Modules', 'zaplane')} description={__('Turn optional features on or off. Disabled modules are removed from the menu.', 'zaplane')} />
    <div className="flex flex-col divide-y divide-[var(--zaplane-border-color)]">
      {modules.map(module => {
        const on = !!form.features?.[module.key];
        return (
          <div key={module.key} className="flex items-center justify-between gap-4 py-4 first:pt-0">
            <div>
              <div className="text-[14px] font-medium text-[var(--zaplane-font-color)]">{module.title}</div>
              <div className="mt-0.5 text-[13px] text-[var(--zaplane-font-secondary-color)]">{module.description}</div>
              {/* A module with its own settings panel links straight to it, but
                  only once it is on — there is nothing to configure otherwise. */}
              {module.panel && on && (
                <button
                  type="button"
                  onClick={() => setActiveTab(module.panel)}
                  className="mt-1.5 cursor-pointer border-0 bg-transparent p-0 text-[13px] font-semibold text-[var(--zaplane-primary)] hover:underline"
                >
                  {__('Configure', 'zaplane')} →
                </button>
              )}
            </div>
            <Toggle checked={on} onChange={val => setFeature(module.key, val)} />
          </div>
        );
      })}
    </div>
  </div>
);

const Setting = () => {
  const dispatch = useDispatch();
  const { data, saving } = useSelector(state => state.setting);
  const { mode: activeMode, set: setActiveMode } = useThemeMode();
  const [form, setForm] = useState(data ? clone(data) : null);
  const query = useQuery();
  // A teaser links here with ?tab=modules, and core's application-password
  // screen returns with ?tab=mcp. Either can arrive on the admin URL rather
  // than the router's, so read both.
  const requestedTab =
    query.get('tab') || new URLSearchParams(window.location.search).get('tab');
  const [activeTab, setActiveTab] = useState(requestedTab || 'appearance');
  const [modules, setModules] = useState([]);
  const [modulesLoaded, setModulesLoaded] = useState(false);
  const [palette, setPalette] = useState({ fields: [], defaults: { light: {}, dark: {} } });
  const [paletteTab, setPaletteTab] = useState(activeMode);
  const savedRef = useRef(data);

  useEffect(() => {
    dispatch(getSettings());
    API.get(`${namespace}modules`)
      .then(res => setModules(Array.isArray(res.data) ? res.data : []))
      .catch(() => setModules([]))
      .finally(() => setModulesLoaded(true));
    API.get(`${namespace}palette`)
      .then(res => res.data?.fields && setPalette(res.data))
      .catch(() => {});
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
  const setColor = (variant, varName, value) =>
    setForm(f => ({ ...f, theme: { ...f.theme, [variant]: { ...f.theme[variant], [varName]: value } } }));
  const resetPalette = variant =>
    setForm(f => ({ ...f, theme: { ...f.theme, [variant]: { ...(palette.defaults?.[variant] || {}) } } }));

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

  const tabs = [...BASE_TABS, ...panelTabs(modules, form?.features)];

  // Switching a module off takes its panel away; don't strand the user on a tab
  // that no longer exists (or one a stale ?tab= asked for).
  useEffect(() => {
    // Only once the panels are known — until then every module tab looks
    // missing, and a ?tab= naming one would be thrown away on first render.
    if (modulesLoaded && tabs.length && !tabs.some(t => t.key === activeTab)) setActiveTab('modules');
  }, [modulesLoaded, tabs.map(t => t.key).join(), activeTab]);

  // Only surface the branded, enabled save button when there are unsaved edits.
  const isDirty = !!form && !!data && JSON.stringify(form) !== JSON.stringify(data);
  const canSave = isDirty && !saving;

  // Nothing to save is not the same as a save that failed. A greyed-out button
  // reads as the second, so tabs with nothing on them (AI access issues tokens
  // through its own controls) show no button at all rather than a dead one.
  const saveButton = canSave || saving ? (
    <button
      onClick={handleSave}
      disabled={!canSave}
      className="inline-flex items-center gap-2 rounded-[4px] bg-[var(--zaplane-primary)] px-4 py-2 text-[13px] font-semibold text-white transition-colors hover:opacity-90 disabled:cursor-wait disabled:opacity-70"
    >
      {saving ? __('Saving…', 'zaplane') : __('Save changes', 'zaplane')}
    </button>
  ) : null;

  return (
    <PageLayout title={__('Settings', 'zaplane')} heading={__('Settings', 'zaplane')} actions={saveButton}>
      {form && (
        <div className="flex flex-col gap-6 md:flex-row">
          {/* Left sidebar tabs */}
          <nav className="w-full shrink-0 rounded-lg border border-[var(--zaplane-border-color)] bg-[var(--zaplane-background)] p-2 md:w-[220px]">
            <div className="flex flex-row gap-1 md:flex-col">
              {tabs.map(tab => {
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
                paletteFields={palette.fields}
                paletteTab={paletteTab}
                setPaletteTab={setPaletteTab}
                setActiveMode={setActiveMode}
                setColor={setColor}
                resetPalette={resetPalette}
              />
            ) : activeTab === 'modules' ? (
              <ModulesTab form={form} modules={modules} setFeature={setFeature} setActiveTab={setActiveTab} />
            ) : activeTab === 'mcp' ? (
              <McpTab />
            ) : null}
          </div>
        </div>
      )}
    </PageLayout>
  );
};

export default Setting;
