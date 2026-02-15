import React, { useRef, useEffect } from 'react';
import { Flex, Text, Input, Textarea } from '@chakra-ui/react';
import { __ } from '@wordpress/i18n';

const ZAPInput = ({
  label,
  placeholder,
  value,
  onChange,
  type = 'text',
  containerStyle,
  inputStyle,
}) => {
  const textareaRef = useRef(null);
  useEffect(() => {
    if (type === 'textarea' && textareaRef.current) {
      const el = textareaRef.current;
      const minHeight = 35;
      el.style.height = minHeight + 'px';
      if (value && value.length > 0) {
        el.style.height = el.scrollHeight + 'px';
      }
    }
  }, [value, type]);





  const InputComponent = type === 'textarea' ? Textarea : Input;

  return (
    <Flex as="label" direction="column" gap={2} style={{ ...containerStyle }}>
      <Text className="zaplane-label" fontWeight="600" fontSize="0.875rem">
        {__(label, 'zaplane')}
      </Text>

      <InputComponent
        ref={type === 'textarea' ? textareaRef : null}
        className={`zaplane-${type === 'textarea' ? 'textarea' : 'input'}`}
        type={type !== 'textarea' ? type : undefined}
        placeholder={__(placeholder, 'zaplane')}
        value={value}
        onChange={onChange}
        style={{
          ...inputStyle,
          overflow: type === 'textarea' ? 'hidden' : undefined,
          resize: 'none',
        }}
      />
    </Flex>
  );
};

export default ZAPInput;
