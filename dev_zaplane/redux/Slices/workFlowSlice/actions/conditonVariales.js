import {  createAsyncThunk} from '@reduxjs/toolkit';


export const conditionVariables = createAsyncThunk(
	'zaplane/conditionVariables',
	async ({workflowHash,targetNodeKey}, thunkAPI) => {
		try {
			const res = await API.get(namespace + `workflows?workflow_hash=${workflowHash}&target_node_key=${targetNodeKey}`);
			return res.data

		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);