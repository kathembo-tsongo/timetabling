"use client"

import React, { useState, useEffect } from "react"
import { Head, usePage, router } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { toast } from "react-hot-toast"
import {
  BookOpen,
  Plus,
  Search,
  Filter,
  Edit,
  Trash2,
  Eye,
  ArrowLeft,
  Building2,
  Award,
  Clock,
  X,
  Check,
  ChevronLeft,
  ChevronRight,
  ChevronsLeft,
  ChevronsRight,
  AlertTriangle,
  Info
} from "lucide-react"
import { route } from 'ziggy-js'

// Interfaces
interface Unit {
  id: number
  code: string
  name: string
  credit_hours: number
  is_active: boolean
  school: {
    id: number
    name: string
    code: string
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

interface PaginationLink {
  url: string | null
  label: string
  active: boolean
}

interface PageProps {
  units: {
    data: Unit[]
    links: PaginationLink[]
    meta: {
      current_page: number
      from: number
      last_page: number
      per_page: number
      to: number
      total: number
    }
  }
  program: Program
  schoolCode: string
  filters: {
    search?: string
    per_page?: number
  }
  stats: {  // ✅ ADD THIS
    total: number
    active: number
    inactive: number
    total_credits: number
    assigned_to_semester: number
    unassigned: number
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
interface UnitFormData {
  code: string
  name: string
  credit_hours: number
  is_active: boolean
}

const ProgramUnitsIndex: React.FC = () => {
  const { 
  units, 
  program, 
  schoolCode, 
  filters,
  stats,  
  can = { create: false, update: false, delete: false }, 
  flash, 
  errors 
} = usePage<PageProps>().props

  // State management
  const [searchTerm, setSearchTerm] = useState(filters.search || '')
  const [perPage, setPerPage] = useState(filters.per_page || 10)
  const [loading, setLoading] = useState(false)
  
  // Modal states
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false)
  const [isEditModalOpen, setIsEditModalOpen] = useState(false)
  const [isViewModalOpen, setIsViewModalOpen] = useState(false)
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false)
  const [selectedUnit, setSelectedUnit] = useState<Unit | null>(null)
  
  // Form state
  const [formData, setFormData] = useState<UnitFormData>({
    code: '',
    name: '',
    credit_hours: 1,
    is_active: true
  })

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
    params.set('per_page', perPage.toString())
    
    const indexRoute = route(`schools.${schoolCode.toLowerCase()}.programs.units.index`, program.id)
    router.get(`${indexRoute}?${params.toString()}`)
  }

  const handlePaginationClick = (url: string | null) => {
    if (!url) return
    
    const params = new URLSearchParams()
    if (searchTerm) params.set('search', searchTerm)
    params.set('per_page', perPage.toString())
    
    const finalUrl = url.includes('?') 
      ? `${url}&${params.toString()}`
      : `${url}?${params.toString()}`
    
    router.get(finalUrl)
  }

  // Form handlers
  const handleCreateUnit = () => {
    setFormData({
      code: '',
      name: '',
      credit_hours: 1,
      is_active: true
    })
    setSelectedUnit(null)
    setIsCreateModalOpen(true)
  }

  const handleEditUnit = (unit: Unit) => {
    setSelectedUnit(unit)
    setFormData({
      code: unit.code,
      name: unit.name,
      credit_hours: unit.credit_hours,
      is_active: unit.is_active
    })
    setIsEditModalOpen(true)
  }

  const handleViewUnit = (unit: Unit) => {
    setSelectedUnit(unit)
    setIsViewModalOpen(true)
  }

  const handleDeleteClick = (unit: Unit) => {
    setSelectedUnit(unit)
    setIsDeleteModalOpen(true)
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    setLoading(true)

    const baseRoute = `schools.${schoolCode.toLowerCase()}.programs.units`
    
    const url = selectedUnit 
      ? route(`${baseRoute}.update`, [program.id, selectedUnit.id])
      : route(`${baseRoute}.store`, program.id)

    const method = selectedUnit ? "put" : "post"

    router[method](url, formData, {
      onSuccess: () => {
        toast.success(`Unit ${selectedUnit ? "updated" : "created"} successfully!`)
        setIsCreateModalOpen(false)
        setIsEditModalOpen(false)
        setSelectedUnit(null)
      },
      onError: (errors) => {
        toast.error(errors.error || `Failed to ${selectedUnit ? "update" : "create"} unit`)
      },
      onFinish: () => setLoading(false)
    })
  }

  const handleDelete = () => {
    if (!selectedUnit) return
    
    setLoading(true)
    const deleteRoute = route(`schools.${schoolCode.toLowerCase()}.programs.units.destroy`, [program.id, selectedUnit.id])
    
    router.delete(deleteRoute, {
      onSuccess: () => {
        toast.success('Unit deleted successfully!')
        setIsDeleteModalOpen(false)
        setSelectedUnit(null)
      },
      onError: (errors) => {
        toast.error(errors.error || 'Failed to delete unit')
      },
      onFinish: () => setLoading(false)
    })
  }

  const closeModals = () => {
    setIsCreateModalOpen(false)
    setIsEditModalOpen(false)
    setIsViewModalOpen(false)
    setIsDeleteModalOpen(false)
    setSelectedUnit(null)
  }

  const backToProgramsRoute = route(`schools.${schoolCode.toLowerCase()}.programs.index`)

  // Helper function to generate suggested code
  const getSuggestedCode = () => {
    const programPrefix = program.code.substring(0, 4)
    return `${programPrefix}XXX`
  }

  return (
    <AuthenticatedLayout>
      <Head title={`${program.code} Units - ${program.school.name}`} />
      
      <div className="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-cyan-50 py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          
          {/* Context Breadcrumb */}
          <div className="bg-white/95 backdrop-blur-sm rounded-xl shadow-sm border border-slate-200/50 p-4 mb-6">
            <div className="flex items-center text-sm text-slate-600">
              <span className="text-slate-700 font-medium">{program.school.code}</span>
              <ChevronRight className="w-4 h-4 mx-2" />
              <a href={backToProgramsRoute} className="hover:text-blue-600 transition-colors">
                Programs
              </a>
              <ChevronRight className="w-4 h-4 mx-2" />
              <span className="font-semibold text-slate-900">{program.code} - Units</span>
            </div>
          </div>

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
                  <h2 className="text-2xl font-semibold text-slate-700 mb-2">Units Management</h2>
                  <p className="text-slate-600 text-lg">
                    Manage academic units and course modules for this program
                  </p>
                  <div className="flex items-center gap-4 mt-4">
  <div className="text-sm text-slate-600">
    Total Units: <span className="font-semibold">{stats?.total || 0}</span>
  </div>
  <div className="text-sm text-slate-600">
    Active: <span className="font-semibold">{stats?.active || 0}</span>
  </div>
  <div className="text-sm text-slate-600">
    Total Credits: <span className="font-semibold">{stats?.total_credits || 0}</span>
  </div>
</div>
                </div>
                <div className="mt-6 sm:mt-0 flex-shrink-0 flex items-center justify-end">
                  {can.create && (
                    <button
                      onClick={handleCreateUnit}
                      className="inline-flex items-center px-6 py-3 bg-gradient-to-r from-emerald-500 via-emerald-600 to-teal-600 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl hover:from-emerald-600 hover:via-emerald-700 hover:to-teal-700 transform hover:scale-105 hover:-translate-y-0.5 transition-all duration-300 group"
                    >
                      <Plus className="w-5 h-5 mr-2 group-hover:rotate-12 transition-transform duration-300" />
                      Create Unit
                    </button>
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
                    placeholder="Search units..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    onKeyPress={(e) => e.key === 'Enter' && handleFilter()}
                    className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  />
                </div>
              </div>
              <div className="flex gap-4 items-center">
                <select
                  value={perPage}
                  onChange={(e) => setPerPage(Number(e.target.value))}
                  className="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                  <option value={5}>5 per page</option>
                  <option value={10}>10 per page</option>
                  <option value={15}>15 per page</option>
                  <option value={25}>25 per page</option>
                  <option value={50}>50 per page</option>
                  <option value={100}>100 per page</option>
                </select>
                <button
                  onClick={handleFilter}
                  className="flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                >
                  <Filter className="w-5 h-5 mr-2" />
                  Filter
                </button>
              </div>
            </div>
          </div>

          {/* Units Table */}
          <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-2xl border border-slate-200/50 overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-slate-50">
                  <tr>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Unit
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Credit Hours
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
                  {units.data && units.data.map((unit, index) => (
                    <tr key={unit.id} className={`hover:bg-slate-50 transition-colors duration-150 ${
                      index % 2 === 0 ? "bg-white" : "bg-slate-50/50"
                    }`}>
                      <td className="px-6 py-4">
                        <div className="flex items-center">
                          <BookOpen className="w-8 h-8 text-blue-500 mr-3" />
                          <div>
                            <div className="text-sm font-medium text-slate-900">{unit.name}</div>
                            <div className="text-xs text-blue-600 font-semibold">{unit.code}</div>
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="flex items-center">
                          <Award className="w-4 h-4 mr-2 text-yellow-500" />
                          <span className="text-sm font-medium text-slate-900">{unit.credit_hours}</span>
                          <span className="text-xs text-gray-500 ml-1">credits</span>
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        {unit.is_active ? (
                          <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium text-white bg-green-500">
                            <Check className="w-3 h-3 mr-1" />
                            Active
                          </span>
                        ) : (
                          <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium text-white bg-gray-500">
                            <X className="w-3 h-3 mr-1" />
                            Inactive
                          </span>
                        )}
                      </td>
                      <td className="px-6 py-4 text-sm font-medium">
                        <div className="flex items-center space-x-2">
                          <button
                            onClick={() => handleViewUnit(unit)}
                            className="text-blue-600 hover:text-blue-900 transition-colors p-1 rounded hover:bg-blue-50"
                            title="View details"
                          >
                            <Eye className="w-4 h-4" />
                          </button>
                          {can.update && (
                            <button
                              onClick={() => handleEditUnit(unit)}
                              className="text-indigo-600 hover:text-indigo-900 transition-colors p-1 rounded hover:bg-indigo-50"
                              title="Edit unit"
                            >
                              <Edit className="w-4 h-4" />
                            </button>
                          )}
                          {can.delete && (
                            <button
                              onClick={() => handleDeleteClick(unit)}
                              className="text-red-600 hover:text-red-900 transition-colors p-1 rounded hover:bg-red-50"
                              title="Delete unit"
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
              
              {units.data && units.data.length === 0 && (
                <div className="text-center py-12">
                  <BookOpen className="mx-auto h-12 w-12 text-gray-400" />
                  <h3 className="mt-2 text-sm font-medium text-gray-900">No units found</h3>
                  <p className="mt-1 text-sm text-gray-500">
                    Get started by creating a new unit for this program
                  </p>
                  {can.create && (
                    <button
                      onClick={handleCreateUnit}
                      className="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                    >
                      <Plus className="w-4 h-4 mr-2" />
                      Create Unit
                    </button>
                  )}
                </div>
              )}
            </div>

            {/* Pagination */}
            {units.meta && units.meta.last_page && units.meta.last_page > 1 && (
              <div className="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                <div className="flex items-center justify-between">
                  <div className="flex-1 flex justify-between sm:hidden">
                    <button
                      onClick={() => handlePaginationClick(units.links?.find(link => link.label === '&laquo; Previous')?.url)}
                      disabled={units.meta?.current_page === 1}
                      className="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      Previous
                    </button>
                    <button
                      onClick={() => handlePaginationClick(units.links?.find(link => link.label === 'Next &raquo;')?.url)}
                      disabled={units.meta?.current_page === units.meta?.last_page}
                      className="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      Next
                    </button>
                  </div>
                  <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                      <p className="text-sm text-gray-700">
                        Showing <span className="font-medium">{units.meta?.from || 0}</span> to{' '}
                        <span className="font-medium">{units.meta?.to || 0}</span> of{' '}
                        <span className="font-medium">{units.meta?.total || 0}</span> results
                      </p>
                    </div>
                    <div>
                      <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                        <button
                          onClick={() => handlePaginationClick(units.links?.[0]?.url)}
                          disabled={units.meta?.current_page === 1}
                          className="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                          <ChevronsLeft className="h-5 w-5" />
                        </button>
                        
                        <button
                          onClick={() => handlePaginationClick(units.links?.find(link => link.label === '&laquo; Previous')?.url)}
                          disabled={units.meta?.current_page === 1}
                          className="relative inline-flex items-center px-2 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                          <ChevronLeft className="h-5 w-5" />
                        </button>

                        {units.links && units.links.slice(1, -1).map((link, index) => (
                          <button
                            key={index}
                            onClick={() => handlePaginationClick(link.url)}
                            className={`relative inline-flex items-center px-4 py-2 border text-sm font-medium ${
                              link.active
                                ? 'z-10 bg-blue-50 border-blue-500 text-blue-600'
                                : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                            }`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                          />
                        ))}

                        <button
                          onClick={() => handlePaginationClick(units.links?.find(link => link.label === 'Next &raquo;')?.url)}
                          disabled={units.meta?.current_page === units.meta?.last_page}
                          className="relative inline-flex items-center px-2 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                          <ChevronRight className="h-5 w-5" />
                        </button>

                        <button
                          onClick={() => handlePaginationClick(units.links?.[units.links.length - 1]?.url)}
                          disabled={units.meta?.current_page === units.meta?.last_page}
                          className="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                          <ChevronsRight className="h-5 w-5" />
                        </button>
                      </nav>
                    </div>
                  </div>
                </div>
              </div>
            )}
          </div>

          {/* CREATE MODAL - Enhanced with Context */}
          {isCreateModalOpen && (
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
              <div className="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div className="bg-gradient-to-r from-emerald-500 via-emerald-600 to-teal-600 p-6 rounded-t-2xl">
                  <div className="flex items-center justify-between">
                    <div>
                      <h3 className="text-xl font-semibold text-white">
                        Create New Unit
                      </h3>
                      <div className="mt-2 space-y-1">
                        <div className="flex items-center text-emerald-50 text-sm">
                          <Building2 className="w-4 h-4 mr-2" />
                          <span className="font-medium">{program.school.name}</span>
                          <span className="mx-2">•</span>
                          <span className="text-emerald-100">{program.school.code}</span>
                        </div>
                        <div className="flex items-center text-emerald-50 text-sm">
                          <Award className="w-4 h-4 mr-2" />
                          <span className="font-medium">{program.name}</span>
                          <span className="mx-2">•</span>
                          <span className="text-emerald-100">{program.code}</span>
                        </div>
                      </div>
                    </div>
                    <button
                      onClick={closeModals}
                      className="text-white hover:text-gray-200 transition-colors"
                    >
                      <X className="w-6 h-6" />
                    </button>
                  </div>
                </div>

                <form onSubmit={handleSubmit} className="p-6 space-y-4">
                  <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div className="flex items-start">
                      <div className="flex-shrink-0">
                        <Info className="w-5 h-5 text-blue-600 mt-0.5" />
                      </div>
                      <div className="ml-3">
                        <h4 className="text-sm font-medium text-blue-900">
                          Creating unit for:
                        </h4>
                        <p className="mt-1 text-sm text-blue-700">
                          <span className="font-semibold">{program.name}</span>
                          <span className="mx-2">•</span>
                          <span>{program.school.name}</span>
                        </p>
                        <p className="mt-1 text-xs text-blue-600">
                          This unit will be automatically assigned to program: {program.code}
                        </p>
                      </div>
                    </div>
                  </div>

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Unit Code *
                      </label>
                      <input
                        type="text"
                        value={formData.code}
                        onChange={(e) => setFormData(prev => ({ ...prev, code: e.target.value.toUpperCase() }))}
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        placeholder={`e.g., ${getSuggestedCode()}`}
                        maxLength={20}
                        required
                      />
                      <p className="mt-1 text-xs text-gray-500">
                        Suggested format: {getSuggestedCode()} (e.g., {program.code.substring(0, 4)}101)
                      </p>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Credit Hours *
                      </label>
                      <input
                        type="number"
                        value={formData.credit_hours}
                        onChange={(e) => setFormData(prev => ({ ...prev, credit_hours: parseInt(e.target.value) || 1 }))}
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        min={1}
                        max={10}
                        required
                      />
                    </div>
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Unit Name *
                    </label>
                    <input
                      type="text"
                      value={formData.name}
                      onChange={(e) => setFormData(prev => ({ ...prev, name: e.target.value }))}
                      className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                      placeholder="e.g., Introduction to Programming"
                      required
                    />
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
                      Unit is active
                    </label>
                  </div>

                  <div className="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200">
                    <button
                      type="button"
                      onClick={closeModals}
                      className="px-6 py-3 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors font-medium"
                    >
                      Cancel
                    </button>
                    <button
                      type="submit"
                      disabled={loading}
                      className="px-6 py-3 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white rounded-lg hover:from-emerald-600 hover:to-emerald-700 transition-all duration-200 font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      {loading ? 'Processing...' : 'Create Unit'}
                    </button>
                  </div>
                </form>
              </div>
            </div>
          )}

          {/* EDIT MODAL - Enhanced with Context */}
          {isEditModalOpen && selectedUnit && (
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
              <div className="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div className="bg-gradient-to-r from-indigo-500 via-indigo-600 to-purple-600 p-6 rounded-t-2xl">
                  <div className="flex items-center justify-between">
                    <div>
                      <h3 className="text-xl font-semibold text-white">
                        Edit Unit
                      </h3>
                      <div className="mt-2 space-y-1">
                        <div className="flex items-center text-indigo-50 text-sm">
                          <Building2 className="w-4 h-4 mr-2" />
                          <span className="font-medium">{program.school.name}</span>
                          <span className="mx-2">•</span>
                          <span className="text-indigo-100">{program.school.code}</span>
                        </div>
                        <div className="flex items-center text-indigo-50 text-sm">
                          <Award className="w-4 h-4 mr-2" />
                          <span className="font-medium">{program.name}</span>
                          <span className="mx-2">•</span>
                          <span className="text-indigo-100">{program.code}</span>
                        </div>
                      </div>
                    </div>
                    <button
                      onClick={closeModals}
                      className="text-white hover:text-gray-200 transition-colors"
                    >
                      <X className="w-6 h-6" />
                    </button>
                  </div>
                </div>

                <form onSubmit={handleSubmit} className="p-6 space-y-4">
                  <div className="bg-amber-50 border border-amber-200 rounded-lg p-4">
                    <div className="flex items-start">
                      <div className="flex-shrink-0">
                        <Info className="w-5 h-5 text-amber-600 mt-0.5" />
                      </div>
                      <div className="ml-3">
                        <h4 className="text-sm font-medium text-amber-900">
                          Editing unit: {selectedUnit.code}
                        </h4>
                        <p className="mt-1 text-sm text-amber-700">
                          Program: <span className="font-semibold">{program.name}</span>
                          <span className="mx-2">•</span>
                          School: <span className="font-semibold">{program.school.name}</span>
                        </p>
                        <p className="mt-1 text-xs text-amber-600">
                          Changes will affect all references to this unit across the system
                        </p>
                      </div>
                    </div>
                  </div>

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Unit Code *
                      </label>
                      <input
                        type="text"
                        value={formData.code}
                        onChange={(e) => setFormData(prev => ({ ...prev, code: e.target.value.toUpperCase() }))}
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        placeholder={`e.g., ${getSuggestedCode()}`}
                        maxLength={20}
                        required
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Credit Hours *
                      </label>
                      <input
                        type="number"
                        value={formData.credit_hours}
                        onChange={(e) => setFormData(prev => ({ ...prev, credit_hours: parseInt(e.target.value) || 1 }))}
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        min={1}
                        max={10}
                        required
                      />
                    </div>
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Unit Name *
                    </label>
                    <input
                      type="text"
                      value={formData.name}
                      onChange={(e) => setFormData(prev => ({ ...prev, name: e.target.value }))}
                      className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                      placeholder="e.g., Introduction to Programming"
                      required
                    />
                  </div>

                  <div className="flex items-center">
                    <input
                      type="checkbox"
                      id="is_active_edit"
                      checked={formData.is_active}
                      onChange={(e) => setFormData(prev => ({ ...prev, is_active: e.target.checked }))}
                      className="w-4 h-4 text-indigo-600 bg-gray-100 border-gray-300 rounded focus:ring-indigo-500 focus:ring-2"
                    />
                    <label htmlFor="is_active_edit" className="ml-2 text-sm font-medium text-gray-700">
                      Unit is active
                    </label>
                  </div>

                  <div className="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200">
                    <button
                      type="button"
                      onClick={closeModals}
                      className="px-6 py-3 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors font-medium"
                    >
                      Cancel
                    </button>
                    <button
                      type="submit"
                      disabled={loading}
                      className="px-6 py-3 bg-gradient-to-r from-indigo-500 to-indigo-600 text-white rounded-lg hover:from-indigo-600 hover:to-indigo-700 transition-all duration-200 font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      {loading ? 'Updating...' : 'Update Unit'}
                    </button>
                  </div>
                </form>
              </div>
            </div>
          )}

          {/* VIEW MODAL - Enhanced with Full Context */}
          {isViewModalOpen && selectedUnit && (
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
              <div className="bg-white rounded-2xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
                <div className="bg-gradient-to-r from-blue-500 via-blue-600 to-indigo-600 p-6 rounded-t-2xl">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center">
                      <BookOpen className="w-8 h-8 text-white mr-3" />
                      <div>
                        <h3 className="text-xl font-semibold text-white">
                          {selectedUnit.name}
                        </h3>
                        <p className="text-blue-100 text-sm mt-1">
                          Unit Details - {selectedUnit.code}
                        </p>
                      </div>
                    </div>
                    <button
                      onClick={closeModals}
                      className="text-white hover:text-gray-200 transition-colors p-1 rounded-lg hover:bg-white/10"
                    >
                      <X className="w-6 h-6" />
                    </button>
                  </div>
                </div>

                <div className="p-8 space-y-8">
                  {/* Context Banner */}
                  <div className="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-4">
                    <div className="flex items-center justify-between">
                      <div>
                        <p className="text-sm text-blue-600 font-medium">Belongs to:</p>
                        <p className="text-lg font-bold text-blue-900">{program.name} ({program.code})</p>
                        <p className="text-sm text-blue-700">{program.school.name} - {program.school.code}</p>
                      </div>
                      {selectedUnit.is_active ? (
                        <span className="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium text-white bg-green-500">
                          <Check className="w-4 h-4 mr-1" />
                          Active
                        </span>
                      ) : (
                        <span className="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium text-white bg-gray-500">
                          <X className="w-4 h-4 mr-1" />
                          Inactive
                        </span>
                      )}
                    </div>
                  </div>

                  {/* Header Info Card */}
                  <div className="bg-gradient-to-r from-slate-50 to-blue-50 p-6 rounded-xl border border-slate-200">
                    <div className="flex items-center justify-between">
                      <div className="flex items-center">
                        <div className="bg-blue-100 p-3 rounded-lg mr-4">
                          <BookOpen className="w-6 h-6 text-blue-600" />
                        </div>
                        <div>
                          <h4 className="text-xl font-bold text-slate-900">{selectedUnit.name}</h4>
                          <p className="text-blue-600 font-semibold text-lg">{selectedUnit.code}</p>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                    {/* Basic Information */}
                    <div className="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                      <h4 className="font-bold text-slate-900 mb-6 text-lg flex items-center">
                        <Award className="w-5 h-5 mr-2 text-blue-600" />
                        Academic Information
                      </h4>
                      <div className="space-y-4">
                        <div>
                          <label className="block text-sm font-medium text-slate-600 mb-1">Credit Hours</label>
                          <div className="flex items-center">
                            <Award className="w-5 h-5 text-yellow-500 mr-2" />
                            <span className="text-lg font-semibold text-slate-900">{selectedUnit.credit_hours}</span>
                            <span className="text-sm text-slate-500 ml-1">credits</span>
                          </div>
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-slate-600 mb-1">Unit Code</label>
                          <div className="text-lg font-semibold text-blue-600">{selectedUnit.code}</div>
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-slate-600 mb-1">Status</label>
                          <div className="mt-1">
                            {selectedUnit.is_active ? (
                              <span className="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium text-white bg-green-500">
                                <Check className="w-4 h-4 mr-1" />
                                Active
                              </span>
                            ) : (
                              <span className="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium text-white bg-gray-500">
                                <X className="w-4 h-4 mr-1" />
                                Inactive
                              </span>
                            )}
                          </div>
                        </div>
                      </div>
                    </div>

                    {/* Program & School Info */}
                    <div className="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                      <h4 className="font-bold text-slate-900 mb-6 text-lg flex items-center">
                        <Building2 className="w-5 h-5 mr-2 text-indigo-600" />
                        Program & School Details
                      </h4>
                      <div className="space-y-4">
                        <div>
                          <label className="block text-sm font-medium text-slate-600 mb-1">Program</label>
                          <div className="text-lg font-semibold text-slate-900">{selectedUnit.program.name}</div>
                          <div className="text-sm text-indigo-600 font-medium">{selectedUnit.program.code}</div>
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-slate-600 mb-1">School</label>
                          <div className="text-lg font-semibold text-slate-900">{selectedUnit.school.name}</div>
                          <div className="text-sm text-slate-600">{selectedUnit.school.code}</div>
                        </div>
                      </div>
                    </div>
                  </div>

                  {/* Timestamps */}
                  <div className="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                    <h4 className="font-bold text-slate-900 mb-4 text-lg flex items-center">
                      <Clock className="w-5 h-5 mr-2 text-slate-600" />
                      Timeline
                    </h4>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div>
                        <label className="block text-sm font-medium text-slate-600 mb-1">Created</label>
                        <div className="text-sm text-slate-900">
                          {new Date(selectedUnit.created_at).toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                          })}
                        </div>
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-slate-600 mb-1">Last Updated</label>
                        <div className="text-sm text-slate-900">
                          {new Date(selectedUnit.updated_at).toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                          })}
                        </div>
                      </div>
                    </div>
                  </div>

                  <div className="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200">
                    <button
                      onClick={closeModals}
                      className="px-6 py-3 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors font-medium"
                    >
                      Close
                    </button>
                    {can.update && (
                      <button
                        onClick={() => {
                          setIsViewModalOpen(false)
                          handleEditUnit(selectedUnit)
                        }}
                        className="px-6 py-3 bg-gradient-to-r from-indigo-500 to-indigo-600 text-white rounded-lg hover:from-indigo-600 hover:to-indigo-700 transition-all duration-200 font-medium"
                      >
                        Edit Unit
                      </button>
                    )}
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* DELETE CONFIRMATION MODAL - Enhanced with Context */}
          {isDeleteModalOpen && selectedUnit && (
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
              <div className="bg-white rounded-2xl shadow-2xl max-w-lg w-full">
                <div className="bg-gradient-to-r from-red-500 via-red-600 to-rose-600 p-6 rounded-t-2xl">
                  <div className="flex items-center">
                    <div className="flex-shrink-0">
                      <AlertTriangle className="w-8 h-8 text-white" />
                    </div>
                    <div className="ml-3">
                      <h3 className="text-xl font-semibold text-white">
                        Delete Unit
                      </h3>
                      <p className="text-red-100 text-sm mt-1">
                        This action cannot be undone
                      </p>
                    </div>
                  </div>
                </div>

                <div className="p-6 space-y-4">
                  <div className="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div className="flex items-start">
                      <div className="flex-shrink-0">
                        <AlertTriangle className="w-5 h-5 text-red-600 mt-0.5" />
                      </div>
                      <div className="ml-3">
                        <h4 className="text-sm font-medium text-red-900">
                          You are about to delete:
                        </h4>
                        <div className="mt-2 space-y-1">
                          <p className="text-sm text-red-800">
                            <span className="font-semibold">Unit:</span> {selectedUnit.name} ({selectedUnit.code})
                          </p>
                          <p className="text-sm text-red-700">
                            <span className="font-semibold">Program:</span> {program.name} ({program.code})
                          </p>
                          <p className="text-sm text-red-700">
                            <span className="font-semibold">School:</span> {program.school.name}
                          </p>
                        </div>
                        <p className="mt-3 text-xs text-red-600 font-medium">
                          ⚠ This will remove all associations with classes, semesters, and enrollments
                        </p>
                      </div>
                    </div>
                  </div>

                  <div className="bg-gray-50 rounded-lg p-4">
                    <p className="text-sm text-gray-700">
                      Are you absolutely sure you want to delete this unit? This action is permanent and cannot be reversed.
                    </p>
                  </div>

                  <div className="flex items-center justify-end space-x-4 pt-4">
                    <button
                      type="button"
                      onClick={closeModals}
                      className="px-6 py-3 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors font-medium"
                    >
                      Cancel
                    </button>
                    <button
                      onClick={handleDelete}
                      disabled={loading}
                      className="px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-200 font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      {loading ? 'Deleting...' : 'Yes, Delete Unit'}
                    </button>
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

export default ProgramUnitsIndex