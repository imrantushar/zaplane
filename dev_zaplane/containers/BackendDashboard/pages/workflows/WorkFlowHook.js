import CreateWorkflows from ".";
import Workflows from "./workFlowMotion";
import { Provider } from 'react-redux';
import { BrowserRouter as Router } from 'react-router-dom';
import { store } from '@ZAPRedux/store';

const ZaplaneWrapper = ({ children }) => (
	<Provider store={store}>
		<Router>
			<div className="zaplane-scope">
				{children}
			</div>
		</Router>
	</Provider>
);

export const WorkFlowHook = () => {
	wp.hooks.addFilter(
		'zaplane-integration.workflow-content.content',
		'zaplane.workflow-content',
		(nullValue, props) => (
			<ZaplaneWrapper>
				<CreateWorkflows {...props} />
			</ZaplaneWrapper>
		)
	);

	wp.hooks.addFilter(
		'zaplane-integration.workflow-editor.content',
		'zaplane.workflow-editor',
		(nullValue, props) => (
			<ZaplaneWrapper>
				<Workflows id={props.id} onNavigateBack={props.onNavigateBack} renderTopBar={props.renderTopBar} />
			</ZaplaneWrapper>
		)
	);
}