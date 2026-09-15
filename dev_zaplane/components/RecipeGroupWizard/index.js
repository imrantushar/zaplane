import { useEffect, useRef, useState } from "react";
import { __, _n, sprintf } from "@wordpress/i18n";
import { useDispatch } from "react-redux";
import { useNavigate } from "react-router-dom";
import { FiArrowLeft, FiArrowRight } from "react-icons/fi";
import WPModal from "@ZAPComponents/Modal/WPModal";
import { showNotification } from "@ZAPRedux/Slices/notificationSlice/notificationSlice";
import { API, namespace, route_path } from "@ZAPUtils/helper";
import Rail from "./Rail";
import WorkflowsStep from "./WorkflowsStep";
import SettingsStep from "./SettingsStep";
import ConnectionsStep from "./ConnectionsStep";
import ReviewStep from "./ReviewStep";
import DoneStep from "./DoneStep";
import { Banner, activeSteps, valueError } from "./parts";
import "./styles.scss";

/**
 * Sets up a recipe: pick its workflows and their optional steps, fill in its
 * settings and connections, and create them. A group recipe's workflows go in a
 * new folder. A recipe of one workflow skips what it has nothing to ask, and
 * names the workflow instead of a folder.
 */
const RecipeGroupWizard = ({ recipe, isOpen, onClose }) => {
  const dispatch = useDispatch();
  const navigate = useNavigate();
  const bodyRef = useRef(null);

  const [setup, setSetup] = useState(null);
  const [loadError, setLoadError] = useState(null);
  const [step, setStep] = useState(0);
  const [furthest, setFurthest] = useState(0);
  const [direction, setDirection] = useState("forward");
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
    setFurthest(0);
    setDirection("forward");
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

  // A new step starts at its top.
  useEffect(() => {
    bodyRef.current?.scrollTo({ top: 0 });
  }, [step, result]);

  const titleOf = key => setup?.workflows?.find(workflow => workflow.key === key)?.title || key;
  const chosen = (setup?.workflows || []).filter(workflow => workflows[workflow.key]);
  const totalSteps = chosen.reduce((sum, workflow) => sum + activeSteps(workflow, options).length, 0);
  const single = setup?.type === "workflow";
  const hasOptions = (setup?.workflows || []).some(workflow => (workflow.options || []).length > 0);

  // A value matters only while a step that reads it will be created.
  const reads = use => workflows[use.workflow] && (use.option === null || !!options[use.workflow]?.[use.option]);
  const valuesInUse = (setup?.values || []).filter(value => (value.used_by || []).some(reads));
  const usedByValue = value => [...new Set((value.used_by || []).filter(reads).map(use => use.workflow))].map(titleOf);
  const errors = Object.fromEntries(valuesInUse.map(value => [value.key, valueError(value, values[value.key])]));
  const settingsValid = Object.values(errors).every(error => !error);

  const selectedApps = (setup?.apps || []).filter(app => app.workflows.some(key => workflows[key]));
  const connectionApps = selectedApps.filter(app => app.requires_connection);
  const inactiveApps = selectedApps.filter(app => !app.plugin_active);
  const usedByApp = app => app.workflows.filter(key => workflows[key]).map(titleOf);

  const steps = [
    ...(!single
      ? [
          {
            key: "workflows",
            label: __("Workflows", "zaplane"),
            hint: setup ? sprintf(__("%1$d of %2$d on", "zaplane"), chosen.length, setup.workflows.length) : "",
          },
        ]
      : hasOptions
        ? [
            {
              key: "workflows",
              label: __("Steps", "zaplane"),
              hint: sprintf(_n("%d step", "%d steps", totalSteps, "zaplane"), totalSteps),
            },
          ]
        : []),
    ...(valuesInUse.length
      ? [
          {
            key: "settings",
            label: __("Settings", "zaplane"),
            hint: settingsValid
              ? sprintf(_n("%d value", "%d values", valuesInUse.length, "zaplane"), valuesInUse.length)
              : __("A value needs a look", "zaplane"),
            alert: !settingsValid,
          },
        ]
      : []),
    ...(connectionApps.length
      ? [
          {
            key: "connections",
            label: __("Connections", "zaplane"),
            hint: sprintf(
              __("%1$d of %2$d linked", "zaplane"),
              connectionApps.filter(app => connections[app.slug]).length,
              connectionApps.length
            ),
          },
        ]
      : []),
    {
      key: "review",
      label: __("Review", "zaplane"),
      hint: single ? __("Name the workflow and create", "zaplane") : __("Name the folder and create", "zaplane"),
    },
  ];
  const index = Math.min(step, steps.length - 1);
  const current = steps[index].key;

  const valid = key => {
    if (key === "workflows") return chosen.length > 0;
    if (key === "settings") return settingsValid;
    if (key === "review") return chosen.length > 0 && folderTitle.trim() !== "";
    return true;
  };
  const canContinue = valid(current);
  const goTo = target => {
    setDirection(target < index ? "back" : "forward");
    setStep(target);
    setFurthest(previous => Math.max(previous, target));
  };
  // A step can be revisited once reached, as long as every step before it is still valid.
  const canJump = target =>
    target !== index && target <= Math.max(furthest, index) && steps.slice(0, target).every(item => valid(item.key));

  const create = async () => {
    setSubmitting(true);
    try {
      const res = await API.post(`${namespace}recipes/${recipe.id}/setup`, {
        workflows,
        options,
        values,
        connections,
        ...(single ? { title: folderTitle.trim() } : { folder_title: folderTitle.trim() }),
        activate,
      });
      setResult(res?.data);
    } catch (e) {
      dispatch(
        showNotification({
          isShow: true,
          type: "error",
          message:
            e?.response?.data?.message ||
            (single ? __("The workflow could not be created.", "zaplane") : __("The workflows could not be created.", "zaplane")),
        })
      );
    }
    setSubmitting(false);
  };

  const go = url => {
    onClose();
    navigate(`${route_path}admin.php?${url}`);
  };

  const renderStep = () => {
    if (current === "workflows") {
      return (
        <WorkflowsStep
          setup={setup}
          single={single}
          workflows={workflows}
          options={options}
          inactiveApps={inactiveApps}
          onWorkflow={(key, on) => setWorkflows(previous => ({ ...previous, [key]: on }))}
          onOption={(workflow, key, on) =>
            setOptions(previous => ({ ...previous, [workflow]: { ...(previous[workflow] || {}), [key]: on } }))
          }
          onAll={on => setWorkflows(Object.fromEntries(setup.workflows.map(workflow => [workflow.key, on])))}
        />
      );
    }

    if (current === "settings") {
      return (
        <SettingsStep
          values={valuesInUse}
          answers={values}
          errors={errors}
          usedBy={usedByValue}
          onChange={(key, value) => setValues(previous => ({ ...previous, [key]: value }))}
        />
      );
    }

    if (current === "connections") {
      return (
        <ConnectionsStep
          apps={connectionApps}
          connections={connections}
          usedBy={usedByApp}
          onChange={(app, id) => setConnections(previous => ({ ...previous, [app]: id }))}
        />
      );
    }

    return (
      <ReviewStep
        setup={setup}
        single={single}
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
    );
  };

  const renderBody = () => {
    if (loadError) {
      return <Banner tone="danger">{loadError}</Banner>;
    }

    if (!setup) {
      return (
        <div className="zgs-stack" aria-busy="true">
          {[0, 1, 2].map(i => (
            <div key={i} className="zgs-skeleton" />
          ))}
        </div>
      );
    }

    if (result) {
      return (
        <div className="zgs-step-panel">
          <DoneStep result={result} onOpenWorkflow={id => go(`page=zaplane-workflows&action=edit&id=${id}`)} />
        </div>
      );
    }

    return (
      <div key={current} className={`zgs-step-panel is-${direction}`}>
        {renderStep()}
      </div>
    );
  };

  return (
    <WPModal
      title={setup?.title || recipe?.title || __("Recipe", "zaplane")}
      isOpen={isOpen}
      onRequestClose={onClose}
      shouldCloseOnClickOutside={false}
      suffix="group-setup"
    >
      <div className="zaplane-group-setup">
        {setup ? (
          <Rail
            steps={steps}
            index={index}
            done={!!result}
            canJump={canJump}
            onJump={goTo}
            chosen={chosen}
            totalSteps={totalSteps}
          />
        ) : (
          <aside className="zgs-rail" aria-hidden="true">
            <div className="zgs-skeleton zgs-skeleton--rail" />
          </aside>
        )}

        <section className="zgs-main">
          <div className="zgs-body" ref={bodyRef}>
            {renderBody()}
          </div>

          {setup && !loadError && (
            <footer className="zgs-footer">
              <span
                className={`zgs-progress${result ? " is-complete" : ""}`}
                style={{ "--zgs-progress": `${result ? 100 : ((index + 1) / steps.length) * 100}%` }}
                aria-hidden="true"
              />

              <span className="zgs-footer__status">
                {result
                  ? result.folder
                    ? sprintf(__("In the folder “%s”", "zaplane"), result.folder.title)
                    : __("Created as a workflow", "zaplane")
                  : sprintf(__("Step %1$d of %2$d · %3$s", "zaplane"), index + 1, steps.length, steps[index].label)}
              </span>

              <div className="zgs-footer__actions">
                {result ? (
                  <>
                    <button type="button" className="zgs-btn zgs-btn--ghost" onClick={onClose}>
                      {__("Close", "zaplane")}
                    </button>
                    <button
                      type="button"
                      className="zgs-btn zgs-btn--primary"
                      onClick={() =>
                        go(
                          result.folder
                            ? `page=zaplane-folders&action=edit&id=${result.folder.id}`
                            : `page=zaplane-workflows&action=edit&id=${result.workflows[0]?.id}`
                        )
                      }
                    >
                      {result.folder ? __("Open folder", "zaplane") : __("Open workflow", "zaplane")}
                      <FiArrowRight className="zgs-btn__next" size={15} aria-hidden="true" />
                    </button>
                  </>
                ) : (
                  <>
                    {index > 0 && (
                      <button type="button" className="zgs-btn zgs-btn--ghost" disabled={submitting} onClick={() => goTo(index - 1)}>
                        <FiArrowLeft className="zgs-btn__back" size={15} aria-hidden="true" />
                        {__("Back", "zaplane")}
                      </button>
                    )}

                    {current === "review" ? (
                      <button type="button" className="zgs-btn zgs-btn--primary" disabled={!canContinue || submitting} onClick={create}>
                        {submitting ? (
                          <>
                            <span className="zgs-spinner" aria-hidden="true" />
                            {__("Creating…", "zaplane")}
                          </>
                        ) : (
                          single
                            ? __("Create workflow", "zaplane")
                            : sprintf(_n("Create %d workflow", "Create %d workflows", chosen.length, "zaplane"), chosen.length)
                        )}
                      </button>
                    ) : (
                      <button type="button" className="zgs-btn zgs-btn--primary" disabled={!canContinue} onClick={() => goTo(index + 1)}>
                        {__("Continue", "zaplane")}
                        <FiArrowRight className="zgs-btn__next" size={15} aria-hidden="true" />
                      </button>
                    )}
                  </>
                )}
              </div>
            </footer>
          )}
        </section>
      </div>
    </WPModal>
  );
};

export default RecipeGroupWizard;
