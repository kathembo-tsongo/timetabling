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
  Clock
} from "lucide-react"
import { route } from 'ziggy-js'

// Interfaces
interface Unit {
  id: number
  code: string
  name: string
  credit_hours: number
  description?: string
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

interface PageProps {
  units: {
    data: Unit[]
    links: any[]
    meta: any
  }
  program: Program
  schoolCode: string
  filters: {
    search?: string
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

const ProgramUnitsIndex: React.FC = () => {
  const { 
    units, 
    program, 
    schoolCode, 
    filters, 
    can = { create: false, update: false, delete: false }, 
    flash, 
    errors 
  } = usePage<PageProps>().props

  // State management
  const [searchTerm, setSearchTerm] = useState(filters.search || '')
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
    
    const indexRoute = route(`schools.${schoolCode.toLowerCase()}.programs.units.index`, program.id)
    router.get(`${indexRoute}?${params.toString()}`)
  }

  const handleDelete = (unit: Unit) => {
    if (confirm(`Are you sure you want to delete "${unit.name}"? This action cannot be undone.`)) {
      setLoading(true)
      const deleteRoute = route(`schools.${schoolCode.toLowerCase()}.programs.units.destroy`, [program.id, unit.id])
      
      router.delete(deleteRoute, {
        onSuccess: () => {
          toast.success('Unit deleted successfully!')
        },
        onError: (errors) => {
          toast.error(errors.error || 'Failed to delete unit')
        },
        onFinish: () => setLoading(false)
      })
    }
  }

  const backToProgramsRoute = route(`schools.${schoolCode.toLowerCase()}.programs.index`)

  return (
    <AuthenticatedLayout>
      <Head title={`${program.code} Units - ${program.school.name}`} />
      
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
                  <h2 className="text-2xl font-semibold text-slate-700 mb-2">Units Management</h2>
                  <p className="text-slate-600 text-lg">
                    Manage academic units and course modules for this program
                  </p>
                  <div className="flex items-center gap-4 mt-4">
                    <div className="text-sm text-slate-600">
                      Total Units: <span className="font-semibold">{units.data.length}</span>
                    </div>
                    <div className="text-sm text-slate-600">
                      Active: <span className="font-semibold">{units.data.filter(u => u.is_active).length}</span>
                    </div>
                    <div className="text-sm text-slate-600">
                      Total Credits: <span className="font-semibold">{units.data.reduce((sum, u) => sum + u.credit_hours, 0)}</span>
                    </div>
                  </div>
                </div>
                <div className="mt-6 sm:mt-0 flex-shrink-0 flex items-center justify-end">
                  {can.create && (
                    <a
                      href={route(`schools.${schoolCode.toLowerCase()}.programs.units.create`, program.id)}
                      className="inline-flex items-center px-6 py-3 bg-gradient-to-r from-emerald-500 via-emerald-600 to-teal-600 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl hover:from-emerald-600 hover:via-emerald-700 hover:to-teal-700 transform hover:scale-105 hover:-translate-y-0.5 transition-all duration-300 group"
                    >
                      <Plus className="w-5 h-5 mr-2 group-hover:rotate-12 transition-transform duration-300" />
                      Create Unit
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
                    placeholder="Search units..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  />
                </div>
              </div>
              <div className="flex gap-4">
                <button
                  onClick={handleFilter}
                  className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                >
                  <Filter className="w-5 h-5" />
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
                      Description
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
                  {units.data.map((unit, index) => (
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
                        <div className="text-sm text-slate-700 max-w-xs truncate">
                          {unit.description || 'No description'}
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        {unit.is_active ? (
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
                            href={route(`schools.${schoolCode.toLowerCase()}.programs.units.show`, [program.id, unit.id])}
                            className="text-blue-600 hover:text-blue-900 transition-colors p-1 rounded hover:bg-blue-50"
                            title="View details"
                          >
                            <Eye className="w-4 h-4" />
                          </a>
                          {can.update && (
                            <a
                              href={route(`schools.${schoolCode.toLowerCase()}.programs.units.edit`, [program.id, unit.id])}
                              className="text-indigo-600 hover:text-indigo-900 transition-colors p-1 rounded hover:bg-indigo-50"
                              title="Edit unit"
                            >
                              <Edit className="w-4 h-4" />
                            </a>
                          )}
                          {can.delete && (
                            <button
                              onClick={() => handleDelete(unit)}
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
              
              {units.data.length === 0 && (
                <div className="text-center py-12">
                  <BookOpen className="mx-auto h-12 w-12 text-gray-400" />
                  <h3 className="mt-2 text-sm font-medium text-gray-900">No units found</h3>
                  <p className="mt-1 text-sm text-gray-500">
                    Get started by creating a new unit for this program
                  </p>
                  {can.create && (
                    <a
                      href={route(`schools.${schoolCode.toLowerCase()}.programs.units.create`, program.id)}
                      className="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                    >
                      <Plus className="w-4 h-4 mr-2" />
                      Create Unit
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

export default ProgramUnitsIndex