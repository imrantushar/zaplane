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


export const getRunWorkFlow = createAsyncThunk(
	'zaplane/getRunWorkFlow',
	async ({ id, page = 1, per_page = 20 } = {}, thunkAPI) => {
		try {
			const res = await API.get(namespace + `workflows/${id}/runs`, {
				params: { page, per_page },
			});

			const { data, pagination } = res.data;

			return {
				data: data || [],
				currentPage: pagination.page || 1,
				itemPerPage: pagination.per_page || 20,
				totalItems: pagination.total || 0,
				totalPages: pagination.total_pages || 0,
			};

		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);
export const getSingleRun = createAsyncThunk(
	'zaplane/getSingleRun',
	async (runId, thunkAPI) => {
		try {
			const res = await API.post(
				namespace + `node-runs/${parseInt(runId)}/retry`
			);

			handleSliceSuccess(thunkAPI, __('Run fetched successfully', 'workflow'));

			return res.data;

		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);