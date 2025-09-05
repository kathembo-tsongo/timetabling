import React, { useState, useEffect } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { toast } from 'react-hot-toast';
import {
  Users,
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
  BookOpen,
  UserCheck,
  UserX,
  School,
  AlertTriangle,
  Info,
  ChevronLeft,
  ChevronRight,
  MoreHorizontal,
  Award,
  Target,
  CheckSquare
} from 'lucide-react';

// Type definitions
type Unit = {
  id: number;
  code: string;
  name: string;
  credit_hours: number;
  school_id: number;
  program_id: number;
  semester_id: number;
  is_active: boolean;
  is_assigned?: boolean;
  assignment?: {
    lecturer_name: string;
    lecturer_code: string;
  };
  school: {
    id: number;
    name: string;
    code: string;
  };
  program: {
    id: number;
    name: string;
    code: string;
  };
  semester: {
    id: number;
    name: string;
  };
};

type Assignment = {
  id: number;
  code: string;
  name: string;
  credit_hours: number;
  school_id: number;
  program_id: number;
  lecturer_code?: string;
  lecturer_name?: string;
  lecturer_email?: string;
  assigned_semester_id?: number;
  school: {
    id: number;
    name: string;
    code: string;
  };
  program: {
    id: number;
    name: string;
    code: string;
  };
  semester: {
    id: number;
    name: string;
  };
};

type Lecturer = {
  id: number;
  code: string;
  name: string;
  email: string;
  school_id?: number;
  current_workload?: number;
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
  school: School;
};

type Semester = {
  id: number;
  name: string;
  is_active: boolean;
};

type PaginationData = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number;
  to: number;
  data: Assignment[];
  links: Array<{
    url: string | null;
    label: string;
    active: boolean;
  }>;
};

type PageProps = {
  assignments: PaginationData;
  semesters: Semester[];
  schools: School[];
  programs: Program[];
  lecturers: Lecturer[];
  stats: {
    total_units: number;
    assigned_units: number;
    unassigned_units: number;
    total_lecturers: number;
  };
  can: {
    create: boolean;
    update: boolean;
    delete: boolean;
  };
  filters: {
    search?: string;
    semester_id?: string | number;
    school_id?: string | number;
    program_id?: string | number;
  };
  flash?: {
    success?: string;
    error?: string;
  };
  auth: {
    user: any;
  };
};

export default function LecturerAssignmentIndex() {
  const { props } = usePage<PageProps>();
  const { 
    assignments, 
    semesters = [], 
    schools = [], 
    programs = [], 
    lecturers = [],
    stats, 
    can = { create: false, update: false, delete: false }, 
    filters = {}, 
    flash,
    auth
  } = props;

  // State management
  const [isAssignModalOpen, setIsAssignModalOpen] = useState(false);
  const [isBulkAssignModalOpen, setIsBulkAssignModalOpen] = useState(false);
  const [isViewModalOpen, setIsViewModalOpen] = useState(false);
  const [selectedAssignment, setSelectedAssignment] = useState<Assignment | null>(null);
  const [loading, setLoading] = useState(false);
  const [showFilters, setShowFilters] = useState(false);

  // Form state for single assignment
  const [assignmentForm, setAssignmentForm] = useState({
    unit_id: '',
    semester_id: '',
    lecturer_code: ''
  });

  // Form state for bulk assignment
  const [bulkAssignmentForm, setBulkAssignmentForm] = useState({
    unit_ids: [] as number[],
    semester_id: '',
    lecturer_code: ''
  });

  // Filter state
  const [searchTerm, setSearchTerm] = useState(filters?.search || '');
  const [selectedSemester, setSelectedSemester] = useState<string | number>(filters?.semester_id || '');
  const [selectedSchool, setSelectedSchool] = useState<string | number>(filters?.school_id || '');
  const [selectedProgram, setSelectedProgram] = useState<string | number>(filters?.program_id || '');

  // Available lecturers for assignment (filtered by school/workload)
  const [availableLecturers, setAvailableLecturers] = useState<Lecturer[]>(lecturers);
  const [lecturerWorkload, setLecturerWorkload] = useState<any>(null);
  const [loadingWorkload, setLoadingWorkload] = useState(false);

  // Bulk assignment specific state
  const [availableUnits, setAvailableUnits] = useState<Unit[]>([]);
  const [loadingUnits, setLoadingUnits] = useState(false);

  // Handle flash messages
  useEffect(() => {
    if (flash?.success) {
      toast.success(flash.success);
    }
    if (flash?.error) {
      toast.error(flash.error);
    }
  }, [flash]);

  // Filter programs based on school selection
  const filteredPrograms = programs.filter(program => 
    !selectedSchool || program.school_id === selectedSchool
  );

  // Filter lecturers based on school
  useEffect(() => {
    if (selectedSchool) {
      const filtered = lecturers.filter(lecturer => 
        !lecturer.school_id || lecturer.school_id === selectedSchool
      );
      setAvailableLecturers(filtered);
    } else {
      setAvailableLecturers(lecturers);
    }
  }, [selectedSchool, lecturers]);

  // Fetch available units for bulk assignment
  const fetchAvailableUnits = async (semesterId: string | number) => {
    if (!semesterId) {
      setAvailableUnits([]);
      return;
    }
    
    setLoadingUnits(true);
    try {
      const params = new URLSearchParams({
        semester_id: semesterId.toString(),
        ...(selectedSchool && { school_id: selectedSchool.toString() }),
        ...(selectedProgram && { program_id: selectedProgram.toString() })
      });
      
      const response = await fetch(`/admin/lecturer-assignments/available-units?${params}`);
      if (response.ok) {
        const data = await response.json();
        setAvailableUnits(data.units || []);
      } else {
        console.error('Failed to fetch units');
        setAvailableUnits([]);
      }
    } catch (error) {
      console.error('Error fetching units:', error);
      setAvailableUnits([]);
      toast.error('Failed to load units');
    } finally {
      setLoadingUnits(false);
    }
  };

  // Fetch lecturer workload when lecturer is selected
  const fetchLecturerWorkload = async (lecturerCode: string, semesterId: string | number) => {
    if (!lecturerCode || !semesterId) return;
    
    setLoadingWorkload(true);
    try {
      const response = await fetch(`/admin/lecturer-assignments/workload?lecturer_code=${lecturerCode}&semester_id=${semesterId}`);
      if (response.ok) {
        const data = await response.json();
        setLecturerWorkload(data);
      } else {
        setLecturerWorkload(null);
      }
    } catch (error) {
      console.error('Failed to fetch lecturer workload:', error);
      setLecturerWorkload(null);
    } finally {
      setLoadingWorkload(false);
    }
  };

  // Search and filter functions
  const handleSearch = () => {
    router.get('/admin/lecturer-assignments', {
      search: searchTerm,
      semester_id: selectedSemester,
      school_id: selectedSchool,
      program_id: selectedProgram,
    }, {
      preserveState: true,
      replace: true
    });
  };

  const clearFilters = () => {
    setSearchTerm('');
    setSelectedSemester('');
    setSelectedSchool('');
    setSelectedProgram('');
    router.get('/admin/lecturer-assignments', {}, {
      preserveState: true,
      replace: true
    });
  };

  // Assignment functions
  const handleAssignLecturer = (assignment: Assignment) => {
    setSelectedAssignment(assignment);
    setAssignmentForm({
      unit_id: assignment.id.toString(),
      semester_id: selectedSemester.toString() || '',
      lecturer_code: assignment.lecturer_code || ''
    });
    setIsAssignModalOpen(true);
  };

  const handleBulkAssign = () => {
    setBulkAssignmentForm({
      unit_ids: [],
      semester_id: selectedSemester.toString() || '',
      lecturer_code: ''
    });
    setIsBulkAssignModalOpen(true);
    // Fetch available units when opening bulk assign modal
    if (selectedSemester) {
      fetchAvailableUnits(selectedSemester);
    }
  };

  const handleViewAssignment = (assignment: Assignment) => {
    setSelectedAssignment(assignment);
    if (assignment.lecturer_code && assignmentForm.semester_id) {
      fetchLecturerWorkload(assignment.lecturer_code, assignmentForm.semester_id);
    }
    setIsViewModalOpen(true);
  };

  const handleRemoveAssignment = (assignment: Assignment) => {
    if (!selectedSemester) {
      toast.error('Please select a semester first');
      return;
    }

    if (confirm(`Remove lecturer assignment for ${assignment.code} - ${assignment.name}?`)) {
      router.delete(`/admin/lecturer-assignments/${assignment.id}/${selectedSemester}`, {
        onSuccess: () => {
          toast.success('Lecturer assignment removed successfully!');
        },
        onError: () => {
          toast.error('Failed to remove assignment');
        }
      });
    }
  };

  const submitAssignment = () => {
    if (!assignmentForm.unit_id || !assignmentForm.semester_id || !assignmentForm.lecturer_code) {
      toast.error('Please fill in all fields');
      return;
    }

    setLoading(true);

    const isUpdate = selectedAssignment?.lecturer_code;
    const url = isUpdate 
      ? `/admin/lecturer-assignments/${assignmentForm.unit_id}/${assignmentForm.semester_id}`
      : '/admin/lecturer-assignments';

    const method = isUpdate ? 'put' : 'post';

    router[method](url, assignmentForm, {
      onSuccess: () => {
        toast.success(isUpdate ? 'Assignment updated successfully!' : 'Lecturer assigned successfully!');
        setIsAssignModalOpen(false);
        setSelectedAssignment(null);
      },
      onError: (errors) => {
        toast.error(errors.error || 'Failed to assign lecturer');
      },
      onFinish: () => {
        setLoading(false);
      }
    });
  };

  const submitBulkAssignment = () => {
    if (!bulkAssignmentForm.semester_id || !bulkAssignmentForm.lecturer_code || bulkAssignmentForm.unit_ids.length === 0) {
      toast.error('Please fill in all fields and select at least one unit');
      return;
    }

    setLoading(true);

    router.post('/admin/lecturer-assignments/bulk', bulkAssignmentForm, {
      onSuccess: () => {
        toast.success('Bulk assignment completed successfully!');
        setIsBulkAssignModalOpen(false);
        setBulkAssignmentForm({
          unit_ids: [],
          semester_id: '',
          lecturer_code: ''
        });
      },
      onError: (errors) => {
        toast.error(errors.error || 'Failed to complete bulk assignment');
      },
      onFinish: () => {
        setLoading(false);
      }
    });
  };

  const handleUnitToggle = (unitId: number) => {
    setBulkAssignmentForm(prev => ({
      ...prev,
      unit_ids: prev.unit_ids.includes(unitId)
        ? prev.unit_ids.filter(id => id !== unitId)
        : [...prev.unit_ids, unitId]
    }));
  };

  const selectAllUnassigned = () => {
    const unassignedUnits = availableUnits
      .filter(unit => !unit.is_assigned)
      .map(unit => unit.id);
    setBulkAssignmentForm(prev => ({ ...prev, unit_ids: unassignedUnits }));
  };

  const selectAllUnits = () => {
    const allUnitIds = availableUnits.map(unit => unit.id);
    setBulkAssignmentForm(prev => ({ ...prev, unit_ids: allUnitIds }));
  };

  // Watch for semester changes in bulk assignment
  useEffect(() => {
    if (isBulkAssignModalOpen && bulkAssignmentForm.semester_id) {
      fetchAvailableUnits(bulkAssignmentForm.semester_id);
    }
  }, [bulkAssignmentForm.semester_id, isBulkAssignModalOpen]);

  // Status badge component
  const StatusBadge: React.FC<{ assignment: Assignment }> = ({ assignment }) => {
    if (assignment.lecturer_code) {
      return (
        <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium text-white bg-green-500">
          <UserCheck className="w-3 h-3 mr-1" />
          Assigned
        </span>
      );
    }
    return (
      <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium text-white bg-red-500">
        <UserX className="w-3 h-3 mr-1" />
        Unassigned
      </span>
    );
  };

  // Pagination Component
  const Pagination: React.FC<{ paginationData: PaginationData }> = ({ paginationData }) => {
    const { current_page, last_page, from, to, total, links } = paginationData;

    if (last_page <= 1) return null;

    const handlePaginationClick = (url: string | null) => {
      if (url) {
        router.get(url, {}, {
          preserveState: true,
          replace: true
        });
      }
    };

    return (
      <div className="flex items-center justify-between px-6 py-4 bg-gray-50 border-t border-gray-200">
        <div className="text-sm text-gray-700">
          Showing <span className="font-medium">{from}</span> to <span className="font-medium">{to}</span> of{' '}
          <span className="font-medium">{total}</span> results
        </div>
        
        <div className="flex items-center space-x-1">
          {links.map((link, index) => {
            if (link.label === '&laquo; Previous') {
              return (
                <button
                  key={index}
                  onClick={() => handlePaginationClick(link.url)}
                  disabled={!link.url}
                  className="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <ChevronLeft className="w-4 h-4" />
                </button>
              );
            }
            
            if (link.label === 'Next &raquo;') {
              return (
                <button
                  key={index}
                  onClick={() => handlePaginationClick(link.url)}
                  disabled={!link.url}
                  className="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <ChevronRight className="w-4 h-4" />
                </button>
              );
            }
            
            if (link.label === '...') {
              return (
                <span key={index} className="px-3 py-2 text-sm font-medium text-gray-700">
                  <MoreHorizontal className="w-4 h-4" />
                </span>
              );
            }
            
            return (
              <button
                key={index}
                onClick={() => handlePaginationClick(link.url)}
                className={`px-3 py-2 text-sm font-medium border rounded-md ${
                  link.active
                    ? 'z-10 bg-emerald-50 border-emerald-500 text-emerald-600'
                    : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                }`}
              >
                {link.label}
              </button>
            );
          })}
        </div>
      </div>
    );
  };

  return (
    <AuthenticatedLayout user={auth.user}>
      <Head title="Lecturer Assignments" />
      
      <div className="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-cyan-50 py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          
          {/* Header */}
          <div className="mb-8">
            <div className="bg-white/80 backdrop-blur-sm rounded-3xl shadow-2xl border border-slate-200/50 p-8">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <h1 className="text-4xl font-bold bg-gradient-to-r from-slate-800 via-blue-800 to-indigo-800 bg-clip-text text-transparent mb-2">
                    Lecturer Assignments
                  </h1>
                  <p className="text-slate-600 text-lg">
                    Assign lecturers to teach specific units in each semester
                  </p>
                  {stats && (
                    <div className="flex items-center gap-6 mt-4">
                      <div className="text-sm text-slate-600">
                        Total Units: <span className="font-semibold">{stats.total_units}</span>
                      </div>
                      <div className="text-sm text-green-600">
                        Assigned: <span className="font-semibold">{stats.assigned_units}</span>
                      </div>
                      <div className="text-sm text-red-600">
                        Unassigned: <span className="font-semibold">{stats.unassigned_units}</span>
                      </div>
                      <div className="text-sm text-slate-600">
                        Available Lecturers: <span className="font-semibold">{stats.total_lecturers}</span>
                      </div>
                    </div>
                  )}
                </div>
                
                <div className="flex items-center gap-3">
                  {can.create && (
                    <>
                      <button
                        onClick={handleBulkAssign}
                        className="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-500 via-blue-600 to-indigo-600 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl hover:from-blue-600 hover:via-blue-700 hover:to-indigo-700 transform hover:scale-105 transition-all duration-300"
                      >
                        <CheckSquare className="w-4 h-4 mr-2" />
                        Bulk Assign
                      </button>
                    </>
                  )}
                </div>
              </div>
            </div>
          </div>

          {/* Search and Filters */}
          <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-lg border border-slate-200/50 mb-8 overflow-hidden">
            <div className="p-6">
              <div className="flex flex-col lg:flex-row gap-4 items-start lg:items-center justify-between">
                {/* Search Bar */}
                <div className="flex-1 max-w-md">
                  <div className="relative">
                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-5 h-5" />
                    <input
                      type="text"
                      placeholder="Search units or lecturers..."
                      value={searchTerm}
                      onChange={(e) => setSearchTerm(e.target.value)}
                      onKeyPress={(e) => e.key === 'Enter' && handleSearch()}
                      className="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                    />
                  </div>
                </div>

                {/* Filter Toggle Button */}
                <div className="flex items-center gap-3">
                  <button
                    onClick={() => setShowFilters(!showFilters)}
                    className="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors"
                  >
                    <Filter className="w-4 h-4 mr-2" />
                    Filters
                    {showFilters ? <ChevronUp className="w-4 h-4 ml-1" /> : <ChevronDown className="w-4 h-4 ml-1" />}
                  </button>
                  
                  <button
                    onClick={handleSearch}
                    className="inline-flex items-center px-6 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors"
                  >
                    <Search className="w-4 h-4 mr-2" />
                    Search
                  </button>
                </div>
              </div>

              {/* Advanced Filters */}
              {showFilters && (
                <div className="mt-6 pt-6 border-t border-gray-200">
                  <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    {/* Semester Filter */}
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Semester *</label>
                      <select
                        value={selectedSemester}
                        onChange={(e) => setSelectedSemester(e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                      >
                        <option value="">Select Semester</option>
                        {semesters.map(semester => (
                          <option key={semester.id} value={semester.id}>
                            {semester.name}
                          </option>
                        ))}
                      </select>
                    </div>

                    {/* School Filter */}
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">School</label>
                      <select
                        value={selectedSchool}
                        onChange={(e) => {
                          setSelectedSchool(e.target.value);
                          setSelectedProgram('');
                        }}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                      >
                        <option value="">All Schools</option>
                        {schools.map(school => (
                          <option key={school.id} value={school.id}>
                            {school.code} - {school.name}
                          </option>
                        ))}
                      </select>
                    </div>

                    {/* Program Filter */}
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Program</label>
                      <select
                        value={selectedProgram}
                        onChange={(e) => setSelectedProgram(e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                        disabled={!selectedSchool}
                      >
                        <option value="">All Programs</option>
                        {filteredPrograms.map(program => (
                          <option key={program.id} value={program.id}>
                            {program.code} - {program.name}
                          </option>
                        ))}
                      </select>
                    </div>

                    {/* Clear Filters Button */}
                    <div className="flex items-end">
                      <button
                        onClick={clearFilters}
                        className="w-full px-4 py-2 text-gray-600 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors"
                      >
                        Clear All
                      </button>
                    </div>
                  </div>

                  {!selectedSemester && (
                    <div className="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                      <div className="flex">
                        <AlertTriangle className="w-5 h-5 text-yellow-600 mr-2" />
                        <div>
                          <p className="text-sm text-yellow-800 font-medium">Semester Required</p>
                          <p className="text-sm text-yellow-700">Please select a semester to view and manage lecturer assignments.</p>
                        </div>
                      </div>
                    </div>
                  )}
                </div>
              )}
            </div>
          </div>

          {/* Assignments Table */}
          <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-2xl border border-slate-200/50 overflow-hidden">
            <div className="p-8">
              {assignments.data && assignments.data.length > 0 ? (
                <>
                  <div className="overflow-x-auto">
                    <table className="w-full">
                      <thead>
                        <tr className="border-b border-gray-200">
                          <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Unit
                          </th>
                          <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            School & Program
                          </th>
                          <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Credit Hours
                          </th>
                          <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Assigned Lecturer
                          </th>
                          <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                          </th>
                          <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                          </th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-gray-200">
                        {assignments.data.map((assignment) => (
                          <tr key={assignment.id} className="hover:bg-gray-50">
                            <td className="px-4 py-4 whitespace-nowrap">
                              <div className="text-sm font-medium text-gray-900">
                                {assignment.code}
                              </div>
                              <div className="text-sm text-gray-500 max-w-xs truncate">
                                {assignment.name}
                              </div>
                            </td>
                            <td className="px-4 py-4 whitespace-nowrap">
                              <div className="text-sm font-medium text-gray-900">
                                {assignment.school?.code}
                              </div>
                              <div className="text-sm text-gray-500">
                                {assignment.program?.code}
                              </div>
                            </td>
                            <td className="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                              <div className="flex items-center">
                                <Award className="w-4 h-4 text-yellow-500 mr-1" />
                                {assignment.credit_hours}
                              </div>
                            </td>
                            <td className="px-4 py-4 whitespace-nowrap">
                              {assignment.lecturer_name ? (
                                <div>
                                  <div className="text-sm font-medium text-gray-900">
                                    {assignment.lecturer_name}
                                  </div>
                                  <div className="text-sm text-gray-500">
                                    {assignment.lecturer_code}
                                  </div>
                                </div>
                              ) : (
                                <div className="text-sm text-gray-400 italic">
                                  Not assigned
                                </div>
                              )}
                            </td>
                            <td className="px-4 py-4 whitespace-nowrap">
                              <StatusBadge assignment={assignment} />
                            </td>
                            <td className="px-4 py-4 whitespace-nowrap text-right text-sm font-medium">
                              <div className="flex space-x-2">
                                <button 
                                  onClick={() => handleViewAssignment(assignment)}
                                  className="text-blue-600 hover:text-blue-900"
                                  title="View Details"
                                >
                                  <Eye className="w-4 h-4" />
                                </button>
                                {can.update && selectedSemester && (
                                  <button 
                                    onClick={() => handleAssignLecturer(assignment)}
                                    className="text-indigo-600 hover:text-indigo-900"
                                    title={assignment.lecturer_code ? "Change Lecturer" : "Assign Lecturer"}
                                  >
                                    <Edit className="w-4 h-4" />
                                  </button>
                                )}
                                {can.delete && assignment.lecturer_code && selectedSemester && (
                                  <button 
                                    onClick={() => handleRemoveAssignment(assignment)}
                                    className="text-red-600 hover:text-red-900"
                                    title="Remove Assignment"
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
                  </div>
                  
                  <Pagination paginationData={assignments} />
                </>
              ) : (
                <div className="text-center py-12">
                  <Users className="mx-auto h-12 w-12 text-gray-400 mb-4" />
                  <h3 className="text-lg font-medium text-gray-900 mb-2">No Assignments Found</h3>
                  <p className="text-gray-500">
                    {!selectedSemester 
                      ? "Please select a semester to view lecturer assignments."
                      : "No units found for the selected criteria."
                    }
                  </p>
                </div>
              )}
            </div>
          </div>

          {/* Assignment Modal */}
          {isAssignModalOpen && (
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
              <div className="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div className="bg-gradient-to-r from-emerald-500 via-emerald-600 to-teal-600 p-6 rounded-t-2xl">
                  <div className="flex items-center justify-between">
                    <h3 className="text-xl font-semibold text-white">
                      {selectedAssignment?.lecturer_code ? 'Update' : 'Assign'} Lecturer
                    </h3>
                    <button
                      onClick={() => setIsAssignModalOpen(false)}
                      className="text-white hover:text-gray-200 transition-colors"
                    >
                      <X className="w-6 h-6" />
                    </button>
                  </div>
                </div>

                <div className="p-6 space-y-6">
                  {selectedAssignment && (
                    <div className="bg-gray-50 p-4 rounded-lg">
                      <h4 className="font-medium text-gray-900 mb-2">Unit Details</h4>
                      <p className="text-sm text-gray-600">Code: <span className="font-medium">{selectedAssignment.code}</span></p>
                      <p className="text-sm text-gray-600">Name: <span className="font-medium">{selectedAssignment.name}</span></p>
                      <p className="text-sm text-gray-600">Credit Hours: <span className="font-medium">{selectedAssignment.credit_hours}</span></p>
                      <p className="text-sm text-gray-600">School: <span className="font-medium">{selectedAssignment.school?.name}</span></p>
                      <p className="text-sm text-gray-600">Program: <span className="font-medium">{selectedAssignment.program?.name}</span></p>
                    </div>
                  )}

                  {/* Semester Selection */}
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Semester *
                    </label>
                    <select
                      value={assignmentForm.semester_id}
                      onChange={(e) => setAssignmentForm(prev => ({ ...prev, semester_id: e.target.value }))}
                      className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                      required
                    >
                      <option value="">Select Semester</option>
                      {semesters.map(semester => (
                        <option key={semester.id} value={semester.id}>
                          {semester.name}
                        </option>
                      ))}
                    </select>
                  </div>

                  {/* Lecturer Selection */}
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Lecturer *
                    </label>
                    <select
                      value={assignmentForm.lecturer_code}
                      onChange={(e) => {
                        setAssignmentForm(prev => ({ ...prev, lecturer_code: e.target.value }));
                        if (e.target.value && assignmentForm.semester_id) {
                          fetchLecturerWorkload(e.target.value, assignmentForm.semester_id);
                        }
                      }}
                      className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                      required
                    >
                      <option value="">Select Lecturer</option>
                      {availableLecturers.map(lecturer => (
                        <option key={lecturer.id} value={lecturer.code}>
                          {lecturer.name} ({lecturer.code}) 
                          {lecturer.current_workload !== undefined && ` - ${lecturer.current_workload} units`}
                        </option>
                      ))}
                    </select>
                  </div>

                  {/* Lecturer Workload Display */}
                  {loadingWorkload && (
                    <div className="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                      <div className="flex items-center">
                        <Info className="w-4 h-4 text-blue-600 mr-2" />
                        <span className="text-sm text-blue-700">Loading lecturer workload...</span>
                      </div>
                    </div>
                  )}

                  {lecturerWorkload && (
                    <div className="p-4 bg-green-50 border border-green-200 rounded-lg">
                      <h4 className="font-medium text-green-800 mb-2">Current Workload</h4>
                      <div className="text-sm text-green-700 space-y-1">
                        <p>Units: {lecturerWorkload.statistics?.total_units || 0}</p>
                        <p>Students: {lecturerWorkload.statistics?.total_students || 0}</p>
                        <p>Credit Hours: {lecturerWorkload.statistics?.total_credit_hours || 0}</p>
                      </div>
                    </div>
                  )}

                  {/* Form Actions */}
                  <div className="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200">
                    <button
                      type="button"
                      onClick={() => setIsAssignModalOpen(false)}
                      className="px-6 py-3 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors font-medium"
                    >
                      Cancel
                    </button>
                    <button
                      type="button"
                      onClick={submitAssignment}
                      disabled={loading || !assignmentForm.semester_id || !assignmentForm.lecturer_code}
                      className="px-6 py-3 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white rounded-lg hover:from-emerald-600 hover:to-emerald-700 transition-all duration-200 font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      {loading ? 'Processing...' : (selectedAssignment?.lecturer_code ? 'Update Assignment' : 'Assign Lecturer')}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* Bulk Assignment Modal */}
          {isBulkAssignModalOpen && (
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
              <div className="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                <div className="bg-gradient-to-r from-blue-500 via-blue-600 to-indigo-600 p-6 rounded-t-2xl">
                  <div className="flex items-center justify-between">
                    <h3 className="text-xl font-semibold text-white">
                      Bulk Assign Lecturer
                    </h3>
                    <button
                      onClick={() => setIsBulkAssignModalOpen(false)}
                      className="text-white hover:text-gray-200 transition-colors"
                    >
                      <X className="w-6 h-6" />
                    </button>
                  </div>
                </div>

                <div className="p-6 space-y-6">
                  {/* Semester and Lecturer Selection */}
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Semester *
                      </label>
                      <select
                        value={bulkAssignmentForm.semester_id}
                        onChange={(e) => setBulkAssignmentForm(prev => ({ ...prev, semester_id: e.target.value }))}
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        required
                      >
                        <option value="">Select Semester</option>
                        {semesters.map(semester => (
                          <option key={semester.id} value={semester.id}>
                            {semester.name}
                          </option>
                        ))}
                      </select>
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Lecturer *
                      </label>
                      <select
                        value={bulkAssignmentForm.lecturer_code}
                        onChange={(e) => setBulkAssignmentForm(prev => ({ ...prev, lecturer_code: e.target.value }))}
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        required
                      >
                        <option value="">Select Lecturer</option>
                        {availableLecturers.map(lecturer => (
                          <option key={lecturer.id} value={lecturer.code}>
                            {lecturer.name} ({lecturer.code})
                          </option>
                        ))}
                      </select>
                    </div>
                  </div>

                  {/* Unit Selection */}
                  <div>
                    <div className="flex items-center justify-between mb-3">
                      <label className="block text-sm font-medium text-gray-700">
                        Select Units to Assign *
                      </label>
                      <div className="flex gap-2">
                        <button
                          type="button"
                          onClick={selectAllUnassigned}
                          className="px-3 py-1 text-xs bg-blue-100 text-blue-700 rounded hover:bg-blue-200"
                        >
                          Select Unassigned
                        </button>
                        <button
                          type="button"
                          onClick={selectAllUnits}
                          className="px-3 py-1 text-xs bg-green-100 text-green-700 rounded hover:bg-green-200"
                        >
                          Select All
                        </button>
                        <button
                          type="button"
                          onClick={() => setBulkAssignmentForm(prev => ({ ...prev, unit_ids: [] }))}
                          className="px-3 py-1 text-xs bg-gray-100 text-gray-700 rounded hover:bg-gray-200"
                        >
                          Clear All
                        </button>
                      </div>
                    </div>

                    {/* Loading State */}
                    {loadingUnits && (
                      <div className="flex items-center justify-center p-8 border border-gray-300 rounded-lg">
                        <div className="flex items-center space-x-2">
                          <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
                          <span className="text-gray-600">Loading units...</span>
                        </div>
                      </div>
                    )}

                    {/* Units List */}
                    {!loadingUnits && (
                      <div className="max-h-60 overflow-y-auto border border-gray-300 rounded-lg">
                        {availableUnits.length > 0 ? (
                          availableUnits.map(unit => (
                            <label
                              key={unit.id}
                              className={`flex items-center p-3 hover:bg-gray-50 border-b border-gray-100 last:border-b-0 cursor-pointer ${
                                unit.is_assigned ? 'bg-yellow-50' : ''
                              }`}
                            >
                              <input
                                type="checkbox"
                                checked={bulkAssignmentForm.unit_ids.includes(unit.id)}
                                onChange={() => handleUnitToggle(unit.id)}
                                className="form-checkbox h-4 w-4 text-blue-600 mr-3"
                              />
                              <div className="flex-1">
                                <div className="text-sm font-medium text-gray-900">
                                  {unit.code} - {unit.name}
                                </div>
                                <div className="text-xs text-gray-500">
                                  {unit.school?.code} | {unit.program?.code} | {unit.credit_hours} credits
                                </div>
                                {unit.is_assigned && unit.assignment && (
                                  <div className="text-xs text-yellow-600 mt-1 flex items-center">
                                    <AlertTriangle className="w-3 h-3 mr-1" />
                                    Already assigned to {unit.assignment.lecturer_name}
                                  </div>
                                )}
                              </div>
                            </label>
                          ))
                        ) : (
                          <div className="p-8 text-center text-gray-500">
                            {bulkAssignmentForm.semester_id ? 
                              'No units found for the selected criteria' : 
                              'Please select a semester to load units'
                            }
                          </div>
                        )}
                      </div>
                    )}

                    <div className="text-sm text-gray-600 mt-2">
                      {bulkAssignmentForm.unit_ids.length} units selected
                      {availableUnits.length > 0 && (
                        <span className="ml-2">
                          ({availableUnits.filter(u => !u.is_assigned).length} unassigned, {availableUnits.filter(u => u.is_assigned).length} already assigned)
                        </span>
                      )}
                    </div>
                  </div>

                  {/* Form Actions */}
                  <div className="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200">
                    <button
                      type="button"
                      onClick={() => setIsBulkAssignModalOpen(false)}
                      className="px-6 py-3 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors font-medium"
                    >
                      Cancel
                    </button>
                    <button
                      type="button"
                      onClick={submitBulkAssignment}
                      disabled={loading || !bulkAssignmentForm.semester_id || !bulkAssignmentForm.lecturer_code || bulkAssignmentForm.unit_ids.length === 0}
                      className="px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 text-white rounded-lg hover:from-blue-600 hover:to-indigo-700 transition-all duration-200 font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      {loading ? 'Processing...' : `Assign to ${bulkAssignmentForm.unit_ids.length} Units`}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* View Assignment Modal */}
          {isViewModalOpen && selectedAssignment && (
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
              <div className="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div className="bg-gradient-to-r from-slate-500 via-slate-600 to-gray-600 p-6 rounded-t-2xl">
                  <div className="flex items-center justify-between">
                    <h3 className="text-xl font-semibold text-white">
                      Assignment Details
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
                  {/* Unit Information */}
                  <div className="bg-gray-50 p-4 rounded-lg">
                    <h4 className="font-medium text-gray-900 mb-3">Unit Information</h4>
                    <div className="grid grid-cols-2 gap-4 text-sm">
                      <div>
                        <span className="font-medium text-gray-700">Code:</span>
                        <div className="text-gray-900">{selectedAssignment.code}</div>
                      </div>
                      <div>
                        <span className="font-medium text-gray-700">Credit Hours:</span>
                        <div className="text-gray-900">{selectedAssignment.credit_hours}</div>
                      </div>
                      <div className="col-span-2">
                        <span className="font-medium text-gray-700">Name:</span>
                        <div className="text-gray-900">{selectedAssignment.name}</div>
                      </div>
                      <div>
                        <span className="font-medium text-gray-700">School:</span>
                        <div className="text-gray-900">{selectedAssignment.school?.name}</div>
                      </div>
                      <div>
                        <span className="font-medium text-gray-700">Program:</span>
                        <div className="text-gray-900">{selectedAssignment.program?.name}</div>
                      </div>
                    </div>
                  </div>

                  {/* Assignment Status */}
                  <div className="bg-gray-50 p-4 rounded-lg">
                    <h4 className="font-medium text-gray-900 mb-3">Assignment Status</h4>
                    {selectedAssignment.lecturer_name ? (
                      <div className="space-y-2">
                        <div className="flex items-center">
                          <StatusBadge assignment={selectedAssignment} />
                        </div>
                        <div className="text-sm">
                          <span className="font-medium text-gray-700">Lecturer:</span>
                          <div className="text-gray-900">{selectedAssignment.lecturer_name}</div>
                        </div>
                        <div className="text-sm">
                          <span className="font-medium text-gray-700">Code:</span>
                          <div className="text-gray-900">{selectedAssignment.lecturer_code}</div>
                        </div>
                        <div className="text-sm">
                          <span className="font-medium text-gray-700">Email:</span>
                          <div className="text-gray-900">{selectedAssignment.lecturer_email}</div>
                        </div>
                      </div>
                    ) : (
                      <div className="space-y-2">
                        <StatusBadge assignment={selectedAssignment} />
                        <div className="text-sm text-gray-600">
                          This unit has not been assigned to any lecturer yet.
                        </div>
                      </div>
                    )}
                  </div>

                  {/* Lecturer Workload (if assigned) */}
                  {lecturerWorkload && (
                    <div className="bg-green-50 border border-green-200 p-4 rounded-lg">
                      <h4 className="font-medium text-green-800 mb-3">Lecturer Workload</h4>
                      <div className="grid grid-cols-3 gap-4 text-sm">
                        <div className="text-center">
                          <div className="text-2xl font-bold text-green-600">
                            {lecturerWorkload.statistics?.total_units || 0}
                          </div>
                          <div className="text-green-700">Units</div>
                        </div>
                        <div className="text-center">
                          <div className="text-2xl font-bold text-green-600">
                            {lecturerWorkload.statistics?.total_students || 0}
                          </div>
                          <div className="text-green-700">Students</div>
                        </div>
                        <div className="text-center">
                          <div className="text-2xl font-bold text-green-600">
                            {lecturerWorkload.statistics?.total_credit_hours || 0}
                          </div>
                          <div className="text-green-700">Credit Hours</div>
                        </div>
                      </div>
                    </div>
                  )}

                  {/* Form Actions */}
                  <div className="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200">
                    <button
                      onClick={() => setIsViewModalOpen(false)}
                      className="px-6 py-3 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors font-medium"
                    >
                      Close
                    </button>
                    {can.update && selectedSemester && (
                      <button
                        onClick={() => {
                          setIsViewModalOpen(false);
                          handleAssignLecturer(selectedAssignment);
                        }}
                        className="px-6 py-3 bg-gradient-to-r from-indigo-500 to-indigo-600 text-white rounded-lg hover:from-indigo-600 hover:to-indigo-700 transition-all duration-200 font-medium"
                      >
                        {selectedAssignment.lecturer_code ? 'Change Lecturer' : 'Assign Lecturer'}
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