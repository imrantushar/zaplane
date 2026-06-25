import React from "react";

const SkeletonBox = ({ className = "", style = {} }) => (
  <div className={`bg-gray-200 rounded animate-pulse ${className}`} style={style} />
);

const RecipesSkeleton = () => {
  return <div className="min-h-[100vh] p-6" style={{background:'var(--zaplane-body-background)'}}>
    <div className="flex justify-between items-center mb-8">
      <SkeletonBox style={{height:'20px', width:'100px'}} />
      <div className="flex gap-3">
        <SkeletonBox className="rounded-md" style={{height:'36px', width:'110px'}} />
        <SkeletonBox className="rounded-md" style={{height:'36px', width:'80px'}} />
      </div>
    </div>

    <div className="zaplane-page-content">
      <div className="flex justify-between items-center mb-6">
        <SkeletonBox style={{height:'28px', width:'140px'}} />
        <SkeletonBox className="rounded-md" style={{height:'40px', width:'150px'}} />
      </div>
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        {[...Array(6)].map((_, i) => (
          <div key={i} className="bg-[var(--zaplane-background)] border border-[var(--zaplane-border-color)] rounded-xl p-5 shadow-sm">
            <div className="flex justify-between items-center mb-4">
              <SkeletonBox className="rounded-md" style={{width:'90px', height:'36px'}} />
              <SkeletonBox className="rounded-md" style={{width:'26px', height:'26px'}} />
            </div>
            <div className="border-t border-[var(--zaplane-border-color)] -mx-5 mb-4 opacity-60" />
            <SkeletonBox style={{height:'16px', width:'140px'}} />
            <div className="flex flex-col gap-2 mt-3">
              <SkeletonBox style={{height:'12px', width:'100%'}} />
              <SkeletonBox style={{height:'12px', width:'80%'}} />
            </div>
            <SkeletonBox className="rounded-md mt-5" style={{height:'38px', width:'100%'}} />
          </div>
        ))}
      </div>
    </div>
  </div>;
};
export default RecipesSkeleton;
