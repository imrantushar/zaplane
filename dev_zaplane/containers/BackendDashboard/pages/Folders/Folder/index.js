import { Flex, Image } from '@chakra-ui/react';
import { __ } from '@wordpress/i18n';
import ZAPLabel from '@ZAPComponents/Labels/ZAPLabel';
import TopBar from '@ZAPComponents/TopBar';
import { plugin_root_url, route_path } from '@ZAPUtils/helper';
import React, { useEffect } from 'react';
import { IoIosArrowForward } from 'react-icons/io';

import { getFolders } from '@ZAPRedux/Slices/folderSlice/folderSlice';
import { useDispatch, useSelector } from 'react-redux';
import WorkflowTable from '@ZAPComponents/WorkflowTable';

const Folder = ({ id }) => {
    const dispatch =useDispatch()
     const { folders } = useSelector((state) => state.folder);
      const allFolders = folders?.data || [];
    
      useEffect(() => {
        dispatch(getFolders());
      }, [dispatch]);
      const title = allFolders.find((v)=>v.id === id)
    return (
        <> <TopBar
            leftContent={() => (
                <>
                    <Flex
                        height="40px"
                        width="40px"
                        borderRadius="20px"
                        background="var(--zaplane-second-primary)"
                        alignItems="center"
                        justifyContent="center"
                    >
                        <img
                            src={`${plugin_root_url}assets/images/zaplane.svg`}
                        />
                    </Flex>

                    <IoIosArrowForward />

                    <ZAPLabel
                        as="h2"
                        type="subtitle"
                        fontWeight="medium"
                        href={`${route_path}admin.php?page=zaplane-folders`}
                        label={__("Folders", "zaplane")}
                    />
                    <IoIosArrowForward />

                    <ZAPLabel
                        as="h2"
                        type="subtitle"
                        fontWeight="medium"
                        label={title?.title}
                    />
                </>

            )}
        />
            <div className="zaplane-page-content">
              <WorkflowTable folderId={id} showHeader />
            </div>

        </>
    );
};

export default Folder;