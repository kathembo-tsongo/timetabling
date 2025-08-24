"use client"
import { Head, usePage, router } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import route from "ziggy-js"

interface ExamTimetable {
  id: number
  date: string
  day: string
  start_time: string
  end_time: string
  unit: {
    code: string
    name: string
  }
  semester: {
    name: string
  }
  venue: string
  location: string
}

interface Student {
  id: number
  code: string
  first_name: string
  last_name: string
  email: string
}

interface Change {
  old: string
  new: string
}

interface PageProps {
  exam: ExamTimetable
  students: Student[]
  studentCount: number
  changes: Record<string, Change>
}

export default function PreviewUpdate({ auth }) {
  const { exam, students, studentCount, changes } = usePage<PageProps>().props

  const formatDate = (dateString: string) => {
    const options: Intl.DateTimeFormatOptions = {
      year: "numeric",
      month: "long",
      day: "numeric",
    }
    return new Date(dateString).toLocaleDateString(undefined, options)
  }

  const handleTestSend = () => {
    if (confirm("Send a test update notification to your email?")) {
      router.post(route("notifications.test-update", exam.id))
    }
  }

  return (
    <AuthenticatedLayout
      user={auth.user}
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Preview Update Notification</h2>}
    >
      <Head title={`Preview Update Notification - ${exam.unit.code}`} />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
          <div className="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
            <div className="flex items-center justify-between mb-6">
              <div className="flex items-center">
                <button
                  onClick={() => router.visit(route("notifications.index"))}
                  className="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 mr-4"
                >
                  <svg
                    className="mr-2 h-4 w-4"
                    xmlns="http://www.w3.org/2000/svg"
                    width="24"
                    height="24"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                  >
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                  </svg>
                  Back to Dashboard
                </button>
                <h2 className="text-lg font-medium text-gray-900">Preview Update Notification for {exam.unit.code}</h2>
              </div>
              <button
                onClick={handleTestSend}
                className="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
              >
                <svg
                  className="mr-2 h-4 w-4"
                  xmlns="http://www.w3.org/2000/svg"
                  width="24"
                  height="24"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="2"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                >
                  <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                  <polyline points="22,6 12,13 2,6"></polyline>
                </svg>
                Send Test Email
              </button>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
              {/* Exam Details Card */}
              <div className="bg-white border rounded-lg shadow-sm md:col-span-1">
                <div className="p-4 border-b">
                  <h3 className="text-lg font-medium">Exam Details</h3>
                  <p className="text-sm text-gray-500">Information about the exam</p>
                </div>
                <div className="p-4 space-y-4">
                  <div>
                    <div className="text-sm font-medium text-gray-500">Unit</div>
                    <div className="font-medium">{exam.unit.code}</div>
                    <div className="text-sm">{exam.unit.name}</div>
                  </div>

                  <div>
                    <div className="text-sm font-medium text-gray-500">Date & Time</div>
                    <div className="font-medium">
                      {formatDate(exam.date)} ({exam.day})
                    </div>
                    <div className="text-sm">
                      {exam.start_time} - {exam.end_time}
                    </div>
                  </div>

                  <div>
                    <div className="text-sm font-medium text-gray-500">Venue</div>
                    <div className="font-medium">{exam.venue}</div>
                    <div className="text-sm">{exam.location}</div>
                  </div>

                  <div>
                    <div className="text-sm font-medium text-gray-500">Semester</div>
                    <div className="font-medium">{exam.semester.name}</div>
                  </div>
                </div>
              </div>

              {/* Students Card */}
              <div className="bg-white border rounded-lg shadow-sm md:col-span-2">
                <div className="p-4 border-b">
                  <div className="flex items-center">
                    <svg
                      className="mr-2 h-5 w-5"
                      xmlns="http://www.w3.org/2000/svg"
                      width="24"
                      height="24"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      strokeWidth="2"
                      strokeLinecap="round"
                      strokeLinejoin="round"
                    >
                      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                      <circle cx="9" cy="7" r="4"></circle>
                      <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                      <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    <h3 className="text-lg font-medium">Enrolled Students</h3>
                  </div>
                  <p className="text-sm text-gray-500">{studentCount} students will receive notifications</p>
                </div>
                <div className="p-4">
                  <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                      <thead className="bg-gray-50">
                        <tr>
                          <th
                            scope="col"
                            className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                          >
                            Code
                          </th>
                          <th
                            scope="col"
                            className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                          >
                            Name
                          </th>
                          <th
                            scope="col"
                            className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                          >
                            Email
                          </th>
                        </tr>
                      </thead>
                      <tbody className="bg-white divide-y divide-gray-200">
                        {students.map((student) => (
                          <tr key={student.id}>
                            <td className="px-6 py-4 whitespace-nowrap">{student.code}</td>
                            <td className="px-6 py-4 whitespace-nowrap">
                              {student.first_name} {student.last_name}
                            </td>
                            <td className="px-6 py-4 whitespace-nowrap">{student.email}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
                <div className="p-4 border-t">
                  <div className="text-sm text-gray-500">
                    These students will receive an email notification when the exam details are updated.
                  </div>
                </div>
              </div>

              {/* Email Preview Card */}
              <div className="bg-white border rounded-lg shadow-sm md:col-span-3">
                <div className="p-4 border-b">
                  <div className="flex items-center">
                    <svg
                      className="mr-2 h-5 w-5"
                      xmlns="http://www.w3.org/2000/svg"
                      width="24"
                      height="24"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      strokeWidth="2"
                      strokeLinecap="round"
                      strokeLinejoin="round"
                    >
                      <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                      <polyline points="22,6 12,13 2,6"></polyline>
                    </svg>
                    <h3 className="text-lg font-medium">Email Preview</h3>
                  </div>
                  <p className="text-sm text-gray-500">
                    Sample of the email that will be sent when exam details change
                  </p>
                </div>
                <div className="p-4">
                  <div className="border rounded-md p-4 bg-gray-50">
                    <div className="mb-4">
                      <div className="text-sm text-gray-500">Subject:</div>
                      <div className="font-medium">Important: Exam Schedule Update for {exam.unit.code}</div>
                    </div>

                    <div className="space-y-4">
                      <p>Hello [Student Name],</p>

                      <p>
                        There has been an update to your exam schedule for {exam.unit.code} - {exam.unit.name}. Please
                        review the changes below:
                      </p>

                      <div className="border-l-4 border-gray-300 pl-4">
                        <p className="font-bold">Exam Details:</p>
                        <p>
                          <strong>Unit:</strong> {exam.unit.code} - {exam.unit.name}
                        </p>
                        <p>
                          <strong>Date:</strong> {formatDate(exam.date)} ({exam.day})
                        </p>
                        <p>
                          <strong>Time:</strong> {exam.start_time} - {exam.end_time}
                        </p>
                        <p>
                          <strong>Venue:</strong> {exam.venue} - {exam.location}
                        </p>
                      </div>

                      <div className="border-l-4 border-yellow-300 pl-4 bg-yellow-50 p-2">
                        <p className="font-bold">Changes Made:</p>
                        {Object.entries(changes).map(([field, values]) => (
                          <p key={field}>
                            <strong>{field.charAt(0).toUpperCase() + field.slice(1).replace("_", " ")}:</strong> Changed
                            from "{values.old}" to "{values.new}"
                          </p>
                        ))}
                        <p>
                        Please make note of these changes and adjust your schedule accordingly. If you have any
                        questions, please contact your admin.
                      </p>
                      </div>                      
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  )
}
