"use client"

import type React from "react"
import { Head, router } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { Calendar, Clock, Download, Filter } from "lucide-react"

interface ClassTimetable {
  id: number
  day: string
  start_time: string
  end_time: string
  venue: string
  location: string
  no: number
  lecturer: string
  lecturer_name: string
  unit_code: string
  unit_name: string
  semester_id: number
  teaching_mode?: string
}

interface Semester {
  id: number
  name: string
  year?: number
  is_active?: boolean
}

interface Props {
  classTimetables: {
    data: ClassTimetable[]
  }
  enrolledUnits: any[]
  currentSemester: Semester
  semesters: Semester[]
  selectedSemesterId: number
  studentInfo: {
    name: string
    code: string
  }
}

export default function StudentTimetable({ 
  classTimetables, 
  semesters, 
  selectedSemesterId,
  studentInfo,
  currentSemester 
}: Props) {

  const handleSemesterChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const newSemesterId = Number.parseInt(e.target.value)
    router.get('/student/timetable', { semester_id: newSemesterId }, {
      preserveState: true,
      preserveScroll: true,
    })
  }

  const handleDownload = () => {
    window.open(`/student/timetable/download?semester_id=${selectedSemesterId}`, "_blank")
  }

  const timetableData = classTimetables?.data || []

  return (
    <AuthenticatedLayout>
      <Head title="My Timetable" />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div className="p-6 bg-white border-b border-gray-200">
              <div className="flex justify-between items-center mb-6">
                <div>
                  <h1 className="text-2xl font-semibold text-gray-900">My Timetable</h1>
                  <p className="text-sm text-gray-600">
                    Student: {studentInfo?.name} ({studentInfo?.code})
                  </p>
                  <p className="text-xs text-gray-500">
                    {timetableData.length} classes found
                  </p>
                </div>

                <div className="flex items-center space-x-4">
                  <div className="flex items-center">
                    <Filter className="h-4 w-4 text-gray-500 mr-2" />
                    <select
                      value={selectedSemesterId}
                      onChange={handleSemesterChange}
                      className="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"
                    >
                      {semesters?.map((semester) => (
                        <option key={semester.id} value={semester.id}>
                          {semester.name} {semester.year || ""}
                        </option>
                      ))}
                    </select>
                  </div>

                  <button
                    onClick={handleDownload}
                    className="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700"
                  >
                    <Download className="h-4 w-4 mr-1" />
                    Download PDF
                  </button>
                </div>
              </div>

              {timetableData.length > 0 ? (
                <div className="overflow-x-auto">
                  <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                      <tr>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Day</th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Venue</th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mode</th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lecturer</th>
                      </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                      {timetableData.map((classItem) => (
                        <tr key={classItem.id} className="hover:bg-gray-50">
                          <td className="px-6 py-4 whitespace-nowrap">
                            <div className="text-sm font-medium text-gray-900">{classItem.day}</div>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <div className="text-sm text-gray-900">
                              {classItem.start_time} - {classItem.end_time}
                            </div>
                          </td>
                          <td className="px-6 py-4">
                            <div className="text-sm font-medium text-gray-900">{classItem.unit_code}</div>
                            <div className="text-sm text-gray-500">{classItem.unit_name}</div>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <div className="text-sm text-gray-900">{classItem.venue}</div>
                            <div className="text-sm text-gray-500">{classItem.location}</div>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${
                              classItem.teaching_mode === 'online' 
                                ? 'bg-blue-100 text-blue-800' 
                                : 'bg-green-100 text-green-800'
                            }`}>
                              {classItem.teaching_mode || 'Physical'}
                            </span>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <div className="text-sm text-gray-900">
                              {classItem.lecturer_name || classItem.lecturer}
                            </div>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (
                <div className="text-center py-8">
                  <Calendar className="mx-auto h-12 w-12 text-gray-400" />
                  <h3 className="mt-2 text-sm font-medium text-gray-900">No classes found</h3>
                  <p className="mt-1 text-sm text-gray-500">
                    No classes scheduled for the selected semester.
                  </p>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  )
}