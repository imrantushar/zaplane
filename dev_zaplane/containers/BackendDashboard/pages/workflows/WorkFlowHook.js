import CreateWorkflows from ".";

export const WorkFlowHook = () => {
wp.hooks.addFilter(
		'zaplane-integration.workflow-content.content',
		'zaplane.workflow-content',
		(nullValue, props) => <CreateWorkflows {...props} />
	);
}