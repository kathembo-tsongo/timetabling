import React, { useState, useEffect } from 'react';
import { Head, usePage, router, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { toast } from 'react-hot-toast';
import {
  Building2,
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
  UserCheck,
  GraduationCap,
  School,
  Calendar
} from "lucide-react";

// Interfaces
interface Semester {
  id: number;
  name: string;
  is_active: boolean;
}

interface Program {
  id: number;
  code: string;
  name: string;
  school_id: number;
  school_name?: string;
}

interface Class {
  id: number;
  name: string;
  semester_id: number;
  program_id: number;
  year_level?: number;
  section?: string;
  capacity?: number;
  students_count?: number;
  is_active: boolean;
  created_at: string;
  updated_at: string;
  semester?: Semester;
  program?: Program;
}

interface PaginationLinks {
  url: string | null;
  label: string;
  active: boolean;
}

interface PaginatedClasses {
  data: Class[];
  links: PaginationLinks[];
  total: number;
  per_page: number;
  current_page: number;
}

interface PageProps {
  classes: PaginatedClasses;
  semesters: Semester[];
  programs: Program[];
  filters: {
    search?: string;
    semester_id?: number;
    program_id?: number;
    year_level?: number;
    per_page?: number;
  };
  can: {
    create_classes: boolean;
    update_classes: boolean;
    delete_classes: boolean;
  };
  flash?: {
    success?: string;
  };
  errors?: {
    error?: string;
  };
}

const ClassesIndex: React.FC = () => {
  const { classes, semesters, programs, filters, can, flash, errors } = usePage<PageProps>().props;

  // State management
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [isBulkModalOpen, setIsBulkModalOpen] = useState(false);
  const [modalType, setModalType] = useState<'create' | 'edit' | 'delete' | 'bulk-create' | ''>('');
  const [currentClass, setCurrentClass] = useState<Class | null>(null);
  const [loading, setLoading] = useState(false);

  // Form state for individual class creation
  const [classForm, setClassForm] = useState({
    name: '',
    semester_id: '',
    program_id: '',
    year_level: 1,
    section: 'A',
    capacity: 50,
    auto_generate_name: true
  });

  // Bulk create state
  const [bulkForm, setBulkForm] = useState({
    semester_id: '',
    program_id: '',
    year_level: 1,
    sections: ['A', 'B'],
    capacity: 50
  });

  // Filter state
  const [searchQuery, setSearchQuery] = useState(filters.search || '');
  const [semesterFilter, setSemesterFilter] = useState(filters.semester_id?.toString() || 'all');
  const [programFilter, setProgramFilter] = useState(filters.program_id?.toString() || 'all');
  const [yearFilter, setYearFilter] = useState(filters.year_level?.toString() || 'all');
  const [itemsPerPage, setItemsPerPage] = useState(filters.per_page || 10);
  const [isViewModalOpen, setIsViewModalOpen] = useState(false);
  // Error handling
  useEffect(() => {
    if (errors?.error) {
      toast.error(errors.error);
    }
    if (flash?.success) {
      toast.success(flash.success);
    }
  }, [errors, flash]);

  // Status badge component
  const StatusBadge: React.FC<{ isActive: boolean }> = ({ isActive }) => {
    return isActive ? (
      <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium text-white bg-green-500">
        Active
      </span>
    ) : (
      <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium text-white bg-gray-500">
        Inactive
      </span>
    );
  };

  // Modal handlers
  const handleOpenModal = (type: 'create' | 'edit' | 'delete' | 'bulk-create', classItem: Class | null = null) => {
    setModalType(type);
    if (type === 'create') {
      setClassForm({
        name: '',
        semester_id: '',
        program_id: '',
        year_level: 1,
        section: 'A',
        capacity: 50,
        auto_generate_name: true
      });
    } else if (type === 'edit' && classItem) {
      setCurrentClass(classItem);
      setClassForm({
        name: classItem.name,
        semester_id: classItem.semester_id.toString(),
        program_id: classItem.program_id.toString(),
        year_level: classItem.year_level || 1,
        section: classItem.section || 'A',
        capacity: classItem.capacity || 50,
        auto_generate_name: false
      });
    } else if (type === 'delete') {
      setCurrentClass(classItem);
    } else if (type === 'bulk-create') {
      setBulkForm({
        semester_id: '',
        program_id: '',
        year_level: 1,
        sections: ['A', 'B'],
        capacity: 50
      });
    }
    setIsModalOpen(true);
  };

  const handleCloseModal = () => {
    setIsModalOpen(false);
    setIsBulkModalOpen(false);
    setModalType('');
    setCurrentClass(null);
  };

  // Generate class name automatically
  const generateClassName = () => {
    if (classForm.program_id && classForm.auto_generate_name) {
      const program = programs.find(p => p.id === parseInt(classForm.program_id));
      if (program) {
        // Generate base class name like "BBIT 1.1" (same for all sections)
        const generatedName = `${program.code} ${classForm.year_level}.1`;
        return generatedName;
      }
    }
    return classForm.name;
  };

  // Form handlers
  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);

    if (modalType === 'create') {
      const finalName = generateClassName();
      const classData = {
        ...classForm,
        name: finalName,
        semester_id: parseInt(classForm.semester_id),
        program_id: parseInt(classForm.program_id)
      };

      router.post(route('admin.classes.store'), classData, {
        onSuccess: () => {
          toast.success('Class created successfully!');
          handleCloseModal();
        },
        onError: (errors) => {
          toast.error(errors.error || 'Failed to create class');
        },
        onFinish: () => setLoading(false)
      });
    } else if (modalType === 'edit' && currentClass) {
      const finalName = generateClassName();
      const classData = {
        ...classForm,
        name: finalName,
        semester_id: parseInt(classForm.semester_id),
        program_id: parseInt(classForm.program_id)
      };

      router.put(route('admin.classes.update', currentClass.id), classData, {
        onSuccess: () => {
          toast.success('Class updated successfully!');
          handleCloseModal();
        },
        onError: (errors) => {
          toast.error(errors.error || 'Failed to update class');
        },
        onFinish: () => setLoading(false)
      });
    } else if (modalType === 'delete' && currentClass) {
      if (currentClass.students_count && currentClass.students_count > 0) {
        toast.error(`Cannot delete class with ${currentClass.students_count} students`);
        setLoading(false);
        return;
      }

      router.delete(route('admin.classes.destroy', currentClass.id), {
        onSuccess: () => {
          toast.success('Class deleted successfully!');
          handleCloseModal();
        },
        onError: (errors) => {
          toast.error(errors.error || 'Failed to delete class');
        },
        onFinish: () => setLoading(false)
      });
    }
  };

  const handleBulkCreate = (e: React.FormEvent) => {
    e.preventDefault();
    if (!bulkForm.semester_id || !bulkForm.program_id || bulkForm.sections.length === 0) {
      toast.error('Please fill in all required fields and select at least one section');
      return;
    }

    setLoading(true);

    const program = programs.find(p => p.id === parseInt(bulkForm.program_id));
    const classesData = bulkForm.sections.map(section => {
      // All sections get the same base class name like "BBIT 1.1"
      const className = `${program?.code} ${bulkForm.year_level}.1`;
      return {
        name: className,
        semester_id: parseInt(bulkForm.semester_id),
        program_id: parseInt(bulkForm.program_id),
        year_level: bulkForm.year_level,
        section: section,
        capacity: bulkForm.capacity,
        is_active: true
      };
    });

    router.post(route('admin.classes.bulk-store'), { classes: classesData }, {
      onSuccess: () => {
        toast.success(`${classesData.length} classes created successfully!`);
        handleCloseModal();
      },
      onError: (errors) => {
        toast.error(errors.error || 'Failed to create classes');
      },
      onFinish: () => setLoading(false)
    });
  };

  // Filter handlers
  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    const params = {
      search: searchQuery,
      semester_id: semesterFilter !== 'all' ? semesterFilter : undefined,
      program_id: programFilter !== 'all' ? programFilter : undefined,
      year_level: yearFilter !== 'all' ? yearFilter : undefined,
      per_page: itemsPerPage
    };
    
    router.get(route('admin.classes.index'), params, { preserveState: true });
  };

  const handlePerPageChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const newPerPage = parseInt(e.target.value, 10);
    setItemsPerPage(newPerPage);
    
    const params = {
      search: searchQuery,
      semester_id: semesterFilter !== 'all' ? semesterFilter : undefined,
      program_id: programFilter !== 'all' ? programFilter : undefined,
      year_level: yearFilter !== 'all' ? yearFilter : undefined,
      per_page: newPerPage
    };
    
    router.get(route('admin.classes.index'), params, { preserveState: true });
  };

  const handlePageChange = (url: string | null) => {
    if (url) {
      router.get(url, {}, { preserveState: true });
    }
  };

  const toggleSection = (section: string) => {
    setBulkForm(prev => ({
      ...prev,
      sections: prev.sections.includes(section)
        ? prev.sections.filter(s => s !== section)
        : [...prev.sections, section]
    }));
  };

  const getSemesterName = (semesterId: number) => {
    const semester = semesters.find(s => s.id === semesterId);
    return semester ? semester.name : 'N/A';
  };

  const getProgramName = (programId: number) => {
    const program = programs.find(p => p.id === programId);
    return program ? `${program.code} - ${program.name}` : 'N/A';
  };

  return (
    <AuthenticatedLayout>
      <Head title="Classes Management" />
      
      <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-indigo-50 py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          
          {/* Header */}
          <div className="mb-8">
            <div className="bg-white/80 backdrop-blur-sm rounded-3xl shadow-2xl border border-slate-200/50 p-8">
              <div className="flex items-center mb-6">
                <School className="w-8 h-8 text-blue-600 mr-3" />
                <div>
                  <h1 className="text-4xl font-bold bg-gradient-to-r from-blue-800 via-blue-600 to-indigo-800 bg-clip-text text-transparent">
                    Classes Management
                  </h1>
                  <p className="text-slate-600 text-lg mt-1">
                    Manage academic classes and program groups
                  </p>
                </div>
              </div>
              
              <p className="text-slate-600 text-base mb-4">
                Create and manage classes like BBIT 1.1, BBIT 1.2, ICS 2.1, etc.
              </p>
              
              <div className="flex items-center gap-4">
                <div className="text-sm text-slate-600">
                  Programs: <span className="font-semibold">{programs.length}</span>
                </div>
                <div className="text-sm text-slate-600">
                  Total Classes: <span className="font-semibold">{classes.total}</span>
                </div>
                <div className="text-sm text-slate-600">
                  Active Classes: <span className="font-semibold">{classes.data.filter(c => c.is_active).length}</span>
                </div>
              </div>
            </div>
          </div>

          {/* Action Buttons and Filters */}
          <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-lg border border-slate-200/50 p-6 mb-6">
            <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
              <div className="flex gap-3">
                {can.create_classes && (
                  <>
                    <button
                      onClick={() => handleOpenModal('create')}
                      className="inline-flex items-center px-4 py-2 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white font-semibold rounded-lg hover:from-emerald-600 hover:to-emerald-700 transition-all duration-200"
                    >
                      <Plus className="w-4 h-4 mr-2" />
                      Add Class
                    </button>
                    {/* <button
                      onClick={() => handleOpenModal('bulk-create')}
                      className="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white font-semibold rounded-lg hover:from-blue-600 hover:to-blue-700 transition-all duration-200"
                    >
                      <UserCheck className="w-4 h-4 mr-2" />
                      Bulk Create
                    </button> */}
                  </>
                )}
              </div>
              
              <div className="flex items-center gap-3">
                <label className="text-sm font-medium text-gray-700">Items per page:</label>
                <select
                  value={itemsPerPage}
                  onChange={handlePerPageChange}
                  className="border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                  <option value={10}>10</option>
                  <option value={15}>15</option>
                  <option value={25}>25</option>
                  <option value={50}>50</option>
                </select>
              </div>
            </div>

            {/* Filters */}
            <form onSubmit={handleSearch} className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
              <div>
                <input
                  type="text"
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  placeholder="Search classes..."
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
              </div>
              <div>
                <select
                  value={semesterFilter}
                  onChange={(e) => setSemesterFilter(e.target.value)}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                  <option value="all">All Semesters</option>
                  {semesters.map(semester => (
                    <option key={semester.id} value={semester.id}>
                      {semester.name}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <select
                  value={programFilter}
                  onChange={(e) => setProgramFilter(e.target.value)}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                  <option value="all">All Programs</option>
                  {programs.map(program => (
                    <option key={program.id} value={program.id}>
                      {program.code}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <select
                  value={yearFilter}
                  onChange={(e) => setYearFilter(e.target.value)}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                  <option value="all">All Years</option>
                  <option value="1">Year 1</option>
                  <option value="2">Year 2</option>
                  <option value="3">Year 3</option>
                  <option value="4">Year 4</option>
                </select>
              </div>
              <div>
                <button
                  type="submit"
                  className="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors flex items-center justify-center"
                >
                  <Search className="w-4 h-4 mr-2" />
                  Search
                </button>
              </div>
            </form>
          </div>

          {/* Classes Table */}
          <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-2xl border border-slate-200/50 overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-slate-50">
                  <tr>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">ID</th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Class Name</th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Program</th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Semester</th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Year/Section</th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Capacity</th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Students</th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Status</th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200">
                  {classes.data.map((classItem, index) => (
                    <tr key={classItem.id} className={`hover:bg-slate-50 transition-colors duration-150 ${
                      index % 2 === 0 ? "bg-white" : "bg-slate-50/50"
                    }`}>
                      <td className="px-6 py-4 text-sm font-medium text-slate-900">{classItem.id}</td>
                      <td className="px-6 py-4">
                        <div className="flex items-center">
                          <GraduationCap className="w-5 h-5 text-blue-500 mr-2" />
                          <div>
                            <div className="text-sm font-medium text-slate-900">{classItem.name}</div>
                            <div className="text-xs text-slate-500">Created {new Date(classItem.created_at).toLocaleDateString()}</div>
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4 text-sm text-slate-700">
                        {getProgramName(classItem.program_id)}
                      </td>
                      <td className="px-6 py-4 text-sm text-slate-700">
                        {getSemesterName(classItem.semester_id)}
                      </td>
                      <td className="px-6 py-4 text-sm text-slate-700">
                        {classItem.year_level && classItem.section 
                          ? `Year ${classItem.year_level}, Section ${classItem.section}`
                          : 'N/A'
                        }
                      </td>
                      <td className="px-6 py-4 text-sm text-slate-700">{classItem.capacity || 'N/A'}</td>
                      <td className="px-6 py-4 text-sm text-slate-700">
                        <span className="font-medium">{classItem.students_count || 0}</span>
                        {classItem.capacity && (
                          <span className="text-xs text-slate-500">
                            /{classItem.capacity}
                          </span>
                        )}
                      </td>
                      <td className="px-6 py-4">
                        <StatusBadge isActive={classItem.is_active} />
                      </td>
                      <td className="px-6 py-4 text-sm font-medium">
                        <div className="flex items-center space-x-2">
                          {can.update_classes && (
                            <button
                              onClick={() => handleOpenModal('edit', classItem)}
                              className="text-indigo-600 hover:text-indigo-900 transition-colors p-1 rounded hover:bg-indigo-50"
                              title="Edit class"
                            >
                              <Edit className="w-4 h-4" />
                            </button>
                          )}
                          {can.delete_classes && (
                            <button
                              onClick={() => handleOpenModal('delete', classItem)}
                              className="text-red-600 hover:text-red-900 transition-colors p-1 rounded hover:bg-red-50"
                              title="Delete class"
                              disabled={classItem.students_count && classItem.students_count > 0}
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

              {/* Empty State */}
              {classes.data.length === 0 && (
                <div className="text-center py-12">
                  <GraduationCap className="mx-auto h-12 w-12 text-gray-400" />
                  <h3 className="mt-2 text-sm font-medium text-gray-900">No classes found</h3>
                  <p className="mt-1 text-sm text-gray-500">
                    {searchQuery || programFilter !== 'all' || semesterFilter !== 'all' || yearFilter !== 'all'
                      ? 'Try adjusting your filters'
                      : 'Get started by creating your first class'
                    }
                  </p>
                  {can.create_classes && !searchQuery && programFilter === 'all' && (
                    <button
                      onClick={() => handleOpenModal('create')}
                      className="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                    >
                      <Plus className="w-4 h-4 mr-2" />
                      Create Class
                    </button>
                  )}
                </div>
              )}
            </div>

            {/* Pagination */}
            {classes.data.length > 0 && (
              <div className="bg-gray-50 px-6 py-4 flex items-center justify-between">
                <div className="text-sm text-gray-600">
                  Showing {classes.data.length} of {classes.total} classes
                </div>
                <div className="flex space-x-2">
                  {classes.links.map((link, index) => (
                    <button
                      key={index}
                      onClick={() => handlePageChange(link.url)}
                      className={`px-3 py-1 rounded text-sm ${
                        link.active
                          ? 'bg-blue-500 text-white'
                          : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                      }`}
                      dangerouslySetInnerHTML={{ __html: link.label }}
                      disabled={!link.url}
                    />
                  ))}
                </div>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div className={`p-6 rounded-t-2xl ${
              modalType === 'delete' 
                ? 'bg-gradient-to-r from-red-500 to-red-600' 
                : modalType === 'bulk-create'
                ? 'bg-gradient-to-r from-blue-500 to-blue-600'
                : 'bg-gradient-to-r from-emerald-500 to-emerald-600'
            }`}>
              <h3 className="text-xl font-semibold text-white">
                {modalType === 'create' && 'Add Class'}
                {modalType === 'edit' && 'Edit Class'}
                {modalType === 'delete' && 'Delete Class'}
                {modalType === 'bulk-create' && 'Bulk Create Classes'}
              </h3>
            </div>

            <div className="p-6">
              {modalType === 'delete' ? (
                <div>
                  <p className="text-gray-700 mb-4">
                    Are you sure you want to delete the class "{currentClass?.name}"?
                  </p>
                  {currentClass?.students_count && currentClass.students_count > 0 && (
                    <div className="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                      <div className="flex items-center">
                        <div className="w-5 h-5 text-red-500 mr-2">⚠</div>
                        <p className="text-sm text-red-700">
                          This class has {currentClass.students_count} students and cannot be deleted.
                        </p>
                      </div>
                    </div>
                  )}
                  <div className="flex items-center justify-end space-x-4">
                    <button
                      onClick={handleCloseModal}
                      className="px-6 py-3 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors font-medium"
                    >
                      Cancel
                    </button>
                    {(!currentClass?.students_count || currentClass.students_count === 0) && (
                      <button
                        onClick={handleSubmit}
                        disabled={loading}
                        className="px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors font-medium disabled:opacity-50"
                      >
                        {loading ? 'Deleting...' : 'Delete Class'}
                      </button>
                    )}
                  </div>
                </div>
              ) : modalType === 'bulk-create' ? (
                <form onSubmit={handleBulkCreate} className="space-y-4">
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Semester *
                      </label>
                      <select
                        value={bulkForm.semester_id}
                        onChange={(e) => setBulkForm(prev => ({ ...prev, semester_id: e.target.value }))}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
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
                        Program *
                      </label>
                      <select
                        value={bulkForm.program_id}
                        onChange={(e) => setBulkForm(prev => ({ ...prev, program_id: e.target.value }))}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        required
                      >
                        <option value="">Select Program</option>
                        {programs.map(program => (
                          <option key={program.id} value={program.id}>
                            {program.code} - {program.name}
                          </option>
                        ))}
                      </select>
                    </div>
                  </div>

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Year Level
                      </label>
                      <select
                        value={bulkForm.year_level}
                        onChange={(e) => setBulkForm(prev => ({ ...prev, year_level: parseInt(e.target.value) }))}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                      >
                        <option value={1}>Year 1</option>
                        <option value={2}>Year 2</option>
                        <option value={3}>Year 3</option>
                        <option value={4}>Year 4</option>
                      </select>
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Capacity per Class
                      </label>
                      <input
                        type="number"
                        value={bulkForm.capacity}
                        onChange={(e) => setBulkForm(prev => ({ ...prev, capacity: parseInt(e.target.value) }))}
                        min="1"
                        max="200"
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                      />
                    </div>
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Sections to Create *
                    </label>
                    <div className="grid grid-cols-5 gap-2">
                      {Array.from({ length: 10 }, (_, i) => String.fromCharCode(65 + i)).map(letter => (
                        <label key={letter} className="flex items-center p-2 border rounded-lg cursor-pointer hover:bg-gray-50">
                          <input
                            type="checkbox"
                            checked={bulkForm.sections.includes(letter)}
                            onChange={() => toggleSection(letter)}
                            className="mr-2 rounded focus:ring-blue-500"
                          />
                          <span className="text-sm font-medium">Section {letter}</span>
                        </label>
                      ))}
                    </div>
                  </div>

                  {/* Preview */}
                  {bulkForm.program_id && bulkForm.semester_id && bulkForm.sections.length > 0 && (
                    <div className="bg-blue-50 p-4 rounded-lg border border-blue-200">
                      <h4 className="font-medium text-blue-900 mb-2">Preview Classes to be Created:</h4>
                      <div className="space-y-1">
                        {bulkForm.sections.map(section => {
                          const program = programs.find(p => p.id === parseInt(bulkForm.program_id));
                          // All sections get the same base class name like "BBIT 1.1"
                          const className = `${program?.code} ${bulkForm.year_level}.1`;
                          return (
                            <div key={section} className="text-sm text-blue-800 font-medium">
                              {className} Section {section} (Capacity: {bulkForm.capacity})
                            </div>
                          );
                        })}
                      </div>
                    </div>
                  )}

                  <div className="flex items-center justify-end space-x-4 pt-4 border-t">
                    <button
                      type="button"
                      onClick={handleCloseModal}
                      className="px-6 py-3 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors font-medium"
                    >
                      Cancel
                    </button>
                    <button
                      type="submit"
                      disabled={loading || !bulkForm.semester_id || !bulkForm.program_id || bulkForm.sections.length === 0}
                      className="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium disabled:opacity-50"
                    >
                      {loading ? 'Creating Classes...' : `Create ${bulkForm.sections.length} Classes`}
                    </button>
                  </div>
                </form>
              ) : (
                <form onSubmit={handleSubmit} className="space-y-4">
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Semester *
                      </label>
                      <select
                        value={classForm.semester_id}
                        onChange={(e) => setClassForm(prev => ({ ...prev, semester_id: e.target.value }))}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
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
                        Program *
                      </label>
                      <select
                        value={classForm.program_id}
                        onChange={(e) => setClassForm(prev => ({ ...prev, program_id: e.target.value }))}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        required
                      >
                        <option value="">Select Program</option>
                        {programs.map(program => (
                          <option key={program.id} value={program.id}>
                            {program.code} - {program.name}
                          </option>
                        ))}
                      </select>
                    </div>
                  </div>

                  <div className="flex items-center mb-4">
                    <input
                      type="checkbox"
                      id="auto_generate_name"
                      checked={classForm.auto_generate_name}
                      onChange={(e) => setClassForm(prev => ({ ...prev, auto_generate_name: e.target.checked }))}
                      className="mr-2 rounded focus:ring-emerald-500"
                    />
                    <label htmlFor="auto_generate_name" className="text-sm font-medium text-gray-700">
                      Auto-generate class name (e.g., BBIT 1.1)
                    </label>
                  </div>

                  {!classForm.auto_generate_name ? (
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Class Name *
                      </label>
                      <input
                        type="text"
                        value={classForm.name}
                        onChange={(e) => setClassForm(prev => ({ ...prev, name: e.target.value }))}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        placeholder="Enter class name"
                        required
                      />
                    </div>
                  ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                          Year Level
                        </label>
                        <select
                          value={classForm.year_level}
                          onChange={(e) => setClassForm(prev => ({ ...prev, year_level: parseInt(e.target.value) }))}
                          className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        >
                          <option value={1}>Year 1</option>
                          <option value={2}>Year 2</option>
                          <option value={3}>Year 3</option>
                          <option value={4}>Year 4</option>
                        </select>
                      </div>

                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                          Section
                        </label>
                        <select
                          value={classForm.section}
                          onChange={(e) => setClassForm(prev => ({ ...prev, section: e.target.value }))}
                          className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        >
                          {Array.from({ length: 10 }, (_, i) => String.fromCharCode(65 + i)).map(letter => (
                            <option key={letter} value={letter}>Section {letter}</option>
                          ))}
                        </select>
                      </div>
                    </div>
                  )}

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Capacity
                    </label>
                    <input
                      type="number"
                      value={classForm.capacity}
                      onChange={(e) => setClassForm(prev => ({ ...prev, capacity: parseInt(e.target.value) }))}
                      min="1"
                      max="200"
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                    />
                  </div>

                  {/* Preview */}
                  {classForm.auto_generate_name && classForm.program_id && (
                    <div className="bg-emerald-50 p-3 rounded-lg border border-emerald-200">
                      <label className="block text-sm font-medium text-gray-700 mb-1">Class Name Preview:</label>
                      <div className="text-lg font-semibold text-emerald-700">
                        {generateClassName()} Section {classForm.section}
                      </div>
                    </div>
                  )}

                  <div className="flex items-center justify-end space-x-4 pt-4 border-t">
                    <button
                      type="button"
                      onClick={handleCloseModal}
                      className="px-6 py-3 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors font-medium"
                    >
                      Cancel
                    </button>
                    <button
                      type="submit"
                      disabled={loading}
                      className="px-6 py-3 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors font-medium disabled:opacity-50"
                    >
                      {loading ? 'Processing...' : modalType === 'create' ? 'Create Class' : 'Update Class'}
                    </button>
                  </div>
                </form>
              )}
            </div>
          </div>
        </div>
      )}
    </AuthenticatedLayout>
  );
};

export default ClassesIndex;