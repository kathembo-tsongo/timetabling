"use client"

import React, { useState, useEffect } from "react"
import { Head, usePage, router } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { toast } from "react-hot-toast"
import {
  Users,
  Plus,
  Search,
  Filter,
  Edit,
  Trash2,
  Eye,
  ArrowLeft,
  GraduationCap,
  Building2,
  Clock,
  BookOpen
} from "lucide-react"
import { route } from 'ziggy-js'

// Interfaces
interface Class {
  id: number
  name: string
  section: string
  year_level: number
  capacity: number
  students_count: number
  is_active: boolean
  semester: {
    id: number
    name: string
  }
  program: {
    id: number
    name: string
    code: string
  }
  created_at: string
  updated_at: string
}

interface Program {
  id: number
  code: string
  name: string
  school: {
    id: number
    name: string
    code: string
  }
}

interface PageProps {
  classes: {
    data: Class[]
    links: any[]
    meta: any
  }
  program: Program
  schoolCode: string
  semesters: any[]
  filters: {
    search?: string
    semester_id?: number
    year_level?: number
    per_page?: number
  }
  can: {
    create: boolean
    update: boolean
    delete: boolean
  }
  flash?: {
    success?: string
  }
  errors?: any
}

const ProgramClassesIndex: React.FC = () => {
  const { 
    classes, 
    program, 
    schoolCode, 
    semesters, 
    filters, 
    can = { create: false, update: false, delete: false }, 
    flash, 
    errors 
  } = usePage<PageProps>().props

  // State management
  const [searchTerm, setSearchTerm] = useState(filters.search || '')
  const [semesterFilter, setSemesterFilter] = useState<number | string>(filters.semester_id || 'all')
  const [yearLevelFilter, setYearLevelFilter] = useState<number | string>(filters.year_level || 'all')
  const [loading, setLoading] = useState(false)

  // Error handling
  useEffect(() => {
    if (errors?.error) {
      toast.error(errors.error)
    }
    if (flash?.success) {
      toast.success(flash.success)
    }
  }, [errors, flash])

  const handleFilter = () => {
    const params = new URLSearchParams()
    
    if (searchTerm) params.set('search', searchTerm)
    if (semesterFilter !== 'all') params.set('semester_id', semesterFilter.toString())
    if (yearLevelFilter !== 'all') params.set('year_level', yearLevelFilter.toString())
    
    const indexRoute = route(`schools.${schoolCode.toLowerCase()}.programs.classes.index`, program.id)
    router.get(`${indexRoute}?${params.toString()}`)
  }

  const handleDelete = (classItem: Class) => {
    if (classItem.students_count > 0) {
      toast.error(`Cannot delete class with ${classItem.students_count} enrolled students`)
      return
    }

    if (confirm(`Are you sure you want to delete "${classItem.name} Section ${classItem.section}"? This action cannot be undone.`)) {
      setLoading(true)
      const deleteRoute = route(`schools.${schoolCode.toLowerCase()}.programs.classes.destroy`, [program.id, classItem.id])
      
      router.delete(deleteRoute, {
        onSuccess: () => {
          toast.success('Class deleted successfully!')
        },
        onError: (errors) => {
          toast.error(errors.error || 'Failed to delete class')
        },
        onFinish: () => setLoading(false)
      })
    }
  }

  const backToProgramsRoute = route(`schools.${schoolCode.toLowerCase()}.programs.index`)

  return (
    <AuthenticatedLayout>
      <Head title={`${program.code} Classes - ${program.school.name}`} />
      
      <div className="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-cyan-50 py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          
          {/* Header */}
          <div className="mb-8">
            <div className="bg-white/80 backdrop-blur-sm rounded-3xl shadow-2xl border border-slate-200/50 p-8">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <div className="flex items-center mb-2">
                    <a
                      href={backToProgramsRoute}
                      className="mr-4 p-2 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-lg transition-colors"
                    >
                      <ArrowLeft className="w-5 h-5" />
                    </a>
                    <Building2 className="w-8 h-8 text-blue-600 mr-3" />
                    <div>
                      <h1 className="text-4xl font-bold bg-gradient-to-r from-slate-800 via-blue-800 to-indigo-800 bg-clip-text text-transparent">
                        {program.name}
                      </h1>
                      <div className="text-sm text-slate-600 mt-1">
                        {program.school.name} • {program.code}
                      </div>
                    </div>
                  </div>
                  <h2 className="text-2xl font-semibold text-slate-700 mb-2">Classes Management</h2>
                  <p className="text-slate-600 text-lg">
                    manage classes, sections, and student capacity for this program
                  </p>
                  <div className="flex items-center gap-4 mt-4">
                    <div className="text-sm text-slate-600">
                      Total Classes: <span className="font-semibold">{classes.data.length}</span>
                    </div>
                    <div className="text-sm text-slate-600">
                      Active: <span className="font-semibold">{classes.data.filter(c => c.is_active).length}</span>
                    </div>
                    <div className="text-sm text-slate-600">
                      Total Students: <span className="font-semibold">{classes.data.reduce((sum, c) => sum + c.students_count, 0)}</span>
                    </div>
                  </div>
                </div>
                <div className="mt-6 sm:mt-0 flex-shrink-0 flex items-center justify-end">
                  {can.create && (
                    <a
                      href={route(`schools.${schoolCode.toLowerCase()}.programs.classes.create`, program.id)}
                      className="inline-flex items-center px-6 py-3 bg-gradient-to-r from-emerald-500 via-emerald-600 to-teal-600 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl hover:from-emerald-600 hover:via-emerald-700 hover:to-teal-700 transform hover:scale-105 hover:-translate-y-0.5 transition-all duration-300 group"
                    >
                      <Plus className="w-5 h-5 mr-2 group-hover:rotate-12 transition-transform duration-300" />
                      Create Class
                    </a>
                  )}
                </div>
              </div>
            </div>
          </div>

          {/* Filters */}
          <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-lg border border-slate-200/50 p-6 mb-6">
            <div className="flex flex-col sm:flex-row gap-4">
              <div className="flex-1">
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-5 h-5" />
                  <input
                    type="text"
                    placeholder="Search classes..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  />
                </div>
              </div>
              <div className="flex gap-4">
                <select
                  value={semesterFilter}
                  onChange={(e) => setSemesterFilter(e.target.value === 'all' ? 'all' : parseInt(e.target.value))}
                  className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                  <option value="all">All Semesters</option>
                  {semesters.map((semester) => (
                    <option key={semester.id} value={semester.id}>
                      {semester.name}
                    </option>
                  ))}
                </select>
                <select
                  value={yearLevelFilter}
                  onChange={(e) => setYearLevelFilter(e.target.value === 'all' ? 'all' : parseInt(e.target.value))}
                  className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                  <option value="all">All Year Levels</option>
                  <option value="1">Year 1</option>
                  <option value="2">Year 2</option>
                  <option value="3">Year 3</option>
                  <option value="4">Year 4</option>
                </select>
                <button
                  onClick={handleFilter}
                  className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                >
                  <Filter className="w-5 h-5" />
                </button>
              </div>
            </div>
          </div>

          {/* Classes Table */}
          <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-2xl border border-slate-200/50 overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-slate-50">
                  <tr>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Class
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Semester
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Capacity
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Students
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Status
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200">
                  {classes.data.map((classItem, index) => (
                    <tr key={classItem.id} className={`hover:bg-slate-50 transition-colors duration-150 ${
                      index % 2 === 0 ? "bg-white" : "bg-slate-50/50"
                    }`}>
                      <td className="px-6 py-4">
                        <div className="flex items-center">
                          <GraduationCap className="w-8 h-8 text-blue-500 mr-3" />
                          <div>
                            <div className="text-sm font-medium text-slate-900">
                              {classItem.name} Section {classItem.section}
                            </div>
                            <div className="text-xs text-blue-600 font-semibold">
                              Year {classItem.year_level}
                            </div>
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="text-sm text-slate-900">{classItem.semester.name}</div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="text-sm text-slate-900">{classItem.capacity}</div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="flex items-center">
                          <Users className="w-4 h-4 mr-2 text-green-500" />
                          <span className="text-sm font-medium text-slate-900">{classItem.students_count}</span>
                          <span className="text-xs text-gray-500 ml-2">
                            ({Math.round((classItem.students_count / classItem.capacity) * 100)}% full)
                          </span>
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        {classItem.is_active ? (
                          <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium text-white bg-green-500">
                            Active
                          </span>
                        ) : (
                          <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium text-white bg-gray-500">
                            Inactive
                          </span>
                        )}
                      </td>
                      <td className="px-6 py-4 text-sm font-medium">
                        <div className="flex items-center space-x-2">
                          <a
                            href={route(`schools.${schoolCode.toLowerCase()}.programs.classes.show`, [program.id, classItem.id])}
                            className="text-blue-600 hover:text-blue-900 transition-colors p-1 rounded hover:bg-blue-50"
                            title="View details"
                          >
                            <Eye className="w-4 h-4" />
                          </a>
                          {can.update && (
                            <a
                              href={route(`schools.${schoolCode.toLowerCase()}.programs.classes.edit`, [program.id, classItem.id])}
                              className="text-indigo-600 hover:text-indigo-900 transition-colors p-1 rounded hover:bg-indigo-50"
                              title="Edit class"
                            >
                              <Edit className="w-4 h-4" />
                            </a>
                          )}
                          {can.delete && (
                            <button
                              onClick={() => handleDelete(classItem)}
                              className="text-red-600 hover:text-red-900 transition-colors p-1 rounded hover:bg-red-50"
                              title="Delete class"
                              disabled={classItem.students_count > 0}
                            >
                              <Trash2 className="w-4 h-4" />
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
              
              {classes.data.length === 0 && (
                <div className="text-center py-12">
                  <Users className="mx-auto h-12 w-12 text-gray-400" />
                  <h3 className="mt-2 text-sm font-medium text-gray-900">No classes found</h3>
                  <p className="mt-1 text-sm text-gray-500">
                    Get started by creating a new class for this program
                  </p>
                  {can.create && (
                    <a
                      href={route(`schools.${schoolCode.toLowerCase()}.programs.classes.create`, program.id)}
                      className="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                    >
                      <Plus className="w-4 h-4 mr-2" />
                      Create Class
                    </a>
                  )}
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  )
}

export default ProgramClassesIndex