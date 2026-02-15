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
	async (payload, thunkAPI) => {
		try {
			await makeRequest('update_workflow_status', {
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