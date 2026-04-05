import React, { useState } from 'react';
import { Button, Flex, Box, Text, Heading, VStack, HStack, Grid } from '@chakra-ui/react';
import ZAPDrawer from '@ZAPComponents/Drawer';
import { __ } from '@wordpress/i18n';
import { FiClipboard, FiCheck, FiArrowRight, FiStar } from 'react-icons/fi';
import { LiaRocketPlanetSolid } from "react-icons/lia";
import { outlineBtn } from '../../../assets/scss/chakra/recipe';


const WhatsNew = () => {
    const [open, setOpen] = useState(false);

    const whatsNewContent = [
        {
            version: '2.0.0',
            date: '2024',
            features: [
                'Completely redesigned user interface with improved workflow builder',
                'Real-time workflow execution with live monitoring',
                'Advanced filter conditions with logical operators',
                'Integration with 50+ popular apps and services',
                'Custom webhook triggers for external events',
                'Email notification system built-in',
                'Advanced logging and debug tools',
                'Bulk workflow operations',
                'Workflow templates library',
                'Role-based access control'
            ],
            improvements: [
                'Performance improvements: 50% faster workflow execution',
                'Reduced memory usage by 30%',
                'Improved error handling and user feedback',
                'Better database query optimization',
                'Enhanced security features'
            ]
        },
        {
            version: '1.5.0',
            date: '2024',
            features: [
                'Schedule-based workflow triggers',
                'Conditional branching with if/else logic',
                'Loop actions for bulk operations',
                'Data transformation utilities',
                'JSON path selector'
            ]
        }
    ];

    return (
        <ZAPDrawer
            open={open}
            onClose={() => setOpen(false)}
            closeOnOverlayClick
            trigger={
                <Button
                    {...outlineBtn}
                    onClick={()=>setOpen(true)}
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--zaplane-font-color)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><g transform="scale(0.9) translate(1.5,1.5)"><path d="M11 6a13 13 0 0 0 8.4-2.8A1 1 0 0 1 21 4v12a1 1 0 0 1-1.6.8A13 13 0 0 0 11 14H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z"></path><path d="M6 14a12 12 0 0 0 2.4 7.2 2 2 0 0 0 3.2-2.4A8 8 0 0 1 10 14"></path><path d="M8 6v8"></path></g></svg>
                    {__("What's New")}
                </Button>
            }
            title={__("What's New", "zaplane")}
            size="sm"
            // maxWidth="900px"
        >
          <Box>
            hello world
          </Box>
        </ZAPDrawer>
    );
};

export default WhatsNew;
