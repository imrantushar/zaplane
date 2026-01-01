import { createAsyncThunk, createSlice } from '@reduxjs/toolkit';
import { __ } from '@wordpress/i18n';

import {
	API,
	current_user_can,
	current_user_id,
	is_admin,
	handleSliceSuccess,
	handleSliceError,
	namespace,
	makeRequest,
} from '@ZAPUtils/helper';
import { showNotification } from '../notificationSlice/notificationSlice';
export const createWorkflows = createAsyncThunk(
	'zaplane/createWorkflows',
	async (payload, thunkAPI) => {
		return await API.post(namespace + 'workflows', payload)
			.then((res) => {
				handleSliceSuccess(thunkAPI, "data fetched successfully");
				return res?.data;
			})
			.catch((err) => {
				return handleSliceError(thunkAPI, "Data fetching failed");
			});
	}
);

export const getWorkFlow = createAsyncThunk(
	'zaplane/getWorkFlow',
	async (thunkAPI) => {
		try {
			const res = await API.get(namespace + "workflows");
			return res.data
		} catch (e) {
			return handleSliceError(thunkAPI, e)
		}
	}
);
export const updateWorkFlow = createAsyncThunk(
	'zaplane/updateWorkFlow',
	async ({ id, payload }, thunkAPI) => {
		try {
			const res = await API.put(
				namespace + "workflows/" + parseInt(id),
				payload
			);
			handleSliceSuccess(thunkAPI, __('Updated workflow Successfully', 'workflow'));
			return res.data;
		} catch (e) {
			handleSliceError(thunkAPI, e)
		}
	}
);
export const getSingleWorkFlow = createAsyncThunk(
	'zaplane/getSingleWorkFlow',
	async (id, thunkAPI) => {
		try {
			const res = await API.get(
				namespace + "workflows/" + parseInt(id) + "/graph", {
			});
			return res.data;
		} catch (e) {
			return handleSliceError(thunkAPI, e)
		}
	}
);
export const deleteWorkFlow = createAsyncThunk(
	'zaplane/deleteWorkFlow',
	async (id, thunkAPI) => {
		try {
			const res = await API.delete(
				namespace + "workflows/" + parseInt(id),
				{
					data: { force: true },
					headers: {
						'X-HTTP-Method-Override': 'DELETE',
					},
				}
			);

			thunkAPI.dispatch(
				showNotification({
					message: 'workflow Deleted',
					isShow: true,
					type: 'success',
				})
			);
			return res?.data?.data?.odd?.id || id;
		} catch (e) {

			return handleSliceError(thunkAPI, e);
		}
	}
);
export const updateWorkFlowStatus = createAsyncThunk(
	'zaplane/updateWorkFlowStatus',
	async ({ payload }, thunkAPI) => {
		try {
			await makeRequest('update_workflow_status', {
				id: payload.id,
				...payload,
			});
			thunkAPI.dispatch(
				showNotification({
					message: __('Updated Status Successfully', 'storeengine'),
					isShow: true,
					type: 'success',
				})
			);
			console.log('res', payload)
			return payload;
		} catch (e) {
			thunkAPI.dispatch(
				showNotification({
					message: e,
					isShow: true,
					type: 'error',
				})
			);
		}
	}
);
export const getSingleRunDetails = createAsyncThunk(
	'zaplane/getSingleRunDetails',
	async (runId, thunkAPI) => {
		try {
			const res = await API.get(
				namespace + `runs/${parseInt(runId)}`
			);

			handleSliceSuccess(thunkAPI, __('Run details fetched successfully', 'workflow'));

			return res.data;
			// {
			//   run: {},
			//   nodes: [],
			//   edges: [],
			//   logs: []
			// }
		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);
export const getRunsList = createAsyncThunk(
	'zaplane/getRunsList',
	async ({ limit = 50, offset = 0 } = {}, thunkAPI) => {
		try {
			const res = await API.get(
				namespace + `runs?limit=${limit}&offset=${offset}`
			);

			handleSliceSuccess(
				thunkAPI,
				__('Runs fetched successfully', 'workflow')
			);

			return res.data; 
		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);
export const getQueueList = createAsyncThunk(
  'zaplane/getQueueList',
  async (_, thunkAPI) => {
    try {
      const res = await API.get(
        namespace + 'queue'
      );

      handleSliceSuccess(
        thunkAPI,
        __('Queue fetched successfully', 'workflow')
      );

      return res.data; 
    } catch (e) {
      return handleSliceError(thunkAPI, e);
    }
  }
);
export const retryNodeRun = createAsyncThunk(
  'zaplane/retryNodeRun',
  async (nodeRunId, thunkAPI) => {
    try {
      const res = await API.post(
        namespace + `node-runs/${parseInt(nodeRunId)}/retry`,
        {}
      );

      thunkAPI.dispatch(
        showNotification({
          message: __('Node retried and queued successfully', 'workflow'),
          isShow: true,
          type: 'success',
        })
      );

      return {
        nodeRunId,
        status: res?.data?.status || 'queued',
      };
    } catch (e) {
      return handleSliceError(thunkAPI, e);
    }
  }
);
export const replayWorkflowRun = createAsyncThunk(
  'zaplane/replayWorkflowRun',
  async (runId, thunkAPI) => {
    try {
      const res = await API.post(
        namespace + `runs/${parseInt(runId)}/replay`,
        {}
      );

      thunkAPI.dispatch(
        showNotification({
          message: __('Workflow replay started successfully', 'workflow'),
          isShow: true,
          type: 'success',
        })
      );

      return {
        oldRunId: runId,
        newRunId: res?.data?.new_run_id,
      };
    } catch (e) {
      return handleSliceError(thunkAPI, e);
    }
  }
);

const workflowsSlice = createSlice({
	name: 'workflows',
	initialState: {
		data: [],

	},
	reducers: {

	},
	extraReducers: (builder) => {
		builder
			.addCase(createWorkflows.fulfilled, (state, action) => {
				state.data = action.payload;
			})
			.addCase(getWorkFlow.fulfilled, (state, action) => {
				state.data = [...action.payload].reverse();
			})

			.addCase(getSingleWorkFlow.fulfilled, (state, action) => {
				if (!action.payload) return;
				state.data = [action.payload];
			})
			.addCase(updateWorkFlow.fulfilled, (state, action) => {
				state.data = state.data.map((item) => {
					if (parseInt(item.id) === parseInt(action.payload.id)) {
						return { ...item, ...action.payload };
					}
					return item;
				});
			})
			.addCase(deleteWorkFlow.fulfilled, (state, action) => {
				state.data = state.data.filter(
					(item) => parseInt(item.id) !== parseInt(action.payload)
				);
			})
			.addCase(updateWorkFlowStatus.fulfilled, (state, action) => {
				state.data = state.data.map((item) =>
					parseInt(item.id) === parseInt(action.payload.id)
						? { ...item, status: action.payload.status }
						: item
				);
			})



	},
});


export async function fetchDynamic({
	integration,
	query,
	select,
	where = {},
	search = "",
	limit = 20,
}) {
	const { data } = await API.post(namespace + "dynamic", {
		integration,
		query,
		select,
		where,
		search,
		limit,
	});

	return data;
}


export const { createWorkflowTitle } = workflowsSlice.actions;
export default workflowsSlice.reducer;
