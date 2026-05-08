import * as React from "react"

const TabsContext = React.createContext<any>({})

export const Tabs = ({ value, onValueChange, defaultValue, children, className="" }: any) => {
  const [active, setActive] = React.useState(defaultValue || value)
  return (
    <TabsContext.Provider value={{ active: value ?? active, setActive: onValueChange ?? setActive }}>
      <div className={className}>{children}</div>
    </TabsContext.Provider>
  )
}

export const TabsList = ({ children, className="" }: any) => (
  <div className={`inline-flex h-10 items-center justify-center rounded-md bg-gray-100 p-1 ${className}`}>{children}</div>
)

export const TabsTrigger = ({ value, children, className="" }: any) => {
  const { active, setActive } = React.useContext(TabsContext)
  return (
    <button onClick={() => setActive(value)}
      className={`inline-flex items-center justify-center rounded-sm px-3 py-1.5 text-sm font-medium transition-all ${active === value ? "bg-white shadow-sm text-gray-900" : "text-gray-500 hover:text-gray-700"} ${className}`}>
      {children}
    </button>
  )
}

export const TabsContent = ({ value, children, className="" }: any) => {
  const { active } = React.useContext(TabsContext)
  if (active !== value) return null
  return <div className={`mt-2 ${className}`}>{children}</div>
}
