import React, { useState } from 'react';
import { Button, Box, Text } from '@chakra-ui/react';
import ZAPDrawer from '@ZAPComponents/Drawer';
import { __ } from '@wordpress/i18n';
import { outlineBtn } from '../../../assets/scss/chakra/recipe';

const whatsNewContent = [
    {
        version: 'v2.1.0',
        age: '1 week ago',
        title: "Fresh Out of OttoKit",
        sections: [
            {
                label: 'New Triggers',
                tag: 'trigger',
                items: [
                    { bold: 'FluentBoards:', text: '3 new triggers — Task Updated, Assignees Updated, and Stage Updated.', note: '(fires when task title, members, or stages change)' },
                    { bold: 'WordPress:', text: 'Post Updated — fires only when an existing post is updated, not on creation.' },
                ]
            },
            {
                label: 'New Actions',
                tag: 'action',
                items: [
                    { bold: 'The Events Calendar:', text: 'Create Event — programmatically create events via automations.' },
                ]
            },
            {
                label: 'Integration Updates',
                tag: 'integration',
                items: [
                    { bold: 'Notion — Find Database Item:', text: 'Multiple filter support. Results are loop-compatible and fully iterable.' },
                ]
            },
            {
                label: 'Improvements',
                tag: 'improvement',
                items: [
                    { bold: '', text: 'Added RTL text direction support across the OttoKit plugin UI.' },
                ]
            },
        ],
        readMoreUrl: 'https://zaplane.com',
    },
];

const TAG_STYLES = {
    trigger:     { bg: 'rgba(59,196,143,0.12)', color: '#0b8f5e', label: 'Trigger' },
    action:      { bg: 'rgba(59,130,246,0.12)', color: '#1a61c4', label: 'Action' },
    integration: { bg: 'rgba(249,168,37,0.12)', color: '#a06000', label: 'Integration' },
    improvement: { bg: 'rgba(148,163,184,0.12)', color: '#526070', label: 'UI' },
};

const SectionTag = ({ type }) => {
    const s = TAG_STYLES[type] || TAG_STYLES.improvement;
    return (
        <Box
            as="span"
            display="inline-block"
            fontSize="10px"
            fontWeight="600"
            px="6px"
            py="1px"
            borderRadius="4px"
            mr="5px"
            textTransform="uppercase"
            letterSpacing="0.04em"
            bg={s.bg}
            color={s.color}
        >
            {s.label}
        </Box>
    );
};

const WhatsNew = () => {
    const [open, setOpen] = useState(false);

    return (
        <ZAPDrawer
            open={open}
            onClose={() => setOpen(false)}
            closeOnOverlayClick
            trigger={
                <Button {...outlineBtn} onClick={() => setOpen(true)}>
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--zaplane-font-color)" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
                        <g transform="scale(0.9) translate(1.5,1.5)">
                            <path d="M11 6a13 13 0 0 0 8.4-2.8A1 1 0 0 1 21 4v12a1 1 0 0 1-1.6.8A13 13 0 0 0 11 14H5a2 2 0 0 1-2-2V8a2 2 0 0 1 5-2z" />
                            <path d="M6 14a12 12 0 0 0 2.4 7.2 2 2 0 0 0 3.2-2.4A8 8 0 0 1 10 14" />
                            <path d="M8 6v8" />
                        </g>
                    </svg>
                    {__("What's New")}
                </Button>
            }
            title={__("What's New", "zaplane")}
            size="sm"
        >
            <Box>
                {whatsNewContent.map((entry, i) => (
                    <Box
                        key={i}
                        borderBottom="1px solid"
                        borderColor="var( --zaplane-border-color)"
                        _last={{ borderBottom: 'none' }}
                    >
                        {/* Meta row */}
                        <Box display="flex" alignItems="center" gap="8px" mb="10px">
                            <Text fontSize="11px" m='0' fontWeight="500" color="gray.400" textTransform="uppercase" letterSpacing="0.06em">
                                {entry.age}
                            </Text>
                            <Box w="4px" h="4px" bg="gray.300" borderRadius="full" />
                            <Box
                                as="span"
                                fontSize="11px"
                                fontWeight="600"
                                color="var(--zaplane-primary)"
                                // bg="var(--zaplane-primary)"
                                px="8px"
                                py="2px"
                                borderRadius="full"
                            >
                                {entry.version}
                            </Box>
                        </Box>

                        {/* Title */}
                        <Text
                            fontSize="22px"
                            fontWeight="400"
                            lineHeight="1.25"
                            mb="18px"
                        >
                            {entry.title}
                        </Text>

                        {/* Sections */}
                        {entry.sections.map((section, si) => (
                            <Box key={si}>
                                {/* Section label */}
                                <Box
                                    display="flex"
                                    alignItems="center"
                                    gap="6px"
                                    mt="16px"
                                    mb="8px"
                                    fontSize="11px"
                                    fontWeight="600"
                                    textTransform="uppercase"
                                    letterSpacing="0.08em"
                                    color="gray.400"
                                    _after={{
                                        content: '""',
                                        flex: 1,
                                        height: '1px',
                                        bg: 'gray.100',
                                    }}
                                >
                                    {section.label}
                                </Box>

                                {/* Items */}
                                <Box as="ul" listStyleType="none" p="0" m="0" display="flex" flexDir="column" gap="7px">
                                    {section.items.map((item, ii) => (
                                        <Box
                                            as="li"
                                            key={ii}
                                            fontSize="13.5px"
                                            lineHeight="1.55"
                                            color="gray.600"
                                            pl="14px"
                                            position="relative"
                                            _before={{
                                                content: '""',
                                                position: 'absolute',
                                                left: 0, top: '8px',
                                                w: '5px', h: '5px',
                                                borderRadius: 'full',
                                                bg: 'purple.300',
                                            }}
                                        >
                                            <SectionTag type={section.tag} />
                                            {item.bold && <Text as="strong" color="gray.700" fontWeight="600">{item.bold} </Text>}
                                            {item.text}
                                            {item.note && <Text as="em" fontStyle="normal" fontSize="12.5px" color="gray.400"> {item.note}</Text>}
                                        </Box>
                                    ))}
                                </Box>
                            </Box>
                        ))}

                        {/* Read more */}
                        {entry.readMoreUrl && (
                            <Box mt="14px" fontSize="12.5px">
                                Read more at{' '}
                                <Box as="a" href={entry.readMoreUrl} target="_blank" color="var(--zaplane-primary)" fontWeight="500" _hover={{ textDecoration: 'underline' }}>
                                  {__('zaplane','zaplane')}
                                </Box>
                            </Box>
                        )}
                    </Box>
                ))}
            </Box>
        </ZAPDrawer>
    );
};

export default WhatsNew;