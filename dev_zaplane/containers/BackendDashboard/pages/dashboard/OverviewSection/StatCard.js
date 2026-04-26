import React from "react";
const StatCard = ({
  title,
  value,
  icon,
  isLoading
}) => {
  return (
    <div className="flex flex-[1] bg-white p-6 rounded-[8px] border border-[#E2E8F0] h-[130px] min-w-[280px]">
      <div className="w-full flex flex-col justify-between">
        <div className="flex justify-between items-start">
          {isLoading ? (
            <div className="h-4 w-24 bg-gray-100 animate-pulse rounded" />
          ) : (
            <span className="text-[#4A5568] text-[16px] font-[500]">
              {title}
            </span>
          )}

          {isLoading ? (
            <div className="w-6 h-6 bg-gray-100 animate-pulse rounded" />
          ) : (
            <div className="text-[#2D3748]">
              {React.createElement(icon, { size: 24 })}
            </div>
          )}
        </div>

        {isLoading ? (
          <div className="h-10 w-16 bg-gray-100 animate-pulse rounded" />
        ) : (
          <span className="text-[#1A202C] text-[32px] font-[600] leading-none">
            {value ?? 0}
          </span>
        )}
      </div>
    </div>
  );

};
export default StatCard;