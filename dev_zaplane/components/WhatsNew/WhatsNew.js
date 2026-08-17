import React, { useState } from 'react';
import ZAPDrawer from '@ZAPComponents/Drawer';
import { __ } from '@wordpress/i18n';
import { outlineBtn } from '../../../assets/scss/chakra/recipe';
const whatsNewContent = [{
  version: 'v2.1.0',
  age: '1 week ago',
  title: "Fresh Out of zaplane",
  sections: [{
    label: 'New Triggers',
    tag: 'trigger',
    items: [{ bold: 'FluentBoards:', text: '3 new triggers — Task Updated, Assignees Updated, and Stage Updated.', note: '(fires when task title, members, or stages change)' },
            { bold: 'WordPress:', text: 'Post Updated — fires only when an existing post is updated, not on creation.' }]
  }, {
    label: 'New Actions',
    tag: 'action',
    items: [{ bold: 'The Events Calendar:', text: 'Create Event — programmatically create events via automations.' }]
  }, {
    label: 'Integration Updates',
    tag: 'integration',
    items: [{ bold: 'Notion — Find Database Item:', text: 'Multiple filter support. Results are loop-compatible and fully iterable.' }]
  }, {
    label: 'Improvements',
    tag: 'improvement',
    items: [{ bold: '', text: 'Added RTL text direction support across the zaplane plugin UI.' }]
  }],
  readMoreUrl: 'https://zaplane.com'
}];
const TAG_STYLES = {
  trigger: { background: 'rgba(59,196,143,0.12)', color: '#0b8f5e', label: 'Trigger' },
  action: { background: 'rgba(59,130,246,0.12)', color: '#1a61c4', label: 'Action' },
  integration: { background: 'rgba(249,168,37,0.12)', color: '#a06000', label: 'Integration' },
  improvement: { background: 'rgba(148,163,184,0.12)', color: '#526070', label: 'UI' }
};
const SectionTag = ({ type }) => {
  const s = TAG_STYLES[type] || TAG_STYLES.improvement;
  return <span style={{ background: s.background, color: s.color, textTransform:'uppercase', letterSpacing:'0.04em' }}
               className="inline-block text-[10px] font-[600] px-[6px] py-[1px] rounded-[4px] mr-[5px]">
    {s.label}
  </span>;
};
const WhatsNew = () => {
  const [open, setOpen] = useState(false);
  return <ZAPDrawer open={open} onClose={() => setOpen(false)} closeOnOverlayClick trigger={
    <button className='flex items-center gap-2' style={outlineBtn}  onClick={() => setOpen(true)}>
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--zaplane-font-color)" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
        <g transform="scale(0.9) translate(1.5,1.5)">
          <path d="M11 6a13 13 0 0 0 8.4-2.8A1 1 0 0 1 21 4v12a1 1 0 0 1-1.6.8A13 13 0 0 0 11 14H5a2 2 0 0 1-2-2V8a2 2 0 0 1 5-2z" />
          <path d="M6 14a12 12 0 0 0 2.4 7.2 2 2 0 0 0 3.2-2.4A8 8 0 0 1 10 14" />
          <path d="M8 6v8" />
        </g>
      </svg>
      {__("What's New")}
    </button>
  } title={__("What's New", "zaplane")} size="sm">
    <div>
      {whatsNewContent.map((entry, i) => (
        <div key={i} className="border-b border-[var(--zaplane-border-color)] last:border-b-0 pb-4 mb-4">
          <div className="flex items-center gap-[8px] mb-[10px]">
            <span style={{textTransform:'uppercase', letterSpacing:'0.06em'}} className="text-[11px] m-0 font-[500] text-[var(--zaplane-text-muted)]">{entry.age}</span>
            <div className="w-[4px] h-[4px] bg-gray-300 rounded-full" />
            <span style={{color:'var(--zaplane-primary)'}} className="text-[11px] font-[600] px-[8px] py-[2px] rounded-full">{entry.version}</span>
          </div>

          <span style={{lineHeight:'1.25'}} className="text-[22px] font-[400] mb-[18px] block">{entry.title}</span>

          {entry.sections.map((section, si) => (
            <div key={si}>
              <div style={{textTransform:'uppercase', letterSpacing:'0.08em'}} className="flex items-center mt-[16px] mb-[8px] text-[11px] font-[600] text-[var(--zaplane-text-muted)] gap-[6px]">
                {section.label}
                <span className="flex-1 h-[1px] bg-[var(--zaplane-secondary-color)] block" />
              </div>
              <ul style={{listStyleType:'none'}} className="p-0 m-0 flex flex-col gap-[7px]">
                {section.items.map((item, ii) => (
                  <li key={ii} style={{lineHeight:'1.55', paddingLeft:'14px', position:'relative'}} className="text-[13.5px] text-[var(--zaplane-font-secondary-color)] before:content-[''] before:absolute before:left-0 before:top-[8px] before:w-[5px] before:h-[5px] before:rounded-full before:bg-purple-300">
                    <SectionTag type={section.tag} />
                    {item.bold && <strong className="text-[var(--zaplane-font-color)]">{item.bold} </strong>}
                    {item.text}
                    {item.note && <em style={{fontStyle:'normal'}} className="text-[12.5px] text-[var(--zaplane-text-muted)]"> {item.note}</em>}
                  </li>
                ))}
              </ul>
            </div>
          ))}

          {entry.readMoreUrl && <div className="mt-[14px] text-[12.5px]">
            Read more at{' '}
            <a href={entry.readMoreUrl} target="_blank" rel="noreferrer" style={{color:'var(--zaplane-primary)'}} className="font-[500] hover:underline">
              {__('zaplane', 'zaplane')}
            </a>
          </div>}
        </div>
      ))}
    </div>
  </ZAPDrawer>;
};
export default WhatsNew;
