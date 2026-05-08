import * as React from "react"

export const Sidebar = ({ children, className = "" }: { children?: React.ReactNode, className?: string }) => (
  <aside className={`flex flex-col h-full bg-white border-r border-gray-200 ${className}`}>{children}</aside>
)

export const SidebarHeader = ({ children, className = "" }: { children?: React.ReactNode, className?: string }) => (
  <div className={`flex items-center px-4 py-3 border-b border-gray-200 ${className}`}>{children}</div>
)

export const SidebarContent = ({ children, className = "" }: { children?: React.ReactNode, className?: string }) => (
  <div className={`flex-1 overflow-y-auto px-2 py-3 ${className}`}>{children}</div>
)

export const SidebarFooter = ({ children, className = "" }: { children?: React.ReactNode, className?: string }) => (
  <div className={`px-4 py-3 border-t border-gray-200 ${className}`}>{children}</div>
)

export const SidebarGroup = ({ children, className = "" }: { children?: React.ReactNode, className?: string }) => (
  <div className={`mb-4 ${className}`}>{children}</div>
)

export const SidebarGroupLabel = ({ children, className = "" }: { children?: React.ReactNode, className?: string }) => (
  <div className={`px-2 mb-1 text-xs font-medium text-gray-500 uppercase tracking-wider ${className}`}>{children}</div>
)

export const SidebarGroupContent = ({ children, className = "" }: { children?: React.ReactNode, className?: string }) => (
  <div className={className}>{children}</div>
)

export const SidebarMenu = ({ children, className = "" }: { children?: React.ReactNode, className?: string }) => (
  <ul className={`space-y-1 ${className}`}>{children}</ul>
)

export const SidebarMenuItem = ({ children, className = "" }: { children?: React.ReactNode, className?: string }) => (
  <li className={className}>{children}</li>
)

export const SidebarMenuButton = ({ children, className = "", onClick, asChild }: any) => (
  <button onClick={onClick}
    className={`w-full flex items-center gap-2 px-2 py-2 text-sm rounded-md text-gray-700 hover:bg-gray-100 transition-colors ${className}`}>
    {children}
  </button>
)

export const SidebarProvider = ({ children }: { children?: React.ReactNode }) => <>{children}</>
export const SidebarTrigger = ({ className = "" }: { className?: string }) => (
  <button className={`p-2 rounded-md hover:bg-gray-100 ${className}`}>☰</button>
)
export const SidebarInset = ({ children, className = "" }: { children?: React.ReactNode, className?: string }) => (
  <div className={`flex-1 ${className}`}>{children}</div>
)

export default Sidebar
