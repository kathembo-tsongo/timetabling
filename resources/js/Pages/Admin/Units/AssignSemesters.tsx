import React, { useState, useEffect } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { toast } from 'react-hot-toast';
import {
  BookOpen,
  Search,
  Filter,
  Calendar,
  Users,
  Check,
  X,
  Building2,
  GraduationCap,
  Clock,
  ChevronRight,
  ArrowRight,
  UserCheck,
  AlertCircle
} from 'lucide-react';

type Unit = {
  id: number;
  code: string;
  name: string;
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

type PageProps = {
  units: Unit[];
  schools: School[];
  programs: Program[];
  semesters: Semester[];
  error?: string;
  flash?: {
    success?: string;
  };
  errors?: {
    error?: string;
  };
};

export default function AssignSemesters() {
  const pageProps = usePage<PageProps>();
  const { 
    units = [], 
    schools = [], 
    programs = [], 
    semesters = [],
    error, 
    flash, 
    errors 
  } = pageProps.props || {};

  // State management
  const [selectedUnits, setSelectedUnits] = useState<Set<number>>(new Set());
  const [selectedSemester, setSelectedSemester] = useState<number | ''>('');
  const [loading, setLoading] = useState(false);
  
  // Filter state
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedSchool, setSelectedSchool] = useState<string | number>('');
  const [selectedProgram, setSelectedProgram] = useState<string | number>('');
  const [assignmentFilter, setAssignmentFilter] = useState<string>('unassigned');

  // Error handling
  useEffect(() => {
    if (errors?.error) {
      toast.error(errors.error);
    }
    if (flash?.success) {
      toast.success(flash.success);
    }
  }, [errors, flash]);

  // Filtered units
  const filteredUnits = units.filter(unit => {
    const matchesSearch = !searchTerm || 
      unit.name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
      unit.code?.toLowerCase().includes(searchTerm.toLowerCase()) ||
      unit.program_name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
      unit.school_name?.toLowerCase().includes(searchTerm.toLowerCase());
    
    const matchesProgram = !selectedProgram || unit.program_id == selectedProgram;
    const matchesSchool = !selectedSchool || unit.school_id == selectedSchool;
    
    const matchesAssignment = assignmentFilter === 'all' || 
      (assignmentFilter === 'assigned' && unit.semester_id) ||
      (assignmentFilter === 'unassigned' && !unit.semester_id);
    
    return matchesSearch && matchesProgram && matchesSchool && matchesAssignment;
  });

  // Get unassigned units
  const unassignedUnits = filteredUnits.filter(unit => !unit.semester_id);
  const assignedUnits = filteredUnits.filter(unit => unit.semester_id);

  // Toggle unit selection
  const toggleUnitSelection = (unitId: number) => {
    const newSelected = new Set(selectedUnits);
    if (newSelected.has(unitId)) {
      newSelected.delete(unitId);
    } else {
      newSelected.add(unitId);
    }
    setSelectedUnits(newSelected);
  };

  // Select all unassigned units
  const selectAllUnassigned = () => {
    setSelectedUnits(new Set(unassignedUnits.map(unit => unit.id)));
  };

  // Clear selection
  const clearSelection = () => {
    setSelectedUnits(new Set());
  };

  // Assign units to semester
  const handleAssignToSemester = () => {
    if (selectedUnits.size === 0) {
      toast.error('Please select at least one unit');
      return;
    }
    
    if (!selectedSemester) {
      toast.error('Please select a semester');
      return;
    }

    setLoading(true);
    router.post(route('admin.units.assign-semester'), {
      unit_ids: Array.from(selectedUnits),
      semester_id: selectedSemester
    }, {
      onSuccess: () => {
        toast.success('Units assigned to semester successfully!');
        setSelectedUnits(new Set());
        setSelectedSemester('');
      },
      onError: (errors) => {
        toast.error(errors.error || 'Failed to assign units to semester');
      },
      onFinish: () => setLoading(false)
    });
  };

  // Remove units from semester
  const handleRemoveFromSemester = (unitIds: number[]) => {
    if (unitIds.length === 0) return;

    if (confirm(`Are you sure you want to remove ${unitIds.length} unit(s) from their semester? This will also deactivate them.`)) {
      setLoading(true);
      router.post(route('admin.units.remove-semester'), {
        unit_ids: unitIds
      }, {
        onSuccess: () => {
          toast.success('Units removed from semester successfully!');
        },
        onError: (errors) => {
          toast.error(errors.error || 'Failed to remove units from semester');
        },
        onFinish: () => setLoading(false)
      });
    }
  };

  return (
    <AuthenticatedLayout>
      <Head title="Assign Units to Semesters" />
      
      <div className="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-cyan-50 py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          
          {/* Header */}
          <div className="mb-8">
            <div className="bg-white/80 backdrop-blur-sm rounded-3xl shadow-2xl border border-slate-200/50 p-8">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <h1 className="text-4xl font-bold bg-gradient-to-r from-slate-800 via-blue-800 to-indigo-800 bg-clip-text text-transparent mb-2">
                    Assign Units to Semesters
                  </h1>
                  <p className="text-slate-600 text-lg">
                    Manage unit assignments across different semesters
                  </p>
                  <div className="flex items-center gap-6 mt-4">
                    <div className="text-sm text-slate-600">
                      Total Units: <span className="font-semibold">{units.length}</span>
                    </div>
                    <div className="text-sm text-slate-600">
                      Unassigned: <span className="font-semibold text-red-600">{unassignedUnits.length}</span>
                    </div>
                    <div className="text-sm text-slate-600">
                      Assigned: <span className="font-semibold text-green-600">{assignedUnits.length}</span>
                    </div>
                  </div>
                </div>
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

          {/* Assignment Panel */}
          {selectedUnits.size > 0 && (
            <div className="bg-emerald-50 border border-emerald-200 rounded-xl p-6 mb-6">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div className="flex items-center">
                  <UserCheck className="w-5 h-5 text-emerald-600 mr-2" />
                  <span className="text-emerald-800 font-medium">
                    {selectedUnits.size} unit(s) selected for assignment
                  </span>
                </div>
                <div className="flex items-center gap-4">
                  <select
                    value={selectedSemester}
                    onChange={(e) => setSelectedSemester(e.target.value ? parseInt(e.target.value) : '')}
                    className="px-4 py-2 border border-emerald-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 bg-white"
                  >
                    <option value="">Select Semester</option>
                    {semesters.map(semester => (
                      <option key={semester.id} value={semester.id}>
                        {semester.name}
                      </option>
                    ))}
                  </select>
                  <button
                    onClick={handleAssignToSemester}
                    disabled={loading || !selectedSemester}
                    className="px-6 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed font-medium"
                  >
                    {loading ? 'Assigning...' : 'Assign to Semester'}
                  </button>
                  <button
                    onClick={clearSelection}
                    className="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium"
                  >
                    Clear Selection
                  </button>
                </div>
              </div>
            </div>
          )}

          {/* Filters */}
          <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-lg border border-slate-200/50 p-6 mb-6">
            <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
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
                value={assignmentFilter}
                onChange={(e) => setAssignmentFilter(e.target.value)}
                className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="all">All Units</option>
                <option value="assigned">Assigned</option>
                <option value="unassigned">Unassigned</option>
              </select>
            </div>
            
            {unassignedUnits.length > 0 && assignmentFilter !== 'assigned' && (
              <div className="flex items-center justify-between mt-4 pt-4 border-t border-gray-200">
                <span className="text-sm text-gray-600">
                  {unassignedUnits.length} unassigned units found
                </span>
                <button
                  onClick={selectAllUnassigned}
                  className="text-sm text-blue-600 hover:text-blue-800 font-medium"
                >
                  Select All Unassigned
                </button>
              </div>
            )}
          </div>

          {/* Units Grid */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            {/* Unassigned Units */}
            {(assignmentFilter === 'all' || assignmentFilter === 'unassigned') && (
              <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-lg border border-slate-200/50 overflow-hidden">
                <div className="bg-red-50 px-6 py-4 border-b border-red-200">
                  <div className="flex items-center">
                    <AlertCircle className="w-5 h-5 text-red-600 mr-2" />
                    <h2 className="text-lg font-semibold text-red-800">
                      Unassigned Units ({unassignedUnits.length})
                    </h2>
                  </div>
                </div>
                <div className="max-h-96 overflow-y-auto">
                  {unassignedUnits.length === 0 ? (
                    <div className="p-8 text-center">
                      <Calendar className="w-12 h-12 text-gray-400 mx-auto mb-4" />
                      <p className="text-gray-500">All units are assigned to semesters</p>
                    </div>
                  ) : (
                    <div className="divide-y divide-gray-200">
                      {unassignedUnits.map(unit => (
                        <div 
                          key={unit.id} 
                          className={`p-4 hover:bg-gray-50 cursor-pointer transition-colors ${
                            selectedUnits.has(unit.id) ? 'bg-blue-50 border-l-4 border-blue-500' : ''
                          }`}
                          onClick={() => toggleUnitSelection(unit.id)}
                        >
                          <div className="flex items-center justify-between">
                            <div className="flex items-center">
                              <input
                                type="checkbox"
                                checked={selectedUnits.has(unit.id)}
                                onChange={() => toggleUnitSelection(unit.id)}
                                className="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2"
                              />
                              <BookOpen className="w-8 h-8 text-red-500 ml-3 mr-3" />
                              <div>
                                <div className="text-sm font-medium text-gray-900">{unit.name}</div>
                                <div className="text-xs text-blue-600 font-semibold">{unit.code}</div>
                              </div>
                            </div>
                            <div className="text-xs text-gray-500">
                              <div className="flex items-center">
                                <Clock className="w-3 h-3 mr-1" />
                                {unit.credit_hours}h
                              </div>
                            </div>
                          </div>
                          <div className="mt-2 ml-12">
                            <div className="flex items-center text-xs text-gray-600">
                              <Building2 className="w-3 h-3 mr-1" />
                              <span className="mr-4">{unit.school_code}</span>
                              <GraduationCap className="w-3 h-3 mr-1" />
                              <span>{unit.program_code}</span>
                            </div>
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              </div>
            )}

            {/* Assigned Units */}
            {(assignmentFilter === 'all' || assignmentFilter === 'assigned') && (
              <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-lg border border-slate-200/50 overflow-hidden">
                <div className="bg-green-50 px-6 py-4 border-b border-green-200">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center">
                      <Check className="w-5 h-5 text-green-600 mr-2" />
                      <h2 className="text-lg font-semibold text-green-800">
                        Assigned Units ({assignedUnits.length})
                      </h2>
                    </div>
                  </div>
                </div>
                <div className="max-h-96 overflow-y-auto">
                  {assignedUnits.length === 0 ? (
                    <div className="p-8 text-center">
                      <Users className="w-12 h-12 text-gray-400 mx-auto mb-4" />
                      <p className="text-gray-500">No units assigned yet</p>
                    </div>
                  ) : (
                    <div className="divide-y divide-gray-200">
                      {assignedUnits.map(unit => (
                        <div key={unit.id} className="p-4 hover:bg-gray-50">
                          <div className="flex items-center justify-between">
                            <div className="flex items-center">
                              <BookOpen className="w-8 h-8 text-green-500 mr-3" />
                              <div>
                                <div className="text-sm font-medium text-gray-900">{unit.name}</div>
                                <div className="text-xs text-blue-600 font-semibold">{unit.code}</div>
                                <div className="flex items-center text-xs text-green-600 mt-1">
                                  <Calendar className="w-3 h-3 mr-1" />
                                  {unit.semester_name}
                                  {unit.is_active && (
                                    <span className="ml-2 px-2 py-0.5 bg-green-100 text-green-700 rounded-full text-xs font-medium">
                                      Active
                                    </span>
                                  )}
                                </div>
                              </div>
                            </div>
                            <div className="flex items-center gap-2">
                              <div className="text-xs text-gray-500">
                                <Clock className="w-3 h-3 inline mr-1" />
                                {unit.credit_hours}h
                              </div>
                              <button
                                onClick={() => handleRemoveFromSemester([unit.id])}
                                className="text-red-600 hover:text-red-800 p-1 rounded hover:bg-red-50"
                                title="Remove from semester"
                              >
                                <X className="w-4 h-4" />
                              </button>
                            </div>
                          </div>
                          <div className="mt-2 ml-12">
                            <div className="flex items-center text-xs text-gray-600">
                              <Building2 className="w-3 h-3 mr-1" />
                              <span className="mr-4">{unit.school_code}</span>
                              <GraduationCap className="w-3 h-3 mr-1" />
                              <span>{unit.program_code}</span>
                            </div>
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              </div>
            )}
          </div>

          {/* Summary Stats */}
          <div className="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div className="bg-white/95 backdrop-blur-sm rounded-xl shadow-lg border border-slate-200/50 p-6">
              <div className="flex items-center">
                <BookOpen className="w-8 h-8 text-blue-500 mr-3" />
                <div>
                  <div className="text-2xl font-bold text-gray-900">{units.length}</div>
                  <div className="text-sm text-gray-600">Total Units</div>
                </div>
              </div>
            </div>
            
            <div className="bg-white/95 backdrop-blur-sm rounded-xl shadow-lg border border-slate-200/50 p-6">
              <div className="flex items-center">
                <AlertCircle className="w-8 h-8 text-red-500 mr-3" />
                <div>
                  <div className="text-2xl font-bold text-red-600">{unassignedUnits.length}</div>
                  <div className="text-sm text-gray-600">Unassigned Units</div>
                </div>
              </div>
            </div>
            
            <div className="bg-white/95 backdrop-blur-sm rounded-xl shadow-lg border border-slate-200/50 p-6">
              <div className="flex items-center">
                <Check className="w-8 h-8 text-green-500 mr-3" />
                <div>
                  <div className="text-2xl font-bold text-green-600">{assignedUnits.length}</div>
                  <div className="text-sm text-gray-600">Assigned Units</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}