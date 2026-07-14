import { FiArrowRight } from 'react-icons/fi';
import { plugin_root_url } from '@ZAPUtils/helper';
import { isAbsoluteIcon, resolveIconFilename } from '@ZAPComponents/ZAPIconGroup/ZAPIconGroup';

const FALLBACK_ICON_SRC = `${plugin_root_url}assets/images/button.svg`;

// Fixed height, flexible width: app logos aren't all square (e.g. the WooCommerce
// mark is a wide wordmark), so a forced square/circle shrinks wide logos to an
// illegibly thin sliver. A pill that grows with the logo's own aspect ratio keeps
// every logo readable.
const ChipFrame = ({ children }) => (
  <div className="flex items-center justify-center h-9 min-w-[36px] px-2 rounded-full bg-white border border-[var(--zaplane-border-color)] shadow-sm shrink-0">
    {children}
  </div>
);

const IconChip = ({ icon }) => {
  const isAbsolute = isAbsoluteIcon(icon);
  const isImage = isAbsolute || icon?.endsWith('.svg');
  const src = isAbsolute ? icon : `${plugin_root_url}assets/images/icons/${icon}`;

  return (
    <ChipFrame>
      {isImage
        ? <img
            src={src}
            alt={icon}
            className="h-5 w-auto max-w-[26px] object-contain"
            onError={e => {
              e.currentTarget.onerror = null;
              e.currentTarget.src = FALLBACK_ICON_SRC;
            }}
          />
        : <span className={`zaplane-icon zaplane-icon--${icon} m-0`} />}
    </ChipFrame>
  );
};

const Connector = () => (
  <div className="relative w-5 border-t border-dashed border-[var(--zaplane-border-color)] shrink-0">
    <span className="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 flex items-center justify-center w-4 h-4 rounded-full bg-[var(--zaplane-primary)] text-white transition-transform duration-300 group-hover:translate-x-[2px]">
      <FiArrowRight size={9} />
    </span>
  </div>
);

// Recipes chain apps together (trigger -> action -> action), so the header renders
// that chain directly instead of a decorative icon badge.
const RecipeFlowIcons = ({ icons = [], maxVisible = 3 }) => {
  const safeIcons = (Array.isArray(icons) ? icons : []).filter(Boolean).map(resolveIconFilename);
  const visible = safeIcons.slice(0, maxVisible);
  const remaining = Math.max(0, safeIcons.length - visible.length);

  if (visible.length === 0) {
    return (
      <ChipFrame>
        <img src={FALLBACK_ICON_SRC} className="h-5 w-auto max-w-[26px] object-contain" alt="" />
      </ChipFrame>
    );
  }

  return (
    <div className="flex items-center">
      {visible.map((icon, index) => (
        <div key={index} className="flex items-center">
          {index > 0 && <Connector />}
          <IconChip icon={icon} />
        </div>
      ))}

      {remaining > 0 && (
        <div className="flex items-center">
          <Connector />
          <div className="flex items-center justify-center h-9 px-2 rounded-full bg-[var(--zaplane-secondary-color)] text-[11px] font-semibold text-[var(--zaplane-font-secondary-color)] shrink-0">
            +{remaining}
          </div>
        </div>
      )}
    </div>
  );
};

export default RecipeFlowIcons;
