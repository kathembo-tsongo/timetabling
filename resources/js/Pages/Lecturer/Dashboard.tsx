"use client"

import React, { useState, useEffect } from "react"
import { Users, Calendar, FileText, BookOpen, Clock, School, TrendingUp, Award, ChevronRight, Bell, Star } from 'lucide-react'
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"

interface Unit {
  id: number
  code: string
  name: string
  school?: { name: string }
}

interface Semester {
  id: number
  name: string
  year?: number
}

interface SemesterUnits {
  semester: Semester
  units: Unit[]
}

interface Props {
  currentSemester: Semester
  lecturerSemesters: Semester[]
  unitsBySemester: Record<string, SemesterUnits>
  studentCounts: Record<string, Record<string, number>>
  error?: string
}

const Dashboard = ({ currentSemester, lecturerSemesters, unitsBySemester, studentCounts, error }: Props) => {
  // Mock data for demonstration
  const mockCurrentSemester = currentSemester || { id: 1, name: "Fall 2024", year: 2024 }
  const mockStats = {
    totalStudents: 156,
    activeClasses: 4,
    upcomingExams: 2,
    completionRate: 87
  }

  const semesterData = unitsBySemester || {}
  const studentCountsData = studentCounts || {}
  const [expandedSemesters, setExpandedSemesters] = useState<Record<number, boolean>>({})

  useEffect(() => {
    if (mockCurrentSemester && Object.keys(semesterData).length > 0) {
      const initialState: Record<number, boolean> = {}
      Object.values(semesterData).forEach((data) => {
        if (data && data.semester) {
          initialState[data.semester.id] = data.semester.id === mockCurrentSemester.id
        }
      })
      setExpandedSemesters(initialState)
    }
  }, [mockCurrentSemester, semesterData])

  const toggleSemester = (semesterId: number) => {
    setExpandedSemesters((prev) => ({
      ...prev,
      [semesterId]: !prev[semesterId],
    }))
  }

 return (
    <AuthenticatedLayout>
    <div className="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50">
      <div className="max-w-7xl mx-auto p-6">
        {/* Header Section */}
        <div className="mb-8">
          <div className="bg-white/80 backdrop-blur-sm rounded-3xl p-8 border border-white/20 shadow-xl">
            <div className="flex items-center justify-between mb-4">
              <div className="flex items-center gap-4">
                <div className="w-16 h-16 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center">
                  <BookOpen className="w-8 h-8 text-white" />
                </div>
                <div>
                  <h1 className="text-4xl font-bold bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent">
                    Welcome Back, Lecturer!
                  </h1>
                  <p className="text-gray-600 text-lg mt-1">
                    Current Semester: <span className="font-semibold text-indigo-600">{mockCurrentSemester.name}</span>
                  </p>
                </div>
              </div>
              <div className="flex items-center gap-3">
                <div className="relative">
                  <Bell className="w-6 h-6 text-gray-500 hover:text-indigo-600 cursor-pointer transition-colors duration-200" />
                  <div className="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full"></div>
                </div>
                <div className="w-12 h-12 bg-gradient-to-r from-purple-400 to-pink-400 rounded-full flex items-center justify-center">
                  <span className="text-white font-semibold">JP</span>
                </div>
              </div>
            </div>
            
            {error && (
              <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-4 flex items-center gap-2">
                <div className="w-5 h-5 rounded-full bg-red-200 flex items-center justify-center">
                  <span className="text-red-600 text-xs">!</span>
                </div>
                {error}
              </div>
            )}
          </div>
        </div>   

      
        <div className="bg-white/80 backdrop-blur-sm rounded-3xl p-8 border border-white/20 shadow-xl mb-8">       
          
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <a 
              href="/my-classes" 
              className="group block p-6 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-2xl border border-blue-100 hover:border-blue-200 transition-all duration-300 transform hover:-translate-y-1 hover:shadow-lg"
            >
              <div className="flex items-start gap-4">
                <div className="w-12 h-12 bg-gradient-to-r from-blue-400 to-blue-600 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                  <Users className="w-6 h-6 text-white" />
                </div>
                <div className="flex-1">
                  <h3 className="font-semibold text-gray-800 mb-2 group-hover:text-blue-600 transition-colors duration-200">
                    My Classes
                  </h3>
                  <p className="text-sm text-gray-600 mb-3">
                    View and manage your classes, track student progress, and update course materials.
                  </p>
                  <div className="flex items-center gap-2 text-blue-600 font-medium text-sm">
                    <span>Manage Classes</span>
                    <ChevronRight className="w-4 h-4 group-hover:translate-x-1 transition-transform duration-200" />
                  </div>
                </div>
              </div>
            </a>

            <a 
              href="/my-timetables" 
              className="group block p-6 bg-gradient-to-br from-green-50 to-emerald-50 rounded-2xl border border-green-100 hover:border-green-200 transition-all duration-300 transform hover:-translate-y-1 hover:shadow-lg"
            >
              <div className="flex items-start gap-4">
                <div className="w-12 h-12 bg-gradient-to-r from-green-400 to-green-600 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                  <Calendar className="w-6 h-6 text-white" />
                </div>
                <div className="flex-1">
                  <h3 className="font-semibold text-gray-800 mb-2 group-hover:text-green-600 transition-colors duration-200">
                    Class Timetable
                  </h3>
                  <p className="text-sm text-gray-600 mb-3">
                    View your teaching schedule, check upcoming classes, and manage your time effectively.
                  </p>
                  <div className="flex items-center gap-2 text-green-600 font-medium text-sm">
                    <span>View Schedule</span>
                    <ChevronRight className="w-4 h-4 group-hover:translate-x-1 transition-transform duration-200" />
                  </div>
                </div>
              </div>
            </a>

            <a 
              href="/examsupervision" 
              className="group block p-6 bg-gradient-to-br from-purple-50 to-pink-50 rounded-2xl border border-purple-100 hover:border-purple-200 transition-all duration-300 transform hover:-translate-y-1 hover:shadow-lg"
            >
              <div className="flex items-start gap-4">
                <div className="w-12 h-12 bg-gradient-to-r from-purple-400 to-purple-600 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                  <FileText className="w-6 h-6 text-white" />
                </div>
                <div className="flex-1">
                  <h3 className="font-semibold text-gray-800 mb-2 group-hover:text-purple-600 transition-colors duration-200">
                    Exam Supervision
                  </h3>
                  <p className="text-sm text-gray-600 mb-3">
                    View your exam supervision duties, schedules, and exam room assignments.
                  </p>
                  <div className="flex items-center gap-2 text-purple-600 font-medium text-sm">
                    <span>View Duties</span>
                    <ChevronRight className="w-4 h-4 group-hover:translate-x-1 transition-transform duration-200" />
                  </div>
                </div>
              </div>
            </a>
          </div>
        </div>

        
      </div>
    </div>     
    </AuthenticatedLayout>
  )
}

export default Dashboard