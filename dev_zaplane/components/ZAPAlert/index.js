
const alertStyles = {
  info: "bg-blue-50 border-blue-300 text-blue-800",
  success: "bg-green-50 border-green-300 text-green-800",
  warning: "bg-yellow-50 border-yellow-400 text-yellow-800",
  error: "bg-red-50 border-red-300 text-red-800",
};

const alertIcons = {
  info: "ℹ️",
  success: "✅",
  warning: "⚠️",
  error: "❌",
};

const ZAPAlert = ({
  status = "info",
  title,
  description,
  children,
  ...rest
}) => {
  const colorClasses = alertStyles[status] || alertStyles.info;
  const icon = alertIcons[status] || alertIcons.info;

  return (
    <div
      role="alert"
      className={`flex gap-3 items-start rounded-md border px-4 py-3 text-sm ${colorClasses}`}
      {...rest}
    >
      <span className="flex-shrink-0">{icon}</span>
      <div className="flex flex-col gap-1">
        {title && (
          <p className="font-semibold text-sm">{title}</p>
        )}
        {description && (
          <p className="text-sm">{description}</p>
        )}
        {children}
      </div>
    </div>
  );
};

export default ZAPAlert;
