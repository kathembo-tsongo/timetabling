import * as React from "react"

interface AlertProps extends React.HTMLAttributes<HTMLDivElement> {
  variant?: "default"|"destructive"
}

export const Alert = React.forwardRef<HTMLDivElement, AlertProps>(
  ({ className="", variant="default", ...props }, ref) => (
    <div ref={ref} className={`relative w-full rounded-lg border p-4 ${variant === "destructive" ? "border-red-200 bg-red-50 text-red-800" : "border-gray-200 bg-white"} ${className}`} {...props} />
  )
)
Alert.displayName = "Alert"

export const AlertDescription = React.forwardRef<HTMLParagraphElement, React.HTMLAttributes<HTMLParagraphElement>>(
  ({ className="", ...props }, ref) => (
    <div ref={ref} className={`text-sm ${className}`} {...props} />
  )
)
AlertDescription.displayName = "AlertDescription"

export const AlertTitle = React.forwardRef<HTMLParagraphElement, React.HTMLAttributes<HTMLHeadingElement>>(
  ({ className="", ...props }, ref) => (
    <h5 ref={ref} className={`mb-1 font-medium ${className}`} {...props} />
  )
)
AlertTitle.displayName = "AlertTitle"
