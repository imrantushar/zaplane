

import { useEffect, useState } from 'react';
import {
  Box,
  Text,
  Flex,
  Button,
  Input,
  VStack,
  HStack,
  Image
} from '@chakra-ui/react';
import { __ } from '@wordpress/i18n';

import { useDispatch } from 'react-redux';
import { useNavigate } from 'react-router-dom';
import { route_path } from '@ZAPUtils/helper';
import WPModal from '@ZAPComponents/Modal/WPModal';

import {
  deleteRecipe,
  recipeToWorkflow
} from '@ZAPRedux/Slices/recipeSlice/recipeSlice';
import { DeleteIcon } from 'lucide-react';
import { primaryBtn } from '../../../../../assets/scss/chakra/recipe';
import ZAPDivider from '@ZAPComponents/ZAPDivider';
import { IoIosPlay } from 'react-icons/io';
import ZAPTooltip from '@ZAPComponents/ZAPTooltip';

const RecipeCard = ({ recipe }) => {
  const dispatch = useDispatch();
  const navigate = useNavigate();

  const [isConvertOpen, setIsConvertOpen] = useState(false);
  const [titleOverride, setTitleOverride] = useState(recipe?.title || '');
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    setTitleOverride(recipe?.title || '');
  }, [recipe]);

  const deletedRecipe = () => {
    dispatch(deleteRecipe(recipe.id));
  };

  const handleConvert = async () => {
    setLoading(true);
    try {
      const response = await dispatch(
        recipeToWorkflow({
          recipeId: recipe.id,
          payload: {
            title: titleOverride?.trim() || undefined,
          },
        })
      ).unwrap();

      if (response?.workflow_id) {
        navigate(
          `${route_path}admin.php?page=zaplane-workflows&action=edit&id=${response.workflow_id}`
        );
      }

      setIsConvertOpen(false);
    } catch (err) {
      console.error(err);
    }
    setLoading(false);
  };

  return (
    <>
      <Flex
        border="1px solid"
        borderColor="var(--zaplane-border-color)"
        borderRadius="8px"
        p="16px"
        bg="white"
        transition="0.2s"
        flexDirection='column'
        gap='8px'
        _hover={{
          boxShadow: "var(--zaplane-shadow)",
        }}

      >
        <Flex justify="space-between" align="center"  >

          <HStack spacing="8px">
            <Box
              p="6px"
              borderRadius="8px"
            >
              <Image src="/icons/google-form.png" boxSize="20px" />
            </Box>
            <Box
              p="6px"
              borderRadius="8px"
            >
              <Image src="/icons/ai.png" boxSize="20px" />
            </Box>
            <Box
              p="6px"
              borderRadius="8px"
            >
              <Image src="/icons/airtable.png" boxSize="20px" />
            </Box>
          </HStack>

          <HStack spacing="6px">
            <ZAPTooltip content='Use Recipe'>
              <Button
                size="sm"
                variant="outline"
                color='var(--zaplane-font-secondary-color)'
                fontWeight='500'
                onClick={() => setIsConvertOpen(true)}
              >
                <IoIosPlay />
                {__('Try now', 'zaplane')}
              </Button>
            </ZAPTooltip>
            <Button onClick={deletedRecipe} variant={'outline'} size="sm">
              <DeleteIcon />
            </Button>

          </HStack>
        </Flex>
        <ZAPDivider m='12px 0 8px -16px' w='109%' />
        <Text className='zaplane-label'>
          {recipe?.title}
        </Text>

        <Text className='zaplane-sub-title' color='#454F59'>
          {recipe?.description ||
            __('No description', 'zaplane')}
        </Text>
      </Flex>


      <WPModal
        title={__('Convert Workflow', 'zaplane')}
        isOpen={isConvertOpen}
        onRequestClose={() => setIsConvertOpen(false)}
        size="small"
      >
        <VStack align="stretch" spacing={4}>
          <Text className='zaplane-label'>
            {__('Workflow title (optional)', 'zaplane')}
          </Text>

          <Input
            className='zaplane-input'
            value={titleOverride}
            onChange={(e) =>
              setTitleOverride(e.target.value)
            }
          />

          <Flex justify="flex-end" gap={3}>
            <Button
              variant="outline"
              onClick={() =>
                setIsConvertOpen(false)
              }
            >
              {__('Cancel', 'zaplane')}
            </Button>

            <Button
              {...primaryBtn}
              onClick={handleConvert}
              isLoading={loading}
            >
              {__('Convert', 'zaplane')}
            </Button>
          </Flex>
        </VStack>
      </WPModal>
    </>
  );
};

export default RecipeCard;