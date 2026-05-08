import * as React from "react"
export const TooltipProvider = ({ children }: { children: React.ReactNode }) => <>{children}</>
const Tooltip = ({ children }: { children: React.ReactNode }) => <>{children}</>
export const TooltipTrigger = ({ children, asChild }: { children: React.ReactNode, asChild?: boolean }) => <>{children}</>
export const TooltipContent = ({ children, className="" }: { children: React.ReactNode, className?: string }) => (
  <div className={`z-50 rounded bg-gray-900 px-2 py-1 text-xs text-white ${className}`}>{children}</div>
)
export { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger }
