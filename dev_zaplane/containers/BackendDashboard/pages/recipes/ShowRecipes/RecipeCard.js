import { Box, Text, Stack, Flex, Button } from '@chakra-ui/react';
import { __ } from '@wordpress/i18n';
import { deleteRecipe, recipeToWorkflow, workflowToRecipe } from '@ZAPRedux/Slices/recipeSlice/actions/recipe';
import { useDispatch } from 'react-redux';

const RecipeCard = ({ recipe }) => {
    const dispatch = useDispatch()
    const deletedRecipe = () => {
        dispatch(deleteRecipe(recipe.id))

    }
    const createRecipe = () => {
        const payload = {
            title: recipe.title,
            description: recipe.description,
            thumbnail_id: recipe.thumbnail_id,
            folder_id: 0
        };

        dispatch(
            recipeToWorkflow({
                recipeId: recipe.id,
                payload: payload
            })
        );
    };
    return (
        <Box
            border="1px solid"
            borderColor="gray.200"
            borderRadius="12px"
            p="16px"
            bg="white"
            _hover={{ boxShadow: 'md' }}
        >
            <Stack spacing="10px">
                {/* Title */}
                <Text fontWeight="600" fontSize="md">
                    {recipe.title}
                </Text>

                {/* Description */}
                <Text fontSize="sm" color="gray.500" noOfLines={2}>
                    {recipe.description || __('No description', 'zaplane')}
                </Text>

                {/* Buttons */}
                <Flex gap="8px" pt="8px" wrap="wrap">
                    <Button
                        size="sm"
                        colorScheme="blue"
                        onClick={createRecipe}
                    >
                        {__('Convert', 'zaplane')}
                    </Button>

                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() => onMove(recipe)}
                    >
                        {__('Move', 'zaplane')}
                    </Button>

                    <Button
                        size="sm"
                        colorScheme="red"
                        variant="outline"
                        onClick={deletedRecipe}
                    >
                        {__('Delete', 'zaplane')}
                    </Button>
                </Flex>
            </Stack>
        </Box>
    );
};
export default RecipeCard