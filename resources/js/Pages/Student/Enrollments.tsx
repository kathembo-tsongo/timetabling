import React, { useState, useEffect } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { toast } from 'react-hot-toast';
import Pagination from '@/components/ui/Pagination';

interface Unit {
  id: number;
  code: string;
  name: string;
  credit_hours?: number;
  school?: {
    id: number;
    name: string;
    code: string;
  };
  program?: {
    id: number;
    name: string;
    code: string;
  };
}

interface Class {
  id: number;
  name: string;
  section: string;
  display_name: string;
  year_level: number;
  capacity: number;
  program_id: number;
  semester_id: number;
}

interface School {
  id: number;
  code: string;
  name: string;
}

interface Program {
  id: number;
  code: string;
  name: string;
  school_id: number;
}

interface Semester {
  id: number;
  name: string;
  is_active: boolean;
}

interface Enrollment {
  id: number;
  unit: {
    code: string;
    name: string;
  };
  semester: {
    name: string;
  };
  lecturer?: {
    first_name: string;
    last_name: string;
  };
  student?: {
    first_name: string;
    last_name: string;
    code: string;
  };
  group: {
    name: string;
  };
}

interface EnrollmentFormData {
  student_code: string;
  school_id: number | '';
  program_id: number | '';
  class_id: number | '';
  semester_id: number | '';
  unit_ids: number[];
  status: 'enrolled' | 'dropped' | 'completed';
}

interface Props {
  enrollments?: {
    data?: Enrollment[];
  };
  availableUnits?: Unit[];
  schools?: School[];
  programs?: Program[];
  classes?: Class[];
  semesters?: Semester[];
  currentSemester?: {
    id: number;
    name: string;
  };
  selectedSemesterId?: number;
  userRoles?: {
    isAdmin: boolean;
    isLecturer: boolean;
    isStudent: boolean;
  };
}

export default function Enrollments({ 
  enrollments = { data: [] }, 
  availableUnits = [],
  schools = [],
  programs = [],
  classes = [],
  semesters = [],
  currentSemester,
  selectedSemesterId,
  userRoles 
}: Props) {
  const { auth } = usePage().props as any;
  const isAdmin = userRoles?.isAdmin || false;
  const isLecturer = userRoles?.isLecturer || false;
  const isStudent = userRoles?.isStudent || false;
  
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedSemester, setSelectedSemester] = useState('all');
  const [viewMode, setViewMode] = useState<'grid' | 'table'>('table');
  const [enrollingUnits, setEnrollingUnits] = useState<Set<number>>(new Set());
  const [isEnrollModalOpen, setIsEnrollModalOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  
  // Form state for enrollment modal
  const [formData, setFormData] = useState<EnrollmentFormData>({
    student_code: auth?.user?.code || '',
    school_id: '',
    program_id: '',
    class_id: '',
    semester_id: '',
    unit_ids: [],
    status: 'enrolled'
  });

  // Available data for enrollment
  const [availableClasses, setAvailableClasses] = useState<Class[]>([]);
  const [availableUnitsForClass, setAvailableUnitsForClass] = useState<Unit[]>([]);

  // Extract unique semesters for filter
  const enrollmentSemesters = [...new Set(enrollments.data?.map(e => e.semester.name) || [])];

  // Filter enrollments based on search and semester
  const filteredEnrollments = enrollments.data?.filter(enrollment => {
    const matchesSearch = searchTerm === '' || 
      enrollment.unit.code.toLowerCase().includes(searchTerm.toLowerCase()) ||
      enrollment.unit.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      (enrollment.student && 
        `${enrollment.student.first_name} ${enrollment.student.last_name}`.toLowerCase().includes(searchTerm.toLowerCase())) ||
      (enrollment.lecturer && 
        `${enrollment.lecturer.first_name} ${enrollment.lecturer.last_name}`.toLowerCase().includes(searchTerm.toLowerCase()));
    
    const matchesSemester = selectedSemester === 'all' || enrollment.semester.name === selectedSemester;
    
    return matchesSearch && matchesSemester;
  }) || [];

  // Filter available units based on search
  const filteredAvailableUnits = (availableUnits || []).filter(unit => {
    const matchesSearch = searchTerm === '' || 
      unit.code.toLowerCase().includes(searchTerm.toLowerCase()) ||
      unit.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      (unit.school?.name.toLowerCase().includes(searchTerm.toLowerCase())) ||
      (unit.program?.name.toLowerCase().includes(searchTerm.toLowerCase()));
    
    return matchesSearch;
  });

  // Computed values
  const filteredPrograms = programs.filter(program => 
    !formData.school_id || program.school_id === formData.school_id
  );

  // Get page title and icon based on user role
  const getPageInfo = () => {
    if (isAdmin) return { title: "All Enrollments", subtitle: "Manage student enrollments across all units", icon: "👥" };
    if (isLecturer) return { title: "My Teaching Units", subtitle: "Units you're currently teaching", icon: "👨‍🏫" };
    return { title: "My Enrollments", subtitle: "Units you're currently enrolled in", icon: "📚" };
  };

  const pageInfo = getPageInfo();

  // Stats calculation
  const stats = {
    total: filteredEnrollments.length,
    uniqueUnits: [...new Set(filteredEnrollments.map(e => e.unit.code))].length,
    uniqueSemesters: [...new Set(filteredEnrollments.map(e => e.semester.name))].length,
    uniqueStudents: isAdmin ? [...new Set(filteredEnrollments.map(e => e.student?.code))].filter(Boolean).length : null,
    availableUnits: isStudent ? filteredAvailableUnits.length : null
  };

  // API calls for enrollment - FIXED
  const fetchClasses = async () => {
    if (!formData.program_id || !formData.semester_id) {
      setAvailableClasses([]);
      return;
    }
    
    try {
      const response = await fetch(`/student/api/classes/by-program-semester?program_id=${formData.program_id}&semester_id=${formData.semester_id}`);
      if (!response.ok) {
        throw new Error('Failed to fetch classes');
      }
      const data = await response.json();
      console.log('Fetched classes:', data); // Debug log
      setAvailableClasses(data);
    } catch (error) {
      console.error('Failed to fetch classes:', error);
      toast.error('Failed to load classes');
      setAvailableClasses([]);
    }
  };

  const fetchUnitsForClass = async () => {
    if (!formData.class_id || !formData.semester_id) {
      setAvailableUnitsForClass([]);
      return;
    }
    
    try {
      const response = await fetch(`/student/api/units/by-class?class_id=${formData.class_id}&semester_id=${formData.semester_id}`);
      if (!response.ok) {
        throw new Error('Failed to fetch units');
      }
      const data = await response.json();
      console.log('Fetched units:', data); // Debug log
      setAvailableUnitsForClass(data);
    } catch (error) {
      console.error('Failed to fetch units:', error);
      toast.error('Failed to load units');
      setAvailableUnitsForClass([]);
    }
  };

  // Effects for enrollment form - UPDATED
  useEffect(() => {
    console.log('Program/Semester changed:', formData.program_id, formData.semester_id); // Debug log
    if (formData.program_id && formData.semester_id) {
      fetchClasses();
    } else {
      setAvailableClasses([]);
      setFormData(prev => ({ ...prev, class_id: '', unit_ids: [] }));
    }
  }, [formData.program_id, formData.semester_id]);

  useEffect(() => {
    console.log('Class changed:', formData.class_id); // Debug log
    if (formData.class_id && formData.semester_id) {
      setFormData(prev => ({ ...prev, unit_ids: [] }));
      fetchUnitsForClass();
    } else {
      setAvailableUnitsForClass([]);
      setFormData(prev => ({ ...prev, unit_ids: [] }));
    }
  }, [formData.class_id, formData.semester_id]);

  // Reset program when school changes
  useEffect(() => {
    if (formData.school_id) {
      setFormData(prev => ({ 
        ...prev, 
        program_id: '', 
        class_id: '', 
        unit_ids: [] 
      }));
      setAvailableClasses([]);
      setAvailableUnitsForClass([]);
    }
  }, [formData.school_id]);

  // Reset class when program changes (but not when semester changes)
  useEffect(() => {
    setFormData(prev => ({ 
      ...prev, 
      class_id: '', 
      unit_ids: [] 
    }));
    setAvailableClasses([]);
    setAvailableUnitsForClass([]);
  }, [formData.program_id]);

  // Handle opening enrollment modal
  const handleOpenEnrollModal = () => {
    setFormData({
      student_code: auth?.user?.code || '',
      school_id: '',
      program_id: '',
      class_id: '',
      semester_id: currentSemester?.id || '',
      unit_ids: [],
      status: 'enrolled'
    });
    setAvailableClasses([]);
    setAvailableUnitsForClass([]);
    setIsEnrollModalOpen(true);
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
      unit_ids: availableUnitsForClass.map(unit => unit.id)
    }));
  };

  const handleClearAllUnits = () => {
    setFormData(prev => ({ ...prev, unit_ids: [] }));
  };

  const handleSubmitEnrollment = (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!formData.class_id) {
      toast.error('Please select a class');
      return;
    }
    
    if (formData.unit_ids.length === 0) {
      toast.error('Please select at least one unit');
      return;
    }

    setLoading(true);

    router.post('/student/enrollments', formData, {
      onSuccess: (response) => {
        toast.success('Enrollment completed successfully!');
        setIsEnrollModalOpen(false);
        setFormData({
          student_code: auth?.user?.code || '',
          school_id: '',
          program_id: '',
          class_id: '',
          semester_id: '',
          unit_ids: [],
          status: 'enrolled'
        });
        setAvailableClasses([]);
        setAvailableUnitsForClass([]);
      },
      onError: (errors) => {
        toast.error(errors.error || 'Failed to complete enrollment');
      },
      onFinish: () => {
        setLoading(false);
      }
    });
  };

  // Legacy single unit enrollment function
  const handleEnroll = async (unitId: number) => {
    setEnrollingUnits(prev => new Set(prev).add(unitId));
    
    try {
      await router.post('/student/enrollments', {
        unit_id: unitId,
        semester_id: selectedSemesterId || currentSemester?.id
      }, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
          setEnrollingUnits(prev => {
            const newSet = new Set(prev);
            newSet.delete(unitId);
            return newSet;
          });
        }
      });
    } catch (error) {
      console.error('Enrollment error:', error);
      setEnrollingUnits(prev => {
        const newSet = new Set(prev);
        newSet.delete(unitId);
        return newSet;
      });
    }
  };

  return (
    <AuthenticatedLayout>
      <Head title={pageInfo.title} />

      <div className="min-h-screen bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          {/* Header Section */}
          <div className="mb-8">
            <div className="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
              <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                <div className="flex items-center space-x-4">
                  <div className="w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-600 rounded-2xl flex items-center justify-center text-3xl">
                    {pageInfo.icon}
                  </div>
                  <div>
                    <h1 className="text-3xl font-bold text-gray-900 mb-2">
                      {pageInfo.title}
                    </h1>
                    <p className="text-gray-600 text-lg">
                      {pageInfo.subtitle}
                    </p>
                  </div>
                </div>
                
                {/* Quick Stats */}
                <div className="mt-6 lg:mt-0 grid grid-cols-2 lg:grid-cols-4 gap-4">
                  <div className="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl p-4 text-white text-center">
                    <div className="text-2xl font-bold">{stats.total}</div>
                    <div className="text-xs opacity-90">Enrolled</div>
                  </div>
                  <div className="bg-gradient-to-r from-green-500 to-green-600 rounded-xl p-4 text-white text-center">
                    <div className="text-2xl font-bold">{stats.uniqueUnits}</div>
                    <div className="text-xs opacity-90">Unique Units</div>
                  </div>
                  <div className="bg-gradient-to-r from-purple-500 to-purple-600 rounded-xl p-4 text-white text-center">
                    <div className="text-2xl font-bold">{stats.uniqueSemesters}</div>
                    <div className="text-xs opacity-90">Semesters</div>
                  </div>
                  {/* {stats.availableUnits !== null && (
                    <div className="bg-gradient-to-r from-indigo-500 to-indigo-600 rounded-xl p-4 text-white text-center">
                      <div className="text-2xl font-bold">{stats.availableUnits}</div>
                      <div className="text-xs opacity-90">Available</div>
                    </div>
                  )} */}
                </div>
              </div>
            </div>
          </div>

          {/* Enrollment Call-to-Action Section */}
          {isStudent && (
            <div className="mb-8">
              <div className="bg-gradient-to-r from-green-50 to-emerald-50 rounded-2xl shadow-lg border border-green-200 p-6">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                  <div className="flex items-center space-x-4 mb-4 sm:mb-0">
                    <div className="w-12 h-12 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center">
                      <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                      </svg>
                    </div>
                    <div>
                      <h3 className="text-lg font-semibold text-green-800">Ready to Enroll in New Units?</h3>
                      <p className="text-green-600 text-sm">
                        Select your semester, program, and class to see available units
                      </p>
                    </div>
                  </div>
                  <div className="flex items-center space-x-3">
                    <button
                      onClick={handleOpenEnrollModal}
                      className="inline-flex items-center px-6 py-3 bg-gradient-to-r from-green-600 to-emerald-600 text-white font-medium rounded-xl hover:from-green-700 hover:to-emerald-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-all duration-200 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5"
                    >
                      <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                      </svg>
                      Enroll in Units
                    </button>
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* Controls Section */}
          <div className="mb-8">
            <div className="bg-white rounded-2xl shadow-lg p-6 border border-gray-100">
              <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                {/* Search and Filters */}
                <div className="flex flex-col sm:flex-row gap-4 flex-1">
                  {/* Search */}
                  <div className="relative flex-1 max-w-md">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <svg className="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                      </svg>
                    </div>
                    <input
                      type="text"
                      value={searchTerm}
                      onChange={(e) => setSearchTerm(e.target.value)}
                      placeholder="Search units, students, or lecturers..."
                      className="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-150"
                    />
                  </div>

                  {/* Semester Filter */}
                  <div className="relative">
                    <select
                      value={selectedSemester}
                      onChange={(e) => setSelectedSemester(e.target.value)}
                      className="appearance-none bg-white border border-gray-300 rounded-xl px-4 py-3 pr-8 text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-150"
                    >
                      <option value="all">All Semesters</option>
                      {enrollmentSemesters.map(semester => (
                        <option key={semester} value={semester}>{semester}</option>
                      ))}
                    </select>
                    <div className="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                      <svg className="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                      </svg>
                    </div>
                  </div>
                </div>

                {/* View Toggle */}
                <div className="flex items-center bg-gray-100 rounded-xl p-1">
                  <button
                    onClick={() => setViewMode('table')}
                    className={`flex items-center px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 ${
                      viewMode === 'table' 
                        ? 'bg-white text-gray-900 shadow-sm' 
                        : 'text-gray-500 hover:text-gray-700'
                    }`}
                  >
                    <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 10h18M3 6h18m-9 8h9" />
                    </svg>
                    Table
                  </button>
                  <button
                    onClick={() => setViewMode('grid')}
                    className={`flex items-center px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 ${
                      viewMode === 'grid' 
                        ? 'bg-white text-gray-900 shadow-sm' 
                        : 'text-gray-500 hover:text-gray-700'
                    }`}
                  >
                    <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                    </svg>
                    Grid
                  </button>
                </div>
              </div>
            </div>
          </div>

          {/* Current Enrollments Section */}
          {filteredEnrollments.length > 0 ? (
            <div className="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden mb-8">
              <div className="bg-gradient-to-r from-blue-50 to-blue-100 border-b border-blue-200 p-6">
                <h2 className="text-xl font-semibold text-blue-800">Current Enrollments</h2>
                <p className="text-blue-600 mt-1">Units you are currently enrolled in</p>
              </div>
              
              <div className="overflow-x-auto">
                <table className="min-w-full">
                  <thead>
                    <tr className="bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
                      <th className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Unit Code</th>
                      <th className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Unit Name</th>
                      <th className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Group</th>
                      <th className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Semester</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100">
                    {filteredEnrollments.map((enrollment, index) => (
                      <tr key={enrollment.id} className={`hover:bg-blue-50 transition-colors duration-200 ${index % 2 === 0 ? 'bg-white' : 'bg-gray-50'}`}>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <span className="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 border border-blue-200">
                            {enrollment.unit.code}
                          </span>
                        </td>
                        <td className="px-6 py-4">
                          <div className="text-sm font-medium text-gray-900">{enrollment.unit.name}</div>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <span className="inline-flex items-center px-2 py-1 rounded-lg text-xs font-medium bg-green-100 text-green-800 border border-green-200">
                            {enrollment.group?.name || 'N/A'}
                          </span>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <span className="inline-flex items-center px-2 py-1 rounded-lg text-xs font-medium bg-purple-100 text-purple-800 border border-purple-200">
                            {enrollment.semester.name}
                          </span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          ) : (
            <div className="bg-white rounded-2xl shadow-xl border border-gray-100 p-12 text-center mb-8">
              <div className="w-24 h-24 bg-gradient-to-br from-gray-100 to-gray-200 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg className="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                </svg>
              </div>
              <h3 className="text-xl font-semibold text-gray-900 mb-2">No Current Enrollments</h3>
              <p className="text-gray-500 mb-6 max-w-md mx-auto">
                You are not currently enrolled in any units. Click the button below to start enrolling.
              </p>
            </div>
          )}         

          {/* Enrollment Modal */}
          {isEnrollModalOpen && (
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
              <div className="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                <div className="bg-gradient-to-r from-green-500 via-green-600 to-emerald-600 p-6 rounded-t-2xl">
                  <div className="flex items-center justify-between">
                    <h3 className="text-xl font-semibold text-white">Enroll in Units</h3>
                    <button
                      onClick={() => setIsEnrollModalOpen(false)}
                      className="text-white hover:text-gray-200 transition-colors"
                    >
                      <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </div>
                </div>

                <form onSubmit={handleSubmitEnrollment} className="p-6 space-y-6">
                  {/* Student Code (Pre-filled) */}
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Student Number</label>
                    <input
                      type="text"
                      value={formData.student_code}
                      disabled
                      className="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100 text-gray-600"
                    />
                  </div>

                  {/* School Selection */}
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">School *</label>
                    <select
                      value={formData.school_id}
                      onChange={(e) => setFormData(prev => ({ 
                        ...prev, 
                        school_id: e.target.value ? parseInt(e.target.value) : '',
                        program_id: '',
                        class_id: ''
                      }))}
                      className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
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

                  {/* Semester Selection */}
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Semester *</label>
                    <select
                      value={formData.semester_id}
                      onChange={(e) => setFormData(prev => ({ 
                        ...prev, 
                        semester_id: e.target.value ? parseInt(e.target.value) : '',
                        class_id: '',
                        unit_ids: []
                      }))}
                      className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
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


                  {/* Program Selection */}
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Program *</label>
                    <select
                      value={formData.program_id}
                      onChange={(e) => setFormData(prev => ({ 
                        ...prev, 
                        program_id: e.target.value ? parseInt(e.target.value) : '',
                        class_id: ''
                      }))}
                      className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
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

                  {/* Class Selection - FIXED */}
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Class *</label>
                    {(!formData.program_id || !formData.semester_id) ? (
                      <select
                        disabled
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100 text-gray-500"
                      >
                        <option value="">Select Class</option>
                      </select>
                    ) : availableClasses.length === 0 ? (
                      <div className="w-full px-4 py-3 border border-gray-300 rounded-lg bg-yellow-50">
                        <div className="flex items-center">
                          <svg className="w-4 h-4 text-yellow-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16c-.77.833.192 2.5 1.732 2.5z" />
                          </svg>
                          <span className="text-yellow-800 text-sm">No classes available for this program and semester</span>
                        </div>
                      </div>
                    ) : (
                      <select
                        value={formData.class_id}
                        onChange={(e) => setFormData(prev => ({ 
                          ...prev, 
                          class_id: e.target.value ? parseInt(e.target.value) : ''
                        }))}
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                        required
                      >
                        <option value="">Select Class</option>
                        {availableClasses.map(cls => (
                          <option key={cls.id} value={cls.id}>
                            {cls.display_name || `${cls.name} Section ${cls.section}`} (Capacity: {cls.capacity})
                          </option>
                        ))}
                      </select>
                    )}
                    {(!formData.program_id || !formData.semester_id) && (
                      <p className="mt-1 text-xs text-gray-500">
                        Select program and semester first to see classes
                      </p>
                    )}
                  </div>

                  {/* Units Selection */}
                  {formData.class_id && (
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">Select Units to Enroll *</label>
                      <div className="flex flex-col gap-2">
                        {availableUnitsForClass.length > 0 ? (
                          availableUnitsForClass.map(unit => (
                            <label key={unit.id} className="flex items-center gap-2">
                              <input
                                type="checkbox"
                                checked={formData.unit_ids.includes(unit.id)}
                                onChange={() => handleUnitToggle(unit.id)}
                                className="form-checkbox h-4 w-4 text-green-600"
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
                      {availableUnitsForClass.length > 1 && (
                        <div className="flex gap-2 mt-2">
                          <button
                            type="button"
                            onClick={handleSelectAllUnits}
                            className="px-2 py-1 text-xs bg-green-100 rounded hover:bg-green-200"
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

                  {/* Form Actions */}
                  <div className="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200">
                    <button
                      type="button"
                      onClick={() => setIsEnrollModalOpen(false)}
                      className="px-6 py-3 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors font-medium"
                    >
                      Cancel
                    </button>
                    <button
                      type="submit"
                      disabled={loading || formData.unit_ids.length === 0 || !formData.class_id}
                      className="px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-lg hover:from-green-600 hover:to-green-700 transition-all duration-200 font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      {loading ? 'Processing...' : 
                       `Enroll in ${formData.unit_ids.length} Unit${formData.unit_ids.length !== 1 ? 's' : ''}`}
                    </button>
                  </div>
                </form>
              </div>
            </div>
          )}

        </div>
      </div>
    </AuthenticatedLayout>
  );
}