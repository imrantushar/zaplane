import { configureStore } from '@reduxjs/toolkit';

// Import all the reducers you have created
import appReducer from './Slices/appSlice/appSlice';
import menuReducer from './Slices/menuSlice/menuSlice';
import workflowsReducer from './Slices/workFlowSlice/workFlowSlice';
import logsReducer from './Slices/logsSlice/logsSlice';
import dashboardReducer from './Slices/dashboardSlice/dashboardSlice';
import connectionsReducer from './Slices/connectionsSlice/connectionsSlice';
import settingReducer from './Slices/settingSlice/settingSlice';
import notificationReducer from './Slices/notificationSlice/notificationSlice';

import logger from 'redux-logger'
/**
 * The main Redux store for the Zaplane application.
 *
 * We use configureStore from Redux Toolkit, which simplifies store setup,
 * automatically combines slice reducers, adds necessary middleware like redux-thunk,
 * and enables the Redux DevTools Extension.
 */
export const store = configureStore({
    reducer: {
        // Register the reducer from each slice here
        notification: notificationReducer,
        adminmenu: menuReducer,
        app: appReducer,
        workflows: workflowsReducer,
        logs:logsReducer,
        connections:connectionsReducer,
        setting:settingReducer,
        dashboard:dashboardReducer
        // Future reducers will be added here (e.g., points, settings)
    },
    middleware: (getDefaultMiddleware) =>
        getDefaultMiddleware().concat(logger),
});