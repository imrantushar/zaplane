import { Box, Flex, Image, SimpleGrid, Text, Button } from '@chakra-ui/react';
import { __ } from '@wordpress/i18n';
import ZAPLabel from '@ZAPComponents/Labels/ZAPLabel';
import TopBar from '@ZAPComponents/TopBar';
import { plugin_root_url, route_path } from '@ZAPUtils/helper';
import React, { useEffect } from 'react';
import { IoIosArrowForward } from 'react-icons/io';
import { useDispatch, useSelector } from 'react-redux';
import { useNavigate } from 'react-router-dom';
import { getRecipeFolders, getRecipes } from '@ZAPRedux/Slices/recipeSlice/actions/recipe';
import RecipeCard from './RecipeCard';
import FolderCard from '../FolderCard';
import { findFolder } from './helper';


const ShowRecipes = ({ id }) => {
    const navigate = useNavigate();
    const dispatch = useDispatch();
    const { recipes, folders = [], loadingFolders } = useSelector((state) => state.recipes);
    const folderId = Number.isFinite(Number(id)) && Number(id) > 0 ? Number(id) : null;

    useEffect(() => {
        dispatch(getRecipeFolders());
    }, [dispatch]);

    useEffect(() => {
        dispatch(getRecipes(folderId ? { folder_id: folderId } : {}));
    }, [dispatch, folderId]);

    const handleNavigate = (selectedId) => {
        const base = `${route_path}admin.php?page=zaplane-recipes&action=edit`;
        navigate(selectedId ? `${base}&id=${selectedId}` : base);
    };

    const currentFolder = folderId ? findFolder(folders, folderId) : null;
    const childFolders = folderId ? (currentFolder?.children || []) : folders;
    const parentId = currentFolder?.parent_id || null;

    return (
        <div>
            <TopBar
                leftContent={() => (
                    <>
                        <Flex
                            height="40px" width="40px" borderRadius="20px"
                            background="var(--zaplane-second-primary)"
                            alignItems="center" justifyContent="center"
                        >
                            <Image src={`${plugin_root_url}assets/images/zaplane.svg`} boxSize="20px" />
                        </Flex>
                        <Flex align="center" gap="6px" wrap="wrap">
                            <ZAPLabel
                                as="span" type="subtitle" fontWeight="medium"
                                label={__("Recipe Library", "zaplane")}

                            />
                        </Flex>
                    </>
                )}
            />

            <div className="zaplane-page-content">

                {folderId && (
                    <Button
                        variant="ghost" size="sm" mb={5}
                        onClick={() => handleNavigate(parentId)}
                    >
                        ← {__("Back", "zaplane")}
                    </Button>
                )}

                <Flex direction="column" gap="32px">
                    {childFolders.length > 0 && (
                        <Box>
                            <Text
                                className='zaplane-label'
                                fontWeight="700" fontSize="12px"
                                color="var(--zaplane-text-muted)"
                                textTransform="uppercase" letterSpacing="0.07em" mb={3}
                            >
                                {__("Folders", "zaplane")}
                            </Text>
                            <SimpleGrid columns={{ base: 1, md: 2, xl: 3 }} spacing={4} gap='20px'>
                                {childFolders.map((folder) => (
                                    <FolderCard key={folder.id} folder={folder} />
                                ))}
                            </SimpleGrid>
                        </Box>
                    )}
                    {recipes?.length > 0 && (
                        <Box>
                            <Text
                                className='zaplane-label'
                                fontWeight="700" fontSize="12px"
                                color="var(--zaplane-text-muted)"
                                textTransform="uppercase" letterSpacing="0.07em" mb={3}
                            >
                                {__("Recipes", "zaplane")}
                            </Text>
                            <SimpleGrid columns={{ base: 1, md: 2, xl: 3 }} spacing={4} gap='20px'>
                                {recipes.map((recipe) => (
                                    <RecipeCard key={recipe.id} recipe={recipe} />
                                ))}
                            </SimpleGrid>
                        </Box>
                    )}

                    {childFolders.length === 0 && !recipes?.length && (
                        <Box p={8} borderRadius="12px" bg="white" border="1px solid var(--zaplane-border-color)">
                            <Text fontSize="16px" className='zaplane-label' color="var(--zaplane-text-muted)">
                                {__('No recipes or subfolders found here.', 'zaplane')}
                            </Text>
                        </Box>
                    )}
                </Flex>
            </div>
        </div>
    );
};

export default ShowRecipes;