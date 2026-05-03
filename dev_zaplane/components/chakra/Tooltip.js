import * as React from "react"

const Tooltip = React.forwardRef((props, ref) => {
  const {
    showArrow,
    children,
    disabled,
    portalled,
    content,
    contentProps,
    portalRef,
    className,
    ...rest
  } = props

  if (disabled) return children

  return (
    <div ref={ref} className={`inline-block ${className || ""}`} title={content} {...rest}>
      {children}
    </div>
  )
})

export default Tooltip