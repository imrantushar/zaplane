import { useEffect, useState } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { useDispatch } from 'react-redux';
import { useNavigate } from 'react-router-dom';
import { route_path } from '@ZAPUtils/helper';
import WPModal from '@ZAPComponents/Modal/WPModal';
import { deleteRecipe, recipeToWorkflow, updateRecipe } from '@ZAPRedux/Slices/recipeSlice/recipeSlice';
import { primaryBtn } from '../../../../../assets/scss/chakra/recipe';
import { IoIosPlay } from 'react-icons/io';
import { FiLayers } from 'react-icons/fi';
import ZAPTooltip from '@ZAPComponents/ZAPTooltip';
import ZAPMenu from '@ZAPComponents/ZapMenu';
import RecipeFlowIcons from './RecipeFlowIcons';
import ZAPInput from '@ZAPComponents/ZAPInput';
import RecipeGroupWizard from '@ZAPComponents/RecipeGroupWizard';
const RecipeCard = ({
  recipe
}) => {
  const dispatch = useDispatch();
  const navigate = useNavigate();
  const [isConvertOpen, setIsConvertOpen] = useState(false);
  const [isSetupOpen, setIsSetupOpen] = useState(false);
  const [isRenameOpen, setIsRenameOpen] = useState(false);
  const [titleOverride, setTitleOverride] = useState(recipe?.title || '');
  const [title, setTitle] = useState(recipe?.title || '');
  const [loading, setLoading] = useState(false);
  const [isUpdating, setIsUpdating] = useState(false);
  const [showFullDesc, setShowFullDesc] = useState(false);
  const isGroup = recipe?.type === 'group';
  const workflowCount = recipe?.workflows?.length || 0;
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
    <div className="flex flex-col rounded-[4px] p-5 bg-[var(--zaplane-background)] border border-[var(--zaplane-border-color)] shadow-sm hover:shadow-md hover:border-[var(--zaplane-primary)]/30 transition-all duration-200 group h-full">
      <div className="flex justify-between items-center mb-5">
        <RecipeFlowIcons icons={recipe?.integration_icons} maxVisible={3} />

        <ZAPMenu isIcon items={[{
          label: __("Rename", "zaplane"),
          onClick: () => setIsRenameOpen(true)
        }, {
          label: __("Delete", "zaplane"),
          onClick: handleDelete
        }]} />
      </div>

      <div className="flex flex-col gap-2 flex-grow">
        <h3 className='zaplane-label truncate' title={recipe?.title}>
          {recipe?.title}
        </h3>

        {isGroup && (
          <span
            className="flex w-fit items-center gap-1 rounded-full px-2 py-[2px] text-[11px] font-semibold"
            style={{ backgroundColor: 'var(--zaplane-secondary-color)', color: 'var(--zaplane-font-secondary-color)' }}
            title={(recipe?.workflows || []).map(workflow => workflow.title).join('\n')}
          >
            <FiLayers size={11} />
            {sprintf(_n('Group recipe · %d workflow', 'Group recipe · %d workflows', workflowCount, 'zaplane'), workflowCount)}
          </span>
        )}

        <div className="flex flex-col gap-1">
          <p className="text-[13px] text-[var(--zaplane-font-secondary-color)] leading-relaxed min-h-[38px]" title={recipe?.description}>
            {recipe?.description
              ? (showFullDesc || recipe.description.length <= 100
                  ? recipe.description
                  : `${recipe.description.slice(0, 100).trimEnd()}… `)
              : __('No description', 'zaplane')}
            {recipe?.description && recipe.description.length > 100 && (
              <button
                onClick={() => setShowFullDesc(prev => !prev)}
                className="ml-1 inline text-[12px] text-[var(--zaplane-font-color)] hover:underline font-semibold whitespace-nowrap"
              >
                {showFullDesc ? __('See less', 'zaplane') : __('See more', 'zaplane')}
              </button>
            )}
          </p>
        </div>
      </div>

      <div className="mt-5">
        <ZAPTooltip content={isGroup ? __('Choose which workflows to set up', 'zaplane') : __('Use Recipe', 'zaplane')}>
          <button
            onClick={() => (isGroup ? setIsSetupOpen(true) : setIsConvertOpen(true))}
            className="flex w-full items-center justify-center gap-2 px-4 py-2.5 rounded bg-[var(--zaplane-primary)] text-white text-[13px] font-semibold hover:opacity-90 active:opacity-80 transition-all"
          >
            <IoIosPlay size={14} />
            {isGroup ? __('Run Group Recipe', 'zaplane') : __('Use Recipe', 'zaplane')}
          </button>
        </ZAPTooltip>
      </div>
    </div>

    {isGroup && <RecipeGroupWizard recipe={recipe} isOpen={isSetupOpen} onClose={() => setIsSetupOpen(false)} />}

    {/* Convert Modal */}
    <WPModal title={__('Use Recipe', 'zaplane')} isOpen={isConvertOpen} onRequestClose={() => setIsConvertOpen(false)} size="small">
      <div align="stretch" className="flex flex-col gap-4">
        <ZAPInput
          label={__('Workflow title (optional)', 'zaplane')}
          value={titleOverride} onChange={e => setTitleOverride(e.target.value)} />


        <div className="flex gap-3">
          <button style={primaryBtn} onClick={handleConvert} disabled={loading}>
            {__('Create Workflow', 'zaplane')}
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
          <button style={primaryBtn} onClick={handleRename} disabled={!title.trim()}>
            {__("Update", "zaplane")}
          </button>
        </div>
      </div>
    </WPModal>
  </>;
};
export default RecipeCard;
