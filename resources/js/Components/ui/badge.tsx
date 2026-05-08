import * as React from "react"

interface BadgeProps extends React.HTMLAttributes<HTMLDivElement> {
  variant?: "default"|"secondary"|"destructive"|"outline"
}

export const Badge = ({ className="", variant="default", ...props }: BadgeProps) => {
  const variants: Record<string,string> = {
    default: "bg-blue-600 text-white",
    secondary: "bg-gray-100 text-gray-800",
    destructive: "bg-red-600 text-white",
    outline: "border border-gray-300 text-gray-700",
  }
  return <div className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${variants[variant]} ${className}`} {...props} />
}
