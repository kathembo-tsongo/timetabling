import * as React from "react"
const Popover = ({ children }: { children: React.ReactNode }) => <>{children}</>
export const PopoverTrigger = ({ children, asChild }: { children: React.ReactNode, asChild?: boolean }) => <>{children}</>
export const PopoverContent = ({ children, className="" }: { children: React.ReactNode, className?: string }) => (
  <div className={`z-50 rounded-md border border-gray-200 bg-white p-4 shadow-md ${className}`}>{children}</div>
)
export { Popover, PopoverContent, PopoverTrigger }
