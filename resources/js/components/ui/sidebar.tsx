import { useState } from "react";
import { Link, usePage } from "@inertiajs/react";
import {
  Home, Users, Building, Calendar, ClipboardList, Layers,
  ClipboardCheck, Settings, BookOpen, GraduationCap,
  BarChart3, Scale, ChevronDown, ChevronRight
} from "lucide-react";

export default function Sidebar() {
  const { auth } = usePage().props as any;
  const user = auth?.user;
  const permissions = user?.permissions || [];
  const roles = user?.roles || [];
  const schoolCode = user?.school_code;

  const [openSections, setOpenSections] = useState<Record<string, boolean>>({});

  const can = (perm: string) => permissions.includes(perm);
  const isRole = (role: string) => roles.includes(role);
  const isAdmin = isRole('Admin');
  const isFacultyAdmin = roles.some((r: string) => r.startsWith('Faculty Admin'));

  const toggleSection = (key: string) => {
    setOpenSections(prev => ({ ...prev, [key]: !prev[key] }));
  };

  return (
    <div className="w-64 bg-blue-900 text-white h-full flex flex-col overflow-y-auto">
      <div className="p-4 border-b border-blue-700">
        <h1 className="text-xl font-bold">Timetabling System</h1>
      </div>

      <nav className="flex-1 py-4 px-2 space-y-1">
        
        {/* DASHBOARD */}
        <Link href="/dashboard" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
          <Home className="mr-3 h-5 w-5" />
          <span>Dashboard</span>
        </Link>

        {/* SYSTEM ADMINISTRATION - Admin Only */}
        {isAdmin && (
          <div className="mt-4">
            <div className="px-4 py-2 text-xs font-semibold text-blue-300 uppercase">System Administration</div>
            {can('view-users') && (
              <Link href="/admin/users" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
                <Users className="mr-3 h-5 w-5" />
                <span>Users</span>
              </Link>
            )}
            {can('view-roles') && (
              <Link href="/admin/roles/dynamic" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
                <Scale className="mr-3 h-5 w-5" />
                <span>Dynamic Roles</span>
              </Link>
            )}
            {can('view-permissions') && (
              <Link href="/admin/permissions/dynamic" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
                <Settings className="mr-3 h-5 w-5" />
                <span>Dynamic Permissions</span>
              </Link>
            )}
            <Link href="/admin/settings" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
              <Settings className="mr-3 h-5 w-5" />
              <span>System Settings</span>
            </Link>
          </div>
        )}

        {/* ACADEMIC INFRASTRUCTURE */}
        {(isFacultyAdmin || isAdmin) && (
          <div className="mt-4">
            <div className="px-4 py-2 text-xs font-semibold text-blue-300 uppercase">Academic Infrastructure</div>
            {can('view-semesters') && (
              <Link href="/admin/semesters" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
                <Calendar className="mr-3 h-5 w-5" />
                <span>Semesters</span>
              </Link>
            )}
            {can('view-classes') && (
              <Link href="/admin/classes" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
                <Users className="mr-3 h-5 w-5" />
                <span>Classes</span>
              </Link>
            )}
            {can('view-groups') && (
              <Link href="/admin/groups" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
                <Users className="mr-3 h-5 w-5" />
                <span>Groups</span>
              </Link>
            )}
            {can('view-buildings') && (
              <Link href="/admin/buildings" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
                <Building className="mr-3 h-5 w-5" />
                <span>Buildings</span>
              </Link>
            )}
            {can('view-classrooms') && (
              <Link href="/admin/classrooms" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
                <Layers className="mr-3 h-5 w-5" />
                <span>Classrooms</span>
              </Link>
            )}
          </div>
        )}

        {/* SCHOOLS MANAGEMENT - Hierarchical Structure */}
        {isAdmin && can('view-schools') && (
          <div className="mt-4">
            <div className="px-4 py-2 text-xs font-semibold text-blue-300 uppercase">Schools Management</div>
            <Link href="/admin/schools" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
              <Building className="mr-3 h-5 w-5" />
              <span>All Schools</span>
            </Link>
            
            {/* SCES Dropdown */}
            <div>
              <button
                onClick={() => toggleSection('sces')}
                className="flex items-center w-full px-4 py-2 hover:bg-blue-800 rounded-md"
              >
                <Building className="mr-3 h-5 w-5" />
                <span className="flex-1 text-left">SCES</span>
                {openSections['sces'] ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
              </button>
              {openSections['sces'] && (
                <div className="ml-6 mt-1 space-y-1">
                  <Link href="/schools/sces/programs" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md text-sm">
                    <BookOpen className="mr-3 h-4 w-4" />
                    <span>Programs</span>
                  </Link>
                </div>
              )}
            </div>

            {/* SBS Dropdown */}
            <div>
              <button
                onClick={() => toggleSection('sbs')}
                className="flex items-center w-full px-4 py-2 hover:bg-blue-800 rounded-md"
              >
                <Building className="mr-3 h-5 w-5" />
                <span className="flex-1 text-left">SBS</span>
                {openSections['sbs'] ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
              </button>
              {openSections['sbs'] && (
                <div className="ml-6 mt-1 space-y-1">
                  <Link href="/schools/sbs/programs" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md text-sm">
                    <BookOpen className="mr-3 h-4 w-4" />
                    <span>Programs</span>
                  </Link>
                </div>
              )}
            </div>

            {/* SLS Dropdown */}
            <div>
              <button
                onClick={() => toggleSection('sls')}
                className="flex items-center w-full px-4 py-2 hover:bg-blue-800 rounded-md"
              >
                <Building className="mr-3 h-5 w-5" />
                <span className="flex-1 text-left">SLS</span>
                {openSections['sls'] ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
              </button>
              {openSections['sls'] && (
                <div className="ml-6 mt-1 space-y-1">
                  <Link href="/schools/sls/programs" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md text-sm">
                    <BookOpen className="mr-3 h-4 w-4" />
                    <span>Programs</span>
                  </Link>
                </div>
              )}
            </div>
          </div>
        )}

        {/* SCHOOL-SPECIFIC (Faculty Admin) */}
        {isFacultyAdmin && schoolCode && can('view-programs') && (
          <div className="mt-4">
            <div className="px-4 py-2 text-xs font-semibold text-blue-300 uppercase">{schoolCode.toUpperCase()}</div>
            <Link href={`/schools/${schoolCode}/programs`} className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
              <BookOpen className="mr-3 h-5 w-5" />
              <span>Programs</span>
            </Link>
            {can('view-units') && (
              <Link href="/admin/units" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
                <ClipboardList className="mr-3 h-5 w-5" />
                <span>Units</span>
              </Link>
            )}
            {can('view-lecturer-assignments') && (
              <Link href="/admin/lecturerassignment" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
                <Users className="mr-3 h-5 w-5" />
                <span>Lecturer Assignments</span>
              </Link>
            )}
          </div>
        )}

        {/* REPORTS */}
        {can('view-reports') && (
          <div className="mt-4">
            <div className="px-4 py-2 text-xs font-semibold text-blue-300 uppercase">Reports</div>
            <Link href="#" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
              <BarChart3 className="mr-3 h-5 w-5" />
              <span>Generate Reports</span>
            </Link>
          </div>
        )}

      </nav>
    </div>
  );
}