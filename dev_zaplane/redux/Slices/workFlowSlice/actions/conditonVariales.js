import {  createAsyncThunk} from '@reduxjs/toolkit';
import { API, namespace } from '@ZAPUtils/helper';


export const conditionVariables = createAsyncThunk(
	'zaplane/conditionVariables',
	async ({ workflowHash, targetNodeKey }, thunkAPI) => {
		try {
			const res = await API.get(namespace + `condition-variables?workflow_hash=${workflowHash}&target_node_key=${targetNodeKey}`);
			return res.data

		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);