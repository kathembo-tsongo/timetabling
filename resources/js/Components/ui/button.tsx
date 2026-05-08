import * as React from "react"

interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: "default"|"destructive"|"outline"|"ghost"|"link"
  size?: "default"|"sm"|"lg"
}

export const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
  ({ className="", variant="default", size="default", ...props }, ref) => {
    const base = "inline-flex items-center justify-center rounded-md text-sm font-medium transition-colors focus:outline-none disabled:opacity-50"
    const variants: Record<string,string> = {
      default: "bg-blue-600 text-white hover:bg-blue-700",
      destructive: "bg-red-600 text-white hover:bg-red-700",
      outline: "border border-gray-300 bg-white hover:bg-gray-50 text-gray-700",
      ghost: "hover:bg-gray-100 text-gray-700",
      link: "text-blue-600 underline-offset-4 hover:underline",
    }
    const sizes: Record<string,string> = {
      default: "h-10 px-4 py-2",
      sm: "h-8 px-3 text-xs",
      lg: "h-11 px-8",
    }
    return <button ref={ref} className={`${base} ${variants[variant]} ${sizes[size]} ${className}`} {...props} />
  }
)
Button.displayName = "Button"
