import { useEffect, useState } from 'react';
import { Box, Text, Stack, Flex, Button, Input, VStack, Alert, AlertIcon } from '@chakra-ui/react';
import { __ } from '@wordpress/i18n';
import {
  deleteRecipe,
  recipeToWorkflow,
  updateRecipe,
} from '@ZAPRedux/Slices/recipeSlice/actions/recipe';
import { useDispatch } from 'react-redux';
import { useNavigate } from 'react-router-dom';
import { route_path } from '@ZAPUtils/helper';
import WPModal from '@ZAPComponents/Modal/WPModal';
import RecipeFolderTree from '@ZAPComponents/RecipeFolderTree';

const RecipeCard = ({ recipe }) => {
    const dispatch = useDispatch();
    const navigate = useNavigate();
    const [isConvertOpen, setIsConvertOpen] = useState(false);
    const [isMoveOpen, setIsMoveOpen] = useState(false);
    const [titleOverride, setTitleOverride] = useState(recipe?.title || '');
    const [selectedFolderId, setSelectedFolderId] = useState(
        recipe?.folder_id ?? null
    );
    const [loading, setLoading] = useState(false);
    const [warningMessage, setWarningMessage] = useState('');

    useEffect(() => {
        setTitleOverride(recipe?.title || '');
        setSelectedFolderId(recipe?.folder_id ?? null);
    }, [recipe]);

    const deletedRecipe = () => {
        dispatch(deleteRecipe(recipe.id));
    };

    const handleConvert = async () => {
        setLoading(true);
        try {
            const payload = {
                title: titleOverride?.trim() || undefined,
            };

            const response = await dispatch(
                recipeToWorkflow({
                    recipeId: recipe.id,
                    payload,
                })
            ).unwrap();

            if (
                Array.isArray(response?.connections_to_relink) &&
                response.connections_to_relink.length > 0
            ) {
                window.alert(
                    sprintf(
                        __(
                            'This workflow uses %d connection(s) that need to be re-linked. Open the workflow editor to re-connect them.',
                            'zaplane'
                        ),
                        response.connections_to_relink.length
                    )
                );
            }

            if (response?.workflow_id) {
                navigate(
                    `${route_path}admin.php?page=zaplane-workflows&action=edit&id=${response.workflow_id}`
                );
            }
            setIsConvertOpen(false);
        } catch (err) {
            console.error('Recipe conversion failed:', err);
        }
        setLoading(false);
    };

    const handleMove = async () => {
        setLoading(true);
        try {
            await dispatch(
                updateRecipe({
                    id: recipe.id,
                    payload: {
                        folder_id: selectedFolderId,
                    },
                })
            ).unwrap();
            setIsMoveOpen(false);
        } catch (err) {
            console.error('Failed to move recipe:', err);
        }
        setLoading(false);
    };

    return (
        <>
            <Box
                border="1px solid"
                borderColor="gray.200"
                borderRadius="12px"
                p="16px"
                bg="white"
                _hover={{ boxShadow: 'md' }}
            >
                <Stack spacing="10px">
                    <Text className="zaplane-label" fontWeight="600">
                        {recipe?.title}
                    </Text>
                    <Text className="zaplane-label">
                        {recipe?.description || __('No description', 'zaplane')}
                    </Text>
                    <Flex gap="8px" pt="8px" wrap="wrap">
                        <Button
                            size="sm"
                            colorScheme="blue"
                            onClick={() => setIsConvertOpen(true)}
                        >
                            {__('Convert To Workflow', 'zaplane')}
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => setIsMoveOpen(true)}
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

            <WPModal
                title={__('Convert to Workflow', 'zaplane')}
                isOpen={isConvertOpen}
                onRequestClose={() => setIsConvertOpen(false)}
                size="small"
            >
                <VStack align="stretch" spacing={4}>
                    <Text>{__('Workflow title (optional)', 'zaplane')}</Text>
                    <Input
                        value={titleOverride}
                        onChange={(e) => setTitleOverride(e.target.value)}
                        placeholder={__('Enter workflow title or leave blank to use recipe title', 'zaplane')}
                    />
                    {warningMessage ? (
                        <Alert status="warning">
                            <AlertIcon />
                            {warningMessage}
                        </Alert>
                    ) : null}
                    <Flex justify="flex-end" gap={3}>
                        <Button variant="outline" onClick={() => setIsConvertOpen(false)}>
                            {__('Cancel', 'zaplane')}
                        </Button>
                        <Button
                            colorScheme="blue"
                            onClick={handleConvert}
                            isLoading={loading}
                        >
                            {__('Convert', 'zaplane')}
                        </Button>
                    </Flex>
                </VStack>
            </WPModal>

            <WPModal
                title={__('Move Recipe', 'zaplane')}
                isOpen={isMoveOpen}
                onRequestClose={() => setIsMoveOpen(false)}
                size="large"
            >
                <VStack align="stretch" spacing={4}>
                    <RecipeFolderTree
                        folders={recipe?.folders || []}
                        selectedFolderId={selectedFolderId}
                        onSelectFolder={setSelectedFolderId}
                        rootLabel={__('Unfiled', 'zaplane')}
                    />
                    <Flex justify="flex-end" gap={3}>
                        <Button variant="outline" onClick={() => setIsMoveOpen(false)}>
                            {__('Cancel', 'zaplane')}
                        </Button>
                        <Button
                            colorScheme="blue"
                            onClick={handleMove}
                            isLoading={loading}
                        >
                            {__('Move', 'zaplane')}
                        </Button>
                    </Flex>
                </VStack>
            </WPModal>
        </>
    );
};
export default RecipeCard;