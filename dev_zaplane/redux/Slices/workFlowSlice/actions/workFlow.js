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
import { showNotification } from '../../notificationSlice/notificationSlice';

export const createWorkflows = createAsyncThunk(
	'zaplane/createWorkflows',
	async (payload, thunkAPI) => {
		return await API.post(namespace + 'workflows', payload)
			.then((res) => {
				return res?.data;
			})
			.catch((err) => {
				return handleSliceError(thunkAPI, "Data fetching failed");
			});
	}
);

export const getWorkFlow = createAsyncThunk(
  'zaplane/getWorkFlow',
  async (args = {}, thunkAPI) => {
    try {
      const { page = 1, per_page = 20 } = args;
      const res = await API.get(namespace + "workflows", {
        params: { page, per_page },
      });

      const { data, pagination } = res.data;

      return {
        data,
        currentPage: pagination.page,
        itemPerPage: pagination.per_page,
        totalItems: pagination.total,
        totalPages: pagination.total_pages,
      };
    } catch (e) {
      return thunkAPI.rejectWithValue(
        e.response?.data || e.message
      );
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
			handleSliceSuccess(thunkAPI, __('Updated workflow Successfully', 'zaplane'));
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
					message: 'Workflow Deleted',
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
	async (payload, thunkAPI) => {
		try {
			const saved = await makeRequest('update_workflow_status', {
				id: payload.id,
				...payload,
			});
			// What the server kept, which is what every table should show.
			return { ...payload, status: saved?.status || payload.status };
		} catch (e) {
			thunkAPI.dispatch(
				showNotification({
					message: e?.message || e,
					isShow: true,
					type: 'error',
				})
			);
			return thunkAPI.rejectWithValue(e);
		}
	}
)
export const updateWorkFlowTitle = createAsyncThunk(
	'zaplane/updateWorkFlowTitle',
	async (payload, thunkAPI) => {
		try {
			await makeRequest('update_workflow_title', {
				id: payload.id,
				...payload,
			});
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
)
