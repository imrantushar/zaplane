import React from "react";

const SkeletonBox = ({ className = "", style = {} }) => (
  <div className={`bg-gray-200 rounded animate-pulse ${className}`} style={style} />
);

const RecipesSkeleton = () => {
  return <div className="min-h-[100vh] p-6" style={{background:'#F9FAFB'}}>
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
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {[...Array(6)].map((_, i) => (
          <div key={i} className="bg-white border border-gray-200 rounded-lg p-5 shadow-sm">
            <div className="flex justify-between items-center mb-4">
              <div className="flex items-center gap-3">
                <SkeletonBox style={{width:'22px', height:'22px'}} />
                <SkeletonBox style={{height:'16px', width:'90px'}} />
              </div>
              <SkeletonBox className="rounded-md" style={{width:'26px', height:'26px'}} />
            </div>
            <SkeletonBox style={{height:'14px', width:'120px'}} />
            <div className="flex justify-end mt-5">
              <SkeletonBox style={{width:'18px', height:'18px'}} />
            </div>
          </div>
        ))}
      </div>
    </div>
  </div>;
};
export default RecipesSkeleton;
