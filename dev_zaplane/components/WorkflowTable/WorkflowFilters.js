import { useEffect, useState } from "react";
import { __ } from "@wordpress/i18n";
import Select from "react-select";
import { FiFolder, FiX } from "react-icons/fi";
import Search from "@ZAPComponents/Search";
import { API, namespace } from "@ZAPUtils/helper";

const STATUSES = [
  { key: "", label: __("All", "zaplane") },
  { key: "active", label: __("Active", "zaplane") },
  { key: "paused", label: __("Paused", "zaplane") },
  { key: "draft", label: __("Draft", "zaplane") },
];

/** Filters from the address bar, so a refresh or Back keeps them. */
export const readFilters = () => {
  const q = new URLSearchParams(window.location.search);
  return { status: q.get("status") || "", folder: q.get("folder") || "", search: q.get("search") || "" };
};

export const writeFilters = (filters) => {
  const url = new URL(window.location.href);
  Object.entries(filters).forEach(([key, value]) => (value ? url.searchParams.set(key, value) : url.searchParams.delete(key)));
  window.history.replaceState(window.history.state, "", url);
};

/**
 * Above the workflow list: status tabs with counts, a folder filter and a
 * title search.
 */
const WorkflowFilters = ({ filters, counts, onChange }) => {
  const [folders, setFolders] = useState([]);

  useEffect(() => {
    API.get(namespace + "folders", { params: { page: 1, per_page: 100 } })
      .then((res) => setFolders(res?.data?.data || []))
      .catch(() => setFolders([]));
  }, []);

  const folderOptions = [
    { value: "none", label: __("No folder", "zaplane") },
    ...folders.map((f) => ({ value: String(f.id), label: f.title || f.name })),
  ];
  const set = (patch) => onChange({ ...filters, ...patch });
  const filtered = filters.status || filters.folder || filters.search;

  return (
    <div className="zaplane-workflow-filters">
      <div className="zaplane-workflow-filters__tabs" role="tablist" aria-label={__("Filter by status", "zaplane")}>
        {STATUSES.map((s) => {
          const active = filters.status === s.key;
          const count = counts ? counts[s.key || "all"] : null;
          return (
            <button key={s.key || "all"} type="button" role="tab" aria-selected={active} className={active ? "is-active" : ""} onClick={() => set({ status: s.key })}>
              {s.key && <span className={"zaplane-workflow-filters__dot is-" + s.key} aria-hidden="true" />}
              {s.label}
              {count !== null && count !== undefined && <span className="zaplane-workflow-filters__count">{count}</span>}
            </button>
          );
        })}
      </div>

      <div className="zaplane-workflow-filters__right">
        <div className="zaplane-workflow-filters__folder">
          <FiFolder aria-hidden="true" />
          <Select
            className="zaplane-select"
            classNamePrefix="zaplane-select"
            options={folderOptions}
            value={folderOptions.find((o) => o.value === filters.folder) || null}
            onChange={(opt) => set({ folder: opt?.value || "" })}
            placeholder={__("All folders", "zaplane")}
            isClearable
            aria-label={__("Filter by folder", "zaplane")}
          />
        </div>
        <Search
          custom="zaplane-workflow-filters__search"
          placeholder={__("Search workflows…", "zaplane")}
          defaultValue={filters.search}
          debounce={350}
          onSearchHandler={(search) => set({ search: search.trim() })}
        />
        {filtered && (
          <button type="button" className="zaplane-workflow-filters__clear" onClick={() => onChange({ status: "", folder: "", search: "" })}>
            <FiX aria-hidden="true" />
            {__("Clear", "zaplane")}
          </button>
        )}
      </div>
    </div>
  );
};

export default WorkflowFilters;
