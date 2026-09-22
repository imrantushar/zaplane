import { createAsyncThunk, createSlice } from '@reduxjs/toolkit';
import { __ } from '@wordpress/i18n';

import {
	API,
	handleSliceSuccess,
	handleSliceError,
	namespace,
	makeRequest,
} from '@ZAPUtils/helper';


export const getRunWorkFlow = createAsyncThunk(
	'zaplane/getRunWorkFlow',
	async ({ id, page , per_page } = {}, thunkAPI) => {
		try {
			const res = await API.get(namespace + `workflows/${id}/runs`, {
				params: { page, per_page },
			});

			const { data, pagination } = res.data;

			return {
				data: data || [],
				currentPage: pagination.page || 1,
				itemPerPage: pagination.per_page ,
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
			// Run it again from the same trigger with the same data. This used to
			// post the run's id to the node-run retry endpoint, which retried
			// whichever node run happened to have that id.
			const res = await API.post(
				namespace + `runs/${parseInt(runId)}/replay`
			);

			handleSliceSuccess(thunkAPI, __('Run started again.', 'zaplane'));

			return res.data;

		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);