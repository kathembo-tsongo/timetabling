"use client"

import type React from "react"
import { Head } from "@inertiajs/react"
import { useState, useEffect } from "react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { Calendar, Clock, BookOpen, Home, AlertTriangle, RefreshCw, Filter, X, MapPin, Users, GraduationCap, ChevronDown, Building } from 'lucide-react'

interface Unit {
  id: number
  code: string
  name: string
}

interface Semester {
  id: number
  name: string
}

interface ClassTimetable {
  id: number
  unit_id: number
  semester_id: number
  unit?: {
    id: number
    code: string
    name: string
  }
  semester?: {
    id: number
    name: string
  }
  day: string
  start_time: string
  end_time: string
  venue: string
  location: string
  no?: number
  lecturer?: string
  program_name?: string      
  class_name?: string        
  group_name?: string        
}

interface Props {
  classTimetables: ClassTimetable[]
  currentSemester: Semester | null
  selectedSemesterId: number | null
  selectedUnitId?: number | null
  assignedUnits: Unit[]
  lecturerSemesters: Semester[]
  showAllByDefault?: boolean
  error?: string
}

const ClassTimetable = ({
  classTimetables = [],
  currentSemester,
  selectedSemesterId,
  selectedUnitId,
  assignedUnits = [],
  lecturerSemesters = [],
  showAllByDefault = true,
  error,
}: Props) => {
  const [unitFilter, setUnitFilter] = useState<number | undefined>(selectedUnitId || undefined)
  const [semesterFilter, setSemesterFilter] = useState<number | undefined>(selectedSemesterId || undefined)
  const [isLoading, setIsLoading] = useState(false)
  const [retryCount, setRetryCount] = useState(0)

  // Handle semester filter changes
  const handleSemesterFilterChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const semesterId = e.target.value ? Number.parseInt(e.target.value) : undefined
    setSemesterFilter(semesterId)

    // Show loading indicator
    setIsLoading(true)

    // Redirect to the same page with the new filter
    let url = semesterId ? `/lecturer/class-timetable?semester_id=${semesterId}` : `/lecturer/class-timetable`

    // Add unit_id to the URL if it's selected
    if (unitFilter) {
      url += `&unit_id=${unitFilter}`
    }

    window.location.href = url
  }

  // Handle unit filter changes
  const handleUnitFilterChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const unitId = e.target.value ? Number.parseInt(e.target.value) : undefined
    setUnitFilter(unitId)

    // Show loading indicator
    setIsLoading(true)

    // Redirect to the same page with the new filter
    let url = unitId ? `/lecturer/class-timetable?unit_id=${unitId}` : `/lecturer/class-timetable`

    // Add semester_id to the URL if it's selected
    if (semesterFilter) {
      url += `&semester_id=${semesterFilter}`
    }

    window.location.href = url
  }

  // Function to retry loading timetable
  const handleRetry = () => {
    setIsLoading(true)
    setRetryCount((prev) => prev + 1)

    // Reload the page
    window.location.reload()
  }

  // Function to clear all filters
  const handleClearFilters = () => {
    setIsLoading(true)
    window.location.href = `/lecturer/class-timetable`
  }

  // Reset loading state after component mounts or updates
  useEffect(() => {
    setIsLoading(false)
  }, [classTimetables, error])

  // Group timetables by day for better display
  const timetablesByDay: Record<string, ClassTimetable[]> = {}
  classTimetables.forEach((timetable) => {
    if (!timetablesByDay[timetable.day]) {
      timetablesByDay[timetable.day] = []
    }
    timetablesByDay[timetable.day].push(timetable)
  })

  // Sort days in a logical order
  const sortedDays = Object.keys(timetablesByDay).sort((a, b) => {
    const days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"]
    return days.indexOf(a) - days.indexOf(b)
  })

  // Get day color
  const getDayColor = (day: string) => {
    const colors = {
      'Monday': 'from-red-400 to-red-600',
      'Tuesday': 'from-orange-400 to-orange-600',
      'Wednesday': 'from-yellow-400 to-yellow-600',
      'Thursday': 'from-green-400 to-green-600',
      'Friday': 'from-blue-400 to-blue-600',
      'Saturday': 'from-indigo-400 to-indigo-600',
      'Sunday': 'from-purple-400 to-purple-600'
    }
    return colors[day] || 'from-gray-400 to-gray-600'
  }

  // Calculate total classes
  const totalClasses = classTimetables.length

  return (
    <AuthenticatedLayout>
      <Head title="Class Timetable" />
      <div className="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50">
        <div className="max-w-7xl mx-auto p-6">
          {/* Header Section */}
          <div className="mb-8">
            <div className="bg-white/80 backdrop-blur-sm rounded-3xl p-8 border border-white/20 shadow-xl">
              <div className="flex items-center justify-between mb-4">
                <div className="flex items-center gap-4">
                  <div className="w-16 h-16 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center">
                    <Calendar className="w-8 h-8 text-white" />
                  </div>
                  <div>
                    <h1 className="text-4xl font-bold bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent">
                      Class Timetable
                    </h1>
                    <p className="text-gray-600 text-lg">
                      Viewing <span className="font-semibold text-indigo-600">{totalClasses}</span> scheduled classes
                    </p>
                  </div>
                </div>
                
                <a
                  href="/lecturer/dashboard"
                  className="group flex items-center gap-2 bg-gradient-to-r from-gray-100 to-gray-200 hover:from-gray-200 hover:to-gray-300 text-gray-700 px-6 py-3 rounded-xl font-medium transition-all duration-300 transform hover:scale-105 hover:shadow-lg"
                >
                  <Home className="w-4 h-4 group-hover:scale-110 transition-transform duration-300" />
                  Dashboard
                </a>
              </div>
              
              {error && (
                <div className="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-xl mb-4 flex justify-between items-center">
                  <div className="flex items-center gap-3">
                    <AlertTriangle className="h-5 w-5 flex-shrink-0" />
                    <span>{error}</span>
                  </div>
                  <button
                    onClick={handleRetry}
                    disabled={isLoading}
                    className="flex items-center gap-2 px-4 py-2 bg-red-100 hover:bg-red-200 text-red-800 rounded-lg text-sm font-medium transition-colors duration-200 disabled:opacity-50"
                  >
                    <RefreshCw className={`w-4 h-4 ${isLoading ? 'animate-spin' : ''}`} />
                    {isLoading ? "Retrying..." : "Retry"}
                  </button>
                </div>
              )}
            </div>
          </div>

          {/* Filters Section */}
          <div className="bg-white/80 backdrop-blur-sm rounded-2xl p-6 mb-6 border border-white/20 shadow-lg">
            <div className="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-6">
              <div className="flex flex-col md:flex-row gap-6">
                <div className="flex-1">
                  <label htmlFor="unit-filter" className="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                    <BookOpen className="w-4 h-4 text-indigo-500" />
                    Filter by Unit
                  </label>
                  <div className="relative">
                    <select
                      id="unit-filter"
                      value={unitFilter || ""}
                      onChange={handleUnitFilterChange}
                      className="appearance-none bg-white border border-gray-300 rounded-xl px-4 py-3 pr-10 min-w-[250px] focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all duration-300"
                      disabled={isLoading}
                    >
                      <option value="">All Units</option>
                      {assignedUnits.map((unit) => (
                        <option key={unit.id} value={unit.id}>
                          {unit.code} - {unit.name}
                        </option>
                      ))}
                    </select>
                    <ChevronDown className="absolute right-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
                  </div>
                </div>
                
                <div className="flex-1">
                  <label htmlFor="semester-filter" className="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                    <Calendar className="w-4 h-4 text-green-500" />
                    Filter by Semester
                  </label>
                  <div className="relative">
                    <select
                      id="semester-filter"
                      value={semesterFilter || ""}
                      onChange={handleSemesterFilterChange}
                      className="appearance-none bg-white border border-gray-300 rounded-xl px-4 py-3 pr-10 min-w-[250px] focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all duration-300"
                      disabled={isLoading}
                    >
                      <option value="">All Semesters</option>
                      {lecturerSemesters?.map((semester) => (
                        <option key={semester.id} value={semester.id}>
                          {semester.name}
                        </option>
                      ))}
                    </select>
                    <ChevronDown className="absolute right-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
                  </div>
                </div>

                {(unitFilter || semesterFilter) && (
                  <div className="flex items-end">
                    <button
                      onClick={handleClearFilters}
                      className="flex items-center gap-2 px-4 py-3 bg-gradient-to-r from-red-100 to-red-200 hover:from-red-200 hover:to-red-300 text-red-700 rounded-xl text-sm font-medium transition-all duration-300 transform hover:scale-105"
                      disabled={isLoading}
                    >
                      <X className="w-4 h-4" />
                      Clear Filters
                    </button>
                  </div>
                )}
              </div>

              <div className="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-xl border border-blue-100">
                <p className="text-blue-800 text-sm font-medium">
                  <strong>Selected Semester:</strong>{" "}
                  {currentSemester?.name || (semesterFilter ? "Loading..." : "All Semesters")}
                </p>
              </div>
            </div>
          </div>

          {/* Timetable Content */}
          {classTimetables.length > 0 ? (
            <div className="space-y-6">
              {sortedDays.map((day) => (
                <div key={day} className="bg-white/80 backdrop-blur-sm rounded-2xl border border-white/20 shadow-lg overflow-hidden">
                  <div className={`bg-gradient-to-r ${getDayColor(day)} px-6 py-4`}>
                    <h3 className="text-xl font-bold text-white flex items-center gap-3">
                      <Calendar className="w-6 h-6" />
                      {day}
                      <span className="text-sm font-normal opacity-90">
                        ({timetablesByDay[day].length} classes)
                      </span>
                    </h3>
                  </div>
                  
                  <div className="p-6">
                    <div className="space-y-4">
                      {timetablesByDay[day]
                        .sort((a, b) => a.start_time.localeCompare(b.start_time))
                        .map((timetable) => (
                          <div
                            key={timetable.id}
                            className="group bg-gradient-to-r from-white to-gray-50 rounded-xl p-6 border border-gray-200 hover:border-indigo-300 hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1"
                          >
                            <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
                              {/* Time & Unit Section */}
                              <div className="lg:col-span-1">
                                <div className="flex items-center gap-3 mb-3">
                                  <Clock className="w-5 h-5 text-indigo-500" />
                                  <span className="text-xl font-bold text-gray-800">
                                    {timetable.start_time} - {timetable.end_time}
                                  </span>
                                </div>
                                <div className="flex items-center gap-2">
                                  <BookOpen className="w-4 h-4 text-blue-500" />
                                  <span className="font-semibold text-gray-700">
                                    {timetable.unit?.name || "Unknown Unit"}
                                  </span>
                                </div>
                              </div>

                              {/* Location & Details */}
                              <div className="lg:col-span-1">
                                <div className="flex items-center gap-2 mb-2">
                                  <MapPin className="w-4 h-4 text-red-500" />
                                  <span className="font-medium text-gray-700">{timetable.venue}</span>
                                </div>
                                {timetable.location && (
                                  <div className="flex items-center gap-2 mb-2">
                                    <Building className="w-4 h-4 text-gray-500" />
                                    <span className="text-sm text-gray-600">{timetable.location}</span>
                                  </div>
                                )}
                                <div className="flex items-center gap-2">
                                  <Users className="w-4 h-4 text-green-500" />
                                  <span className="text-sm text-gray-600">{timetable.no || 0} students</span>
                                </div>
                              </div>

                              {/* Program & Class Info */}
                              <div className="lg:col-span-1">
                                <div className="space-y-2">
                                  <div className="text-sm">
                                    <span className="text-gray-500">Semester:</span>
                                    <span className="ml-2 font-medium text-gray-700">
                                      {timetable.semester?.name || "Unknown"}
                                    </span>
                                  </div>
                                  <div className="text-sm">
                                    <span className="text-gray-500">Program:</span>
                                    <span className="ml-2 font-medium text-gray-700">
                                      {timetable.program_name || "No Program"}
                                    </span>
                                  </div>
                                </div>
                              </div>

                              {/* Class & Group Info */}
                              <div className="lg:col-span-1">
                                <div className="space-y-2">
                                  <div className="text-sm">
                                    <span className="text-gray-500">Class:</span>
                                    <span className="ml-2 font-medium text-gray-700">
                                      {timetable.class_name || "No Class"}
                                    </span>
                                  </div>
                                  <div className="text-sm">
                                    <span className="text-gray-500">Group:</span>
                                    <span className="ml-2 font-medium text-gray-700">
                                      {timetable.group_name || "No Group"}
                                    </span>
                                  </div>
                                </div>
                              </div>
                            </div>
                          </div>
                        ))}
                    </div>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="text-center py-16">
              <div className="bg-white/80 backdrop-blur-sm rounded-3xl p-12 border border-white/20 shadow-lg max-w-2xl mx-auto">
                <div className="w-24 h-24 bg-gradient-to-r from-yellow-400 to-orange-500 rounded-full flex items-center justify-center mx-auto mb-6">
                  <GraduationCap className="w-12 h-12 text-white" />
                </div>
                <h3 className="text-2xl font-bold text-gray-800 mb-4">No Classes Found</h3>
                <p className="text-gray-600 text-lg mb-6">
                  No class timetable entries found for the selected criteria. Please check your schedule with the academic office.
                </p>
                <div className="flex flex-col sm:flex-row gap-4 justify-center">
                  <button
                    onClick={handleClearFilters}
                    className="inline-flex items-center gap-2 bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 text-white px-6 py-3 rounded-xl font-medium transition-all duration-300 transform hover:scale-105"
                    disabled={isLoading}
                  >
                    <Filter className="w-4 h-4" />
                    Clear All Filters
                  </button>
                  <a
                    href="/lecturer/dashboard"
                    className="inline-flex items-center gap-2 bg-gradient-to-r from-gray-200 to-gray-300 hover:from-gray-300 hover:to-gray-400 text-gray-700 px-6 py-3 rounded-xl font-medium transition-all duration-300 transform hover:scale-105"
                  >
                    <Home className="w-4 h-4" />
                    Return to Dashboard
                  </a>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
    </AuthenticatedLayout>
  )
}

export default ClassTimetable