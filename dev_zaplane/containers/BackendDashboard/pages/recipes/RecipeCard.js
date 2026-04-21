import { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { useDispatch } from 'react-redux';
import { useNavigate } from 'react-router-dom';
import { route_path } from '@ZAPUtils/helper';
import WPModal from '@ZAPComponents/Modal/WPModal';
import { deleteRecipe, recipeToWorkflow, updateRecipe } from '@ZAPRedux/Slices/recipeSlice/recipeSlice';
import { outlineBtn, primaryBtn } from '../../../../../assets/scss/chakra/recipe';
import ZAPDivider from '@ZAPComponents/ZAPDivider';
import { IoIosPlay } from 'react-icons/io';
import ZAPTooltip from '@ZAPComponents/ZAPTooltip';
import ZAPIconGroup from '@ZAPComponents/ZAPIconGroup/ZAPIconGroup';
import ZAPMenu from '@ZAPComponents/ZapMenu';
import ZAPInput from '@ZAPComponents/ZAPInput';
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
    <div className="flex flex-col rounded-[12px] p-5 bg-white border border-[#E5E7EB] shadow-sm hover:shadow-md transition-all duration-200 group h-full">
      <div className="flex justify-between items-center mb-4">
        <ZAPIconGroup icons={recipe?.integration_icons} maxVisible={3} />

        <div className="flex flex-row items-center gap-2">
          <ZAPTooltip content='Use Recipe'>
            <button
              onClick={() => setIsConvertOpen(true)}
              className="flex items-center gap-2 px-4 py-1.5 rounded-full border border-[#E5E7EB] text-[13px] font-semibold text-[#374151] hover:bg-[#F9FAFB] hover:border-[#D1D5DB] transition-all"
            >
              <IoIosPlay className="text-[#6366F1]" size={14} />
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

      <div className="border-t border-[#F3F4F6] -mx-5 mb-4"></div>

      <div className="flex flex-col gap-2 flex-grow">
        <h3 className='zaplane-label' title={recipe?.title}>
          {recipe?.title}
        </h3>

        <div className="flex flex-col gap-[16px]">
          <p className={`text-[13px] text-[#6B7280] leading-relaxed ${!showFullDesc ? 'line-clamp-2' : ''}`} title={recipe?.description}>
            {recipe?.description || __('No description', 'zaplane')}
          </p>

          {recipe?.description && recipe.description.length > 100 && (
            <button
              onClick={() => setShowFullDesc(prev => !prev)}
              className="text-[12px] text-[#6366F1] hover:underline self-end font-semibold mt-1"
            >
              {showFullDesc ? __('See less', 'zaplane') : __('See more', 'zaplane')}
            </button>
          )}
        </div>
      </div>
    </div>

    {/* Convert Modal */}
    <WPModal title={__('Convert Workflow', 'zaplane')} isOpen={isConvertOpen} onRequestClose={() => setIsConvertOpen(false)} size="small">
      <div align="stretch" className="flex flex-col gap-4">
        <ZAPInput
          label={__('Workflow title (optional)', 'zaplane')}
          value={titleOverride} onChange={e => setTitleOverride(e.target.value)} />


        <div className="flex gap-3">
          <button style={outlineBtn} onClick={() => setIsConvertOpen(false)}>
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
      <div className='flex flex-col gap-6'>
        <ZAPInput
          placeholder={__("Enter recipe name", "zaplane")}
          label={__('Enter recipe name', 'zaplane')}
          value={title}
          onChange={e => setTitle(e.target.value)} />
        <div className="flex gap-3">
          <button style={outlineBtn} onClick={() => setIsRenameOpen(false)}>
            {__("Cancel", "zaplane")}
          </button>

          <button style={primaryBtn} onClick={handleRename} disabled={!title.trim()}>
            {__("Update", "zaplane")}
          </button>
        </div>
      </div>
    </WPModal>
  </>;
};
export default RecipeCard;