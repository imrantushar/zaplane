import { Box, Flex, Image, SimpleGrid, Text } from '@chakra-ui/react';
import { __ } from '@wordpress/i18n';
import ZAPLabel from '@ZAPComponents/Labels/ZAPLabel';
import TopBar from '@ZAPComponents/TopBar';
import RecipeFolderTree from '@ZAPComponents/RecipeFolderTree';
import { plugin_root_url, route_path } from '@ZAPUtils/helper';
import React, { useEffect } from 'react';
import { IoIosArrowForward } from 'react-icons/io';
import { useDispatch, useSelector } from 'react-redux';
import { useNavigate } from 'react-router-dom';
import { getRecipeFolders, getRecipes } from '@ZAPRedux/Slices/recipeSlice/actions/recipe';
import RecipeCard from './RecipeCard';

const ShowRecipes = ({ id }) => {
    const navigate = useNavigate();
    const dispatch = useDispatch();
    const { recipes, folders, loadingFolders } = useSelector((state) => state.recipes);
    const folderId = Number.isFinite(Number(id)) && Number(id) > 0 ? Number(id) : null;

    useEffect(() => {
        dispatch(getRecipeFolders());
    }, [dispatch]);

    useEffect(() => {
        dispatch(getRecipes(folderId !== null ? { folder_id: folderId } : {}));
    }, [dispatch, folderId]);

    const handleSelectFolder = (selectedId) => {
        const base = `${route_path}admin.php?page=zaplane-recipes&action=edit`;
        if (selectedId === null) {
            navigate(base);
            return;
        }

        navigate(`${base}&id=${selectedId}`);
    };

    return (
        <div>
          <TopBar
                leftContent={() => (
                    <>
                        <Flex
                            height="40px"
                            width="40px"
                            borderRadius="20px"
                            gap="10px"
                            background="var(--zaplane-second-primary)"
                            alignItems="center"
                            justifyContent="center"
                        >
                            <Image
                                src={`${plugin_root_url}assets/images/zaplane.svg`}
                                boxSize="20px"
                            />
                        </Flex>
                        <IoIosArrowForward />
                        <ZAPLabel
                            as="h2"
                            color="var(--zapplane-font-color)"
                            type="subtitle"
                            fontWeight="medium"
                            label={__("Recipe Library", "zaplane")}
                        />
                    </>
                )}
            />

            <div className="zaplane-page-content">
                <Flex gap="22px" wrap="wrap" align="flex-start">
                    <Box minW={{ base: '100%', md: '320px' }} flex={{ base: '1 1 100%', md: '0 0 320px' }}>
                        <RecipeFolderTree
                            folders={folders}
                            selectedFolderId={folderId}
                            onSelectFolder={handleSelectFolder}
                            loading={loadingFolders}
                        />
                    </Box>

                    <Box flex="1 1 0">
                        {recipes?.length > 0 ? (
                            <SimpleGrid columns={{ base: 1, md: 2, xl: 3 }} spacing={4}>
                                {recipes.map((recipe) => (
                                    <RecipeCard key={recipe.id} recipe={recipe} />
                                ))}
                            </SimpleGrid>
                        ) : (
                            <Box p={8} borderRadius="12px" bg="white" border="1px solid var(--zaplane-border-color)">
                                <Text fontSize="16px" color="var(--zaplane-text-muted)">
                                    {__(
                                        'No recipes found in this folder. Select another folder or save a workflow as a recipe.',
                                        'zaplane'
                                    )}
                                </Text>
                            </Box>
                        )}
                    </Box>
                </Flex>
            </div>
        </div>
    );
};

export default ShowRecipes;