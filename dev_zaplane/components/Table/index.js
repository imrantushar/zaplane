import React from "react";

import ZAPLoading from "@ZAPComponents/Loading";
import { __ } from "@wordpress/i18n";
import CustomTableMessage from "@ZAPComponents/Oops/CustomTableMessage";
const ZAPTable = ({
  data = [],
  columns = [],
  rowKey = "id",
  actionsRenderer,
  caption,
  variant = "line",
  size = "sm",
  isLoading = false,
  noDataText = __('Please, create data to see the available list here.', 'zaplane')
}) => {
  const colSpan = columns.length + (actionsRenderer ? 1 : 0);
  return <div className="flex flex-col bg-[var(--zaplane-background)] border border-[var(--zaplane-border-color)] rounded-lg overflow-hidden">
      <table className="min-w-full" style={{marginBottom: data.length > 0 ? "25px" : "0"}}>
        {caption && <caption>{caption}</caption>}
        <thead style={{background:'var(--zaplane-secondary-color)'}}>
          <tr>
            {columns.map((col, i) => <th key={i} style={{textAlign: col.textAlign || "left", width: col.width}} className="px-4 py-3 text-xs font-medium text-[var(--zaplane-font-secondary-color)] uppercase tracking-wider border-b border-[var(--zaplane-border-color)] whitespace-nowrap">
                {__(col.label, 'zaplane')}
              </th>)}
            {actionsRenderer && <th style={{textAlign:'center'}} className="px-4 py-3 text-xs font-medium text-[var(--zaplane-font-secondary-color)] uppercase tracking-wider border-b border-[var(--zaplane-border-color)]">
                {__('Actions', 'zaplane')}
              </th>}
          </tr>
        </thead>

        <tbody>
          {isLoading && <tr>
              <td colSpan={colSpan}>
                <div className="flex justify-center py-6">
                  <ZAPLoading />
                </div>
              </td>
            </tr>}
          {!isLoading && Array.isArray(data) && data.map(row => <tr key={row[rowKey]} className="border-b border-[var(--zaplane-border-color)] hover:bg-[var(--zaplane-secondary-color)]">
                {columns.map((col, i) => <td key={i} style={{textAlign: col.textAlign || "left"}} className="px-4 py-3 text-sm">
                    {col.render ? col.render(row) : row[col.key] || "--"}
                  </td>)}

                {actionsRenderer && <td style={{textAlign:'center'}} className="px-4 py-3 text-sm">
                    <div className="flex justify-center flex-row items-center gap-1">
                      {actionsRenderer(row)}
                    </div>
                  </td>}
              </tr>)}
          {!isLoading && Array.isArray(data) && data.length === 0 && <tr>
              <td colSpan={colSpan} style={{textAlign:'center'}}>
                <span>
                  <CustomTableMessage title={__('No Data Available!!!', 'zaplane')} subText={noDataText} />
                </span>
              </td>
            </tr>}
        </tbody>
      </table>
    </div>;
};
export default ZAPTable;