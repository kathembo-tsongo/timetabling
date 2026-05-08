import * as React from "react"
interface SwitchProps { checked?: boolean; onCheckedChange?: (v: boolean) => void; className?: string }
const Switch = ({ checked = false, onCheckedChange, className = "" }: SwitchProps) => (
  <button type="button" role="switch" aria-checked={checked}
    onClick={() => onCheckedChange?.(!checked)}
    className={`relative inline-flex h-5 w-9 items-center rounded-full transition-colors ${checked ? 'bg-blue-600' : 'bg-gray-200'} ${className}`}>
    <span className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${checked ? 'translate-x-4' : 'translate-x-1'}`} />
  </button>
)
export { Switch }
