import React, { useEffect, useRef } from 'react';
import { useSelector, useDispatch } from 'react-redux';
import { __ } from '@wordpress/i18n';
import { createPortal } from 'react-dom';
import { showNotification } from '@ZAPRedux/Slices/notificationSlice/notificationSlice';
import {
  CheckCircle,
  AlertCircle,
  Info,
  AlertTriangle,
  X,
} from "lucide-react";
import Button from "@ZAPComponents/Button";

// 🔹 Icon component
const getIcon = (type) => {
  switch (type) {
    case "error":
      return <AlertCircle className="w-5 h-5 text-[var(--zaplane-danger)]" />;
    case "info":
      return <Info className="w-5 h-5 text-blue-600" />;
    case "warning":
      return <AlertTriangle className="w-5 h-5 text-yellow-600" />;
    default:
      return <CheckCircle className="w-5 h-5 text-green-600" />;
  }
};
const Notification = () => {
  const notificationRef = useRef(null);
  const targetElement = document.querySelector('#zaplane-app');
  const notification = useSelector(state => state.notification);
  const dispatch = useDispatch();
  const isShowNotification = notification?.showNotification || notification?.isShow;
  useEffect(() => {
    if (isShowNotification && targetElement) {
      notificationRef.current.style.position = 'fixed';
      notificationRef.current.style.left = `50%`; // Adjust left position as needed
      notificationRef.current.style.transform = 'translateX(-50%)';
      notificationRef.current.style.bottom = `50px`; // Place it at the bottom
      document.body.appendChild(notificationRef.current);
    } else if (notificationRef.current && notificationRef.current.parentNode === document.body) {
      document.body.removeChild(notificationRef.current);
    }
    return () => {
      if (notificationRef.current && notificationRef.current.parentNode === document.body) {
        document.body.removeChild(notificationRef?.current);
      }
    };
  }, [isShowNotification]);

  useEffect(() => {
    if (isShowNotification) {
      const timeout_id = setTimeout(() => {
        closeHandler();
      }, 6000);
      return () => clearTimeout(timeout_id);
    }
  }, [isShowNotification]);

  const closeHandler = () => {
    dispatch(showNotification({
      message: '',
      isShow: false
    }));
  };
  return <>
    {isShowNotification && createPortal(<div className={`zaplane-notification ${notification.type && `zaplane-notification--${notification.type}`}`} ref={notificationRef}>
      <div className="zaplane-notification__message">
        <div className="flex items-center justify-center w-6 h-6 rounded-full bg-[var(--zaplane-secondary-color)]">
          {getIcon(notification.type)}
        </div>
        {notification.isHtml ? <div dangerouslySetInnerHTML={{
          __html: notification.message
        }} /> : notification.message}
      </div>
      <Button 
        onClick={closeHandler} 
        aria-label={__('Close notification', 'zaplane')} 
        preset='transparent' 
        suffix="close"
        icon={
          <span className="zaplane-icon zaplane-icon--close-x" />
        }
      />
    </div>, document.body)}
  </>;
};
export default Notification;