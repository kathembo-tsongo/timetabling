"use client"

import type React from "react"
import { Head, useForm } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { Calendar, Clock, Download, Filter } from "lucide-react"
import { format } from "date-fns"

interface Unit {
  id: number
  code: string
  name: string
}

interface ClassTimetable {
  id: number
  date: string
  day: string
  start_time: string
  end_time: string
  venue: string
  location: string
  no: number
  lecturer: string
  unit: Unit
  semester_id: number
  mode_of_teaching?: string
}

interface Semester {
  id: number
  name: string
  year?: number
  is_active?: boolean
}

interface Props {
    classTimetables: ClassTimetable[]
  semesters: Semester[]
  selectedSemesterId: number
}

export default function ClassTimetable({ classTimetables, semesters, selectedSemesterId }: Props) {
  const { data, setData, get, processing } = useForm({
    semester_id: selectedSemesterId,
  })

  const handleSemesterChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const newSemesterId = Number.parseInt(e.target.value)
    setData("semester_id", newSemesterId)
    get("/my-classes", {
      preserveState: true,
      preserveScroll: true,
    })
  }

  const handleDownload = () => {
    window.open(`/my-classes/download?semester_id=${data.semester_id}`, "_blank")
  }

  return (
    <AuthenticatedLayout>
      <Head title="My Class Timetable" />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div className="p-6 bg-white border-b border-gray-200">
              <div className="flex justify-between items-center mb-6">
                <h1 className="text-2xl font-semibold text-gray-900">My Class Timetable</h1>

                <div className="flex items-center space-x-4">
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

                  <button
                    onClick={handleDownload}
                    className="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                  >
                    <Download className="h-4 w-4 mr-1" />
                    Download PDF
                  </button>
                </div>
              </div>

              {classTimetables.length > 0 ? (
                <div className="overflow-x-auto">
                  <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                      <tr>
                        <th className="px-3 py-2">Day</th>
                        <th className="px-3 py-2">Unit Code</th>
                        <th className="px-3 py-2">Unit Name</th>
                        <th className="px-3 py-2">Semester</th>
                        <th className="px-3 py-2">Classroom</th>
                        <th className="px-3 py-2">Time</th>
                        <th className="px-3 py-2">Location</th>
                        <th className="px-3 py-2">Lecturer</th>
                       
                      </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                      {classTimetables.map((classes) => (
                        <tr key={classes.id} className="hover:bg-gray-50">
                          <td className="px-6 py-4 whitespace-nowrap">
                            <div className="text-sm text-gray-500">{classes.day}</div>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <div className="text-sm text-gray-900">{classes.unit.code}</div>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <div className="text-sm text-gray-900">{classes.unit.name}</div>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <div className="text-sm text-gray-900">
                              {semesters.find((semester) => semester.id === classes.semester_id)?.name || "N/A"}
                            </div>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <div className="text-sm text-gray-900">{classes.venue}</div>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <div className="text-sm text-gray-900">
                              {classes.start_time} - {classes.end_time}
                            </div>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <div className="text-sm text-gray-900">{classes.location}</div>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <div className="text-sm text-gray-900">{classes.lecturer}</div>
                          </td>                     
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (
                <div className="text-center py-8">
                  <div className="text-gray-500 mb-2">No class found for the selected semester.</div>
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

// https://www.youtube.com/watch?v=tyItKNCMmRQ&t=977s / for notifying the user about changes
