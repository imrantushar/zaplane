import React, { Fragment } from 'react';
import { __ } from '@wordpress/i18n';
import { IoIosArrowForward } from "react-icons/io";
import { plugin_root_url } from "@ZAPUtils/helper";
import TopBar from "@ZAPComponents/TopBar";
import SubTopBar from "@ZAPComponents/SubTopBar";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";

/**
 * Standardized Page Layout for Zaplane Dashboard
 * 
 * @param {Object} props
 * @param {string} [props.title] - Breadcrumb title (legacy)
 * @param {Array<{label: string, href?: string}>} [props.breadcrumbs] - Array of breadcrumb segments
 * @param {string} [props.heading] - SubTopBar heading (defaults to title or last breadcrumb)
 * @param {React.ReactNode} [props.actions] - Action components (buttons, etc)
 * @param {boolean} [props.isLoading] - Loading state
 * @param {React.ComponentType} [props.skeleton] - Skeleton component to show during loading
 * @param {React.ReactNode} props.children - Main content
 */
const PageLayout = ({
  title,
  breadcrumbs,
  heading,
  actions,
  children,
  isLoading,
  skeleton: Skeleton,
  topBarStyles = {}
}) => {
  if (isLoading && Skeleton) {
    return <Skeleton />;
  }
  const renderBreadcrumbs = () => {
    if (breadcrumbs && Array.isArray(breadcrumbs)) {
      return breadcrumbs.map((crumb, index) => <Fragment key={index}>
                    <ZAPLabel as="h2" type="subtitle" fontWeight="medium" href={crumb.href} label={__(crumb.label, "zaplane")} />
                    {index < breadcrumbs.length - 1 && <IoIosArrowForward />}
                </Fragment>);
    }
    return <ZAPLabel as="h2" type="subtitle" fontWeight="medium" label={__(title, "zaplane")} />;
  };
  const displayHeading = heading || (breadcrumbs ? breadcrumbs[breadcrumbs.length - 1].label : title);
  return <>
            <TopBar topBarStyles={topBarStyles} leftContent={() => <>
                        <div style={{height:'40px', width:'40px', background:'var(--zaplane-second-primary)'}} className="flex rounded-[20px] items-center justify-center">
                            <img src={`${plugin_root_url}assets/images/zaplane.svg`} alt="Zaplane" />
                        </div>

                        <IoIosArrowForward />
                        {renderBreadcrumbs()}
                    </>} />
            
            <SubTopBar heading={__(displayHeading, "zaplane")}>
                {actions}
            </SubTopBar>

            <div className="zaplane-page-content">
                {children}
            </div>
        </>;
};
export default PageLayout;