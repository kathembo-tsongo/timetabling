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
  Clock,
  School,
  GraduationCap,
  Building,
  CheckSquare,
  Square,
  PlayCircle,
  StopCircle
} from "lucide-react"

// Interfaces
interface Semester {
  id: number
  name: string
  is_active: boolean
  school_code: string | null
  intake_type: string | null
  academic_year: string | null
  start_date: string | null
  end_date: string | null
  status: 'current' | 'upcoming' | 'past' | 'inactive' | 'no_dates'
  duration_days?: number
  formatted_period?: string
  created_at: string
  updated_at: string
  stats: {
    units_count: number
    enrollments_count: number
    class_timetables_count: number
    exam_timetables_count: number
    units_by_school?: Record<string, number>
    units_by_program?: Record<string, number>
  }
}

interface PageProps {
  semesters: {
    data: Semester[]
    links: any[]
    meta: any
  }
  filters: {
    search?: string
    is_active?: boolean
    intake_type?: string
    academic_year?: string
    school_code?: string
    sort_field?: string
    sort_direction?: string
  }
  filterOptions: {
    intake_types: string[]
    academic_years: string[]
    school_codes: string[]
  }
  can: {
    create: boolean
    update: boolean
    delete: boolean
  }
  flash: {
    success?: string
    error?: string
  }
  error?: string
}

interface SemesterFormData {
  name: string
  school_code: string
  intake_type: string
  academic_year: string
  start_date: string
  end_date: string
  is_active: boolean
}

const SemesterManagement: React.FC = () => {
  const { semesters, filters, filterOptions, can, flash, error } = usePage<PageProps>().props

  // State management
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false)
  const [isEditModalOpen, setIsEditModalOpen] = useState(false)
  const [isViewModalOpen, setIsViewModalOpen] = useState(false)
  const [selectedSemester, setSelectedSemester] = useState<Semester | null>(null)
  const [expandedRows, setExpandedRows] = useState<Set<number>>(new Set())
  const [loading, setLoading] = useState(false)

  // Bulk operations state
  const [selectedSemesters, setSelectedSemesters] = useState<Set<number>>(new Set())
  const [bulkLoading, setBulkLoading] = useState(false)

  // Form state - Default is_active to true
  const [formData, setFormData] = useState<SemesterFormData>({
    name: '',
    school_code: '',
    intake_type: '',
    academic_year: '',
    start_date: '',
    end_date: '',
    is_active: true // Default to active
  })

  // Filter state - CHANGED: intake and year filters now use filter props
  const [searchTerm, setSearchTerm] = useState(filters.search || '')
  const [statusFilter, setStatusFilter] = useState<string>(
    filters.is_active !== undefined ? (filters.is_active ? 'active' : 'inactive') : 'all'
  )
  const [intakeFilter, setIntakeFilter] = useState<string>(filters.intake_type || '')
  const [yearFilter, setYearFilter] = useState<string>(filters.academic_year || '')
  const [schoolFilter, setSchoolFilter] = useState<string>(filters.school_code || 'all')
  const [sortField, setSortField] = useState(filters.sort_field || 'created_at')
  const [sortDirection, setSortDirection] = useState(filters.sort_direction || 'desc')

  // Show flash messages
  useEffect(() => {
    if (flash?.success) {
      toast.success(flash.success)
    }
    if (flash?.error) {
      toast.error(flash.error)
    }
    if (error) {
      toast.error(error)
    }
  }, [flash, error])

  // Selection handlers
  const handleSelectAll = () => {
    if (selectedSemesters.size === semesters.data?.length && semesters.data?.length > 0) {
      setSelectedSemesters(new Set())
    } else {
      setSelectedSemesters(new Set(semesters.data?.map(s => s.id) || []))
    }
  }

  const handleSelectSemester = (semesterId: number) => {
    const newSelected = new Set(selectedSemesters)
    if (newSelected.has(semesterId)) {
      newSelected.delete(semesterId)
    } else {
      newSelected.add(semesterId)
    }
    setSelectedSemesters(newSelected)
  }

  // Bulk operations handlers
  const handleBulkActivate = () => {
    if (selectedSemesters.size === 0) {
      toast.error('Please select semesters to activate')
      return
    }

    if (confirm(`Activate ${selectedSemesters.size} selected semester(s)?`)) {
      setBulkLoading(true)
      router.post('/admin/semesters/bulk-activate', {
        ids: Array.from(selectedSemesters)
      }, {
        onSuccess: () => {
          setSelectedSemesters(new Set())
          toast.success('Semesters activated successfully!')
        },
        onError: () => {
          toast.error('Failed to activate semesters')
        },
        onFinish: () => setBulkLoading(false)
      })
    }
  }

  const handleBulkDeactivate = () => {
    if (selectedSemesters.size === 0) {
      toast.error('Please select semesters to deactivate')
      return
    }

    if (confirm(`Deactivate ${selectedSemesters.size} selected semester(s)?`)) {
      setBulkLoading(true)
      router.post('/admin/semesters/bulk-deactivate', {
        ids: Array.from(selectedSemesters)
      }, {
        onSuccess: () => {
          setSelectedSemesters(new Set())
          toast.success('Semesters deactivated successfully!')
        },
        onError: () => {
          toast.error('Failed to deactivate semesters')
        },
        onFinish: () => setBulkLoading(false)
      })
    }
  }

  const handleBulkDelete = () => {
    if (selectedSemesters.size === 0) {
      toast.error('Please select semesters to delete')
      return
    }

    if (confirm(`Delete ${selectedSemesters.size} selected semester(s)? This action cannot be undone.`)) {
      setBulkLoading(true)
      router.post('/admin/semesters/bulk-delete', {
        ids: Array.from(selectedSemesters)
      }, {
        onSuccess: () => {
          setSelectedSemesters(new Set())
          toast.success('Semesters deleted successfully!')
        },
        onError: () => {
          toast.error('Failed to delete semesters')
        },
        onFinish: () => setBulkLoading(false)
      })
    }
  }

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

  // Bulk operations bar component - FIXED: This was missing proper placement
  const BulkOperationsBar = () => {
    if (selectedSemesters.size === 0) return null

    return (
      <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-4">
            <span className="text-blue-700 font-medium">
              {selectedSemesters.size} semester(s) selected
            </span>
            <button
              onClick={() => setSelectedSemesters(new Set())}
              className="text-blue-600 hover:text-blue-800 text-sm underline"
            >
              Clear selection
            </button>
          </div>
          
          <div className="flex items-center space-x-2">
            {can.update && (
              <>
                <button
                  onClick={handleBulkActivate}
                  disabled={bulkLoading}
                  className="inline-flex items-center px-3 py-1.5 bg-green-600 text-white text-sm rounded-md hover:bg-green-700 disabled:opacity-50 transition-colors"
                >
                  <PlayCircle className="w-4 h-4 mr-1" />
                  Activate
                </button>
                <button
                  onClick={handleBulkDeactivate}
                  disabled={bulkLoading}
                  className="inline-flex items-center px-3 py-1.5 bg-yellow-600 text-white text-sm rounded-md hover:bg-yellow-700 disabled:opacity-50 transition-colors"
                >
                  <StopCircle className="w-4 h-4 mr-1" />
                  Deactivate
                </button>
              </>
            )}
            {can.delete && (
              <button
                onClick={handleBulkDelete}
                disabled={bulkLoading}
                className="inline-flex items-center px-3 py-1.5 bg-red-600 text-white text-sm rounded-md hover:bg-red-700 disabled:opacity-50 transition-colors"
              >
                <Trash2 className="w-4 h-4 mr-1" />
                Delete
              </button>
            )}
          </div>
        </div>
      </div>
    )
  }

  // Form handlers
  const handleCreateSemester = () => {
    setFormData({
      name: '',
      school_code: '',
      intake_type: '',
      academic_year: '',
      start_date: '',
      end_date: '',
      is_active: true // Default to active
    })
    setIsCreateModalOpen(true)
  }

  const handleEditSemester = (semester: Semester) => {
    setSelectedSemester(semester)
    setFormData({
      name: semester.name,
      school_code: semester.school_code || '',
      intake_type: semester.intake_type || '',
      academic_year: semester.academic_year || '',
      start_date: semester.start_date || '',
      end_date: semester.end_date || '',
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
        onFinish: () => setLoading(false)
      })
    }
  }

  const handleActivateSemester = (semester: Semester) => {
    const action = semester.is_active ? 'deactivate' : 'activate'
    const message = semester.is_active 
      ? `Deactivate "${semester.name}"?` 
      : `Activate "${semester.name}"?`
    
    if (confirm(message)) {
      setLoading(true)
      if (semester.is_active) {
        // Deactivate
        router.post(`/admin/semesters/bulk-deactivate`, {
          ids: [semester.id]
        }, {
          onFinish: () => setLoading(false)
        })
      } else {
        // Activate
        router.put(`/admin/semesters/${semester.id}/activate`, {}, {
          onFinish: () => setLoading(false)
        })
      }
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
        setIsCreateModalOpen(false)
        setIsEditModalOpen(false)
        setSelectedSemester(null)
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
    if (intakeFilter) params.set('intake_type', intakeFilter)
    if (yearFilter) params.set('academic_year', yearFilter)
    if (schoolFilter !== 'all') params.set('school_code', schoolFilter)
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
                      Total: <span className="font-semibold">{semesters.meta?.total || 0}</span>
                    </div>
                    <div className="text-sm text-slate-600">
                      Active: <span className="font-semibold">{semesters.data?.filter(s => s.is_active).length || 0}</span>
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

          {/* Enhanced Filters - CHANGED: Text inputs for intake and academic year */}
          <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-lg border border-slate-200/50 p-6 mb-6">
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
              <div className="lg:col-span-2">
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
              
              <select
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value)}
                className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="all">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>

              {/* CHANGED: Intake Type - from select to text input */}
              <div className="relative">
                <input
                  type="text"
                  placeholder="Filter by intake (e.g., September)"
                  value={intakeFilter}
                  onChange={(e) => setIntakeFilter(e.target.value)}
                  className="w-full px-4 py-2 pr-8 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
                {intakeFilter && (
                  <button
                    onClick={() => setIntakeFilter('')}
                    className="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
                    title="Clear intake filter"
                  >
                    <X className="w-4 h-4" />
                  </button>
                )}
              </div>

              {/* CHANGED: Academic Year - from select to text input */}
              <div className="relative">
                <input
                  type="text"
                  placeholder="Filter by year (e.g., 2024/25)"
                  value={yearFilter}
                  onChange={(e) => setYearFilter(e.target.value)}
                  className="w-full px-4 py-2 pr-8 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
                {yearFilter && (
                  <button
                    onClick={() => setYearFilter('')}
                    className="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
                    title="Clear year filter"
                  >
                    <X className="w-4 h-4" />
                  </button>
                )}
              </div>

              <select
                value={schoolFilter}
                onChange={(e) => setSchoolFilter(e.target.value)}
                className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="all">All Schools</option>
                {filterOptions?.school_codes?.map(school => (
                  <option key={school} value={school}>{school}</option>
                ))}
              </select>
            </div>
            
            <div className="flex justify-end mt-4">
              <button
                onClick={handleFilter}
                className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center"
              >
                <Filter className="w-4 h-4 mr-2" />
                Apply Filters
              </button>
            </div>
          </div>

          {/* BULK OPERATIONS BAR - This should appear when you select semesters */}
          <BulkOperationsBar />

          {/* DEBUG: Show selected count for troubleshooting */}
          {process.env.NODE_ENV === 'development' && selectedSemesters.size > 0 && (
            <div className="bg-yellow-50 border border-yellow-200 rounded p-2 mb-4 text-sm">
              DEBUG: {selectedSemesters.size} semesters selected: {Array.from(selectedSemesters).join(', ')}
            </div>
          )}

          {/* Semesters Table */}
          <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-2xl border border-slate-200/50 overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-slate-50">
                  <tr>
                    {/* SELECTION COLUMN - This is where the bulk select checkbox should be */}
                    <th className="px-6 py-4 text-left">
                      <button
                        onClick={handleSelectAll}
                        className="text-slate-600 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded"
                        title={selectedSemesters.size === semesters.data?.length && semesters.data?.length > 0 ? "Deselect all" : "Select all"}
                      >
                        {selectedSemesters.size === semesters.data?.length && semesters.data?.length > 0 ? (
                          <CheckSquare className="w-5 h-5 text-blue-600" />
                        ) : (
                          <Square className="w-5 h-5" />
                        )}
                      </button>
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Semester Details
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Academic Info
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Period
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
                  {semesters.data?.map((semester, index) => (
                    <React.Fragment key={semester.id}>
                      <tr className={`hover:bg-slate-50 transition-colors duration-150 ${
                        index % 2 === 0 ? "bg-white" : "bg-slate-50/50"
                      } ${selectedSemesters.has(semester.id) ? "bg-blue-50" : ""}`}>
                        {/* INDIVIDUAL SELECTION CHECKBOX */}
                        <td className="px-6 py-4">
                          <button
                            onClick={() => handleSelectSemester(semester.id)}
                            className="text-slate-600 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded"
                            title={selectedSemesters.has(semester.id) ? "Deselect semester" : "Select semester"}
                          >
                            {selectedSemesters.has(semester.id) ? (
                              <CheckSquare className="w-5 h-5 text-blue-600" />
                            ) : (
                              <Square className="w-5 h-5" />
                            )}
                          </button>
                        </td>
                        
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
                          <div className="space-y-1">
                            {semester.school_code && (
                              <div className="flex items-center">
                                <Building className="w-3 h-3 mr-1 text-blue-500" />
                                <span className="text-xs">{semester.school_code}</span>
                              </div>
                            )}
                            {semester.intake_type && (
                              <div className="flex items-center">
                                <Calendar className="w-3 h-3 mr-1 text-green-500" />
                                <span className="text-xs">{semester.intake_type}</span>
                              </div>
                            )}
                            {semester.academic_year && (
                              <div className="flex items-center">
                                <GraduationCap className="w-3 h-3 mr-1 text-purple-500" />
                                <span className="text-xs">{semester.academic_year}</span>
                              </div>
                            )}
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
                            {can.update && (
                              <button
                                onClick={() => handleActivateSemester(semester)}
                                className={`transition-colors ${
                                  semester.is_active
                                    ? "text-orange-600 hover:text-orange-900"
                                    : "text-green-600 hover:text-green-900"
                                }`}
                                title={semester.is_active ? "Deactivate" : "Activate"}
                              >
                                <Power className="w-4 h-4" />
                              </button>
                            )}
                            {can.delete && (
                              <button
                                onClick={() => handleDeleteSemester(semester)}
                                className="text-red-600 hover:text-red-900 transition-colors"
                                title="Delete semester"
                                disabled={loading}
                              >
                                <Trash2 className="w-4 h-4" />
                              </button>
                            )}
                          </div>
                        </td>
                      </tr>              
                      
                    </React.Fragment>
                  ))}
                </tbody>
              </table>

              {(!semesters.data || semesters.data.length === 0) && (
                <div className="text-center py-12">
                  <Calendar className="mx-auto h-12 w-12 text-gray-400" />
                  <h3 className="mt-2 text-sm font-medium text-gray-900">No semesters found</h3>
                  <p className="mt-1 text-sm text-gray-500">
                    Try adjusting your filters or create a new semester
                  </p>
                </div>
              )}
            </div>

            {/* Pagination */}
            {semesters.links && semesters.links.length > 3 && (
              <div className="px-6 py-4 border-t border-gray-200">
                <div className="flex items-center justify-between">
                  <div className="text-sm text-gray-500">
                    Showing {semesters.meta?.from || 0} to {semesters.meta?.to || 0} of {semesters.meta?.total || 0} results
                  </div>
                  <div className="flex space-x-1">
                    {semesters.links.map((link: any, index: number) => (
                      <button
                        key={index}
                        onClick={() => link.url && router.get(link.url)}
                        disabled={!link.url}
                        className={`px-3 py-1 text-sm rounded ${
                          link.active
                            ? 'bg-blue-600 text-white'
                            : link.url
                            ? 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'
                            : 'bg-gray-100 text-gray-400 cursor-not-allowed'
                        }`}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                      />
                    ))}
                  </div>
                </div>
              </div>
            )}
          </div>

          {/* Create/Edit Modal - CHANGED: Text inputs for intake_type and academic_year */}
          {(isCreateModalOpen || isEditModalOpen) && (
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
              <div className="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div className="bg-gradient-to-r from-emerald-500 via-emerald-600 to-teal-600 p-6 rounded-t-2xl">
                  <h3 className="text-xl font-semibold text-white">
                    {selectedSemester ? 'Edit Semester' : 'Create New Semester'}
                  </h3>
                </div>
                <form onSubmit={handleSubmit} className="p-6 space-y-6">
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="md:col-span-2">
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Semester Name *
                      </label>
                      <input
                        type="text"
                        value={formData.name}
                        onChange={(e) => setFormData(prev => ({ ...prev, name: e.target.value }))}
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        placeholder="e.g., Fall 2024, May-August 2025"
                        required
                      />
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        School Code
                      </label>
                      <select
                        value={formData.school_code}
                        onChange={(e) => setFormData(prev => ({ ...prev, school_code: e.target.value }))}
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                      >
                        <option value="">Select School</option>
                        {filterOptions?.school_codes?.map(school => (
                          <option key={school} value={school}>{school}</option>
                        ))}
                      </select>
                    </div>

                    {/* CHANGED: Intake Type - from select to text input */}
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Intake Type
                      </label>
                      <input
                        type="text"
                        value={formData.intake_type}
                        onChange={(e) => setFormData(prev => ({ ...prev, intake_type: e.target.value }))}
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        placeholder="e.g., September, January, May"
                      />
                    </div>

                    {/* CHANGED: Academic Year - from select to text input */}
                    <div className="md:col-span-2">
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Academic Year
                      </label>
                      <input
                        type="text"
                        value={formData.academic_year}
                        onChange={(e) => setFormData(prev => ({ ...prev, academic_year: e.target.value }))}
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        placeholder="e.g., 2024/25, 2025/26"
                      />
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Start Date
                      </label>
                      <input
                        type="date"
                        value={formData.start_date}
                        onChange={(e) => setFormData(prev => ({ ...prev, start_date: e.target.value }))}
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                      />
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        End Date
                      </label>
                      <input
                        type="date"
                        value={formData.end_date}
                        onChange={(e) => setFormData(prev => ({ ...prev, end_date: e.target.value }))}
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        min={formData.start_date}
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
                    <div className="bg-green-50 border border-green-200 rounded-lg p-3">
                      <p className="text-green-800 text-sm">
                        This semester will be set as active alongside any other active semesters. Multiple semesters can be active for different schools or programs.
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
              <div className="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
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
                      <h4 className="font-semibold text-gray-900 mb-3">Academic Information</h4>
                      <div className="space-y-3">
                        <div>
                          <label className="block text-sm font-medium text-gray-700">School Code</label>
                          <div className="mt-1 text-sm text-gray-900">
                            {selectedSemester.school_code || 'Not set'}
                          </div>
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700">Intake Type</label>
                          <div className="mt-1 text-sm text-gray-900">
                            {selectedSemester.intake_type || 'Not set'}
                          </div>
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700">Academic Year</label>
                          <div className="mt-1 text-sm text-gray-900">
                            {selectedSemester.academic_year || 'Not set'}
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
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

                    <div>
                      <h4 className="font-semibold text-gray-900 mb-3">Statistics Overview</h4>
                      <div className="grid grid-cols-2 gap-3">
                        <div className="bg-blue-50 p-3 rounded-lg">
                          <div className="flex items-center">
                            <BookOpen className="w-6 h-6 text-blue-500" />
                            <div className="ml-2">
                              <div className="text-lg font-bold text-blue-600">{selectedSemester.stats.units_count}</div>
                              <div className="text-xs text-blue-500">Units</div>
                            </div>
                          </div>
                        </div>
                        <div className="bg-green-50 p-3 rounded-lg">
                          <div className="flex items-center">
                            <Users className="w-6 h-6 text-green-500" />
                            <div className="ml-2">
                              <div className="text-lg font-bold text-green-600">{selectedSemester.stats.enrollments_count}</div>
                              <div className="text-xs text-green-500">Enrollments</div>
                            </div>
                          </div>
                        </div>
                        <div className="bg-purple-50 p-3 rounded-lg">
                          <div className="flex items-center">
                            <Calendar className="w-6 h-6 text-purple-500" />
                            <div className="ml-2">
                              <div className="text-lg font-bold text-purple-600">{selectedSemester.stats.class_timetables_count}</div>
                              <div className="text-xs text-purple-500">Class Timetables</div>
                            </div>
                          </div>
                        </div>
                        <div className="bg-orange-50 p-3 rounded-lg">
                          <div className="flex items-center">
                            <ClipboardList className="w-6 h-6 text-orange-500" />
                            <div className="ml-2">
                              <div className="text-lg font-bold text-orange-600">{selectedSemester.stats.exam_timetables_count}</div>
                              <div className="text-xs text-orange-500">Exam Timetables</div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  {selectedSemester.stats.units_by_school && Object.keys(selectedSemester.stats.units_by_school).length > 0 && (
                    <div>
                      <h4 className="font-semibold text-gray-900 mb-3">Units by School</h4>
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                        {Object.entries(selectedSemester.stats.units_by_school).map(([school, count]) => (
                          <div key={school} className="flex justify-between items-center p-3 bg-gray-50 rounded">
                            <span className="text-gray-700 font-medium">{school}</span>
                            <span className="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-sm font-medium">{count}</span>
                          </div>
                        ))}
                      </div>
                    </div>
                  )}

                  {selectedSemester.stats.units_by_program && Object.keys(selectedSemester.stats.units_by_program).length > 0 && (
                    <div>
                      <h4 className="font-semibold text-gray-900 mb-3">Units by Program</h4>
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                        {Object.entries(selectedSemester.stats.units_by_program).map(([program, count]) => (
                          <div key={program} className="flex justify-between items-center p-3 bg-gray-50 rounded">
                            <span className="text-gray-700 font-medium">{program}</span>
                            <span className="bg-green-100 text-green-800 px-2 py-1 rounded-full text-sm font-medium">{count}</span>
                          </div>
                        ))}
                      </div>
                    </div>
                  )}

                  <div>
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
            </div>
          )}
        </div>
      </div>
    </AuthenticatedLayout>
  )
}

export default SemesterManagement