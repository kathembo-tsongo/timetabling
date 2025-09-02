"use client"

import React, { useState, useEffect } from "react"
import { Head, usePage, router } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { toast } from "react-hot-toast"
import {
  Calendar,
  Plus,
  Search,
  Filter,
  Edit,
  Trash2,
  Eye,
  Power,
  ChevronDown,
  ChevronUp,
  BookOpen,
  Users,
  ClipboardList,
  AlertTriangle,
  Check,
  X,
  Clock
} from "lucide-react"

// Interfaces
interface Semester {
  id: number
  name: string
  start_date: string
  end_date: string
  is_active: boolean
  status: 'current' | 'upcoming' | 'past' | 'inactive' | 'no_dates'
  duration_days?: number
  created_at: string
  updated_at: string
  stats: {
    units_count: number
    enrollments_count: number
    class_timetables_count: number
    exam_timetables_count: number
    units_by_school: Record<string, number>
    units_by_program: Record<string, number>
  }
}

interface PageProps {
  semesters: Semester[]
  filters: {
    search?: string
    is_active?: boolean
    sort_field?: string
    sort_direction?: string
  }
  can: {
    create: boolean
    update: boolean
    delete: boolean
  }
  error?: string
}

interface SemesterFormData {
  name: string
  start_date: string
  end_date: string
  is_active: boolean
}

const SemesterManagement: React.FC = () => {
  const { semesters, filters, can, error } = usePage<PageProps>().props

  // State management
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false)
  const [isEditModalOpen, setIsEditModalOpen] = useState(false)
  const [isViewModalOpen, setIsViewModalOpen] = useState(false)
  const [selectedSemester, setSelectedSemester] = useState<Semester | null>(null)
  const [expandedRows, setExpandedRows] = useState<Set<number>>(new Set())
  const [loading, setLoading] = useState(false)

  // Form state
  const [formData, setFormData] = useState<SemesterFormData>({
    name: '',
    start_date: '',
    end_date: '',
    is_active: false
  })

  // Filter state
  const [searchTerm, setSearchTerm] = useState(filters.search || '')
  const [statusFilter, setStatusFilter] = useState<string>(
    filters.is_active !== undefined ? (filters.is_active ? 'active' : 'inactive') : 'all'
  )
  const [sortField, setSortField] = useState(filters.sort_field || 'created_at')
  const [sortDirection, setSortDirection] = useState(filters.sort_direction || 'desc')

  // Error handling
  useEffect(() => {
    if (error) {
      toast.error(error)
    }
  }, [error])

  // Filtered and sorted semesters
  const filteredSemesters = semesters.filter(semester => {
    const matchesSearch = semester.name.toLowerCase().includes(searchTerm.toLowerCase())
    const matchesStatus = statusFilter === 'all' || 
      (statusFilter === 'active' && semester.is_active) ||
      (statusFilter === 'inactive' && !semester.is_active)
    
    return matchesSearch && matchesStatus
  })

  // Status badge component
  const StatusBadge: React.FC<{ status: Semester['status'], isActive: boolean }> = ({ status, isActive }) => {
    const getStatusConfig = () => {
      if (!isActive) return { color: 'bg-gray-500', text: 'Inactive', icon: X }
      
      switch (status) {
        case 'current':
          return { color: 'bg-green-500', text: 'Current', icon: Check }
        case 'upcoming':
          return { color: 'bg-blue-500', text: 'Upcoming', icon: Clock }
        case 'past':
          return { color: 'bg-orange-500', text: 'Past', icon: Calendar }
        case 'no_dates':
          return { color: 'bg-yellow-500', text: 'No Dates', icon: AlertTriangle }
        default:
          return { color: 'bg-gray-500', text: 'Unknown', icon: X }
      }
    }

    const { color, text, icon: Icon } = getStatusConfig()

    return (
      <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium text-white ${color}`}>
        <Icon className="w-3 h-3 mr-1" />
        {text}
      </span>
    )
  }

  // Form handlers
  const handleCreateSemester = () => {
    setFormData({
      name: '',
      start_date: '',
      end_date: '',
      is_active: false
    })
    setIsCreateModalOpen(true)
  }

  const handleEditSemester = (semester: Semester) => {
    setSelectedSemester(semester)
    setFormData({
      name: semester.name,
      start_date: semester.start_date,
      end_date: semester.end_date,
      is_active: semester.is_active
    })
    setIsEditModalOpen(true)
  }

  const handleViewSemester = (semester: Semester) => {
    setSelectedSemester(semester)
    setIsViewModalOpen(true)
  }

  const handleDeleteSemester = (semester: Semester) => {
    if (confirm(`Are you sure you want to delete "${semester.name}"? This action cannot be undone.`)) {
      setLoading(true)
      router.delete(`/admin/semesters/${semester.id}`, {
        onSuccess: () => {
          toast.success('Semester deleted successfully!')
        },
        onError: (errors) => {
          toast.error(errors.error || 'Failed to delete semester')
        },
        onFinish: () => setLoading(false)
      })
    }
  }

  const handleActivateSemester = (semester: Semester) => {
    if (confirm(`Set "${semester.name}" as the active semester? This will deactivate all other semesters.`)) {
      setLoading(true)
      router.put(`/admin/semesters/${semester.id}/activate`, {}, {
        onSuccess: () => {
          toast.success(`${semester.name} is now the active semester!`)
        },
        onError: (errors) => {
          toast.error(errors.error || 'Failed to activate semester')
        },
        onFinish: () => setLoading(false)
      })
    }
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    setLoading(true)

    const url = selectedSemester 
      ? `/admin/semesters/${selectedSemester.id}`
      : '/admin/semesters'
    
    const method = selectedSemester ? 'put' : 'post'

    router[method](url, formData, {
      onSuccess: () => {
        toast.success(`Semester ${selectedSemester ? 'updated' : 'created'} successfully!`)
        setIsCreateModalOpen(false)
        setIsEditModalOpen(false)
        setSelectedSemester(null)
      },
      onError: (errors) => {
        toast.error(errors.error || `Failed to ${selectedSemester ? 'update' : 'create'} semester`)
      },
      onFinish: () => setLoading(false)
    })
  }

  const handleFilter = () => {
    const params = new URLSearchParams()
    
    if (searchTerm) params.set('search', searchTerm)
    if (statusFilter !== 'all') {
      params.set('is_active', statusFilter === 'active' ? '1' : '0')
    }
    params.set('sort_field', sortField)
    params.set('sort_direction', sortDirection)
    
    router.get(`/admin/semesters?${params.toString()}`)
  }

  const toggleRowExpansion = (semesterId: number) => {
    const newExpanded = new Set(expandedRows)
    if (newExpanded.has(semesterId)) {
      newExpanded.delete(semesterId)
    } else {
      newExpanded.add(semesterId)
    }
    setExpandedRows(newExpanded)
  }

  return (
    <AuthenticatedLayout>
      <Head title="Semester Management" />
      
      <div className="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-cyan-50 py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          
          {/* Header */}
          <div className="mb-8">
            <div className="bg-white/80 backdrop-blur-sm rounded-3xl shadow-2xl border border-slate-200/50 p-8">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <h1 className="text-4xl font-bold bg-gradient-to-r from-slate-800 via-blue-800 to-indigo-800 bg-clip-text text-transparent mb-2">
                    Semester Management
                  </h1>
                  <p className="text-slate-600 text-lg">
                    Manage academic semesters, schedules, and academic periods
                  </p>
                  <div className="flex items-center gap-4 mt-4">
                    <div className="text-sm text-slate-600">
                      Total: <span className="font-semibold">{semesters.length}</span>
                    </div>
                    <div className="text-sm text-slate-600">
                      Active: <span className="font-semibold">{semesters.filter(s => s.is_active).length}</span>
                    </div>
                  </div>
                </div>
                {can.create && (
                  <button
                    onClick={handleCreateSemester}
                    className="inline-flex items-center px-6 py-3 bg-gradient-to-r from-emerald-500 via-emerald-600 to-teal-600 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl hover:from-emerald-600 hover:via-emerald-700 hover:to-teal-700 transform hover:scale-105 hover:-translate-y-0.5 transition-all duration-300 group"
                  >
                    <Plus className="w-5 h-5 mr-2 group-hover:rotate-12 transition-transform duration-300" />
                    Create Semester
                  </button>
                )}
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
                    placeholder="Search semesters..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  />
                </div>
              </div>
              <div className="flex gap-4">
                <select
                  value={statusFilter}
                  onChange={(e) => setStatusFilter(e.target.value)}
                  className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                  <option value="all">All Status</option>
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
                <select
                  value={`${sortField}-${sortDirection}`}
                  onChange={(e) => {
                    const [field, direction] = e.target.value.split('-')
                    setSortField(field)
                    setSortDirection(direction)
                  }}
                  className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                  <option value="created_at-desc">Newest First</option>
                  <option value="created_at-asc">Oldest First</option>
                  <option value="name-asc">Name A-Z</option>
                  <option value="name-desc">Name Z-A</option>
                  <option value="start_date-desc">Start Date (Latest)</option>
                  <option value="start_date-asc">Start Date (Earliest)</option>
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

          {/* Semesters Table */}
          <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-2xl border border-slate-200/50 overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-slate-50">
                  <tr>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Semester
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Period
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Status
                    </th>
                    {/* <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Statistics
                    </th> */}
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200">
                  {filteredSemesters.map((semester, index) => (
                    <React.Fragment key={semester.id}>
                      <tr className={`hover:bg-slate-50 transition-colors duration-150 ${
                        index % 2 === 0 ? "bg-white" : "bg-slate-50/50"
                      }`}>
                        <td className="px-6 py-4">
                          <div className="flex items-center">
                            <button
                              onClick={() => toggleRowExpansion(semester.id)}
                              className="mr-3 p-1 hover:bg-gray-200 rounded"
                            >
                              {expandedRows.has(semester.id) ? (
                                <ChevronUp className="w-4 h-4" />
                              ) : (
                                <ChevronDown className="w-4 h-4" />
                              )}
                            </button>
                            <div>
                              <div className="text-sm font-medium text-slate-900">{semester.name}</div>
                              <div className="text-xs text-slate-500">
                                Created: {new Date(semester.created_at).toLocaleDateString()}
                              </div>
                            </div>
                          </div>
                        </td>
                        <td className="px-6 py-4 text-sm text-slate-700">
                          {semester.start_date && semester.end_date ? (
                            <div>
                              <div>{new Date(semester.start_date).toLocaleDateString()} -</div>
                              <div>{new Date(semester.end_date).toLocaleDateString()}</div>
                              {semester.duration_days && (
                                <div className="text-xs text-slate-500 mt-1">
                                  {semester.duration_days} days
                                </div>
                              )}
                            </div>
                          ) : (
                            <span className="text-gray-400">No dates set</span>
                          )}
                        </td>
                        <td className="px-6 py-4">
                          <StatusBadge status={semester.status} isActive={semester.is_active} />
                        </td>
                        {/* <td className="px-6 py-4 text-sm text-slate-700">
                          <div className="flex gap-4">
                            <div className="flex items-center">
                              <BookOpen className="w-4 h-4 mr-1 text-blue-500" />
                              {semester.stats.units_count}
                            </div>
                            <div className="flex items-center">
                              <Users className="w-4 h-4 mr-1 text-green-500" />
                              {semester.stats.enrollments_count}
                            </div>
                            <div className="flex items-center">
                              <Calendar className="w-4 h-4 mr-1 text-purple-500" />
                              {semester.stats.class_timetables_count}
                            </div>
                          </div>
                        </td> */}
                        <td className="px-6 py-4 text-sm font-medium">
                          <div className="flex items-center space-x-2">
                            <button
                              onClick={() => handleViewSemester(semester)}
                              className="text-blue-600 hover:text-blue-900 transition-colors"
                              title="View details"
                            >
                              <Eye className="w-4 h-4" />
                            </button>
                            {can.update && (
                              <button
                                onClick={() => handleEditSemester(semester)}
                                className="text-indigo-600 hover:text-indigo-900 transition-colors"
                                title="Edit semester"
                              >
                                <Edit className="w-4 h-4" />
                              </button>
                            )}
                            {can.update && !semester.is_active && (
                              <button
                                onClick={() => handleActivateSemester(semester)}
                                className="text-green-600 hover:text-green-900 transition-colors"
                                title="Set as active"
                              >
                                <Power className="w-4 h-4" />
                              </button>
                            )}
                            {can.delete && (
                              <button
                                onClick={() => handleDeleteSemester(semester)}
                                className="text-red-600 hover:text-red-900 transition-colors"
                                title="Delete semester"
                              >
                                <Trash2 className="w-4 h-4" />
                              </button>
                            )}
                          </div>
                        </td>
                      </tr>
                      
                      {/* Expanded row content */}
                      {expandedRows.has(semester.id) && (
                        <tr>
                          <td colSpan={5} className="px-6 py-4 bg-gray-50">
                            <div>
                                                         
                              {/* Units by Program */}
                              <div>
                                <h4 className="font-medium text-gray-900 mb-3">Units by Program</h4>
                                {Object.entries(semester.stats.units_by_program).length > 0 ? (
                                  <div className="space-y-2">
                                    {Object.entries(semester.stats.units_by_program).map(([program, count]) => (
                                      <div key={program} className="flex justify-between">
                                        <span className="text-gray-600">{program}</span>
                                        <span className="font-medium">{count}</span>
                                      </div>
                                    ))}
                                  </div>
                                ) : (
                                  <p className="text-gray-500">No units assigned</p>
                                )}
                              </div>
                            </div>
                          </td>
                        </tr>
                      )}
                    </React.Fragment>
                  ))}
                </tbody>
              </table>
              
              {filteredSemesters.length === 0 && (
                <div className="text-center py-12">
                  <Calendar className="mx-auto h-12 w-12 text-gray-400" />
                  <h3 className="mt-2 text-sm font-medium text-gray-900">No semesters found</h3>
                  <p className="mt-1 text-sm text-gray-500">
                    {searchTerm || statusFilter !== 'all' 
                      ? 'Try adjusting your filters'
                      : 'Get started by creating a new semester'
                    }
                  </p>
                </div>
              )}
            </div>
          </div>

          {/* Create/Edit Modal */}
          {(isCreateModalOpen || isEditModalOpen) && (
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
              <div className="bg-white rounded-2xl shadow-2xl max-w-md w-full">
                <div className="bg-gradient-to-r from-emerald-500 via-emerald-600 to-teal-600 p-6 rounded-t-2xl">
                  <h3 className="text-xl font-semibold text-white">
                    {selectedSemester ? 'Edit Semester' : 'Create New Semester'}
                  </h3>
                </div>

                <form onSubmit={handleSubmit} className="p-6 space-y-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Semester Name *
                    </label>
                    <input
                      type="text"
                      value={formData.name}
                      onChange={(e) => setFormData(prev => ({ ...prev, name: e.target.value }))}
                      className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                      placeholder="e.g., Fall 2024, Spring 2025"
                      required
                    />
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Start Date *
                      </label>
                      <input
                        type="date"
                        value={formData.start_date}
                        onChange={(e) => setFormData(prev => ({ ...prev, start_date: e.target.value }))}
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        required
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        End Date *
                      </label>
                      <input
                        type="date"
                        value={formData.end_date}
                        onChange={(e) => setFormData(prev => ({ ...prev, end_date: e.target.value }))}
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        min={formData.start_date}
                        required
                      />
                    </div>
                  </div>

                  <div className="flex items-center">
                    <input
                      type="checkbox"
                      id="is_active"
                      checked={formData.is_active}
                      onChange={(e) => setFormData(prev => ({ ...prev, is_active: e.target.checked }))}
                      className="w-4 h-4 text-emerald-600 bg-gray-100 border-gray-300 rounded focus:ring-emerald-500 focus:ring-2"
                    />
                    <label htmlFor="is_active" className="ml-2 text-sm font-medium text-gray-700">
                      Set as active semester
                    </label>
                  </div>
                  
                  {formData.is_active && (
                    <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                      <p className="text-yellow-800 text-sm">
                        Setting this as active will deactivate all other semesters.
                      </p>
                    </div>
                  )}

                  <div className="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200">
                    <button
                      type="button"
                      onClick={() => {
                        setIsCreateModalOpen(false)
                        setIsEditModalOpen(false)
                        setSelectedSemester(null)
                      }}
                      className="px-6 py-3 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors font-medium"
                    >
                      Cancel
                    </button>
                    <button
                      type="submit"
                      disabled={loading}
                      className="px-6 py-3 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white rounded-lg hover:from-emerald-600 hover:to-emerald-700 transition-all duration-200 font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      {loading ? 'Processing...' : selectedSemester ? 'Update Semester' : 'Create Semester'}
                    </button>
                  </div>
                </form>
              </div>
            </div>
          )}

          {/* View Modal */}
          {isViewModalOpen && selectedSemester && (
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
              <div className="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div className="bg-gradient-to-r from-slate-500 via-slate-600 to-gray-600 p-6 rounded-t-2xl">
                  <div className="flex items-center justify-between">
                    <h3 className="text-xl font-semibold text-white">
                      Semester Details
                    </h3>
                    <button
                      onClick={() => setIsViewModalOpen(false)}
                      className="text-white hover:text-gray-200 transition-colors"
                    >
                      <X className="w-6 h-6" />
                    </button>
                  </div>
                </div>

                <div className="p-6 space-y-6">
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                      <h4 className="font-semibold text-gray-900 mb-3">Basic Information</h4>
                      <div className="space-y-3">
                        <div>
                          <label className="block text-sm font-medium text-gray-700">Name</label>
                          <div className="mt-1 text-sm text-gray-900">{selectedSemester.name}</div>
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700">Status</label>
                          <div className="mt-1">
                            <StatusBadge status={selectedSemester.status} isActive={selectedSemester.is_active} />
                          </div>
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700">Duration</label>
                          <div className="mt-1 text-sm text-gray-900">
                            {selectedSemester.duration_days ? `${selectedSemester.duration_days} days` : 'Not calculated'}
                          </div>
                        </div>
                      </div>
                    </div>

                    <div>
                      <h4 className="font-semibold text-gray-900 mb-3">Period</h4>
                      <div className="space-y-3">
                        <div>
                          <label className="block text-sm font-medium text-gray-700">Start Date</label>
                          <div className="mt-1 text-sm text-gray-900">
                            {selectedSemester.start_date ? new Date(selectedSemester.start_date).toLocaleDateString() : 'Not set'}
                          </div>
                        </div>
                          <div>
                            <label className="block text-sm font-medium text-gray-700">End Date</label>
                            <div className="mt-1 text-sm text-gray-900">
                              {selectedSemester.end_date ? new Date(selectedSemester.end_date).toLocaleDateString() : 'Not set'}
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div className="md:col-span-2">
                      <h4 className="font-semibold text-gray-900 mb-3">Statistics Overview</h4>
                      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div className="bg-blue-50 p-4 rounded-lg">
                          <div className="flex items-center">
                            <BookOpen className="w-8 h-8 text-blue-500" />
                            <div className="ml-3">
                              <div className="text-2xl font-bold text-blue-600">{selectedSemester.stats.units_count}</div>
                              <div className="text-sm text-blue-500">Units</div>
                            </div>
                          </div>
                        </div>
                        <div className="bg-green-50 p-4 rounded-lg">
                          <div className="flex items-center">
                            <Users className="w-8 h-8 text-green-500" />
                            <div className="ml-3">
                              <div className="text-2xl font-bold text-green-600">{selectedSemester.stats.enrollments_count}</div>
                              <div className="text-sm text-green-500">Enrollments</div>
                            </div>
                          </div>
                        </div>
                        <div className="bg-purple-50 p-4 rounded-lg">
                          <div className="flex items-center">
                            <Calendar className="w-8 h-8 text-purple-500" />
                            <div className="ml-3">
                              <div className="text-2xl font-bold text-purple-600">{selectedSemester.stats.class_timetables_count}</div>
                              <div className="text-sm text-purple-500">Class Timetables</div>
                            </div>
                          </div>
                        </div>
                        <div className="bg-orange-50 p-4 rounded-lg">
                          <div className="flex items-center">
                            <ClipboardList className="w-8 h-8 text-orange-500" />
                            <div className="ml-3">
                              <div className="text-2xl font-bold text-orange-600">{selectedSemester.stats.exam_timetables_count}</div>
                              <div className="text-sm text-orange-500">Exam Timetables</div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>

                    {Object.keys(selectedSemester.stats.units_by_school).length > 0 && (
                      <div>
                        <h4 className="font-semibold text-gray-900 mb-3">Units by School</h4>
                        <div className="space-y-2">
                          {Object.entries(selectedSemester.stats.units_by_school).map(([school, count]) => (
                            <div key={school} className="flex justify-between items-center p-2 bg-gray-50 rounded">
                              <span className="text-gray-700 font-medium">{school}</span>
                              <span className="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-sm font-medium">{count}</span>
                            </div>
                          ))}
                        </div>
                      </div>
                    )}

                    {Object.keys(selectedSemester.stats.units_by_program).length > 0 && (
                      <div>
                        <h4 className="font-semibold text-gray-900 mb-3">Units by Program</h4>
                        <div className="space-y-2">
                          {Object.entries(selectedSemester.stats.units_by_program).map(([program, count]) => (
                            <div key={program} className="flex justify-between items-center p-2 bg-gray-50 rounded">
                              <span className="text-gray-700 font-medium">{program}</span>
                              <span className="bg-green-100 text-green-800 px-2 py-1 rounded-full text-sm font-medium">{count}</span>
                            </div>
                          ))}
                        </div>
                      </div>
                    )}

                    <div className="md:col-span-2">
                      <h4 className="font-semibold text-gray-900 mb-3">Timestamps</h4>
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                          <label className="block text-sm font-medium text-gray-700">Created</label>
                          <div className="mt-1 text-sm text-gray-900">
                            {new Date(selectedSemester.created_at).toLocaleString()}
                          </div>
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700">Last Updated</label>
                          <div className="mt-1 text-sm text-gray-900">
                            {new Date(selectedSemester.updated_at).toLocaleString()}
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div className="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200">
                    <button
                      onClick={() => setIsViewModalOpen(false)}
                      className="px-6 py-3 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors font-medium"
                    >
                      Close
                    </button>
                    {can.update && (
                      <button
                        onClick={() => {
                          setIsViewModalOpen(false)
                          handleEditSemester(selectedSemester)
                        }}
                        className="px-6 py-3 bg-gradient-to-r from-indigo-500 to-indigo-600 text-white rounded-lg hover:from-indigo-600 hover:to-indigo-700 transition-all duration-200 font-medium"
                      >
                        Edit Semester
                      </button>
                    )}
                  </div>
                </div>
              </div>
          )}
        </div>
      </div>
    </AuthenticatedLayout>
  )
}

export default SemesterManagement