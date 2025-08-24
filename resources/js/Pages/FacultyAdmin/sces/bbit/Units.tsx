import React, { useState, useMemo, useEffect } from 'react';
import { PageProps } from '@inertiajs/inertia-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import RoleAwareComponent from '@/Components/RoleAwareComponent';
import axios from 'axios';

// Type Definitions
type Unit = {
  id: number;
  code: string;
  name: string;
  credit_hours: number;
  semester_id?: number;
  program_id?: number;
  class_id?: number;
  school_id?: number;
  is_active: number;
  created_at: string;
  updated_at: string;
};

type Semester = {
  id: number;
  name: string;
  is_active: boolean;
};

type UnitsProps = PageProps & {
  units: Unit[];
  schoolCode: string;
  programCode: string;
  userPermissions: string[];
  userRoles: string[];
  error?: string;
};

type ModalType = 'view' | 'create' | 'edit' | 'delete' | null;

// Modal Component
const Modal: React.FC<{ 
  children: React.ReactNode; 
  title: string; 
  size?: 'sm' | 'md' | 'lg' | 'xl';
  onClose: () => void;
}> = ({ children, title, size = 'md', onClose }) => {
  const sizeClasses = {
    sm: 'max-w-md',
    md: 'max-w-lg',
    lg: 'max-w-2xl',
    xl: 'max-w-4xl',
  };

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto">
      <div className="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div className="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onClick={onClose}></div>
        <span className="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
        <div className={`inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle ${sizeClasses[size]} sm:w-full`}>
          <div className="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-lg font-medium text-gray-900">{title}</h3>
              <button
                onClick={onClose}
                className="text-gray-400 hover:text-gray-600 transition-colors"
              >
                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            {children}
          </div>
        </div>
      </div>
    </div>
  );
};

// View Modal Component
const ViewModal: React.FC<{
  unit: Unit;
  onClose: () => void;
  onEdit: (unit: Unit) => void;
  semesters: Semester[];
}> = ({ unit, onClose, onEdit, semesters }) => {
  const getSemesterName = (semesterId: number | undefined): string => {
    if (!semesterId) return 'Not assigned';
    const semester = semesters.find(s => s.id === semesterId);
    return semester ? semester.name : `Semester ${semesterId}`;
  };

  return (
    <Modal title="Unit Details" size="lg" onClose={onClose}>
      <div className="space-y-6">
        <div className="grid grid-cols-2 gap-6">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">Unit Code</label>
            <div className="bg-gray-50 rounded-lg p-3 font-mono text-sm">
              {unit.code}
            </div>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">Credit Hours</label>
            <div className="bg-gray-50 rounded-lg p-3 text-sm">
              {unit.credit_hours} hours
            </div>
          </div>
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">Unit Name</label>
          <div className="bg-gray-50 rounded-lg p-3 text-sm">
            {unit.name}
          </div>
        </div>
        <div className="grid grid-cols-2 gap-6">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">Semester</label>
            <div className="bg-gray-50 rounded-lg p-3 text-sm">
              {getSemesterName(unit.semester_id)}
            </div>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">Status</label>
            <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ${
              unit.is_active === 1 
                ? 'bg-green-100 text-green-800' 
                : 'bg-red-100 text-red-800'
            }`}>
              {unit.is_active === 1 ? 'Active' : 'Inactive'}
            </span>
          </div>
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">Created Date</label>
          <div className="bg-gray-50 rounded-lg p-3 text-sm">
            {new Date(unit.created_at).toLocaleDateString('en-US', {
              year: 'numeric',
              month: 'long',
              day: 'numeric'
            })}
          </div>
        </div>
        <div className="flex justify-end space-x-3 pt-4 border-t">
          <button
            onClick={onClose}
            className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors"
          >
            Close
          </button>
          <RoleAwareComponent requiredRoles={['Faculty Admin - SCES']}>
            <button
              onClick={() => onEdit(unit)}
              className="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 transition-colors"
            >
              Edit Unit
            </button>
          </RoleAwareComponent>
        </div>
      </div>
    </Modal>
  );
};

// Create/Edit Modal Component with Optional Semester
const CreateEditModal: React.FC<{
  mode: 'create' | 'edit';
  data: any;
  setData: (key: string, value: any) => void;
  errors: any;
  processing: boolean;
  onSubmit: (e: React.FormEvent) => void;
  onClose: () => void;
  semesters: Semester[];
}> = ({ mode, data, setData, errors, processing, onSubmit, onClose, semesters }) => {
  return (
    <Modal title={mode === 'create' ? 'Add New Unit' : 'Edit Unit'} size="lg" onClose={onClose}>
      <form onSubmit={onSubmit} className="space-y-6">
        <div className="grid grid-cols-2 gap-6">
          <div>
            <label htmlFor="code" className="block text-sm font-medium text-gray-700 mb-2">
              Unit Code <span className="text-red-500">*</span>
            </label>
            <input
              type="text"
              id="code"
              value={data.code}
              onChange={(e) => setData('code', e.target.value)}
              className={`w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 ${
                errors.code ? 'border-red-300' : 'border-gray-300'
              }`}
              placeholder="e.g., BIT101"
              required
              autoComplete="off"
            />
            {errors.code && <p className="mt-1 text-sm text-red-600">{errors.code}</p>}
          </div>
          <div>
            <label htmlFor="credit_hours" className="block text-sm font-medium text-gray-700 mb-2">
              Credit Hours <span className="text-red-500">*</span>
            </label>
            <select
              id="credit_hours"
              value={data.credit_hours}
              onChange={(e) => setData('credit_hours', Number(e.target.value))}
              className={`w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 ${
                errors.credit_hours ? 'border-red-300' : 'border-gray-300'
              }`}
              required
            >
              <option value={1}>1 Hour</option>
              <option value={2}>2 Hours</option>
              <option value={3}>3 Hours</option>
              <option value={4}>4 Hours</option>
              <option value={5}>5 Hours</option>
              <option value={6}>6 Hours</option>
            </select>
            {errors.credit_hours && <p className="mt-1 text-sm text-red-600">{errors.credit_hours}</p>}
          </div>
        </div>
        <div>
          <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-2">
            Unit Name <span className="text-red-500">*</span>
          </label>
          <input
            type="text"
            id="name"
            value={data.name}
            onChange={(e) => setData('name', e.target.value)}
            className={`w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 ${
              errors.name ? 'border-red-300' : 'border-gray-300'
            }`}
            placeholder="e.g., Introduction to Information Technology"
            required
            autoComplete="off"
          />
          {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
        </div>
        <div>
          <label htmlFor="is_active" className="block text-sm font-medium text-gray-700 mb-2">
            Status
          </label>
          <select
            id="is_active"
            value={data.is_active}
            onChange={(e) => setData('is_active', Number(e.target.value))}
            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          >
            <option value={1}>Active</option>
            <option value={0}>Inactive</option>
          </select>
        </div>
        <div>
          <label htmlFor="semester_id" className="block text-sm font-medium text-gray-700 mb-2">
            Assign to Semester <span className="text-gray-500 text-xs">(Optional)</span>
          </label>
          <div className="space-y-2">
            {semesters.length === 0 ? (
              <div className="text-gray-500 text-sm">Loading semesters...</div>
            ) : (
              <select
                id="semester_id"
                value={data.semester_id || ''}
                onChange={(e) => setData('semester_id', e.target.value ? Number(e.target.value) : null)}
                className={`w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 ${
                  errors.semester_id ? 'border-red-300' : 'border-gray-300'
                }`}
              >
                <option value="">No semester assigned (can be assigned later)</option>
                {semesters.map((semester) => (
                  <option key={semester.id} value={semester.id}>
                    {semester.name} {semester.is_active ? '(Current)' : ''}
                  </option>
                ))}
              </select>
            )}
            <p className="text-xs text-gray-500">
              You can create the unit now and assign it to a semester later through the semester management system.
            </p>
          </div>
          {errors.semester_id && <p className="mt-1 text-sm text-red-600">{errors.semester_id}</p>}
        </div>
        <div className="flex justify-end space-x-3 pt-4 border-t">
          <button
            type="button"
            onClick={onClose}
            className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors"
            disabled={processing}
          >
            Cancel
          </button>
          <button
            type="submit"
            className="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            disabled={processing}
          >
            {processing ? 'Saving...' : (mode === 'create' ? 'Create Unit' : 'Update Unit')}
          </button>
        </div>
      </form>
    </Modal>
  );
};
// Delete Modal Component
const DeleteModal: React.FC<{
  unit: Unit;
  processing: boolean;
  onDelete: () => void;
  onClose: () => void;
}> = ({ unit, processing, onDelete, onClose }) => (
  <Modal title="Delete Unit" size="md" onClose={onClose}>
    <div className="space-y-4">
      <div className="bg-red-50 border border-red-200 rounded-lg p-4">
        <div className="flex">
          <svg className="h-5 w-5 text-red-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.996-.833-2.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
          </svg>
          <div className="ml-3">
            <h3 className="text-sm font-medium text-red-800">Delete Confirmation</h3>
            <p className="mt-1 text-sm text-red-700">
              This action cannot be undone. This will permanently delete the unit.
            </p>
          </div>
        </div>
      </div>
      <div className="bg-gray-50 rounded-lg p-4">
        <h4 className="font-medium text-gray-900 mb-2">Unit to be deleted:</h4>
        <div className="text-sm text-gray-700">
          <p><span className="font-medium">Code:</span> {unit.code}</p>
          <p><span className="font-medium">Name:</span> {unit.name}</p>
          <p><span className="font-medium">Credit Hours:</span> {unit.credit_hours}</p>
        </div>
      </div>
      <div className="flex justify-end space-x-3 pt-4 border-t">
        <button
          onClick={onClose}
          className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors"
          disabled={processing}
        >
          Cancel
        </button>
        <button
          onClick={onDelete}
          className="px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          disabled={processing}
        >
          {processing ? 'Deleting...' : 'Delete Unit'}
        </button>
      </div>
    </div>
  </Modal>
);

// Main Units Component
const Units: React.FC<UnitsProps> = ({
  units,
  schoolCode,
  programCode,
  userPermissions,
  userRoles,
  error,
}) => {
  // State Management
  const [searchTerm, setSearchTerm] = useState('');
  const [sortBy, setSortBy] = useState<'name' | 'code' | 'credit_hours'>('name');
  const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('asc');
  const [currentPage, setCurrentPage] = useState(1);
  const [itemsPerPage, setItemsPerPage] = useState(10);
  const [modalType, setModalType] = useState<ModalType>(null);
  const [selectedUnit, setSelectedUnit] = useState<Unit | null>(null);
  const [semesters, setSemesters] = useState<Semester[]>([]);
  const [loadingSemesters, setLoadingSemesters] = useState(false);

  // Form handling for Create/Edit
  const { data, setData, post, put, delete: destroy, processing, errors, reset } = useForm({
    code: '',
    name: '',
    credit_hours: 4,
    is_active: 1,
    semester_id: '',
  });

  // Fetch semesters on component mount
  useEffect(() => {
    setLoadingSemesters(true);
    axios.get('/facultyadmin/sces/bbit/semesters')
      .then(res => {
        if (res.data && res.data.success && Array.isArray(res.data.semesters)) {
          setSemesters(res.data.semesters);
        } else if (res.data && Array.isArray(res.data.semesters)) {
          // Fallback for different response structure
          setSemesters(res.data.semesters);
        } else {
          console.error('Failed to load semesters:', res.data);
        }
      })
      .catch((error) => {
        console.error('Error fetching semesters:', error);
      })
      .finally(() => setLoadingSemesters(false));
  }, []);

  // Helper function to get semester name by ID
  const getSemesterName = (semesterId: number | undefined): string => {
    if (!semesterId) return 'Not assigned';
    const semester = semesters.find(s => s.id === semesterId);
    return semester ? semester.name : `Semester ${semesterId}`;
  };

  // Filter and sort units
  const filteredAndSortedUnits = useMemo(() => {
    return units
      .filter(unit => 
        unit.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        unit.code.toLowerCase().includes(searchTerm.toLowerCase())
      )
      .sort((a, b) => {
        const aValue = a[sortBy];
        const bValue = b[sortBy];
        if (typeof aValue === 'string' && typeof bValue === 'string') {
          return sortDirection === 'asc' 
            ? aValue.localeCompare(bValue)
            : bValue.localeCompare(aValue);
        }
        if (typeof aValue === 'number' && typeof bValue === 'number') {
          return sortDirection === 'asc' 
            ? aValue - bValue
            : bValue - aValue;
        }
        return 0;
      });
  }, [units, searchTerm, sortBy, sortDirection]);

  // Pagination calculations
  const totalItems = filteredAndSortedUnits.length;
  const totalPages = Math.ceil(totalItems / itemsPerPage);
  const startIndex = (currentPage - 1) * itemsPerPage;
  const endIndex = startIndex + itemsPerPage;
  const currentUnits = filteredAndSortedUnits.slice(startIndex, endIndex);

  // Reset page when search or filters change
  useEffect(() => {
    setCurrentPage(1);
  }, [searchTerm, sortBy, sortDirection]);

  // Event Handlers
  const handleSort = (column: 'name' | 'code' | 'credit_hours') => {
    if (sortBy === column) {
      setSortDirection(sortDirection === 'asc' ? 'desc' : 'asc');
    } else {
      setSortBy(column);
      setSortDirection('asc');
    }
  };

  const getSortIcon = (column: 'name' | 'code' | 'credit_hours') => {
    if (sortBy !== column) return '↕';
    return sortDirection === 'asc' ? '↑' : '↓';
  };

  const handlePageChange = (page: number) => {
    setCurrentPage(page);
  };

  const handleItemsPerPageChange = (items: number) => {
    setItemsPerPage(items);
    setCurrentPage(1);
  };

  // Modal handlers
  const openModal = (type: ModalType, unit?: Unit) => {
    setModalType(type);
    setSelectedUnit(unit || null);
    if (type === 'create') {
      reset();
    } else if (type === 'edit' && unit) {
      setData({
        code: unit.code,
        name: unit.name,
        credit_hours: unit.credit_hours,
        is_active: unit.is_active,
        semester_id: unit.semester_id || '',
      });
    }
  };

  const closeModal = () => {
    setModalType(null);
    setSelectedUnit(null);
    reset();
  };

  // CRUD operations
  const handleCreate = (e: React.FormEvent) => {
    e.preventDefault();
    post('/facultyadmin/sces/bbit/units', {
      onSuccess: () => {
        closeModal();
      },
    });
  };

  const handleUpdate = (e: React.FormEvent) => {
    e.preventDefault();
    if (selectedUnit) {
      put(`/facultyadmin/sces/bbit/units/${selectedUnit.id}`, {
        onSuccess: () => {
          closeModal();
        },
      });
    }
  };

  const handleDelete = () => {
    if (selectedUnit) {
      destroy(`/facultyadmin/sces/bbit/units/${selectedUnit.id}`, {
        onSuccess: () => {
          closeModal();
        },
      });
    }
  };

  // Pagination component
  const Pagination = () => {
    const pageNumbers = [];
    const maxVisiblePages = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
    let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
    if (endPage - startPage + 1 < maxVisiblePages) {
      startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }
    for (let i = startPage; i <= endPage; i++) {
      pageNumbers.push(i);
    }

    return (
      <div className="flex items-center justify-between px-6 py-4 bg-white border-t border-gray-200">
        <div className="flex items-center space-x-2">
          <span className="text-sm text-gray-700">Show</span>
          <select
            value={itemsPerPage}
            onChange={(e) => handleItemsPerPageChange(Number(e.target.value))}
            className="border border-gray-300 rounded-md px-3 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          >
            <option value={10}>10</option>
            <option value={25}>25</option>
            <option value={50}>50</option>
            <option value={100}>100</option>
          </select>
          <span className="text-sm text-gray-700">entries</span>
        </div>
        <div className="flex items-center space-x-2">
          <span className="text-sm text-gray-700">
            Showing {startIndex + 1} to {Math.min(endIndex, totalItems)} of {totalItems} entries
          </span>
        </div>
        <div className="flex items-center space-x-1">
          <button
            onClick={() => handlePageChange(currentPage - 1)}
            disabled={currentPage === 1}
            className={`px-3 py-2 text-sm font-medium rounded-md transition-colors ${
              currentPage === 1
                ? 'text-gray-400 cursor-not-allowed'
                : 'text-gray-700 hover:bg-gray-100'
            }`}
          >
            Previous
          </button>
          {startPage > 1 && (
            <>
              <button
                onClick={() => handlePageChange(1)}
                className="px-3 py-2 text-sm font-medium text-gray-700 rounded-md hover:bg-gray-100 transition-colors"
              >
                1
              </button>
              {startPage > 2 && (
                <span className="px-2 py-2 text-gray-400">...</span>
              )}
            </>
          )}
          {pageNumbers.map((page) => (
            <button
              key={page}
              onClick={() => handlePageChange(page)}
              className={`px-3 py-2 text-sm font-medium rounded-md transition-colors ${
                currentPage === page
                  ? 'bg-blue-600 text-white'
                  : 'text-gray-700 hover:bg-gray-100'
              }`}
            >
              {page}
            </button>
          ))}
          {endPage < totalPages && (
            <>
              {endPage < totalPages - 1 && (
                <span className="px-2 py-2 text-gray-400">...</span>
              )}
              <button
                onClick={() => handlePageChange(totalPages)}
                className="px-3 py-2 text-sm font-medium text-gray-700 rounded-md hover:bg-gray-100 transition-colors"
              >
                {totalPages}
              </button>
            </>
          )}
          <button
            onClick={() => handlePageChange(currentPage + 1)}
            disabled={currentPage === totalPages}
            className={`px-3 py-2 text-sm font-medium rounded-md transition-colors ${
              currentPage === totalPages
                ? 'text-gray-400 cursor-not-allowed'
                : 'text-gray-700 hover:bg-gray-100'
            }`}
          >
            Next
          </button>
        </div>
      </div>
    );
  };

  return (
    <AuthenticatedLayout>
      <Head title={`${programCode} Units - ${schoolCode}`} />
      <div className="py-8">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          {/* Header Section */}
          <div className="mb-8">
            <div className="bg-gradient-to-r from-blue-600 via-blue-700 to-blue-800 rounded-xl shadow-xl p-8 text-white relative overflow-hidden">
              <div className="absolute inset-0 bg-black/10"></div>
              <div className="relative flex items-center justify-between">
                <div>
                  <div className="flex items-center space-x-2 mb-2">
                    <div className="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                      <span className="text-lg">📚</span>
                    </div>
                    <h1 className="text-3xl font-bold">{programCode} Units Management</h1>
                  </div>
                  <p className="text-blue-100 text-lg">School of Computing and Engineering Sciences ({schoolCode})</p>
                </div>
                <div className="text-right">
                  <div className="text-3xl font-bold bg-white/20 rounded-lg p-4 min-w-[80px] text-center">
                    {units.length}
                  </div>
                  <div className="text-sm text-blue-200 mt-1">Total Units</div>
                </div>
              </div>
            </div>
          </div>

          {/* Error Alert */}
          {error && (
            <div className="mb-6 bg-red-50 border-l-4 border-red-400 p-4 rounded-r-lg shadow-sm">
              <div className="flex">
                <div className="flex-shrink-0">
                  <svg className="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                  </svg>
                </div>
                <div className="ml-3">
                  <p className="text-sm font-medium text-red-800">{error}</p>
                </div>
              </div>
            </div>
          )}

          {/* Controls Section */}
          <div className="bg-white rounded-xl shadow-lg mb-6 border border-gray-100">
            <div className="p-6 border-b border-gray-200">
              <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                {/* Search Bar */}
                <div className="flex-1 max-w-md">
                  <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <svg className="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                      </svg>
                    </div>
                    <input
                      type="text"
                      placeholder="Search units by name or code..."
                      value={searchTerm}
                      onChange={(e) => setSearchTerm(e.target.value)}
                      className="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                    />
                    {searchTerm && (
                      <button
                        onClick={() => setSearchTerm('')}
                        className="absolute inset-y-0 right-0 pr-3 flex items-center"
                      >
                        <svg className="h-4 w-4 text-gray-400 hover:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                      </button>
                    )}
                  </div>
                </div>
                {/* Action Buttons */}
                <div className="flex flex-wrap gap-3">
                  <RoleAwareComponent requiredRoles={['Faculty Admin - SCES']}>
                    <button 
                      onClick={() => openModal('create')}
                      className="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white px-6 py-3 rounded-lg text-sm font-medium transition-all duration-200 flex items-center shadow-lg hover:shadow-xl transform hover:-translate-y-0.5"
                    >
                      <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                      </svg>
                      Add New Unit
                    </button>
                  </RoleAwareComponent>
                  <button className="bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white px-6 py-3 rounded-lg text-sm font-medium transition-all duration-200 flex items-center shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
                    <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Export Data
                  </button>
                  <button className="bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 text-white px-6 py-3 rounded-lg text-sm font-medium transition-all duration-200 flex items-center shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
                    <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    Bulk Actions
                  </button>
                </div>
              </div>
            </div>
            {/* Enhanced Statistics Row */}
            <div className="p-6 bg-gradient-to-r from-gray-50 to-blue-50">
              <div className="grid grid-cols-2 md:grid-cols-4 gap-6">
                <div className="text-center bg-white rounded-lg p-4 shadow-sm border border-blue-100">
                  <div className="text-3xl font-bold text-blue-600 mb-1">{units.length}</div>
                  <div className="text-sm text-gray-600 font-medium">Total Units</div>
                </div>
                <div className="text-center bg-white rounded-lg p-4 shadow-sm border border-green-100">
                  <div className="text-3xl font-bold text-green-600 mb-1">
                    {units.filter(u => u.is_active === 1).length}
                  </div>
                  <div className="text-sm text-gray-600 font-medium">Active Units</div>
                </div>
                <div className="text-center bg-white rounded-lg p-4 shadow-sm border border-orange-100">
                  <div className="text-3xl font-bold text-orange-600 mb-1">
                    {units.reduce((sum, unit) => sum + unit.credit_hours, 0)}
                  </div>
                  <div className="text-sm text-gray-600 font-medium">Total Credit Hours</div>
                </div>
                <div className="text-center bg-white rounded-lg p-4 shadow-sm border border-purple-100">
                  <div className="text-3xl font-bold text-purple-600 mb-1">
                    {filteredAndSortedUnits.length}
                  </div>
                  <div className="text-sm text-gray-600 font-medium">Filtered Results</div>
                </div>
              </div>
            </div>
          </div>

          {/* Units Table */}
          <div className="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
            <div className="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-blue-50">
              <div className="flex items-center justify-between">
                <h3 className="text-lg font-semibold text-gray-900 flex items-center">
                  <svg className="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                  Units List
                </h3>
                <div className="text-sm text-gray-600">
                  Page {currentPage} of {totalPages}
                </div>
              </div>
            </div>
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th 
                      className="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none group transition-colors"
                      onClick={() => handleSort('code')}
                    >
                      <div className="flex items-center space-x-1">
                        <span>Unit Code</span>
                        <span className="text-gray-400 group-hover:text-gray-600">{getSortIcon('code')}</span>
                      </div>
                    </th>
                    <th 
                      className="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none group transition-colors"
                      onClick={() => handleSort('name')}
                    >
                      <div className="flex items-center space-x-1">
                        <span>Unit Name</span>
                        <span className="text-gray-400 group-hover:text-gray-600">{getSortIcon('name')}</span>
                      </div>
                    </th>
                    <th 
                      className="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none group transition-colors"
                      onClick={() => handleSort('credit_hours')}
                    >
                      <div className="flex items-center space-x-1">
                        <span>Credit Hours</span>
                        <span className="text-gray-400 group-hover:text-gray-600">{getSortIcon('credit_hours')}</span>
                      </div>
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Semester
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Status
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Created Date
                    </th>
                    <th className="px-6 py-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {currentUnits.length > 0 ? (
                    currentUnits.map((unit, index) => (
                      <tr key={unit.id} className={`hover:bg-blue-50 transition-colors ${index % 2 === 0 ? 'bg-white' : 'bg-gray-50'}`}>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <div className="text-sm font-medium text-gray-900 font-mono bg-blue-100 px-3 py-1 rounded-md inline-block">
                            {unit.code}
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <div className="text-sm font-medium text-gray-900 leading-relaxed">
                            {unit.name}
                          </div>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <div className="text-sm text-gray-900 text-center">
                            <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200">
                              {unit.credit_hours} hours
                            </span>
                          </div>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <div className="text-sm text-gray-900">
                            {unit.semester_id ? (
                              <span className="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-800">
                                {getSemesterName(unit.semester_id)}
                              </span>
                            ) : (
                              <span className="text-gray-400 text-xs">Not assigned</span>
                            )}
                          </div>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-medium border ${
                            unit.is_active === 1 
                              ? 'bg-green-100 text-green-800 border-green-200' 
                              : 'bg-red-100 text-red-800 border-red-200'
                          }`}>
                            <span className={`w-2 h-2 rounded-full mr-2 ${
                              unit.is_active === 1 ? 'bg-green-500' : 'bg-red-500'
                            }`}></span>
                            {unit.is_active === 1 ? 'Active' : 'Inactive'}
                          </span>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                          {new Date(unit.created_at).toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: 'short',
                            day: 'numeric'
                          })}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                          <div className="flex justify-end space-x-2">
                            <button 
                              onClick={() => openModal('view', unit)}
                              className="bg-blue-50 text-blue-700 hover:bg-blue-100 hover:text-blue-900 text-sm font-medium px-3 py-1 rounded-lg shadow-sm transition-all flex items-center"
                            >
                              <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                              </svg>
                              View
                            </button>
                            <RoleAwareComponent requiredRoles={['Faculty Admin - SCES']}>
                              <button 
                                onClick={() => openModal('edit', unit)}
                                className="bg-yellow-50 text-yellow-700 hover:bg-yellow-100 hover:text-yellow-900 text-sm font-medium px-3 py-1 rounded-lg shadow-sm transition-all flex items-center"
                              >
                                <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.232 5.232l3.536 3.536M9 13l6.536-6.536a2 2 0 112.828 2.828L11.828 15.828a2 2 0 01-2.828 0L7 13l2-2z" />
                                </svg>
                                Edit
                              </button>
                              <button
                                onClick={() => openModal('delete', unit)}
                                className="bg-red-50 text-red-700 hover:bg-red-100 hover:text-red-900 text-sm font-medium px-3 py-1 rounded-lg shadow-sm transition-all flex items-center"
                              >
                                <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                Delete
                              </button>
                            </RoleAwareComponent>
                          </div>
                        </td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan={7} className="px-6 py-8 text-center text-gray-500 text-sm">
                        No units found.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
            {/* Pagination */}
            <Pagination />
          </div>
        </div>
      </div>

      {/* Modals */}
      {modalType === 'view' && selectedUnit && (
        <ViewModal
          unit={selectedUnit}
          onClose={closeModal}
          onEdit={(unit) => openModal('edit', unit)}
          semesters={semesters}
        />
      )}
      {(modalType === 'create' || modalType === 'edit') && (
        <CreateEditModal
          mode={modalType === 'create' ? 'create' : 'edit'}
          data={data}
          setData={setData}
          errors={errors}
          processing={processing}
          onSubmit={modalType === 'create' ? handleCreate : handleUpdate}
          onClose={closeModal}
          semesters={semesters}
        />
      )}
      {modalType === 'delete' && selectedUnit && (
        <DeleteModal
          unit={selectedUnit}
          processing={processing}
          onDelete={handleDelete}
          onClose={closeModal}
        />
      )}
    </AuthenticatedLayout>
  );
};

export default Units;