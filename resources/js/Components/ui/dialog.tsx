import * as React from "react"

export const Dialog = ({ open, onOpenChange, children }: any) => {
  if (!open) return null
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center">
      <div className="fixed inset-0 bg-black/50" onClick={() => onOpenChange?.(false)} />
      <div className="relative z-50">{children}</div>
    </div>
  )
}

export const DialogTrigger = ({ children, asChild, onClick }: any) => (
  <span onClick={onClick}>{children}</span>
)

export const DialogContent = ({ children, className="" }: any) => (
  <div className={`relative bg-white rounded-lg shadow-lg p-6 w-full max-w-lg mx-4 ${className}`}>{children}</div>
)

export const DialogHeader = ({ children, className="" }: any) => (
  <div className={`flex flex-col space-y-1.5 mb-4 ${className}`}>{children}</div>
)

export const DialogTitle = ({ children, className="" }: any) => (
  <h2 className={`text-lg font-semibold ${className}`}>{children}</h2>
)

export const DialogDescription = ({ children, className="" }: any) => (
  <p className={`text-sm text-gray-500 ${className}`}>{children}</p>
)

export const DialogFooter = ({ children, className="" }: any) => (
  <div className={`flex justify-end gap-2 mt-4 ${className}`}>{children}</div>
)

export const DialogClose = ({ children, asChild }: any) => <span>{children}</span>
