import React from "react";

const SkeletonBox = ({ className = "", style = {} }) => (
  <div className={`bg-gray-200 rounded animate-pulse ${className}`} style={style} />
);

const FolderSkeleton = () => {
  return <div className="p-6">
    <div className="flex justify-between items-center mb-6">
      <SkeletonBox style={{height:'24px', width:'120px'}} />
      <SkeletonBox className="rounded-md" style={{height:'40px', width:'140px'}} />
    </div>

    <div className="zaplane-page-content">
      <div className="flex justify-between items-center mb-6">
        <SkeletonBox style={{height:'28px', width:'140px'}} />
        <SkeletonBox className="rounded-md" style={{height:'40px', width:'150px'}} />
      </div>
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {[...Array(6)].map((_, i) => (
          <div key={i} className="border border-gray-200 rounded-lg p-4">
            <div className="flex justify-between items-center mb-4">
              <div className="flex items-center gap-3">
                <SkeletonBox style={{width:'20px', height:'20px'}} />
                <SkeletonBox style={{height:'16px', width:'80px'}} />
              </div>
              <SkeletonBox className="rounded-md" style={{width:'24px', height:'24px'}} />
            </div>
            <SkeletonBox style={{height:'14px', width:'100px'}} />
            <div className="flex justify-end mt-4">
              <SkeletonBox style={{width:'18px', height:'18px'}} />
            </div>
          </div>
        ))}
      </div>
    </div>
  </div>;
};
export default FolderSkeleton;
