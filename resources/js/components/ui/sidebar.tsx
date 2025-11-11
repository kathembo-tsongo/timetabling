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

  const [openSections, setOpenSections] = useState<Record<string, boolean>>({});

  const can = (perm: string) => permissions.includes(perm);
  const isRole = (role: string) => roles.includes(role);
  const isAdmin = isRole('Admin');
  const isFacultyAdmin = roles.some((r: string) => r.startsWith('Faculty Admin'));
  const isClassTimetableOffice = isRole('Class Timetable office');
  const isExamTimetableOffice = isRole('Exam Timetable office');
  const isExamOffice = isRole('Exam Office');
  const isLecturer = isRole('Lecturer');
  const isStudent = isRole('Student'); // ✅ NEW: Check for Student role

  // Extract school code from Faculty Admin role
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

        {/* ✅ STUDENT SECTION */}
        {isStudent && (
          <div className="mt-4">
            <div className="px-4 py-2 text-xs font-semibold text-blue-300 uppercase">Student Portal</div>
            
            {/* My Enrollments/Units */}
            <Link href="/student/enrollments" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
              <BookMarked className="mr-3 h-5 w-5" />
              <span>My Units</span>
            </Link>

            {/* Class Timetable */}
            <Link href="/student/timetable" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
              <Calendar className="mr-3 h-5 w-5" />
              <span>Class Timetable</span>
            </Link>

            {/* Exam Timetable */}
            <Link href="/student/examtimetable" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
              <FileCheck className="mr-3 h-5 w-5" />
              <span>Exam Timetable</span>
            </Link>

            {/* Profile
            <Link href="/student/profile" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
              <UserCircle className="mr-3 h-5 w-5" />
              <span>Profile</span>
            </Link> */}
          </div>
        )}

        {/* ✅ LECTURER SECTION */}
        {isLecturer && (
          <div className="mt-4">
            <div className="px-4 py-2 text-xs font-semibold text-blue-300 uppercase">Lecturer Portal</div>
            
            {/* My Classes */}
            <Link href="/lecturer/classes" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
              <BookOpen className="mr-3 h-5 w-5" />
              <span>My Classes</span>
            </Link>

            {/* Class Timetable */}
            <Link href="/lecturer/class-timetable" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
              <Calendar className="mr-3 h-5 w-5" />
              <span>Class Timetable</span>
            </Link>

            {/* Exam Supervision */}
            <Link href="/lecturer/exam-supervision" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
              <ClipboardCheck className="mr-3 h-5 w-5" />
              <span>Exam Supervision</span>
            </Link>

            {/* Profile
            <Link href="/lecturer/profile" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
              <UserCircle className="mr-3 h-5 w-5" />
              <span>Profile</span>
            </Link> */}
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
            {can('view-settings') && (
              <Link href="/admin/settings" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
                <Settings className="mr-3 h-5 w-5" />
                <span>System Settings</span>
              </Link>
            )}
          </div>
        )}

        {/* CLASS TIMETABLE OFFICE - Timetable Management for All Schools */}
        {isClassTimetableOffice && can('view-class-timetables') && (
          <div className="mt-4">
            <div className="px-4 py-2 text-xs font-semibold text-blue-300 uppercase">Class Timetable Management</div>
            
            {/* Schools Timetables Dropdown */}
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
                  </div>
                )}
              </div>
            )}

            {/* Time Slots */}
            {can('view-classtimeslots') && (
              <Link href="/classtimeslot" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
                <Clock className="mr-3 h-5 w-5" />
                <span>Time Slots</span>
              </Link>
            )}

            {/* Classrooms */}
            {can('view-classrooms') && (
              <Link href="/admin/classrooms" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
                <Layers className="mr-3 h-5 w-5" />
                <span>Classrooms</span>
              </Link>
            )}
          </div>
        )}

        {/* ✅ EXAM OFFICE - Complete Exam Management Section */}
        {isExamOffice && (
          <div className="mt-4">
            <div className="px-4 py-2 text-xs font-semibold text-blue-300 uppercase">Exam Management</div>
            
            {/* Exam Rooms */}
    {can('view-exam-rooms') && (
      <Link 
        href="/examoffice/examrooms" 
        className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md"
      >
        <FileText className="mr-3 h-5 w-5" />
        <span>Exam Rooms</span>
      </Link>
    )}

            {/* Schools Exam Timetables Dropdown */}
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
                    {/* SCES Exams */}
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

                    {/* SBS Exams */}
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

                    {/* SLS Exams */}
                    {/* <div>
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
                    </div>*/}
                  </div> 
                )}
              </div>
            )}            

            {/* Conflicts */}
            {can('solve-exam-conflicts') && (
              <Link href="/examoffice/conflicts" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
                <AlertTriangle className="mr-3 h-5 w-5" />
                <span>Resolve Conflicts</span>
              </Link>
            )}

            {/* Download Reports */}
            {can('download-exam-timetables') && (
              <Link href="/examoffice/download/pdf" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
                <Download className="mr-3 h-5 w-5" />
                <span>Download Reports</span>
              </Link>
            )}
          </div>
        )}

        {/* EXAM TIMETABLE OFFICE (if you have this as a separate role) */}
        {isExamTimetableOffice && can('view-exam-timetables') && (
          <div className="mt-4">
            <div className="px-4 py-2 text-xs font-semibold text-blue-300 uppercase">Exam Timetable Management</div>
            
            {/* Schools Timetables Dropdown */}
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
                  </div>
                )}
              </div>
            )}

            {/* Time Slots */}
            {can('view-classtimeslots') && (
              <Link href="/classtimeslot" className="flex items-center px-4 py-2 hover:bg-blue-800 rounded-md">
                <Clock className="mr-3 h-5 w-5" />
                <span>Time Slots</span>
              </Link>
            )}

            {/* Classrooms */}
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

        {/* SCHOOLS MANAGEMENT - Admin sees all */}
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