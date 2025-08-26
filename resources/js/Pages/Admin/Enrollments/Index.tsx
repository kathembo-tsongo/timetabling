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
  Download,
  Upload
} from 'lucide-react';

type Student = {
  id: number;
  student_code: string;
  name: string;
  email: string;
  school_id: number;
  school_name: string;
  school_code: string;
  program_id: number;
  program_name: string;
  program_code: string;
};

type Unit = {
  id: number;
  code: string;
  name: string;
  credit_hours: number;
  school_id: number;
  school_name: string;
  school_code: string;
  program_id: number;
  program_name: string;
  program_code: string;
  semester_id: number;
  semester_name: string;
  is_active: boolean;
};

type Enrollment = {
  id: number;
  student_id: number;
  unit_id: number;
  semester_id: number;
  enrollment_date: string;
  status: 'enrolled' | 'dropped' | 'completed';
  grade?: string;
  student: Student;
  unit: Unit;
  semester_name: string;
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
  school_id: number;
};

type Semester = {
  id: number;
  name: string;
};

type Stats = {
  total: number;
  active: number;
  dropped: number;
  completed: number;
  pending: number;
};

type Can = {
  create: boolean;
  update: boolean;
  delete: boolean;
};

type Filters = {
  search?: string;
  student_id?: string | number;
  unit_id?: string | number;
  semester_id?: string | number;
  school_id?: string | number;
  program_id?: string | number;
  status?: string;
  sort_field?: string;
  sort_direction?: string;
};

type PageProps = {
  enrollments: Enrollment[];
  students: Student[];
  units: Unit[];
  schools: School[];
  programs: Program[];
  groups: Group[];
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

type EnrollmentFormData = {
  student_code: string;
  school_id: number | '';
  program_id: number | '';
  group_id: number | '';
  semester_id: number | '';
  unit_ids: number[]; // Changed to array for multiple unit selection
  status: 'enrolled' | 'dropped' | 'completed';
};

export default function EnrollmentsIndex() {
  const pageProps = usePage<PageProps>();
  const { 
    enrollments = [], 
    students = [], 
    units = [],
    schools = [], 
    programs = [], 
    groups = [],
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
  const [selectedEnrollment, setSelectedEnrollment] = useState<Enrollment | null>(null);
  const [expandedRows, setExpandedRows] = useState<Set<number>>(new Set());
  const [loading, setLoading] = useState(false);

  // Form state
  const [formData, setFormData] = useState<EnrollmentFormData>({
    student_code: '',
    school_id: '',
    program_id: '',
    group_id: '',
    semester_id: '',
    unit_ids: [],
    status: 'enrolled'
  });

  // Remove student validation state - we'll only validate on submit
  
  // Filter state
  const [searchTerm, setSearchTerm] = useState(filters?.search || '');
  const [selectedStudent, setSelectedStudent] = useState<string | number>(filters?.student_id || '');
  const [selectedUnit, setSelectedUnit] = useState<string | number>(filters?.unit_id || '');
  const [selectedSemester, setSelectedSemester] = useState<string | number>(filters?.semester_id || '');
  const [selectedSchool, setSelectedSchool] = useState<string | number>(filters?.school_id || '');
  const [selectedProgram, setSelectedProgram] = useState<string | number>(filters?.program_id || '');
  const [statusFilter, setStatusFilter] = useState<string>(filters?.status || 'all');
  const [sortField, setSortField] = useState(filters?.sort_field || 'enrollment_date');
  const [sortDirection, setSortDirection] = useState(filters?.sort_direction || 'desc');

  // Filtered data based on form selections
  const filteredPrograms = programs.filter(program => 
    !formData.school_id || program.school_id === formData.school_id
  );

  const filteredGroups = groups.filter(group => 
    !formData.program_id || group.program_id === formData.program_id
  );

  const filteredUnits = units.filter(unit => 
    unit.is_active && 
    (!formData.semester_id || unit.semester_id == formData.semester_id)
  );

  // Reset dependent fields when selections change
  useEffect(() => {
    if (formData.school_id) {
      setFormData(prev => ({ ...prev, program_id: '', group_id: '' }));
    }
  }, [formData.school_id]);

  useEffect(() => {
    if (formData.program_id) {
      setFormData(prev => ({ ...prev, group_id: '' }));
    }
  }, [formData.program_id]);

  useEffect(() => {
    if (formData.semester_id) {
      setFormData(prev => ({ ...prev, unit_ids: [] }));
    }
  }, [formData.semester_id]);

  // Error handling
  useEffect(() => {
    if (errors?.error) {
      toast.error(errors.error);
    }
    if (flash?.success) {
      toast.success(flash.success);
    }
  }, [errors, flash]);

  // Filtered enrollments
  const filteredEnrollments = enrollments.filter(enrollment => {
    const matchesSearch = !searchTerm || 
      enrollment.student.name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
      enrollment.student.student_code?.toLowerCase().includes(searchTerm.toLowerCase()) ||
      enrollment.unit.name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
      enrollment.unit.code?.toLowerCase().includes(searchTerm.toLowerCase());
    
    const matchesStudent = !selectedStudent || enrollment.student_id == selectedStudent;
    const matchesUnit = !selectedUnit || enrollment.unit_id == selectedUnit;
    const matchesSemester = !selectedSemester || enrollment.semester_id == selectedSemester;
    const matchesSchool = !selectedSchool || enrollment.student.school_id == selectedSchool;
    const matchesProgram = !selectedProgram || enrollment.student.program_id == selectedProgram;
    const matchesStatus = statusFilter === 'all' || enrollment.status === statusFilter;
    
    return matchesSearch && matchesStudent && matchesUnit && matchesSemester && matchesSchool && matchesProgram && matchesStatus;
  });

  // Status badge component
  const StatusBadge: React.FC<{ status: string }> = ({ status }) => {
    const statusConfig = {
      enrolled: { color: 'bg-green-500', icon: Check, text: 'Enrolled' },
      dropped: { color: 'bg-red-500', icon: X, text: 'Dropped' },
      completed: { color: 'bg-blue-500', icon: UserCheck, text: 'Completed' }
    };

    const config = statusConfig[status as keyof typeof statusConfig] || statusConfig.enrolled;
    const Icon = config.icon;

    return (
      <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium text-white ${config.color}`}>
        <Icon className="w-3 h-3 mr-1" />
        {config.text}
      </span>
    );
  };

  // Form handlers
  const handleCreateEnrollment = () => {
    setFormData({
      student_code: '',
      school_id: '',
      program_id: '',
      group_id: '',
      semester_id: '',
      unit_ids: [],
      status: 'enrolled'
    });
    setIsCreateModalOpen(true);
  };

  const handleEditEnrollment = (enrollment: Enrollment) => {
    setSelectedEnrollment(enrollment);
    setFormData({
      student_code: enrollment.student.code,
      school_id: enrollment.student.school_id,
      program_id: enrollment.student.program_id,
      group_id: '', // Would need to be fetched from enrollment data
      semester_id: enrollment.semester_id,
      unit_ids: [enrollment.unit_id],
      status: enrollment.status
    });
    setIsEditModalOpen(true);
  };

  const handleUnitToggle = (unitId: number) => {
    setFormData(prev => ({
      ...prev,
      unit_ids: prev.unit_ids.includes(unitId)
        ? prev.unit_ids.filter(id => id !== unitId)
        : [...prev.unit_ids, unitId]
    }));
  };

  const handleSelectAllUnits = () => {
    setFormData(prev => ({
      ...prev,
      unit_ids: filteredUnits.map(unit => unit.id)
    }));
  };

  const handleClearAllUnits = () => {
    setFormData(prev => ({ ...prev, unit_ids: [] }));
  };

  const handleViewEnrollment = (enrollment: Enrollment) => {
    setSelectedEnrollment(enrollment);
    setIsViewModalOpen(true);
  };

  const handleDeleteEnrollment = (enrollment: Enrollment) => {
    if (confirm(`Are you sure you want to delete the enrollment for "${enrollment.student.name}" in "${enrollment.unit.name}"? This action cannot be undone.`)) {
      setLoading(true);
      router.delete(route('admin.enrollments.destroy', enrollment.id), {
        onSuccess: () => {
          toast.success('Enrollment deleted successfully!');
        },
        onError: (errors) => {
          toast.error(errors.error || 'Failed to delete enrollment');
        },
        onFinish: () => setLoading(false)
      });
    }
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);

    const url = selectedEnrollment 
      ? route('admin.enrollments.update', selectedEnrollment.id)
      : route('admin.enrollments.store');
    
    const method = selectedEnrollment ? 'put' : 'post';

    router[method](url, formData, {
      onSuccess: () => {
        toast.success(`Enrollment ${selectedEnrollment ? 'updated' : 'created'} successfully!`);
        setIsCreateModalOpen(false);
        setIsEditModalOpen(false);
        setSelectedEnrollment(null);
      },
      onError: (errors) => {
        toast.error(errors.error || `Failed to ${selectedEnrollment ? 'update' : 'create'} enrollment`);
      },
      onFinish: () => setLoading(false)
    });
  };

  const handleFilter = () => {
    const params = new URLSearchParams();
    
    if (searchTerm) params.set('search', searchTerm);
    if (selectedStudent) params.set('student_id', String(selectedStudent));
    if (selectedUnit) params.set('unit_id', String(selectedUnit));
    if (selectedSemester) params.set('semester_id', String(selectedSemester));
    if (selectedSchool) params.set('school_id', String(selectedSchool));
    if (selectedProgram) params.set('program_id', String(selectedProgram));
    if (statusFilter !== 'all') params.set('status', statusFilter);
    params.set('sort_field', sortField);
    params.set('sort_direction', sortDirection);
    
    router.get(`${route('admin.enrollments.index')}?${params.toString()}`);
  };

  const toggleRowExpansion = (enrollmentId: number) => {
    const newExpanded = new Set(expandedRows);
    if (newExpanded.has(enrollmentId)) {
      newExpanded.delete(enrollmentId);
    } else {
      newExpanded.add(enrollmentId);
    }
    setExpandedRows(newExpanded);
  };

  return (
    <AuthenticatedLayout>
      <Head title="Enrollments Management" />
      
      <div className="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-cyan-50 py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          
          {/* Header */}
          <div className="mb-8">
            <div className="bg-white/80 backdrop-blur-sm rounded-3xl shadow-2xl border border-slate-200/50 p-8">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <h1 className="text-4xl font-bold bg-gradient-to-r from-slate-800 via-blue-800 to-indigo-800 bg-clip-text text-transparent mb-2">
                    Enrollments Management
                  </h1>
                  <p className="text-slate-600 text-lg">
                    Manage student enrollments across units and semesters
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
                        Dropped: <span className="font-semibold">{stats.dropped}</span>
                      </div>
                      <div className="text-sm text-slate-600">
                        Completed: <span className="font-semibold">{stats.completed}</span>
                      </div>
                    </div>
                  )}
                </div>
                
                <div className="flex items-center gap-3">
                  <button
                    onClick={() => {/* Handle bulk import */}}
                    className="inline-flex items-center px-4 py-2 bg-gray-600 text-white font-medium rounded-lg shadow-md hover:bg-gray-700 transition-colors"
                  >
                    <Upload className="w-4 h-4 mr-2" />
                    Import
                  </button>
                  
                  {can.create && (
                    <button
                      onClick={handleCreateEnrollment}
                      className="inline-flex items-center px-6 py-3 bg-gradient-to-r from-emerald-500 via-emerald-600 to-teal-600 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl hover:from-emerald-600 hover:via-emerald-700 hover:to-teal-700 transform hover:scale-105 hover:-translate-y-0.5 transition-all duration-300 group"
                    >
                      <Plus className="w-5 h-5 mr-2 group-hover:rotate-12 transition-transform duration-300" />
                      New Enrollment
                    </button>
                  )}
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

          {/* Filters */}
          <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-lg border border-slate-200/50 p-6 mb-6">
            <div className="grid grid-cols-1 md:grid-cols-7 gap-4">
              <div className="md:col-span-2">
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-5 h-5" />
                  <input
                    type="text"
                    placeholder="Search enrollments..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  />
                </div>
              </div>
              
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
                value={selectedUnit}
                onChange={(e) => setSelectedUnit(e.target.value)}
                className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="">All Units</option>
                {units.map(unit => (
                  <option key={unit.id} value={unit.id}>
                    {unit.code} - {unit.name}
                  </option>
                ))}
              </select>

              <select
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value)}
                className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="all">All Status</option>
                <option value="enrolled">Enrolled</option>
                <option value="dropped">Dropped</option>
                <option value="completed">Completed</option>
              </select>

              <button
                onClick={handleFilter}
                className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
              >
                <Filter className="w-5 h-5" />
              </button>
            </div>
          </div>

          {/* Enrollments Table */}
          <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-2xl border border-slate-200/50 overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-slate-50">
                  <tr>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Student
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Unit
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Semester
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
                  {filteredEnrollments.map((enrollment, index) => (
                    <React.Fragment key={enrollment.id}>
                      <tr className={`hover:bg-slate-50 transition-colors duration-150 ${
                        index % 2 === 0 ? "bg-white" : "bg-slate-50/50"
                      }`}>
                        <td className="px-6 py-4">
                          <div className="flex items-center">
                            <button
                              onClick={() => toggleRowExpansion(enrollment.id)}
                              className="mr-3 p-1 hover:bg-gray-200 rounded"
                            >
                              {expandedRows.has(enrollment.id) ? (
                                <ChevronUp className="w-4 h-4" />
                              ) : (
                                <ChevronDown className="w-4 h-4" />
                              )}
                            </button>
                            <Users className="w-8 h-8 text-blue-500 mr-3" />
                            <div>
                              <div className="text-sm font-medium text-slate-900">{enrollment.student.name}</div>
                              <div className="text-xs text-blue-600 font-semibold">{enrollment.student.student_code}</div>
                              <div className="text-xs text-gray-500">{enrollment.student.email}</div>
                            </div>
                          </div>
                        </td>
                        <td className="px-6 py-4 text-sm text-slate-700">
                          <div>
                            <div className="font-medium">{enrollment.unit.name}</div>
                            <div className="text-xs text-blue-600 font-semibold">{enrollment.unit.code}</div>
                            <div className="text-xs text-gray-500">{enrollment.unit.credit_hours} hrs</div>
                          </div>
                        </td>
                        <td className="px-6 py-4 text-sm text-slate-700">
                          <div className="flex items-center">
                            <Calendar className="w-4 h-4 text-gray-400 mr-2" />
                            {enrollment.semester_name}
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <StatusBadge status={enrollment.status} />
                        </td>
                        <td className="px-6 py-4 text-sm font-medium">
                          <div className="flex items-center space-x-2">
                            <button
                              onClick={() => handleViewEnrollment(enrollment)}
                              className="text-blue-600 hover:text-blue-900 transition-colors p-1 rounded hover:bg-blue-50"
                              title="View details"
                            >
                              <Eye className="w-4 h-4" />
                            </button>
                         
                            {can.update && (
                              <button
                                onClick={() => handleEditEnrollment(enrollment)}
                                className="text-indigo-600 hover:text-indigo-900 transition-colors p-1 rounded hover:bg-indigo-50"
                                title="Edit enrollment"
                              >
                                <Edit className="w-4 h-4" />
                              </button>
                            )}
                        
                            {can.delete && (
                              <button
                                onClick={() => handleDeleteEnrollment(enrollment)}
                                className="text-red-600 hover:text-red-900 transition-colors p-1 rounded hover:bg-red-50"
                                title="Delete enrollment"
                              >
                                <Trash2 className="w-4 h-4" />
                              </button>
                            )}
                          </div>
                        </td>
                      </tr>
                      
                      {/* Expanded row content */}
                      {expandedRows.has(enrollment.id) && (
                        <tr>
                          <td colSpan={5} className="px-6 py-4 bg-gray-50">
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                              <div>
                                <h4 className="font-medium text-gray-900 mb-3">Student Details</h4>
                                <div className="space-y-2 text-sm">
                                  <div>
                                    <span className="font-medium">Name:</span>
                                    <div className="text-gray-600">{enrollment.student.name}</div>
                                  </div>
                                  <div>
                                    <span className="font-medium">Student Code:</span>
                                    <div className="text-gray-600">{enrollment.student.student_code}</div>
                                  </div>
                                  <div>
                                    <span className="font-medium">Email:</span>
                                    <div className="text-gray-600">{enrollment.student.email}</div>
                                  </div>
                                  <div>
                                    <span className="font-medium">Program:</span>
                                    <div className="text-gray-600">{enrollment.student.program_code} - {enrollment.student.program_name}</div>
                                  </div>
                                </div>
                              </div>
                              
                              <div>
                                <h4 className="font-medium text-gray-900 mb-3">Unit Details</h4>
                                <div className="space-y-2 text-sm">
                                  <div>
                                    <span className="font-medium">Unit:</span>
                                    <div className="text-gray-600">{enrollment.unit.code} - {enrollment.unit.name}</div>
                                  </div>
                                  <div>
                                    <span className="font-medium">Credit Hours:</span>
                                    <div className="text-gray-600">{enrollment.unit.credit_hours}</div>
                                  </div>
                                  <div>
                                    <span className="font-medium">School:</span>
                                    <div className="text-gray-600">{enrollment.unit.school_code} - {enrollment.unit.school_name}</div>
                                  </div>
                                </div>
                              </div>

                              <div>
                                <h4 className="font-medium text-gray-900 mb-3">Enrollment Info</h4>
                                <div className="space-y-2 text-sm">
                                  <div>
                                    <span className="font-medium">Status:</span>
                                    <div className="text-gray-600 mt-1">
                                      <StatusBadge status={enrollment.status} />
                                    </div>
                                  </div>
                                  <div>
                                    <span className="font-medium">Enrolled:</span>
                                    <div className="text-gray-600">{new Date(enrollment.enrollment_date).toLocaleDateString()}</div>
                                  </div>
                                  {enrollment.grade && (
                                    <div>
                                      <span className="font-medium">Grade:</span>
                                      <div className="text-gray-600">{enrollment.grade}</div>
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
              
              {filteredEnrollments.length === 0 && (
                <div className="text-center py-12">
                  <Users className="mx-auto h-12 w-12 text-gray-400" />
                  <h3 className="mt-2 text-sm font-medium text-gray-900">No enrollments found</h3>
                  <p className="mt-1 text-sm text-gray-500">
                    {searchTerm || selectedStudent || selectedUnit || selectedSemester || statusFilter !== 'all'
                      ? 'Try adjusting your filters'
                      : 'Get started by creating a new enrollment'
                    }
                  </p>
                  {can.create && !searchTerm && !selectedStudent && !selectedUnit && !selectedSemester && statusFilter === 'all' && (
                    <button
                      onClick={handleCreateEnrollment}
                      className="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                    >
                      <Plus className="w-4 h-4 mr-2" />
                      New Enrollment
                    </button>
                  )}
                </div>
              )}
            </div>
          </div>

          {/* Create/Edit Modal */}
          {(isCreateModalOpen || isEditModalOpen) && (
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
              <div className="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                <div className="bg-gradient-to-r from-emerald-500 via-emerald-600 to-teal-600 p-6 rounded-t-2xl">
                  <h3 className="text-xl font-semibold text-white">
                    {selectedEnrollment ? 'Edit Enrollment' : 'Create New Enrollment'}
                  </h3>
                </div>

                <form onSubmit={handleSubmit} className="p-6 space-y-6">
                  {/* Student Code Input */}
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Student Number *
                    </label>
                    <input
                      type="text"
                      value={formData.student_code}
                      onChange={(e) => setFormData(prev => ({ ...prev, student_code: e.target.value }))}
                      className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                      placeholder="e.g., BBIT0003"
                      required
                    />
                    <p className="mt-1 text-xs text-gray-500">
                      Student validation will occur when you submit the form
                    </p>
                  </div>

                  {/* School Selection */}
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      School *
                    </label>
                    <select
                      value={formData.school_id}
                      onChange={(e) => setFormData(prev => ({ 
                        ...prev, 
                        school_id: e.target.value ? parseInt(e.target.value) : '',
                        program_id: '',
                        group_id: ''
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

                  {/* Program Selection */}
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Program *
                    </label>
                    <select
                      value={formData.program_id}
                      onChange={(e) => setFormData(prev => ({ 
                        ...prev, 
                        program_id: e.target.value ? parseInt(e.target.value) : '',
                        group_id: ''
                      }))}
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
                    {!formData.school_id && (
                      <p className="mt-1 text-xs text-gray-500">
                        Select a school first to see programs
                      </p>
                    )}
                  </div>

                  {/* Group Selection */}
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Group *
                    </label>
                    <select
                      value={formData.group_id}
                      onChange={(e) => setFormData(prev => ({ ...prev, group_id: e.target.value ? parseInt(e.target.value) : '' }))}
                      className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                      required
                      disabled={!formData.program_id}
                    >
                      <option value="">Select Group</option>
                      {filteredGroups.map(group => (
                        <option key={group.id} value={group.id}>
                          {group.name} (Capacity: {group.capacity})
                        </option>
                      ))}
                    </select>
                    {!formData.program_id && (
                      <p className="mt-1 text-xs text-gray-500">
                        Select a program first to see groups
                      </p>
                    )}
                  </div>

                  {/* Semester Selection */}
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Semester *
                    </label>
                    <select
                      value={formData.semester_id}
                      onChange={(e) => setFormData(prev => ({ 
                        ...prev, 
                        semester_id: e.target.value ? parseInt(e.target.value) : '',
                        unit_ids: []
                      }))}
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

                  {/* Units Selection */}
                  {formData.semester_id && (
                    <div>
                      <div className="flex items-center justify-between mb-3">
                        <label className="block text-sm font-medium text-gray-700">
                          Select Units to Enroll ({formData.unit_ids.length} selected)
                        </label>
                        <div className="flex gap-2">
                          <button
                            type="button"
                            onClick={handleSelectAllUnits}
                            className="text-xs px-3 py-1 bg-blue-100 text-blue-700 rounded-md hover:bg-blue-200"
                          >
                            Select All
                          </button>
                          <button
                            type="button"
                            onClick={handleClearAllUnits}
                            className="text-xs px-3 py-1 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200"
                          >
                            Clear All
                          </button>
                        </div>
                      </div>
                      
                      <div className="max-h-60 overflow-y-auto border border-gray-200 rounded-lg">
                        {filteredUnits.length === 0 ? (
                          <div className="p-4 text-center text-gray-500">
                            No units available for this semester
                          </div>
                        ) : (
                          <div className="divide-y divide-gray-200">
                            {filteredUnits.map(unit => (
                              <div key={unit.id} className="p-3 hover:bg-gray-50">
                                <label className="flex items-center cursor-pointer">
                                  <input
                                    type="checkbox"
                                    checked={formData.unit_ids.includes(unit.id)}
                                    onChange={() => handleUnitToggle(unit.id)}
                                    className="w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
                                  />
                                  <div className="ml-3 flex-1">
                                    <div className="flex items-center justify-between">
                                      <div>
                                        <div className="text-sm font-medium text-gray-900">
                                          {unit.code} - {unit.name}
                                        </div>
                                        <div className="text-xs text-gray-500">
                                          {unit.credit_hours} credit hours • {unit.school_code}
                                        </div>
                                      </div>
                                    </div>
                                  </div>
                                </label>
                              </div>
                            ))}
                          </div>
                        )}
                      </div>
                    </div>
                  )}

                  {/* Status Selection */}
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Status *
                    </label>
                    <select
                      value={formData.status}
                      onChange={(e) => setFormData(prev => ({ ...prev, status: e.target.value as 'enrolled' | 'dropped' | 'completed' }))}
                      className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                      required
                    >
                      <option value="enrolled">Enrolled</option>
                      <option value="dropped">Dropped</option>
                      <option value="completed">Completed</option>
                    </select>
                  </div>

                  {/* Form Actions */}
                  <div className="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200">
                    <button
                      type="button"
                      onClick={() => {
                        setIsCreateModalOpen(false);
                        setIsEditModalOpen(false);
                        setSelectedEnrollment(null);
                      }}
                      className="px-6 py-3 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors font-medium"
                    >
                      Cancel
                    </button>
                    <button
                      type="submit"
                      disabled={loading || formData.unit_ids.length === 0}
                      className="px-6 py-3 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white rounded-lg hover:from-emerald-600 hover:to-emerald-700 transition-all duration-200 font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      {loading ? 'Processing...' : 
                       selectedEnrollment ? 'Update Enrollment' : 
                       `Enroll in ${formData.unit_ids.length} Unit${formData.unit_ids.length !== 1 ? 's' : ''}`}
                    </button>
                  </div>
                </form>
              </div>
            </div>
          )}

          {/* View Modal */}
          {isViewModalOpen && selectedEnrollment && (
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
              <div className="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div className="bg-gradient-to-r from-slate-500 via-slate-600 to-gray-600 p-6 rounded-t-2xl">
                  <div className="flex items-center justify-between">
                    <h3 className="text-xl font-semibold text-white">
                      Enrollment Details
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
                      <h4 className="font-semibold text-gray-900 mb-3">Student Information</h4>
                      <div className="space-y-3">
                        <div>
                          <label className="block text-sm font-medium text-gray-700">Student Name</label>
                          <div className="mt-1 text-sm text-gray-900">{selectedEnrollment.student.name}</div>
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700">Student Code</label>
                          <div className="mt-1 text-sm font-semibold text-blue-600">{selectedEnrollment.student.student_code}</div>
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700">Email</label>
                          <div className="mt-1 text-sm text-gray-900">{selectedEnrollment.student.email}</div>
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700">Program</label>
                          <div className="mt-1 text-sm text-gray-900">
                            <div className="flex items-center">
                              <GraduationCap className="w-4 h-4 text-gray-400 mr-2" />
                              {selectedEnrollment.student.program_code} - {selectedEnrollment.student.program_name}
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div>
                      <h4 className="font-semibold text-gray-900 mb-3">Unit Information</h4>
                      <div className="space-y-3">
                        <div>
                          <label className="block text-sm font-medium text-gray-700">Unit Name</label>
                          <div className="mt-1 text-sm text-gray-900">{selectedEnrollment.unit.name}</div>
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700">Unit Code</label>
                          <div className="mt-1 text-sm font-semibold text-blue-600">{selectedEnrollment.unit.code}</div>
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700">Credit Hours</label>
                          <div className="mt-1 text-sm text-gray-900">{selectedEnrollment.unit.credit_hours} hours</div>
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700">School</label>
                          <div className="mt-1 text-sm text-gray-900">
                            <div className="flex items-center">
                              <Building2 className="w-4 h-4 text-gray-400 mr-2" />
                              {selectedEnrollment.unit.school_code} - {selectedEnrollment.unit.school_name}
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div>
                    <h4 className="font-semibold text-gray-900 mb-3">Enrollment Details</h4>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div>
                        <label className="block text-sm font-medium text-gray-700">Semester</label>
                        <div className="mt-1 text-sm text-gray-900">
                          <div className="flex items-center">
                            <Calendar className="w-4 h-4 text-gray-400 mr-2" />
                            {selectedEnrollment.semester_name}
                          </div>
                        </div>
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700">Status</label>
                        <div className="mt-1">
                          <StatusBadge status={selectedEnrollment.status} />
                        </div>
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700">Enrollment Date</label>
                        <div className="mt-1 text-sm text-gray-900">
                          {new Date(selectedEnrollment.enrollment_date).toLocaleDateString()}
                        </div>
                      </div>
                      {selectedEnrollment.grade && (
                        <div>
                          <label className="block text-sm font-medium text-gray-700">Grade</label>
                          <div className="mt-1 text-sm text-gray-900">{selectedEnrollment.grade}</div>
                        </div>
                      )}
                    </div>
                  </div>

                  <div>
                    <h4 className="font-semibold text-gray-900 mb-3">Timestamps</h4>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div>
                        <label className="block text-sm font-medium text-gray-700">Created</label>
                        <div className="mt-1 text-sm text-gray-900">
                          {new Date(selectedEnrollment.created_at).toLocaleString()}
                        </div>
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700">Last Updated</label>
                        <div className="mt-1 text-sm text-gray-900">
                          {new Date(selectedEnrollment.updated_at).toLocaleString()}
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
                          handleEditEnrollment(selectedEnrollment);
                        }}
                        className="px-6 py-3 bg-gradient-to-r from-indigo-500 to-indigo-600 text-white rounded-lg hover:from-indigo-600 hover:to-indigo-700 transition-all duration-200 font-medium"
                      >
                        Edit Enrollment
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