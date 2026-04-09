import { Flex, Image } from '@chakra-ui/react';
import { __ } from '@wordpress/i18n';
import ZAPLabel from '@ZAPComponents/Labels/ZAPLabel';
import TopBar from '@ZAPComponents/TopBar';
import { plugin_root_url } from '@ZAPUtils/helper';
import React, { useEffect } from 'react';
import { IoIosArrowForward } from 'react-icons/io';
import WorkflowTable from '../../workflows/WorkflowTable';
import { useDispatch, useSelector } from 'react-redux';
import { getRecipes } from '@ZAPRedux/Slices/recipeSlice/actions/recipe';

const ShowRecipes = ({ id }) => {
    const dispatch=useDispatch()
    const {recipes}=useSelector((state)=>state.recipes)
    useEffect(async()=>{
   await getRecipes()
    },[dispatch])
    console.log(recipes,'recipe');
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
                

            </div>

        </div>
    );
};

export default ShowRecipes;