import React from "react";
const StatCard = ({
  title,
  value,
  icon,
  isLoading
}) => {
  return (
    <div className="flex flex-[1] bg-[var(--zaplane-background)] p-6 rounded-[8px] border border-[var(--zaplane-border-color)] h-[130px] min-w-[280px]">
      <div className="w-full flex flex-col justify-between">
        <div className="flex justify-between items-start">
          {isLoading ? (
            <div className="h-4 w-24 bg-[var(--zaplane-secondary-color)] animate-pulse rounded" />
          ) : (
            <span className="text-[var(--zaplane-font-secondary-color)] text-[16px] font-[500]">
              {title}
            </span>
          )}

          {isLoading ? (
            <div className="w-6 h-6 bg-[var(--zaplane-secondary-color)] animate-pulse rounded" />
          ) : (
            <div className="text-[var(--zaplane-font-color)]">
              {React.createElement(icon, { size: 24 })}
            </div>
          )}
        </div>

        {isLoading ? (
          <div className="h-10 w-16 bg-[var(--zaplane-secondary-color)] animate-pulse rounded" />
        ) : (
          <span className="text-[var(--zaplane-font-color)] text-[32px] font-[600] leading-none">
            {value ?? 0}
          </span>
        )}
      </div>
    </div>
  );

};
export default StatCard;