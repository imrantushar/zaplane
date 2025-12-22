import { createAsyncThunk, createSlice } from '@reduxjs/toolkit';

import {
	API,
	current_user_can,
	current_user_id,
	is_admin,
	handleSliceSuccess,
	handleSliceError,
} from '@ZAPUtils/helper';
const namespace = 'zaplane/v1/';

export const createWorkflows = createAsyncThunk(
	'zaplane/createWorkflows',
	async ( payload, thunkAPI ) => {
		return await API.post( namespace + 'workflows', payload )
			.then( ( res ) => {
				if ( res.status === 201 ) {
					handleSliceSuccess( thunkAPI, res.data.message );
					return res.data;
				}
			} )
			.catch( ( err ) => {
				return handleSliceError( thunkAPI, err );
			} );
	}
);

export const getWorkFlow = createAsyncThunk(
	'zaplane/getWorkFlow',
	async (thunkAPI ) => {
		return await API.get(
			namespace + "workflows"
		)
			.then( ( res ) => {
				if ( res.status === 200 ) {
					console.log(res,'res in slice');
					return res.data;
				}
			} )
			.catch( ( err ) => {
				return handleSliceError( thunkAPI, err );
			} );
	}
);



const workflowsSlice = createSlice( {
	name: 'workflows',
	initialState: {
     data:[],
	workflow_Title: '',
		
	},
	reducers: {
		createWorkflowTitle: ( state, action ) => {
			state.workflow_Title = action.payload;
		}
		
	},
	extraReducers: ( builder ) => {
		builder
			.addCase( createWorkflows.fulfilled, ( state, action ) => {
				state.data = [ action.payload, ...state.data ];
			} )

			.addCase( getWorkFlow.fulfilled, ( state, action ) => {
				const { data } =action.payload;
				state.data = data;
				
			} )

			
	},
} );

export const { createWorkflowTitle } = workflowsSlice.actions;
export default workflowsSlice.reducer;
