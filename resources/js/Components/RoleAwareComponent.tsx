import type React from "react"
import { usePage } from "@inertiajs/react"

interface User {
  id: number
  name: string
  email: string
  // Add other user properties as needed
}

interface Auth {
  user: User
  roles?: string[]
  permissions?: string[]
}

interface PageProps {
  auth: Auth
}

interface RoleAwareComponentProps {
  children: React.ReactNode
  requiredRoles?: string[]
  requiredPermissions?: string[]
  fallback?: React.ReactNode
}

export default function RoleAwareComponent({
  children,
  requiredRoles = [],
  requiredPermissions = [],
  fallback = null,
}: RoleAwareComponentProps) {
  const { auth } = usePage<PageProps>().props

  // Safely check if roles and permissions exist
  const userRoles = auth?.roles || []
  const userPermissions = auth?.permissions || []

  // Allow Admin to bypass all role and permission checks
  const isAdmin = userRoles.includes("Admin")

  // Check if user has any of the required roles
  const hasRequiredRole = isAdmin || requiredRoles.length === 0 || requiredRoles.some((role) => userRoles.includes(role))

  // Check if user has any of the required permissions
  const hasRequiredPermission =
    isAdmin || requiredPermissions.length === 0 || requiredPermissions.some((permission) => userPermissions.includes(permission))

  // Render children only if user has required role and permission
  if (hasRequiredRole && hasRequiredPermission) {
    return <>{children}</>
  }

  // Otherwise render fallback (if provided)
  return <>{fallback}</>
}
