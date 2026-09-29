import React, { useEffect } from 'react';
import { Link } from 'react-router-dom';
import { __ } from '@wordpress/i18n';
import {
	route_path,
	plugin_root_url,
	useQuery,
} from '@ZAPUtils/helper';
import { useSelector } from 'react-redux';
import MenuItem from './MenuItem';

const AdminMenu = () => {
	const adminmenu = useSelector( ( state ) => state.adminmenu.data );
	const location = useQuery();
	const page = location.get( 'page' );
	const path = location.get( 'path' );
	useEffect( () => {
		document.title =
			adminmenu[ page ]?.title + ' - ' + __( 'Zaplane', 'zaplane' );
	}, [ page ] );

	return (
		<React.Fragment>
				<Link
					to={ `${ route_path }admin.php?page=zaplane` }
					className="wp-has-submenu wp-has-current-submenu wp-menu-open menu-top toplevel_page_zaplane menu-top-last"
					aria-haspopup="false"
				>
					<div className="wp-menu-arrow">
						<div></div>
					</div>
					<div
						className="wp-menu-image svg"
						style={ {
							backgroundImage: `url(${plugin_root_url}assets/images/menu-icon.svg)`,
						} }
						aria-hidden="true"
					>
						<br />
					</div>
					<div className="wp-menu-name">
						{ __( 'Zaplane', 'zaplane' ) }
					</div>
				</Link>
				<ul className="wp-submenu wp-submenu-wrap">
					<li className="wp-submenu-head" aria-hidden="true">
						{ __( 'zaplane', 'zaplane' ) }
					</li>
					{ Object.entries( adminmenu ).map( ( [ key, item ], index ) => {
						const menuItemClassName =
							index === 0 ? 'wp-first-item ' : '';
						return (
							<MenuItem
								className={
									page === key
										? menuItemClassName + 'current'
										: menuItemClassName
								}
								key={ index }
								parent={ key }
								currentPath={ path }
								subMenuItems={ item.sub_items }
							>
								<Link
									to={ `${ route_path }admin.php?page=${ key }` }
								>
									{ item.title }
									{ item?.sub_items && (
										<span className="zaplane-icon zaplane-icon--angle-right"></span>
									) }
								</Link>
							</MenuItem>
						);
					} ) }
				</ul>
		</React.Fragment>
	);
};

export default AdminMenu;
