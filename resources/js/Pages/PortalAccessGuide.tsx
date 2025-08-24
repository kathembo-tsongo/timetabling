import { Head } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"

export default function PortalAccessGuide() {
  return (
    <AuthenticatedLayout>
      <Head title="Portal Access Guide" />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div className="p-6 bg-white border-b border-gray-200">
              <h1 className="text-2xl font-semibold text-gray-900 mb-6">Portal Access Guide</h1>

              <div className="mb-8">
                <h2 className="text-xl font-medium text-blue-600 mb-4">Lecturer Portal</h2>
                <p className="mb-4">
                  Lecturers can access the system to view their assigned classes, manage their students, and check their
                  exam schedules.
                </p>

                <div className="bg-gray-50 p-4 rounded-lg mb-4">
                  <h3 className="text-lg font-medium text-gray-800 mb-2">Key Features for Lecturers</h3>
                  <ul className="list-disc pl-5 space-y-2">
                    <li>
                      <span className="font-medium">My Classes:</span> View all assigned classes, including schedules,
                      locations, and enrolled students.
                    </li>
                    <li>
                      <span className="font-medium">My Students:</span> Access student lists for each class, view
                      student details, and track attendance.
                    </li>
                    <li>
                      <span className="font-medium">My Exam Schedule:</span> View upcoming exams, including dates,
                      times, locations, and assigned proctoring duties.
                    </li>
                  </ul>
                </div>

                <div className="bg-blue-50 p-4 rounded-lg">
                  <h3 className="text-lg font-medium text-blue-800 mb-2">How Lecturers Access the System</h3>
                  <ol className="list-decimal pl-5 space-y-2">
                    <li>Lecturers log in with their institutional credentials</li>
                    <li>The system automatically detects their role and displays the Lecturer Portal</li>
                    <li>They can navigate through the sidebar menu to access different features</li>
                    <li>
                      Changes made by administrators (like class assignments) are immediately visible to lecturers
                    </li>
                  </ol>
                </div>
              </div>

              <div className="mb-8">
                <h2 className="text-xl font-medium text-green-600 mb-4">Student Portal</h2>
                <p className="mb-4">
                  Students can access the system to enroll in units, view their enrollments, check their timetables, and
                  see their exam schedules.
                </p>

                <div className="bg-gray-50 p-4 rounded-lg mb-4">
                  <h3 className="text-lg font-medium text-gray-800 mb-2">Key Features for Students</h3>
                  <ul className="list-disc pl-5 space-y-2">
                    <li>
                      <span className="font-medium">Enroll in Units:</span> Browse available units and enroll in them
                      for the current semester.
                    </li>
                    <li>
                      <span className="font-medium">My Enrollments:</span> View all current and past enrollments,
                      including unit details and lecturers.
                    </li>
                    <li>
                      <span className="font-medium">My Timetable:</span> Access a personalized timetable showing all
                      enrolled classes with times and locations.
                    </li>
                    <li>
                      <span className="font-medium">My Exams:</span> View upcoming exams, including dates, times, and
                      locations.
                    </li>
                  </ul>
                </div>

                <div className="bg-green-50 p-4 rounded-lg">
                  <h3 className="text-lg font-medium text-green-800 mb-2">How Students Access the System</h3>
                  <ol className="list-decimal pl-5 space-y-2">
                    <li>Students log in with their institutional credentials</li>
                    <li>The system automatically detects their role and displays the Student Portal</li>
                    <li>They can navigate through the sidebar menu to access different features</li>
                    <li>Enrollments and timetables are personalized based on their selections and program</li>
                  </ol>
                </div>
              </div>

              <div className="mb-8">
                <h2 className="text-xl font-medium text-amber-600 mb-4">School Admin Portal</h2>
                <p className="mb-4">
              School administrators manage academic programs, units, classes, and lecturer assignments for their
                  school.
                </p>

                <div className="bg-gray-50 p-4 rounded-lg mb-4">
                  <h3 className="text-lg font-medium text-gray-800 mb-2">Key Features for Faculty Admins</h3>
                  <ul className="list-disc pl-5 space-y-2">
                    <li>
                      <span className="font-medium">Manage Programs:</span> Create and update academic programs offered
                      by the faculty.
                    </li>
                    <li>
                      <span className="font-medium">Manage Units:</span> Add, edit, and assign units to programs and
                      semesters.
                    </li>
                    <li>
                      <span className="font-medium">Manage Classes:</span> Create class groups and assign them to
                      specific programs and years.
                    </li>
                    <li>
                      <span className="font-medium">Manage Lecturers:</span> Assign lecturers to units and monitor
                      teaching loads.
                    </li>
                  </ul>
                </div>

                <div className="bg-amber-50 p-4 rounded-lg">
                  <h3 className="text-lg font-medium text-amber-800 mb-2">How Faculty Admins Access the System</h3>
                  <ol className="list-decimal pl-5 space-y-2">
                    <li>Faculty admins log in with their institutional credentials</li>
                    <li>The system automatically detects their role and displays the Faculty Admin Portal</li>
                    <li>They can navigate through the sidebar menu to access different management features</li>
                    <li>
                      Changes made by faculty admins affect what students and lecturers see in their respective portals
                    </li>
                  </ol>
                </div>
              </div>

              <div className="mb-8">
                <h2 className="text-xl font-medium text-red-600 mb-4">Exam Office Portal</h2>
                <p className="mb-4">
                  Exam office staff manage exam timetables, room allocations, and proctor assignments for all exams.
                </p>

                <div className="bg-gray-50 p-4 rounded-lg mb-4">
                  <h3 className="text-lg font-medium text-gray-800 mb-2">Key Features for Exam Office</h3>
                  <ul className="list-disc pl-5 space-y-2">
                    <li>
                      <span className="font-medium">Exam Timetables:</span> Create and manage exam schedules for all
                      units and programs.
                    </li>
                    <li>
                      <span className="font-medium">Exam Rooms:</span> Manage room availability and allocate rooms for
                      exams.
                    </li>
                    <li>
                      <span className="font-medium">Exam Time Slots:</span> Define time slots for exams and manage
                      scheduling conflicts.
                    </li>
                    <li>
                      <span className="font-medium">Proctor Assignments:</span> Assign lecturers and staff as proctors
                      for exams.
                    </li>
                  </ul>
                </div>

                <div className="bg-red-50 p-4 rounded-lg">
                  <h3 className="text-lg font-medium text-red-800 mb-2">How Exam Office Staff Access the System</h3>
                  <ol className="list-decimal pl-5 space-y-2">
                    <li>Exam office staff log in with their institutional credentials</li>
                    <li>The system automatically detects their role and displays the Exam Office Portal</li>
                    <li>They can navigate through the sidebar menu to access different exam management features</li>
                    <li>Changes made by exam office staff are reflected in student and lecturer exam schedules</li>
                  </ol>
                </div>
              </div>

              <div>
                <h2 className="text-xl font-medium text-purple-600 mb-4">Administrator Preview Access</h2>
                <p className="mb-4">
                  As an administrator, you have the ability to preview all portals to understand the user experience for
                  each role.
                </p>

                <div className="bg-purple-50 p-4 rounded-lg">
                  <h3 className="text-lg font-medium text-purple-800 mb-2">How to Use the Preview Feature</h3>
                  <ul className="list-disc pl-5 space-y-2">
                    <li>Use the "Portal Previews" section in the sidebar to access any portal</li>
                    <li>These previews show exactly what users with different roles see when they log in</li>
                    <li>You can interact with the interfaces to understand the user experience</li>
                    <li>Any changes made in preview mode are not saved to the actual system</li>
                    <li>This feature helps you ensure that the system is working correctly for all users</li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  )
}
