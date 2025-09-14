"use client"

import React from "react"
import { Head, router } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { Calendar, Clock, Download, Filter, MapPin, User } from "lucide-react"

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
  class_name?: string
  class_section?: string
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
    class_name?: string
    section?: string
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
  
  // Group timetable by days and sort by time
  const dayOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']
  
  const groupedByDay = timetableData.reduce((acc, classItem) => {
    if (!acc[classItem.day]) {
      acc[classItem.day] = []
    }
    acc[classItem.day].push(classItem)
    return acc
  }, {} as Record<string, ClassTimetable[]>)

  // Sort classes within each day by start time
  Object.keys(groupedByDay).forEach(day => {
    groupedByDay[day].sort((a, b) => a.start_time.localeCompare(b.start_time))
  })

  // Get ordered days that have classes
  const orderedDaysWithClasses = dayOrder.filter(day => groupedByDay[day]?.length > 0)

  // Calculate total hours for each day
  const calculateDayHours = (classes: ClassTimetable[]) => {
    return classes.reduce((total, classItem) => {
      const start = new Date(`1970-01-01T${classItem.start_time}`)
      const end = new Date(`1970-01-01T${classItem.end_time}`)
      const hours = (end.getTime() - start.getTime()) / (1000 * 60 * 60)
      return total + hours
    }, 0)
  }

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
                  {studentInfo?.class_name && (
                    <p className="text-sm text-blue-600">
                      Class: {studentInfo.class_name} - Section {studentInfo.section}
                    </p>
                  )}
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

              {/* Summary Cards */}
              {timetableData.length > 0 && (
                <div className="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
                  <div className="bg-gray-50 p-4 rounded-lg text-center">
                    <div className="text-2xl font-bold text-gray-900">{orderedDaysWithClasses.length}</div>
                    <div className="text-sm text-gray-600">Days with Classes</div>
                  </div>
                  <div className="bg-blue-50 p-4 rounded-lg text-center">
                    <div className="text-2xl font-bold text-blue-600">{timetableData.length}</div>
                    <div className="text-sm text-gray-600">Total Sessions</div>
                  </div>
                  <div className="bg-green-50 p-4 rounded-lg text-center">
                    <div className="text-2xl font-bold text-green-600">
                      {timetableData.filter(c => c.teaching_mode !== 'online').length}
                    </div>
                    <div className="text-sm text-gray-600">Physical Classes</div>
                  </div>
                  <div className="bg-purple-50 p-4 rounded-lg text-center">
                    <div className="text-2xl font-bold text-purple-600">
                      {timetableData.filter(c => c.teaching_mode === 'online').length}
                    </div>
                    <div className="text-sm text-gray-600">Online Classes</div>
                  </div>
                  <div className="bg-orange-50 p-4 rounded-lg text-center">
                    <div className="text-2xl font-bold text-orange-600">
                      {Math.round(orderedDaysWithClasses.reduce((total, day) => 
                        total + calculateDayHours(groupedByDay[day]), 0))}h
                    </div>
                    <div className="text-sm text-gray-600">Total Hours/Week</div>
                  </div>
                </div>
              )}

              <div className="mb-4 text-sm text-green-700 bg-green-50 p-3 rounded-md">
                Days displayed in chronological order: {orderedDaysWithClasses.join(' → ')}
              </div>

              {timetableData.length > 0 ? (
                <div className="space-y-8">
                  {orderedDaysWithClasses.map((day) => {
                    const dayClasses = groupedByDay[day]
                    const dayHours = calculateDayHours(dayClasses)
                    
                    return (
                      <div key={day} className="border border-gray-200 rounded-lg overflow-hidden">
                        {/* Day Header */}
                        <div className="bg-gray-50 px-6 py-4 border-b border-gray-200">
                          <div className="flex items-center justify-between">
                            <div className="flex items-center">
                              <Calendar className="h-5 w-5 text-gray-500 mr-2" />
                              <h3 className="text-lg font-semibold text-gray-900">{day}</h3>
                              <span className="ml-3 text-sm text-gray-600">
                                {dayClasses.length} session{dayClasses.length !== 1 ? 's' : ''}
                              </span>
                            </div>
                            <div className="text-sm text-gray-600">
                              {dayHours.toFixed(1)} total hours
                            </div>
                          </div>
                        </div>

                        {/* Day Classes */}
                        <div className="divide-y divide-gray-100">
                          {dayClasses.map((classItem, index) => (
                            <div key={classItem.id} className="p-6 hover:bg-gray-50 transition-colors">
                              <div className="grid grid-cols-1 lg:grid-cols-8 gap-4">
                                {/* Time Column */}
                                <div className="lg:col-span-2">
                                  <div className="flex items-center text-sm font-medium text-gray-900">
                                    <Clock className="h-4 w-4 text-gray-400 mr-2" />
                                    {classItem.start_time} - {classItem.end_time}
                                  </div>
                                  <div className="text-xs text-gray-500 mt-1">
                                    {(() => {
                                      const start = new Date(`1970-01-01T${classItem.start_time}`)
                                      const end = new Date(`1970-01-01T${classItem.end_time}`)
                                      const duration = (end.getTime() - start.getTime()) / (1000 * 60 * 60)
                                      return `${duration}h duration`
                                    })()}
                                  </div>
                                </div>

                                {/* Unit Column */}
                                <div className="lg:col-span-2">
                                  <div className="text-sm font-medium text-gray-900">
                                    {classItem.unit_code}
                                  </div>
                                  <div className="text-sm text-gray-600 mt-1">
                                    {classItem.unit_name}
                                  </div>
                                </div>

                                {/* Class/Group Column */}
                                <div className="lg:col-span-1">
                                  <div className="text-sm text-gray-900">
                                    {classItem.class_name || studentInfo?.class_name || 'N/A'}
                                  </div>
                                  <div className="text-xs text-gray-500">
                                    Section {classItem.class_section || studentInfo?.section || 'N/A'}
                                  </div>
                                </div>

                                {/* Venue Column */}
                                <div className="lg:col-span-1">
                                  <div className="flex items-start">
                                    <MapPin className="h-4 w-4 text-gray-400 mr-1 mt-0.5 flex-shrink-0" />
                                    <div>
                                      <div className="text-sm text-gray-900">{classItem.venue}</div>
                                      {classItem.location && (
                                        <div className="text-xs text-gray-500">{classItem.location}</div>
                                      )}
                                    </div>
                                  </div>
                                </div>

                                {/* Mode Column */}
                                <div className="lg:col-span-1">
                                  <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${
                                    classItem.teaching_mode === 'online' 
                                      ? 'bg-blue-100 text-blue-800' 
                                      : 'bg-green-100 text-green-800'
                                  }`}>
                                    {classItem.teaching_mode || 'physical'}
                                  </span>
                                </div>

                                {/* Lecturer Column */}
                                <div className="lg:col-span-1">
                                  <div className="flex items-start">
                                    <User className="h-4 w-4 text-gray-400 mr-1 mt-0.5 flex-shrink-0" />
                                    <div className="text-sm text-gray-900">
                                      {classItem.lecturer_name || classItem.lecturer}
                                    </div>
                                  </div>
                                </div>
                              </div>
                            </div>
                          ))}
                        </div>
                      </div>
                    )
                  })}
                </div>
              ) : (
                <div className="text-center py-12">
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