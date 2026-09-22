import { __ } from "@wordpress/i18n";
import Select from "react-select";
import { FiX } from "react-icons/fi";
import Search from "@ZAPComponents/Search";
import "./styles.scss";

/** Filters from the address bar, so a refresh or Back keeps them. */
export const readFilters = (keys) => {
  const q = new URLSearchParams(window.location.search);
  return Object.fromEntries(keys.map((key) => [key, q.get(key) || ""]));
};

export const writeFilters = (filters) => {
  const url = new URL(window.location.href);
  Object.entries(filters).forEach(([key, value]) => (value ? url.searchParams.set(key, value) : url.searchParams.delete(key)));
  window.history.replaceState(window.history.state, "", url);
};

/**
 * Above a list: status tabs with counts, an optional dropdown filter
 * (folder, app…), a search and "Clear filters".
 *
 * @param {Object[]} tabs    [{ key: "" | status, label, tone }] — tone colours the dot.
 * @param {Object}   counts  { all, [status]: n } from the list endpoint.
 * @param {Object}   group   { key, icon, placeholder, options: [{ value, label }] } or null.
 * @param {Object}   filters Current values: { status, search, [group.key] }.
 */
const ListFilters = ({ tabs, counts, group = null, filters, onChange, searchPlaceholder }) => {
  const set = (patch) => onChange({ ...filters, ...patch });
  const empty = Object.fromEntries(Object.keys(filters).map((key) => [key, ""]));
  const filtered = Object.values(filters).some(Boolean);
  const GroupIcon = group?.icon;

  return (
    <div className="zaplane-list-filters">
      <div className="zaplane-list-filters__tabs" role="tablist" aria-label={__("Filter by status", "zaplane")}>
        {tabs.map((t) => {
          const active = (filters.status || "") === t.key;
          const count = counts ? counts[t.key || "all"] : null;
          return (
            <button key={t.key || "all"} type="button" role="tab" aria-selected={active} className={active ? "is-active" : ""} onClick={() => set({ status: t.key })}>
              {t.tone && <span className={"zaplane-list-filters__dot is-" + t.tone} aria-hidden="true" />}
              {t.label}
              {count !== null && count !== undefined && <span className="zaplane-list-filters__count">{count}</span>}
            </button>
          );
        })}
      </div>

      <div className="zaplane-list-filters__right">
        {filtered && (
          <button type="button" className="zaplane-list-filters__clear" onClick={() => onChange(empty)} title={__("Clear filters", "zaplane")} aria-label={__("Clear filters", "zaplane")}>
            <FiX aria-hidden="true" />
          </button>
        )}
        {group && (
          <div className={"zaplane-list-filters__group" + (GroupIcon ? " has-icon" : "")}>
            {GroupIcon && <GroupIcon aria-hidden="true" />}
            <Select
              className="zaplane-select"
              classNamePrefix="zaplane-select"
              options={group.options}
              value={group.options.find((o) => o.value === filters[group.key]) || null}
              onChange={(opt) => set({ [group.key]: opt?.value || "" })}
              placeholder={group.placeholder}
              isClearable
              aria-label={group.placeholder}
            />
          </div>
        )}
        <Search
          key={filtered ? "set" : "clear"}
          custom="zaplane-list-filters__search"
          placeholder={searchPlaceholder || __("Search…", "zaplane")}
          defaultValue={filters.search}
          debounce={350}
          onSearchHandler={(search) => set({ search: search.trim() })}
        />
      </div>
    </div>
  );
};

export default ListFilters;
