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
    <div className="bg-[var(--zaplane-background)] rounded-[8px] border border-[var(--zaplane-border-color)] w-full h-[388px] flex flex-col">
      <div className="p-6">
        <span className="text-[var(--zaplane-font-secondary-color)] text-[16px] font-[500]">
          {__("Total Executions", "zaplane")}
        </span>
      </div>

      <div className="flex-1 px-4 pb-6">
        <ResponsiveContainer width="100%" height="100%">
          <AreaChart data={monthly_executions} margin={{ top: 10, right: 30, left: 0, bottom: 0 }}>
            <defs>
              <linearGradient id="colorValue" x1="0" y1="0" x2="0" y2="1">
                <stop offset="5%" stopColor="var(--zaplane-primary)" stopOpacity={0.1} />
                <stop offset="95%" stopColor="var(--zaplane-primary)" stopOpacity={0} />
              </linearGradient>
            </defs>

            <CartesianGrid strokeDasharray="0" vertical={false} stroke="var(--zaplane-border-color)" />

            <XAxis 
              dataKey="month" 
              tick={{ fontSize: 12, fill: 'var(--zaplane-text-muted)' }} 
              axisLine={false} 
              tickLine={false} 
              dy={10}
            />

            <YAxis 
              allowDecimals={false} 
              tick={{ fontSize: 12, fill: 'var(--zaplane-text-muted)' }} 
              axisLine={false} 
              tickLine={false} 
            />

            <Tooltip 
              contentStyle={{
                borderRadius: '8px',
                background: 'var(--zaplane-background)',
                border: '1px solid var(--zaplane-border-color)',
                boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.25)',
              }}
              labelStyle={{ color: 'var(--zaplane-font-color)' }}
              itemStyle={{ color: 'var(--zaplane-font-secondary-color)' }}
              cursor={{ stroke: 'var(--zaplane-border-color)' }}
            />

            <Area 
              type="monotone" 
              dataKey="runs" 
              name="Runs" 
              stroke="var(--zaplane-primary)" 
              fill="url(#colorValue)" 
              strokeWidth={3} 
              dot={false} 
              activeDot={{ r: 6, strokeWidth: 0, fill: 'var(--zaplane-primary)' }}
            />
          </AreaChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
};
export default TotalExecutions;
