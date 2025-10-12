"use client"

import React, { useState, useEffect } from "react"
import { Head, usePage, router, Link } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { toast } from "react-hot-toast"
import {
  Calendar,
  Clock,
  MapPin,
  Users,
  FileText,
  Plus,
  Search,
  Filter,
  Edit,
  Trash2,
  Eye,
  Download,
  AlertCircle,
  CheckCircle,
  TrendingUp,
  Building2,
  User,
  ChevronLeft
} from "lucide-react"

// Interfaces
interface ExamTimetable {
  id: number
  date: string
  day: string
  start_time: string
  end_time: string
  venue: string
  location: string
  no: number
  chief_invigilator: string
  unit_id: number
  semester_id: number
  class_id: number
  unit_name: string
  unit_code: string
  class_name: string
  class_code: string
  semester_name: string
}

interface Program {
  id: number
  code: string
  name: string
  full_name: string
  school: {
    id: number
    code: string
    name: string
  }
}

interface Semester {
  id: number
  name: string
  is_active: boolean
}

interface PageProps {
  examTimetables: {
    data: ExamTimetable[]
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  program: Program
  semesters: Semester[]
  schoolCode: string
  filters: {
    search?: string
    semester_id?: number
    per_page: number
  }
  can: {
    create: boolean
    edit: boolean
    delete: boolean
  }
  flash?: {
    success?: string
    error?: string
  }
}

// 🎨 School-specific theming configuration
const schoolThemes = {
  SCES: {
    primary: 'blue',
    secondary: 'indigo',
    gradient: 'from-blue-50 via-white to-indigo-50',
    headerGradient: 'from-blue-500 via-indigo-500 to-purple-500',
    buttonGradient: 'from-blue-500 via-indigo-500 to-purple-500',
    buttonHover: 'hover:from-blue-600 hover:via-indigo-600 hover:to-purple-600',
    iconColor: 'text-blue-600',
    titleGradient: 'from-slate-800 via-blue-700 to-indigo-800',
    filterFocus: 'focus:ring-blue-500 focus:border-blue-500',
    filterButton: 'bg-blue-600 hover:bg-blue-700',
    tableBorder: 'border-blue-200',
    paginationActive: 'bg-blue-50 border-blue-500 text-blue-600'
  },
  SBS: {
    primary: 'red',
    secondary: 'orange',
    gradient: 'from-red-50 via-white to-orange-50',
    headerGradient: 'from-red-500 via-orange-500 to-amber-500',
    buttonGradient: 'from-red-500 via-orange-500 to-amber-500',
    buttonHover: 'hover:from-red-600 hover:via-orange-600 hover:to-amber-600',
    iconColor: 'text-red-600',
    titleGradient: 'from-slate-800 via-red-700 to-orange-800',
    filterFocus: 'focus:ring-red-500 focus:border-red-500',
    filterButton: 'bg-red-600 hover:bg-red-700',
    tableBorder: 'border-red-200',
    paginationActive: 'bg-red-50 border-red-500 text-red-600'
  },
  SLS: {
    primary: 'green',
    secondary: 'emerald',
    gradient: 'from-green-50 via-white to-emerald-50',
    headerGradient: 'from-green-500 via-emerald-500 to-teal-500',
    buttonGradient: 'from-green-500 via-emerald-500 to-teal-500',
    buttonHover: 'hover:from-green-600 hover:via-emerald-600 hover:to-teal-600',
    iconColor: 'text-green-600',
    titleGradient: 'from-slate-800 via-green-700 to-emerald-800',
    filterFocus: 'focus:ring-green-500 focus:border-green-500',
    filterButton: 'bg-green-600 hover:bg-green-700',
    tableBorder: 'border-green-200',
    paginationActive: 'bg-green-50 border-green-500 text-green-600'
  }
}

const ExamTimetablesIndex: React.FC = () => {
  const { examTimetables, program, semesters, schoolCode, filters, can, flash } = usePage<PageProps>().props

  // Get theme based on school code
  const theme = schoolThemes[schoolCode as keyof typeof schoolThemes] || schoolThemes.SBS

  // State management
  const [searchTerm, setSearchTerm] = useState(filters.search || '')
  const [selectedSemester, setSelectedSemester] = useState<number | null>(filters.semester_id || null)
  const [perPage, setPerPage] = useState(filters.per_page || 15)
  const [loading, setLoading] = useState(false)

  // Flash messages
  useEffect(() => {
    if (flash?.success) {
      toast.success(flash.success)
    }
    if (flash?.error) {
      toast.error(flash.error)
    }
  }, [flash])

  // Dynamic route helper based on school code
  const getRoute = (routeName: string, params?: any) => {
    const routeMap: Record<string, string> = {
      'programs.index': `schools.${schoolCode.toLowerCase()}.programs.index`,
      'exam-timetables.create': `schools.${schoolCode.toLowerCase()}.programs.exam-timetables.create`,
      'exam-timetables.show': `schools.${schoolCode.toLowerCase()}.programs.exam-timetables.show`,
      'exam-timetables.edit': `schools.${schoolCode.toLowerCase()}.programs.exam-timetables.edit`,
      'exam-timetables.destroy': `schools.${schoolCode.toLowerCase()}.programs.exam-timetables.destroy`,
    }
    
    return route(routeMap[routeName], params)
  }

  // Handle filter changes
  const handleFilter = () => {
    const params = new URLSearchParams()
    
    if (searchTerm) params.set('search', searchTerm)
    if (selectedSemester) params.set('semester_id', selectedSemester.toString())
    params.set('per_page', perPage.toString())
    
    router.get(`${window.location.pathname}?${params.toString()}`, {}, {
      preserveState: true,
      preserveScroll: true
    })
  }

  // Handle pagination
  const handlePageChange = (page: number) => {
    const params = new URLSearchParams(window.location.search)
    params.set('page', page.toString())
    router.get(`${window.location.pathname}?${params.toString()}`, {}, {
      preserveState: true,
      preserveScroll: true
    })
  }

  // Handle delete
  const handleDelete = (timetable: ExamTimetable) => {
    if (confirm(`Are you sure you want to delete this exam timetable for ${timetable.unit_name}?`)) {
      setLoading(true)
      router.delete(getRoute('exam-timetables.destroy', [program.id, timetable.id]), {
        onSuccess: () => {
          toast.success('Exam timetable deleted successfully!')
        },
        onError: () => {
          toast.error('Failed to delete exam timetable')
        },
        onFinish: () => setLoading(false)
      })
    }
  }

  // Format date
  const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
      weekday: 'short',
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    })
  }

  // Format time
  const formatTime = (time: string) => {
    return time.substring(0, 5) // HH:MM
  }

  return (
    <AuthenticatedLayout>
      <Head title={`${program.name} - Exam Timetables`} />
      
      <div className={`min-h-screen bg-gradient-to-br ${theme.gradient} py-8`}>
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          
          {/* Header with Back Button */}
          <div className="mb-8">
            <Link
              href={getRoute('programs.index')}
              className={`inline-flex items-center ${theme.iconColor} hover:opacity-80 mb-4`}
            >
              <ChevronLeft className="w-5 h-5 mr-1" />
              Back to Programs
            </Link>
            
            <div className="bg-white/80 backdrop-blur-sm rounded-3xl shadow-2xl border border-slate-200/50 p-8">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <div className="flex items-center mb-2">
                    <Calendar className={`w-8 h-8 ${theme.iconColor} mr-3`} />
                    <h1 className={`text-4xl font-bold bg-gradient-to-r ${theme.titleGradient} bg-clip-text text-transparent`}>
                      Exam Timetables
                    </h1>
                  </div>
                  <h2 className="text-2xl font-semibold text-slate-700 mb-2">{program.name}</h2>
                  <p className="text-slate-600 text-lg">
                    {program.school.name} ({schoolCode}) - {program.code}
                  </p>
                  <div className="flex items-center gap-4 mt-4">
                    <div className="text-sm text-slate-600">
                      Total: <span className="font-semibold">{examTimetables.total}</span>
                    </div>
                  </div>
                </div>
                {can.create && (
                  <Link
                    href={getRoute('exam-timetables.create', program.id)}
                    className={`inline-flex items-center px-6 py-3 bg-gradient-to-r ${theme.buttonGradient} text-white font-semibold rounded-xl shadow-lg hover:shadow-xl ${theme.buttonHover} transform hover:scale-105 hover:-translate-y-0.5 transition-all duration-300 group`}
                  >
                    <Plus className="w-5 h-5 mr-2 group-hover:rotate-90 transition-transform duration-300" />
                    Schedule Exam
                  </Link>
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
                    placeholder="Search by unit code, name, or venue..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    className={`w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg ${theme.filterFocus}`}
                  />
                </div>
              </div>
              <div className="flex gap-4">
                <select
                  value={selectedSemester || ''}
                  onChange={(e) => setSelectedSemester(e.target.value ? parseInt(e.target.value) : null)}
                  className={`px-4 py-2 border border-gray-300 rounded-lg ${theme.filterFocus}`}
                >
                  <option value="">All Semesters</option>
                  {semesters.map((semester) => (
                    <option key={semester.id} value={semester.id}>
                      {semester.name} {semester.is_active && '(Active)'}
                    </option>
                  ))}
                </select>
                <select
                  value={perPage}
                  onChange={(e) => setPerPage(parseInt(e.target.value))}
                  className={`px-4 py-2 border border-gray-300 rounded-lg ${theme.filterFocus}`}
                >
                  <option value={10}>10 per page</option>
                  <option value={15}>15 per page</option>
                  <option value={25}>25 per page</option>
                  <option value={50}>50 per page</option>
                </select>
                <button
                  onClick={handleFilter}
                  className={`px-4 py-2 ${theme.filterButton} text-white rounded-lg transition-colors`}
                >
                  <Filter className="w-5 h-5" />
                </button>
              </div>
            </div>
          </div>

          {/* Exam Timetables Table */}
          <div className={`bg-white/95 backdrop-blur-sm rounded-2xl shadow-2xl border ${theme.tableBorder} overflow-hidden`}>
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-slate-50">
                  <tr>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Date & Time
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Unit
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Venue
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Students
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Invigilator
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200">
                  {examTimetables.data.map((exam, index) => (
                    <tr
                      key={exam.id}
                      className={`hover:bg-slate-50 transition-colors duration-150 ${
                        index % 2 === 0 ? "bg-white" : "bg-slate-50/50"
                      }`}
                    >
                      <td className="px-6 py-4">
                        <div className="flex items-start">
                          <Calendar className={`w-5 h-5 ${theme.iconColor} mr-3 mt-0.5`} />
                          <div>
                            <div className="text-sm font-medium text-slate-900">{formatDate(exam.date)}</div>
                            <div className="text-sm text-slate-600 flex items-center mt-1">
                              <Clock className="w-4 h-4 mr-1" />
                              {formatTime(exam.start_time)} - {formatTime(exam.end_time)}
                            </div>
                            <div className="text-xs text-slate-500 mt-1">{exam.day}</div>
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="flex items-center">
                          <FileText className="w-5 h-5 text-blue-500 mr-3" />
                          <div>
                            <div className="text-sm font-medium text-slate-900">{exam.unit_name}</div>
                            <div className={`text-xs font-semibold ${theme.iconColor}`}>{exam.unit_code}</div>
                            {exam.class_name && (
                              <div className="text-xs text-slate-500 mt-1">{exam.class_name}</div>
                            )}
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="flex items-start">
                          <MapPin className="w-5 h-5 text-green-500 mr-2 mt-0.5" />
                          <div>
                            <div className="text-sm font-medium text-slate-900">{exam.venue}</div>
                            {exam.location && (
                              <div className="text-xs text-slate-500">{exam.location}</div>
                            )}
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="flex items-center">
                          <Users className="w-5 h-5 text-purple-500 mr-2" />
                          <span className="text-sm font-semibold text-slate-900">{exam.no}</span>
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="flex items-center">
                          <User className="w-5 h-5 text-orange-500 mr-2" />
                          <span className="text-sm text-slate-700">{exam.chief_invigilator}</span>
                        </div>
                      </td>
                      <td className="px-6 py-4 text-sm font-medium">
                        <div className="flex items-center space-x-2">
                          <Link
                            href={getRoute('exam-timetables.show', [program.id, exam.id])}
                            className="text-blue-600 hover:text-blue-900 transition-colors p-1 rounded hover:bg-blue-50"
                            title="View details"
                          >
                            <Eye className="w-4 h-4" />
                          </Link>
                          {can.edit && (
                            <Link
                              href={getRoute('exam-timetables.edit', [program.id, exam.id])}
                              className="text-orange-600 hover:text-orange-900 transition-colors p-1 rounded hover:bg-orange-50"
                              title="Edit exam"
                            >
                              <Edit className="w-4 h-4" />
                            </Link>
                          )}
                          {can.delete && (
                            <button
                              onClick={() => handleDelete(exam)}
                              className="text-red-600 hover:text-red-900 transition-colors p-1 rounded hover:bg-red-50"
                              title="Delete exam"
                              disabled={loading}
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
              
              {examTimetables.data.length === 0 && (
                <div className="text-center py-12">
                  <Calendar className="mx-auto h-12 w-12 text-gray-400" />
                  <h3 className="mt-2 text-sm font-medium text-gray-900">No exam timetables found</h3>
                  <p className="mt-1 text-sm text-gray-500">
                    {searchTerm || selectedSemester
                      ? 'Try adjusting your filters'
                      : 'Get started by scheduling an exam'
                    }
                  </p>
                  {can.create && !searchTerm && !selectedSemester && (
                    <Link
                      href={getRoute('exam-timetables.create', program.id)}
                      className={`mt-4 inline-flex items-center px-4 py-2 ${theme.filterButton} text-white rounded-lg transition-colors`}
                    >
                      <Plus className="w-4 h-4 mr-2" />
                      Schedule Exam
                    </Link>
                  )}
                </div>
              )}
            </div>

            {/* Pagination */}
            {examTimetables.last_page > 1 && (
              <div className="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                <div className="flex-1 flex justify-between sm:hidden">
                  <button
                    onClick={() => handlePageChange(examTimetables.current_page - 1)}
                    disabled={examTimetables.current_page === 1}
                    className="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
                  >
                    Previous
                  </button>
                  <button
                    onClick={() => handlePageChange(examTimetables.current_page + 1)}
                    disabled={examTimetables.current_page === examTimetables.last_page}
                    className="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
                  >
                    Next
                  </button>
                </div>
                <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                  <div>
                    <p className="text-sm text-gray-700">
                      Showing <span className="font-medium">{(examTimetables.current_page - 1) * examTimetables.per_page + 1}</span> to{' '}
                      <span className="font-medium">
                        {Math.min(examTimetables.current_page * examTimetables.per_page, examTimetables.total)}
                      </span>{' '}
                      of <span className="font-medium">{examTimetables.total}</span> results
                    </p>
                  </div>
                  <div>
                    <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                      {Array.from({ length: examTimetables.last_page }, (_, i) => i + 1).map((page) => (
                        <button
                          key={page}
                          onClick={() => handlePageChange(page)}
                          className={`relative inline-flex items-center px-4 py-2 border text-sm font-medium ${
                            page === examTimetables.current_page
                              ? `z-10 ${theme.paginationActive}`
                              : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                          }`}
                        >
                          {page}
                        </button>
                      ))}
                    </nav>
                  </div>
                </div>
              </div>
            )}
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  )
}

export default ExamTimetablesIndex