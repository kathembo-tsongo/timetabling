"use client"

import { Head } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { useState, useEffect } from "react"
import { Users, BookOpen, ArrowLeft, Home, AlertTriangle, RefreshCw, Mail, CreditCard, School, Calendar, User, GraduationCap } from 'lucide-react'

interface Unit {
  id: number
  code: string
  name: string
 
}

interface Student {
  id: number | null 
  email: string
  code: string
}

interface Enrollment {
  id: number
  student_id: number | null
  unit_id: number
  semester_id: number
  student: Student
}

interface Semester {
  id: number
  name: string
}

interface Props {
  unit: Unit | null
  students: Enrollment[]
  unitSemester: Semester | null
  selectedSemesterId: number
  studentCount?: number
  error?: string
}

const ClassStudents = ({ unit, students = [], unitSemester, selectedSemesterId, studentCount = 0, error }: Props) => {
  const [isLoading, setIsLoading] = useState(false)
  const [retryCount, setRetryCount] = useState(0)

  // If we have a count but no students, we can show a more specific message
  const hasCountMismatch = studentCount > 0 && students.length === 0

  // Function to retry loading students
  const handleRetry = () => {
    setIsLoading(true)
    setRetryCount((prev) => prev + 1)

    // Reload the page
    window.location.reload()
  }

  // Reset loading state after component mounts or updates
  useEffect(() => {
    setIsLoading(false)
  }, [students, error])

  return (
    <AuthenticatedLayout>
      <Head title={unit ? `Students - ${unit.code}` : "Students"} />
      <div className="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50">
        <div className="max-w-7xl mx-auto p-6">
          {/* Header Section */}
          <div className="mb-8">
            <div className="bg-white/80 backdrop-blur-sm rounded-3xl p-8 border border-white/20 shadow-xl">
              <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                <div className="flex items-center gap-4">
                  <div className="w-16 h-16 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center">
                    <Users className="w-8 h-8 text-white" />
                  </div>
                  <div>
                    <h1 className="text-3xl lg:text-4xl font-bold bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent">
                      {unit ? `Students in ${unit.code}` : "Students"}
                    </h1>
                    <p className="text-gray-600 text-lg mt-1">
                      {unit ? unit.name : "Student Management"}
                    </p>
                  </div>
                </div>
                
                <div className="flex flex-wrap gap-3">
                  <a
                    href={`/lecturer/my-classes?semester_id=${selectedSemesterId}`}
                    className="group flex items-center gap-2 bg-gradient-to-r from-gray-100 to-gray-200 hover:from-gray-200 hover:to-gray-300 text-gray-700 px-6 py-3 rounded-xl font-medium transition-all duration-300 transform hover:scale-105 hover:shadow-lg"
                  >
                    <ArrowLeft className="w-4 h-4 group-hover:-translate-x-1 transition-transform duration-300" />
                    Back to Classes
                  </a>
                  <a
                    href="/lecturer/dashboard"
                    className="group flex items-center gap-2 bg-gradient-to-r from-indigo-100 to-purple-100 hover:from-indigo-200 hover:to-purple-200 text-indigo-700 px-6 py-3 rounded-xl font-medium transition-all duration-300 transform hover:scale-105 hover:shadow-lg"
                  >
                    <Home className="w-4 h-4 group-hover:scale-110 transition-transform duration-300" />
                    Dashboard
                  </a>
                </div>
              </div>
              
              {error && (
                <div className="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-xl mt-6 flex justify-between items-center">
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

          {/* Unit Information Card */}
          {unit && (
            <div className="bg-white/80 backdrop-blur-sm rounded-2xl p-6 mb-6 border border-white/20 shadow-lg">
              <div className="flex items-center gap-3 mb-6">
                <BookOpen className="w-6 h-6 text-indigo-500" />
                <h2 className="text-xl font-semibold text-gray-800">Unit Information</h2>
              </div>
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div className="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-4 border border-blue-100">
                  <div className="flex items-center gap-2 mb-2">
                    <CreditCard className="w-4 h-4 text-blue-500" />
                    <p className="text-sm font-medium text-gray-600">Unit Code</p>
                  </div>
                  <p className="font-bold text-lg text-blue-600">{unit.code}</p>
                </div>
                <div className="bg-gradient-to-r from-green-50 to-emerald-50 rounded-xl p-4 border border-green-100">
                  <div className="flex items-center gap-2 mb-2">
                    <BookOpen className="w-4 h-4 text-green-500" />
                    <p className="text-sm font-medium text-gray-600">Unit Name</p>
                  </div>
                  <p className="font-bold text-lg text-green-600">{unit.name}</p>
                </div>
                
                <div className="bg-gradient-to-r from-orange-50 to-red-50 rounded-xl p-4 border border-orange-100">
                  <div className="flex items-center gap-2 mb-2">
                    <Calendar className="w-4 h-4 text-orange-500" />
                    <p className="text-sm font-medium text-gray-600">Semester</p>
                  </div>
                  <p className="font-bold text-lg text-orange-600">{unitSemester?.name || "N/A"}</p>
                </div>
              </div>
            </div>
          )}

          {/* Students Section */}
          <div className="bg-white/80 backdrop-blur-sm rounded-2xl border border-white/20 shadow-lg overflow-hidden">
            <div className="p-6 border-b border-gray-100">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <Users className="w-6 h-6 text-green-500" />
                  <h2 className="text-xl font-semibold text-gray-800">
                    Enrolled Students ({students.length})
                    {hasCountMismatch && (
                      <span className="text-sm text-yellow-600 ml-2 font-normal">
                        (Expected: {studentCount})
                      </span>
                    )}
                  </h2>
                </div>
                <div className="bg-gradient-to-r from-green-100 to-emerald-100 px-4 py-2 rounded-xl border border-green-200">
                  <span className="text-green-700 font-medium text-sm">
                    {students.length} Total Students
                  </span>
                </div>
              </div>

              {hasCountMismatch && (
                <div className="bg-yellow-50 border border-yellow-200 text-yellow-700 px-6 py-4 rounded-xl mt-4 flex justify-between items-start">
                  <div className="flex items-start gap-3">
                    <AlertTriangle className="w-5 h-5 text-yellow-600 flex-shrink-0 mt-0.5" />
                    <div>
                      <p className="font-medium">
                        Data Synchronization Issue Detected
                      </p>
                      <p className="text-sm mt-1">
                        Expected {studentCount} students but loaded {students.length}. This may be due to a data synchronization issue.
                      </p>
                    </div>
                  </div>
                  <button
                    onClick={handleRetry}
                    disabled={isLoading}
                    className="flex items-center gap-2 px-4 py-2 bg-yellow-100 hover:bg-yellow-200 text-yellow-800 rounded-lg text-sm font-medium transition-colors duration-200 whitespace-nowrap ml-4 disabled:opacity-50"
                  >
                    <RefreshCw className={`w-4 h-4 ${isLoading ? 'animate-spin' : ''}`} />
                    {isLoading ? "Retrying..." : "Retry Loading"}
                  </button>
                </div>
              )}
            </div>

            {students.length > 0 ? (
              <div className="overflow-x-auto">
                <div className="grid gap-4 p-6">
                  {students.map((enrollment, index) => (
                    <div
                      key={enrollment.id}
                      className="group bg-gradient-to-r from-white to-gray-50 rounded-xl p-6 border border-gray-200 hover:border-indigo-300 hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1"
                    >
                      <div className="flex items-center gap-6">
                        {/* Student Avatar */}
                        <div className="w-12 h-12 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-full flex items-center justify-center flex-shrink-0">
                          <User className="w-6 h-6 text-white" />
                        </div>

                        {/* Student Info */}
                        <div className="flex-1 grid grid-cols-1 md:grid-cols-2 gap-4">
                          <div className="flex items-center gap-3">
                            <CreditCard className="w-5 h-5 text-blue-500" />
                            <div>
                              <p className="text-sm font-medium text-gray-600">Student ID</p>
                              <p className="font-bold text-lg text-gray-900">
                                {enrollment.student?.code || "N/A"}
                              </p>
                            </div>
                          </div>
                          
                          <div className="flex items-center gap-3">
                            <Mail className="w-5 h-5 text-green-500" />
                            <div>
                              <p className="text-sm font-medium text-gray-600">Email Address</p>
                              <p className="font-medium text-gray-900">
                                {enrollment.student?.email || "N/A"}
                              </p>
                            </div>
                          </div>
                        </div>

                        {/* Student Number Badge */}
                        <div className="hidden sm:flex items-center justify-center w-10 h-10 bg-gradient-to-r from-indigo-100 to-purple-100 rounded-full">
                          <span className="text-indigo-600 font-bold text-sm">#{index + 1}</span>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            ) : (
              <div className="text-center py-16 px-6">
                <div className="max-w-md mx-auto">
                  <div className="w-24 h-24 bg-gradient-to-r from-yellow-400 to-orange-500 rounded-full flex items-center justify-center mx-auto mb-6">
                    <GraduationCap className="w-12 h-12 text-white" />
                  </div>
                  <h3 className="text-2xl font-bold text-gray-800 mb-4">No Students Enrolled</h3>
                  <p className="text-gray-600 text-lg mb-6">
                    {hasCountMismatch
                      ? "There should be students enrolled in this unit, but they couldn't be loaded. Please try again or contact support."
                      : "No students are currently enrolled in this unit."}
                  </p>
                  {hasCountMismatch && (
                    <button
                      onClick={handleRetry}
                      disabled={isLoading}
                      className="inline-flex items-center gap-2 bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 text-white px-6 py-3 rounded-xl font-medium transition-all duration-300 transform hover:scale-105 disabled:opacity-50"
                    >
                      <RefreshCw className={`w-4 h-4 ${isLoading ? 'animate-spin' : ''}`} />
                      {isLoading ? "Retrying..." : "Retry Loading"}
                    </button>
                  )}
                </div>
              </div>
            )}
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  )
}

export default ClassStudents