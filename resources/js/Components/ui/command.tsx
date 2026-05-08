import * as React from "react"
const Command = ({ children, className="" }: { children: React.ReactNode, className?: string }) => (
  <div className={`flex flex-col overflow-hidden rounded-md bg-white ${className}`}>{children}</div>
)
export const CommandInput = ({ placeholder="", ...props }: React.InputHTMLAttributes<HTMLInputElement>) => (
  <input placeholder={placeholder} className="w-full border-0 px-3 py-2 text-sm outline-none" {...props} />
)
export const CommandList = ({ children }: { children: React.ReactNode }) => <div className="overflow-y-auto">{children}</div>
export const CommandEmpty = ({ children }: { children: React.ReactNode }) => <div className="py-6 text-center text-sm text-gray-500">{children}</div>
export const CommandGroup = ({ children, heading }: { children: React.ReactNode, heading?: string }) => <div>{heading && <div className="px-2 py-1 text-xs font-medium text-gray-500">{heading}</div>}{children}</div>
export const CommandItem = ({ children, onSelect, className="" }: { children: React.ReactNode, onSelect?: () => void, className?: string }) => (
  <div onClick={onSelect} className={`relative flex cursor-pointer select-none items-center rounded px-2 py-1.5 text-sm hover:bg-gray-100 ${className}`}>{children}</div>
)
export { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList }
