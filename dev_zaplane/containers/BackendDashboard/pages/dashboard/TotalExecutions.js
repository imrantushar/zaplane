import React from "react";
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from "recharts";
import { __ } from "@wordpress/i18n";
import { useSelector } from "react-redux";
const TotalExecutions = () => {
  const {
    summary
  } = useSelector(state => state.dashboard);
  const monthly_executions = summary?.monthly_executions || [];
  return (
    <div className="bg-white rounded-[8px] border border-[#E2E8F0] w-full h-[388px] flex flex-col">
      <div className="p-6">
        <span className="text-[#4A5568] text-[16px] font-[500]">
          {__("Total Executions", "zaplane")}
        </span>
      </div>

      <div className="flex-1 px-4 pb-6">
        <ResponsiveContainer width="100%" height="100%">
          <AreaChart data={monthly_executions} margin={{ top: 10, right: 30, left: 0, bottom: 0 }}>
            <defs>
              <linearGradient id="colorValue" x1="0" y1="0" x2="0" y2="1">
                <stop offset="5%" stopColor="#3182CE" stopOpacity={0.1} />
                <stop offset="95%" stopColor="#3182CE" stopOpacity={0} />
              </linearGradient>
            </defs>

            <CartesianGrid strokeDasharray="0" vertical={false} stroke="#EDF2F7" />

            <XAxis 
              dataKey="month" 
              tick={{ fontSize: 12, fill: '#718096' }} 
              axisLine={false} 
              tickLine={false} 
              dy={10}
            />

            <YAxis 
              allowDecimals={false} 
              tick={{ fontSize: 12, fill: '#718096' }} 
              axisLine={false} 
              tickLine={false} 
            />

            <Tooltip 
              contentStyle={{ borderRadius: '8px', border: '1px solid #E2E8F0', boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1)' }}
            />

            <Area 
              type="monotone" 
              dataKey="runs" 
              name="Runs" 
              stroke="#3182CE" 
              fill="url(#colorValue)" 
              strokeWidth={3} 
              dot={false} 
              activeDot={{ r: 6, strokeWidth: 0, fill: '#3182CE' }}
            />
          </AreaChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
};
export default TotalExecutions;