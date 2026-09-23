import { useEffect, useState } from "react";
import { __ } from "@wordpress/i18n";
import { FiFolder } from "react-icons/fi";
import ListFilters from "@ZAPComponents/ListFilters";
import { API, namespace } from "@ZAPUtils/helper";

export { readFilters, writeFilters } from "@ZAPComponents/ListFilters";

const TABS = [
  { key: "", label: __("All", "zaplane") },
  { key: "active", label: __("Active", "zaplane"), tone: "active" },
  { key: "paused", label: __("Paused", "zaplane"), tone: "paused" },
  { key: "draft", label: __("Draft", "zaplane"), tone: "draft" },
];

/** The workflow list's filters: status, folder, search. */
const WorkflowFilters = ({ filters, counts, onChange }) => {
  const [folders, setFolders] = useState([]);

  useEffect(() => {
    API.get(namespace + "folders", { params: { page: 1, per_page: 100 } })
      .then((res) => setFolders(res?.data?.data || []))
      .catch(() => setFolders([]));
  }, []);

  return (
    <ListFilters
      tabs={TABS}
      counts={counts}
      filters={filters}
      onChange={onChange}
      searchPlaceholder={__("Search workflows…", "zaplane")}
      group={{
        key: "folder",
        icon: FiFolder,
        placeholder: __("All folders", "zaplane"),
        options: [
          { value: "none", label: __("No folder", "zaplane") },
          ...folders.map((f) => ({ value: String(f.id), label: f.title || f.name })),
        ],
      }}
    />
  );
};

export default WorkflowFilters;
