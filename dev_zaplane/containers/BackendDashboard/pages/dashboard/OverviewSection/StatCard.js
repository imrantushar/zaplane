import React from "react";
const StatCard = ({
  title,
  value,
  icon,
  isLoading
}) => {
  return <div boxShadow="sm" align="center" justify="space-between" width="410px" height="130px" className="flex flex-[1] bg-white p-6 rounded-[4px]">
            <div className="w-[full]">
                <div className="flex justify-between mb-5">
                    {isLoading ? <div height="16px" width="120px" /> : <span className="zaplane-label text-[16px] font-[400]">
                            {title}
                        </span>}

                    {isLoading ? <div boxSize="24px" className="rounded-[4px]" /> : React.createElement(icon, {style: {width:"24px", height:"24px"}})}
                </div>

                {isLoading ? <div height="30px" width="80px" /> : <span className="zaplane-label text-[30px]">
                        {value ?? 0}
                    </span>}
            </div>
        </div>;
};
export default StatCard;