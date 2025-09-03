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
  MoreHorizontal
} from 'lucide-react';

// Type definitions
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
  student_code: string;
  unit_id: number;
  class_id: number;
  semester_id: number;
  enrollment_date: string;
  status: 'enrolled' | 'dropped' | 'completed';
  grade?: string;
  student: Student;
  unit: Unit;
  class: Class;
  semester_name: string;
  first_name?: string;
  last_name?: string;
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
  is_active: boolean;
};

type Class = {
  id: number;
  name: string;
  section: string;
  display_name: string;
  year_level: number;
  capacity: number;
  program_id: number;
  semester_id: number;
};

type EnrollmentFormData = {
  student_code: string;
  school_id: number | '';
  program_id: number | '';
  class_id: number | '';
  semester_id: number | '';
  unit_ids: number[];
  status: 'enrolled' | 'dropped' | 'completed';
};

type CapacityInfo = {
  capacity: number;
  current_enrollments: number;
  available_spots: number;
  is_full: boolean;
};

type PaginationData = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number;
  to: number;
  data: Enrollment[];
  links: Array<{
    url: string | null;
    label: string;
    active: boolean;
  }>;
};

type PageProps = {
  enrollments: PaginationData;
  students: Student[];
  units: Unit[];
  schools: School[];
  programs: Program[];
  classes: Class[];
  semesters: Semester[];
  stats?: {
    total: number;
    active: number;
    dropped: number;
    completed: number;
    total_enrollments?: number;
    active_enrollments?: number;
  };
  can: {
    create: boolean;
    update: boolean;
    delete: boolean;
  };
  filters: {
    search?: string;
    student_code?: string;
    unit_id?: string | number;
    semester_id?: string | number;
    school_id?: string | number;
    program_id?: string | number;
    class_id?: string | number;
    status?: string;
  };
  flash?: {
    success?: string;
    error?: string;
  };
  auth: {
    user: any;
  };
};

export default function EnrollmentsIndex() {
  const { props } = usePage<PageProps>();
  const { 
    enrollments, 
    students = [], 
    units = [],
    schools = [], 
    programs = [], 
    classes = [],
    semesters = [], 
    stats, 
    can = { create: false, update: false, delete: false }, 
    filters = {}, 
    flash,
    auth
  } = props;

  // State management
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [selectedEnrollment, setSelectedEnrollment] = useState<Enrollment | null>(null);
  const [loading, setLoading] = useState(false);
  const [showFilters, setShowFilters] = useState(false);

  // Form state
  const [formData, setFormData] = useState<EnrollmentFormData>({
    student_code: '',
    school_id: '',
    program_id: '',
    class_id: '',
    semester_id: '',
    unit_ids: [],
    status: 'enrolled'
  });

  // Available classes and units for selection
  const [availableClasses, setAvailableClasses] = useState<Class[]>([]);
  const [availableUnits, setAvailableUnits] = useState<Unit[]>([]);
  const [capacityInfo, setCapacityInfo] = useState<CapacityInfo | null>(null);
  const [loadingCapacity, setLoadingCapacity] = useState(false);

  // Filter state
  const [searchTerm, setSearchTerm] = useState(filters?.search || '');
  const [selectedSemester, setSelectedSemester] = useState<string | number>(filters?.semester_id || '');
  const [selectedSchool, setSelectedSchool] = useState<string | number>(filters?.school_id || '');
  const [selectedProgram, setSelectedProgram] = useState<string | number>(filters?.program_id || '');
  const [selectedClass, setSelectedClass] = useState<string | number>(filters?.class_id || '');
  const [selectedStudent, setSelectedStudent] = useState<string>(filters?.student_code || '');
  const [selectedUnit, setSelectedUnit] = useState<string | number>(filters?.unit_id || '');
  const [statusFilter, setStatusFilter] = useState<string>(filters?.status || 'all');

  // Load classes when program and semester change
  useEffect(() => {
    if (formData.program_id && formData.semester_id) {
      fetchClasses();
    } else {
      setAvailableClasses([]);
      setFormData(prev => ({ ...prev, class_id: '' }));
    }
  }, [formData.program_id, formData.semester_id]);

  // Load capacity info when class and semester change
  useEffect(() => {
    if (formData.class_id && formData.semester_id) {
      fetchCapacityInfo();
    } else {
      setCapacityInfo(null);
    }
  }, [formData.class_id, formData.semester_id]);

  const fetchClasses = async () => {
    try {
      const response = await fetch(`/admin/api/classes/by-program-semester?program_id=${formData.program_id}&semester_id=${formData.semester_id}`);
      const data = await response.json();
      setAvailableClasses(data);
    } catch (error) {
      console.error('Failed to fetch classes:', error);
      toast.error('Failed to load classes');
      setAvailableClasses([]);
    }
  };

  const fetchCapacityInfo = async () => {
    setLoadingCapacity(true);
    try {
      const response = await fetch(`/admin/api/class-capacity?class_id=${formData.class_id}&semester_id=${formData.semester_id}`);
      const data = await response.json();
      setCapacityInfo(data);
    } catch (error) {
      console.error('Failed to fetch capacity info:', error);
      setCapacityInfo(null);
    } finally {
      setLoadingCapacity(false);
    }
  };

  const fetchUnitsForClass = async () => {
    try {
      const response = await fetch(`/admin/api/units/by-class?class_id=${formData.class_id}&semester_id=${formData.semester_id}`);
      const data = await response.json();
      setAvailableUnits(data);
    } catch (error) {
      console.error('Failed to fetch units:', error);
      toast.error('Failed to load units');
      setAvailableUnits([]);
    }
  };

  // Filtered programs based on school selection
  const filteredPrograms = programs.filter(program => 
    !formData.school_id || program.school_id === formData.school_id
  );

  // Filter programs for filter dropdown
  const filterPrograms = programs.filter(program => 
    !selectedSchool || program.school_id === selectedSchool
  );

  // Reset dependent fields when selections change
  useEffect(() => {
    if (formData.school_id) {
      setFormData(prev => ({ ...prev, program_id: '', class_id: '' }));
    }
  }, [formData.school_id]);

  useEffect(() => {
    if (formData.program_id) {
      setFormData(prev => ({ ...prev, class_id: '' }));
    }
  }, [formData.program_id]);

  useEffect(() => {
    if (formData.class_id) {
      setFormData(prev => ({ ...prev, unit_ids: [] }));
      fetchUnitsForClass();
    } else {
      setAvailableUnits([]);
    }
  }, [formData.class_id]);

  // Handle flash messages
  useEffect(() => {
    if (flash?.success) {
      toast.success(flash.success);
    }
    if (flash?.error) {
      toast.error(flash.error);
    }
  }, [flash]);

  // Pagination and Filter Functions
  const handleSearch = () => {
    router.get('/admin/enrollments', {
      search: searchTerm,
      semester_id: selectedSemester,
      school_id: selectedSchool,
      program_id: selectedProgram,
      class_id: selectedClass,
      student_code: selectedStudent,
      unit_id: selectedUnit,
      status: statusFilter !== 'all' ? statusFilter : undefined
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
    setSelectedClass('');
    setSelectedStudent('');
    setSelectedUnit('');
    setStatusFilter('all');
    router.get('/admin/enrollments', {}, {
      preserveState: true,
      replace: true
    });
  };

  const handlePaginationClick = (url: string | null) => {
    if (url) {
      router.get(url, {}, {
        preserveState: true,
        replace: true
      });
    }
  };

  // Capacity Warning Component
  const CapacityWarning: React.FC<{ capacityInfo: CapacityInfo; loadingCapacity: boolean }> = ({ 
    capacityInfo, 
    loadingCapacity 
  }) => {
    if (loadingCapacity) {
      return (
        <div className="flex items-center gap-2 p-3 bg-blue-50 border border-blue-200 rounded-lg">
          <Info className="w-4 h-4 text-blue-600" />
          <span className="text-sm text-blue-700">Loading capacity information...</span>
        </div>
      );
    }

    if (!capacityInfo) return null;

    const { capacity, current_enrollments, available_spots, is_full } = capacityInfo;

    if (is_full) {
      return (
        <div className="flex items-center gap-2 p-3 bg-red-50 border border-red-200 rounded-lg">
          <AlertTriangle className="w-4 h-4 text-red-600" />
          <div className="text-sm">
            <span className="font-medium text-red-800">Class is Full!</span>
            <span className="text-red-700 ml-1">
              ({current_enrollments}/{capacity} students enrolled)
            </span>
          </div>
        </div>
      );
    }

    if (available_spots <= 2) {
      return (
        <div className="flex items-center gap-2 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
          <AlertTriangle className="w-4 h-4 text-yellow-600" />
          <div className="text-sm">
            <span className="font-medium text-yellow-800">Limited Space!</span>
            <span className="text-yellow-700 ml-1">
              Only {available_spots} spots remaining ({current_enrollments}/{capacity} enrolled)
            </span>
          </div>
        </div>
      );
    }

    return (
      <div className="flex items-center gap-2 p-3 bg-green-50 border border-green-200 rounded-lg">
        <Check className="w-4 h-4 text-green-600" />
        <div className="text-sm">
          <span className="font-medium text-green-800">Space Available</span>
          <span className="text-green-700 ml-1">
            {available_spots} spots remaining ({current_enrollments}/{capacity} enrolled)
          </span>
        </div>
      </div>
    );
  };

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

  // Pagination Component
  const Pagination: React.FC<{ paginationData: PaginationData }> = ({ paginationData }) => {
    const { current_page, last_page, from, to, total, links } = paginationData;

    if (last_page <= 1) return null;

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

  // Form handlers
  const handleCreateEnrollment = () => {
    setFormData({
      student_code: '',
      school_id: '',
      program_id: '',
      class_id: '',
      semester_id: '',
      unit_ids: [],
      status: 'enrolled'
    });
    setCapacityInfo(null);
    setIsCreateModalOpen(true);
  };

  const handleEditEnrollment = (enrollment: Enrollment) => {
    setSelectedEnrollment(enrollment);
    setFormData({
      student_code: enrollment.student_code,
      school_id: enrollment.unit?.school_id || '',
      program_id: enrollment.class?.program_id || '',
      class_id: enrollment.class_id,
      semester_id: enrollment.semester_id,
      unit_ids: [enrollment.unit_id],
      status: enrollment.status
    });
    setIsEditModalOpen(true);
  };

  const handleDeleteEnrollment = (enrollmentId: number) => {
    if (confirm('Are you sure you want to delete this enrollment?')) {
      router.delete(`/admin/enrollments/${enrollmentId}`, {
        onSuccess: () => {
          toast.success('Enrollment deleted successfully!');
        },
        onError: () => {
          toast.error('Failed to delete enrollment');
        }
      });
    }
  };

  const handleUpdateEnrollment = () => {
    if (!selectedEnrollment) return;
    
    setLoading(true);
    
    router.put(`/admin/enrollments/${selectedEnrollment.id}`, {
      status: formData.status
    }, {
      onSuccess: () => {
        toast.success('Enrollment updated successfully!');
        setIsEditModalOpen(false);
        setSelectedEnrollment(null);
      },
      onError: (errors) => {
        toast.error(errors.error || 'Failed to update enrollment');
      },
      onFinish: () => {
        setLoading(false);
      }
    });
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
      unit_ids: availableUnits.map(unit => unit.id)
    }));
  };

  const handleClearAllUnits = () => {
    setFormData(prev => ({ ...prev, unit_ids: [] }));
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!formData.class_id) {
      toast.error('Please select a class');
      return;
    }
    
    if (formData.unit_ids.length === 0) {
      toast.error('Please select at least one unit');
      return;
    }

    if (capacityInfo?.is_full) {
      toast.error('Cannot enroll student. The selected class has reached its maximum capacity.');
      return;
    }

    setLoading(true);

    router.post('/admin/enrollments', formData, {
      onSuccess: (response) => {
        toast.success('Enrollment created successfully!');
        setIsCreateModalOpen(false);
        setFormData({
          student_code: '',
          school_id: '',
          program_id: '',
          class_id: '',
          semester_id: '',
          unit_ids: [],
          status: 'enrolled'
        });
        setCapacityInfo(null);
      },
      onError: (errors) => {
        toast.error(errors.error || 'Failed to create enrollment');
      },
      onFinish: () => {
        setLoading(false);
      }
    });
  };

  return (
    <AuthenticatedLayout user={auth.user}>
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
                    Manage student enrollments in specific classes and units
                  </p>
                  {stats && (
                    <div className="flex items-center gap-4 mt-4">
                      <div className="text-sm text-slate-600">
                        Total Students: <span className="font-semibold">{stats.total}</span>
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
                      {stats.total_enrollments && (
                        <div className="text-sm text-slate-600">
                          Total Records: <span className="font-semibold">{stats.total_enrollments}</span>
                        </div>
                      )}
                    </div>
                  )}
                </div>
                
                <div className="flex items-center gap-3">               
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

          {/* Search and Filters Section */}
          <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-lg border border-slate-200/50 mb-8 overflow-hidden">
            <div className="p-6">
              <div className="flex flex-col lg:flex-row gap-4 items-start lg:items-center justify-between">
                {/* Search Bar */}
                <div className="flex-1 max-w-md">
                  <div className="relative">
                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-5 h-5" />
                    <input
                      type="text"
                      placeholder="Search students, units, or classes..."
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
                    {/* Student Filter */}
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Student</label>
                      <input
                        type="text"
                        placeholder="e.g., BBIT0001"
                        value={selectedStudent}
                        onChange={(e) => setSelectedStudent(e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                      />
                    </div>

                    {/* School Filter */}
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">School</label>
                      <select
                        value={selectedSchool}
                        onChange={(e) => {
                          setSelectedSchool(e.target.value);
                          setSelectedProgram(''); // Reset program when school changes
                        }}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                      >
                        <option value="">All Schools</option>
                        {schools.map(school => (
                          <option key={school.id} value={school.id}>
                            {school.code}
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
                        {filterPrograms.map(program => (
                          <option key={program.id} value={program.id}>
                            {program.code}
                          </option>
                        ))}
                      </select>
                    </div>

                    {/* Class Filter */}
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Class</label>
                      <select
                        value={selectedClass}
                        onChange={(e) => setSelectedClass(e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                      >
                        <option value="">All Classes</option>
                        {classes.map(cls => (
                          <option key={cls.id} value={cls.id}>
                            {cls.name} Sec {cls.section}
                          </option>
                        ))}
                      </select>
                    </div>

                    {/* Semester Filter */}
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Semester</label>
                      <select
                        value={selectedSemester}
                        onChange={(e) => setSelectedSemester(e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                      >
                        <option value="">All Semesters</option>
                        {semesters.map(semester => (
                          <option key={semester.id} value={semester.id}>
                            {semester.name}
                          </option>
                        ))}
                      </select>
                    </div>

                    {/* Unit Filter */}
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Unit</label>
                      <select
                        value={selectedUnit}
                        onChange={(e) => setSelectedUnit(e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                      >
                        <option value="">All Units</option>
                        {units.map(unit => (
                          <option key={unit.id} value={unit.id}>
                            {unit.code}
                          </option>
                        ))}
                      </select>
                    </div>

                    {/* Status Filter */}
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Status</label>
                      <select
                        value={statusFilter}
                        onChange={(e) => setStatusFilter(e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                      >
                        <option value="all">All Statuses</option>
                        <option value="enrolled">Enrolled</option>
                        <option value="dropped">Dropped</option>
                        <option value="completed">Completed</option>
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
                </div>
              )}
            </div>
          </div>

          {/* Create Modal */}
          {isCreateModalOpen && (
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
              <div className="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                <div className="bg-gradient-to-r from-emerald-500 via-emerald-600 to-teal-600 p-6 rounded-t-2xl">
                  <div className="flex items-center justify-between">
                    <h3 className="text-xl font-semibold text-white">
                      Create New Enrollment
                    </h3>
                    <button
                      onClick={() => setIsCreateModalOpen(false)}
                      className="text-white hover:text-gray-200 transition-colors"
                    >
                      <X className="w-6 h-6" />
                    </button>
                  </div>
                </div>

                <div className="p-6 space-y-6">
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
                      placeholder="e.g., BBIT0007"
                      required
                    />
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
                        class_id: ''
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
                        class_id: ''
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
                        class_id: '',
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

                  {/* Class Selection */}
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Class *
                    </label>
                    <select
                      value={formData.class_id}
                      onChange={(e) => setFormData(prev => ({ 
                        ...prev, 
                        class_id: e.target.value ? parseInt(e.target.value) : ''
                      }))}
                      className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                      required
                      disabled={!formData.program_id || !formData.semester_id}
                    >
                      <option value="">Select Class</option>
                      {availableClasses.map(cls => (
                        <option key={cls.id} value={cls.id}>
                          {cls.display_name || `${cls.name} Section ${cls.section}`} (Capacity: {cls.capacity})
                        </option>
                      ))}
                    </select>
                    {(!formData.program_id || !formData.semester_id) && (
                      <p className="mt-1 text-xs text-gray-500">
                        Select program and semester first to see classes
                      </p>
                    )}
                  </div>

                  {/* Capacity Warning Display */}
                  {capacityInfo && formData.class_id && (
                    <CapacityWarning capacityInfo={capacityInfo} loadingCapacity={loadingCapacity} />
                  )}

                  {/* Units Selection */}
                  {formData.class_id && (
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Select Units to Enroll *
                      </label>
                      <div className="flex flex-col gap-2">
                        {availableUnits.length > 0 ? (
                          availableUnits.map(unit => (
                            <label key={unit.id} className="flex items-center gap-2">
                              <input
                                type="checkbox"
                                checked={formData.unit_ids.includes(unit.id)}
                                onChange={() => handleUnitToggle(unit.id)}
                                className="form-checkbox h-4 w-4 text-emerald-600"
                              />
                              <span>
                                {unit.code} - {unit.name} ({unit.credit_hours} credits)
                              </span>
                            </label>
                          ))
                        ) : (
                          <p className="mt-2 text-xs text-gray-500">
                            No units available for this class, semester, and program.
                          </p>
                        )}
                      </div>
                      {availableUnits.length > 1 && (
                        <div className="flex gap-2 mt-2">
                          <button
                            type="button"
                            onClick={handleSelectAllUnits}
                            className="px-2 py-1 text-xs bg-emerald-100 rounded hover:bg-emerald-200"
                          >
                            Select All
                          </button>
                          <button
                            type="button"
                            onClick={handleClearAllUnits}
                            className="px-2 py-1 text-xs bg-gray-100 rounded hover:bg-gray-200"
                          >
                            Clear All
                          </button>
                        </div>
                      )}
                    </div>
                  )}

                  {/* Status Selection */}
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Default Status *
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
                      onClick={() => setIsCreateModalOpen(false)}
                      className="px-6 py-3 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors font-medium"
                    >
                      Cancel
                    </button>
                    <button
                      type="button"
                      onClick={handleSubmit}
                      disabled={loading || formData.unit_ids.length === 0 || !formData.class_id || capacityInfo?.is_full}
                      className="px-6 py-3 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white rounded-lg hover:from-emerald-600 hover:to-emerald-700 transition-all duration-200 font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      {loading ? 'Processing...' : 
                       capacityInfo?.is_full ? 'Class Full - Cannot Enroll' :
                       `Enroll in ${formData.unit_ids.length} Unit${formData.unit_ids.length !== 1 ? 's' : ''}`}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* Edit Modal */}
          {isEditModalOpen && selectedEnrollment && (
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
              <div className="bg-white rounded-2xl shadow-2xl max-w-lg w-full">
                <div className="bg-gradient-to-r from-blue-500 via-blue-600 to-indigo-600 p-6 rounded-t-2xl">
                  <div className="flex items-center justify-between">
                    <h3 className="text-xl font-semibold text-white">
                      Edit Enrollment Status
                    </h3>
                    <button
                      onClick={() => setIsEditModalOpen(false)}
                      className="text-white hover:text-gray-200 transition-colors"
                    >
                      <X className="w-6 h-6" />
                    </button>
                  </div>
                </div>

                <div className="p-6 space-y-6">
                  <div className="bg-gray-50 p-4 rounded-lg">
                    <p className="text-sm text-gray-600">Student: <span className="font-medium">{selectedEnrollment.student_code}</span></p>
                    <p className="text-sm text-gray-600">Unit: <span className="font-medium">{selectedEnrollment.unit?.code} - {selectedEnrollment.unit?.name}</span></p>
                    <p className="text-sm text-gray-600">Class: <span className="font-medium">{selectedEnrollment.class?.name} Section {selectedEnrollment.class?.section}</span></p>
                  </div>

                  {/* Status Selection */}
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Status *
                    </label>
                    <select
                      value={formData.status}
                      onChange={(e) => setFormData(prev => ({ ...prev, status: e.target.value as 'enrolled' | 'dropped' | 'completed' }))}
                      className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
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
                      onClick={() => setIsEditModalOpen(false)}
                      className="px-6 py-3 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors font-medium"
                    >
                      Cancel
                    </button>
                    <button
                      type="button"
                      onClick={handleUpdateEnrollment}
                      disabled={loading}
                      className="px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 text-white rounded-lg hover:from-blue-600 hover:to-indigo-700 transition-all duration-200 font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      {loading ? 'Processing...' : 'Update Status'}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* Enrollments Table */}
          <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-2xl border border-slate-200/50 overflow-hidden">
            <div className="p-8">
              {enrollments.data && enrollments.data.length > 0 ? (
                <>
                  <div className="overflow-x-auto">
                    <table className="w-full">
                      <thead>
                        <tr className="border-b border-gray-200">
                          <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Student
                          </th>
                          <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Unit
                          </th>
                          <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Class
                          </th>
                          <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                          </th>
                          <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Enrolled Date
                          </th>
                          <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                          </th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-gray-200">
                        {enrollments.data.map((enrollment) => (
                          <tr key={enrollment.id} className="hover:bg-gray-50">
                            <td className="px-4 py-4 whitespace-nowrap">
                              <div className="text-sm font-medium text-gray-900">
                                {enrollment.student_code}
                              </div>
                              <div className="text-sm text-gray-500">
                                {enrollment.first_name} {enrollment.last_name}
                              </div>
                            </td>
                            <td className="px-4 py-4 whitespace-nowrap">
                              <div className="text-sm font-medium text-gray-900">
                                {enrollment.unit?.code}
                              </div>
                              <div className="text-sm text-gray-500">
                                {enrollment.unit?.name}
                              </div>
                            </td>
                            <td className="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                              {enrollment.class?.name} Section {enrollment.class?.section}
                            </td>
                            <td className="px-4 py-4 whitespace-nowrap">
                              <StatusBadge status={enrollment.status} />
                            </td>
                            <td className="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                              {new Date(enrollment.enrollment_date).toLocaleDateString()}
                            </td>
                            <td className="px-4 py-4 whitespace-nowrap text-right text-sm font-medium">
                              <div className="flex space-x-2">
                                {can.update && (
                                  <button 
                                    onClick={() => handleEditEnrollment(enrollment)}
                                    className="text-indigo-600 hover:text-indigo-900"
                                    title="Edit Enrollment"
                                  >
                                    <Edit className="w-4 h-4" />
                                  </button>
                                )}
                                {can.delete && (
                                  <button 
                                    onClick={() => handleDeleteEnrollment(enrollment.id)}
                                    className="text-red-600 hover:text-red-900"
                                    title="Delete Enrollment"
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
                  
                  {/* Pagination Component */}
                  <Pagination paginationData={enrollments} />
                </>
              ) : (
                <div className="text-center py-12">
                  <Users className="mx-auto h-12 w-12 text-gray-400 mb-4" />
                  <h3 className="text-lg font-medium text-gray-900 mb-2">No Enrollments Found</h3>
                  <p className="text-gray-500">
                    {Object.values(filters).some(filter => filter) 
                      ? "No enrollments match your current filters. Try adjusting your search criteria."
                      : "Get started by creating a new enrollment."
                    }
                  </p>
                  {can.create && (
                    <button
                      onClick={handleCreateEnrollment}
                      className="mt-4 inline-flex items-center px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700"
                    >
                      <Plus className="w-4 h-4 mr-2" />
                      Create First Enrollment
                    </button>
                  )}
                  {Object.values(filters).some(filter => filter) && (
                    <button
                      onClick={clearFilters}
                      className="mt-2 ml-4 inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200"
                    >
                      <X className="w-4 h-4 mr-2" />
                      Clear Filters
                    </button>
                  )}
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}