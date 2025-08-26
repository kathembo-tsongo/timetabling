import React, { useState, useEffect } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { toast } from 'react-hot-toast';
import {
  BookOpen,
  Plus,
  Search,
  Filter,
  Edit,
  Trash2,
  Eye,
  ChevronDown,
  ChevronUp,
  Building2,
  GraduationCap,
  Calendar,
  Check,
  X,
  Clock,
  Users
} from 'lucide-react';

type Unit = {
  id: number;
  code: string;
  name: string;
  description?: string;
  program_id: number;
  program_name: string;
  program_code: string;
  school_id: number;
  school_name: string;
  school_code: string;
  semester_id?: number;
  semester_name?: string;
  is_active: boolean;
  credit_hours: number;
  created_at: string;
  updated_at: string;
};

type School = {
  id: number;
  code: string;
  name: string;
};

type Program = {
  id: number;
  code: string;
  name: string;
  school_id?: number;
};

type Semester = {
  id: number;
  name: string;
};

type Stats = {
  total: number;
  active: number;
  inactive: number;
  assigned_to_semester: number;
  unassigned: number;
};

type Can = {
  create: boolean;
  update: boolean;
  delete: boolean;
};

type Filters = {
  search?: string;
  program_id?: string | number;
  semester_id?: string | number;
  school_id?: string | number;
  is_active?: string;
  sort_field?: string;
  sort_direction?: string;
};

type PageProps = {
  units: Unit[];
  schools: School[];
  programs: Program[];
  semesters: Semester[];
  stats?: Stats;
  can: Can;
  filters: Filters;
  error?: string;
  flash?: {
    success?: string;
  };
  errors?: {
    error?: string;
  };
};

type UnitFormData = {
  code: string;
  name: string;
  credit_hours: number;
  school_id: number | '';
  program_id: number | '';
  semester_id: number | '';
  is_active: boolean;
};

export default function UnitsIndex() {
  const pageProps = usePage<PageProps>();
  const { 
    units = [], 
    schools = [], 
    programs = [], 
    semesters = [], 
    stats, 
    can = { create: false, update: false, delete: false }, 
    filters = {}, 
    error, 
    flash, 
    errors 
  } = pageProps.props || {};

  // State management
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [isViewModalOpen, setIsViewModalOpen] = useState(false);
  const [selectedUnit, setSelectedUnit] = useState<Unit | null>(null);
  const [expandedRows, setExpandedRows] = useState<Set<number>>(new Set());
  const [loading, setLoading] = useState(false);

  // Form state
  const [formData, setFormData] = useState<UnitFormData>({
    code: '',
    name: '',
    credit_hours: 3,
    school_id: '',
    program_id: '',
    semester_id: '',
    is_active: false
  });

  // Filter state
  const [searchTerm, setSearchTerm] = useState(filters?.search || '');
  const [selectedProgram, setSelectedProgram] = useState<string | number>(filters?.program_id || '');
  const [selectedSchool, setSelectedSchool] = useState<string | number>(filters?.school_id || '');
  const [selectedSemester, setSelectedSemester] = useState<string | number>(filters?.semester_id || '');
  const [activeFilter, setActiveFilter] = useState<string>(
    filters?.is_active !== undefined ? (filters.is_active === '1' ? 'active' : 'inactive') : 'all'
  );
  const [sortField, setSortField] = useState(filters?.sort_field || 'name');
  const [sortDirection, setSortDirection] = useState(filters?.sort_direction || 'asc');

  // Filtered programs based on selected school in form
  const filteredPrograms = programs.filter(program => 
    !formData.school_id || program.school_id === formData.school_id
  );

  // Error handling
  useEffect(() => {
    if (errors?.error) {
      toast.error(errors.error);
    }
    if (flash?.success) {
      toast.success(flash.success);
    }
  }, [errors, flash]);

  // Reset program when school changes
  useEffect(() => {
    if (formData.school_id && formData.program_id) {
      const selectedProgram = programs.find(p => p.id === formData.program_id);
      if (selectedProgram && selectedProgram.school_id !== formData.school_id) {
        setFormData(prev => ({ ...prev, program_id: '' }));
      }
    }
  }, [formData.school_id, formData.program_id, programs]);

  // Auto-deactivate unit when semester is removed
  useEffect(() => {
    if (!formData.semester_id && formData.is_active) {
      setFormData(prev => ({ ...prev, is_active: false }));
    }
  }, [formData.semester_id, formData.is_active]);

  // Filtered units
  const filteredUnits = units.filter(unit => {
    const matchesSearch = !searchTerm || 
      unit.name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
      unit.code?.toLowerCase().includes(searchTerm.toLowerCase()) ||
      unit.program_name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
      unit.school_name?.toLowerCase().includes(searchTerm.toLowerCase());
    
    const matchesProgram = !selectedProgram || unit.program_id == selectedProgram;
    const matchesSchool = !selectedSchool || unit.school_id == selectedSchool;
    const matchesSemester = !selectedSemester || unit.semester_id == selectedSemester;
    const matchesStatus = activeFilter === 'all' || 
      (activeFilter === 'active' && unit.is_active) ||
      (activeFilter === 'inactive' && !unit.is_active);
    
    return matchesSearch && matchesProgram && matchesSchool && matchesSemester && matchesStatus;
  });

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
    );
  };

  // Form handlers
  const handleCreateUnit = () => {
    setFormData({
      code: '',
      name: '',
      credit_hours: 3,
      school_id: '',
      program_id: '',
      semester_id: '',
      is_active: false
    });
    setIsCreateModalOpen(true);
  };

  const handleEditUnit = (unit: Unit) => {
    setSelectedUnit(unit);
    setFormData({
      code: unit.code,
      name: unit.name,
      credit_hours: unit.credit_hours,
      school_id: unit.school_id,
      program_id: unit.program_id,
      semester_id: unit.semester_id || '',
      is_active: unit.is_active
    });
    setIsEditModalOpen(true);
  };

  const handleViewUnit = (unit: Unit) => {
    setSelectedUnit(unit);
    setIsViewModalOpen(true);
  };

  const handleDeleteUnit = (unit: Unit) => {
    if (confirm(`Are you sure you want to delete "${unit.name}"? This action cannot be undone.`)) {
      setLoading(true);
      router.delete(route('admin.units.destroy', unit.id), {
        onSuccess: () => {
          toast.success('Unit deleted successfully!');
        },
        onError: (errors) => {
          toast.error(errors.error || 'Failed to delete unit');
        },
        onFinish: () => setLoading(false)
      });
    }
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);

    const url = selectedUnit 
      ? route('admin.units.update', selectedUnit.id)
      : route('admin.units.store');
    
    const method = selectedUnit ? 'put' : 'post';

    router[method](url, formData, {
      onSuccess: () => {
        toast.success(`Unit ${selectedUnit ? 'updated' : 'created'} successfully!`);
        setIsCreateModalOpen(false);
        setIsEditModalOpen(false);
        setSelectedUnit(null);
      },
      onError: (errors) => {
        toast.error(errors.error || `Failed to ${selectedUnit ? 'update' : 'create'} unit`);
      },
      onFinish: () => setLoading(false)
    });
  };

  const handleFilter = () => {
    const params = new URLSearchParams();
    
    if (searchTerm) params.set('search', searchTerm);
    if (selectedProgram) params.set('program_id', String(selectedProgram));
    if (selectedSchool) params.set('school_id', String(selectedSchool));
    if (selectedSemester) params.set('semester_id', String(selectedSemester));
    if (activeFilter !== 'all') {
      params.set('is_active', activeFilter === 'active' ? '1' : '0');
    }
    params.set('sort_field', sortField);
    params.set('sort_direction', sortDirection);
    
    router.get(`${route('admin.units.index')}?${params.toString()}`);
  };

  const toggleRowExpansion = (unitId: number) => {
    const newExpanded = new Set(expandedRows);
    if (newExpanded.has(unitId)) {
      newExpanded.delete(unitId);
    } else {
      newExpanded.add(unitId);
    }
    setExpandedRows(newExpanded);
  };

  return (
    <AuthenticatedLayout>
      <Head title="Units Management" />
      
      <div className="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-cyan-50 py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          
          {/* Header */}
          <div className="mb-8">
            <div className="bg-white/80 backdrop-blur-sm rounded-3xl shadow-2xl border border-slate-200/50 p-8">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <h1 className="text-4xl font-bold bg-gradient-to-r from-slate-800 via-blue-800 to-indigo-800 bg-clip-text text-transparent mb-2">
                    Units Management
                  </h1>
                  <p className="text-slate-600 text-lg">
                    Manage academic units across all schools and programs
                  </p>
                  {stats && (
                    <div className="flex items-center gap-4 mt-4">
                      <div className="text-sm text-slate-600">
                        Total: <span className="font-semibold">{stats.total}</span>
                      </div>
                      <div className="text-sm text-slate-600">
                        Active: <span className="font-semibold">{stats.active}</span>
                      </div>
                      <div className="text-sm text-slate-600">
                        Assigned: <span className="font-semibold">{stats.assigned_to_semester}</span>
                      </div>
                      <div className="text-sm text-slate-600">
                        Unassigned: <span className="font-semibold">{stats.unassigned}</span>
                      </div>
                    </div>
                  )}
                </div>
                
                  <button
                    onClick={handleCreateUnit}
                    className="inline-flex items-center px-6 py-3 bg-gradient-to-r from-emerald-500 via-emerald-600 to-teal-600 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl hover:from-emerald-600 hover:via-emerald-700 hover:to-teal-700 transform hover:scale-105 hover:-translate-y-0.5 transition-all duration-300 group"
                  >
                    <Plus className="w-5 h-5 mr-2 group-hover:rotate-12 transition-transform duration-300" />
                    Create Unit
                  </button>
                
              </div>
            </div>
          </div>

          {/* Error Message */}
          {error && (
            <div className="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
              <div className="flex">
                <X className="h-5 w-5 text-red-400" />
                <div className="ml-3">
                  <p className="text-sm text-red-800">{error}</p>
                </div>
              </div>
            </div>
          )}

          {/* Filters */}
          <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-lg border border-slate-200/50 p-6 mb-6">
            <div className="grid grid-cols-1 md:grid-cols-6 gap-4">
              <div className="md:col-span-2">
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
              
              <select
                value={selectedSchool}
                onChange={(e) => setSelectedSchool(e.target.value)}
                className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="">All Schools</option>
                {schools.map(school => (
                  <option key={school.id} value={school.id}>
                    {school.code} - {school.name}
                  </option>
                ))}
              </select>

              <select
                value={selectedProgram}
                onChange={(e) => setSelectedProgram(e.target.value)}
                className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="">All Programs</option>
                {programs.map(program => (
                  <option key={program.id} value={program.id}>
                    {program.code} - {program.name}
                  </option>
                ))}
              </select>

              <select
                value={selectedSemester}
                onChange={(e) => setSelectedSemester(e.target.value)}
                className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="">All Semesters</option>
                {semesters.map(semester => (
                  <option key={semester.id} value={semester.id}>
                    {semester.name}
                  </option>
                ))}
              </select>

              <select
                value={activeFilter}
                onChange={(e) => setActiveFilter(e.target.value)}
                className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="all">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>

              <button
                onClick={handleFilter}
                className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
              >
                <Filter className="w-5 h-5" />
              </button>
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
                      School & Program
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Details
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
                  {filteredUnits.map((unit, index) => (
                    <React.Fragment key={unit.id}>
                      <tr className={`hover:bg-slate-50 transition-colors duration-150 ${
                        index % 2 === 0 ? "bg-white" : "bg-slate-50/50"
                      }`}>
                        <td className="px-6 py-4">
                          <div className="flex items-center">
                            <button
                              onClick={() => toggleRowExpansion(unit.id)}
                              className="mr-3 p-1 hover:bg-gray-200 rounded"
                            >
                              {expandedRows.has(unit.id) ? (
                                <ChevronUp className="w-4 h-4" />
                              ) : (
                                <ChevronDown className="w-4 h-4" />
                              )}
                            </button>
                            <BookOpen className="w-8 h-8 text-blue-500 mr-3" />
                            <div>
                              <div className="text-sm font-medium text-slate-900">{unit.name}</div>
                              <div className="text-xs text-blue-600 font-semibold">{unit.code}</div>
                              {unit.description && (
                                <div className="text-xs text-slate-500 mt-1 max-w-xs truncate">
                                  {unit.description}
                                </div>
                              )}
                            </div>
                          </div>
                        </td>
                        <td className="px-6 py-4 text-sm text-slate-700">
                          <div className="space-y-1">
                            <div className="flex items-center">
                              <Building2 className="w-4 h-4 text-gray-400 mr-2" />
                              <span className="text-sm font-medium">{unit.school_code} - {unit.school_name}</span>
                            </div>
                            <div className="flex items-center">
                              <GraduationCap className="w-4 h-4 text-gray-400 mr-2" />
                              <span className="text-sm">{unit.program_code} - {unit.program_name}</span>
                            </div>
                          </div>
                        </td>
                        <td className="px-6 py-4 text-sm text-slate-700">
                          <div className="space-y-1">
                            <div className="flex items-center">
                              <Clock className="w-4 h-4 text-gray-400 mr-2" />
                              <span className="font-medium">{unit.credit_hours} hrs</span>
                            </div>
                            <div className="flex items-center">
                              <Calendar className="w-4 h-4 text-gray-400 mr-2" />
                              <span className="text-sm">
                                {unit.semester_name || 'Not assigned'}
                              </span>
                            </div>
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <StatusBadge isActive={unit.is_active} />
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
                         
                              <button
                                onClick={() => handleEditUnit(unit)}
                                className="text-indigo-600 hover:text-indigo-900 transition-colors p-1 rounded hover:bg-indigo-50"
                                title="Edit unit"
                              >
                                <Edit className="w-4 h-4" />
                              </button>
                        
                           
                              <button
                                onClick={() => handleDeleteUnit(unit)}
                                className="text-red-600 hover:text-red-900 transition-colors p-1 rounded hover:bg-red-50"
                                title="Delete unit"
                              >
                                <Trash2 className="w-4 h-4" />
                              </button>
                            
                          </div>
                        </td>
                      </tr>
                      
                      {/* Expanded row content */}
                      {expandedRows.has(unit.id) && (
                        <tr>
                          <td colSpan={5} className="px-6 py-4 bg-gray-50">
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                              <div>
                                <h4 className="font-medium text-gray-900 mb-3">Unit Details</h4>
                                <div className="space-y-2 text-sm">
                                  <div>
                                    <span className="font-medium">Credit Hours:</span>
                                    <div className="text-gray-600">{unit.credit_hours}</div>
                                  </div>
                                  <div>
                                    <span className="font-medium">Semester:</span>
                                    <div className="text-gray-600">{unit.semester_name || 'Not assigned'}</div>
                                  </div>
                                  <div>
                                    <span className="font-medium">Created:</span>
                                    <div className="text-gray-600">{new Date(unit.created_at).toLocaleDateString()}</div>
                                  </div>
                                </div>
                              </div>
                              
                              <div>
                                <h4 className="font-medium text-gray-900 mb-3">Academic Structure</h4>
                                <div className="space-y-2 text-sm">
                                  <div>
                                    <span className="font-medium">School:</span>
                                    <div className="text-gray-600">{unit.school_name} ({unit.school_code})</div>
                                  </div>
                                  <div>
                                    <span className="font-medium">Program:</span>
                                    <div className="text-gray-600">{unit.program_name} ({unit.program_code})</div>
                                  </div>
                                </div>
                              </div>

                              {/* <div>
                                <h4 className="font-medium text-gray-900 mb-3">Description</h4>
                                <div className="text-sm text-gray-600">
                                  {unit.description || 'No description available'}
                                </div>
                              </div> */}
                            </div>
                          </td>
                        </tr>
                      )}
                    </React.Fragment>
                  ))}
                </tbody>
              </table>
              
              {filteredUnits.length === 0 && (
                <div className="text-center py-12">
                  <BookOpen className="mx-auto h-12 w-12 text-gray-400" />
                  <h3 className="mt-2 text-sm font-medium text-gray-900">No units found</h3>
                  <p className="mt-1 text-sm text-gray-500">
                    {searchTerm || selectedProgram || selectedSchool || selectedSemester || activeFilter !== 'all'
                      ? 'Try adjusting your filters'
                      : 'Get started by creating a new unit'
                    }
                  </p>
                  {can.create && !searchTerm && !selectedProgram && !selectedSchool && !selectedSemester && activeFilter === 'all' && (
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
          </div>

          {/* Create/Edit Modal */}
          {(isCreateModalOpen || isEditModalOpen) && (
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
              <div className="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div className="bg-gradient-to-r from-emerald-500 via-emerald-600 to-teal-600 p-6 rounded-t-2xl">
                  <h3 className="text-xl font-semibold text-white">
                    {selectedUnit ? 'Edit Unit' : 'Create New Unit'}
                  </h3>
                </div>

                <form onSubmit={handleSubmit} className="p-6 space-y-4">
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
                        placeholder="e.g., CS101, MATH201"
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
                        onChange={(e) => setFormData(prev => ({ ...prev, credit_hours: parseInt(e.target.value) || 3 }))}
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        min={1}
                        max={6}
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
                      placeholder="e.g., Introduction to Computer Science"
                      required
                    />
                  </div>

                  <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        School *
                      </label>
                      <select
                        value={formData.school_id}
                        onChange={(e) => setFormData(prev => ({ 
                          ...prev, 
                          school_id: e.target.value ? parseInt(e.target.value) : '',
                          program_id: '' // Reset program when school changes
                        }))}
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        required
                      >
                        <option value="">Select School</option>
                        {schools.map(school => (
                          <option key={school.id} value={school.id}>
                            {school.code} - {school.name}
                          </option>
                        ))}
                      </select>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Program *
                      </label>
                      <select
                        value={formData.program_id}
                        onChange={(e) => setFormData(prev => ({ ...prev, program_id: e.target.value ? parseInt(e.target.value) : '' }))}
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        required
                        disabled={!formData.school_id}
                      >
                        <option value="">Select Program</option>
                        {filteredPrograms.map(program => (
                          <option key={program.id} value={program.id}>
                            {program.code} - {program.name}
                          </option>
                        ))}
                      </select>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Semester
                      </label>
                      <select
                        value={formData.semester_id}
                        onChange={(e) => setFormData(prev => ({ ...prev, semester_id: e.target.value ? parseInt(e.target.value) : '' }))}
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                      >
                        <option value="">Select Semester</option>
                        {semesters.map(semester => (
                          <option key={semester.id} value={semester.id}>
                            {semester.name}
                          </option>
                        ))}
                      </select>
                    </div>
                  </div>

                  <div className="flex items-center">
                    <input
                      type="checkbox"
                      id="is_active"
                      checked={formData.is_active}
                      onChange={(e) => setFormData(prev => ({ ...prev, is_active: e.target.checked }))}
                      className="w-4 h-4 text-emerald-600 bg-gray-100 border-gray-300 rounded focus:ring-emerald-500 focus:ring-2"
                      disabled={!formData.semester_id}
                    />
                    <label htmlFor="is_active" className="ml-2 text-sm font-medium text-gray-700">
                      Unit is active
                    </label>
                    {!formData.semester_id && (
                      <p className="ml-2 text-xs text-gray-500">
                        (Must assign to semester first)
                      </p>
                    )}
                  </div>

                  <div className="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200">
                    <button
                      type="button"
                      onClick={() => {
                        setIsCreateModalOpen(false);
                        setIsEditModalOpen(false);
                        setSelectedUnit(null);
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
                      {loading ? 'Processing...' : selectedUnit ? 'Update Unit' : 'Create Unit'}
                    </button>
                  </div>
                </form>
              </div>
            </div>
          )}

          {/* View Modal */}
          {isViewModalOpen && selectedUnit && (
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
              <div className="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div className="bg-gradient-to-r from-slate-500 via-slate-600 to-gray-600 p-6 rounded-t-2xl">
                  <div className="flex items-center justify-between">
                    <h3 className="text-xl font-semibold text-white">
                      Unit Details
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
                          <label className="block text-sm font-medium text-gray-700">Unit Name</label>
                          <div className="mt-1 text-sm text-gray-900">{selectedUnit.name}</div>
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700">Unit Code</label>
                          <div className="mt-1 text-sm font-semibold text-blue-600">{selectedUnit.code}</div>
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700">Credit Hours</label>
                          <div className="mt-1 text-sm text-gray-900">{selectedUnit.credit_hours} hours</div>
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700">Status</label>
                          <div className="mt-1">
                            <StatusBadge isActive={selectedUnit.is_active} />
                          </div>
                        </div>
                      </div>
                    </div>

                    <div>
                      <h4 className="font-semibold text-gray-900 mb-3">Academic Structure</h4>
                      <div className="space-y-3">
                        <div>
                          <label className="block text-sm font-medium text-gray-700">School</label>
                          <div className="mt-1 text-sm text-gray-900">
                            <div className="flex items-center">
                              <Building2 className="w-4 h-4 text-gray-400 mr-2" />
                              {selectedUnit.school_code} - {selectedUnit.school_name}
                            </div>
                          </div>
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700">Program</label>
                          <div className="mt-1 text-sm text-gray-900">
                            <div className="flex items-center">
                              <GraduationCap className="w-4 h-4 text-gray-400 mr-2" />
                              {selectedUnit.program_code} - {selectedUnit.program_name}
                            </div>
                          </div>
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700">Semester</label>
                          <div className="mt-1 text-sm text-gray-900">
                            {selectedUnit.semester_name ? (
                              <div className="flex items-center">
                                <Calendar className="w-4 h-4 text-gray-400 mr-2" />
                                {selectedUnit.semester_name}
                              </div>
                            ) : (
                              <span className="text-gray-400">Not assigned</span>
                            )}
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  {selectedUnit.description && (
                    <div>
                      <h4 className="font-semibold text-gray-900 mb-3">Description</h4>
                      <div className="text-sm text-gray-700 bg-gray-50 p-4 rounded-lg">
                        {selectedUnit.description}
                      </div>
                    </div>
                  )}

                  <div>
                    <h4 className="font-semibold text-gray-900 mb-3">Timestamps</h4>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div>
                        <label className="block text-sm font-medium text-gray-700">Created</label>
                        <div className="mt-1 text-sm text-gray-900">
                          {new Date(selectedUnit.created_at).toLocaleString()}
                        </div>
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700">Last Updated</label>
                        <div className="mt-1 text-sm text-gray-900">
                          {new Date(selectedUnit.updated_at).toLocaleString()}
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
                          setIsViewModalOpen(false);
                          handleEditUnit(selectedUnit);
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
        </div>
      </div>
    </AuthenticatedLayout>
  );
}