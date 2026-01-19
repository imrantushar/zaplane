import classNames from 'classnames';

export function suffixClassNames( suffix, expandableSize ) {
	return classNames(
		'quizleap-react-modal',
		suffix && `quizleap-react-modal--${ suffix }`,
		expandableSize && `quizleap-react-modal--${ expandableSize }`
	);
}

export function contentClassNames( expandableSize ) {
	return classNames(
		'quizleap-react-modal__content',
		expandableSize && `quizleap-react-modal--content-${ expandableSize }`
	);
}
