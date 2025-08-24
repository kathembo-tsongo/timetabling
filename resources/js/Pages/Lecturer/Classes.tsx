"use client"

import type React from "react"
import { Head } from "@inertiajs/react"
import { useState } from "react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { Users, BookOpen, ArrowLeft, Eye, Calendar, School, AlertTriangle, ChevronDown, GraduationCap } from 'lucide-react'

interface Unit {
  id: number
  code: string
  name: string
 
}

interface Semester {
  id: number
  name: string
  year?: number
  is_active?: boolean
}

interface Props {
  units: Unit[]
  currentSemester: Semester
  semesters: Semester[]
  selectedSemesterId: number
  lecturerSemesters: number[]
  studentCounts: Record<string, number>
  error?: string
}

const Classes = ({
  units = [],
  currentSemester,
  semesters = [],
  selectedSemesterId,
  lecturerSemesters = [],
  studentCounts = {},
  error,
}: Props) => {
  const [selectedSemester, setSelectedSemester] = useState<number>(selectedSemesterId || 0)

  // Handle semester change
  const handleSemesterChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const semesterId = Number.parseInt(e.target.value)
    setSelectedSemester(semesterId)

    // Add a loading indicator or message
    const loadingMessage = document.createElement("div")
    loadingMessage.className = "fixed top-0 left-0 w-full bg-blue-500 text-white text-center py-2 z-50"
    loadingMessage.textContent = "Loading classes..."
    document.body.appendChild(loadingMessage)

    // Redirect to the same page with the new filter
    window.location.href = `/lecturer/my-classes?semester_id=${semesterId}`
  }

  // Filter available semesters to only those the lecturer is assigned to
  const availableSemesters = semesters.filter((semester) => lecturerSemesters.includes(semester.id))

  // Calculate total students
  const totalStudents = Object.values(studentCounts).reduce((sum, count) => sum + count, 0)

  return (
    <AuthenticatedLayout>
      <Head title="My Classes" />
      <div className="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50">
        <div className="max-w-7xl mx-auto p-6">
          {/* Header Section */}
          <div className="mb-8">
            <div className="bg-white/80 backdrop-blur-sm rounded-3xl p-8 border border-white/20 shadow-xl">
              <div className="flex items-center justify-between mb-4">
                <div className="flex items-center gap-4">
                  <div className="w-12 h-12 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center">
                    <BookOpen className="w-6 h-6 text-white" />
                  </div>
                  <div>
                    <h1 className="text-4xl font-bold bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent">
                      My Classes
                    </h1>
                    <p className="text-gray-600 text-lg">
                      Managing <span className="font-semibold text-indigo-600">{units.length}</span> units with <span className="font-semibold text-indigo-600">{totalStudents}</span> students
                    </p>
                  </div>
                </div>
                
                <a
                  href="/lecturer/dashboard"
                  className="group flex items-center gap-2 bg-gradient-to-r from-gray-100 to-gray-200 hover:from-gray-200 hover:to-gray-300 text-gray-700 px-6 py-3 rounded-xl font-medium transition-all duration-300 transform hover:scale-105 hover:shadow-lg"
                >
                  <ArrowLeft className="w-4 h-4 group-hover:-translate-x-1 transition-transform duration-300" />
                  Back to Dashboard
                </a>
              </div>
              
              {error && (
                <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-4 flex items-center gap-2">
                  <AlertTriangle className="h-5 w-5" />
                  <span className="text-sm font-medium">{error}</span>
                </div>
              )}
            </div>
          </div>

          {/* Controls Section */}
          <div className="bg-white/80 backdrop-blur-sm rounded-2xl p-6 mb-6 border border-white/20 shadow-lg">
            <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
              <div>
                <label htmlFor="semester" className="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                  <Calendar className="w-4 h-4 text-indigo-500" />
                  Select Semester
                </label>
                <div className="relative">
                  <select
                    id="semester"
                    value={selectedSemester}
                    onChange={handleSemesterChange}
                    className="appearance-none bg-white border border-gray-300 rounded-xl px-4 py-3 pr-10 min-w-[250px] focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all duration-300"
                  >
                    {availableSemesters.length > 0 ? (
                      availableSemesters.map((semester) => (
                        <option key={semester.id} value={semester.id}>
                          {semester.name} {semester.is_active ? "(Current)" : ""}
                        </option>
                      ))
                    ) : (
                      <option value="">No semesters available</option>
                    )}
                  </select>
                  <ChevronDown className="absolute right-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
                </div>
              </div>

              {/* Stats Summary */}
              <div className="flex gap-4">
                <div className="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-4 border border-blue-100">
                  <div className="flex items-center gap-2 mb-1">
                    <BookOpen className="w-4 h-4 text-blue-500" />
                    <span className="text-sm font-medium text-gray-600">Units</span>
                  </div>
                  <span className="text-2xl font-bold text-blue-600">{units.length}</span>
                </div>
                <div className="bg-gradient-to-r from-green-50 to-emerald-50 rounded-xl p-4 border border-green-100">
                  <div className="flex items-center gap-2 mb-1">
                    <Users className="w-4 h-4 text-green-500" />
                    <span className="text-sm font-medium text-gray-600">Students</span>
                  </div>
                  <span className="text-2xl font-bold text-green-600">{totalStudents}</span>
                </div>
              </div>
            </div>
          </div>

          {/* Classes Grid */}
          {units.length > 0 ? (
            <div className="grid gap-6">
              {units.map((unit) => (
                <div
                  key={unit.id}
                  className="group bg-white/80 backdrop-blur-sm rounded-2xl p-6 border border-white/20 shadow-lg hover:shadow-2xl transition-all duration-500 transform hover:-translate-y-2"
                >
                  <div className="flex flex-col lg:flex-row gap-6">
                    {/* Unit Info Section */}
                    <div className="flex-1">
                      <div className="flex items-start gap-4">
                        <div className="w-16 h-16 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-2xl flex items-center justify-center flex-shrink-0">
                          <span className="text-white font-bold text-lg">{unit.code.substring(0, 3)}</span>
                        </div>
                        <div className="flex-1">
                          <div className="flex items-center gap-3 mb-2">
                            <h3 className="text-xl font-bold text-gray-800">{unit.code}</h3>
                            <span className="text-xs bg-indigo-100 text-indigo-800 px-2 py-1 rounded-full font-medium">
                              Unit Code
                            </span>
                          </div>
                          <p className="text-gray-600 font-medium text-lg mb-3">{unit.name}</p>
                          
                          <div className="flex flex-wrap gap-4 text-sm">                            
                            <div className="flex items-center gap-2">
                              <Users className="w-4 h-4 text-green-500" />
                              <span className="text-gray-600">
                                <span className="font-medium">Students:</span> {studentCounts[unit.id] || 0}
                              </span>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>

                    {/* Actions Section */}
                    <div className="flex lg:flex-col gap-3 lg:w-48">
                      <a
                        href={`/lecturer/my-classes/${unit.id}/students?semester_id=${selectedSemester}`}
                        className="group/btn flex items-center justify-center gap-2 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white px-4 py-3 rounded-xl font-medium transition-all duration-300 transform hover:scale-105 hover:shadow-lg flex-1 lg:flex-none"
                      >
                        <Eye className="w-4 h-4 group-hover/btn:scale-110 transition-transform duration-300" />
                        <span>View Students</span>
                      </a>
                      
                      <a
                        href={`/lecturer/class-timetable?unit_id=${unit.id}&semester_id=${selectedSemester}`}
                        className="group/btn flex items-center justify-center gap-2 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white px-4 py-3 rounded-xl font-medium transition-all duration-300 transform hover:scale-105 hover:shadow-lg flex-1 lg:flex-none"
                      >
                        <Calendar className="w-4 h-4 group-hover/btn:scale-110 transition-transform duration-300" />
                        <span>View Timetable</span>
                      </a>
                    </div>
                  </div>

                  {/* Student Count Bar */}
                  <div className="mt-4 pt-4 border-t border-gray-100">
                    <div className="flex items-center justify-between text-sm text-gray-500 mb-2">
                      <span>Enrollment Status</span>
                      <span>{studentCounts[unit.id] || 0} students enrolled</span>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-2">
                      <div 
                        className="bg-gradient-to-r from-green-400 to-green-600 h-2 rounded-full transition-all duration-500"
                        style={{ 
                          width: `${Math.min(((studentCounts[unit.id] || 0) / 60) * 100, 100)}%` 
                        }}
                      ></div>
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
                <h3 className="text-2xl font-bold text-gray-800 mb-4">No Classes Assigned</h3>
                <p className="text-gray-600 text-lg mb-6">
                  You don't have any assigned units for this semester. Please contact your administrator if you believe this is an error.
                </p>
                <div className="flex flex-col sm:flex-row gap-4 justify-center">
                  <a
                    href="/lecturer/dashboard"
                    className="inline-flex items-center gap-2 bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 text-white px-6 py-3 rounded-xl font-medium transition-all duration-300 transform hover:scale-105"
                  >
                    <ArrowLeft className="w-4 h-4" />
                    Return to Dashboard
                  </a>
                  <button className="inline-flex items-center gap-2 bg-gradient-to-r from-gray-200 to-gray-300 hover:from-gray-300 hover:to-gray-400 text-gray-700 px-6 py-3 rounded-xl font-medium transition-all duration-300 transform hover:scale-105">
                    <Users className="w-4 h-4" />
                    Contact Administrator
                  </button>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
    </AuthenticatedLayout>
  )
}

export default Classes