import React from 'react';
import { Flex, Text, Input, Textarea } from '@chakra-ui/react';
import { __ } from '@wordpress/i18n';
import ZAPText from '@ZAPComponents/Text';

const ZAPInput = ({
  label,
  placeholder,
  value,
  onChange,
  type = 'text',
  style,
  inputStyle,
  textAreaSize,

}) => {

  const InputComponent = type === 'textarea' ? Textarea : Input;

  return (
    <Flex as="label" direction="column"  gap={2} style={{ ...style }}>
      <ZAPText fontWeight="600" fontSize="0.875rem" >
        {__(label, 'zaplane')}
      </ZAPText>

      <InputComponent
        className={type === 'textarea' ? 'zaplane-textarea' : 'zaplane-input'}
        type={type !== "textarea" ? type : undefined}
        placeholder={__(placeholder, 'zaplane')}
        value={value}
        onChange={onChange}
        style={{ ...inputStyle }}
        
      />
    </Flex>
  );
};

export default ZAPInput;
