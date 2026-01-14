import React, { useState } from 'react'
import { Head, Link, router } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import {
  AlertTriangle,
  CheckCircle,
  XCircle,
  Clock,
  Download,
  Filter,
  Search,
  Trash2,
  Eye,
  ChevronLeft,
  Calendar,
  Users,
  BookOpen,
  RefreshCw,
  Ban,
} from 'lucide-react'
import { toast } from 'react-hot-toast'

interface Failure {
  id: number
  program_id: number
  school_id: number
  class_name: string
  section: string | null
  unit_code: string
  unit_name: string
  student_count: number
  lecturer_name: string | null
  failure_reasons: string
  attempted_dates: string | null
  status: 'pending' | 'resolved' | 'ignored'
  created_at: string
  resolved_at: string | null
  resolved_by: number | null
  resolution_notes: string | null
  program?: { id: number; name: string }
  school?: { id: number; name: string }
  creator?: { id: number; first_name: string; last_name: string }
  resolver?: { id: number; first_name: string; last_name: string }
}

interface Props {
  failedExams: {
    data: Failure[]
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number
    to: number
  }
  statistics: {
    total: number
    pending: number
    resolved: number
    ignored: number
  }
  schools: Array<{ id: number; name: string }>
  programs: Array<{ id: number; name: string; school_id: number }>
  filters: {
    status?: string
    school_id?: number
    program_id?: number
    search?: string
  }
  can: {
    view: boolean
    delete: boolean
    resolve: boolean
    ignore: boolean
    revert: boolean
  }
}

export default function FailedScheduledExams({
  failedExams,
  statistics,
  schools,
  programs,
  filters,
  can,
}: Props) {
  const [selectedStatus, setSelectedStatus] = useState(filters.status || 'all')
  const [selectedSchool, setSelectedSchool] = useState(filters.school_id)
  const [selectedProgram, setSelectedProgram] = useState(filters.program_id)
  const [searchQuery, setSearchQuery] = useState(filters.search || '')
  const [selectedFailure, setSelectedFailure] = useState<Failure | null>(null)
  const [isDetailModalOpen, setIsDetailModalOpen] = useState(false)
  const [isResolveModalOpen, setIsResolveModalOpen] = useState(false)
  const [isIgnoreModalOpen, setIsIgnoreModalOpen] = useState(false)
  const [resolutionNotes, setResolutionNotes] = useState('')

  const handleFilter = () => {
    const params = new URLSearchParams()
    if (selectedStatus && selectedStatus !== 'all') params.set('status', selectedStatus)
    if (selectedSchool) params.set('school_id', selectedSchool.toString())
    if (selectedProgram) params.set('program_id', selectedProgram.toString())
    if (searchQuery) params.set('search', searchQuery)

    router.get(`/examoffice/failedScheduledExams?${params.toString()}`, {}, {
      preserveState: true,
      preserveScroll: true,
    })
  }

  const handlePageChange = (page: number) => {
    const params = new URLSearchParams(window.location.search)
    params.set('page', page.toString())
    router.get(`/examoffice/failedScheduledExams?${params.toString()}`, {}, {
      preserveState: true,
      preserveScroll: true,
    })
  }

  const handleResolve = () => {
    if (!selectedFailure) return

    router.post(
      route('admin.failed-exams.resolve', selectedFailure.id),
      {
        resolution_notes: resolutionNotes,
      },
      {
        onSuccess: () => {
          toast.success('Exam marked as resolved')
          setIsResolveModalOpen(false)
          setSelectedFailure(null)
          setResolutionNotes('')
        },
        onError: () => {
          toast.error('Failed to resolve')
        },
      }
    )
  }

  const handleIgnore = () => {
    if (!selectedFailure) return

    router.post(
      route('admin.failed-exams.ignore', selectedFailure.id),
      {
        resolution_notes: resolutionNotes,
      },
      {
        onSuccess: () => {
          toast.success('Exam marked as ignored')
          setIsIgnoreModalOpen(false)
          setSelectedFailure(null)
          setResolutionNotes('')
        },
        onError: () => {
          toast.error('Failed to ignore')
        },
      }
    )
  }

  const handleRevert = (failure: Failure) => {
    if (!confirm('Revert this exam back to pending status?')) return

    router.post(
      route('admin.failed-exams.revert', failure.id),
      {},
      {
        onSuccess: () => toast.success('Status reverted to pending'),
        onError: () => toast.error('Failed to revert status'),
      }
    )
  }

  const handleDelete = (id: number) => {
    if (!confirm('Are you sure you want to delete this failure record?')) return

    router.delete(route('admin.failed-exams.destroy', id), {
      onSuccess: () => toast.success('Failure record deleted'),
      onError: () => toast.error('Failed to delete record'),
    })
  }

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'pending':
        return (
          <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
            <Clock className="w-3 h-3 mr-1" />
            Pending
          </span>
        )
      case 'resolved':
        return (
          <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
            <CheckCircle className="w-3 h-3 mr-1" />
            Resolved
          </span>
        )
      case 'ignored':
        return (
          <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
            <Ban className="w-3 h-3 mr-1" />
            Ignored
          </span>
        )
      default:
        return null
    }
  }

  // Filter programs based on selected school
  const filteredPrograms = selectedSchool
    ? programs.filter(p => p.school_id === selectedSchool)
    : programs

  return (
    <AuthenticatedLayout>
      <Head title="Failed Exam Schedules" />

      <div className="min-h-screen bg-gradient-to-br from-red-50 via-white to-orange-50 py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          {/* Header */}
          <div className="mb-8">
            <Link
              href={route('examoffice.dashboard')}
              className="inline-flex items-center text-red-600 hover:opacity-80 mb-4"
            >
              <ChevronLeft className="w-5 h-5 mr-1" />
              Back to Dashboard
            </Link>

            <div className="bg-white/80 backdrop-blur-sm rounded-3xl shadow-2xl border border-slate-200/50 p-8">
              <div className="flex items-center justify-between">
                <div>
                  <div className="flex items-center mb-2">
                    <AlertTriangle className="w-8 h-8 text-red-600 mr-3" />
                    <h1 className="text-4xl font-bold bg-gradient-to-r from-slate-800 via-red-700 to-orange-800 bg-clip-text text-transparent">
                      Failed Exam Schedules
                    </h1>
                  </div>
                  <p className="text-slate-600 text-lg">
                    Review and manage exams that couldn't be scheduled
                  </p>
                </div>
              </div>
            </div>
          </div>

          {/* Statistics Cards */}
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <StatCard
              title="Total Failures"
              value={statistics.total}
              icon={<AlertTriangle className="w-6 h-6" />}
              color="gray"
            />
            <StatCard
              title="Pending"
              value={statistics.pending}
              icon={<Clock className="w-6 h-6" />}
              color="yellow"
            />
            <StatCard
              title="Resolved"
              value={statistics.resolved}
              icon={<CheckCircle className="w-6 h-6" />}
              color="green"
            />
            <StatCard
              title="Ignored"
              value={statistics.ignored}
              icon={<Ban className="w-6 h-6" />}
              color="gray"
            />
          </div>

          {/* Filters */}
          <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-lg border border-slate-200/50 p-6 mb-6">
            <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Status
                </label>
                <select
                  value={selectedStatus}
                  onChange={(e) => setSelectedStatus(e.target.value)}
                  className="w-full rounded-lg border-gray-300 focus:ring-red-500 focus:border-red-500"
                >
                  <option value="all">All Status</option>
                  <option value="pending">Pending</option>
                  <option value="resolved">Resolved</option>
                  <option value="ignored">Ignored</option>
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  School
                </label>
                <select
                  value={selectedSchool || ''}
                  onChange={(e) => {
                    setSelectedSchool(e.target.value ? parseInt(e.target.value) : undefined)
                    setSelectedProgram(undefined)
                  }}
                  className="w-full rounded-lg border-gray-300 focus:ring-red-500 focus:border-red-500"
                >
                  <option value="">All Schools</option>
                  {schools.map((school) => (
                    <option key={school.id} value={school.id}>
                      {school.name}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Program
                </label>
                <select
                  value={selectedProgram || ''}
                  onChange={(e) => setSelectedProgram(e.target.value ? parseInt(e.target.value) : undefined)}
                  className="w-full rounded-lg border-gray-300 focus:ring-red-500 focus:border-red-500"
                  disabled={!selectedSchool}
                >
                  <option value="">All Programs</option>
                  {filteredPrograms.map((program) => (
                    <option key={program.id} value={program.id}>
                      {program.name}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Search
                </label>
                <input
                  type="text"
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  placeholder="Unit code, name, class..."
                  className="w-full rounded-lg border-gray-300 focus:ring-red-500 focus:border-red-500"
                  onKeyPress={(e) => e.key === 'Enter' && handleFilter()}
                />
              </div>

              <div className="flex items-end">
                <button
                  onClick={handleFilter}
                  className="w-full px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg flex items-center justify-center"
                >
                  <Filter className="w-4 h-4 mr-2" />
                  Apply Filters
                </button>
              </div>
            </div>
          </div>

          {/* Failures Table */}
          <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-lg border border-slate-200/50 overflow-hidden">
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Unit
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Program / School
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Class
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Attempted Dates
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Reason
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Status
                    </th>
                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {failedExams.data.length === 0 ? (
                    <tr>
                      <td colSpan={7} className="px-6 py-12 text-center text-gray-500">
                        <CheckCircle className="w-16 h-16 mx-auto mb-4 text-green-500" />
                        <p className="text-lg font-medium">No scheduling failures found!</p>
                        <p className="text-sm">All exams were scheduled successfully.</p>
                      </td>
                    </tr>
                  ) : (
                    failedExams.data.map((failure) => (
                      <tr key={failure.id} className="hover:bg-gray-50">
                        <td className="px-6 py-4">
                          <div className="flex items-center">
                            <BookOpen className="w-5 h-5 text-red-500 mr-2" />
                            <div>
                              <div className="text-sm font-medium text-gray-900">
                                {failure.unit_code}
                              </div>
                              <div className="text-xs text-gray-500">{failure.unit_name}</div>
                            </div>
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <div className="text-sm text-gray-900">{failure.program?.name || 'N/A'}</div>
                          <div className="text-xs text-gray-500">{failure.school?.name || 'N/A'}</div>
                        </td>
                        <td className="px-6 py-4">
                          <div className="flex items-center text-sm text-gray-900">
                            <Users className="w-4 h-4 text-blue-500 mr-1" />
                            {failure.student_count} students
                          </div>
                          <div className="text-xs text-gray-500">
                            {failure.class_name} {failure.section ? `(${failure.section})` : ''}
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <div className="text-sm text-gray-900">
                            {failure.attempted_dates || 'N/A'}
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <div className="text-sm text-gray-900 max-w-xs truncate" title={failure.failure_reasons}>
                            {failure.failure_reasons}
                          </div>
                        </td>
                        <td className="px-6 py-4">{getStatusBadge(failure.status)}</td>
                        <td className="px-6 py-4 text-right text-sm font-medium">
                          <div className="flex items-center justify-end space-x-2">
                            {/* View Details - always available if can.view is true */}
                            {can.view && (
                              <button
                                onClick={() => {
                                  setSelectedFailure(failure)
                                  setIsDetailModalOpen(true)
                                }}
                                className="text-blue-600 hover:text-blue-900"
                                title="View Details"
                              >
                                <Eye className="w-5 h-5" />
                              </button>
                            )}

                            {/* Resolve and Ignore - only for authorized users with pending status */}
                            {can.resolve && failure.status === 'pending' && (
                              <button
                                onClick={() => {
                                  setSelectedFailure(failure)
                                  setIsResolveModalOpen(true)
                                }}
                                className="text-green-600 hover:text-green-900"
                                title="Mark as Resolved"
                              >
                                <CheckCircle className="w-5 h-5" />
                              </button>
                            )}

                            {can.ignore && failure.status === 'pending' && (
                              <button
                                onClick={() => {
                                  setSelectedFailure(failure)
                                  setIsIgnoreModalOpen(true)
                                }}
                                className="text-gray-600 hover:text-gray-900"
                                title="Ignore"
                              >
                                <Ban className="w-5 h-5" />
                              </button>
                            )}

                            {/* Revert - only for authorized users with resolved or ignored status */}
                            {can.revert && (failure.status === 'resolved' || failure.status === 'ignored') && (
                              <button
                                onClick={() => handleRevert(failure)}
                                className="text-yellow-600 hover:text-yellow-900"
                                title="Revert to Pending"
                              >
                                <RefreshCw className="w-5 h-5" />
                              </button>
                            )}

                            {/* Delete - available for Exam Office and other authorized users */}
                            {can.delete && (
                              <button
                                onClick={() => handleDelete(failure.id)}
                                className="text-red-600 hover:text-red-900"
                                title="Delete"
                              >
                                <Trash2 className="w-5 h-5" />
                              </button>
                            )}
                          </div>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>

            {/* Pagination */}
            {failedExams.last_page > 1 && (
              <div className="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                <div className="flex-1 flex justify-between sm:hidden">
                  <button
                    onClick={() => handlePageChange(failedExams.current_page - 1)}
                    disabled={failedExams.current_page === 1}
                    className="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
                  >
                    Previous
                  </button>
                  <button
                    onClick={() => handlePageChange(failedExams.current_page + 1)}
                    disabled={failedExams.current_page === failedExams.last_page}
                    className="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
                  >
                    Next
                  </button>
                </div>
                <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                  <div>
                    <p className="text-sm text-gray-700">
                      Showing{' '}
                      <span className="font-medium">{failedExams.from}</span>
                      {' '}to{' '}
                      <span className="font-medium">{failedExams.to}</span>
                      {' '}of{' '}
                      <span className="font-medium">{failedExams.total}</span> results
                    </p>
                  </div>
                  <div>
                    <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                      {Array.from({ length: Math.min(failedExams.last_page, 10) }, (_, i) => i + 1).map((page) => (
                        <button
                          key={page}
                          onClick={() => handlePageChange(page)}
                          className={`relative inline-flex items-center px-4 py-2 border text-sm font-medium ${
                            page === failedExams.current_page
                              ? 'z-10 bg-red-50 border-red-500 text-red-600'
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

          {/* Detail Modal */}
          {isDetailModalOpen && selectedFailure && (
            <FailureDetailModal
              failure={selectedFailure}
              onClose={() => {
                setIsDetailModalOpen(false)
                setSelectedFailure(null)
              }}
            />
          )}

          {/* Resolve Modal - only render if can.resolve is true */}
          {can.resolve && isResolveModalOpen && selectedFailure && (
            <ActionModal
              title="Mark as Resolved"
              description="Add notes about how this issue was resolved."
              buttonText="Mark as Resolved"
              buttonColor="green"
              notes={resolutionNotes}
              setNotes={setResolutionNotes}
              onSubmit={handleResolve}
              onClose={() => {
                setIsResolveModalOpen(false)
                setSelectedFailure(null)
                setResolutionNotes('')
              }}
            />
          )}

          {/* Ignore Modal - only render if can.ignore is true */}
          {can.ignore && isIgnoreModalOpen && selectedFailure && (
            <ActionModal
              title="Ignore This Failure"
              description="Add notes about why this failure is being ignored."
              buttonText="Ignore"
              buttonColor="gray"
              notes={resolutionNotes}
              setNotes={setResolutionNotes}
              onSubmit={handleIgnore}
              onClose={() => {
                setIsIgnoreModalOpen(false)
                setSelectedFailure(null)
                setResolutionNotes('')
              }}
            />
          )}
        </div>
      </div>
    </AuthenticatedLayout>
  )
}

// Stat Card Component
const StatCard = ({ title, value, icon, color }: { title: string; value: number; icon: React.ReactNode; color: string }) => {
  const colorClasses = {
    gray: 'bg-gray-50 text-gray-600',
    yellow: 'bg-yellow-50 text-yellow-600',
    green: 'bg-green-50 text-green-600',
  }

  return (
    <div className={`${colorClasses[color as keyof typeof colorClasses]} rounded-xl p-4 shadow-md`}>
      <div className="flex items-center justify-between">
        <div>
          <p className="text-sm font-medium opacity-80">{title}</p>
          <p className="text-2xl font-bold mt-1">{value}</p>
        </div>
        <div className="opacity-80">{icon}</div>
      </div>
    </div>
  )
}

// Failure Detail Modal Component
const FailureDetailModal = ({ failure, onClose }: { failure: Failure; onClose: () => void }) => {
  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div className="p-6">
          <div className="flex items-center justify-between mb-6">
            <h2 className="text-2xl font-bold text-gray-900">Failure Details</h2>
            <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
              <XCircle className="w-6 h-6" />
            </button>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <DetailRow label="Unit Code" value={failure.unit_code} />
            <DetailRow label="Unit Name" value={failure.unit_name} />
            <DetailRow label="Program" value={failure.program?.name || 'N/A'} />
            <DetailRow label="School" value={failure.school?.name || 'N/A'} />
            <DetailRow label="Class" value={failure.class_name} />
            <DetailRow label="Section" value={failure.section || 'N/A'} />
            <DetailRow label="Student Count" value={failure.student_count.toString()} />
            <DetailRow label="Lecturer" value={failure.lecturer_name || 'N/A'} />
            <DetailRow label="Attempted Dates" value={failure.attempted_dates || 'N/A'} fullWidth />
            <DetailRow label="Failure Reasons" value={failure.failure_reasons} fullWidth />
            <DetailRow label="Status" value={failure.status} />
            <DetailRow label="Created At" value={new Date(failure.created_at).toLocaleString()} />
            {failure.resolved_at && (
              <>
                <DetailRow label="Resolved At" value={new Date(failure.resolved_at).toLocaleString()} />
                {failure.resolver && (
                  <DetailRow label="Resolved By" value={`${failure.resolver.first_name} ${failure.resolver.last_name}`} />
                )}
              </>
            )}
            {failure.resolution_notes && (
              <DetailRow label="Resolution Notes" value={failure.resolution_notes} fullWidth />
            )}
          </div>

          <div className="mt-6 flex justify-end">
            <button onClick={onClose} className="px-6 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg">
              Close
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}

// Detail Row Component
const DetailRow = ({ label, value, fullWidth = false }: { label: string; value: string; fullWidth?: boolean }) => (
  <div className={fullWidth ? 'col-span-2' : ''}>
    <label className="block text-sm font-medium text-gray-700">{label}</label>
    <p className="mt-1 text-sm text-gray-900 bg-gray-50 p-2 rounded">{value}</p>
  </div>
)

// Action Modal Component
const ActionModal = ({
  title,
  description,
  buttonText,
  buttonColor,
  notes,
  setNotes,
  onSubmit,
  onClose,
}: {
  title: string
  description: string
  buttonText: string
  buttonColor: 'green' | 'gray'
  notes: string
  setNotes: (notes: string) => void
  onSubmit: () => void
  onClose: () => void
}) => {
  const buttonClasses = buttonColor === 'green' ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-600 hover:bg-gray-700'

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-2xl shadow-2xl max-w-md w-full">
        <div className="p-6">
          <h2 className="text-xl font-bold text-gray-900 mb-2">{title}</h2>
          <p className="text-sm text-gray-600 mb-4">{description}</p>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">Notes (Optional)</label>
            <textarea
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              rows={4}
              className="w-full rounded-lg border-gray-300 focus:ring-red-500 focus:border-red-500"
              placeholder="Add any relevant notes..."
            />
          </div>

          <div className="mt-6 flex justify-end space-x-3">
            <button onClick={onClose} className="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg">
              Cancel
            </button>
            <button onClick={onSubmit} className={`px-4 py-2 ${buttonClasses} text-white rounded-lg`}>
              {buttonText}
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}