import React, { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { primaryBtn } from '../../../../../assets/scss/chakra/recipe';
import { useDispatch, useSelector } from 'react-redux';
import RecentLogs from './RecentLogs';
import ExecutedFlows from './ExecutedFlows';
import { getRunsList } from '@ZAPRedux/Slices/logsSlice/logsSlice';
import TotalExecutions from './TotalExecutions';
import { deshboardSumary, topExecutedFlows } from '@ZAPRedux/Slices/dashboardSlice/dashboardSlice';
import OverviewSection from './OverviewSection/OverviewSection';
import CreateWorkflowModal from '@ZAPComponents/CreateWorkflowModal';
import PageLayout from '@ZAPComponents/PageLayout';
import Teaser from '@ZAPComponents/Teaser';
export default function Dashboard() {
  const dispatch = useDispatch();
  const {
    data = []
  } = useSelector(state => state.logs || {});
  const [isModalOpen, setIsModalOpen] = useState(false);
  useEffect(() => {
    dispatch(getRunsList());
    dispatch(topExecutedFlows());
    dispatch(deshboardSumary());
  }, [dispatch]);
  return (
    <PageLayout
      title="Dashboard"
      actions={
        <button
          onClick={() => setIsModalOpen(true)}
          style={primaryBtn}
        >
          {__("Create New Workflow", "zaplane")}
        </button>
      }
    >
      <div className="flex flex-col gap-6">
        <Teaser screen="dashboard" />

        <OverviewSection />

        <TotalExecutions />

        <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(360px,0.95fr)_minmax(0,1.7fr)] xl:items-stretch">
          <div className="min-w-0 h-full">
            <ExecutedFlows />
          </div>
          <div className="min-w-0 h-full">
            <RecentLogs data={data} />
          </div>
        </div>
      </div>
      <CreateWorkflowModal isOpen={isModalOpen} onClose={() => setIsModalOpen(false)} />
    </PageLayout>
  );
}
