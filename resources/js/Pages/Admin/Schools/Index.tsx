"use client"

import React, { useState, useEffect } from "react"
import { Head, usePage, router } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { toast } from "react-hot-toast"
import {
  Building2,
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
  Phone,
  Mail,
  Check,
  X,
  AlertTriangle
} from "lucide-react"

// Interfaces
interface School {
  id: number
  code: string
  name: string
  full_name: string
  description?: string
  is_active: boolean
  contact_email?: string
  contact_phone?: string
  programs_count: number
  units_count: number
  sort_order: number
  created_at: string
  updated_at: string
}

interface PageProps {
  schools: School[]
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
  flash?: {
    success?: string
  }
  errors?: {
    error?: string
  }
}

interface SchoolFormData {
  code: string
  name: string
  description: string
  contact_email: string
  contact_phone: string
  is_active: boolean
  sort_order: number
}

const SchoolsManagement: React.FC = () => {
  const { schools, filters, can, flash, errors } = usePage<PageProps>().props

  // State management
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false)
  const [isEditModalOpen, setIsEditModalOpen] = useState(false)
  const [isViewModalOpen, setIsViewModalOpen] = useState(false)
  const [selectedSchool, setSelectedSchool] = useState<School | null>(null)
  const [expandedRows, setExpandedRows] = useState<Set<number>>(new Set())
  const [loading, setLoading] = useState(false)

  // Form state
  const [formData, setFormData] = useState<SchoolFormData>({
    code: '',
    name: '',
    description: '',
    contact_email: '',
    contact_phone: '',
    is_active: true,
    sort_order: 0
  })

  // Filter state
  const [searchTerm, setSearchTerm] = useState(filters.search || '')
  const [statusFilter, setStatusFilter] = useState<string>(
    filters.is_active !== undefined ? (filters.is_active ? 'active' : 'inactive') : 'all'
  )
  const [sortField, setSortField] = useState(filters.sort_field || 'sort_order')
  const [sortDirection, setSortDirection] = useState(filters.sort_direction || 'asc')

  // Error handling
  useEffect(() => {
    if (errors?.error) {
      toast.error(errors.error)
    }
    if (flash?.success) {
      toast.success(flash.success)
    }
  }, [errors, flash])

  // Filtered and sorted schools
  const filteredSchools = schools.filter(school => {
    const matchesSearch = school.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      school.code.toLowerCase().includes(searchTerm.toLowerCase())
    const matchesStatus = statusFilter === 'all' || 
      (statusFilter === 'active' && school.is_active) ||
      (statusFilter === 'inactive' && !school.is_active)
    
    return matchesSearch && matchesStatus
  })

  // Status badge component
  const StatusBadge: React.FC<{ isActive: boolean }> = ({ isActive }) => {
    return isActive ? (
      <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium text-white bg-green-500">
        <Check className="w-3 h-3 mr-1" />
        Active
      </span>
    ) : (
      <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium text-white bg-gray-500">
        <X className="w-3 h-3 mr-1" />
        Inactive
      </span>
    )
  }

  // Form handlers
  const handleCreateSchool = () => {
    setFormData({
      code: '',
      name: '',
      description: '',
      contact_email: '',
      contact_phone: '',
      is_active: true,
      sort_order: schools.length + 1
    })
    setIsCreateModalOpen(true)
  }

  const handleEditSchool = (school: School) => {
    setSelectedSchool(school)
    setFormData({
      code: school.code,
      name: school.name,
      description: school.description || '',
      contact_email: school.contact_email || '',
      contact_phone: school.contact_phone || '',
      is_active: school.is_active,
      sort_order: school.sort_order
    })
    setIsEditModalOpen(true)
  }

  const handleViewSchool = (school: School) => {
    setSelectedSchool(school)
    setIsViewModalOpen(true)
  }

  const handleDeleteSchool = (school: School) => {
    if (school.programs_count > 0 || school.units_count > 0) {
      toast.error(`Cannot delete school with ${school.programs_count} programs and ${school.units_count} units`)
      return
    }

    if (confirm(`Are you sure you want to delete "${school.name}"? This action cannot be undone.`)) {
      setLoading(true)
      router.delete(route('admin.schools.destroy', school.id), {
        onSuccess: () => {
          toast.success('School deleted successfully!')
        },
        onError: (errors) => {
          toast.error(errors.error || 'Failed to delete school')
        },
        onFinish: () => setLoading(false)
      })
    }
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    setLoading(true)

    const url = selectedSchool 
      ? route('admin.schools.update', selectedSchool.id)
      : route('admin.schools.store')
    
    const method = selectedSchool ? 'put' : 'post'

    router[method](url, formData, {
      onSuccess: () => {
        toast.success(`School ${selectedSchool ? 'updated' : 'created'} successfully!`)
        setIsCreateModalOpen(false)
        setIsEditModalOpen(false)
        setSelectedSchool(null)
      },
      onError: (errors) => {
        toast.error(errors.error || `Failed to ${selectedSchool ? 'update' : 'create'} school`)
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
    
    router.get(`${route('admin.schools.index')}?${params.toString()}`)
  }

  const toggleRowExpansion = (schoolId: number) => {
    const newExpanded = new Set(expandedRows)
    if (newExpanded.has(schoolId)) {
      newExpanded.delete(schoolId)
    } else {
      newExpanded.add(schoolId)
    }
    setExpandedRows(newExpanded)
  }

  return (
    <AuthenticatedLayout>
      <Head title="Schools Management" />
      
      <div className="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-cyan-50 py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          
          {/* Header */}
          <div className="mb-8">
            <div className="bg-white/80 backdrop-blur-sm rounded-3xl shadow-2xl border border-slate-200/50 p-8">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <h1 className="text-4xl font-bold bg-gradient-to-r from-slate-800 via-blue-800 to-indigo-800 bg-clip-text text-transparent mb-2">
                    Schools Management
                  </h1>
                  <p className="text-slate-600 text-lg">
                    Manage academic schools, departments, and organizational structure
                  </p>
                  <div className="flex items-center gap-4 mt-4">
                    <div className="text-sm text-slate-600">
                      Total: <span className="font-semibold">{schools.length}</span>
                    </div>
                    <div className="text-sm text-slate-600">
                      Active: <span className="font-semibold">{schools.filter(s => s.is_active).length}</span>
                    </div>
                    <div className="text-sm text-slate-600">
                      Total Programs: <span className="font-semibold">{schools.reduce((sum, s) => sum + s.programs_count, 0)}</span>
                    </div>
                  </div>
                </div>
                {can.create && (
                  <button
                    onClick={handleCreateSchool}
                    className="inline-flex items-center px-6 py-3 bg-gradient-to-r from-emerald-500 via-emerald-600 to-teal-600 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl hover:from-emerald-600 hover:via-emerald-700 hover:to-teal-700 transform hover:scale-105 hover:-translate-y-0.5 transition-all duration-300 group"
                  >
                    <Plus className="w-5 h-5 mr-2 group-hover:rotate-12 transition-transform duration-300" />
                    Create School
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
                    placeholder="Search schools..."
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
                  <option value="sort_order-asc">Sort Order</option>
                  <option value="name-asc">Name A-Z</option>
                  <option value="name-desc">Name Z-A</option>
                  <option value="code-asc">Code A-Z</option>
                  <option value="code-desc">Code Z-A</option>
                  <option value="created_at-desc">Newest First</option>
                  <option value="created_at-asc">Oldest First</option>
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

          {/* Schools Table */}
          <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-2xl border border-slate-200/50 overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-slate-50">
                  <tr>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      School
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Contact
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Status
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Statistics
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200">
                  {filteredSchools.map((school, index) => (
                    <React.Fragment key={school.id}>
                      <tr className={`hover:bg-slate-50 transition-colors duration-150 ${
                        index % 2 === 0 ? "bg-white" : "bg-slate-50/50"
                      }`}>
                        <td className="px-6 py-4">
                          <div className="flex items-center">
                            <button
                              onClick={() => toggleRowExpansion(school.id)}
                              className="mr-3 p-1 hover:bg-gray-200 rounded"
                            >
                              {expandedRows.has(school.id) ? (
                                <ChevronUp className="w-4 h-4" />
                              ) : (
                                <ChevronDown className="w-4 h-4" />
                              )}
                            </button>
                            <Building2 className="w-8 h-8 text-blue-500 mr-3" />
                            <div>
                              <div className="text-sm font-medium text-slate-900">{school.name}</div>
                              <div className="text-xs text-blue-600 font-semibold">{school.code}</div>
                              {school.description && (
                                <div className="text-xs text-slate-500 mt-1 max-w-xs truncate">
                                  {school.description}
                                </div>
                              )}
                            </div>
                          </div>
                        </td>
                        <td className="px-6 py-4 text-sm text-slate-700">
                          <div className="space-y-1">
                            {school.contact_email && (
                              <div className="flex items-center">
                                <Mail className="w-4 h-4 text-gray-400 mr-2" />
                                <span className="text-sm">{school.contact_email}</span>
                              </div>
                            )}
                            {school.contact_phone && (
                              <div className="flex items-center">
                                <Phone className="w-4 h-4 text-gray-400 mr-2" />
                                <span className="text-sm">{school.contact_phone}</span>
                              </div>
                            )}
                            {!school.contact_email && !school.contact_phone && (
                              <span className="text-gray-400">No contact info</span>
                            )}
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <StatusBadge isActive={school.is_active} />
                        </td>
                        <td className="px-6 py-4 text-sm text-slate-700">
                          <div className="flex gap-4">
                            <div className="flex items-center">
                              <BookOpen className="w-4 h-4 mr-1 text-blue-500" />
                              <span className="font-medium">{school.programs_count}</span>
                              <span className="text-xs text-gray-500 ml-1">programs</span>
                            </div>
                            <div className="flex items-center">
                              <Users className="w-4 h-4 mr-1 text-green-500" />
                              <span className="font-medium">{school.units_count}</span>
                              <span className="text-xs text-gray-500 ml-1">units</span>
                            </div>
                          </div>
                        </td>
                        <td className="px-6 py-4 text-sm font-medium">
                          <div className="flex items-center space-x-2">
                            <button
                              onClick={() => handleViewSchool(school)}
                              className="text-blue-600 hover:text-blue-900 transition-colors p-1 rounded hover:bg-blue-50"
                              title="View details"
                            >
                              <Eye className="w-4 h-4" />
                            </button>
                            {can.update && (
                              <button
                                onClick={() => handleEditSchool(school)}
                                className="text-indigo-600 hover:text-indigo-900 transition-colors p-1 rounded hover:bg-indigo-50"
                                title="Edit school"
                              >
                                <Edit className="w-4 h-4" />
                              </button>
                            )}
                            {can.delete && (
                              <button
                                onClick={() => handleDeleteSchool(school)}
                                className="text-red-600 hover:text-red-900 transition-colors p-1 rounded hover:bg-red-50"
                                title="Delete school"
                                disabled={school.programs_count > 0 || school.units_count > 0}
                              >
                                <Trash2 className="w-4 h-4" />
                              </button>
                            )}
                          </div>
                        </td>
                      </tr>
                      
                      {/* Expanded row content */}
                      {expandedRows.has(school.id) && (
                        <tr>
                          <td colSpan={5} className="px-6 py-4 bg-gray-50">
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                              <div>
                                <h4 className="font-medium text-gray-900 mb-3">School Details</h4>
                                <div className="space-y-2 text-sm">
                                  <div>
                                    <span className="font-medium">Full Name:</span>
                                    <div className="text-gray-600">{school.full_name || school.name}</div>
                                  </div>
                                  <div>
                                    <span className="font-medium">Sort Order:</span>
                                    <div className="text-gray-600">{school.sort_order}</div>
                                  </div>
                                  <div>
                                    <span className="font-medium">Created:</span>
                                    <div className="text-gray-600">{new Date(school.created_at).toLocaleDateString()}</div>
                                  </div>
                                </div>
                              </div>
                              
                              <div>
                                <h4 className="font-medium text-gray-900 mb-3">Contact Information</h4>
                                <div className="space-y-2 text-sm">
                                  <div>
                                    <span className="font-medium">Email:</span>
                                    <div className="text-gray-600">{school.contact_email || 'Not set'}</div>
                                  </div>
                                  <div>
                                    <span className="font-medium">Phone:</span>
                                    <div className="text-gray-600">{school.contact_phone || 'Not set'}</div>
                                  </div>
                                </div>
                              </div>

                              <div>
                                <h4 className="font-medium text-gray-900 mb-3">Statistics</h4>
                                <div className="space-y-2 text-sm">
                                  <div className="flex justify-between">
                                    <span>Programs:</span>
                                    <span className="font-medium">{school.programs_count}</span>
                                  </div>
                                  <div className="flex justify-between">
                                    <span>Units:</span>
                                    <span className="font-medium">{school.units_count}</span>
                                  </div>
                                  {school.description && (
                                    <div>
                                      <span className="font-medium">Description:</span>
                                      <div className="text-gray-600 mt-1">{school.description}</div>
                                    </div>
                                  )}
                                </div>
                              </div>
                            </div>
                          </td>
                        </tr>
                      )}
                    </React.Fragment>
                  ))}
                </tbody>
              </table>
              
              {filteredSchools.length === 0 && (
                <div className="text-center py-12">
                  <Building2 className="mx-auto h-12 w-12 text-gray-400" />
                  <h3 className="mt-2 text-sm font-medium text-gray-900">No schools found</h3>
                  <p className="mt-1 text-sm text-gray-500">
                    {searchTerm || statusFilter !== 'all' 
                      ? 'Try adjusting your filters'
                      : 'Get started by creating a new school'
                    }
                  </p>
                  {can.create && !searchTerm && statusFilter === 'all' && (
                    <button
                      onClick={handleCreateSchool}
                      className="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                    >
                      <Plus className="w-4 h-4 mr-2" />
                      Create School
                    </button>
                  )}
                </div>
              )}
            </div>
          </div>

          {/* Create/Edit Modal */}
          {(isCreateModalOpen || isEditModalOpen) && (
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
              <div className="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div className="bg-gradient-to-r from-emerald-500 via-emerald-600 to-teal-600 p-6 rounded-t-2xl">
                  <h3 className="text-xl font-semibold text-white">
                    {selectedSchool ? 'Edit School' : 'Create New School'}
                  </h3>
                </div>

                <form onSubmit={handleSubmit} className="p-6 space-y-4">
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        School Code *
                      </label>
                      <input
                        type="text"
                        value={formData.code}
                        onChange={(e) => setFormData(prev => ({ ...prev, code: e.target.value.toUpperCase() }))}
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        placeholder="e.g., SCES, SBS"
                        maxLength={10}
                        required
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Sort Order
                      </label>
                      <input
                        type="number"
                        value={formData.sort_order}
                        onChange={(e) => setFormData(prev => ({ ...prev, sort_order: parseInt(e.target.value) || 0 }))}
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        min={0}
                      />
                    </div>
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      School Name *
                    </label>
                    <input
                      type="text"
                      value={formData.name}
                      onChange={(e) => setFormData(prev => ({ ...prev, name: e.target.value }))}
                      className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                      placeholder="e.g., School of Computing and Engineering Sciences"
                      required
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Description
                    </label>
                    <textarea
                      value={formData.description}
                      onChange={(e) => setFormData(prev => ({ ...prev, description: e.target.value }))}
                      className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                      rows={3}
                      placeholder="Brief description of the school..."
                    />
                  </div>

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Contact Email
                      </label>
                      <input
                        type="email"
                        value={formData.contact_email}
                        onChange={(e) => setFormData(prev => ({ ...prev, contact_email: e.target.value }))}
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        placeholder="contact@school.edu"
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Contact Phone
                      </label>
                      <input
                        type="tel"
                        value={formData.contact_phone}
                        onChange={(e) => setFormData(prev => ({ ...prev, contact_phone: e.target.value }))}
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        placeholder="+1 (555) 123-4567"
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
                      School is active
                    </label>
                  </div>

                  <div className="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200">
                    <button
                      type="button"
                      onClick={() => {
                        setIsCreateModalOpen(false)
                        setIsEditModalOpen(false)
                        setSelectedSchool(null)
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
                      {loading ? 'Processing...' : selectedSchool ? 'Update School' : 'Create School'}
                    </button>
                  </div>
                </form>
              </div>
            </div>
          )}

          {/* View Modal */}
          {isViewModalOpen && selectedSchool && (
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
              <div className="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div className="bg-gradient-to-r from-slate-500 via-slate-600 to-gray-600 p-6 rounded-t-2xl">
                  <div className="flex items-center justify-between">
                    <h3 className="text-xl font-semibold text-white">
                      School Details
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
                          <label className="block text-sm font-medium text-gray-700">School Name</label>
                          <div className="mt-1 text-sm text-gray-900">{selectedSchool.name}</div>
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700">School Code</label>
                          <div className="mt-1 text-sm font-semibold text-blue-600">{selectedSchool.code}</div>
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700">Status</label>
                          <div className="mt-1">
                            <StatusBadge isActive={selectedSchool.is_active} />
                          </div>
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700">Sort Order</label>
                          <div className="mt-1 text-sm text-gray-900">{selectedSchool.sort_order}</div>
                        </div>
                      </div>
                    </div>

                    <div>
                      <h4 className="font-semibold text-gray-900 mb-3">Contact Information</h4>
                      <div className="space-y-3">
                        <div>
                          <label className="block text-sm font-medium text-gray-700">Email</label>
                          <div className="mt-1 text-sm text-gray-900">
                            {selectedSchool.contact_email ? (
                              <div className="flex items-center">
                                <Mail className="w-4 h-4 text-gray-400 mr-2" />
                                {selectedSchool.contact_email}
                              </div>
                            ) : (
                              <span className="text-gray-400">Not set</span>
                            )}
                          </div>
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700">Phone</label>
                          <div className="mt-1 text-sm text-gray-900">
                            {selectedSchool.contact_phone ? (
                              <div className="flex items-center">
                                <Phone className="w-4 h-4 text-gray-400 mr-2" />
                                {selectedSchool.contact_phone}
                              </div>
                            ) : (
                              <span className="text-gray-400">Not set</span>
                            )}
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  {selectedSchool.description && (
                    <div>
                      <h4 className="font-semibold text-gray-900 mb-3">Description</h4>
                      <div className="text-sm text-gray-700 bg-gray-50 p-4 rounded-lg">
                        {selectedSchool.description}
                      </div>
                    </div>
                  )}

                  <div>
                    <h4 className="font-semibold text-gray-900 mb-3">Statistics Overview</h4>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div className="bg-blue-50 p-4 rounded-lg">
                        <div className="flex items-center">
                          <BookOpen className="w-8 h-8 text-blue-500" />
                          <div className="ml-3">
                            <div className="text-2xl font-bold text-blue-600">{selectedSchool.programs_count}</div>
                            <div className="text-sm text-blue-500">Programs</div>
                          </div>
                        </div>
                      </div>
                      <div className="bg-green-50 p-4 rounded-lg">
                        <div className="flex items-center">
                          <Users className="w-8 h-8 text-green-500" />
                          <div className="ml-3">
                            <div className="text-2xl font-bold text-green-600">{selectedSchool.units_count}</div>
                            <div className="text-sm text-green-500">Units</div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div>
                    <h4 className="font-semibold text-gray-900 mb-3">Timestamps</h4>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div>
                        <label className="block text-sm font-medium text-gray-700">Created</label>
                        <div className="mt-1 text-sm text-gray-900">
                          {new Date(selectedSchool.created_at).toLocaleString()}
                        </div>
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700">Last Updated</label>
                        <div className="mt-1 text-sm text-gray-900">
                          {new Date(selectedSchool.updated_at).toLocaleString()}
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
                          handleEditSchool(selectedSchool)
                        }}
                        className="px-6 py-3 bg-gradient-to-r from-indigo-500 to-indigo-600 text-white rounded-lg hover:from-indigo-600 hover:to-indigo-700 transition-all duration-200 font-medium"
                      >
                        Edit School
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

export default SchoolsManagement