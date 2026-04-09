import { useEffect, useMemo, useState } from "react";
import {
  Box,
  Button,
  Flex,
  HStack,
  Icon,
  Image,
  Input,
  Menu,
  Portal,
  Text,
  VStack,
} from "@chakra-ui/react";
import { __ } from "@wordpress/i18n";
import { useDispatch, useSelector } from "react-redux";
import TopBar from "@ZAPComponents/TopBar";
import WPModal from "@ZAPComponents/Modal/WPModal";
import ListTable from "@ZAPComponents/ListTable";
import ZAPTooltip from "@ZAPComponents/ZAPTooltip";
import {
  createRecipeFolder,
  deleteRecipe,
  getRecipeFolders,
  getRecipes,
  recipeToWorkflow,
  updateRecipe,
} from "@ZAPRedux/Slices/recipeSlice/actions/recipe";
import { primaryBtn } from "../../../../../assets/scss/chakra/recipe";
import { plugin_root_url, route_path } from "@ZAPUtils/helper";
import { FiCheck, FiFolder, FiFolderPlus } from "react-icons/fi";
import { RiDeleteBin6Line } from "react-icons/ri";
import { TbTemplate } from "react-icons/tb";
import { IoIosArrowForward } from "react-icons/io";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";

const flattenFolders = (nodes = [], result = []) => {
  nodes.forEach((node) => {
    result.push({ id: node.id, title: node.title });
    flattenFolders(node.children || [], result);
  });
  return result;
};

const RecipesPage = () => {
  const dispatch = useDispatch();
  const { folders, recipes, loadingRecipes } = useSelector((state) => state.recipes);
  const [activeFolderFilter, setActiveFolderFilter] = useState(null);
 

  const folderOptions = useMemo(() => flattenFolders(folders), [folders]);

  useEffect(() => {
    dispatch(getRecipeFolders());
    dispatch(getRecipes());
  }, [dispatch]);
console.log(folders,'folders');



  return (
    <>
      <TopBar
        leftContent={() => (
          <>
            <Flex height='40px' width='40px' borderRadius='20px' gap='10px' background='var(--zaplane-second-primary)' alignItems='center' justifyContent='center'>
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
              label={__('Recipe Library', 'zaplane')}
            />
          </>
        )}
      />

      <div className="zaplane-page-content">

      </div>

    
    </>
  );
};

export default RecipesPage;
