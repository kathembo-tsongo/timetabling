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
  AlertCircle,
  School
} from 'lucide-react';

type Unit = {
  id: number;
  code: string;
  name: string;
  program_id: number;
  program: {
    id: number;
    code: string;
    name: string;
    school: {
      id: number;
      code: string;
      name: string;
    };
  };
  credit_hours: number;
  is_active: boolean;
  assignments?: UnitAssignment[];
};

type UnitAssignment = {
  id: number;
  unit_id: number;
  semester_id: number;
  class_id: number;
  program_id: number;
  is_active: boolean;
  semester: {
    id: number;
    name: string;
  };
  class: {
    id: number;
    name: string;
    section: string;
    year_level: number;
  };
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
  school_id: number;
};

type Semester = {
  id: number;
  name: string;
  is_active: boolean;
};

type Class = {
  id: number;
  name: string;
  section: string;
  display_name: string;
  year_level: number;
  capacity: number;
};

type PageProps = {
  unassigned_units: Unit[];
  assigned_units: UnitAssignment[];
  schools: School[];
  programs: Program[];
  semesters: Semester[];
  classes: Class[];
  filters: {
    search?: string;
    semester_id?: number;
    program_id?: number;
    class_id?: number;
  };
  stats: {
    total_units: number;
    unassigned_count: number;
    assigned_count: number;
  };
  flash?: {
    success?: string;
    error?: string;
  };
};

export default function UnitAssignments() {
  const { props } = usePage<PageProps>();
  const { 
    unassigned_units = [], 
    assigned_units = [],
    schools = [], 
    programs = [], 
    semesters = [],
    classes = [],
    filters = {},
    stats = {
      total_units: 0,
      unassigned_count: 0,
      assigned_count: 0
    },
    flash
  } = props;

  // State management
  const [selectedUnits, setSelectedUnits] = useState<Set<number>>(new Set());
  const [selectedSemester, setSelectedSemester] = useState<number | ''>(filters.semester_id || '');
  const [selectedProgram, setSelectedProgram] = useState<number | ''>(filters.program_id || '');
  const [selectedClass, setSelectedClass] = useState<number | ''>(filters.class_id || '');
  const [availableClasses, setAvailableClasses] = useState<Class[]>(classes);
  const [loading, setLoading] = useState(false);
  
  // Filter state
  const [searchTerm, setSearchTerm] = useState(filters.search || '');
  const [selectedSchoolFilter, setSelectedSchoolFilter] = useState<string | number>('');

  // Handle flash messages
  useEffect(() => {
    if (flash?.success) {
      toast.success(flash.success);
    }
    if (flash?.error) {
      toast.error(flash.error);
    }
  }, [flash]);

  // Load classes when semester and program change
  useEffect(() => {
    if (selectedSemester && selectedProgram) {
      fetchClasses();
    } else {
      setAvailableClasses([]);
      setSelectedClass('');
    }
  }, [selectedSemester, selectedProgram]);

  const fetchClasses = async () => {
    try {
      const response = await fetch(`/api/classes/by-program-semester?program_id=${selectedProgram}&semester_id=${selectedSemester}`);
      const data = await response.json();
      setAvailableClasses(data);
    } catch (error) {
      console.error('Failed to fetch classes:', error);
      toast.error('Failed to load classes');
    }
  };

  // Filter unassigned units
  const filteredUnassignedUnits = unassigned_units.filter(unit => {
    const matchesSearch = !searchTerm || 
      unit.name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
      unit.code?.toLowerCase().includes(searchTerm.toLowerCase());
    
    const matchesProgram = !selectedProgram || unit.program_id == selectedProgram;
    const matchesSchool = !selectedSchoolFilter || unit.program.school.id == selectedSchoolFilter;
    
    return matchesSearch && matchesProgram && matchesSchool;
  });

  // Filter assigned units
  const filteredAssignedUnits = assigned_units.filter(assignment => {
    const matchesSearch = !searchTerm || 
      assignment.unit?.name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
      assignment.unit?.code?.toLowerCase().includes(searchTerm.toLowerCase());
    
    const matchesSemester = !selectedSemester || assignment.semester_id == selectedSemester;
    const matchesProgram = !selectedProgram || assignment.program_id == selectedProgram;
    const matchesClass = !selectedClass || assignment.class_id == selectedClass;
    
    return matchesSearch && matchesSemester && matchesProgram && matchesClass;
  });

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

  // Select all filtered unassigned units
  const selectAllUnassigned = () => {
    setSelectedUnits(new Set(filteredUnassignedUnits.map(unit => unit.id)));
  };

  // Clear selection
  const clearSelection = () => {
    setSelectedUnits(new Set());
  };

  // Assign units to class
  const handleAssignToClass = () => {
    if (selectedUnits.size === 0) {
      toast.error('Please select at least one unit');
      return;
    }
    
    if (!selectedSemester) {
      toast.error('Please select a semester');
      return;
    }

    if (!selectedClass) {
      toast.error('Please select a class');
      return;
    }

    setLoading(true);
    router.post(route('admin.units.assign-semester'), {
      unit_ids: Array.from(selectedUnits),
      semester_id: selectedSemester,
      class_id: selectedClass
    }, {
      onSuccess: () => {
        setSelectedUnits(new Set());
        // Refresh the page to see updated data
        router.reload();
      },
      onError: (errors) => {
        toast.error(errors.error || 'Failed to assign units to class');
      },
      onFinish: () => setLoading(false)
    });
  };

  // Remove assignments
  const handleRemoveAssignments = (assignmentIds: number[]) => {
    if (assignmentIds.length === 0) return;

    if (confirm(`Are you sure you want to remove ${assignmentIds.length} assignment(s)?`)) {
      setLoading(true);
      router.post(route('admin.units.remove-semester'), {
        assignment_ids: assignmentIds
      }, {
        onSuccess: () => {
          router.reload();
        },
        onError: (errors) => {
          toast.error(errors.error || 'Failed to remove assignments');
        },
        onFinish: () => setLoading(false)
      });
    }
  };

  // Apply filters
  const applyFilters = () => {
    const params = new URLSearchParams();
    if (searchTerm) params.append('search', searchTerm);
    if (selectedSemester) params.append('semester_id', selectedSemester.toString());
    if (selectedProgram) params.append('program_id', selectedProgram.toString());
    if (selectedClass) params.append('class_id', selectedClass.toString());
    
    router.get(`/admin/unit-assignments?${params.toString()}`);
  };

  return (
    <AuthenticatedLayout>
      <Head title="Assign Units to Classes" />
      
      <div className="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-cyan-50 py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          
          {/* Header */}
          <div className="mb-8">
            <div className="bg-white/80 backdrop-blur-sm rounded-3xl shadow-2xl border border-slate-200/50 p-8">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <h1 className="text-4xl font-bold bg-gradient-to-r from-slate-800 via-blue-800 to-indigo-800 bg-clip-text text-transparent mb-2">
                    Assign Units to Classes
                  </h1>
                  <p className="text-slate-600 text-lg">
                    Manage unit assignments to specific classes within semesters
                  </p>
                  <div className="flex items-center gap-6 mt-4">
                    <div className="text-sm text-slate-600">
                      Total Units: <span className="font-semibold">{stats.total_units}</span>
                    </div>
                    <div className="text-sm text-slate-600">
                      Unassigned: <span className="font-semibold text-red-600">{stats.unassigned_count}</span>
                    </div>
                    <div className="text-sm text-slate-600">
                      Assigned: <span className="font-semibold text-green-600">{stats.assigned_count}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Assignment Panel */}
          {selectedUnits.size > 0 && (
            <div className="bg-emerald-50 border border-emerald-200 rounded-xl p-6 mb-6">
              <div className="flex flex-col gap-4">
                <div className="flex items-center">
                  <UserCheck className="w-5 h-5 text-emerald-600 mr-2" />
                  <span className="text-emerald-800 font-medium">
                    {selectedUnits.size} unit(s) selected for assignment
                  </span>
                </div>
                
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
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

                  <select
                    value={selectedProgram}
                    onChange={(e) => setSelectedProgram(e.target.value ? parseInt(e.target.value) : '')}
                    className="px-4 py-2 border border-emerald-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 bg-white"
                  >
                    <option value="">Select Program</option>
                    {programs.map(program => (
                      <option key={program.id} value={program.id}>
                        {program.code} - {program.name}
                      </option>
                    ))}
                  </select>

                  <select
                    value={selectedClass}
                    onChange={(e) => setSelectedClass(e.target.value ? parseInt(e.target.value) : '')}
                    className="px-4 py-2 border border-emerald-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 bg-white"
                  >
                    <option value="">Select Class</option>
                    {classes.map(cls => (
                      <option key={cls.id} value={cls.id}>
                        {cls.name} - {cls.section}
                      </option>
                    ))}
                  </select>

                  <div className="flex gap-2">
                    <button
                      onClick={handleAssignToClass}
                      disabled={loading || !selectedSemester || !selectedClass}
                      className="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed font-medium"
                    >
                      {loading ? 'Assigning...' : 'Assign to Class'}
                    </button>
                    <button
                      onClick={clearSelection}
                      className="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium border border-gray-300 rounded-lg"
                    >
                      Clear
                    </button>
                  </div>
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
                value={selectedSchoolFilter}
                onChange={(e) => setSelectedSchoolFilter(e.target.value)}
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
                onChange={(e) => setSelectedProgram(e.target.value ? parseInt(e.target.value) : '')}
                className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="">All Programs</option>
                {programs.map(program => (
                  <option key={program.id} value={program.id}>
                    {program.code}
                  </option>
                ))}
              </select>

              <select
                value={selectedSemester}
                onChange={(e) => setSelectedSemester(e.target.value ? parseInt(e.target.value) : '')}
                className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="">All Semesters</option>
                {semesters.map(semester => (
                  <option key={semester.id} value={semester.id}>
                    {semester.name}
                  </option>
                ))}
              </select>

              {/* <button
                onClick={applyFilters}
                className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium"
              >
                Apply Filters
              </button> */}
            </div>
            
            {filteredUnassignedUnits.length > 0 && (
              <div className="flex items-center justify-between mt-4 pt-4 border-t border-gray-200">
                <span className="text-sm text-gray-600">
                  {filteredUnassignedUnits.length} unassigned units found
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
            <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-lg border border-slate-200/50 overflow-hidden">
              <div className="bg-red-50 px-6 py-4 border-b border-red-200">
                <div className="flex items-center">
                  <AlertCircle className="w-5 h-5 text-red-600 mr-2" />
                  <h2 className="text-lg font-semibold text-red-800">
                    Unassigned Units ({filteredUnassignedUnits.length})
                  </h2>
                </div>
              </div>
              <div className="max-h-96 overflow-y-auto">
                {filteredUnassignedUnits.length === 0 ? (
                  <div className="p-8 text-center">
                    <Calendar className="w-12 h-12 text-gray-400 mx-auto mb-4" />
                    <p className="text-gray-500">No unassigned units found</p>
                  </div>
                ) : (
                  <div className="divide-y divide-gray-200">
                    {filteredUnassignedUnits.map(unit => (
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
                            <span className="mr-4">{unit.program.school.code}</span>
                            <GraduationCap className="w-3 h-3 mr-1" />
                            <span>{unit.program.code}</span>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>

            {/* Assigned Units */}
            <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-lg border border-slate-200/50 overflow-hidden">
              <div className="bg-green-50 px-6 py-4 border-b border-green-200">
                <div className="flex items-center justify-between">
                  <div className="flex items-center">
                    <Check className="w-5 h-5 text-green-600 mr-2" />
                    <h2 className="text-lg font-semibold text-green-800">
                      Assigned Units ({filteredAssignedUnits.length})
                    </h2>
                  </div>
                </div>
              </div>
              <div className="max-h-96 overflow-y-auto">
                {filteredAssignedUnits.length === 0 ? (
                  <div className="p-8 text-center">
                    <Users className="w-12 h-12 text-gray-400 mx-auto mb-4" />
                    <p className="text-gray-500">No assigned units found</p>
                  </div>
                ) : (
                  <div className="divide-y divide-gray-200">
                    {filteredAssignedUnits.map(assignment => (
                      <div key={assignment.id} className="p-4 hover:bg-gray-50">
                        <div className="flex items-center justify-between">
                          <div className="flex items-center">
                            <BookOpen className="w-8 h-8 text-green-500 mr-3" />
                            <div>
                              <div className="text-sm font-medium text-gray-900">{assignment.unit?.name}</div>
                              <div className="text-xs text-blue-600 font-semibold">{assignment.unit?.code}</div>
                              <div className="flex items-center text-xs text-green-600 mt-1">
                                <Calendar className="w-3 h-3 mr-1" />
                                {assignment.semester?.name}
                                <School className="w-3 h-3 ml-2 mr-1" />
                                {assignment.class?.name} Section {assignment.class?.section}
                              </div>
                            </div>
                          </div>
                          <button
                            onClick={() => handleRemoveAssignments([assignment.id])}
                            className="text-red-600 hover:text-red-800 p-1 rounded hover:bg-red-50"
                            title="Remove assignment"
                          >
                            <X className="w-4 h-4" />
                          </button>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* Summary Stats */}
          <div className="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div className="bg-white/95 backdrop-blur-sm rounded-xl shadow-lg border border-slate-200/50 p-6">
              <div className="flex items-center">
                <BookOpen className="w-8 h-8 text-blue-500 mr-3" />
                <div>
                  <div className="text-2xl font-bold text-gray-900">{stats.total_units}</div>
                  <div className="text-sm text-gray-600">Total Units</div>
                </div>
              </div>
            </div>
            
            <div className="bg-white/95 backdrop-blur-sm rounded-xl shadow-lg border border-slate-200/50 p-6">
              <div className="flex items-center">
                <AlertCircle className="w-8 h-8 text-red-500 mr-3" />
                <div>
                  <div className="text-2xl font-bold text-red-600">{stats.unassigned_count}</div>
                  <div className="text-sm text-gray-600">Unassigned Units</div>
                </div>
              </div>
            </div>
            
            <div className="bg-white/95 backdrop-blur-sm rounded-xl shadow-lg border border-slate-200/50 p-6">
              <div className="flex items-center">
                <Check className="w-8 h-8 text-green-500 mr-3" />
                <div>
                  <div className="text-2xl font-bold text-green-600">{stats.assigned_count}</div>
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