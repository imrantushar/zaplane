import React, { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { FiMoreHorizontal } from "react-icons/fi";
import { HiDotsHorizontal } from "react-icons/hi";
import './styles.scss';
import Button from '@ZAPComponents/Button';
const OptionMenu = props => {
  const {
    icon = HiDotsHorizontal,
    options = [],
    iconClass,
    suffix = '',
    alwaysShowOptions = false
  } = props;
  const [itemSelected, setItemSelected] = useState(false);
  const menuItemRef = useRef(null);
  const relativeTo = useRef(null);
  const handleClick = e => {
    if (menuItemRef?.current && !menuItemRef?.current?.contains(e.target) && !relativeTo.current.contains(e.target)) {
      setItemSelected(false);
    }
  };
  const handleMenuToggle = () => {
    setItemSelected(!itemSelected);
  };
  useEffect(() => {
    if (!alwaysShowOptions) {
      document.addEventListener('mousedown', handleClick);
      return () => document.removeEventListener('mousedown', handleClick);
    }
  }, [alwaysShowOptions]);
  useEffect(() => {
    if (alwaysShowOptions) {
      return;
    }
    if (itemSelected && relativeTo.current) {
      const rect = relativeTo.current.getBoundingClientRect();
      const x = rect.left + window.pageXOffset;
      const y = rect.top + window.pageYOffset;
      const buttonHeight = relativeTo.current.offsetHeight;
      menuItemRef.current.style.position = 'absolute';
      menuItemRef.current.style.left = `${x - 160}px`;
      menuItemRef.current.style.top = `${y + buttonHeight + 2}px`;
      document.body.appendChild(menuItemRef.current);
    } else if (menuItemRef.current && menuItemRef.current.parentNode === document.body) {
      document.body.removeChild(menuItemRef.current);
    }
  }, [itemSelected, alwaysShowOptions]);
  const renderOptions = () => <div className={`zaplane-dropdown-menu__lists  ${alwaysShowOptions ? 'zaplane-dropdown-menu--inline' : ''} ${suffix && `zaplane-dropdown-menu--list-${suffix}`}`} ref={menuItemRef}>
			<ul className={`${alwaysShowOptions ? 'zaplane-dropdown-menu__inline' : 'zaplane-more-options'}`}>
				{options.map((item, itemIndex) => {
        const handleItemClick = () => {
          setItemSelected(false);
          if ('button' === item.type) {
            return item?.onClick();
          }
          return null;
        };
        return <React.Fragment key={itemIndex}>
							{item.action ? <form className={`${alwaysShowOptions ? 'zaplane-dropdown-menu__inline-form' : 'zaplane-more-options__item'}`} action={item.action} method={item.method}>
									<Button preset="transparent" iconPosition="left" {...item} suffix={`${alwaysShowOptions ? 'inline' : 'block'}`} />
									{item.hasBorder && <hr className="zaplane-option-separator" />}
								</form> : <li className={`${alwaysShowOptions ? 'zaplane-dropdown-menu__inline-form' : 'zaplane-more-options__item'}`}>
									<Button preset="transparent" iconPosition="left" {...item} onClick={handleItemClick} />
									{item.hasBorder && <hr className="zaplane-option-separator" />}
								</li>}
						</React.Fragment>;
      })}
			</ul>
		</div>;
  return <>
			{!alwaysShowOptions && <button className={`zaplane-dropdown-menu ${suffix && `zaplane-dropdown-menu--${suffix}`}`} type="button" ref={relativeTo} onClick={handleMenuToggle}>
				{iconClass ? iconClass : React.createElement(icon)}
				</button>}
			{alwaysShowOptions ? renderOptions() : itemSelected && createPortal(renderOptions(), document.body)}
		</>;
};
export default OptionMenu;