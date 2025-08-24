"use client"

import { usePage } from "@inertiajs/react"
import type { ReactNode } from "react"

interface PermissionAwareComponentProps {
  requiredPermissions?: string[]
  requiredRoles?: string[]
  requireAll?: boolean
  children: ReactNode
}

export default function PermissionAwareComponent({
  requiredPermissions = [],
  requiredRoles = [],
  requireAll = false,
  children,
}: PermissionAwareComponentProps) {
  const { auth } = usePage().props as any

  if (!auth?.user) {
    return null
  }

  // Safely get user permissions and roles
  const userPermissions = auth.user.permissions || []
  const userRoles = auth.user.roles || []

  // Convert roles to array of strings if needed
  const roleNames = Array.isArray(userRoles)
    ? userRoles.map((role) => (typeof role === "string" ? role : role.name)).filter(Boolean)
    : []

  // Check permissions
  const hasPermissions =
    requiredPermissions.length === 0 ||
    (requireAll
      ? requiredPermissions.every((permission) => userPermissions.includes(permission))
      : requiredPermissions.some((permission) => userPermissions.includes(permission)))

  // Check roles
  const hasRoles =
    requiredRoles.length === 0 ||
    (requireAll
      ? requiredRoles.every((role) => roleNames.includes(role))
      : requiredRoles.some((role) => roleNames.includes(role)))

  // If both permissions and roles are specified, user must have both (unless requireAll is false)
  const hasAccess =
    requiredPermissions.length > 0 && requiredRoles.length > 0
      ? requireAll
        ? hasPermissions && hasRoles
        : hasPermissions || hasRoles
      : hasPermissions && hasRoles

  return hasAccess ? <>{children}</> : null
}
