"use client"

import { useState } from "react"
import { Link, usePage } from "@inertiajs/react"
import {
  Home,
  Users,
  Building,
  Calendar,
  ClipboardList,
  Layers,
  ClipboardCheck,
  Settings,
  BookOpen,
  GraduationCap,
  Bell,
  BarChart3,
  Clock,
  ChevronDown,
  ChevronRight,
  Scale,
  TrendingUp,
} from "lucide-react"

// Types for better type safety
interface Permission {
  name: string
  description?: string
}

interface Role {
  name: string
  permissions: Permission[]
}

interface User {
  id: number
  name: string
  roles: Role[]
  permissions: Permission[]
}

interface NavigationItem {
  label: string
  href?: string
  icon: React.ComponentType<any>
  permissions?: string[]
  roles?: string[]
  children?: NavigationItem[]
  type?: 'link' | 'section' | 'dropdown'
  schoolCode?: string
  programCode?: string
}

interface NavigationSection {
  title: string
  items: NavigationItem[]
  permissions?: string[]
  roles?: string[]
}

export default function Sidebar() {
  const { auth } = usePage().props as any
  const user = auth.user as User

  // DEBUG: Remove this after checking
  console.log('User object:', user)
  console.log('User roles:', user?.roles)
  console.log('User permissions:', user?.permissions)

  const [openSections, setOpenSections] = useState<Record<string, boolean>>({})

  // Permission and role checking utilities (Spatie compatible)
  const hasPermission = (permission: string): boolean => {
    // Check direct permissions
    if (user?.permissions) {
      const hasDirectPermission = Array.isArray(user.permissions) && user.permissions.some(
        (p: any) => (typeof p === 'string' ? p === permission : p.name === permission)
      )
      if (hasDirectPermission) return true
    }
    
    // Check permissions through roles (Spatie style)
    if (user?.roles) {
      return user.roles.some((role: any) => {
        return role.permissions && role.permissions.some((p: any) => 
          typeof p === 'string' ? p === permission : p.name === permission
        )
      })
    }
    
    return false
  }

  const hasRole = (role: string): boolean => {
    if (!user?.roles) return false
    if (Array.isArray(user.roles)) {
      return user.roles.some((r: any) => (typeof r === "string" ? r === role : r.name === role))
    }
    return false
  }

  const hasAnyPermission = (permissions: string[]): boolean => {
    return permissions.some(permission => hasPermission(permission))
  }

  const hasAnyRole = (roles: string[]): boolean => {
    return roles.some(role => hasRole(role))
  }

  const canAccess = (item: NavigationItem): boolean => {
    // TEMPORARY: If user has 'ADM1' in their name/email, give full access for setup
    if (user?.name?.includes('ADM1') || user?.email?.includes('ADM1')) {
      return true;
    }
    
    // If no restrictions, allow access
    if (!item.permissions && !item.roles) return true
    
    // Check permissions
    if (item.permissions && !hasAnyPermission(item.permissions)) return false
    
    // Check roles
    if (item.roles && !hasAnyRole(item.roles)) return false
    
    return true
  }

  const toggleSection = (section: string) => {
    setOpenSections(prev => ({
      ...prev,
      [section]: !prev[section]
    }))
  }

  // Dynamic school configuration
  const schoolsConfig = [
    {
      code: 'sces',
      name: 'SCES',
      fullName: 'Computing & Engineering',
      role: 'Faculty Admin - SCES',
      icon: GraduationCap,
      programs: [
        { code: 'bbit', name: 'BBIT', fullName: 'Bachelor of Business Information Technology' },
        { code: 'ics', name: 'ICS', fullName: 'Information Communication Systems' },
        { code: 'cs', name: 'CS', fullName: 'Computer Science' },
      ]
    },
    {
      code: 'sbs',
      name: 'SBS',
      fullName: 'Business Studies',
      role: 'Faculty Admin - SBS',
      icon: TrendingUp,
      programs: [
        { code: 'mba', name: 'MBA', fullName: 'Master of Business Administration' },
        { code: 'bba', name: 'BBA', fullName: 'Bachelor of Business Administration' },
        { code: 'bcom', name: 'BCOM', fullName: 'Bachelor of Commerce' },
      ]
    },
    {
      code: 'sls',
      name: 'SLS',
      fullName: 'Law Studies',
      role: 'Faculty Admin - SLS',
      icon: Scale,
      programs: [
        { code: 'llb', name: 'LLB', fullName: 'Bachelor of Laws' },
        { code: 'llm', name: 'LLM', fullName: 'Master of Laws' },
      ]
    }
  ]

  // Navigation configuration - completely dynamic
  const navigationConfig: NavigationSection[] = [
    {
      title: "Dashboard",
      items: [
        {
          label: "Dashboard",
          href: "/dashboard",
          icon: Home,
          type: 'link',
          permissions: ["view-dashboard"],
          roles: ["Admin"] // Add Admin role as fallback
        }
      ]
    },
    {
      title: "Faculty Dashboards",
      items: schoolsConfig
        .filter(school => hasRole(school.role))
        .map(school => ({
          label: `${school.name} Dashboard`,
          href: `/facultyadmin/${school.code}`,
          icon: Home,
          type: 'link' as const,
          roles: [school.role]
        })),
      roles: schoolsConfig.map(school => school.role)
    },
    {
      title: "Academic Management",
      items: [
        {
          label: "Schools & Programs",
          icon: Building,
          type: 'dropdown',
          children: schoolsConfig
            .filter(school => hasRole(school.role))
            .map(school => ({
              label: school.name,
              icon: school.icon,
              type: 'dropdown' as const,
              children: school.programs.map(program => ({
                label: program.name,
                icon: BookOpen,
                type: 'dropdown' as const,
                children: [
                  {
                    label: "Dashboard",
                    href: `/facultyadmin/${school.code}/${program.code}`,
                    icon: Home,
                    type: 'link' as const
                  },
                  {
                    label: "Units",
                    href: `/facultyadmin/${school.code}/${program.code}/units`,
                    icon: BookOpen,
                    type: 'link' as const
                  },
                  {
                    label: "Enrollments",
                    href: `/facultyadmin/${school.code}/${program.code}/enrollments`,
                    icon: ClipboardList,
                    type: 'link' as const
                  },
                  {
                    label: "Classes",
                    href: `/facultyadmin/${school.code}/${program.code}/classes`,
                    icon: Users,
                    type: 'link' as const,
                    permissions: ["manage-classes"]
                  },
                  {
                    label: "Students",
                    href: `/facultyadmin/${school.code}/${program.code}/students`,
                    icon: GraduationCap,
                    type: 'link' as const,
                    permissions: ["manage-students"]
                  },
                  {
                    label: "Lecturers",
                    href: `/facultyadmin/${school.code}/${program.code}/lecturers`,
                    icon: Users,
                    type: 'link' as const,
                    permissions: ["manage-lecturers"]
                  },
                  {
                    label: "Timetables",
                    href: `/facultyadmin/${school.code}/${program.code}/timetables`,
                    icon: Calendar,
                    type: 'link' as const,
                    permissions: ["manage-timetables"]
                  }
                ]
              }))
            }))
        },
        {
          label: "Semesters",
          href: "/semesters",
          icon: Calendar,
          type: 'link',
          permissions: ["manage-semesters"]
        },
        {
          label: "Classrooms",
          href: "admin/Classrooms",
          icon: Building,
          type: 'link',
          permissions: ["manage-classrooms"]
        }
      ],
      roles: schoolsConfig.map(school => school.role)
    },
    {
      title: "System Administration",
      items: [
        {
          label: "Users",
          href: "/admin/users",
          icon: Users,
          type: 'link',
          permissions: ["manage-users"],
          roles: ["Admin"]
        },
        {
          label: "Dynamic Roles",
          href: "/admin/roles/dynamic",
          icon: Layers,
          type: 'link',
          permissions: ["manage-roles"],
          roles: ["Admin"]
        },
        {
          label: "Dynamic Permissions", 
          href: "/admin/permissions/dynamic",
          icon: Settings,
          type: 'link',
          permissions: ["manage-permissions"],
          roles: ["Admin"]
        },
        {
          label: "User Roles",
          href: "/admin/roles/users",
          icon: Users,
          type: 'link',
          permissions: ["manage-user-roles"],
          roles: ["Admin"]
        },
        {
          label: "System Settings",
          href: "/admin/settings",
          icon: Settings,
          type: 'link',
          permissions: ["manage-settings"],
          roles: ["Admin"]
        }
      ],
      permissions: ["manage-users", "manage-roles", "manage-settings", "manage-permissions", "manage-user-roles"],
      roles: ["Admin"]
    },
    {
      title: "Academic Infrastructure",
      items: [
        {
          label: "Semesters",
          href: "/admin/semesters",
          icon: Calendar,
          type: 'link',
          permissions: ["manage-semesters"],
          roles: ["Admin"]
        },
        {
          label: "Enrollment",
          href: "/admin/enrollments",
          icon: Calendar,
          type: 'link',
          permissions: ["manage-enrollments"],
          roles: ["Admin"]
        },
        {
          label: "Groups",
          href: "/admin/groups",
          icon: Calendar,
          type: 'link',
          permissions: ["manage-groups"],
          roles: ["Admin"]
        },
        {
          label: "Classes",
          href: "/admin/classes",
          icon: Calendar,
          type: 'link',
          permissions: ["manage-classes"],
          roles: ["Admin"]
        },
        {
          label: "Classrooms",
          href: "/admin/classrooms",
          icon: Building,
          type: 'link',
          permissions: ["manage-classrooms"],
          roles: ["Admin"]
        },
        {
          label: "Exam Rooms",
          href: "/admin/examrooms",
          icon: Building,
          type: 'link',
          permissions: ["manage-exam-rooms"],
          roles: ["Admin"]
        },
        {
          label: "Time Slots",
          href: "/admin/timeslots",
          icon: Clock,
          type: 'link',
          permissions: ["manage-time-slots"],
          roles: ["Admin"]
        },
        {
          label: "Schools Management",
          icon: Building,
          type: 'dropdown',
          permissions: ["manage-schools"],
          roles: ["Admin"],
          children: [
            {
              label: "All Schools",
              href: "/admin/schools",
              icon: Building,
              type: 'link'
            },
            ...schoolsConfig.map(school => ({
              label: school.name,
              icon: school.icon,
              type: 'dropdown' as const,
              children: [
                {
                  label: "Manage Programs",
                  href: `/schools/${school.code}/programs`,
                  icon: BookOpen,
                  type: 'link' as const
                }
              ]
            }))
          ]
        },
        {
          label: "All Units",
          icon: BookOpen,
          type: 'dropdown',
          permissions: ["manage-units"],
          roles: ["Admin"],
          children: [
            {
              label: "Manage All Units",
              href: "/admin/units",
              icon: BookOpen,
              type: 'link'
            },
            {
              label: "Assign to Semesters",
              href: "/admin/units/assign-semesters",
              icon: Calendar,
              type: 'link'
            },
            // {
            //   label: "Assign Units to Lecturers",
            //   href: "/admin/lecturerassignment",
            //   icon: Calendar,
            //   type: 'link'
            // },
            // {
            //   label: "Bulk Operations",
            //   href: "/admin/units/bulk-operations",
            //   icon: Layers,
            //   type: 'link'
            // }
          ]
        }
      ],
      permissions: [
        "manage-schools", "manage-programs", "manage-semesters", 
        "manage-classrooms", "manage-units", "manage-exam-rooms", 
        "manage-time-slots"
      ],
      roles: ["Admin"]
    },
    {
      title: "Timetables",
      items: [
        {
          label: "Class Timetables",
          href: "/admin/classtimetable",
          icon: Calendar,
          type: 'link',
          permissions: ["manage-class-timetables"]
        },
        {
          label: "Exam Timetables",
          href: "/examtimetables",
          icon: ClipboardCheck,
          type: 'link',
          permissions: ["manage-exam-timetables"]
        },
        {
          label: "Time Slots",
          href: "/timeslots",
          icon: Clock,
          type: 'link',
          permissions: ["manage-time-slots"]
        }
      ],
      permissions: ["manage-timetables", "manage-class-timetables", "manage-exam-timetables"]
    },
    {
      title: "Student Portal",
      items: [        
        {
          label: "Enrollment",
          href: "/enroll",
          icon: ClipboardList,
          type: 'link'
        },
        {
          label: "My Class Timetable",
          href: "/student/timetable",
          icon: Calendar,
          type: 'link'
        },
        {
          label: "My Exam Timetable",
          href: "/student/exam-timetable",
          icon: Calendar,
          type: 'link'
        }
      ],
      roles: ["Student"]
    },
    {
      title: "Lecturer Portal",
      items: [
        {
          label: "My Classes",
          href: "/my-classes",
          icon: Calendar,
          type: 'link'
        },
        {
          label: "My Timetables",
          href: "/my-timetables",
          icon: Calendar,
          type: 'link'
        }
      ],
      roles: ["Lecturer"]
    },
    {
      title: "Exam Office",
      items: [
        {
          label: "Exam Timetables",
          href: "/examtimetables",
          icon: ClipboardCheck,
          type: 'link'
        },
        {
          label: "Exam Rooms",
          href: "/examrooms",
          icon: Building,
          type: 'link'
        },
        {
          label: "Time Slots",
          href: "/timeslots",
          icon: Clock,
          type: 'link'
        }
      ],
      roles: ["Exam office"]
    },
    {
      title: "Reports",
      items: [
        {
          label: "Generate Reports",
          href: "/reports",
          icon: BarChart3,
          type: 'link'
        }
      ],
      permissions: ["generate-reports"]
    }
  ]

  // Check if section should be visible
  const shouldShowSection = (section: NavigationSection): boolean => {
    // If no restrictions, show section
    if (!section.permissions && !section.roles) {
      return section.items.some(item => canAccess(item))
    }
    
    // Check section-level permissions/roles
    if (section.permissions && !hasAnyPermission(section.permissions)) return false
    if (section.roles && !hasAnyRole(section.roles)) return false
    
    return true
  }

  // Recursive component to render navigation items
  const NavigationItem = ({ 
    item, 
    level = 0, 
    sectionKey 
  }: { 
    item: NavigationItem
    level?: number
    sectionKey?: string
  }) => {
    if (!canAccess(item)) return null

    const itemKey = sectionKey ? `${sectionKey}-${item.label.toLowerCase().replace(/\s+/g, '-')}` : item.label.toLowerCase().replace(/\s+/g, '-')
    const Icon = item.icon
    const indentClass = level > 0 ? `ml-${level * 6}` : ''

    if (item.type === 'dropdown' && item.children) {
      return (
        <div key={itemKey}>
          <button
            type="button"
            className={`flex items-center w-full px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700 focus:outline-none ${indentClass}`}
            onClick={() => toggleSection(itemKey)}
          >
            <Icon className="mr-3 h-5 w-5" />
            {item.label}
            {openSections[itemKey] ? (
              <ChevronDown className="ml-auto h-4 w-4" />
            ) : (
              <ChevronRight className="ml-auto h-4 w-4" />
            )}
          </button>
          
          {openSections[itemKey] && (
            <div className="mt-1 space-y-1">
              {item.children.map((child, index) => (
                <NavigationItem 
                  key={`${itemKey}-${index}`}
                  item={child} 
                  level={level + 1}
                  sectionKey={itemKey}
                />
              ))}
            </div>
          )}
        </div>
      )
    }

    return (
      <Link
        key={itemKey}
        href={item.href || '#'}
        className={`flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700 ${indentClass}`}
      >
        <Icon className="mr-3 h-5 w-5" />
        {item.label}
      </Link>
    )
  }

  return (
    <div className="w-64 bg-blue-800 text-white h-full flex flex-col">
      <div className="p-4 border-b border-gray-700">
        <h1 className="text-xl font-bold">Timetabling System</h1>
      </div>
      
      <div className="flex-1 overflow-y-auto py-4">
        <nav className="px-2 space-y-1">
          {navigationConfig.map((section, sectionIndex) => {
            if (!shouldShowSection(section)) return null

            const visibleItems = section.items.filter(item => canAccess(item))
            if (visibleItems.length === 0) return null

            return (
              <div key={sectionIndex} className="pt-4">
                <p className="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                  {section.title}
                </p>
                
                {visibleItems.map((item, itemIndex) => (
                  <NavigationItem 
                    key={`${sectionIndex}-${itemIndex}`}
                    item={item}
                    sectionKey={`section-${sectionIndex}`}
                  />
                ))}
              </div>
            )
          })}
        </nav>
      </div>
    </div>
  )
}