import { Flex, Image } from '@chakra-ui/react';
import { __ } from '@wordpress/i18n';
import ZAPLabel from '@ZAPComponents/Labels/ZAPLabel';
import TopBar from '@ZAPComponents/TopBar';
import { plugin_root_url, route_path } from '@ZAPUtils/helper';
import React from 'react';
import { IoIosArrowForward } from 'react-icons/io';

import FolderTable from './FolderTable';

const Folder = ({ id }) => {
    return (
        <>
            <TopBar
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
                            <Image
                                src={`${plugin_root_url}assets/images/zaplane.svg`}
                                boxSize="20px"
                            />
                        </Flex>

                        <IoIosArrowForward/>

                        <ZAPLabel
                            as="h2"
                            type="subtitle"
                            fontWeight="medium"
                            href={`${route_path}admin.php?page=zaplane-folders`}
                            label={__("Folder", "zaplane")}
                        />
                        <IoIosArrowForward/>

                        <ZAPLabel
                            as="h2"
                            type="subtitle"
                            fontWeight="medium"
                            label={__("Workflows", "zaplane")}
                        />
                    </>
                    
                )}
            />
             <div className="zaplane-page-content">
                <FolderTable folderId={id}/>
             </div>

        </>
    );
};

export default Folder;