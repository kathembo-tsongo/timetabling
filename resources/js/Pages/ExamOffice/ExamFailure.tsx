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
  MapPin,
  FileText,
  RefreshCw,
  Ban,
} from 'lucide-react'
import { toast } from 'react-hot-toast'

interface Failure {
  id: number
  batch_id: string
  semester: { id: number; name: string }
  program: { id: number; name: string; code: string }
  unit_code: string
  unit_name: string
  class_names: string
  student_count: number
  attempted_date: string | null
  attempted_start_time: string | null
  attempted_end_time: string | null
  assigned_slot_number: number | null
  failure_reason: string
  status: 'pending' | 'resolved' | 'retried' | 'ignored'
  created_at: string
  resolved_at: string | null
  resolver: { first_name: string; last_name: string } | null
  resolution_notes: string | null
  formatted_time_slot: string
}

interface Props {
  failures: {
    data: Failure[]
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  semesters: Array<{ id: number; name: string }>
  programs: Array<{ id: number; name: string; code: string }>
  batches: Array<{ batch_id: string; created_at: string; failure_count: number }>
  stats: {
    total_failures: number
    pending_failures: number
    resolved_failures: number
    retried_failures: number
    ignored_failures: number
  }
  filters: {
    status: string
    semester_id: number | null
    program_id: number | null
    batch_id: string | null
    per_page: number
  }
  can: {
    resolve: boolean
    retry: boolean
  }
}

export default function SchedulingFailures({
  failures,
  semesters,
  programs,
  batches,
  stats,
  filters,
  can,
}: Props) {
  const [selectedStatus, setSelectedStatus] = useState(filters.status)
  const [selectedSemester, setSelectedSemester] = useState(filters.semester_id)
  const [selectedProgram, setSelectedProgram] = useState(filters.program_id)
  const [selectedBatch, setSelectedBatch] = useState(filters.batch_id)
  const [perPage, setPerPage] = useState(filters.per_page)
  const [selectedFailure, setSelectedFailure] = useState<Failure | null>(null)
  const [isDetailModalOpen, setIsDetailModalOpen] = useState(false)
  const [isStatusModalOpen, setIsStatusModalOpen] = useState(false)
  const [newStatus, setNewStatus] = useState<'resolved' | 'retried' | 'ignored'>('resolved')
  const [statusNotes, setStatusNotes] = useState('')

  const handleFilter = () => {
    const params = new URLSearchParams()
    if (selectedStatus && selectedStatus !== 'all') params.set('status', selectedStatus)
    if (selectedSemester) params.set('semester_id', selectedSemester.toString())
    if (selectedProgram) params.set('program_id', selectedProgram.toString())
    if (selectedBatch) params.set('batch_id', selectedBatch)
    params.set('per_page', perPage.toString())

    router.get(`${window.location.pathname}?${params.toString()}`, {}, {
      preserveState: true,
      preserveScroll: true,
    })
  }

  const handlePageChange = (page: number) => {
    const params = new URLSearchParams(window.location.search)
    params.set('page', page.toString())
    router.get(`${window.location.pathname}?${params.toString()}`, {}, {
      preserveState: true,
      preserveScroll: true,
    })
  }

  const handleUpdateStatus = () => {
    if (!selectedFailure) return

    router.patch(
      route('exam-scheduling-failures.update-status', selectedFailure.id),
      {
        status: newStatus,
        notes: statusNotes,
      },
      {
        onSuccess: () => {
          toast.success(`Failure marked as ${newStatus}`)
          setIsStatusModalOpen(false)
          setSelectedFailure(null)
          setStatusNotes('')
        },
        onError: () => {
          toast.error('Failed to update status')
        },
      }
    )
  }

  const handleDelete = (id: number) => {
    if (!confirm('Are you sure you want to delete this failure record?')) return

    router.delete(route('exam-scheduling-failures.destroy', id), {
      onSuccess: () => toast.success('Failure record deleted'),
      onError: () => toast.error('Failed to delete record'),
    })
  }

  const handleExport = () => {
    window.location.href = route('exam-scheduling-failures.export', {
      status: selectedStatus,
      semester_id: selectedSemester,
      program_id: selectedProgram,
      batch_id: selectedBatch,
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
      case 'retried':
        return (
          <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
            <RefreshCw className="w-3 h-3 mr-1" />
            Retried
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

  return (
    <AuthenticatedLayout>
      <Head title="Exam Scheduling Failures" />

      <div className="min-h-screen bg-gradient-to-br from-red-50 via-white to-orange-50 py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          {/* Header */}
          <div className="mb-8">
            <Link
              href={route('dashboard')}
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
                      Exam Scheduling Failures
                    </h1>
                  </div>
                  <p className="text-slate-600 text-lg">
                    Review and manage exams that couldn't be scheduled
                  </p>
                </div>

                {can.resolve && (
                  <button
                    onClick={handleExport}
                    className="inline-flex items-center px-6 py-3 bg-gradient-to-r from-red-500 to-orange-600 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl hover:from-red-600 hover:to-orange-700 transform hover:scale-105 transition-all duration-300"
                  >
                    <Download className="w-5 h-5 mr-2" />
                    Export CSV
                  </button>
                )}
              </div>
            </div>
          </div>

          {/* Statistics Cards */}
          <div className="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
            <StatCard
              title="Total Failures"
              value={stats.total_failures}
              icon={<AlertTriangle className="w-6 h-6" />}
              color="gray"
            />
            <StatCard
              title="Pending"
              value={stats.pending_failures}
              icon={<Clock className="w-6 h-6" />}
              color="yellow"
            />
            <StatCard
              title="Resolved"
              value={stats.resolved_failures}
              icon={<CheckCircle className="w-6 h-6" />}
              color="green"
            />
            <StatCard
              title="Retried"
              value={stats.retried_failures}
              icon={<RefreshCw className="w-6 h-6" />}
              color="blue"
            />
            <StatCard
              title="Ignored"
              value={stats.ignored_failures}
              icon={<Ban className="w-6 h-6" />}
              color="gray"
            />
          </div>

          {/* Filters */}
          <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-lg border border-slate-200/50 p-6 mb-6">
            <div className="grid grid-cols-1 md:grid-cols-6 gap-4">
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
                  <option value="retried">Retried</option>
                  <option value="ignored">Ignored</option>
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Semester
                </label>
                <select
                  value={selectedSemester || ''}
                  onChange={(e) => setSelectedSemester(e.target.value ? parseInt(e.target.value) : null)}
                  className="w-full rounded-lg border-gray-300 focus:ring-red-500 focus:border-red-500"
                >
                  <option value="">All Semesters</option>
                  {semesters.map((semester) => (
                    <option key={semester.id} value={semester.id}>
                      {semester.name}
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
                  onChange={(e) => setSelectedProgram(e.target.value ? parseInt(e.target.value) : null)}
                  className="w-full rounded-lg border-gray-300 focus:ring-red-500 focus:border-red-500"
                >
                  <option value="">All Programs</option>
                  {programs.map((program) => (
                    <option key={program.id} value={program.id}>
                      {program.code} - {program.name}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Batch
                </label>
                <select
                  value={selectedBatch || ''}
                  onChange={(e) => setSelectedBatch(e.target.value || null)}
                  className="w-full rounded-lg border-gray-300 focus:ring-red-500 focus:border-red-500"
                >
                  <option value="">All Batches</option>
                  {batches.map((batch) => (
                    <option key={batch.batch_id} value={batch.batch_id}>
                      {new Date(batch.created_at).toLocaleDateString()} ({batch.failure_count} failures)
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Per Page
                </label>
                <select
                  value={perPage}
                  onChange={(e) => setPerPage(parseInt(e.target.value))}
                  className="w-full rounded-lg border-gray-300 focus:ring-red-500 focus:border-red-500"
                >
                  <option value={10}>10</option>
                  <option value={15}>15</option>
                  <option value={25}>25</option>
                  <option value={50}>50</option>
                  <option value={100}>100</option>
                </select>
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
                      Program / Semester
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Classes
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Attempted
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
                  {failures.data.length === 0 ? (
                    <tr>
                      <td colSpan={7} className="px-6 py-12 text-center text-gray-500">
                        <CheckCircle className="w-16 h-16 mx-auto mb-4 text-green-500" />
                        <p className="text-lg font-medium">No scheduling failures found!</p>
                        <p className="text-sm">All exams were scheduled successfully.</p>
                      </td>
                    </tr>
                  ) : (
                    failures.data.map((failure) => (
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
                          <div className="text-sm text-gray-900">{failure.program.code}</div>
                          <div className="text-xs text-gray-500">{failure.semester.name}</div>
                        </td>
                        <td className="px-6 py-4">
                          <div className="flex items-center text-sm text-gray-900">
                            <Users className="w-4 h-4 text-blue-500 mr-1" />
                            {failure.student_count} students
                          </div>
                          <div className="text-xs text-gray-500">{failure.class_names}</div>
                        </td>
                        <td className="px-6 py-4">
                          <div className="flex items-center text-sm text-gray-900">
                            <Calendar className="w-4 h-4 text-purple-500 mr-1" />
                            {failure.attempted_date
                              ? new Date(failure.attempted_date).toLocaleDateString()
                              : 'N/A'}
                          </div>
                          <div className="flex items-center text-xs text-gray-500">
                            <Clock className="w-3 h-3 mr-1" />
                            {failure.formatted_time_slot}
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <div className="text-sm text-gray-900 max-w-xs truncate" title={failure.failure_reason}>
                            {failure.failure_reason}
                          </div>
                        </td>
                        <td className="px-6 py-4">{getStatusBadge(failure.status)}</td>
                        <td className="px-6 py-4 text-right text-sm font-medium space-x-2">
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

                          {can.resolve && failure.status === 'pending' && (
                            <button
                              onClick={() => {
                                setSelectedFailure(failure)
                                setIsStatusModalOpen(true)
                              }}
                              className="text-green-600 hover:text-green-900"
                              title="Update Status"
                            >
                              <CheckCircle className="w-5 h-5" />
                            </button>
                          )}

                          {can.resolve && (
                            <button
                              onClick={() => handleDelete(failure.id)}
                              className="text-red-600 hover:text-red-900"
                              title="Delete"
                            >
                              <Trash2 className="w-5 h-5" />
                            </button>
                          )}
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>

            {/* Pagination */}
            {failures.last_page > 1 && (
              <div className="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                <div className="flex-1 flex justify-between sm:hidden">
                  <button
                    onClick={() => handlePageChange(failures.current_page - 1)}
                    disabled={failures.current_page === 1}
                    className="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
                  >
                    Previous
                  </button>
                  <button
                    onClick={() => handlePageChange(failures.current_page + 1)}
                    disabled={failures.current_page === failures.last_page}
                    className="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
                  >
                    Next
                  </button>
                </div>
                <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                  <div>
                    <p className="text-sm text-gray-700">
                      Showing{' '}
                      <span className="font-medium">
                        {(failures.current_page - 1) * failures.per_page + 1}
                      </span>{' '}
                      to{' '}
                      <span className="font-medium">
                        {Math.min(failures.current_page * failures.per_page, failures.total)}
                      </span>{' '}
                      of <span className="font-medium">{failures.total}</span> results
                    </p>
                  </div>
                  <div>
                    <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                      {Array.from({ length: failures.last_page }, (_, i) => i + 1).map((page) => (
                        <button
                          key={page}
                          onClick={() => handlePageChange(page)}
                          className={`relative inline-flex items-center px-4 py-2 border text-sm font-medium ${
                            page === failures.current_page
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

          {/* Status Update Modal */}
          {isStatusModalOpen && selectedFailure && (
            <StatusUpdateModal
              failure={selectedFailure}
              newStatus={newStatus}
              statusNotes={statusNotes}
              setNewStatus={setNewStatus}
              setStatusNotes={setStatusNotes}
              onSubmit={handleUpdateStatus}
              onClose={() => {
                setIsStatusModalOpen(false)
                setSelectedFailure(null)
                setStatusNotes('')
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
    blue: 'bg-blue-50 text-blue-600',
    red: 'bg-red-50 text-red-600',
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

// Detail Modal Component
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

          <div className="space-y-4">
            <DetailRow label="Unit Code" value={failure.unit_code} />
            <DetailRow label="Unit Name" value={failure.unit_name} />
            <DetailRow label="Program" value={`${failure.program.code} - ${failure.program.name}`} />
            <DetailRow label="Semester" value={failure.semester.name} />
            <DetailRow label="Classes" value={failure.class_names} />
            <DetailRow label="Student Count" value={failure.student_count.toString()} />
            <DetailRow
              label="Attempted Date"
              value={failure.attempted_date ? new Date(failure.attempted_date).toLocaleDateString() : 'N/A'}
            />
            <DetailRow label="Time Slot" value={failure.formatted_time_slot} />
            <DetailRow label="Slot Number" value={failure.assigned_slot_number?.toString() || 'N/A'} />
            <DetailRow label="Failure Reason" value={failure.failure_reason} fullWidth />
            <DetailRow label="Status" value={failure.status} />
            <DetailRow
              label="Created At"
              value={new Date(failure.created_at).toLocaleString()}
            />
            {failure.resolved_at && (
              <>
                <DetailRow
                  label="Resolved At"
                  value={new Date(failure.resolved_at).toLocaleString()}
                />
                {failure.resolver && (
                  <DetailRow
                    label="Resolved By"
                    value={`${failure.resolver.first_name} ${failure.resolver.last_name}`}
                  />
                )}
                {failure.resolution_notes && (
                  <DetailRow label="Resolution Notes" value={failure.resolution_notes} fullWidth />
                )}
              </>
            )}
          </div>

          <div className="mt-6 flex justify-end">
            <button
              onClick={onClose}
              className="px-6 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg"
            >
              Close
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}

const DetailRow = ({ label, value, fullWidth = false }: { label: string; value: string; fullWidth?: boolean }) => (
  <div className={fullWidth ? 'col-span-2' : ''}>
    <label className="block text-sm font-medium text-gray-700">{label}</label>
    <p className="mt-1 text-sm text-gray-900 bg-gray-50 p-2 rounded">{value}</p>
  </div>
)

// Status Update Modal
const StatusUpdateModal = ({
  failure,
  newStatus,
  statusNotes,
  setNewStatus,
  setStatusNotes,
  onSubmit,
  onClose,
}: {
  failure: Failure
  newStatus: 'resolved' | 'retried' | 'ignored'
  statusNotes: string
  setNewStatus: (status: 'resolved' | 'retried' | 'ignored') => void
  setStatusNotes: (notes: string) => void
  onSubmit: () => void
  onClose: () => void
}) => {
  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-2xl shadow-2xl max-w-md w-full">
        <div className="p-6">
          <h2 className="text-xl font-bold text-gray-900 mb-4">Update Failure Status</h2>

          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">New Status</label>
              <select
                value={newStatus}
                onChange={(e) => setNewStatus(e.target.value as 'resolved' | 'retried' | 'ignored')}
                className="w-full rounded-lg border-gray-300 focus:ring-red-500 focus:border-red-500"
              >
                <option value="resolved">Resolved</option>
                <option value="retried">Retried</option>
                <option value="ignored">Ignored</option>
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Notes (Optional)
              </label>
              <textarea
                value={statusNotes}
                onChange={(e) => setStatusNotes(e.target.value)}
                rows={3}
                className="w-full rounded-lg border-gray-300 focus:ring-red-500 focus:border-red-500"
                placeholder="Add any notes about this resolution..."
              />
            </div>
          </div>

          <div className="mt-6 flex justify-end space-x-3">
            <button
              onClick={onClose}
              className="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg"
            >
              Cancel
            </button>
            <button
              onClick={onSubmit}
              className="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg"
            >
              Update Status
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}