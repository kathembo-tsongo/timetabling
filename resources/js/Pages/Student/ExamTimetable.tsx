"use client"

import type React from "react"
import { Head, useForm } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { Calendar, Clock, Download, Filter, AlertCircle } from "lucide-react"
import { format } from "date-fns"

interface Unit {
  id: number
  code: string
  name: string
}

interface ExamTimetable {
  id: number
  date: string
  day: string
  start_time: string
  end_time: string
  venue: string
  location: string
  no: number
  chief_invigilator: string
  unit: Unit
  semester_id: number
  unit_code?: string
  unit_name?: string
}

interface Semester {
  id: number
  name: string
  year?: number
  is_active?: boolean
}

interface Props {
  examTimetables: ExamTimetable[]
  semesters: Semester[]
  selectedSemesterId?: number
  message?: string
}

export default function ExamTimetable({ 
  examTimetables, 
  semesters, 
  selectedSemesterId,
  message 
}: Props) {
  const { data, setData, get, processing } = useForm({
    semester_id: selectedSemesterId || (semesters.length > 0 ? semesters[0].id : ''),
  })

  const handleSemesterChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const newSemesterId = Number.parseInt(e.target.value)
    setData("semester_id", newSemesterId)
    get("/my-exams", {
      preserveState: true,
      preserveScroll: true,
    })
  }

  const handleDownload = () => {
    // Use the correct route with semester_id parameter
    const downloadUrl = `/my-exams/download?semester_id=${data.semester_id}`
    
    // Create a temporary link element to trigger download
    const link = document.createElement('a')
    link.href = downloadUrl
    link.download = `exam-timetable-${new Date().toISOString().split('T')[0]}.pdf`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  }

  // Get unit information from exam data
  const getUnitInfo = (exam: ExamTimetable) => {
    const code = exam.unit_code || exam.unit?.code || 'N/A'
    const name = exam.unit_name || exam.unit?.name || 'Unknown Unit'
    return { code, name }
  }

  return (
    <AuthenticatedLayout>
      <Head title="My Exam Timetable" />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div className="p-6 bg-white border-b border-gray-200">
              <div className="flex justify-between items-center mb-6">
                <h1 className="text-2xl font-semibold text-gray-900">My Exam Timetable</h1>

                <div className="flex items-center space-x-4">
                  {semesters.length > 0 && (
                    <div className="flex items-center">
                      <Filter className="h-4 w-4 text-gray-500 mr-2" />
                      <select
                        value={data.semester_id}
                        onChange={handleSemesterChange}
                        className="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"
                        disabled={processing}
                      >
                        {semesters.map((semester) => (
                          <option key={semester.id} value={semester.id}>
                            {semester.name} {semester.year || ""}
                          </option>
                        ))}
                      </select>
                    </div>
                  )}

                  <button
                    onClick={handleDownload}
                    disabled={processing || examTimetables.length === 0}
                    className="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    <Download className="h-4 w-4 mr-1" />
                    Download PDF
                  </button>
                </div>
              </div>

              {message && (
                <div className="mb-6 p-4 bg-yellow-50 border-l-4 border-yellow-400">
                  <div className="flex">
                    <AlertCircle className="h-5 w-5 text-yellow-400" />
                    <div className="ml-3">
                      <p className="text-sm text-yellow-700">{message}</p>
                    </div>
                  </div>
                </div>
              )}

              {examTimetables.length > 0 ? (
                <div className="overflow-x-auto">
                  <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                      <tr>
                        <th
                          scope="col"
                          className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          Date
                        </th>
                        <th
                          scope="col"
                          className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          Unit
                        </th>
                        <th
                          scope="col"
                          className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          Time
                        </th>
                        <th
                          scope="col"
                          className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          Venue
                        </th>
                        <th
                          scope="col"
                          className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          Location
                        </th>
                        <th
                          scope="col"
                          className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                          Chief Invigilator
                        </th>
                      </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                      {examTimetables.map((exam) => {
                        const unitInfo = getUnitInfo(exam)
                        
                        return (
                          <tr key={exam.id} className="hover:bg-gray-50">
                            <td className="px-6 py-4 whitespace-nowrap">
                              <div className="flex items-center">
                                <Calendar className="h-4 w-4 text-gray-400 mr-2" />
                                <div>
                                  <div className="text-sm font-medium text-gray-900">
                                    {format(new Date(exam.date), "MMM d, yyyy")}
                                  </div>
                                  <div className="text-sm text-gray-500">{exam.day}</div>
                                </div>
                              </div>
                            </td>
                            <td className="px-6 py-4">
                              <div className="text-sm font-medium text-gray-900">{unitInfo.code}</div>
                              <div className="text-sm text-gray-500">{unitInfo.name}</div>
                            </td>
                            <td className="px-6 py-4 whitespace-nowrap">
                              <div className="flex items-center">
                                <Clock className="h-4 w-4 text-gray-400 mr-2" />
                                <div className="text-sm text-gray-900">
                                  {exam.start_time} - {exam.end_time}
                                </div>
                              </div>
                            </td>
                            <td className="px-6 py-4">
                              <div className="text-sm text-gray-900">{exam.venue || 'TBA'}</div>
                            </td>
                            <td className="px-6 py-4">
                              <div className="text-sm text-gray-500">{exam.location || 'TBA'}</div>
                            </td>
                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                              {exam.chief_invigilator || 'TBA'}
                            </td>
                          </tr>
                        )
                      })}
                    </tbody>
                  </table>
                </div>
              ) : (
                <div className="text-center py-8">
                  <div className="text-gray-500 mb-2">No exams found for the selected semester.</div>
                  <p className="text-sm text-gray-400">
                    Try selecting a different semester or contact your administrator if you believe this is an error.
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