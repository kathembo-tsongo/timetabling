interface User {
  id: number
  name: string
  email: string
  roles: Array<{ name: string }>
}

interface SidebarProps {
  user: User
}

export function RoleBasedSidebar({ user }: SidebarProps) {
  // Get user roles
  const userRoles = user.roles.map((role) => role.name)
  const isLecturer = userRoles.includes("Lecturer")
  const isStudent = userRoles.includes("Student")
  const isAdmin = userRoles.includes("Admin")
  const isExamOfficer = userRoles.includes("Exam office")

  return (
    <div className="w-64 bg-purple-900 text-white min-h-screen">
      {/* Header */}
      <div className="p-4 border-b border-purple-800">
        <h2 className="text-xl font-bold">Timetabling System Management</h2>
        <p className="text-purple-300 text-sm">{user.name}</p>
      </div>

      {/* Navigation */}
      <nav className="mt-4">
        {/* Dashboard - Always visible */}
        <div className="px-4 py-2">
          <a href="/dashboard" className="flex items-center space-x-2 text-purple-200 hover:text-white">
            <span>📊</span>
            <span>Dashboard</span>
          </a>
        </div>

        {/* STUDENT PORTAL - Only show if user is Student and NOT Lecturer */}
        {isStudent && !isLecturer && (
          <div className="mt-6">
            <div className="px-4 py-2">
              <h3 className="text-purple-300 text-sm font-semibold uppercase tracking-wider">Student Portal</h3>
            </div>
            <div className="space-y-1">
              <a href="/enroll" className="block px-4 py-2 text-purple-200 hover:text-white hover:bg-purple-800">
                📝 Enroll in Units
              </a>
              <a
                href="/my-enrollments"
                className="block px-4 py-2 text-purple-200 hover:text-white hover:bg-purple-800"
              >
                📋 My Enrollments
              </a>
              <a href="/my-timetable" className="block px-4 py-2 text-purple-200 hover:text-white hover:bg-purple-800">
                📅 My Timetable
              </a>
              <a href="/my-exams" className="block px-4 py-2 text-purple-200 hover:text-white hover:bg-purple-800">
                📝 My Exams
              </a>
            </div>
          </div>
        )}

        {/* LECTURER PORTAL - Only show if user is Lecturer */}
        {isLecturer && (
          <div className="mt-6">
            <div className="px-4 py-2">
              <h3 className="text-purple-300 text-sm font-semibold uppercase tracking-wider">Lecturer Portal</h3>
            </div>
            <div className="space-y-1">
              <a href="/my-classes" className="block px-4 py-2 text-purple-200 hover:text-white hover:bg-purple-800">
                🏫 My Classes
              </a>
              <a href="/my-units" className="block px-4 py-2 text-purple-200 hover:text-white hover:bg-purple-800">
                📚 My Units
              </a>
              <a
                href="/lecturer/timetable"
                className="block px-4 py-2 text-purple-200 hover:text-white hover:bg-purple-800"
              >
                📅 My Timetable
              </a>
              <a
                href="/lecturer/students"
                className="block px-4 py-2 text-purple-200 hover:text-white hover:bg-purple-800"
              >
                👥 My Students
              </a>
            </div>
          </div>
        )}

        {/* ADMIN PORTAL - Only show if user is Admin */}
        {isAdmin && (
          <div className="mt-6">
            <div className="px-4 py-2">
              <h3 className="text-purple-300 text-sm font-semibold uppercase tracking-wider">Admin Portal</h3>
            </div>
            <div className="space-y-1">
              <a href="/users" className="block px-4 py-2 text-purple-200 hover:text-white hover:bg-purple-800">
                👥 Users
              </a>
              <a
                href="/admin/enrollments"
                className="block px-4 py-2 text-purple-200 hover:text-white hover:bg-purple-800"
              >
                📋 Enrollments
              </a>
              <a href="/classes" className="block px-4 py-2 text-purple-200 hover:text-white hover:bg-purple-800">
                🏫 Classes
              </a>
              <a href="/units" className="block px-4 py-2 text-purple-200 hover:text-white hover:bg-purple-800">
                📚 Units
              </a>
            </div>
          </div>
        )}

        {/* EXAM OFFICE PORTAL - Only show if user is Exam Officer */}
        {isExamOfficer && (
          <div className="mt-6">
            <div className="px-4 py-2">
              <h3 className="text-purple-300 text-sm font-semibold uppercase tracking-wider">Exam Office</h3>
            </div>
            <div className="space-y-1">
              <a
                href="/exam-timetables"
                className="block px-4 py-2 text-purple-200 hover:text-white hover:bg-purple-800"
              >
                📝 Exam Timetables
              </a>
              <a href="/exam-results" className="block px-4 py-2 text-purple-200 hover:text-white hover:bg-purple-800">
                📊 Exam Results
              </a>
            </div>
          </div>
        )}
      </nav>

      {/* User Info */}
      <div className="absolute bottom-0 left-0 right-0 p-4 border-t border-purple-800">
        <div className="flex items-center space-x-2">
          <div className="w-8 h-8 bg-purple-600 rounded-full flex items-center justify-center">
            <span className="text-sm font-semibold">{user.name.charAt(0).toUpperCase()}</span>
          </div>
          <div>
            <p className="text-sm font-medium">{user.name}</p>
            <p className="text-xs text-purple-300">{userRoles.join(", ")}</p>
          </div>
        </div>
      </div>
    </div>
  )
}
