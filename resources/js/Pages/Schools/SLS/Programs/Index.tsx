"use client"

import React, { useState, useEffect } from "react"
import { useMemo } from "react"
import { Head, usePage, router } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { toast } from "react-hot-toast"
import {
  Scale,
  Plus,
  Search,
  Filter,
  Edit,
  Trash2,
  Eye,
  ChevronDown,
  ChevronUp,
  BookOpen,
  Users,
  Phone,
  Mail,
  Check,
  X,
  Clock,
  Award,
  Building2,
  Calendar,
  ClipboardList,
  Settings,
  Gavel,
  FileText
} from "lucide-react"
import { route } from 'ziggy-js';

// Interfaces
interface Program {
  id: number
  code: string
  name: string
  full_name: string
  degree_type: string
  duration_years: number
  description?: string
  is_active: boolean
  contact_email?: string
  contact_phone?: string
  sort_order: number
  school_name: string
  units_count: number
  enrollments_count: number
  created_at: string
  updated_at: string
  routes?: {
    units: string
    classes: string
    enrollments: string
    class_timetables: string
    exam_timetables: string
    unit_assignment: string
  }
}

interface School {
  id: number
  name: string
  code: string
}

interface PageProps {
  programs: Program[]
  school: School
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
  auth?: {
    user?: {
      roles?: string[]
      permissions?: string[]
    }
  }
}

interface ProgramFormData {
  code: string
  name: string
  degree_type: string
  duration_years: number
  description: string
  contact_email: string
  contact_phone: string
  is_active: boolean
  sort_order: number
}

const SLSProgramsManagement: React.FC = () => {
  
  const { programs, school, filters, can = { create: false, update: false, delete: false }, flash, errors, auth } = usePage<PageProps>().props

  // Role and permission checks
  const user = auth?.user
  const roles = user?.roles || []
  const permissions = user?.permissions || []
  
  const canPermission = (perm: string) => permissions.includes(perm)
  const isRole = (role: string) => roles.includes(role)
  const isClassTimetableOffice = isRole('Class Timetable office')
  const isExamTimetableOffice = isRole('Exam Office')

  // State management
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false)
  const [isEditModalOpen, setIsEditModalOpen] = useState(false)
  const [isViewModalOpen, setIsViewModalOpen] = useState(false)
  const [selectedProgram, setSelectedProgram] = useState<Program | null>(null)
  const [expandedRows, setExpandedRows] = useState<Set<number>>(new Set())
  const [loading, setLoading] = useState(false)

  // Form state
  const [formData, setFormData] = useState<ProgramFormData>({
    code: '',
    name: '',
    degree_type: 'Bachelor',
    duration_years: 4,
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
  const [degreeFilter, setDegreeFilter] = useState<string>('all')
  const [sortField, setSortField] = useState(filters.sort_field || 'sort_order')
  const [sortDirection, setSortDirection] = useState(filters.sort_direction || 'asc')

  // 🔥 SLS-SPECIFIC: Law degree types
  const degreeTypes = [
    { value: 'Certificate', label: 'Certificate in Law' },
    { value: 'Diploma', label: 'Diploma in Law' },
    { value: 'Bachelor', label: "Bachelor of Laws (LLB)" },
    { value: 'Master', label: "Master of Laws (LLM)" },
    { value: 'PhD', label: 'Doctor of Philosophy in Law' }
  ]

  // Error handling
  useEffect(() => {
    if (errors?.error) {
      toast.error(errors.error)
    }
    if (flash?.success) {
      toast.success(flash.success)
    }
  }, [errors, flash])

  // Filtered and sorted programs
  const filteredPrograms = programs.filter(program => {
    const matchesSearch = program.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      program.code.toLowerCase().includes(searchTerm.toLowerCase()) ||
      program.degree_type.toLowerCase().includes(searchTerm.toLowerCase())
    const matchesStatus = statusFilter === 'all' || 
      (statusFilter === 'active' && program.is_active) ||
      (statusFilter === 'inactive' && !program.is_active)
    const matchesDegree = degreeFilter === 'all' || program.degree_type === degreeFilter
    
    return matchesSearch && matchesStatus && matchesDegree
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

  // 🔥 SLS-SPECIFIC: Law degree badge with Gavel icon
  const DegreeBadge: React.FC<{ degreeType: string }> = ({ degreeType }) => {
    const getBadgeColor = (type: string) => {
      switch (type) {
        case 'Certificate': return 'bg-blue-400'
        case 'Diploma': return 'bg-blue-500'
        case 'Bachelor': return 'bg-indigo-600'
        case 'Master': return 'bg-indigo-700'
        case 'PhD': return 'bg-indigo-900'
        default: return 'bg-gray-500'
      }
    }

    return (
      <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium text-white ${getBadgeColor(degreeType)}`}>
        <Gavel className="w-3 h-3 mr-1" />
        {degreeType}
      </span>
    )
  }

  // Form handlers
  const handleCreateProgram = () => {
    setFormData({
      code: '',
      name: '',
      degree_type: 'Bachelor',
      duration_years: 4,
      description: '',
      contact_email: '',
      contact_phone: '',
      is_active: true,
      sort_order: programs.length + 1
    })
    setIsCreateModalOpen(true)
  }

  const handleEditProgram = (program: Program) => {
    setSelectedProgram(program)
    setFormData({
      code: program.code,
      name: program.name,
      degree_type: program.degree_type,
      duration_years: program.duration_years,
      description: program.description || '',
      contact_email: program.contact_email || '',
      contact_phone: program.contact_phone || '',
      is_active: program.is_active,
      sort_order: program.sort_order
    })
    setIsEditModalOpen(true)
  }

  const handleViewProgram = (program: Program) => {
    setSelectedProgram(program)
    setIsViewModalOpen(true)
  }

  const handleDeleteProgram = (program: Program) => {
    if (program.units_count > 0 || program.enrollments_count > 0) {
      toast.error(`Cannot delete program with ${program.units_count} units and ${program.enrollments_count} enrollments`)
      return
    }

    if (confirm(`Are you sure you want to delete "${program.name}"? This action cannot be undone.`)) {
      setLoading(true)
      // 🔥 FIXED: Using SLS route
      router.delete(route('schools.sls.programs.destroy', program.id), {
        onSuccess: () => {
          toast.success('Program deleted successfully!')
        },
        onError: (errors) => {
          toast.error(errors.error || 'Failed to delete program')
        },
        onFinish: () => setLoading(false)
      })
    }
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    setLoading(true)

    // 🔥 FIXED: Using SLS routes
    const url = selectedProgram 
      ? route("schools.sls.programs.update", { program: selectedProgram.id })
      : route("schools.sls.programs.store") 

    const method = selectedProgram ? "put" : "post"

    router[method](url, formData, {
      onSuccess: () => {
        toast.success(`Program ${selectedProgram ? "updated" : "created"} successfully!`)
        setIsCreateModalOpen(false)
        setIsEditModalOpen(false)
        setSelectedProgram(null)
      },
      onError: (errors) => {
        toast.error(errors.error || `Failed to ${selectedProgram ? "update" : "create"} program`)
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
    
    // 🔥 FIXED: Using SLS route
    router.get(`${route('schools.sls.programs.index')}?${params.toString()}`)
  }

  const toggleRowExpansion = (programId: number) => {
    const newExpanded = new Set(expandedRows)
    if (newExpanded.has(programId)) {
      newExpanded.delete(programId)
    } else {
      newExpanded.add(programId)
    }
    setExpandedRows(newExpanded)
  }

  const getDurationDisplay = (years: number) => {
    if (years === 1) return '1 year'
    if (years < 1) return `${years * 12} months`
    return `${years} years`
  }

  return (
    <AuthenticatedLayout>
      <Head title={`${school.code} Programs Management`} />
      
      {/* 🔥 SLS BRANDING: Navy/Indigo gradient for law school */}
      <div className="min-h-screen bg-gradient-to-br from-slate-50 via-white to-indigo-50 py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          
          {/* Header */}
          <div className="mb-8">
            <div className="bg-white/80 backdrop-blur-sm rounded-3xl shadow-2xl border border-slate-200/50 p-8">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <div className="flex items-center mb-2">
                    {/* 🔥 SLS ICON: Scale for justice */}
                    <Scale className="w-8 h-8 text-indigo-700 mr-3" />
                    <h1 className="text-4xl font-bold bg-gradient-to-r from-slate-800 via-indigo-800 to-blue-900 bg-clip-text text-transparent">
                      {school.name}
                    </h1>
                  </div>
                  <h2 className="text-2xl font-semibold text-slate-700 mb-2">Law Programs</h2>
                  <p className="text-slate-600 text-lg">
                    Manage law, legal studies, and jurisprudence programs
                  </p>
                  <div className="flex items-center gap-4 mt-4">
                    <div className="text-sm text-slate-600">
                      Total: <span className="font-semibold">{programs.length}</span>
                    </div>
                    <div className="text-sm text-slate-600">
                      Active: <span className="font-semibold">{programs.filter(p => p.is_active).length}</span>
                    </div>
                    <div className="text-sm text-slate-600">
                      Total Units: <span className="font-semibold">{programs.reduce((sum, p) => sum + p.units_count, 0)}</span>
                    </div>
                    <div className="text-sm text-slate-600">
                      Total Enrollments: <span className="font-semibold">{programs.reduce((sum, p) => sum + p.enrollments_count, 0)}</span>
                    </div>
                  </div>
                </div>
                <div className="mt-6 sm:mt-0 flex-shrink-0 flex items-center justify-end">
                  {can.create && !isClassTimetableOffice && (
                    <button
                      onClick={handleCreateProgram}
                      // 🔥 SLS COLORS: Navy/Indigo/Blue gradient
                      className="inline-flex items-center px-6 py-3 bg-gradient-to-r from-slate-700 via-indigo-700 to-blue-800 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl hover:from-slate-800 hover:via-indigo-800 hover:to-blue-900 transform hover:scale-105 hover:-translate-y-0.5 transition-all duration-300 group"
                    >
                      <Plus className="w-5 h-5 mr-2 group-hover:rotate-12 transition-transform duration-300" />
                      Create Program
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
                    placeholder="Search law programs..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  />
                </div>
              </div>
              <div className="flex gap-4">
                <select
                  value={statusFilter}
                  onChange={(e) => setStatusFilter(e.target.value)}
                  className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                >
                  <option value="all">All Status</option>
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
                <select
                  value={degreeFilter}
                  onChange={(e) => setDegreeFilter(e.target.value)}
                  className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                >
                  <option value="all">All Degrees</option>
                  {degreeTypes.map((degree) => (
                    <option key={degree.value} value={degree.value}>
                      {degree.label}
                    </option>
                  ))}
                </select>
                <button
                  onClick={handleFilter}
                  className="px-4 py-2 bg-indigo-700 text-white rounded-lg hover:bg-indigo-800 transition-colors"
                >
                  <Filter className="w-5 h-5" />
                </button>
              </div>
            </div>
          </div>

          {/* Programs Table */}
          <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-2xl border border-slate-200/50 overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-slate-50">
                  <tr>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Program
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Degree & Duration
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
                  {filteredPrograms.map((program, index) => (
                    <React.Fragment key={program.id}>
                      <tr className={`hover:bg-slate-50 transition-colors duration-150 ${
                        index % 2 === 0 ? "bg-white" : "bg-slate-50/50"
                      }`}>
                        <td className="px-6 py-4">
                          <div className="flex items-center">
                            <button
                              onClick={() => toggleRowExpansion(program.id)}
                              className="mr-3 p-1 hover:bg-gray-200 rounded"
                            >
                              {expandedRows.has(program.id) ? (
                                <ChevronUp className="w-4 h-4" />
                              ) : (
                                <ChevronDown className="w-4 h-4" />
                              )}
                            </button>
                            {/* 🔥 SLS ICON: FileText for legal documents */}
                            <FileText className="w-8 h-8 text-indigo-600 mr-3" />
                            <div>
                              <div className="text-sm font-medium text-slate-900">{program.name}</div>
                              <div className="text-xs text-indigo-700 font-semibold">{program.code}</div>
                              {program.description && (
                                <div className="text-xs text-slate-500 mt-1 max-w-xs truncate">
                                  {program.description}
                                </div>
                              )}
                            </div>
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <div className="space-y-2">
                            <DegreeBadge degreeType={program.degree_type} />
                            <div className="flex items-center text-sm text-slate-600">
                              <Clock className="w-4 h-4 text-gray-400 mr-2" />
                              {getDurationDisplay(program.duration_years)}
                            </div>
                          </div>
                        </td>
                        <td className="px-6 py-4 text-sm text-slate-700">
                          <div className="space-y-1">
                            {program.contact_email && (
                              <div className="flex items-center">
                                <Mail className="w-4 h-4 text-gray-400 mr-2" />
                                <span className="text-sm truncate max-w-xs">{program.contact_email}</span>
                              </div>
                            )}
                            {program.contact_phone && (
                              <div className="flex items-center">
                                <Phone className="w-4 h-4 text-gray-400 mr-2" />
                                <span className="text-sm">{program.contact_phone}</span>
                              </div>
                            )}
                            {!program.contact_email && !program.contact_phone && (
                              <span className="text-gray-400">No contact info</span>
                            )}
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <StatusBadge isActive={program.is_active} />
                        </td>
                        <td className="px-6 py-4 text-sm text-slate-700">
                          <div className="flex gap-4">
                            <div className="flex items-center">
                              <BookOpen className="w-4 h-4 mr-1 text-indigo-600" />
                              <span className="font-medium">{program.units_count}</span>
                              <span className="text-xs text-gray-500 ml-1">units</span>
                            </div>
                            <div className="flex items-center">
                              <Users className="w-4 h-4 mr-1 text-green-500" />
                              <span className="font-medium">{program.enrollments_count}</span>
                              <span className="text-xs text-gray-500 ml-1">enrolled</span>
                            </div>
                          </div>
                        </td>
                        <td className="px-6 py-4 text-sm font-medium">
                          <div className="flex items-center space-x-2">
                            <button
                              onClick={() => handleViewProgram(program)}
                              className="text-indigo-600 hover:text-indigo-900 transition-colors p-1 rounded hover:bg-indigo-50"
                              title="View details"
                            >
                              <Eye className="w-4 h-4" />
                            </button>
                            {can.update && !isClassTimetableOffice && (
                              <button
                                onClick={() => handleEditProgram(program)}
                                className="text-blue-600 hover:text-blue-900 transition-colors p-1 rounded hover:bg-blue-50"
                                title="Edit program"
                              >
                                <Edit className="w-4 h-4" />
                              </button>
                            )}
                            {can.delete && !isClassTimetableOffice && (
                              <button
                                onClick={() => handleDeleteProgram(program)}
                                className="text-red-600 hover:text-red-900 transition-colors p-1 rounded hover:bg-red-50"
                                title="Delete program"
                                disabled={program.units_count > 0 || program.enrollments_count > 0}
                              >
                                <Trash2 className="w-4 h-4" />
                              </button>
                            )}
                          </div>
                        </td>
                      </tr>

                      {/* Expanded row content */}
                      {expandedRows.has(program.id) && (
                        <tr>
                          {/* 🔥 SLS COLORS: Indigo gradient background */}
                          <td colSpan={6} className="px-6 py-6 bg-indigo-50/50">
                            <div className="space-y-6">
                              
                              {/* Program Management Section */}
                              {program.routes && (
                                <div className="mb-6">
                                  <h3 className="text-sm font-bold text-slate-800 mb-4 flex items-center">
                                    <BookOpen className="w-5 h-5 mr-2 text-indigo-700" />
                                    Program Management
                                  </h3>
                                  <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                                    
                                    {/* Units Card */}
                                    {!isClassTimetableOffice && !isExamTimetableOffice && (
                                      <a
                                        href={program.routes.units}
                                        className="group relative p-4 bg-gradient-to-br from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 rounded-xl shadow-lg hover:shadow-2xl transform hover:scale-105 hover:-translate-y-1 transition-all duration-300"
                                      >
                                        <div className="flex flex-col items-center text-center">
                                          <BookOpen className="w-8 h-8 text-white mb-2 group-hover:scale-110 transition-transform" />
                                          <div className="text-xs font-bold text-white mb-1">Units</div>
                                          <div className="text-xl font-bold text-white">{program.units_count || 0}</div>
                                        </div>
                                      </a>
                                    )}

                                    {/* Unit Assignment Card */}
                                    {!isClassTimetableOffice && !isExamTimetableOffice && (
                                      <a
                                        href={program.routes.unit_assignment}
                                        className="group relative p-4 bg-gradient-to-br from-indigo-500 to-indigo-600 hover:from-indigo-600 hover:to-indigo-700 rounded-xl shadow-lg hover:shadow-2xl transform hover:scale-105 hover:-translate-y-1 transition-all duration-300"
                                      >
                                        <div className="flex flex-col items-center text-center">
                                          <Settings className="w-8 h-8 text-white mb-2 group-hover:rotate-90 transition-transform duration-300" />
                                          <div className="text-xs font-bold text-white mb-1">Unit Assignment</div>
                                          <div className="text-xs text-white/90">Manage</div>
                                        </div>
                                      </a>
                                    )}

                                    {/* Classes Card */}
                                    {!isClassTimetableOffice && !isExamTimetableOffice && (
                                      <a
                                        href={program.routes.classes}
                                        className="group relative p-4 bg-gradient-to-br from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 rounded-xl shadow-lg hover:shadow-2xl transform hover:scale-105 hover:-translate-y-1 transition-all duration-300"
                                      >
                                        <div className="flex flex-col items-center text-center">
                                          <Users className="w-8 h-8 text-white mb-2 group-hover:scale-110 transition-transform" />
                                          <div className="text-xs font-bold text-white mb-1">Classes</div>
                                          <div className="text-xs text-white/90">Manage</div>
                                        </div>
                                      </a>
                                    )}

                                    {/* Enrollments Card */}
                                    {!isClassTimetableOffice && !isExamTimetableOffice && (
                                      <a
                                        href={program.routes.enrollments}
                                        className="group relative p-4 bg-gradient-to-br from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 rounded-xl shadow-lg hover:shadow-2xl transform hover:scale-105 hover:-translate-y-1 transition-all duration-300"
                                      >
                                        <div className="flex flex-col items-center text-center">
                                          <Scale className="w-8 h-8 text-white mb-2 group-hover:scale-110 transition-transform" />
                                          <div className="text-xs font-bold text-white mb-1">Enrollments</div>
                                          <div className="text-xl font-bold text-white">{program.enrollments_count || 0}</div>
                                        </div>
                                      </a>
                                    )}

                                    {/* Class Timetable Card */}
                                    {!isExamTimetableOffice && (
                                      <a
                                        href={program.routes.class_timetables}
                                        className="group relative p-4 bg-gradient-to-br from-cyan-500 to-cyan-600 hover:from-cyan-600 hover:to-cyan-700 rounded-xl shadow-lg hover:shadow-2xl transform hover:scale-105 hover:-translate-y-1 transition-all duration-300"
                                      >
                                        <div className="flex flex-col items-center text-center">
                                          <Calendar className="w-8 h-8 text-white mb-2 group-hover:scale-110 transition-transform" />
                                          <div className="text-xs font-bold text-white mb-1">Class Timetable</div>
                                          <div className="text-xs text-white/90">Schedule</div>
                                        </div>
                                      </a>
                                    )}

                                    {/* Exam Timetable Card */}
                                    {!isClassTimetableOffice && (
                                      <a
                                        href={program.routes.exam_timetables}
                                        className="group relative p-4 bg-gradient-to-br from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 rounded-xl shadow-lg hover:shadow-2xl transform hover:scale-105 hover:-translate-y-1 transition-all duration-300"
                                      >
                                        <div className="flex flex-col items-center text-center">
                                          <ClipboardList className="w-8 h-8 text-white mb-2 group-hover:scale-110 transition-transform" />
                                          <div className="text-xs font-bold text-white mb-1">Exam Timetable</div>
                                          <div className="text-xs text-white/90">Schedule</div>
                                        </div>
                                      </a>
                                    )}
                                  </div>
                                </div>
                              )}
                            </div>
                          </td>
                        </tr>
                      )}
                    </React.Fragment>
                  ))}
                </tbody>
              </table>
              
              {filteredPrograms.length === 0 && (
                <div className="text-center py-12">
                  <Scale className="mx-auto h-12 w-12 text-gray-400" />
                  <h3 className="mt-2 text-sm font-medium text-gray-900">No programs found</h3>
                  <p className="mt-1 text-sm text-gray-500">
                    {searchTerm || statusFilter !== 'all' || degreeFilter !== 'all'
                      ? 'Try adjusting your filters'
                      : 'Get started by creating a new law program'
                    }
                  </p>
                  {can.create && !isClassTimetableOffice && (
                    <button
                      onClick={handleCreateProgram}
                      className="mt-4 inline-flex items-center px-4 py-2 bg-indigo-700 text-white rounded-lg hover:bg-indigo-800 transition-colors"
                    >
                      <Plus className="w-4 h-4 mr-2" />
                      Create Law Program
                    </button>
                  )}
                </div>
              )}
            </div>
          </div>

          {/* Create/Edit Modal */}
          {(isCreateModalOpen || isEditModalOpen) && !isClassTimetableOffice && (
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
              <div className="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                {/* 🔥 SLS COLORS: Modal header */}
                <div className="bg-gradient-to-r from-slate-700 via-indigo-700 to-blue-800 p-6 rounded-t-2xl">
                  <h3 className="text-xl font-semibold text-white">
                    {selectedProgram ? 'Edit Law Program' : 'Create New Law Program'}
                  </h3>
                </div>

                <form onSubmit={handleSubmit} className="p-6 space-y-4">
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Program Code *
                      </label>
                      <input
                        type="text"
                        value={formData.code}
                        onChange={(e) => setFormData(prev => ({ ...prev, code: e.target.value.toUpperCase() }))}
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        placeholder="e.g., LLB, LLM"
                        maxLength={20}
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
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        min={0}
                      />
                    </div>
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Program Name *
                    </label>
                    <input
                      type="text"
                      value={formData.name}
                      onChange={(e) => setFormData(prev => ({ ...prev, name: e.target.value }))}
                      className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                      placeholder="e.g., Corporate Law, Criminal Law"
                      required
                    />
                  </div>

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Degree Type *
                      </label>
                      <select
                        value={formData.degree_type}
                        onChange={(e) => setFormData(prev => ({ ...prev, degree_type: e.target.value }))}
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        required
                      >
                        {degreeTypes.map((degree) => (
                          <option key={degree.value} value={degree.value}>
                            {degree.label}
                          </option>
                        ))}
                      </select>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Duration (Years) *
                      </label>
                      <input
                        type="number"
                        step="0.5"
                        min="0.5"
                        max="10"
                        value={formData.duration_years}
                        onChange={(e) => setFormData(prev => ({ ...prev, duration_years: parseFloat(e.target.value) || 1 }))}
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        required
                      />
                    </div>
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Description
                    </label>
                    <textarea
                      value={formData.description}
                      onChange={(e) => setFormData(prev => ({ ...prev, description: e.target.value }))}
                      className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                      rows={3}
                      placeholder="Brief description of the law program..."
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
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        placeholder="law@strathmore.edu"
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
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        placeholder="+254 123 456 789"
                      />
                    </div>
                  </div>

                  <div className="flex items-center">
                    <input
                      type="checkbox"
                      id="is_active"
                      checked={formData.is_active}
                      onChange={(e) => setFormData(prev => ({ ...prev, is_active: e.target.checked }))}
                      className="w-4 h-4 text-indigo-600 bg-gray-100 border-gray-300 rounded focus:ring-indigo-500 focus:ring-2"
                    />
                    <label htmlFor="is_active" className="ml-2 text-sm font-medium text-gray-700">
                      Program is active
                    </label>
                  </div>

                  <div className="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200">
                    <button
                      type="button"
                      onClick={() => {
                        setIsCreateModalOpen(false)
                        setIsEditModalOpen(false)
                        setSelectedProgram(null)
                      }}
                      className="px-6 py-3 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors font-medium"
                    >
                      Cancel
                    </button>
                    <button
                      type="submit"
                      disabled={loading}
                      className="px-6 py-3 bg-gradient-to-r from-slate-700 to-indigo-700 text-white rounded-lg hover:from-slate-800 hover:to-indigo-800 transition-all duration-200 font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      {loading ? 'Processing...' : selectedProgram ? 'Update Program' : 'Create Program'}
                    </button>
                  </div>
                </form>
              </div>
            </div>
          )}

          {/* View Modal */}
          {isViewModalOpen && selectedProgram && (
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
              <div className="bg-white rounded-2xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
                {/* 🔥 SLS COLORS: View modal header */}
                <div className="bg-gradient-to-r from-slate-700 via-indigo-800 to-blue-900 p-6 rounded-t-2xl">
                  <div className="flex items-center justify-between">
                    <h3 className="text-xl font-semibold text-white">Law Program Details</h3>
                    <button
                      onClick={() => setIsViewModalOpen(false)}
                      className="text-white hover:text-gray-200 transition-colors"
                    >
                      <X className="w-6 h-6" />
                    </button>
                  </div>
                </div>
                
                <div className="p-6 space-y-6">
                  {/* Header Info */}
                  <div className="flex items-start space-x-4">
                    <Scale className="w-12 h-12 text-indigo-700 flex-shrink-0" />
                    <div className="flex-1">
                      <h4 className="text-2xl font-bold text-gray-900">{selectedProgram.name}</h4>
                      <p className="text-indigo-700 font-semibold mt-1">{selectedProgram.code}</p>
                      {selectedProgram.description && (
                        <p className="text-gray-600 mt-2">{selectedProgram.description}</p>
                      )}
                      <div className="flex items-center gap-3 mt-3">
                        <DegreeBadge degreeType={selectedProgram.degree_type} />
                        <StatusBadge isActive={selectedProgram.is_active} />
                      </div>
                    </div>
                  </div>

                  {/* Details Grid */}
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6 pt-6 border-t">
                    <div className="space-y-4">
                      <h5 className="font-semibold text-gray-900 text-lg">Program Information</h5>
                      <div className="space-y-3">
                        <div>
                          <span className="text-sm text-gray-500">Full Name</span>
                          <p className="font-medium text-gray-900">{selectedProgram.full_name}</p>
                        </div>
                        <div>
                          <span className="text-sm text-gray-500">Duration</span>
                          <p className="font-medium text-gray-900 flex items-center">
                            <Clock className="w-4 h-4 mr-2 text-gray-400" />
                            {getDurationDisplay(selectedProgram.duration_years)}
                          </p>
                        </div>
                        <div>
                          <span className="text-sm text-gray-500">School</span>
                          <p className="font-medium text-gray-900">{selectedProgram.school_name}</p>
                        </div>
                        <div>
                          <span className="text-sm text-gray-500">Sort Order</span>
                          <p className="font-medium text-gray-900">{selectedProgram.sort_order}</p>
                        </div>
                      </div>
                    </div>

                    <div className="space-y-4">
                      <h5 className="font-semibold text-gray-900 text-lg">Contact & Statistics</h5>
                      <div className="space-y-3">
                        {selectedProgram.contact_email && (
                          <div>
                            <span className="text-sm text-gray-500">Email</span>
                            <p className="font-medium text-gray-900 flex items-center">
                              <Mail className="w-4 h-4 mr-2 text-gray-400" />
                              {selectedProgram.contact_email}
                            </p>
                          </div>
                        )}
                        {selectedProgram.contact_phone && (
                          <div>
                            <span className="text-sm text-gray-500">Phone</span>
                            <p className="font-medium text-gray-900 flex items-center">
                              <Phone className="w-4 h-4 mr-2 text-gray-400" />
                              {selectedProgram.contact_phone}
                            </p>
                          </div>
                        )}
                        <div>
                          <span className="text-sm text-gray-500">Total Units</span>
                          <p className="font-medium text-gray-900 flex items-center">
                            <BookOpen className="w-4 h-4 mr-2 text-indigo-600" />
                            {selectedProgram.units_count} units
                          </p>
                        </div>
                        <div>
                          <span className="text-sm text-gray-500">Total Enrollments</span>
                          <p className="font-medium text-gray-900 flex items-center">
                            <Users className="w-4 h-4 mr-2 text-green-500" />
                            {selectedProgram.enrollments_count} students
                          </p>
                        </div>
                      </div>
                    </div>
                  </div>

                  {/* Dates */}
                  <div className="pt-6 border-t">
                    <h5 className="font-semibold text-gray-900 text-lg mb-3">Timeline</h5>
                    <div className="grid grid-cols-2 gap-4 text-sm">
                      <div>
                        <span className="text-gray-500">Created</span>
                        <p className="font-medium text-gray-900">{new Date(selectedProgram.created_at).toLocaleString()}</p>
                      </div>
                      <div>
                        <span className="text-gray-500">Last Updated</span>
                        <p className="font-medium text-gray-900">{new Date(selectedProgram.updated_at).toLocaleString()}</p>
                      </div>
                    </div>
                  </div>
                </div>

                <div className="flex justify-end gap-3 px-6 pb-6">
                  {can.update && !isClassTimetableOffice && (
                    <button
                      onClick={() => {
                        setIsViewModalOpen(false)
                        handleEditProgram(selectedProgram)
                      }}
                      className="px-6 py-3 bg-indigo-700 text-white rounded-lg hover:bg-indigo-800 transition-colors font-medium"
                    >
                      Edit Program
                    </button>
                  )}
                  <button
                    onClick={() => setIsViewModalOpen(false)}
                    className="px-6 py-3 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors font-medium"
                  >
                    Close
                  </button>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
    </AuthenticatedLayout>
  )
}

export default SLSProgramsManagement