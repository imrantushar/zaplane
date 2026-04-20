import { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { useDispatch } from 'react-redux';
import { useNavigate } from 'react-router-dom';
import { route_path } from '@ZAPUtils/helper';
import WPModal from '@ZAPComponents/Modal/WPModal';
import { deleteRecipe, recipeToWorkflow, updateRecipe } from '@ZAPRedux/Slices/recipeSlice/recipeSlice';
import { primaryBtn } from '../../../../../assets/scss/chakra/recipe';
import ZAPDivider from '@ZAPComponents/ZAPDivider';
import { IoIosPlay } from 'react-icons/io';
import ZAPTooltip from '@ZAPComponents/ZAPTooltip';
import ZAPIconGroup from '@ZAPComponents/ZAPIconGroup/ZAPIconGroup';
import ZAPMenu from '@ZAPComponents/ZapMenu';
const RecipeCard = ({
  recipe
}) => {
  const dispatch = useDispatch();
  const navigate = useNavigate();
  const [isConvertOpen, setIsConvertOpen] = useState(false);
  const [isRenameOpen, setIsRenameOpen] = useState(false);
  const [titleOverride, setTitleOverride] = useState(recipe?.title || '');
  const [title, setTitle] = useState(recipe?.title || '');
  const [loading, setLoading] = useState(false);
  const [isUpdating, setIsUpdating] = useState(false);
  const [showFullDesc, setShowFullDesc] = useState(false);
  useEffect(() => {
    setTitleOverride(recipe?.title || '');
    setTitle(recipe?.title || '');
  }, [recipe]);
  const handleDelete = () => {
    if (window.confirm("Are you sure you want to delete this recipe?")) {
      dispatch(deleteRecipe(recipe.id));
    }
  };
  const handleConvert = async () => {
    setLoading(true);
    try {
      const response = await dispatch(recipeToWorkflow({
        recipeId: recipe.id,
        payload: {
          title: titleOverride?.trim() || undefined
        }
      })).unwrap();
      if (response?.workflow_id) {
        navigate(`${route_path}admin.php?page=zaplane-workflows&action=edit&id=${response.workflow_id}`);
      }
      setIsConvertOpen(false);
    } catch (err) {
      console.error(err);
    }
    setLoading(false);
  };
  const handleRename = async () => {
    if (!title.trim()) return;
    setIsUpdating(true);
    try {
      await dispatch(updateRecipe({
        id: recipe.id,
        payload: {
          title: title.trim()
        }
      })).unwrap();
      setIsRenameOpen(false);
    } catch (err) {
      console.error(err);
    }
    setIsUpdating(false);
  };
  return <>
      <div boxShadow={'var(--zaplane-shadow-2)'} transition="0.2s" flexDirection='column' gap='8px' _hover={{
      boxShadow: "var(--zaplane-shadow)"
    }} className="flex rounded-[8px] p-[16px] bg-white min-h-[190px]">
        <div justify="space-between" align="center" className="flex">
          <ZAPIconGroup icons={recipe?.integration_icons} />

          <div className="flex flex-row items-center gap-[6px]">
            <ZAPTooltip content='Use Recipe'>
              <button size="sm" variant="outline" onClick={() => setIsConvertOpen(true)} className="text-var(--zaplane-font-secondary-color) font-[500]">
                <IoIosPlay />
                {__('Try now', 'zaplane')}
              </button>
            </ZAPTooltip>

            <ZAPMenu isIcon items={[{
            label: __("Rename", "zaplane"),
            onClick: () => setIsRenameOpen(true)
          }, {
            label: __("Delete", "zaplane"),
            onClick: handleDelete
          }]} />
          </div>
        </div>

        <ZAPDivider m='12px 0 8px -16px' w='109%' />

        <span style={{
        whiteSpace: 'nowrap',
        overflow: 'hidden',
        textOverflow: 'ellipsis'
      }} title={recipe?.title} className="zaplane-label w-[80%]">
          {recipe?.title}
        </span>

        <>
          <span style={!showFullDesc ? {
          display: '-webkit-box',
          WebkitLineClamp: 2,
          WebkitBoxOrient: 'vertical',
          overflow: 'hidden',
          textOverflow: 'ellipsis'
        } : {}} title={recipe?.description} className="zaplane-sub-title text-#454F59">
            {recipe?.description || __('No description', 'zaplane')}
          </span>

          {recipe?.description && recipe.description.length > 100 && <span as="span" onClick={() => setShowFullDesc(prev => !prev)} _hover={{
          textDecoration: 'underline'
        }} className="text-[12px] text-var(--zaplane-primary-color) cursor-pointer font-[500] text-right">
              {showFullDesc ? __('See less', 'zaplane') : __('See more', 'zaplane')}
            </span>}
        </>
      </div>

      {/* Convert Modal */}
      <WPModal title={__('Convert Workflow', 'zaplane')} isOpen={isConvertOpen} onRequestClose={() => setIsConvertOpen(false)} size="small">
        <div align="stretch" className="flex flex-col gap-4">
          <span>
            {__('Workflow title (optional)', 'zaplane')}
          </span>

          <input value={titleOverride} onChange={e => setTitleOverride(e.target.value)} />

          <div justify="flex-end" gap={3} className="flex">
            <button variant="outline" onClick={() => setIsConvertOpen(false)}>
              {__('Cancel', 'zaplane')}
            </button>

            <button style={primaryBtn} onClick={handleConvert}>
              {__('Convert', 'zaplane')}
            </button>
          </div>
        </div>
      </WPModal>

      {/* Rename Modal  */}
      <WPModal title={__("Rename Recipe", "zaplane")} isOpen={isRenameOpen} onRequestClose={() => setIsRenameOpen(false)} size="small">
        <input value={title} onChange={e => setTitle(e.target.value)} placeholder={__("Enter recipe name", "zaplane")} className="zaplane-input mb-4" />

        <div justify="flex-end" gap={3} className="flex">
          <button variant="outline" onClick={() => setIsRenameOpen(false)}>
            {__("Cancel", "zaplane")}
          </button>

          <button style={primaryBtn} onClick={handleRename} disabled={!title.trim()}>
            {__("Update", "zaplane")}
          </button>
        </div>
      </WPModal>
    </>;
};
export default RecipeCard;