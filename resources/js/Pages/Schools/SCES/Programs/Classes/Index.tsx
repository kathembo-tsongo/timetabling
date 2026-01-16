"use client"

import React, { useState, useEffect } from "react"
import { Head, usePage, router } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import Pagination from "@/Components/Pagination"
import { toast } from "react-hot-toast"
import {
  Users,
  Plus,
  Search,
  Filter,
  Edit,
  Trash2,
  ArrowLeft,
  GraduationCap,
  Building2,
  X
} from "lucide-react"

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

interface Semester {
  id: number
  name: string
  is_active: boolean
}

interface PaginationData {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number
  to: number
  data: Class[]
  links: Array<{
    url: string | null
    label: string
    active: boolean
  }>
}

interface PageProps {
  classes: PaginationData
  program: Program
  schoolCode: string
  semesters: Semester[]
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
    error?: string
  }
  errors?: any
}

interface ClassFormData {
  name: string
  semester_id: number | ''
  year_level: number | ''
  section: string
  capacity: number | ''
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
  const [perPage, setPerPage] = useState(filters.per_page || 15)
  const [loading, setLoading] = useState(false)

  // Modal states
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false)
  const [isEditModalOpen, setIsEditModalOpen] = useState(false)
  const [selectedClass, setSelectedClass] = useState<Class | null>(null)

  // Form data
  const [formData, setFormData] = useState<ClassFormData>({
    name: '',
    semester_id: '',
    year_level: '',
    section: '',
    capacity: 50
  })

  // Flash messages
  useEffect(() => {
    if (flash?.success) {
      toast.success(flash.success)
    }
    if (flash?.error) {
      toast.error(flash.error)
    }
    if (errors?.error) {
      toast.error(errors.error)
    }
  }, [errors, flash])

  const handleFilter = () => {
    const params: any = {
      per_page: perPage
    }
    
    if (searchTerm) params.search = searchTerm
    if (semesterFilter !== 'all') params.semester_id = semesterFilter
    if (yearLevelFilter !== 'all') params.year_level = yearLevelFilter
    
    router.get(`/schools/${schoolCode.toLowerCase()}/programs/${program.id}/classes`, params, {
      preserveState: true,
      replace: false
    })
  }

  const clearFilters = () => {
    setSearchTerm('')
    setSemesterFilter('all')
    setYearLevelFilter('all')
    router.get(`/schools/${schoolCode.toLowerCase()}/programs/${program.id}/classes`, {
      per_page: perPage
    }, {
      preserveState: true,
      replace: false
    })
  }

  // Pagination handlers
  const handlePerPageChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const newPerPage = Number(e.target.value)
    setPerPage(newPerPage)
    
    const params: any = {
      per_page: newPerPage
    }
    
    if (searchTerm) params.search = searchTerm
    if (semesterFilter !== 'all') params.semester_id = semesterFilter
    if (yearLevelFilter !== 'all') params.year_level = yearLevelFilter
    
    router.get(`/schools/${schoolCode.toLowerCase()}/programs/${program.id}/classes`, params, {
      preserveState: true,
      replace: false
    })
  }

  const handlePaginationClick = (url: string | null) => {
    if (url) {
      router.get(url, {}, {
        preserveState: true,
        replace: false
      })
    }
  }

  const handleCreateClass = () => {
    setFormData({
      name: '',
      semester_id: '',
      year_level: '',
      section: '',
      capacity: 50
    })
    setIsCreateModalOpen(true)
  }

  const handleEditClass = (classItem: Class) => {
    setSelectedClass(classItem)
    setFormData({
      name: classItem.name,
      semester_id: classItem.semester.id,
      year_level: classItem.year_level,
      section: classItem.section,
      capacity: classItem.capacity
    })
    setIsEditModalOpen(true)
  }

  const handleSubmitCreate = (e: React.FormEvent) => {
    e.preventDefault()
    
    if (!formData.name || !formData.semester_id || !formData.section) {
      toast.error('Please fill in all required fields')
      return
    }

    setLoading(true)

    router.post(`/schools/${schoolCode.toLowerCase()}/programs/${program.id}/classes`, formData, {
      onSuccess: () => {
        toast.success('Class created successfully!')
        setIsCreateModalOpen(false)
        setFormData({
          name: '',
          semester_id: '',
          year_level: '',
          section: '',
          capacity: 50
        })
      },
      onError: (errors) => {
        toast.error(errors.error || 'Failed to create class')
      },
      onFinish: () => setLoading(false)
    })
  }

  const handleSubmitEdit = (e: React.FormEvent) => {
    e.preventDefault()
    
    if (!selectedClass) return

    setLoading(true)

    router.put(`/schools/${schoolCode.toLowerCase()}/programs/${program.id}/classes/${selectedClass.id}`, formData, {
      onSuccess: () => {
        toast.success('Class updated successfully!')
        setIsEditModalOpen(false)
        setSelectedClass(null)
      },
      onError: (errors) => {
        toast.error(errors.error || 'Failed to update class')
      },
      onFinish: () => setLoading(false)
    })
  }

  const handleDelete = (classItem: Class) => {
    if (classItem.students_count > 0) {
      toast.error(`Cannot delete class with ${classItem.students_count} enrolled students`)
      return
    }

    if (confirm(`Are you sure you want to delete "${classItem.name} Section ${classItem.section}"? This action cannot be undone.`)) {
      setLoading(true)
      
      router.delete(`/schools/${schoolCode.toLowerCase()}/programs/${program.id}/classes/${classItem.id}`, {
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
                      href={`/schools/${schoolCode.toLowerCase()}/programs`}
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
                    Manage classes, sections, and student capacity for this program
                  </p>
                  <div className="flex items-center gap-4 mt-4">
                    <div className="text-sm text-slate-600">
                      Total Classes: <span className="font-semibold">{classes.total}</span>
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
                    <button
                      onClick={handleCreateClass}
                      className="inline-flex items-center px-6 py-3 bg-gradient-to-r from-emerald-500 via-emerald-600 to-teal-600 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl hover:from-emerald-600 hover:via-emerald-700 hover:to-teal-700 transform hover:scale-105 hover:-translate-y-0.5 transition-all duration-300 group"
                    >
                      <Plus className="w-5 h-5 mr-2 group-hover:rotate-12 transition-transform duration-300" />
                      Create Class
                    </button>
                  )}
                </div>
              </div>
            </div>
          </div>

          {/* Filters */}
          <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-lg border border-slate-200/50 p-6 mb-6">
            <div className="flex flex-col lg:flex-row gap-4 items-start lg:items-center">
              <div className="flex-1 max-w-md">
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-5 h-5" />
                  <input
                    type="text"
                    placeholder="Search classes..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    onKeyPress={(e) => e.key === 'Enter' && handleFilter()}
                    className="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  />
                </div>
              </div>
              <div className="flex flex-wrap gap-3">
                <select
                  value={semesterFilter}
                  onChange={(e) => setSemesterFilter(e.target.value === 'all' ? 'all' : parseInt(e.target.value))}
                  className="px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
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
                  className="px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                  <option value="all">All Year Levels</option>
                  <option value="1">Year 1</option>
                  <option value="2">Year 2</option>
                  <option value="3">Year 3</option>
                  <option value="4">Year 4</option>
                </select>
                <button
                  onClick={handleFilter}
                  className="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium"
                >
                  <Filter className="w-5 h-5" />
                </button>
                <button
                  onClick={clearFilters}
                  className="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors font-medium"
                >
                  Clear
                </button>
              </div>
            </div>
          </div>

          {/* Classes Table */}
          <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-2xl border border-slate-200/50 overflow-hidden">
            {/* Per-Page Selector */}
            <div className="px-6 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
              <h3 className="text-sm font-medium text-gray-700">Classes</h3>
              <div className="flex items-center gap-2">
                <label htmlFor="perPage" className="text-sm text-gray-600">
                  Items per page:
                </label>
                <select
                  id="perPage"
                  value={perPage}
                  onChange={handlePerPageChange}
                  className="px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                  <option value={5}>5</option>
                  <option value={10}>10</option>
                  <option value={15}>15</option>
                  <option value={20}>20</option>
                </select>
              </div>
            </div>

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
                          {can.update && (
                            <button
                              onClick={() => handleEditClass(classItem)}
                              className="text-indigo-600 hover:text-indigo-900 transition-colors p-1 rounded hover:bg-indigo-50"
                              title="Edit class"
                            >
                              <Edit className="w-4 h-4" />
                            </button>
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
                  <GraduationCap className="mx-auto h-12 w-12 text-gray-400" />
                  <h3 className="mt-2 text-sm font-medium text-gray-900">No classes found</h3>
                  <p className="mt-1 text-sm text-gray-500">
                    Get started by creating a new class for this program
                  </p>
                  {can.create && (
                    <button
                      onClick={handleCreateClass}
                      className="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                    >
                      <Plus className="w-4 h-4 mr-2" />
                      Create Class
                    </button>
                  )}
                </div>
              )}
            </div>

            {/* Pagination */}
            {classes.data.length > 0 && (
              <div className="px-6 py-4 bg-gray-50 border-t border-gray-200">
                <div className="flex flex-col sm:flex-row items-center justify-between gap-4">
                  <div className="text-sm text-gray-700">
                    Showing <span className="font-medium">{classes.from || 0}</span> to{' '}
                    <span className="font-medium">{classes.to || 0}</span> of{' '}
                    <span className="font-medium">{classes.total || 0}</span> classes
                  </div>
                  <Pagination 
                    links={classes.links} 
                    onPageChange={handlePaginationClick} 
                  />
                </div>
              </div>
            )}
          </div>

          {/* Create Modal */}
          {isCreateModalOpen && (
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
              <div className="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div className="bg-gradient-to-r from-emerald-500 via-emerald-600 to-teal-600 p-6 rounded-t-2xl">
                  <div className="flex items-center justify-between">
                    <h3 className="text-xl font-semibold text-white">Create New Class</h3>
                    <button
                      onClick={() => setIsCreateModalOpen(false)}
                      className="text-white hover:text-gray-200 transition-colors"
                    >
                      <X className="w-6 h-6" />
                    </button>
                  </div>
                </div>

                <form onSubmit={handleSubmitCreate} className="p-6 space-y-6">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Class Name *</label>
                    <input
                      type="text"
                      value={formData.name}
                      onChange={(e) => setFormData({...formData, name: e.target.value})}
                      className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                      placeholder="e.g., BBIT 1.1"
                      required
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Semester *</label>
                    <select
                      value={formData.semester_id}
                      onChange={(e) => setFormData({...formData, semester_id: parseInt(e.target.value)})}
                      className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                      required
                    >
                      <option value="">Select Semester</option>
                      {semesters.map((semester) => (
                        <option key={semester.id} value={semester.id}>
                          {semester.name}
                        </option>
                      ))}
                    </select>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">Year Level</label>
                      <select
                        value={formData.year_level}
                        onChange={(e) => setFormData({...formData, year_level: parseInt(e.target.value)})}
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                      >
                        <option value="">Select Year</option>
                        <option value="1">Year 1</option>
                        <option value="2">Year 2</option>
                        <option value="3">Year 3</option>
                        <option value="4">Year 4</option>
                      </select>
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">Section *</label>
                      <input
                        type="text"
                        value={formData.section}
                        onChange={(e) => setFormData({...formData, section: e.target.value.toUpperCase()})}
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        placeholder="A"                        
                        required
                      />
                    </div>
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Capacity</label>
                    <input
                      type="number"
                      value={formData.capacity}
                      onChange={(e) => setFormData({...formData, capacity: parseInt(e.target.value)})}
                      className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                      min="1"
                      max="200"
                    />
                  </div>

                  <div className="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200">
                    <button
                      type="button"
                      onClick={() => setIsCreateModalOpen(false)}
                      className="px-6 py-3 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors font-medium"
                    >
                      Cancel
                    </button>
                    <button
                      type="submit"
                      disabled={loading}
                      className="px-6 py-3 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white rounded-lg hover:from-emerald-600 hover:to-emerald-700 transition-all duration-200 font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      {loading ? 'Creating...' : 'Create Class'}
                    </button>
                  </div>
                </form>
              </div>
            </div>
          )}

          {/* Edit Modal */}
          {isEditModalOpen && selectedClass && (
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
              <div className="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div className="bg-gradient-to-r from-blue-500 via-blue-600 to-indigo-600 p-6 rounded-t-2xl">
                  <div className="flex items-center justify-between">
                    <h3 className="text-xl font-semibold text-white">Edit Class</h3>
                    <button
                      onClick={() => setIsEditModalOpen(false)}
                      className="text-white hover:text-gray-200 transition-colors"
                    >
                      <X className="w-6 h-6" />
                    </button>
                  </div>
                </div>

                <form onSubmit={handleSubmitEdit} className="p-6 space-y-6">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Class Name *</label>
                    <input
                      type="text"
                      value={formData.name}
                      onChange={(e) => setFormData({...formData, name: e.target.value})}
                      className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                      required
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Semester *</label>
                    <select
                      value={formData.semester_id}
                      onChange={(e) => setFormData({...formData, semester_id: parseInt(e.target.value)})}
                      className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                      required
                    >
                      <option value="">Select Semester</option>
                      {semesters.map((semester) => (
                        <option key={semester.id} value={semester.id}>
                          {semester.name}
                        </option>
                      ))}
                    </select>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">Year Level</label>
                      <select
                        value={formData.year_level}
                        onChange={(e) => setFormData({...formData, year_level: parseInt(e.target.value)})}
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                      >
                        <option value="">Select Year</option>
                        <option value="1">Year 1</option>
                        <option value="2">Year 2</option>
                        <option value="3">Year 3</option>
                        <option value="4">Year 4</option>
                      </select>
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">Section *</label>
                      <input
                        type="text"
                        value={formData.section}
                        onChange={(e) => setFormData({...formData, section: e.target.value.toUpperCase()})}
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        required
                      />
                    </div>
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Capacity</label>
                    <input
                      type="number"
                      value={formData.capacity}
                      onChange={(e) => setFormData({...formData, capacity: parseInt(e.target.value)})}
                      className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                      min="1"
                      max="200"
                    />
                  </div>

                  <div className="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200">
                    <button
                      type="button"
                      onClick={() => setIsEditModalOpen(false)}
                      className="px-6 py-3 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors font-medium"
                    >
                      Cancel
                    </button>
                    <button
                      type="submit"
                      disabled={loading}
                      className="px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-lg hover:from-blue-600 hover:to-blue-700 transition-all duration-200 font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      {loading ? 'Updating...' : 'Update Class'}
                    </button>
                  </div>
                </form>
              </div>
            </div>
          )}

        </div>
      </div>
    </AuthenticatedLayout>
  )
}

export default ProgramClassesIndex