import * as React from "react"

interface SelectProps { value?: string; onValueChange?: (v: string) => void; children?: React.ReactNode; disabled?: boolean }
interface SelectItemProps { value: string; children: React.ReactNode; className?: string }

export const Select = ({ value, onValueChange, children, disabled }: SelectProps) => {
  return <SelectContext.Provider value={{ value, onValueChange, disabled }}>{children}</SelectContext.Provider>
}

const SelectContext = React.createContext<any>({})

export const SelectTrigger = ({ children, className="" }: { children?: React.ReactNode, className?: string }) => {
  const { value, disabled } = React.useContext(SelectContext)
  return <div className={`flex h-10 w-full items-center justify-between rounded-md border border-gray-300 bg-white px-3 py-2 text-sm ${disabled ? "opacity-50" : ""} ${className}`}>{children}</div>
}

export const SelectValue = ({ placeholder="" }: { placeholder?: string }) => {
  const { value } = React.useContext(SelectContext)
  return <span>{value || placeholder}</span>
}

export const SelectContent = ({ children, className="" }: { children?: React.ReactNode, className?: string }) => (
  <div className={`absolute z-50 min-w-[8rem] rounded-md border border-gray-200 bg-white shadow-md ${className}`}>{children}</div>
)

export const SelectItem = ({ value, children, className="" }: SelectItemProps) => {
  const { onValueChange } = React.useContext(SelectContext)
  return (
    <div onClick={() => onValueChange?.(value)}
      className={`relative flex cursor-pointer select-none items-center rounded px-2 py-1.5 text-sm hover:bg-gray-100 ${className}`}>
      {children}
    </div>
  )
}

export const SelectGroup = ({ children }: { children?: React.ReactNode }) => <div>{children}</div>
export const SelectLabel = ({ children, className="" }: { children?: React.ReactNode, className?: string }) => (
  <div className={`px-2 py-1.5 text-xs font-medium text-gray-500 ${className}`}>{children}</div>
)
