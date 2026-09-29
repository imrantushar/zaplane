import { useEffect, useMemo, useRef, useState } from "react";
import { __, sprintf } from "@wordpress/i18n";
import ZAPSelect from "@ZAPComponents/ZAPSelect";
import { API, namespace } from "@ZAPUtils/helper";
import { mapNodesForBackend } from "../../helper";

const PLACEHOLDER_APP = "Select an app";

const isConfiguredTrigger = node =>
  node?.data?.action === "trigger" &&
  !!node.data.app &&
  node.data.app !== PLACEHOLDER_APP &&
  !!node.data.event;

// A key like `items[].sku` stands for every element of a list, so it can't be
// matched one to one.
const matchable = (variables = []) => variables.filter(v => v?.key && !String(v.key).includes("["));

const hasSample = sample => sample !== undefined && sample !== null && sample !== "";

// Match each of the other trigger's fields to a field of this trigger with the
// same name, keeping any match already made.
const matchByName = (theirs, mine, current) => {
  const own = new Set(mine.map(v => v.key));
  const next = { ...current };
  theirs.forEach(v => {
    if (!next[v.key] && own.has(v.key)) {
      next[v.key] = `{{${v.key}}}`;
    }
  });
  return next;
};

/**
 * Match this trigger's fields to another trigger's.
 *
 * Steps in a workflow that several triggers start are usually built against one
 * of them, e.g. {{1.email}}. When this trigger fires instead, those fields would
 * be empty. Matching fills them: each field of the chosen trigger takes a value
 * from this trigger's own data.
 */
const TriggerFieldMap = ({ node, nodes = [], workFlow, app, event, config = {}, value, onChange }) => {
  const triggers = useMemo(() => nodes.filter(n => n.data?.action === "trigger"), [nodes]);
  const others = triggers.filter(n => n.id !== node?.id && isConfiguredTrigger(n));

  const target = value?.target != null ? String(value.target) : "";
  const fields = value?.fields && typeof value.fields === "object" && !Array.isArray(value.fields) ? value.fields : {};

  const [targetFields, setTargetFields] = useState([]);
  const [ownFields, setOwnFields] = useState([]);
  const [loading, setLoading] = useState(false);
  // Set when a trigger is picked, so fields with the same name are matched as
  // soon as they load.
  const autoMatchNext = useRef(false);

  const configKey = JSON.stringify(config || {});

  useEffect(() => {
    if (!target || node?.id == null || !app || !event) {
      setTargetFields([]);
      setOwnFields([]);
      return undefined;
    }

    let cancelled = false;
    setLoading(true);

    // This trigger may not be saved yet, so describe it as the drawer has it.
    const graphNodes = mapNodesForBackend(nodes).map(n =>
      String(n.id) === String(node.id)
        ? { ...n, type: "trigger", data: { ...n.data, app, event, config: JSON.parse(configKey) } }
        : n
    );

    API.post(`${namespace}trigger-fields`, {
      workflow_id: workFlow?.workflow?.id,
      graph: { nodes: graphNodes, edges: [] },
      node_ids: [target, String(node.id)],
    })
      .then(res => {
        if (cancelled) return;
        const data = res?.data?.data || {};
        const theirs = matchable(data[target]?.variables);
        const mine = matchable(data[String(node.id)]?.variables);
        setTargetFields(theirs);
        setOwnFields(mine);

        if (autoMatchNext.current) {
          autoMatchNext.current = false;
          onChange({ target, fields: matchByName(theirs, mine, {}) });
        }
      })
      .catch(() => {
        if (cancelled) return;
        setTargetFields([]);
        setOwnFields([]);
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
    // `nodes` changes on every drag; the request only depends on what is listed.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [target, node?.id, app, event, configKey, workFlow?.workflow?.id]);

  if (!app || app === PLACEHOLDER_APP || !event || others.length === 0) {
    return null;
  }

  const numberOf = id => triggers.findIndex(n => n.id === id) + 1;
  const nameOf = n => sprintf(__("Trigger %1$d · %2$s", "zaplane"), numberOf(n.id), n.data?.name || n.data?.app);

  const ownOptions = ownFields.map(v => ({
    value: `{{${v.key}}}`,
    label: hasSample(v.sample) ? `${v.key} (${String(v.sample)})` : v.key,
  }));

  const matched = targetFields.filter(v => fields[v.key]).length;

  const setField = (key, expression) => {
    const next = { ...fields };
    if (expression) {
      next[key] = expression;
    } else {
      delete next[key];
    }
    onChange({ target, fields: next });
  };

  return (
    <div className="flex flex-col gap-3 mt-2 pt-4 border-t border-[var(--zaplane-border-color)]">
      <div>
        <p className="zaplane-label font-semibold m-0">{__("Match fields to another trigger", "zaplane")}</p>
        <p className="text-xs text-[var(--zaplane-font-secondary-color)] mt-1 mb-0">
          {__("Steps built for another trigger read its fields, such as {{1.email}}. When this trigger fires, those fields take the values you match here.", "zaplane")}
        </p>
      </div>

      <ZAPSelect
        label={__("Trigger to match", "zaplane")}
        options={others.map(n => ({ label: nameOf(n), value: String(n.id) }))}
        value={target}
        isClearable
        placeholder={__("Don't match fields", "zaplane")}
        onChange={option => {
          if (!option?.value) {
            onChange(null);
            return;
          }
          autoMatchNext.current = true;
          onChange({ target: String(option.value), fields: {} });
        }}
      />

      {target && loading && (
        <p className="text-xs text-[var(--zaplane-font-secondary-color)] m-0">{__("Loading fields…", "zaplane")}</p>
      )}

      {target && !loading && targetFields.length === 0 && (
        <p className="text-xs text-[var(--zaplane-font-secondary-color)] m-0">
          {__("That trigger has no fields to match yet. Test it once to capture its data.", "zaplane")}
        </p>
      )}

      {target && !loading && targetFields.length > 0 && (
        <>
          <div className="flex items-center justify-between gap-2">
            <span className="text-xs text-[var(--zaplane-font-secondary-color)]">
              {sprintf(__("%1$d of %2$d fields matched", "zaplane"), matched, targetFields.length)}
            </span>
            <button
              type="button"
              onClick={() => onChange({ target, fields: matchByName(targetFields, ownFields, fields) })}
              className="text-[13px] font-medium text-[var(--zaplane-primary)] hover:underline border-0 bg-transparent p-0 cursor-pointer"
            >
              {__("Match same names", "zaplane")}
            </button>
          </div>

          <div className="flex flex-col gap-2">
            {targetFields.map(variable => (
              <div key={variable.key} className="grid grid-cols-2 gap-2 items-center">
                <div className="min-w-0">
                  <div className="text-[13px] font-medium text-[var(--zaplane-font-color)] truncate" title={variable.key}>
                    {variable.key}
                  </div>
                  {hasSample(variable.sample) && (
                    <div className="text-xs text-[var(--zaplane-font-secondary-color)] truncate" title={String(variable.sample)}>
                      {String(variable.sample)}
                    </div>
                  )}
                </div>
                <ZAPSelect
                  options={ownOptions}
                  value={fields[variable.key] || ""}
                  isClearable
                  placeholder={__("Not matched", "zaplane")}
                  onChange={option => setField(variable.key, option?.value)}
                />
              </div>
            ))}
          </div>
        </>
      )}
    </div>
  );
};

export default TriggerFieldMap;
