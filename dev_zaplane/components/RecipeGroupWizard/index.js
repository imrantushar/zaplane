import { useEffect, useRef, useState } from "react";
import { __, _n, sprintf } from "@wordpress/i18n";
import { useDispatch } from "react-redux";
import { useNavigate } from "react-router-dom";
import Select from "react-select";
import { FiAlertTriangle, FiCheck, FiCheckCircle, FiMinus } from "react-icons/fi";
import WPModal from "@ZAPComponents/Modal/WPModal";
import ZAPToggle from "@ZAPComponents/ZAPToggle";
import ZAPInput from "@ZAPComponents/ZAPInput";
import ZAPIconGroup from "@ZAPComponents/ZAPIconGroup/ZAPIconGroup";
import ZAPLoading from "@ZAPComponents/Loading";
import { showNotification } from "@ZAPRedux/Slices/notificationSlice/notificationSlice";
import { API, namespace, route_path } from "@ZAPUtils/helper";
import { primaryBtn } from "../../../assets/scss/chakra/recipe";
import "./styles.scss";

// Paragraphs and headings carry m-0: wp-admin gives them margins of their own.

const secondaryBtn = {
  padding: "8px 16px",
  borderRadius: "4px",
  border: "1px solid var(--zaplane-border-color)",
  backgroundColor: "var(--zaplane-background)",
  color: "var(--zaplane-font-color)",
  fontSize: "13px",
  fontWeight: 600,
  cursor: "pointer",
};

const withDisabled = (style, disabled) => ({
  ...style,
  opacity: disabled ? 0.5 : 1,
  cursor: disabled ? "not-allowed" : "pointer",
});

/**
 * Why a setup value would be refused, or null. Mirrors the server's check in
 * RecipeGroupBuilder, so the setup can say so before anything is sent.
 */
const valueError = (value, raw) => {
  const text = String(raw ?? "").trim();

  if (value.type !== "number") {
    return value.required && text === "" ? sprintf(__("%s is required.", "zaplane"), value.label) : null;
  }

  if (text === "" || Number.isNaN(Number(text))) {
    return sprintf(__("%s must be a number.", "zaplane"), value.label);
  }

  const number = Number(text);
  const { min, max } = value;

  if ((min != null && number < min) || (max != null && number > max)) {
    if (min != null && max != null) {
      return sprintf(__("%1$s must be between %2$s and %3$s.", "zaplane"), value.label, min, max);
    }
    return min != null
      ? sprintf(__("%1$s must be at least %2$s.", "zaplane"), value.label, min)
      : sprintf(__("%1$s must be at most %2$s.", "zaplane"), value.label, max);
  }

  return null;
};

const StepIntro = ({ title, text }) => (
  <div>
    <h3 className="m-0 text-[15px] font-semibold text-[var(--zaplane-font-color)]">{title}</h3>
    {text && <p className="m-0 mt-1 text-[13px] text-[var(--zaplane-font-secondary-color)]">{text}</p>}
  </div>
);

const Note = ({ children }) => (
  <p className="m-0 flex items-start gap-2 text-[12px] text-[var(--zaplane-font-secondary-color)]">
    <FiAlertTriangle className="mt-[2px] shrink-0" size={13} style={{ color: "#D97706" }} />
    <span>{children}</span>
  </p>
);

const InactiveAppsNote = ({ apps }) =>
  apps.length > 0 && (
    <Note>
      {sprintf(
        _n(
          "%s isn't active on this site. The workflows that use it are created, but won't run until it is.",
          "%s aren't active on this site. The workflows that use them are created, but won't run until they are.",
          apps.length,
          "zaplane"
        ),
        apps.map(app => app.name).join(", ")
      )}
    </Note>
  );

const Steps = ({ steps, index }) => (
  <ol className="m-0 flex flex-wrap items-center gap-2 p-0">
    {steps.map((step, i) => (
      <li key={step.key} className="m-0 flex items-center gap-2" aria-current={i === index ? "step" : undefined}>
        <span
          className="flex h-6 w-6 items-center justify-center rounded-full text-[12px] font-semibold"
          style={
            i <= index
              ? { backgroundColor: "var(--zaplane-primary)", color: "#fff" }
              : { border: "1px solid var(--zaplane-border-color)", color: "var(--zaplane-font-secondary-color)" }
          }
        >
          {i < index ? <FiCheck size={13} /> : i + 1}
        </span>
        <span
          className={`text-[13px] ${
            i === index ? "font-semibold text-[var(--zaplane-font-color)]" : "text-[var(--zaplane-font-secondary-color)]"
          }`}
        >
          {step.label}
        </span>
        {i < steps.length - 1 && <span className="mx-1 h-px w-8 bg-[var(--zaplane-border-color)]" />}
      </li>
    ))}
  </ol>
);

const WorkflowsStep = ({ setup, workflows, options, inactiveApps, onWorkflow, onOption }) => (
  <div className="flex flex-col gap-3">
    <StepIntro
      title={__("Choose what to set up", "zaplane")}
      text={__("Each one becomes its own workflow in a new folder. You can edit any of them afterwards.", "zaplane")}
    />

    <InactiveAppsNote apps={inactiveApps} />

    {setup.workflows.map(workflow => {
      const on = !!workflows[workflow.key];

      return (
        <div
          key={workflow.key}
          className="rounded-[6px] border p-4"
          style={{ borderColor: on ? "var(--zaplane-primary)" : "var(--zaplane-border-color)" }}
        >
          <div className="flex items-start gap-3">
            <ZAPToggle checked={on} onChange={value => onWorkflow(workflow.key, value)} label={workflow.title} />

            <div className="min-w-0 flex-1">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <p className="m-0 text-[14px] font-semibold leading-6 text-[var(--zaplane-font-color)]">{workflow.title}</p>
                  {workflow.description && (
                    <p className="m-0 mt-1 text-[13px] text-[var(--zaplane-font-secondary-color)]">{workflow.description}</p>
                  )}
                </div>
                <ZAPIconGroup icons={workflow.icons} maxVisible={3} />
              </div>

              {on && workflow.options.length > 0 && (
                <div className="mt-3 flex flex-col gap-2 border-t border-[var(--zaplane-border-color)] pt-3">
                  {workflow.options.map(option => (
                    <div key={option.key} className="flex items-center gap-2">
                      <ZAPToggle
                        size="sm"
                        checked={!!options[workflow.key]?.[option.key]}
                        onChange={value => onOption(workflow.key, option.key, value)}
                        label={option.label}
                      />
                      <span className="text-[13px] text-[var(--zaplane-font-color)]">{option.label}</span>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        </div>
      );
    })}
  </div>
);

const SettingsStep = ({ values, answers, errors, onChange, usedBy }) => (
  <div className="flex flex-col gap-6">
    <StepIntro
      title={__("Adjust the settings", "zaplane")}
      text={__("These are written into the workflows you picked. You can change them in each workflow later.", "zaplane")}
    />

    {values.map(value => (
      <div key={value.key} className="flex flex-col gap-2">
        <label htmlFor={`zaplane-setup-${value.key}`} className="zaplane-label">
          {value.label}
        </label>
        {value.description && <p className="m-0 text-[13px] text-[var(--zaplane-font-secondary-color)]">{value.description}</p>}

        <div className="flex items-center gap-2">
          <input
            id={`zaplane-setup-${value.key}`}
            type={value.type === "number" ? "number" : "text"}
            className="zaplane-input text-[var(--zaplane-text-muted)] bg-[var(--zaplane-background)]"
            style={{ maxWidth: value.type === "number" ? "140px" : "360px" }}
            value={answers[value.key] ?? ""}
            min={value.min ?? undefined}
            max={value.max ?? undefined}
            onChange={e => onChange(value.key, e.target.value)}
          />
          {value.suffix && <span className="text-[13px] text-[var(--zaplane-font-secondary-color)]">{value.suffix}</span>}
        </div>

        {errors[value.key] ? (
          <p className="m-0 text-[12px] text-[var(--zaplane-danger)]">{errors[value.key]}</p>
        ) : (
          <p className="m-0 text-[12px] text-[var(--zaplane-font-secondary-color)]">
            {sprintf(__("Used by: %s", "zaplane"), usedBy(value).join(", "))}
          </p>
        )}
      </div>
    ))}
  </div>
);

const ConnectionPicker = ({ app, value, onChange, usedBy }) => {
  const [connections, setConnections] = useState([]);

  const load = () =>
    API.get(`${namespace}connections`, { params: { app: app.slug } })
      .then(res => {
        const rows = res?.data?.data || res?.data || [];
        setConnections(Array.isArray(rows) ? rows.filter(row => row.app === app.slug) : []);
      })
      .catch(() => setConnections([]));

  // A connection added in another tab shows up once this tab is back in front.
  useEffect(() => {
    load();
    window.addEventListener("focus", load);
    return () => window.removeEventListener("focus", load);
  }, [app.slug]);

  const choices = connections.map(connection => ({ value: String(connection.id), label: connection.name }));

  return (
    <div className="flex flex-col gap-3 rounded-[6px] border border-[var(--zaplane-border-color)] p-4">
      <div className="flex items-center justify-between gap-3">
        <div className="flex min-w-0 items-center gap-3">
          <ZAPIconGroup icons={app.icon ? [app.icon] : []} maxVisible={1} />
          <div className="min-w-0">
            <p className="m-0 text-[14px] font-semibold text-[var(--zaplane-font-color)]">{app.name}</p>
            <p className="m-0 text-[12px] text-[var(--zaplane-font-secondary-color)]">
              {sprintf(__("Used by: %s", "zaplane"), usedBy.join(", "))}
            </p>
          </div>
        </div>
        <button
          type="button"
          style={secondaryBtn}
          onClick={() => window.open(`${route_path}admin.php?page=zaplane-connections`, "_blank", "noopener")}
        >
          {__("Add connection", "zaplane")}
        </button>
      </div>

      <Select
        className="zaplane-select"
        classNamePrefix="zaplane-select"
        options={choices}
        value={choices.find(choice => choice.value === value) || null}
        onChange={choice => onChange(choice?.value || "")}
        placeholder={__("Select a connection", "zaplane")}
        noOptionsMessage={() => __("No connections yet", "zaplane")}
        isClearable
        menuPortalTarget={document.body}
        styles={{ menuPortal: base => ({ ...base, zIndex: 100000 }) }}
      />
    </div>
  );
};

const ConnectionsStep = ({ apps, connections, onChange, usedBy }) => (
  <div className="flex flex-col gap-4">
    <StepIntro
      title={__("Connect your accounts", "zaplane")}
      text={__("Pick the account each app should use. A connection added in another tab shows up here when you come back. You can skip one and link it in the workflow later; until then that workflow can't be turned on.", "zaplane")}
    />

    {apps.map(app => (
      <ConnectionPicker
        key={app.slug}
        app={app}
        value={connections[app.slug] || ""}
        onChange={id => onChange(app.slug, id)}
        usedBy={usedBy(app)}
      />
    ))}
  </div>
);

const ReviewStep = ({ setup, chosen, options, valuesInUse, values, inactiveApps, folderTitle, onFolderTitle, activate, onActivate }) => (
  <div className="flex flex-col gap-5">
    <StepIntro title={__("Review and create", "zaplane")} text={__("The workflows are created in a new folder.", "zaplane")} />

    <ZAPInput label={__("Folder name", "zaplane")} value={folderTitle} onChange={e => onFolderTitle(e.target.value)} isRequired />

    {setup.folders.length > 0 && (
      <Note>
        {sprintf(__("Already set up in “%s”. Setting it up again creates another folder.", "zaplane"), setup.folders[0].title)}
      </Note>
    )}

    <div className="divide-y divide-[var(--zaplane-border-color)] rounded-[6px] border border-[var(--zaplane-border-color)]">
      {chosen.map(workflow => (
        <div key={workflow.key} className="p-3">
          <p className="m-0 text-[13px] font-semibold text-[var(--zaplane-font-color)]">{workflow.title}</p>
          {workflow.options.map(option => {
            const on = !!options[workflow.key]?.[option.key];
            return (
              <p key={option.key} className="m-0 mt-1 flex items-center gap-2 text-[12px] text-[var(--zaplane-font-secondary-color)]">
                {on ? <FiCheck size={12} /> : <FiMinus size={12} />}
                <span style={on ? undefined : { textDecoration: "line-through" }}>{option.label}</span>
              </p>
            );
          })}
        </div>
      ))}
    </div>

    {valuesInUse.length > 0 && (
      <p className="m-0 text-[13px] text-[var(--zaplane-font-secondary-color)]">
        {valuesInUse
          .map(value => `${value.label}: ${values[value.key]}${value.suffix === "%" ? "%" : value.suffix ? ` ${value.suffix}` : ""}`)
          .join(" · ")}
      </p>
    )}

    <InactiveAppsNote apps={inactiveApps} />

    <div className="flex items-start gap-3">
      <ZAPToggle checked={activate} onChange={onActivate} label={__("Turn the workflows on after creating them", "zaplane")} />
      <div>
        <p className="m-0 text-[13px] font-semibold leading-6 text-[var(--zaplane-font-color)]">
          {__("Turn the workflows on after creating them", "zaplane")}
        </p>
        <p className="m-0 text-[12px] text-[var(--zaplane-font-secondary-color)]">
          {__("Each one is checked first. One that can't run yet stays a draft, and says why.", "zaplane")}
        </p>
      </div>
    </div>
  </div>
);

const StatusChip = ({ status }) => (
  <span
    className="rounded-full px-2 py-[2px] text-[11px] font-semibold"
    style={
      status === "active"
        ? { backgroundColor: "var(--zaplane-primary)", color: "#fff" }
        : { backgroundColor: "var(--zaplane-secondary-color)", color: "var(--zaplane-font-secondary-color)" }
    }
  >
    {status === "active" ? __("Active", "zaplane") : __("Draft", "zaplane")}
  </span>
);

const Done = ({ result, onOpenFolder, onOpenWorkflow, onClose }) => {
  const workflows = result?.workflows || [];
  const notOn = workflows.filter(workflow => workflow.error);

  return (
    <div className="flex flex-col gap-5 pb-6">
      <div className="flex items-start gap-3">
        <FiCheckCircle size={26} className="shrink-0" style={{ color: "var(--zaplane-primary)" }} />
        <div>
          <p className="m-0 text-[16px] font-semibold leading-[26px] text-[var(--zaplane-font-color)]">
            {sprintf(
              _n("Created %1$d workflow in “%2$s”", "Created %1$d workflows in “%2$s”", workflows.length, "zaplane"),
              workflows.length,
              result.folder.title
            )}
          </p>
          {notOn.length > 0 && (
            <p className="m-0 mt-1 text-[13px] text-[var(--zaplane-font-secondary-color)]">
              {sprintf(
                _n("%d of them couldn't be turned on yet.", "%d of them couldn't be turned on yet.", notOn.length, "zaplane"),
                notOn.length
              )}
            </p>
          )}
        </div>
      </div>

      <div className="divide-y divide-[var(--zaplane-border-color)] rounded-[6px] border border-[var(--zaplane-border-color)]">
        {workflows.map(workflow => (
          <div key={workflow.id} className="flex items-start justify-between gap-3 p-3">
            <div className="min-w-0">
              <p className="m-0 text-[13px] font-semibold leading-6 text-[var(--zaplane-font-color)]">{workflow.title}</p>
              {workflow.error && <p className="m-0 mt-1 text-[12px] text-[var(--zaplane-danger)]">{workflow.error}</p>}
            </div>
            <div className="flex h-6 shrink-0 items-center gap-3">
              <StatusChip status={workflow.status} />
              <button
                type="button"
                className="text-[13px] font-semibold"
                style={{ color: "var(--zaplane-primary)", background: "none", border: 0, cursor: "pointer" }}
                onClick={() => onOpenWorkflow(workflow.id)}
              >
                {__("Open", "zaplane")}
              </button>
            </div>
          </div>
        ))}
      </div>

      <div className="flex justify-end gap-2">
        <button type="button" style={secondaryBtn} onClick={onClose}>
          {__("Close", "zaplane")}
        </button>
        <button type="button" style={primaryBtn} onClick={onOpenFolder}>
          {__("Open folder", "zaplane")}
        </button>
      </div>
    </div>
  );
};

/**
 * Sets up a group recipe: pick its workflows and their optional steps, fill in
 * its settings and connections, and create the workflows in a new folder.
 */
const RecipeGroupWizard = ({ recipe, isOpen, onClose }) => {
  const dispatch = useDispatch();
  const navigate = useNavigate();
  const bodyRef = useRef(null);

  const [setup, setSetup] = useState(null);
  const [loadError, setLoadError] = useState(null);
  const [step, setStep] = useState(0);
  const [workflows, setWorkflows] = useState({});
  const [options, setOptions] = useState({});
  const [values, setValues] = useState({});
  const [connections, setConnections] = useState({});
  const [folderTitle, setFolderTitle] = useState("");
  const [activate, setActivate] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [result, setResult] = useState(null);

  // Each time the setup opens it starts again from the group's defaults.
  useEffect(() => {
    if (!isOpen || !recipe?.id) return undefined;

    let cancelled = false;
    setSetup(null);
    setLoadError(null);
    setStep(0);
    setResult(null);
    setActivate(false);
    setConnections({});

    API.get(`${namespace}recipes/${recipe.id}/setup`)
      .then(res => {
        if (cancelled) return;
        const data = res?.data || {};
        setSetup(data);
        setWorkflows(Object.fromEntries((data.workflows || []).map(workflow => [workflow.key, !!workflow.default])));
        setOptions(
          Object.fromEntries(
            (data.workflows || []).map(workflow => [
              workflow.key,
              Object.fromEntries((workflow.options || []).map(option => [option.key, !!option.default])),
            ])
          )
        );
        setValues(Object.fromEntries((data.values || []).map(value => [value.key, value.default ?? ""])));
        setFolderTitle(data.folder_title || recipe.title || "");
      })
      .catch(e => {
        if (!cancelled) {
          setLoadError(e?.response?.data?.message || __("The setup could not be loaded.", "zaplane"));
        }
      });

    return () => {
      cancelled = true;
    };
  }, [isOpen, recipe?.id]);

  // A new step starts at its top, with the steps in view.
  useEffect(() => {
    bodyRef.current?.closest(".components-modal__content")?.scrollTo({ top: 0 });
  }, [step, result]);

  const titleOf = key => setup?.workflows?.find(workflow => workflow.key === key)?.title || key;
  const chosen = (setup?.workflows || []).filter(workflow => workflows[workflow.key]);

  // A value matters only while a step that reads it will be created.
  const reads = use => workflows[use.workflow] && (use.option === null || !!options[use.workflow]?.[use.option]);
  const valuesInUse = (setup?.values || []).filter(value => (value.used_by || []).some(reads));
  const usedByValue = value => [...new Set((value.used_by || []).filter(reads).map(use => use.workflow))].map(titleOf);

  const selectedApps = (setup?.apps || []).filter(app => app.workflows.some(key => workflows[key]));
  const connectionApps = selectedApps.filter(app => app.requires_connection);
  const inactiveApps = selectedApps.filter(app => !app.plugin_active);
  const usedByApp = app => app.workflows.filter(key => workflows[key]).map(titleOf);

  const steps = [
    { key: "workflows", label: __("Workflows", "zaplane") },
    ...(valuesInUse.length ? [{ key: "settings", label: __("Settings", "zaplane") }] : []),
    ...(connectionApps.length ? [{ key: "connections", label: __("Connections", "zaplane") }] : []),
    { key: "review", label: __("Review", "zaplane") },
  ];
  const index = Math.min(step, steps.length - 1);
  const current = steps[index].key;

  const errors = Object.fromEntries(valuesInUse.map(value => [value.key, valueError(value, values[value.key])]));
  const canContinue =
    current === "workflows"
      ? chosen.length > 0
      : current === "settings"
        ? Object.values(errors).every(error => !error)
        : current === "review"
          ? chosen.length > 0 && folderTitle.trim() !== ""
          : true;

  const create = async () => {
    setSubmitting(true);
    try {
      const res = await API.post(`${namespace}recipes/${recipe.id}/setup`, {
        workflows,
        options,
        values,
        connections,
        folder_title: folderTitle.trim(),
        activate,
      });
      setResult(res?.data);
    } catch (e) {
      dispatch(
        showNotification({
          isShow: true,
          type: "error",
          message: e?.response?.data?.message || __("The workflows could not be created.", "zaplane"),
        })
      );
    }
    setSubmitting(false);
  };

  const go = url => {
    onClose();
    navigate(`${route_path}admin.php?${url}`);
  };

  const renderBody = () => {
    if (loadError) {
      return <p className="m-0 pb-6 text-[13px] text-[var(--zaplane-danger)]">{loadError}</p>;
    }

    if (!setup) {
      return (
        <div className="pb-6">
          <ZAPLoading />
        </div>
      );
    }

    if (result) {
      return (
        <Done
          result={result}
          onClose={onClose}
          onOpenFolder={() => go(`page=zaplane-folders&action=edit&id=${result.folder.id}`)}
          onOpenWorkflow={id => go(`page=zaplane-workflows&action=edit&id=${id}`)}
        />
      );
    }

    return (
      <div className="flex flex-col gap-6">
        <Steps steps={steps} index={index} />

        <div>
          {current === "workflows" && (
            <WorkflowsStep
              setup={setup}
              workflows={workflows}
              options={options}
              inactiveApps={inactiveApps}
              onWorkflow={(key, on) => setWorkflows(prev => ({ ...prev, [key]: on }))}
              onOption={(workflow, key, on) =>
                setOptions(prev => ({ ...prev, [workflow]: { ...(prev[workflow] || {}), [key]: on } }))
              }
            />
          )}

          {current === "settings" && (
            <SettingsStep
              values={valuesInUse}
              answers={values}
              errors={errors}
              usedBy={usedByValue}
              onChange={(key, value) => setValues(prev => ({ ...prev, [key]: value }))}
            />
          )}

          {current === "connections" && (
            <ConnectionsStep
              apps={connectionApps}
              connections={connections}
              usedBy={usedByApp}
              onChange={(app, id) => setConnections(prev => ({ ...prev, [app]: id }))}
            />
          )}

          {current === "review" && (
            <ReviewStep
              setup={setup}
              chosen={chosen}
              options={options}
              valuesInUse={valuesInUse}
              values={values}
              inactiveApps={inactiveApps}
              folderTitle={folderTitle}
              onFolderTitle={setFolderTitle}
              activate={activate}
              onActivate={setActivate}
            />
          )}
        </div>

        <div className="zaplane-group-setup__footer flex items-center justify-between gap-3">
          <span className="text-[12px] text-[var(--zaplane-font-secondary-color)]">
            {sprintf(_n("%d workflow selected", "%d workflows selected", chosen.length, "zaplane"), chosen.length)}
          </span>

          <div className="flex gap-2">
            {index > 0 && (
              <button type="button" style={withDisabled(secondaryBtn, submitting)} disabled={submitting} onClick={() => setStep(index - 1)}>
                {__("Back", "zaplane")}
              </button>
            )}

            {current === "review" ? (
              <button type="button" style={withDisabled(primaryBtn, !canContinue || submitting)} disabled={!canContinue || submitting} onClick={create}>
                {submitting
                  ? __("Creating…", "zaplane")
                  : sprintf(_n("Create %d workflow", "Create %d workflows", chosen.length, "zaplane"), chosen.length)}
              </button>
            ) : (
              <button type="button" style={withDisabled(primaryBtn, !canContinue)} disabled={!canContinue} onClick={() => setStep(index + 1)}>
                {__("Next", "zaplane")}
              </button>
            )}
          </div>
        </div>
      </div>
    );
  };

  return (
    <WPModal
      title={setup?.title || recipe?.title || __("Group recipe", "zaplane")}
      isOpen={isOpen}
      onRequestClose={onClose}
      shouldCloseOnClickOutside={false}
      suffix="group-setup"
    >
      <div ref={bodyRef} className="zaplane-group-setup">
        {renderBody()}
      </div>
    </WPModal>
  );
};

export default RecipeGroupWizard;
