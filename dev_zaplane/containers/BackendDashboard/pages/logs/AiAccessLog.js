import React, { useCallback, useEffect, useState } from "react";
import { __ } from "@wordpress/i18n";
import ListTable from "@ZAPComponents/ListTable";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";
import { API, namespace } from "@ZAPUtils/helper";

/**
 * What AI clients did, on the page people already go to for history.
 *
 * The panel that issues tokens is the wrong home for this: a log needs room,
 * paging and a filter, and that screen is about handing out credentials.
 */

// Colours come from the theme tokens rather than Tailwind's arbitrary classes,
// which lose to a more specific admin rule inside this app.
const OUTCOME = {
  ok: { label: __("Succeeded", "zaplane"), color: "var(--zaplane-success, #12b76a)" },
  failed: { label: __("Failed", "zaplane"), color: "var(--zaplane-danger)" },
  refused: { label: __("Refused", "zaplane"), color: "var(--zaplane-warning)" },
};

const FILTERS = [
  { value: "all", label: __("All", "zaplane") },
  { value: "ok", label: __("Succeeded", "zaplane") },
  { value: "failed", label: __("Failed", "zaplane") },
  { value: "refused", label: __("Refused", "zaplane") },
];

const AiAccessLog = ({ reloadKey = 0 }) => {
  const [entries, setEntries] = useState([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(20);
  const [filter, setFilter] = useState("all");
  const [loading, setLoading] = useState(true);

  const load = useCallback(async (nextPage = page, nextPerPage = perPage) => {
    setLoading(true);
    try {
      const res = await API.get(`${namespace}mcp/audit`, { params: { page: nextPage, per_page: nextPerPage } });
      setEntries(res.data?.entries || []);
      setTotal(res.data?.total || 0);
    } catch (e) {
      setEntries([]);
      setTotal(0);
    } finally {
      setLoading(false);
    }
  }, [page, perPage]);

  useEffect(() => {
    setPage(1);
    load(1, perPage);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [reloadKey]);

  // Filtering is applied to the page in hand rather than the query: the trail is
  // capped at a couple of thousand rows, and a filter that silently changes what
  // "page 2" means is worse than one that narrows what you are looking at.
  const shown = filter === "all" ? entries : entries.filter(e => e.outcome === filter);

  const columns = [
    {
      name: <span>{__("Outcome", "zaplane")}</span>,
      cell: row => (
        <span className="text-[13px] font-medium" style={{ color: OUTCOME[row.outcome]?.color }}>
          {OUTCOME[row.outcome]?.label || row.outcome}
        </span>
      ),
      textAlign: "start",
    },
    {
      name: <span>{__("Tool", "zaplane")}</span>,
      cell: row => <ZAPLabel label={row.tool} type={"simple"} />,
      textAlign: "start",
    },
    {
      name: <span>{__("Client", "zaplane")}</span>,
      cell: row => (
        <span className="text-[13px] text-[var(--zaplane-font-color)]">
          {row.token_name || __("Unnamed", "zaplane")}
        </span>
      ),
      textAlign: "start",
    },
    {
      name: <span>{__("Detail", "zaplane")}</span>,
      cell: row => (
        <span className="block max-w-[320px] truncate text-[13px] text-[var(--zaplane-text-muted)]" title={row.detail || ""}>
          {row.detail || "—"}
        </span>
      ),
      textAlign: "start",
    },
    {
      name: <span>{__("Took", "zaplane")}</span>,
      cell: row => (
        <span className="text-[13px] text-[var(--zaplane-text-muted)]">
          {row.duration_ms ? `${row.duration_ms}ms` : "—"}
        </span>
      ),
      textAlign: "start",
    },
    {
      name: <span>{__("When", "zaplane")}</span>,
      cell: row => <span className="text-[13px] text-[var(--zaplane-text-muted)]">{row.created_at}</span>,
      textAlign: "start",
    },
  ];

  return (
    <ListTable
      columns={columns}
      // Nothing acts on a selection here — the trail is cleared as a whole, from
      // the button above, not row by row.
      isRowSelectable={false}
      data={shown}
      showSubHeader={true}
      subHeaderComponent={
        <div className="zaplane-table-sub-header-tabs">
          {FILTERS.map(opt => (
            <span
              key={opt.value}
              role="presentation"
              className={`tab ${opt.value === filter ? "is-active" : ""}`}
              onClick={() => setFilter(opt.value)}
            >
              {opt.label}
            </span>
          ))}
        </div>
      }
      showColumnFilter={false}
      showPagination={total > 0}
      noDataText={__("No AI client activity yet. Connect a client under Settings → AI access.", "zaplane")}
      totalItems={total}
      dataFetchingStatus={loading}
      suffix="ai-access-log-table"
      currentPageNumber={page}
      perPage={perPage}
      rowsPerPage={perPage}
      onChangePage={p => {
        setPage(p);
        load(p, perPage);
      }}
      onChangeItemsPerPage={n => {
        setPerPage(n);
        setPage(1);
        load(1, n);
      }}
    />
  );
};

export default AiAccessLog;
