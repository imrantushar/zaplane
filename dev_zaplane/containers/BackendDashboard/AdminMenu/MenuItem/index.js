import { route_path } from '@ZAPUtils/helper';
import React from 'react';
import { Link } from 'react-router-dom';

export default function MenuItem( props ) {
	const { className, children, parent, currentPath, subMenuItems } = props;

	const redirectLink = ( item ) => {
		if ( item.slug ) {
			if ( item.slug === 'question' ) {
				return `${ route_path }admin.php?page=${ parent }`;
			}
			return `${ route_path }admin.php?page=${ parent }&path=${ item.slug }`;
		}
		return `${ route_path }admin.php?page=${ parent }`;
	};

	return (
		<React.Fragment>
			<li className={ className }>
				{ children }
				{ className === 'current' && subMenuItems && (
					<>
						<ul className="wp-submenu">
							{ subMenuItems.map( ( item, index ) => {
								return (
									<li
										className={
											( ! item.slug && ! currentPath ) ||
											currentPath === item.slug
												? 'current'
												: ''
										}
										key={ index }
									>
										<Link to={ redirectLink( item ) }>
											{ item.title }
										</Link>
									</li>
								);
							} ) }
						</ul>
					</>
				) }
			</li>
		</React.Fragment>
	);
}
