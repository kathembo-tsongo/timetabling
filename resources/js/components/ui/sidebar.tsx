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

export default function Sidebar() {
  const { auth } = usePage().props as any
  const user = auth.user

  // Permission checking function
  function hasPermission(user: any, permission: string): boolean {
    if (!user?.permissions) return false
    return Array.isArray(user.permissions) && user.permissions.includes(permission)
  }

  // Role checking function
  function hasRole(user: any, role: string): boolean {
    if (!user?.roles) return false
    if (Array.isArray(user.roles)) {
      return user.roles.some((r: any) => (typeof r === "string" ? r === role : r.name === role))
    }
    return false
  }

  // School-specific role checks
  const isFacultyAdminSCES = () => hasRole(user, "Faculty Admin - SCES")
  const isFacultyAdminSBS = () => hasRole(user, "Faculty Admin - SBS")

  const [openSections, setOpenSections] = useState<Record<string, boolean>>({})

  const toggleSection = (section: string) => {
    setOpenSections(prev => ({
      ...prev,
      [section]: !prev[section]
    }))
  }

  // Define schools with their programs
  const schools = [
    {
      code: 'sces',
      name: 'SCES',
      fullName: 'Computing & Engineering',
      hasAccess: isFacultyAdminSCES(),
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
      hasAccess: isFacultyAdminSBS(),
      programs: [
        { code: 'mba', name: 'MBA', fullName: 'Master of Business Administration' },
        { code: 'bba', name: 'BBA', fullName: 'Bachelor of Business Administration' },
        { code: 'bcom', name: 'BCOM', fullName: 'Bachelor of Commerce' },
      ]
    }
  ]

  return (
    <div className="w-64 bg-blue-800 text-white h-full flex flex-col">
      <div className="p-4 border-b border-gray-700">
        <h1 className="text-xl font-bold">Timetabling System</h1>
      </div>
      
      <div className="flex-1 overflow-y-auto py-4">
        <nav className="px-2 space-y-1">
          
          {/* Dashboard */}
          {hasPermission(user, "view-dashboard") && (
            <Link
              href="/dashboard"
              className="flex items-center px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
            >
              <Home className="mr-3 h-5 w-5" />
              Dashboard
            </Link>
          )}

          {/* Faculty Dashboards */}
          {(isFacultyAdminSCES() || isFacultyAdminSBS()) && (
            <div className="pt-4">
              <p className="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                Faculty Dashboards
              </p>
              
              {isFacultyAdminSCES() && (
                <Link
                  href="/facultyadmin/sces"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700 bg-blue-700/50"
                >
                  <Home className="mr-3 h-5 w-5 text-blue-300" />
                  <div>
                    <div className="font-medium">SCES Dashboard</div>
                    <div className="text-xs text-blue-200">Computing & Engineering</div>
                  </div>
                </Link>
              )}

              {isFacultyAdminSBS() && (
                <Link
                  href="/facultyadmin/sbs"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700 bg-green-700/50"
                >
                  <Home className="mr-3 h-5 w-5 text-green-300" />
                  <div>
                    <div className="font-medium">SBS Dashboard</div>
                    <div className="text-xs text-green-200">Business Studies</div>
                  </div>
                </Link>
              )}
            </div>
          )}

          {/* Academic Management - Unified Structure */}
          {(isFacultyAdminSCES() || isFacultyAdminSBS()) && (
            <div className="pt-4">
              <p className="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                Academic Management
              </p>
              
              <div>
                <button
                  type="button"
                  className="flex items-center w-full px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700 focus:outline-none"
                  onClick={() => toggleSection("schools")}
                >
                  <Building className="mr-3 h-5 w-5" />
                  Schools & Programs
                  {openSections.schools ? (
                    <ChevronDown className="ml-auto h-4 w-4" />
                  ) : (
                    <ChevronRight className="ml-auto h-4 w-4" />
                  )}
                </button>
                
                {openSections.schools && (
                  <div className="ml-6 mt-1 space-y-1">
                    {schools.map((school) => (
                      school.hasAccess && (
                        <div key={school.code}>
                          <button
                            type="button"
                            className="flex items-center w-full px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700 focus:outline-none"
                            onClick={() => toggleSection(school.code)}
                          >
                            <GraduationCap className="mr-3 h-5 w-5" />
                            {school.name}
                            {openSections[school.code] ? (
                              <ChevronDown className="ml-auto h-4 w-4" />
                            ) : (
                              <ChevronRight className="ml-auto h-4 w-4" />
                            )}
                          </button>
                          
                          {openSections[school.code] && (
                            <div className="ml-6 mt-1 space-y-1">
                              {school.programs.map((program) => (
                                <div key={program.code}>
                                  <button
                                    type="button"
                                    className="flex items-center w-full px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700 focus:outline-none"
                                    onClick={() => toggleSection(`${school.code}-${program.code}`)}
                                  >
                                    <BookOpen className="mr-3 h-4 w-4" />
                                    {program.name}
                                    {openSections[`${school.code}-${program.code}`] ? (
                                      <ChevronDown className="ml-auto h-4 w-4" />
                                    ) : (
                                      <ChevronRight className="ml-auto h-4 w-4" />
                                    )}
                                  </button>
                                  
                                  {openSections[`${school.code}-${program.code}`] && (
                                    <div className="ml-6 mt-1 space-y-1">
                                      
                                      {/* Program Dashboard */}
                                      <Link
                                        href={`/facultyadmin/${school.code}/${program.code}`}
                                        className="flex items-center px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
                                      >
                                        <Home className="mr-3 h-4 w-4" />
                                        Dashboard
                                      </Link>

                                      {/* Units */}
                                      <Link
                                        href={`/facultyadmin/${school.code}/${program.code}/units`}
                                        className="flex items-center px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
                                      >
                                        <BookOpen className="mr-3 h-4 w-4" />
                                        Units
                                      </Link>

                                      {/* Enrollments */}
                                      <Link
                                        href={`/facultyadmin/${school.code}/${program.code}/enrollments`}
                                        className="flex items-center px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
                                      >
                                        <ClipboardList className="mr-3 h-4 w-4" />
                                        Enrollments
                                      </Link>

                                      {/* Classes */}
                                      {hasPermission(user, "manage-classes") && (
                                        <Link
                                          href={`/facultyadmin/${school.code}/${program.code}/classes`}
                                          className="flex items-center px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
                                        >
                                          <Users className="mr-3 h-4 w-4" />
                                          Classes
                                        </Link>
                                      )}

                                      {/* Students */}
                                      {hasPermission(user, "manage-students") && (
                                        <Link
                                          href={`/facultyadmin/${school.code}/${program.code}/students`}
                                          className="flex items-center px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
                                        >
                                          <GraduationCap className="mr-3 h-4 w-4" />
                                          Students
                                        </Link>
                                      )}

                                      {/* Lecturers */}
                                      {hasPermission(user, "manage-lecturers") && (
                                        <Link
                                          href={`/facultyadmin/${school.code}/${program.code}/lecturers`}
                                          className="flex items-center px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
                                        >
                                          <Users className="mr-3 h-4 w-4" />
                                          Lecturers
                                        </Link>
                                      )}

                                      {/* Timetables */}
                                      {hasPermission(user, "manage-timetables") && (
                                        <Link
                                          href={`/facultyadmin/${school.code}/${program.code}/timetables`}
                                          className="flex items-center px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
                                        >
                                          <Calendar className="mr-3 h-4 w-4" />
                                          Timetables
                                        </Link>
                                      )}
                                    </div>
                                  )}
                                </div>
                              ))}
                            </div>
                          )}
                        </div>
                      )
                    ))}
                  </div>
                )}
              </div>

              {/* Global Academic Items */}
              {hasPermission(user, "manage-semesters") && (
                <Link
                  href="/semesters"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <Calendar className="mr-3 h-5 w-5" />
                  Semesters
                </Link>
              )}

              {hasPermission(user, "manage-classrooms") && (
                <Link
                  href="/classrooms"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <Building className="mr-3 h-5 w-5" />
                  Classrooms
                </Link>
              )}
            </div>
          )}

          {/* System Administration */}
          {(hasPermission(user, "manage-users") ||
            hasPermission(user, "manage-roles") ||
            hasPermission(user, "manage-settings")) && (
            <div className="pt-4">
              <p className="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                System Administration
              </p>
              
              {hasPermission(user, "manage-users") && (
                <Link
                  href="/admin/users"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <Users className="mr-3 h-5 w-5" />
                  Users
                </Link>
              )}

              {hasPermission(user, "manage-roles") && (
                <Link
                  href="/admin/roles"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <Layers className="mr-3 h-5 w-5" />
                  Roles & Permissions
                </Link>
              )}

              {hasPermission(user, "manage-settings") && (
                <Link
                  href="/admin/settings"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <Settings className="mr-3 h-5 w-5" />
                  System Settings
                </Link>
              )}
            </div>
          )}

          {/* Academic Infrastructure */}
          {(hasPermission(user, "manage-schools") ||
            hasPermission(user, "manage-programs") ||
            hasPermission(user, "manage-semesters") ||
            hasPermission(user, "manage-classrooms") ||
            hasPermission(user, "manage-units") ||
            hasPermission(user, "manage-exam-rooms") ||
            hasPermission(user, "manage-time-slots") ||
            hasRole(user, "Admin")) && (
            <div className="pt-4">
              <p className="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                Academic Infrastructure
              </p>
              
              
              {(hasPermission(user, "manage-semesters") || hasRole(user, "Admin")) && (
                <Link
                  href="/admin/semesters"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <Calendar className="mr-3 h-5 w-5" />
                  Semesters
                </Link>
              )}

              {(hasPermission(user, "manage-classrooms") || hasRole(user, "Admin")) && (
                <Link
                  href="/admin/classrooms"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <Building className="mr-3 h-5 w-5" />
                  Classrooms
                </Link>
              )}

              {(hasPermission(user, "manage-exam-rooms") || hasRole(user, "Admin")) && (
                <Link
                  href="/admin/examrooms"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <Building className="mr-3 h-5 w-5" />
                  Exam Rooms
                </Link>
              )}

              {(hasPermission(user, "manage-time-slots") || hasRole(user, "Admin")) && (
                <Link
                  href="/admin/timeslots"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <Clock className="mr-3 h-5 w-5" />
                  Time Slots
                </Link>
              )}

              {/* Schools Management with Program Dropdowns */}
{(hasPermission(user, "manage-schools") || hasRole(user, "Admin")) && (
  <div>
    <button
      type="button"
      className="flex items-center w-full px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700 focus:outline-none"
      onClick={() => toggleSection("admin-schools")}
    >
      <Building className="mr-3 h-5 w-5" />
      Schools Management
      {openSections["admin-schools"] ? (
        <ChevronDown className="ml-auto h-4 w-4" />
      ) : (
        <ChevronRight className="ml-auto h-4 w-4" />
      )}
    </button>
    
    {openSections["admin-schools"] && (
      <div className="ml-6 mt-1 space-y-1">
        
        {/* All Schools Overview */}
        <Link
          href="/admin/schools"
          className="flex items-center px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
        >
          <Building className="mr-3 h-4 w-4" />
          All Schools
        </Link>

        {/* SCES School */}
        <div>
          <button
            type="button"
            className="flex items-center w-full px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700 focus:outline-none"
            onClick={() => toggleSection("admin-sces")}
          >
            <GraduationCap className="mr-3 h-4 w-4" />
            SCES
            {openSections["admin-sces"] ? (
              <ChevronDown className="ml-auto h-3 w-3" />
            ) : (
              <ChevronRight className="ml-auto h-3 w-3" />
            )}
          </button>
          
          {openSections["admin-sces"] && (
            <div className="ml-6 mt-1 space-y-1">
              <Link
                href="/schools/sces/programs"
                className="flex items-center px-4 py-2 text-xs font-medium rounded-md hover:bg-gray-700"
              >
                <BookOpen className="mr-3 h-3 w-3" />
                Manage Programs
              </Link>
            </div>
          )}
        </div>

        {/* SBS School */}
        <div>
          <button
            type="button"
            className="flex items-center w-full px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700 focus:outline-none"
            onClick={() => toggleSection("admin-sbs")}
          >
            <TrendingUp className="mr-3 h-4 w-4" />
            SBS
            {openSections["admin-sbs"] ? (
              <ChevronDown className="ml-auto h-3 w-3" />
            ) : (
              <ChevronRight className="ml-auto h-3 w-3" />
            )}
          </button>
          
          {openSections["admin-sbs"] && (
            <div className="ml-6 mt-1 space-y-1">
              <Link
                href="/schools/sbs/programs"
                className="flex items-center px-4 py-2 text-xs font-medium rounded-md hover:bg-gray-700"
              >
                <BookOpen className="mr-3 h-3 w-3" />
                Manage Programs
              </Link>
            </div>
          )}
        </div>

        {/* SLS School */}
        <div>
          <button
            type="button"
            className="flex items-center w-full px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700 focus:outline-none"
            onClick={() => toggleSection("admin-sls")}
          >
            <Scale className="mr-3 h-4 w-4" />
            SLS
            {openSections["admin-sls"] ? (
              <ChevronDown className="ml-auto h-3 w-3" />
            ) : (
              <ChevronRight className="ml-auto h-3 w-3" />
            )}
          </button>
          
          {openSections["admin-sls"] && (
            <div className="ml-6 mt-1 space-y-1">
              <Link
                href="/schools/sls/programs"
                className="flex items-center px-4 py-2 text-xs font-medium rounded-md hover:bg-gray-700"
              >
                <BookOpen className="mr-3 h-3 w-3" />
                Manage Programs
              </Link>
            </div>
          )}
        </div>
      </div>
    )}
  </div>
)}

              {(hasPermission(user, "manage-units") || hasRole(user, "Admin")) && (
                <div>
                  <button
                    type="button"
                    className="flex items-center w-full px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700 focus:outline-none"
                    onClick={() => toggleSection("admin-units")}
                  >
                    <BookOpen className="mr-3 h-5 w-5" />
                    All Units
                    {openSections["admin-units"] ? (
                      <ChevronDown className="ml-auto h-4 w-4" />
                    ) : (
                      <ChevronRight className="ml-auto h-4 w-4" />
                    )}
                  </button>
                  
                  {openSections["admin-units"] && (
                    <div className="ml-6 mt-1 space-y-1">
                      <Link
                        href="/admin/units"
                        className="flex items-center px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
                      >
                        <BookOpen className="mr-3 h-4 w-4" />
                        Manage All Units
                      </Link>
                      <Link
                        href="/admin/units/assign-semesters"
                        className="flex items-center px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
                      >
                        <Calendar className="mr-3 h-4 w-4" />
                        Assign to Semesters
                      </Link>
                      <Link
                        href="/admin/units/bulk-operations"
                        className="flex items-center px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-700"
                      >
                        <Layers className="mr-3 h-4 w-4" />
                        Bulk Operations
                      </Link>
                    </div>
                  )}
                </div>
              )}
            </div>
          )}

          {/* Timetables Section */}
          {(hasPermission(user, "manage-timetables") ||
            hasPermission(user, "manage-class-timetables") ||
            hasPermission(user, "manage-exam-timetables")) && (
            <div className="pt-4">
              <p className="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                Timetables
              </p>
              
              {hasPermission(user, "manage-class-timetables") && (
                <Link
                  href="/classtimetables"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <Calendar className="mr-3 h-5 w-5" />
                  Class Timetables
                </Link>
              )}

              {hasPermission(user, "manage-exam-timetables") && (
                <Link
                  href="/examtimetables"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <ClipboardCheck className="mr-3 h-5 w-5" />
                  Exam Timetables
                </Link>
              )}

              {hasPermission(user, "manage-time-slots") && (
                <Link
                  href="/timeslots"
                  className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
                >
                  <Clock className="mr-3 h-5 w-5" />
                  Time Slots
                </Link>
              )}
            </div>
          )}

          {/* Student Section */}
          {hasRole(user, "Student") && (
            <div className="pt-4">
              <p className="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                Student Portal
              </p>
              
              <Link
                href="/my-timetable"
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <Calendar className="mr-3 h-5 w-5" />
                My Timetable
              </Link>

              <Link
                href="/enroll"
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <ClipboardList className="mr-3 h-5 w-5" />
                Enrollment
              </Link>
            </div>
          )}

          {/* Lecturer Section */}
          {hasRole(user, "Lecturer") && (
            <div className="pt-4">
              <p className="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                Lecturer Portal
              </p>
              
              <Link
                href="/my-classes"
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <Calendar className="mr-3 h-5 w-5" />
                My Classes
              </Link>

              <Link
                href="/my-timetables"
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <Calendar className="mr-3 h-5 w-5" />
                My Timetables
              </Link>
            </div>
          )}

          {/* Exam Office Section */}
          {hasRole(user, "Exam office") && (
            <div className="pt-4">
              <p className="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                Exam Office
              </p>
              
              <Link
                href="/examtimetables"
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <ClipboardCheck className="mr-3 h-5 w-5" />
                Exam Timetables
              </Link>

              <Link
                href="/examrooms"
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <Building className="mr-3 h-5 w-5" />
                Exam Rooms
              </Link>

              <Link
                href="/timeslots"
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <Clock className="mr-3 h-5 w-5" />
                Time Slots
              </Link>
            </div>
          )}

          {/* Reports Section */}
          {hasPermission(user, "generate-reports") && (
            <div className="pt-4">
              <p className="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                Reports
              </p>
              
              <Link
                href="/reports"
                className="flex items-center px-4 py-2 mt-1 text-sm font-medium rounded-md hover:bg-gray-700"
              >
                <BarChart3 className="mr-3 h-5 w-5" />
                Generate Reports
              </Link>
            </div>
          )}
        </nav>
      </div>
    </div>
  )
}