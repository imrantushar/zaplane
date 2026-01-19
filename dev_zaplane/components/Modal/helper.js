import classNames from 'classnames';

export function suffixClassNames( suffix, expandableSize ) {
	return classNames(
		'zaplane-react-modal',
		suffix && `zaplane-react-modal--${ suffix }`,
		expandableSize && `zaplane-react-modal--${ expandableSize }`
	);
}

export function contentClassNames( expandableSize ) {
	return classNames(
		'zaplane-react-modal__content',
		expandableSize && `zaplane-react-modal--content-${ expandableSize }`
	);
}
