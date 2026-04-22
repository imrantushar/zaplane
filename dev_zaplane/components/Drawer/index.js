import { Dialog, DialogPanel, DialogTitle, Transition, TransitionChild } from '@headlessui/react';
import React, { Fragment } from 'react';
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";
import { FiArrowLeft } from "react-icons/fi";
import { IoIosArrowBack, IoMdClose } from "react-icons/io";

const ZAPDrawer = ({
  trigger,
  children,
  title,
  footer,
  placement = "end",
  size = "md",
  closeOnOverlayClick = false,
  zIndex = 9999,
  onClose,
  open,
  arrowClose,
  isFullscreen = false,
  arrowOnClick,
  maxWidth = "max-w-md"
}) => {
  return (
    <>
      <div onClick={() => open = true}>
          {trigger}
      </div>
      <Transition appear show={open} as={Fragment}>
        <Dialog 
          as="div" 
          className="relative z-50" 
          onClose={closeOnOverlayClick ? onClose : () => {}}
          style={{ zIndex }}
        >
          <TransitionChild
            as={Fragment}
            enter="ease-out duration-300"
            enterFrom="opacity-0"
            enterTo="opacity-100"
            leave="ease-in duration-200"
            leaveFrom="opacity-100"
            leaveTo="opacity-0"
          >
            <div className="fixed inset-0 bg-black/25" />
          </TransitionChild>

          <div className={`fixed inset-y-0 ${placement === 'start' ? 'left-0' : 'right-0'} flex max-w-full ${isFullscreen ? '' : 'mt-8 '}`}>
            <TransitionChild
              as={Fragment}
              enter="transform transition ease-in-out duration-300 sm:duration-500"
              enterFrom={placement === 'start' ? '-translate-x-full' : 'translate-x-full'}
              enterTo="translate-x-0"
              leave="transform transition ease-in-out duration-300 sm:duration-500"
              leaveFrom="translate-x-0"
              leaveTo={placement === 'start' ? '-translate-x-full' : 'translate-x-full'}
            >
              <DialogPanel className={`pointer-events-auto w-screen ${isFullscreen ? 'max-w-full' : maxWidth}`}>
                <div className="flex h-full flex-col overflow-y-scroll bg-white shadow-xl">
                  {title && (
                    <div className="px-4 py-6 sm:px-6 flex items-center justify-between ">
                      <div className="flex items-center">
                        {arrowClose && (
                          <button
                            type="button"
                            className="mr-3 text-gray-400 hover:text-gray-500 focus:outline-none"
                            onClick={arrowOnClick || onClose}
                          >
                            <span className="sr-only">Close panel</span>
                            <IoIosArrowBack className="h-6 w-6" aria-hidden="true" />
                          </button>
                        )}
                        <DialogTitle className="text-base font-semibold leading-6 text-gray-900 m-0">
                          <ZAPLabel label={title} type={"bold"} />
                        </DialogTitle>
                      </div>
                      <div className="ml-3 flex h-7 items-center">
                        <button
                          type="button"
                          className="relative rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none"
                          onClick={onClose}
                        >
                          <span className="absolute -inset-2.5" />
                          <span className="sr-only">Close panel</span>
                          <IoMdClose className="h-6 w-6" aria-hidden="true" />
                        </button>
                      </div>
                    </div>
                  )}
                  <div className="relative flex-1 px-4 pb-6 sm:px-6 overflow-x-hidden">
                    {typeof children === "function" ? children({ onClose }) : children}
                  </div>
                  {footer && (
                    <div className="border-t border-gray-200 px-4 py-4 sm:px-6">
                      {typeof footer === "function" ? footer({ onClose }) : footer}
                    </div>
                  )}
                </div>
              </DialogPanel>
            </TransitionChild>
          </div>
        </Dialog>
      </Transition>
    </>
  );
};
export default ZAPDrawer;