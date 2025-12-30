import React, { useState, FormEvent } from 'react';
import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { toast } from 'react-hot-toast';

interface Unit {
  id: number;
  code: string;
  name: string;
  credit_hours?: number;
}

interface Elective {
  id: number;
  unit_id: number;
  category: 'language' | 'other';
  year_level: number;
  max_students: number | null;
  min_students: number | null;
  is_active: boolean;
  description: string | null;
  prerequisites: string | null;
  unit: Unit;
  current_enrollment?: number;
  available_spots?: number | null;
  created_at: string;
  updated_at: string;
}

interface PaginatedElectives {
  data: Elective[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number;
  to: number;
}

interface Props {
  electives: PaginatedElectives;
  units: Unit[];
  filters: {
    search?: string;
    category?: string;
    year_level?: number | string;
    status?: string;
  };
}

interface FormData {
  unit_id: string | number;
  category: 'language' | 'other';
  year_level: number;
  max_students: string | number;
  min_students: string | number;
  is_active: boolean;
  description: string;
  prerequisites: string;
}

export default function Index({ electives, units, filters }: Props) {
  const [searchTerm, setSearchTerm] = useState(filters.search || '');
  const [selectedCategory, setSelectedCategory] = useState(filters.category || 'all');
  const [selectedYearLevel, setSelectedYearLevel] = useState(filters.year_level?.toString() || 'all');
  const [selectedStatus, setSelectedStatus] = useState(filters.status || 'all');
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [isViewModalOpen, setIsViewModalOpen] = useState(false);
  const [selectedElective, setSelectedElective] = useState<Elective | null>(null);
  const [loading, setLoading] = useState(false);

  // Form state
  const [formData, setFormData] = useState<FormData>({
    unit_id: '',
    category: 'language',
    year_level: 1,
    max_students: '',
    min_students: '',
    is_active: true,
    description: '',
    prerequisites: '',
  });

  const [formErrors, setFormErrors] = useState<Record<string, string>>({});

  // Filter available units - exclude those already used as electives
  const availableUnits = units.filter(unit => 
    !electives.data.some(elective => elective.unit_id === unit.id && (!selectedElective || selectedElective.unit_id !== unit.id))
  );

  // Stats
  const stats = {
    total: electives.total,
    language: electives.data.filter(e => e.category === 'language').length,
    other: electives.data.filter(e => e.category === 'other').length,
    active: electives.data.filter(e => e.is_active).length,
  };

  const handleSearch = (e: FormEvent) => {
    e.preventDefault();
    applyFilters();
  };

  const handleFilterChange = (type: string, value: string) => {
    if (type === 'category') setSelectedCategory(value);
    else if (type === 'year_level') setSelectedYearLevel(value);
    else if (type === 'status') setSelectedStatus(value);
    
    // Apply filters with a slight delay to allow state to update
    setTimeout(() => applyFilters(), 0);
  };

  const applyFilters = () => {
    const params: Record<string, any> = {};
    
    if (searchTerm) params.search = searchTerm;
    if (selectedCategory !== 'all') params.category = selectedCategory;
    if (selectedYearLevel !== 'all') params.year_level = selectedYearLevel;
    if (selectedStatus !== 'all') params.status = selectedStatus;

    router.get('/schools/shss/electives', params, {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const resetForm = () => {
    setFormData({
      unit_id: '',
      category: 'language',
      year_level: 1,
      max_students: '',
      min_students: '',
      is_active: true,
      description: '',
      prerequisites: '',
    });
    setFormErrors({});
  };

  const handleOpenCreateModal = () => {
    resetForm();
    setIsCreateModalOpen(true);
  };

  const handleOpenEditModal = (elective: Elective) => {
    setSelectedElective(elective);
    setFormData({
      unit_id: elective.unit_id.toString(),
      category: elective.category,
      year_level: elective.year_level,
      max_students: elective.max_students?.toString() || '',
      min_students: elective.min_students?.toString() || '',
      is_active: elective.is_active,
      description: elective.description || '',
      prerequisites: elective.prerequisites || '',
    });
    setFormErrors({});
    setIsEditModalOpen(true);
  };

  const handleOpenViewModal = (elective: Elective) => {
    setSelectedElective(elective);
    setIsViewModalOpen(true);
  };

  const handleCloseModals = () => {
    setIsCreateModalOpen(false);
    setIsEditModalOpen(false);
    setIsViewModalOpen(false);
    setSelectedElective(null);
    resetForm();
  };

  const validateForm = (): boolean => {
    const errors: Record<string, string> = {};

    if (!formData.unit_id) {
      errors.unit_id = 'Please select a unit';
    }

    if (formData.max_students && formData.min_students) {
      const max = parseInt(formData.max_students.toString());
      const min = parseInt(formData.min_students.toString());
      if (min > max) {
        errors.min_students = 'Minimum students cannot be greater than maximum students';
      }
    }

    setFormErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleSubmitCreate = (e: FormEvent) => {
    e.preventDefault();
    
    if (!validateForm()) {
      toast.error('Please fix the form errors');
      return;
    }

    setLoading(true);

    const submitData = {
      ...formData,
      max_students: formData.max_students ? parseInt(formData.max_students.toString()) : null,
      min_students: formData.min_students ? parseInt(formData.min_students.toString()) : null,
    };

    router.post('/schools/shss/electives', submitData, {
      onSuccess: () => {
        toast.success('Elective created successfully!');
        handleCloseModals();
      },
      onError: (errors: any) => {
        if (typeof errors === 'object') {
          setFormErrors(errors);
        }
        toast.error(errors.message || 'Failed to create elective');
      },
      onFinish: () => {
        setLoading(false);
      },
    });
  };

  const handleSubmitEdit = (e: FormEvent) => {
    e.preventDefault();
    if (!selectedElective) return;

    if (!validateForm()) {
      toast.error('Please fix the form errors');
      return;
    }

    setLoading(true);

    const submitData = {
      ...formData,
      max_students: formData.max_students ? parseInt(formData.max_students.toString()) : null,
      min_students: formData.min_students ? parseInt(formData.min_students.toString()) : null,
    };

    router.put(`/schools/shss/electives/${selectedElective.id}`, submitData, {
      onSuccess: () => {
        toast.success('Elective updated successfully!');
        handleCloseModals();
      },
      onError: (errors: any) => {
        if (typeof errors === 'object') {
          setFormErrors(errors);
        }
        toast.error(errors.message || 'Failed to update elective');
      },
      onFinish: () => {
        setLoading(false);
      },
    });
  };

  const handleDelete = (elective: Elective) => {
    if (!confirm(`Are you sure you want to delete the elective "${elective.unit.name}"?\n\nThis action cannot be undone.`)) {
      return;
    }

    router.delete(`/schools/shss/electives/${elective.id}`, {
      onSuccess: () => {
        toast.success('Elective deleted successfully!');
      },
      onError: (errors: any) => {
        toast.error(errors.message || errors.error || 'Failed to delete elective');
      },
    });
  };

  const handleToggleStatus = (elective: Elective) => {
    router.patch(`/schools/shss/electives/${elective.id}/toggle-status`, {}, {
      onSuccess: () => {
        toast.success(`Elective ${elective.is_active ? 'deactivated' : 'activated'} successfully!`);
      },
      onError: (errors: any) => {
        toast.error(errors.message || 'Failed to update status');
      },
    });
  };

  const handlePageChange = (page: number) => {
    const params: Record<string, any> = { page };
    
    if (searchTerm) params.search = searchTerm;
    if (selectedCategory !== 'all') params.category = selectedCategory;
    if (selectedYearLevel !== 'all') params.year_level = selectedYearLevel;
    if (selectedStatus !== 'all') params.status = selectedStatus;

    router.get('/schools/shss/electives', params, {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const getCategoryColor = (category: string) => {
    return category === 'language' 
      ? 'bg-blue-100 text-blue-800 border-blue-200'
      : 'bg-purple-100 text-purple-800 border-purple-200';
  };

  const getCategoryIcon = (category: string) => {
    if (category === 'language') {
      return (
        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" />
        </svg>
      );
    }
    return (
      <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
      </svg>
    );
  };

  return (
    <AuthenticatedLayout>
      <Head title="Electives Management - SHSS" />

      <div className="min-h-screen bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          {/* Header */}
          <div className="mb-8">
            <div className="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
              <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                <div className="flex items-center space-x-4">
                  <div className="w-16 h-16 bg-gradient-to-br from-amber-500 to-orange-600 rounded-2xl flex items-center justify-center text-3xl">
                    ⭐
                  </div>
                  <div>
                    <h1 className="text-3xl font-bold text-gray-900 mb-2">
                      Electives Management
                    </h1>
                    <p className="text-gray-600 text-lg">
                      Manage language and other elective units for SHSS
                    </p>
                  </div>
                </div>

                {/* Quick Stats */}
                <div className="mt-6 lg:mt-0 grid grid-cols-2 lg:grid-cols-4 gap-4">
                  <div className="bg-gradient-to-r from-gray-500 to-gray-600 rounded-xl p-4 text-white text-center">
                    <div className="text-2xl font-bold">{stats.total}</div>
                    <div className="text-xs opacity-90">Total</div>
                  </div>
                  <div className="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl p-4 text-white text-center">
                    <div className="text-2xl font-bold">{stats.language}</div>
                    <div className="text-xs opacity-90">Language</div>
                  </div>
                  <div className="bg-gradient-to-r from-purple-500 to-purple-600 rounded-xl p-4 text-white text-center">
                    <div className="text-2xl font-bold">{stats.other}</div>
                    <div className="text-xs opacity-90">Other</div>
                  </div>
                  <div className="bg-gradient-to-r from-green-500 to-green-600 rounded-xl p-4 text-white text-center">
                    <div className="text-2xl font-bold">{stats.active}</div>
                    <div className="text-xs opacity-90">Active</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Controls */}
          <div className="mb-8">
            <div className="bg-white rounded-2xl shadow-lg p-6 border border-gray-100">
              <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                {/* Search and Filters */}
                <form onSubmit={handleSearch} className="flex flex-col sm:flex-row gap-4 flex-1">
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
                      onKeyDown={(e) => e.key === 'Enter' && handleSearch(e)}
                      placeholder="Search electives..."
                      className="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl leading-5 bg-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                    />
                  </div>

                  {/* Category Filter */}
                  <select
                    value={selectedCategory}
                    onChange={(e) => handleFilterChange('category', e.target.value)}
                    className="appearance-none bg-white border border-gray-300 rounded-xl px-4 py-3 pr-8 text-gray-700 focus:outline-none focus:ring-2 focus:ring-amber-500"
                  >
                    <option value="all">All Categories</option>
                    <option value="language">Language</option>
                    <option value="other">Other</option>
                  </select>

                  {/* Year Level Filter */}
                  <select
                    value={selectedYearLevel}
                    onChange={(e) => handleFilterChange('year_level', e.target.value)}
                    className="appearance-none bg-white border border-gray-300 rounded-xl px-4 py-3 pr-8 text-gray-700 focus:outline-none focus:ring-2 focus:ring-amber-500"
                  >
                    <option value="all">All Years</option>
                    <option value="1">Year 1</option>
                    <option value="2">Year 2</option>
                    <option value="3">Year 3</option>
                    <option value="4">Year 4</option>
                  </select>

                  {/* Status Filter */}
                  <select
                    value={selectedStatus}
                    onChange={(e) => handleFilterChange('status', e.target.value)}
                    className="appearance-none bg-white border border-gray-300 rounded-xl px-4 py-3 pr-8 text-gray-700 focus:outline-none focus:ring-2 focus:ring-amber-500"
                  >
                    <option value="all">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                  </select>
                </form>

                {/* Create Button */}
                <button
                  onClick={handleOpenCreateModal}
                  className="inline-flex items-center px-6 py-3 bg-gradient-to-r from-green-600 to-emerald-600 text-white font-medium rounded-xl hover:from-green-700 hover:to-emerald-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-all duration-200 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5"
                >
                  <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                  </svg>
                  Create Elective
                </button>
              </div>
            </div>
          </div>

          {/* Electives Table */}
          <div className="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gradient-to-r from-gray-50 to-gray-100">
                  <tr>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Unit</th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Category</th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Year Level</th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Capacity</th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Enrollment</th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                  {electives.data.length > 0 ? (
                    electives.data.map((elective, index) => (
                      <tr key={elective.id} className={`hover:bg-amber-50 transition-colors duration-200 ${index % 2 === 0 ? 'bg-white' : 'bg-gray-50'}`}>
                        <td className="px-6 py-4">
                          <div>
                            <div className="text-sm font-medium text-gray-900">{elective.unit.name}</div>
                            <div className="text-xs text-gray-500">{elective.unit.code} • {elective.unit.credit_hours} credits</div>
                          </div>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-medium border ${getCategoryColor(elective.category)}`}>
                            {getCategoryIcon(elective.category)}
                            <span className="ml-1.5 capitalize">{elective.category}</span>
                          </span>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <span className="inline-flex items-center px-2 py-1 rounded-lg text-xs font-medium bg-gray-100 text-gray-800">
                            Year {elective.year_level}
                          </span>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <div className="text-sm text-gray-900">
                            {elective.max_students ? (
                              <span>Max: {elective.max_students}</span>
                            ) : (
                              <span className="text-gray-400">Unlimited</span>
                            )}
                          </div>
                          {elective.min_students && (
                            <div className="text-xs text-gray-500">Min: {elective.min_students}</div>
                          )}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <div className="text-sm font-medium text-gray-900">
                            {elective.current_enrollment || 0}
                            {elective.max_students && ` / ${elective.max_students}`}
                          </div>
                          {elective.available_spots !== null && (
                            <div className={`text-xs ${elective.available_spots === 0 ? 'text-red-600' : 'text-green-600'}`}>
                              {elective.available_spots} spots left
                            </div>
                          )}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <button
                            onClick={() => handleToggleStatus(elective)}
                            className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ${
                              elective.is_active
                                ? 'bg-green-100 text-green-800 hover:bg-green-200'
                                : 'bg-red-100 text-red-800 hover:bg-red-200'
                            } transition-colors`}
                          >
                            {elective.is_active ? 'Active' : 'Inactive'}
                          </button>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <div className="flex items-center space-x-3">
                            <button
                              onClick={() => handleOpenViewModal(elective)}
                              className="text-indigo-600 hover:text-indigo-800 transition-colors"
                              title="View Details"
                            >
                              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                              </svg>
                            </button>
                            <button
                              onClick={() => handleOpenEditModal(elective)}
                              className="text-blue-600 hover:text-blue-800 transition-colors"
                              title="Edit"
                            >
                              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                              </svg>
                            </button>
                            <button
                              onClick={() => handleDelete(elective)}
                              className="text-red-600 hover:text-red-800 transition-colors"
                              title="Delete"
                            >
                              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                              </svg>
                            </button>
                          </div>
                        </td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan={7} className="px-6 py-12 text-center">
                        <div className="flex flex-col items-center">
                          <svg className="w-16 h-16 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                          </svg>
                          <h3 className="text-lg font-semibold text-gray-900 mb-2">No electives found</h3>
                          <p className="text-gray-500 mb-4">
                            {searchTerm || selectedCategory !== 'all' || selectedYearLevel !== 'all' || selectedStatus !== 'all'
                              ? 'Try adjusting your filters'
                              : 'Get started by creating your first elective'}
                          </p>
                          <button
                            onClick={handleOpenCreateModal}
                            className="inline-flex items-center px-4 py-2 bg-gradient-to-r from-green-600 to-emerald-600 text-white font-medium rounded-lg hover:from-green-700 hover:to-emerald-700"
                          >
                            <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            Create Elective
                          </button>
                        </div>
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>

            {/* Pagination */}
            {electives.last_page > 1 && (
              <div className="bg-gray-50 px-6 py-4 border-t border-gray-200">
                <div className="flex items-center justify-between">
                  <div className="text-sm text-gray-600">
                    Showing {electives.from} to {electives.to} of {electives.total} results
                  </div>
                  <div className="flex space-x-2">
                    {/* Previous Button */}
                    {electives.current_page > 1 && (
                      <button
                        onClick={() => handlePageChange(electives.current_page - 1)}
                        className="px-4 py-2 rounded-lg text-sm font-medium bg-white text-gray-700 hover:bg-gray-100 border border-gray-300 transition-colors"
                      >
                        Previous
                      </button>
                    )}

                    {/* Page Numbers */}
                    {Array.from({ length: electives.last_page }, (_, i) => i + 1)
                      .filter(page => {
                        const current = electives.current_page;
                        return page === 1 || page === electives.last_page || 
                               (page >= current - 1 && page <= current + 1);
                      })
                      .map((page, index, array) => {
                        const prevPage = array[index - 1];
                        const showEllipsis = prevPage && page - prevPage > 1;
                        
                        return (
                          <React.Fragment key={page}>
                            {showEllipsis && (
                              <span className="px-4 py-2 text-gray-500">...</span>
                            )}
                            <button
                              onClick={() => handlePageChange(page)}
                              className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
                                page === electives.current_page
                                  ? 'bg-amber-600 text-white'
                                  : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-300'
                              }`}
                            >
                              {page}
                            </button>
                          </React.Fragment>
                        );
                      })}

                    {/* Next Button */}
                    {electives.current_page < electives.last_page && (
                      <button
                        onClick={() => handlePageChange(electives.current_page + 1)}
                        className="px-4 py-2 rounded-lg text-sm font-medium bg-white text-gray-700 hover:bg-gray-100 border border-gray-300 transition-colors"
                      >
                        Next
                      </button>
                    )}
                  </div>
                </div>
              </div>
            )}
          </div>

          {/* Create/Edit Modal */}
          {(isCreateModalOpen || isEditModalOpen) && (
            <ElectiveFormModal
              title={isEditModalOpen ? 'Edit Elective' : 'Create New Elective'}
              formData={formData}
              setFormData={setFormData}
              formErrors={formErrors}
              onSubmit={isEditModalOpen ? handleSubmitEdit : handleSubmitCreate}
              onClose={handleCloseModals}
              loading={loading}
              availableUnits={availableUnits}
              isEdit={isEditModalOpen}
              currentUnit={selectedElective?.unit}
            />
          )}

          {/* View Modal */}
          {isViewModalOpen && selectedElective && (
            <ElectiveViewModal
              elective={selectedElective}
              onClose={handleCloseModals}
              onEdit={() => {
                setIsViewModalOpen(false);
                handleOpenEditModal(selectedElective);
              }}
            />
          )}
        </div>
      </div>
    </AuthenticatedLayout>
  );
}

// Form Modal Component
interface ElectiveFormModalProps {
  title: string;
  formData: FormData;
  setFormData: (data: FormData) => void;
  formErrors: Record<string, string>;
  onSubmit: (e: FormEvent) => void;
  onClose: () => void;
  loading: boolean;
  availableUnits: Unit[];
  isEdit: boolean;
  currentUnit?: Unit;
}

function ElectiveFormModal({
  title,
  formData,
  setFormData,
  formErrors,
  onSubmit,
  onClose,
  loading,
  availableUnits,
  isEdit,
  currentUnit
}: ElectiveFormModalProps) {
  return (
    <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-2xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
        <div className="bg-gradient-to-r from-amber-500 via-orange-500 to-orange-600 p-6 rounded-t-2xl">
          <div className="flex items-center justify-between">
            <h3 className="text-xl font-semibold text-white">{title}</h3>
            <button
              onClick={onClose}
              className="text-white hover:text-gray-200 transition-colors"
            >
              <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        <form onSubmit={onSubmit} className="p-6 space-y-6">
          {/* Unit Selection */}
          {/* Unit Selection */}
<div>
  <label className="block text-sm font-medium text-gray-700 mb-2">Unit *</label>
  {isEdit && currentUnit ? (
    <div className="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100">
      <div className="font-medium text-gray-900">{currentUnit.name}</div>
      <div className="text-sm text-gray-600">{currentUnit.code} • {currentUnit.credit_hours} credits</div>
    </div>
  ) : (
    <>
      <select
        value={formData.unit_id}
        onChange={(e) => setFormData({ ...formData, unit_id: e.target.value })}
        className={`w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500 ${
          formErrors.unit_id ? 'border-red-300' : 'border-gray-300'
        }`}
        required
      >
        <option value="">-- Select a unit --</option>
        {availableUnits.map((unit) => (
          <option key={unit.id} value={unit.id}>
            {unit.name} ({unit.code}) - {unit.credit_hours} credits
          </option>
        ))}
      </select>

      {formErrors.unit_id && (
        <p className="mt-1 text-sm text-red-600">{formErrors.unit_id}</p>
      )}
    </>
  )}
</div>

          {/* Category and Year Level */}
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Category *</label>
              <select
                value={formData.category}
                onChange={(e) => setFormData({ ...formData, category: e.target.value as 'language' | 'other' })}
                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                required
              >
                <option value="language">Language</option>
                <option value="other">Other</option>
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Year Level *</label>
              <select
                value={formData.year_level}
                onChange={(e) => setFormData({ ...formData, year_level: parseInt(e.target.value) })}
                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                required
              >
                <option value="1">Year 1</option>
                <option value="2">Year 2</option>
                <option value="3">Year 3</option>
                <option value="4">Year 4</option>
              </select>
            </div>
          </div>

          {/* Capacity Settings */}
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Maximum Students</label>
              <input
                type="number"
                value={formData.max_students}
                onChange={(e) => setFormData({ ...formData, max_students: e.target.value })}
                placeholder="Leave empty for unlimited"
                min="0"
                className={`w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500 ${
                  formErrors.max_students ? 'border-red-300' : 'border-gray-300'
                }`}
              />
              {formErrors.max_students && (
                <p className="mt-1 text-sm text-red-600">{formErrors.max_students}</p>
              )}
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Minimum Students</label>
              <input
                type="number"
                value={formData.min_students}
                onChange={(e) => setFormData({ ...formData, min_students: e.target.value })}
                placeholder="Minimum to run"
                min="0"
                className={`w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500 ${
                  formErrors.min_students ? 'border-red-300' : 'border-gray-300'
                }`}
              />
              {formErrors.min_students && (
                <p className="mt-1 text-sm text-red-600">{formErrors.min_students}</p>
              )}
            </div>
          </div>

          {/* Description */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">Description</label>
            <textarea
              value={formData.description}
              onChange={(e) => setFormData({ ...formData, description: e.target.value })}
              rows={3}
              placeholder="Describe this elective..."
              className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
            />
          </div>

          {/* Prerequisites */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">Prerequisites</label>
            <textarea
              value={formData.prerequisites}
              onChange={(e) => setFormData({ ...formData, prerequisites: e.target.value })}
              rows={2}
              placeholder="Any prerequisites for this elective..."
              className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
            />
          </div>

          {/* Active Status */}
          <div className="flex items-center">
            <input
              type="checkbox"
              id="is_active"
              checked={formData.is_active}
              onChange={(e) => setFormData({ ...formData, is_active: e.target.checked })}
              className="h-4 w-4 text-amber-600 focus:ring-amber-500 border-gray-300 rounded"
            />
            <label htmlFor="is_active" className="ml-2 block text-sm text-gray-900">
              Active (Students can enroll in this elective)
            </label>
          </div>

          {/* Form Actions */}
          <div className="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200">
            <button
              type="button"
              onClick={onClose}
              disabled={loading}
              className="px-6 py-3 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors font-medium disabled:opacity-50"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={loading}
              className="px-6 py-3 bg-gradient-to-r from-amber-500 to-orange-600 text-white rounded-lg hover:from-amber-600 hover:to-orange-700 transition-all duration-200 font-medium disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {loading ? 'Saving...' : isEdit ? 'Update Elective' : 'Create Elective'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

// View Modal Component
interface ElectiveViewModalProps {
  elective: Elective;
  onClose: () => void;
  onEdit: () => void;
}

function ElectiveViewModal({ elective, onClose, onEdit }: ElectiveViewModalProps) {
  return (
    <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div className="bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 p-6 rounded-t-2xl">
          <div className="flex items-center justify-between">
            <div>
              <h3 className="text-xl font-semibold text-white">Elective Details</h3>
              <p className="text-indigo-100 text-sm mt-1">{elective.unit.code}</p>
            </div>
            <button
              onClick={onClose}
              className="text-white hover:text-gray-200 transition-colors"
            >
              <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        <div className="p-6 space-y-6">
          {/* Unit Info */}
          <div className="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-4 border border-blue-200">
            <h4 className="text-lg font-semibold text-gray-900 mb-2">{elective.unit.name}</h4>
            <div className="flex items-center space-x-4 text-sm text-gray-600">
              <span className="font-medium">{elective.unit.code}</span>
              <span>•</span>
              <span>{elective.unit.credit_hours} Credit Hours</span>
            </div>
          </div>

          {/* Details Grid */}
          <div className="grid grid-cols-2 gap-4">
            <div className="bg-gray-50 rounded-lg p-4">
              <div className="text-xs text-gray-500 uppercase tracking-wider mb-1">Category</div>
              <div className="text-sm font-semibold text-gray-900 capitalize">{elective.category}</div>
            </div>

            <div className="bg-gray-50 rounded-lg p-4">
              <div className="text-xs text-gray-500 uppercase tracking-wider mb-1">Year Level</div>
              <div className="text-sm font-semibold text-gray-900">Year {elective.year_level}</div>
            </div>

            <div className="bg-gray-50 rounded-lg p-4">
              <div className="text-xs text-gray-500 uppercase tracking-wider mb-1">Current Enrollment</div>
              <div className="text-sm font-semibold text-gray-900">
                {elective.current_enrollment || 0}
                {elective.max_students && ` / ${elective.max_students}`}
              </div>
            </div>

            <div className="bg-gray-50 rounded-lg p-4">
              <div className="text-xs text-gray-500 uppercase tracking-wider mb-1">Status</div>
              <div>
                <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${
                  elective.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                }`}>
                  {elective.is_active ? 'Active' : 'Inactive'}
                </span>
              </div>
            </div>
          </div>

          {/* Capacity Info */}
          {(elective.max_students || elective.min_students) && (
            <div className="bg-amber-50 rounded-lg p-4 border border-amber-200">
              <h5 className="text-sm font-semibold text-amber-900 mb-2">Capacity Settings</h5>
              <div className="space-y-1 text-sm text-amber-800">
                {elective.max_students && (
                  <div>Maximum Students: {elective.max_students}</div>
                )}
                {elective.min_students && (
                  <div>Minimum Students: {elective.min_students}</div>
                )}
                {elective.available_spots !== null && (
                  <div className="font-medium">Available Spots: {elective.available_spots}</div>
                )}
              </div>
            </div>
          )}

          {/* Description */}
          {elective.description && (
            <div>
              <h5 className="text-sm font-semibold text-gray-700 mb-2">Description</h5>
              <p className="text-sm text-gray-600 whitespace-pre-wrap">{elective.description}</p>
            </div>
          )}

          {/* Prerequisites */}
          {elective.prerequisites && (
            <div>
              <h5 className="text-sm font-semibold text-gray-700 mb-2">Prerequisites</h5>
              <p className="text-sm text-gray-600 whitespace-pre-wrap">{elective.prerequisites}</p>
            </div>
          )}

          {/* Timestamps */}
          <div className="text-xs text-gray-500 space-y-1 pt-4 border-t border-gray-200">
            <div>Created: {new Date(elective.created_at).toLocaleString()}</div>
            <div>Last Updated: {new Date(elective.updated_at).toLocaleString()}</div>
          </div>

          {/* Actions */}
          <div className="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200">
            <button
              onClick={onClose}
              className="px-6 py-3 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors font-medium"
            >
              Close
            </button>
            <button
              onClick={onEdit}
              className="px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-lg hover:from-blue-700 hover:to-indigo-700 transition-all duration-200 font-medium"
            >
              Edit Elective
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}