import { useState } from "react";
import { Link, usePage } from "@inertiajs/react";
import {
  Home, Users, Building, Calendar, ClipboardList, Layers,
  ClipboardCheck, Settings, BookOpen, GraduationCap,
  BarChart3, Scale, ChevronDown, ChevronRight, Clock,
  FileText, AlertTriangle, MapPin, Download, UserCircle,
  BookMarked, FileCheck
} from "lucide-react";

export default function Sidebar() {
  const { auth } = usePage().props as any;
  const user = auth?.user;
  const permissions = user?.permissions || [];
  const roles = user?.roles || [];

  // ✅ Initialize with all sections closed INCLUDING SI and all timetable office dropdowns
  const [openSections, setOpenSections] = useState<Record<string, boolean>>({
    schools: false,
    sces: false,
    sbs: false,
    sls: false,
    shss: false,
    sms: false,
    sth: false,
    si: false,
    // Class Timetable Office sections
    'timetable-schools': false,
    'timetable-sces': false,
    'timetable-sbs': false,
    'timetable-sls': false,
    'timetable-shss': false,
    'timetable-sms': false,
    'timetable-sth': false,
    'timetable-si': false,
    // Exam Timetable Office sections
    'exam-timetable-schools': false,
    'exam-timetable-sces': false,
    'exam-timetable-sbs': false,
    'exam-timetable-sls': false,
    'exam-timetable-shss': false,
    'exam-timetable-sms': false,
    'exam-timetable-sth': false,
    'exam-timetable-si': false,
  });

  const can = (perm: string) => permissions.includes(perm);
  const isRole = (role: string) => roles.includes(role);
  const isAdmin = isRole('Admin');
  const isFacultyAdmin = roles.some((r: string) => r.startsWith('Faculty Admin'));
  const isClassTimetableOffice = isRole('Class Timetable office');
  const isExamTimetableOffice = isRole('Exam Timetable office');
  const isExamOffice = isRole('Exam Office');
  const isLecturer = isRole('Lecturer');
  const isStudent = isRole('Student');

  const getSchoolCode = (): string | null => {
    const facultyRole = roles.find((r: string) => r.startsWith('Faculty Admin - '));
    if (facultyRole) {
      return facultyRole.replace('Faculty Admin - ', '').toLowerCase();
    }
    return null;
  };

  const schoolCode = getSchoolCode();

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

        {/* STUDENT SECTION */}
        {isStudent && (
          <div className="mt-4">
            <div className="px-4 py-2 text-xs font-semibold text-blue-300 uppercase">Student Portal</div>
            <Link href="/student/enrollments" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
              <BookMarked className="mr-3 h-5 w-5" />
              <span>My Units</span>
            </Link>
            <Link href="/student/timetable" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
              <Calendar className="mr-3 h-5 w-5" />
              <span>Class Timetable</span>
            </Link>
            <Link href="/student/examtimetable" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
              <FileCheck className="mr-3 h-5 w-5" />
              <span>Exam Timetable</span>
            </Link>
          </div>
        )}

        {/* LECTURER SECTION */}
        {isLecturer && (
          <div className="mt-4">
            <div className="px-4 py-2 text-xs font-semibold text-blue-300 uppercase">Lecturer Portal</div>
            <Link href="/lecturer/classes" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
              <BookOpen className="mr-3 h-5 w-5" />
              <span>My Classes</span>
            </Link>
            <Link href="/lecturer/class-timetable" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
              <Calendar className="mr-3 h-5 w-5" />
              <span>Class Timetable</span>
            </Link>
            <Link href="/lecturer/exam-supervision" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
              <ClipboardCheck className="mr-3 h-5 w-5" />
              <span>Exam Supervision</span>
            </Link>
          </div>
        )}

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
            {can('view-schools') && (
              <Link href="/admin/schools" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
                <Building className="mr-3 h-5 w-5" />
                  <span>Schools</span>
              </Link>
            )}
            {can('view-settings') && (
              <Link href="/admin/settings" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
                <Settings className="mr-3 h-5 w-5" />
                <span>System Settings</span>
              </Link>
            )}
          </div>
        )}

        {/* CLASS TIMETABLE OFFICE */}
        {isClassTimetableOffice && can('view-class-timetables') && (
          <div className="mt-4">
            <div className="px-4 py-2 text-xs font-semibold text-blue-300 uppercase">Class Timetable Management</div>
            
            {can('view-class-timetables') && (
              <div>
                <button
                  onClick={() => toggleSection('timetable-schools')}
                  className="flex items-center w-full px-4 py-2 hover:bg-blue-800 rounded-md"
                >
                  <Building className="mr-3 h-5 w-5" />
                  <span className="flex-1 text-left">School Timetables</span>
                  {openSections['timetable-schools'] ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
                </button>
                
                {openSections['timetable-schools'] && (
                  <div className="ml-4 mt-1 space-y-1">
                    {/* SCES */}
                    {can('view-programs') && (
                      <div>
                        <button
                          onClick={() => toggleSection('timetable-sces')}
                          className="flex items-center w-full px-4 py-2 hover:bg-blue-800 rounded-md text-sm"
                        >
                          <Building className="mr-3 h-4 w-4" />
                          <span className="flex-1 text-left">SCES</span>
                          {openSections['timetable-sces'] ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
                        </button>
                        {openSections['timetable-sces'] && (
                          <div className="ml-6 mt-1 space-y-1">
                            <Link href="/schools/sces/programs" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md text-xs">
                              <BookOpen className="mr-3 h-3 w-3" />
                              <span>SCES Programs</span>
                            </Link>
                          </div>
                        )}
                      </div>
                    )}

                    {/* SBS */}
                    {can('view-programs') && (
                      <div>
                        <button
                          onClick={() => toggleSection('timetable-sbs')}
                          className="flex items-center w-full px-4 py-2 hover:bg-blue-800 rounded-md text-sm"
                        >
                          <Building className="mr-3 h-4 w-4" />
                          <span className="flex-1 text-left">SBS</span>
                          {openSections['timetable-sbs'] ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
                        </button>
                        {openSections['timetable-sbs'] && (
                          <div className="ml-6 mt-1 space-y-1">
                            <Link href="/schools/sbs/programs" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md text-xs">
                              <BookOpen className="mr-3 h-3 w-3" />
                              <span>SBS Programs</span>
                            </Link>
                          </div>
                        )}
                      </div>
                    )}

                    {/* SLS */}
                    {can('view-programs') && (
                      <div>
                        <button
                          onClick={() => toggleSection('timetable-sls')}
                          className="flex items-center w-full px-4 py-2 hover:bg-blue-800 rounded-md text-sm"
                        >
                          <Building className="mr-3 h-4 w-4" />
                          <span className="flex-1 text-left">SLS</span>
                          {openSections['timetable-sls'] ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
                        </button>
                        {openSections['timetable-sls'] && (
                          <div className="ml-6 mt-1 space-y-1">
                            <Link href="/schools/sls/programs" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md text-xs">
                              <BookOpen className="mr-3 h-3 w-3" />
                              <span>SLS Programs</span>
                            </Link>
                          </div>
                        )}
                      </div>
                    )}

                    {/* SHSS */}
                    {can('view-programs') && (
                      <div>
                        <button
                          onClick={() => toggleSection('timetable-shss')}
                          className="flex items-center w-full px-4 py-2 hover:bg-blue-800 rounded-md text-sm"
                        >
                          <Building className="mr-3 h-4 w-4" />
                          <span className="flex-1 text-left">SHSS</span>
                          {openSections['timetable-shss'] ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
                        </button>
                        {openSections['timetable-shss'] && (
                          <div className="ml-6 mt-1 space-y-1">
                            <Link href="/schools/shss/programs" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md text-xs">
                              <BookOpen className="mr-3 h-3 w-3" />
                              <span>SHSS Programs</span>
                            </Link>
                          </div>
                        )}
                      </div>
                    )}

                    {/* SMS */}
                    {can('view-programs') && (
                      <div>
                        <button
                          onClick={() => toggleSection('timetable-sms')}
                          className="flex items-center w-full px-4 py-2 hover:bg-blue-800 rounded-md text-sm"
                        >
                          <Building className="mr-3 h-4 w-4" />
                          <span className="flex-1 text-left">SMS</span>
                          {openSections['timetable-sms'] ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
                        </button>
                        {openSections['timetable-sms'] && (
                          <div className="ml-6 mt-1 space-y-1">
                            <Link href="/schools/sms/programs" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md text-xs">
                              <BookOpen className="mr-3 h-3 w-3" />
                              <span>SMS Programs</span>
                            </Link>
                          </div>
                        )}
                      </div>
                    )}

                    {/* STH */}
                    {can('view-programs') && (
                      <div>
                        <button
                          onClick={() => toggleSection('timetable-sth')}
                          className="flex items-center w-full px-4 py-2 hover:bg-blue-800 rounded-md text-sm"
                        >
                          <Building className="mr-3 h-4 w-4" />
                          <span className="flex-1 text-left">STH</span>
                          {openSections['timetable-sth'] ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
                        </button>
                        {openSections['timetable-sth'] && (
                          <div className="ml-6 mt-1 space-y-1">
                            <Link href="/schools/sth/programs" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md text-xs">
                              <BookOpen className="mr-3 h-3 w-3" />
                              <span>STH Programs</span>
                            </Link>
                          </div>
                        )}
                      </div>
                    )}

                    {/* SI */}
                    {can('view-programs') && (
                      <div>
                        <button
                          onClick={() => toggleSection('timetable-si')}
                          className="flex items-center w-full px-4 py-2 hover:bg-blue-800 rounded-md text-sm"
                        >
                          <Building className="mr-3 h-4 w-4" />
                          <span className="flex-1 text-left">SI</span>
                          {openSections['timetable-si'] ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
                        </button>
                        {openSections['timetable-si'] && (
                          <div className="ml-6 mt-1 space-y-1">
                            <Link href="/schools/si/programs" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md text-xs">
                              <BookOpen className="mr-3 h-3 w-3" />
                              <span>SI Programs</span>
                            </Link>
                          </div>
                        )}
                      </div>
                    )}
                  </div>
                )}
              </div>
            )}

            {can('view-classtimeslots') && (
              <Link href="/classtimeslot" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
                <Clock className="mr-3 h-5 w-5" />
                <span>Time Slots</span>
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

        {/* EXAM OFFICE */}
        {isExamOffice && (
          <div className="mt-4">
            <div className="px-4 py-2 text-xs font-semibold text-blue-300 uppercase">Exam Management</div>
            
            {can('view-exam-rooms') && (
              <Link href="/examoffice/examrooms" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
                <FileText className="mr-3 h-5 w-5" />
                <span>Exam Rooms</span>
              </Link>
            )}

            {can('view-exam-timetables') && can('view-programs') && (
              <div>
                <button
                  onClick={() => toggleSection('exam-schools')}
                  className="flex items-center w-full px-4 py-2 hover:bg-blue-800 rounded-md"
                >
                  <Building className="mr-3 h-5 w-5" />
                  <span className="flex-1 text-left">School Exam Schedules</span>
                  {openSections['exam-schools'] ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
                </button>
                
                {openSections['exam-schools'] && (
                  <div className="ml-4 mt-1 space-y-1">
                    
                    {/* SCES */}
                    <div>
                      <button
                        onClick={() => toggleSection('exam-sces')}
                        className="flex items-center w-full px-4 py-2 hover:bg-blue-800 rounded-md text-sm"
                      >
                        <Building className="mr-3 h-4 w-4" />
                        <span className="flex-1 text-left">SCES</span>
                        {openSections['exam-sces'] ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
                      </button>
                      {openSections['exam-sces'] && (
                        <div className="ml-6 mt-1 space-y-1">
                          <Link href="/schools/sces/programs" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md text-xs">
                            <BookOpen className="mr-3 h-3 w-3" />
                            <span>SCES Programs</span>
                          </Link>
                        </div>
                      )}
                    </div>

                    {/* SBS */}
                    <div>
                      <button
                        onClick={() => toggleSection('exam-sbs')}
                        className="flex items-center w-full px-4 py-2 hover:bg-blue-800 rounded-md text-sm"
                      >
                        <Building className="mr-3 h-4 w-4" />
                        <span className="flex-1 text-left">SBS</span>
                        {openSections['exam-sbs'] ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
                      </button>
                      {openSections['exam-sbs'] && (
                        <div className="ml-6 mt-1 space-y-1">
                          <Link href="/schools/sbs/programs" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md text-xs">
                            <BookOpen className="mr-3 h-3 w-3" />
                            <span>SBS Programs</span>
                          </Link>
                        </div>
                      )}
                    </div>

                    {/* SLS */}
                    <div>
                      <button
                        onClick={() => toggleSection('exam-sls')}
                        className="flex items-center w-full px-4 py-2 hover:bg-blue-800 rounded-md text-sm"
                      >
                        <Building className="mr-3 h-4 w-4" />
                        <span className="flex-1 text-left">SLS</span>
                        {openSections['exam-sls'] ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
                      </button>
                      {openSections['exam-sls'] && (
                        <div className="ml-6 mt-1 space-y-1">
                          <Link href="/schools/sls/programs" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md text-xs">
                            <BookOpen className="mr-3 h-3 w-3" />
                            <span>SLS Programs</span>
                          </Link>
                        </div>
                      )}
                    </div>

                    {/* SHSS */}
                    <div>
                      <button
                        onClick={() => toggleSection('exam-shss')}
                        className="flex items-center w-full px-4 py-2 hover:bg-blue-800 rounded-md text-sm"
                      >
                        <Building className="mr-3 h-4 w-4" />
                        <span className="flex-1 text-left">SHSS</span>
                        {openSections['exam-shss'] ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
                      </button>
                      {openSections['exam-shss'] && (
                        <div className="ml-6 mt-1 space-y-1">
                          <Link href="/schools/shss/programs" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md text-xs">
                            <BookOpen className="mr-3 h-3 w-3" />
                            <span>SHSS Programs</span>
                          </Link>
                        </div>
                      )}
                    </div>

                    {/* SMS */}
                    <div>
                      <button
                        onClick={() => toggleSection('exam-sms')}
                        className="flex items-center w-full px-4 py-2 hover:bg-blue-800 rounded-md text-sm"
                      >
                        <Building className="mr-3 h-4 w-4" />
                        <span className="flex-1 text-left">SMS</span>
                        {openSections['exam-sms'] ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
                      </button>
                      {openSections['exam-sms'] && (
                        <div className="ml-6 mt-1 space-y-1">
                          <Link href="/schools/sms/programs" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md text-xs">
                            <BookOpen className="mr-3 h-3 w-3" />
                            <span>SMS Programs</span>
                          </Link>
                        </div>
                      )}
                    </div>

                    {/* STH */}
                    <div>
                      <button
                        onClick={() => toggleSection('exam-sth')}
                        className="flex items-center w-full px-4 py-2 hover:bg-blue-800 rounded-md text-sm"
                      >
                        <Building className="mr-3 h-4 w-4" />
                        <span className="flex-1 text-left">STH</span>
                        {openSections['exam-sth'] ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
                      </button>
                      {openSections['exam-sth'] && (
                        <div className="ml-6 mt-1 space-y-1">
                          <Link href="/schools/sth/programs" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md text-xs">
                            <BookOpen className="mr-3 h-3 w-3" />
                            <span>STH Programs</span>
                          </Link>
                        </div>
                      )}
                    </div>

                    {/* SI */}
                    <div>
                      <button
                        onClick={() => toggleSection('exam-si')}
                        className="flex items-center w-full px-4 py-2 hover:bg-blue-800 rounded-md text-sm"
                      >
                        <Building className="mr-3 h-4 w-4" />
                        <span className="flex-1 text-left">SI</span>
                        {openSections['exam-si'] ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
                      </button>
                      {openSections['exam-si'] && (
                        <div className="ml-6 mt-1 space-y-1">
                          <Link href="/schools/si/programs" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md text-xs">
                            <BookOpen className="mr-3 h-3 w-3" />
                            <span>SI Programs</span>
                          </Link>
                        </div>
                      )}
                    </div>
                  </div>
                )}
              </div>
            )}

            {can('solve-exam-conflicts') && (
              <Link href="/examoffice/conflicts" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
                <AlertTriangle className="mr-3 h-5 w-5" />
                <span>Resolve Conflicts</span>
              </Link>
            )}

            {can('download-exam-timetables') && (
              <Link href="/examoffice/download/pdf" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
                <Download className="mr-3 h-5 w-5" />
                <span>Download Reports</span>
              </Link>
            )}
          </div>
        )}

        {/* EXAM TIMETABLE OFFICE */}
        {isExamTimetableOffice && can('view-exam-timetables') && (
          <div className="mt-4">
            <div className="px-4 py-2 text-xs font-semibold text-blue-300 uppercase">Exam Timetable Management</div>
            
            {can('view-exam-timetables') && (
              <div>
                <button
                  onClick={() => toggleSection('exam-timetable-schools')}
                  className="flex items-center w-full px-4 py-2 hover:bg-blue-800 rounded-md"
                >
                  <Building className="mr-3 h-5 w-5" />
                  <span className="flex-1 text-left">School Timetables</span>
                  {openSections['exam-timetable-schools'] ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
                </button>
                
                {openSections['exam-timetable-schools'] && (
                  <div className="ml-4 mt-1 space-y-1">
                    {/* SCES */}
                    {can('view-programs') && (
                      <div>
                        <button
                          onClick={() => toggleSection('exam-timetable-sces')}
                          className="flex items-center w-full px-4 py-2 hover:bg-blue-800 rounded-md text-sm"
                        >
                          <Building className="mr-3 h-4 w-4" />
                          <span className="flex-1 text-left">SCES</span>
                          {openSections['exam-timetable-sces'] ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
                        </button>
                        {openSections['exam-timetable-sces'] && (
                          <div className="ml-6 mt-1 space-y-1">
                            <Link href="/schools/sces/programs" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md text-xs">
                              <BookOpen className="mr-3 h-3 w-3" />
                              <span>SCES Programs</span>
                            </Link>
                          </div>
                        )}
                      </div>
                    )}

                    {/* SBS */}
                    {can('view-programs') && (
                      <div>
                        <button
                          onClick={() => toggleSection('exam-timetable-sbs')}
                          className="flex items-center w-full px-4 py-2 hover:bg-blue-800 rounded-md text-sm"
                        >
                          <Building className="mr-3 h-4 w-4" />
                          <span className="flex-1 text-left">SBS</span>
                          {openSections['exam-timetable-sbs'] ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
                        </button>
                        {openSections['exam-timetable-sbs'] && (
                          <div className="ml-6 mt-1 space-y-1">
                            <Link href="/schools/sbs/programs" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md text-xs">
                              <BookOpen className="mr-3 h-3 w-3" />
                              <span>SBS Programs</span>
                            </Link>
                          </div>
                        )}
                      </div>
                    )}

                    {/* SLS */}
                    {can('view-programs') && (
                      <div>
                        <button
                          onClick={() => toggleSection('exam-timetable-sls')}
                          className="flex items-center w-full px-4 py-2 hover:bg-blue-800 rounded-md text-sm"
                        >
                          <Building className="mr-3 h-4 w-4" />
                          <span className="flex-1 text-left">SLS</span>
                          {openSections['exam-timetable-sls'] ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
                        </button>
                        {openSections['exam-timetable-sls'] && (
                          <div className="ml-6 mt-1 space-y-1">
                            <Link href="/schools/sls/programs" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md text-xs">
                              <BookOpen className="mr-3 h-3 w-3" />
                              <span>SLS Programs</span>
                            </Link>
                          </div>
                        )}
                      </div>
                    )}

                    {/* SHSS */}
                    {can('view-programs') && (
                      <div>
                        <button
                          onClick={() => toggleSection('exam-timetable-shss')}
                          className="flex items-center w-full px-4 py-2 hover:bg-blue-800 rounded-md text-sm"
                        >
                          <Building className="mr-3 h-4 w-4" />
                          <span className="flex-1 text-left">SHSS</span>
                          {openSections['exam-timetable-shss'] ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
                        </button>
                        {openSections['exam-timetable-shss'] && (
                          <div className="ml-6 mt-1 space-y-1">
                            <Link href="/schools/shss/programs" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md text-xs">
                              <BookOpen className="mr-3 h-3 w-3" />
                              <span>SHSS Programs</span>
                            </Link>
                          </div>
                        )}
                      </div>
                    )}

                    {/* SMS */}
                    {can('view-programs') && (
                      <div>
                        <button
                          onClick={() => toggleSection('exam-timetable-sms')}
                          className="flex items-center w-full px-4 py-2 hover:bg-blue-800 rounded-md text-sm"
                        >
                          <Building className="mr-3 h-4 w-4" />
                          <span className="flex-1 text-left">SMS</span>
                          {openSections['exam-timetable-sms'] ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
                        </button>
                        {openSections['exam-timetable-sms'] && (
                          <div className="ml-6 mt-1 space-y-1">
                            <Link href="/schools/sms/programs" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md text-xs">
                              <BookOpen className="mr-3 h-3 w-3" />
                              <span>SMS Programs</span>
                            </Link>
                          </div>
                        )}
                      </div>
                    )}

                    {/* STH */}
                    {can('view-programs') && (
                      <div>
                        <button
                          onClick={() => toggleSection('exam-timetable-sth')}
                          className="flex items-center w-full px-4 py-2 hover:bg-blue-800 rounded-md text-sm"
                        >
                          <Building className="mr-3 h-4 w-4" />
                          <span className="flex-1 text-left">STH</span>
                          {openSections['exam-timetable-sth'] ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
                        </button>
                        {openSections['exam-timetable-sth'] && (
                          <div className="ml-6 mt-1 space-y-1">
                            <Link href="/schools/sth/programs" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md text-xs">
                              <BookOpen className="mr-3 h-3 w-3" />
                              <span>STH Programs</span>
                            </Link>
                          </div>
                        )}
                      </div>
                    )}

                    {/* SI */}
                    {can('view-programs') && (
                      <div>
                        <button
                          onClick={() => toggleSection('exam-timetable-si')}
                          className="flex items-center w-full px-4 py-2 hover:bg-blue-800 rounded-md text-sm"
                        >
                          <Building className="mr-3 h-4 w-4" />
                          <span className="flex-1 text-left">SI</span>
                          {openSections['exam-timetable-si'] ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
                        </button>
                        {openSections['exam-timetable-si'] && (
                          <div className="ml-6 mt-1 space-y-1">
                            <Link href="/schools/si/programs" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md text-xs">
                              <BookOpen className="mr-3 h-3 w-3" />
                              <span>SI Programs</span>
                            </Link>
                          </div>
                        )}
                      </div>
                    )}
                  </div>
                )}
              </div>
            )}

            {can('view-classtimeslots') && (
              <Link href="/classtimeslot" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
                <Clock className="mr-3 h-5 w-5" />
                <span>Time Slots</span>
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
            {can('view-classtimeslots') && (
              <Link href="/classtimeslot" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
                <Clock className="mr-3 h-5 w-5" />
                <span>Time Slots</span>
              </Link>
            )}           
          </div>
        )}

        {/* ✅ SCHOOLS MANAGEMENT - Admin sees all */}
        {(isAdmin && can('view-schools')) && (
          <div className="mt-4">
            <div className="px-4 py-2 text-xs font-semibold text-blue-300 uppercase">Schools Management</div>
            
            <div>
              <button
                onClick={() => toggleSection('schools')}
                className="flex items-center w-full px-4 py-2 hover:bg-blue-800 rounded-md"
              >
                <Building className="mr-3 h-5 w-5" />
                <span className="flex-1 text-left">Schools</span>
                {openSections['schools'] ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
              </button>
              
              {openSections['schools'] && (
                <div className="ml-4 mt-1 space-y-1">
                  {/* SCES */}
                  {can('view-programs') && (
                    <div>
                      <button
                        onClick={() => toggleSection('sces')}
                        className="flex items-center w-full px-4 py-2 hover:bg-blue-800 rounded-md text-sm"
                      >
                        <Building className="mr-3 h-4 w-4" />
                        <span className="flex-1 text-left">SCES</span>
                        {openSections['sces'] ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
                      </button>
                      {openSections['sces'] && (
                        <div className="ml-6 mt-1 space-y-1">
                          <Link href="/schools/sces/programs" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md text-xs">
                            <BookOpen className="mr-3 h-3 w-3" />
                            <span>Programs</span>
                          </Link>
                        </div>
                      )}
                    </div>
                  )}

                  {/* SBS */}
                  {can('view-programs') && (
                    <div>
                      <button
                        onClick={() => toggleSection('sbs')}
                        className="flex items-center w-full px-4 py-2 hover:bg-blue-800 rounded-md text-sm"
                      >
                        <Building className="mr-3 h-4 w-4" />
                        <span className="flex-1 text-left">SBS</span>
                        {openSections['sbs'] ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
                      </button>
                      {openSections['sbs'] && (
                        <div className="ml-6 mt-1 space-y-1">
                          <Link href="/schools/sbs/programs" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md text-xs">
                            <BookOpen className="mr-3 h-3 w-3" />
                            <span>Programs</span>
                          </Link>
                        </div>
                      )}
                    </div>
                  )}

                  {/* SLS */}
                  {can('view-programs') && (
                    <div>
                      <button
                        onClick={() => toggleSection('sls')}
                        className="flex items-center w-full px-4 py-2 hover:bg-blue-800 rounded-md text-sm"
                      >
                        <Building className="mr-3 h-4 w-4" />
                        <span className="flex-1 text-left">SLS</span>
                        {openSections['sls'] ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
                      </button>
                      {openSections['sls'] && (
                        <div className="ml-6 mt-1 space-y-1">
                          <Link href="/schools/sls/programs" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md text-xs">
                            <BookOpen className="mr-3 h-3 w-3" />
                            <span>Programs</span>
                          </Link>
                        </div>
                      )}
                    </div>
                  )}

                  {/* SHSS */}
                  {can('view-programs') && (
                    <div>
                      <button
                        onClick={() => toggleSection('shss')}
                        className="flex items-center w-full px-4 py-2 hover:bg-blue-800 rounded-md text-sm"
                      >
                        <Building className="mr-3 h-4 w-4" />
                        <span className="flex-1 text-left">SHSS</span>
                        {openSections['shss'] ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
                      </button>
                      {openSections['shss'] && (
                        <div className="ml-6 mt-1 space-y-1">
                          <Link href="/schools/shss/programs" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md text-xs">
                            <BookOpen className="mr-3 h-3 w-3" />
                            <span>Programs</span>
                          </Link>
                        </div>
                      )}
                    </div>
                  )}

                  {/* SMS */}
                  {can('view-programs') && (
                    <div>
                      <button
                        onClick={() => toggleSection('sms')}
                        className="flex items-center w-full px-4 py-2 hover:bg-blue-800 rounded-md text-sm"
                      >
                        <Building className="mr-3 h-4 w-4" />
                        <span className="flex-1 text-left">SMS</span>
                        {openSections['sms'] ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
                      </button>
                      {openSections['sms'] && (
                        <div className="ml-6 mt-1 space-y-1">
                          <Link href="/schools/sms/programs" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md text-xs">
                            <BookOpen className="mr-3 h-3 w-3" />
                            <span>Programs</span>
                          </Link>
                        </div>
                      )}
                    </div>
                  )}

                  {/* STH */}
                  {can('view-programs') && (
                    <div>
                      <button
                        onClick={() => toggleSection('sth')}
                        className="flex items-center w-full px-4 py-2 hover:bg-blue-800 rounded-md text-sm"
                      >
                        <Building className="mr-3 h-4 w-4" />
                        <span className="flex-1 text-left">STH</span>
                        {openSections['sth'] ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
                      </button>
                      {openSections['sth'] && (
                        <div className="ml-6 mt-1 space-y-1">
                          <Link href="/schools/sth/programs" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md text-xs">
                            <BookOpen className="mr-3 h-3 w-3" />
                            <span>Programs</span>
                          </Link>
                        </div>
                      )}
                    </div>
                  )}

                  {/* ✅ SI - ADDED TO ADMIN SCHOOLS MANAGEMENT */}
                  {can('view-programs') && (
                    <div>
                      <button
                        onClick={() => toggleSection('si')}
                        className="flex items-center w-full px-4 py-2 hover:bg-blue-800 rounded-md text-sm"
                      >
                        <Building className="mr-3 h-4 w-4" />
                        <span className="flex-1 text-left">SI</span>
                        {openSections['si'] ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
                      </button>
                      {openSections['si'] && (
                        <div className="ml-6 mt-1 space-y-1">
                          <Link href="/schools/si/programs" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md text-xs">
                            <BookOpen className="mr-3 h-3 w-3" />
                            <span>Programs</span>
                          </Link>
                        </div>
                      )}
                    </div>
                  )}
                </div>
              )}
            </div>
          </div>
        )}

        {/* FACULTY ADMIN - Only Their School */}
        {isFacultyAdmin && schoolCode && (
          <div className="mt-4">
            <div className="px-4 py-2 text-xs font-semibold text-blue-300 uppercase">{schoolCode.toUpperCase()} Management</div>
            
            {can(`view-schools-${schoolCode}`) && (
              <div>
                <button
                  onClick={() => toggleSection(`faculty-${schoolCode}`)}
                  className="flex items-center w-full px-4 py-2 hover:bg-blue-800 rounded-md"
                >
                  <Building className="mr-3 h-5 w-5" />
                  <span className="flex-1 text-left">{schoolCode.toUpperCase()}</span>
                  {openSections[`faculty-${schoolCode}`] ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
                </button>
                
                {openSections[`faculty-${schoolCode}`] && (
                  <div className="ml-6 mt-1 space-y-1">
                    {can('view-programs') && (
                      <Link href={`/schools/${schoolCode}/programs`} className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md text-sm">
                        <BookOpen className="mr-3 h-4 w-4" />
                        <span>Programs</span>
                      </Link>
                    )}
                  </div>
                )}
              </div>
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