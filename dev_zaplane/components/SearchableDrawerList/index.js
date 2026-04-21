import React from "react";
import Search from "@ZAPComponents/Search";
import DrawerSearchList from "./DrawerSearchList/DrawerSearchList";
import DrawerItemList from "./DrawerItemList";


const SearchableDrawerList = ({
    search,
    setSearch,
    searchList,
    list,
    placeholder,
    onSelect,
    setMode,
    children,
}) => {
    return (
        <div className="flex flex-col gap-[16px]">
            <Search
                placeholder={placeholder || "Search apps..."}
                defaultValue={search}
                onSearchHandler={(value) => setSearch(value)}
            />
            {search ? (
                <DrawerSearchList
                    searchList={searchList}
                    setSelectedItem={onSelect}
                    setSearch={setSearch}
                    setMode={setMode}
                />
            ) : (
                children || <DrawerItemList list={list} setSelectedItem={onSelect} setMode={setMode} />
            )}
        </div>
    );
};

export default SearchableDrawerList;
