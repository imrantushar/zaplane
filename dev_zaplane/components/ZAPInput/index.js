import React, { useRef, useEffect } from "react";
import { Flex, Text, Input, Textarea } from "@chakra-ui/react";
import { __ } from "@wordpress/i18n";

const ZAPInput = ({
  label,
  placeholder,
  value,
  onChange,
  type = "text",
  containerStyle,
  onKeyDown,
  inputStyle,
  inputRef,
}) => {
  // const textareaRef = useRef(null);
  // useEffect(() => {
  //   if (type === "textarea" && textareaRef.current) {
  //     const el = inputRef?.current || textareaRef.current;
  //     const minHeight = 35;
  //     el.style.height = "0px";
  //     const newHeight = Math.max(el.scrollHeight, minHeight);
  //     el.style.height = newHeight + "px";
  //   }
  // }, [value, type]);

  const isTextarea = type === "textarea";
  console.log(isTextarea,'is');

  return (
    <Flex as="label" direction="column" gap={2} style={{ ...containerStyle }}>
      <Text className="zaplane-label">
        {__(label, "zaplane")}
      </Text>
      {isTextarea ? (
        <Textarea
          className="zaplane-textarea"
          placeholder={__(placeholder, "zaplane")}
          value={value}
          onChange={onChange}
          autoresize 
          onKeyDown={onKeyDown}
          {...inputStyle}
        />
      ) : (
        <Input
          ref={inputRef}
          className="zaplane-input"
          type={type}
          placeholder={__(placeholder, "zaplane")}
          value={value}
          onKeyDown={onKeyDown}
          onChange={onChange}
          {...(type === "number" && {
            inputMode: "numeric",
            pattern: "[0-9]*",
          })}
          style={{ ...inputStyle }}
        />
      )}
    </Flex>
  );
};

export default ZAPInput;
