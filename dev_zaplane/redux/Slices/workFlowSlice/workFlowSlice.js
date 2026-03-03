import { createSlice, createAsyncThunk } from '@reduxjs/toolkit';

import { createWorkflows, deleteWorkFlow, getSingleWorkFlow, getWorkFlow, updateWorkFlow, updateWorkFlowStatus } from './actions/workFlow';
import { getRunWorkFlow, getSingleRun } from './actions/workFlowRuns';
import { getAllVersion, getPreviewOldVersion, versionActive } from './actions/workFlowVersion';
import { nodeLogsRunDetails, getNodeLogDetails } from './actions/workFlowLogs';
import { workFLowSingeNodeExction } from './actions/workflowExctions';
import { workflowNodeListiner, workflowNodeListinerStop } from './actions/workFlowListiner';
import { conditionVariables } from './actions/conditonVariales';
import { fetchConnectionsByApp } from './actions/connectionsSlice';


const workflowsSlice = createSlice({
	name: 'workflows',
	initialState: {
		appConnections: [],
		allWorkFlows: [],
		workFlow: {},
		runs: [],
		versions: [],
		nodeDetails: [],
		isLoading: true,
		singleNodeExecution: null,
		apiCountdown: 0,
		apiRequestRunning: false,
		workflowVariables: [],
		itemPerPage: 10,
		currentPage: 1,
		totalItems: 0,


	},
	reducers: {
		resetSingleNodeExecution(state) {
			state.singleNodeExecution = null;
			state.isLoading = false;
		},
		startApiCountdown(state, action) {
			state.apiCountdown = action.payload;
			state.apiRequestRunning = true;
		},

		decrementApiCountdown(state) {
			if (state.apiCountdown > 1 && state.apiRequestRunning) {
				state.apiCountdown -= 1;
			}
		},
	},
	extraReducers: (builder) => {
		builder
			.addCase(createWorkflows.fulfilled, (state, action) => {
				state.allWorkFlows = action.payload;
			})
			.addCase(getWorkFlow.fulfilled, (state, action) => {
				const { data, totalItems, currentPage, itemPerPage } =
					action.payload;
				state.allWorkFlows = data;
				state.totalItems = totalItems;
				state.currentPage = currentPage;
				state.itemPerPage = itemPerPage;

				state.isLoading = false
			})

			.addCase(getSingleWorkFlow.fulfilled, (state, action) => {
				if (!action.payload) return;
				state.workFlow = action.payload;
			})
			.addCase(updateWorkFlow.fulfilled, (state, action) => {
				state.allWorkFlows = action.payload
			})
			.addCase(deleteWorkFlow.fulfilled, (state, action) => {
				state.allWorkFlows = state.allWorkFlows.filter(
					(item) => parseInt(item.id) !== parseInt(action.payload)
				);
			})
			.addCase(updateWorkFlowStatus.fulfilled, (state, action) => {
				const { id, status } = action.payload || {};
				if (state.workFlow?.workflow?.id === id) {
					state.workFlow.workflow.status = status;
				}
				state.allWorkFlows = Array.isArray(state.allWorkFlows)
					? state.allWorkFlows.map((item) =>
						Number(item.id) === Number(id)
							? { ...item, status }
							: item
					)
					: [];
			})
			.addCase(getRunWorkFlow.fulfilled, (state, action) => {
				// action.payload now has { data, currentPage, itemPerPage, totalItems, totalPages }
				const { data, currentPage, itemPerPage, totalItems, totalPages } = action.payload;
				state.runs = data || [];
				state.currentPage = currentPage;
				state.itemPerPage = itemPerPage;
				state.totalItems = totalItems;
				state.totalPages = totalPages;
				state.isLoading = false;
			})
			.addCase(getPreviewOldVersion.fulfilled, (state, action) => {
				if (!state.workFlow || Object.keys(state.workFlow).length === 0) return;

				state.workFlow = {
					...state.workFlow,
					graph: action.payload.graph || state.workFlow.graph,
					// is_preview: true,
					// preview_version_id: action.payload.version?.id || null,
				};
			})

			.addCase(getAllVersion.fulfilled, (state, action) => {
				const { data, totalItems, currentPage, itemPerPage, totalPages } =
					action.payload;
				state.versions = data || [];
				state.totalItems = totalItems;
				state.currentPage = currentPage;
				state.itemPerPage = itemPerPage;
				state.totalPages = totalPages;
				state.isLoading = false;
			})
			.addCase(versionActive.fulfilled, (state, action) => {
				const activeVersionId = action.meta.arg.versionID;
				state.versions = state.versions.map((version) => ({
					...version,
					is_active:
						parseInt(version.id) === parseInt(activeVersionId)
							? true
							: false,
				}));
			})
			.addCase(nodeLogsRunDetails.fulfilled, (state, action) => {
				state.nodeDetails = action.payload;
				state.isLoading = false
			})
			.addCase(workFLowSingeNodeExction.fulfilled, (state, action) => {
				state.isLoading = false;
				state.singleNodeExecution = action.payload?.data || null;
			})


			.addCase(workflowNodeListiner.fulfilled, (state) => {
				state.isLoading = false;
				state.apiRequestRunning = false;
				state.apiCountdown = 0;
			})
			.addCase(workflowNodeListiner.rejected, (state) => {
				state.isLoading = false;
				state.apiRequestRunning = false;
				state.apiCountdown = 0;
			})
			.addCase(workflowNodeListinerStop.fulfilled, (state) => {
				state.isLoading = false;
				state.apiRequestRunning = false;
				state.apiCountdown = 0;
			})
			.addCase(conditionVariables.fulfilled, (state, action) => {
				if (!action.payload) return;
				state.workflowVariables = action.payload;
			})
			.addCase(fetchConnectionsByApp.fulfilled, (state, action) => {
				state.isLoading = false;
				state.appConnections = action.payload;
			})
	},
});




export const {
	resetSingleNodeExecution,
	startApiCountdown,
	decrementApiCountdown,
} = workflowsSlice.actions;

export default workflowsSlice.reducer;
