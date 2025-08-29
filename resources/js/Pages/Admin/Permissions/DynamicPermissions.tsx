import React, { useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { toast } from 'react-hot-toast';

interface Permission {
    id: number;
    name: string;
    roles_count: number;
    is_core: boolean;
    category: string;
    description: string | null;
    created_at: string;
}

interface PaginationLinks {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedPermissions {
    data: Permission[];
    links: PaginationLinks[];
    total: number;
    per_page: number;
    current_page: number;
}

const DynamicPermissions = () => {
    const { permissions, stats, categories, categoryLabels, filter, category, search, perPage } = usePage().props as {
        permissions: PaginatedPermissions;
        stats: { total: number; core: number; dynamic: number; };
        categories: string[];
        categoryLabels: Record<string, string>;
        filter: string;
        category: string;
        search: string;
        perPage: number;
    };

    const [searchQuery, setSearchQuery] = useState(search);
    const [itemsPerPage, setItemsPerPage] = useState(perPage);
    const [selectedFilter, setSelectedFilter] = useState(filter);
    const [selectedCategory, setSelectedCategory] = useState(category);

    // Modal states
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [isEditModalOpen, setIsEditModalOpen] = useState(false);
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [isViewModalOpen, setIsViewModalOpen] = useState(false);
    const [isBulkCreateModalOpen, setIsBulkCreateModalOpen] = useState(false);
    
    // Current permission being operated on
    const [currentPermission, setCurrentPermission] = useState<Permission | null>(null);
    
    // Form data
    const [formData, setFormData] = useState({
        name: '',
        description: '',
        category: 'general'
    });

    // Bulk create data
    const [bulkPermissions, setBulkPermissions] = useState([
        { name: '', description: '', category: 'general' }
    ]);
    
    const [selectedPermissions, setSelectedPermissions] = useState<number[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const handleSearch = () => {
        router.get('/admin/permissions/dynamic', {
            search: searchQuery,
            filter: selectedFilter,
            category: selectedCategory,
            per_page: itemsPerPage
        }, { preserveState: true });
    };

    const handleFilterChange = (newFilter: string) => {
        setSelectedFilter(newFilter);
        router.get('/admin/permissions/dynamic', {
            search: searchQuery,
            filter: newFilter,
            category: selectedCategory,
            per_page: itemsPerPage
        }, { preserveState: true });
    };

    const handleCategoryChange = (newCategory: string) => {
        setSelectedCategory(newCategory);
        router.get('/admin/permissions/dynamic', {
            search: searchQuery,
            filter: selectedFilter,
            category: newCategory,
            per_page: itemsPerPage
        }, { preserveState: true });
    };

    const handlePerPageChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        const newPerPage = parseInt(e.target.value);
        setItemsPerPage(newPerPage);
        router.get('/admin/permissions/dynamic', {
            search: searchQuery,
            filter: selectedFilter,
            category: selectedCategory,
            per_page: newPerPage
        }, { preserveState: true });
    };

    const handlePageChange = (url: string | null) => {
        if (url) {
            router.get(url, {}, { preserveState: true });
        }
    };

    const resetForm = () => {
        setFormData({ name: '', description: '', category: 'general' });
        setErrors({});
    };

    const closeAllModals = () => {
        setIsCreateModalOpen(false);
        setIsEditModalOpen(false);
        setIsDeleteModalOpen(false);
        setIsViewModalOpen(false);
        setIsBulkCreateModalOpen(false);
        setCurrentPermission(null);
        resetForm();
    };

    // Create Modal
    const openCreateModal = () => {
        resetForm();
        setIsCreateModalOpen(true);
    };

    const handleCreate = () => {
        setIsLoading(true);
        router.post('/admin/permissions', formData, {
            onSuccess: () => {
                toast.success('Permission created successfully!');
                closeAllModals();
            },
            onError: (errors) => {
                setErrors(errors);
                if (errors.error) toast.error(errors.error);
            },
            onFinish: () => setIsLoading(false)
        });
    };

    // Edit Modal
    const openEditModal = (permission: Permission) => {
        if (permission.is_core) {
            toast.error('Core permissions cannot be edited.');
            return;
        }

        setCurrentPermission(permission);
        setFormData({
            name: permission.name,
            description: permission.description || '',
            category: permission.category
        });
        setIsEditModalOpen(true);
    };

    const handleUpdate = () => {
        if (!currentPermission) return;
        
        setIsLoading(true);
        router.put(`/admin/permissions/${currentPermission.id}`, formData, {
            onSuccess: () => {
                toast.success('Permission updated successfully!');
                closeAllModals();
            },
            onError: (errors) => {
                setErrors(errors);
                if (errors.error) toast.error(errors.error);
            },
            onFinish: () => setIsLoading(false)
        });
    };

    // Delete Modal
    const openDeleteModal = (permission: Permission) => {
        if (permission.is_core) {
            toast.error('Core permissions cannot be deleted.');
            return;
        }
        setCurrentPermission(permission);
        setIsDeleteModalOpen(true);
    };

    const handleDelete = () => {
        if (!currentPermission) return;
        
        setIsLoading(true);
        router.delete(`/admin/permissions/${currentPermission.id}`, {
            onSuccess: () => {
                toast.success('Permission deleted successfully!');
                closeAllModals();
            },
            onError: (errors) => {
                const errorMessage = errors.error || 'Failed to delete permission.';
                toast.error(errorMessage);
            },
            onFinish: () => setIsLoading(false)
        });
    };

    // View Modal
    const openViewModal = (permission: Permission) => {
        setCurrentPermission(permission);
        setIsViewModalOpen(true);
    };

    // Bulk Create
    const openBulkCreateModal = () => {
        setBulkPermissions([{ name: '', description: '', category: 'general' }]);
        setIsBulkCreateModalOpen(true);
    };

    const addBulkPermission = () => {
        setBulkPermissions([...bulkPermissions, { name: '', description: '', category: 'general' }]);
    };

    const removeBulkPermission = (index: number) => {
        setBulkPermissions(bulkPermissions.filter((_, i) => i !== index));
    };

    const updateBulkPermission = (index: number, field: string, value: string) => {
        const updated = [...bulkPermissions];
        updated[index] = { ...updated[index], [field]: value };
        setBulkPermissions(updated);
    };

    const handleBulkCreate = () => {
        const validPermissions = bulkPermissions.filter(p => p.name.trim());
        
        if (validPermissions.length === 0) {
            toast.error('Please add at least one permission with a name.');
            return;
        }

        setIsLoading(true);
        router.post('/admin/permissions/bulk', { permissions: validPermissions }, {
            onSuccess: () => {
                toast.success(`${validPermissions.length} permissions created successfully!`);
                closeAllModals();
            },
            onError: (errors) => {
                setErrors(errors);
                if (errors.error) toast.error(errors.error);
            },
            onFinish: () => setIsLoading(false)
        });
    };

    const handleInputChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
        const { name, value } = e.target;
        setFormData(prev => ({ ...prev, [name]: value }));
        if (errors[name]) {
            setErrors(prev => ({ ...prev, [name]: '' }));
        }
    };

    const handlePermissionSelection = (permissionId: number) => {
        setSelectedPermissions(prev => 
            prev.includes(permissionId)
                ? prev.filter(id => id !== permissionId)
                : [...prev, permissionId]
        );
    };

    const handleSelectAll = () => {
        if (selectedPermissions.length === permissions.data.length) {
            setSelectedPermissions([]);
        } else {
            setSelectedPermissions(permissions.data.map(p => p.id));
        }
    };

    const getPermissionBadgeColor = (permission: Permission) => {
        if (permission.is_core) {
            return 'bg-blue-100 text-blue-800 border-blue-200';
        }
        return 'bg-green-100 text-green-800 border-green-200';
    };

    const getCategoryBadgeColor = (category: string) => {
        const colors: Record<string, string> = {
            'dashboard': 'bg-purple-100 text-purple-800',
            'user_management': 'bg-indigo-100 text-indigo-800',
            'role_management': 'bg-pink-100 text-pink-800',
            'academic_core': 'bg-emerald-100 text-emerald-800',
            'timetable_management': 'bg-orange-100 text-orange-800',
            'personal_access': 'bg-cyan-100 text-cyan-800',
            'custom': 'bg-yellow-100 text-yellow-800',
            'general': 'bg-slate-100 text-slate-800',
        };
        return colors[category] || colors['general'];
    };

    return (
        <AuthenticatedLayout>
            <Head title="Dynamic Permissions Management" />
            <div className="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50 py-8">
                {/* Header */}
                <div className="bg-white rounded-2xl shadow-xl border p-8 mb-6">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h1 className="text-4xl font-bold text-slate-800 mb-2">Permission Management</h1>
                            <p className="text-slate-600 text-lg">Manage system permissions and create dynamic permissions</p>
                        </div>
                        <div className="flex space-x-3 mt-6 sm:mt-0">
                            <button
                                onClick={openBulkCreateModal}
                                className="inline-flex items-center px-4 py-2 bg-gradient-to-r from-purple-500 to-purple-600 text-white font-medium rounded-xl shadow-lg hover:from-purple-600 hover:to-purple-700 transform hover:scale-105 transition-all duration-200"
                            >
                                <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4" />
                                </svg>
                                Bulk Create
                            </button>
                            <button
                                onClick={openCreateModal}
                                className="inline-flex items-center px-6 py-3 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white font-semibold rounded-xl shadow-lg hover:from-emerald-600 hover:to-emerald-700 transform hover:scale-105 transition-all duration-200"
                            >
                                <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                Create Permission
                            </button>
                        </div>
                    </div>
                </div>

                {/* Statistics Cards */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div 
                        className={`bg-white rounded-xl p-6 shadow-lg border cursor-pointer hover:scale-105 transition-transform ${selectedFilter === 'all' ? 'ring-2 ring-blue-500' : ''}`}
                        onClick={() => handleFilterChange('all')}
                    >
                        <h3 className="text-sm font-semibold text-slate-600 uppercase tracking-wider">Total Permissions</h3>
                        <p className="text-3xl font-bold text-slate-800">{stats.total}</p>
                    </div>

                    <div 
                        className={`bg-white rounded-xl p-6 shadow-lg border cursor-pointer hover:scale-105 transition-transform ${selectedFilter === 'core' ? 'ring-2 ring-blue-500' : ''}`}
                        onClick={() => handleFilterChange('core')}
                    >
                        <h3 className="text-sm font-semibold text-slate-600 uppercase tracking-wider">Core Permissions</h3>
                        <p className="text-3xl font-bold text-blue-600">{stats.core}</p>
                    </div>

                    <div 
                        className={`bg-white rounded-xl p-6 shadow-lg border cursor-pointer hover:scale-105 transition-transform ${selectedFilter === 'dynamic' ? 'ring-2 ring-blue-500' : ''}`}
                        onClick={() => handleFilterChange('dynamic')}
                    >
                        <h3 className="text-sm font-semibold text-slate-600 uppercase tracking-wider">Dynamic Permissions</h3>
                        <p className="text-3xl font-bold text-emerald-600">{stats.dynamic}</p>
                    </div>

                    <div className="bg-white rounded-xl p-6 shadow-lg border">
                        <h3 className="text-sm font-semibold text-slate-600 uppercase tracking-wider mb-2">Category Filter</h3>
                        <select
                            value={selectedCategory}
                            onChange={(e) => handleCategoryChange(e.target.value)}
                            className="w-full border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                        >
                            <option value="all">All Categories</option>
                            {categories.map(cat => (
                                <option key={cat} value={cat}>{categoryLabels[cat] || cat}</option>
                            ))}
                        </select>
                    </div>
                </div>

                {/* Controls */}
                <div className="bg-white rounded-2xl shadow-xl border p-6 mb-6">
                    <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
                        <div className="flex items-center space-x-3">
                            <div className="relative">
                                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg className="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                <input
                                    type="text"
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    onKeyPress={(e) => e.key === 'Enter' && handleSearch()}
                                    placeholder="Search permissions..."
                                    className="block w-full pl-10 pr-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                />
                            </div>
                            <button
                                onClick={handleSearch}
                                className="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white font-medium rounded-lg hover:from-blue-600 hover:to-blue-700 transition-all duration-200"
                            >
                                Search
                            </button>
                        </div>

                        <div className="flex items-center space-x-4">
                            {selectedPermissions.length > 0 && (
                                <div className="text-sm text-slate-600">
                                    {selectedPermissions.length} selected
                                </div>
                            )}
                            
                            <div className="flex items-center space-x-2">
                                <label htmlFor="perPage" className="text-sm font-medium text-slate-700">
                                    Items per page:
                                </label>
                                <select
                                    id="perPage"
                                    value={itemsPerPage}
                                    onChange={handlePerPageChange}
                                    className="border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                >
                                    <option value={10}>10</option>
                                    <option value={20}>20</option>
                                    <option value={50}>50</option>
                                    <option value={100}>100</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Permissions Table */}
                <div className="bg-white rounded-2xl shadow-xl border">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="bg-slate-50">
                                <tr>
                                    <th className="px-6 py-4 text-left">
                                        <input
                                            type="checkbox"
                                            checked={selectedPermissions.length === permissions.data.length && permissions.data.length > 0}
                                            onChange={handleSelectAll}
                                            className="rounded focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300"
                                        />
                                    </th>
                                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Permission</th>
                                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Category</th>
                                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Type</th>
                                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Roles</th>
                                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Created</th>
                                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200">
                                {permissions.data.length ? (
                                    permissions.data.map((permission, index) => (
                                        <tr key={permission.id} className={`hover:bg-slate-50 transition-colors ${index % 2 === 0 ? 'bg-white' : 'bg-slate-25'}`}>
                                            <td className="px-6 py-4">
                                                <input
                                                    type="checkbox"
                                                    checked={selectedPermissions.includes(permission.id)}
                                                    onChange={() => handlePermissionSelection(permission.id)}
                                                    className="rounded focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300"
                                                />
                                            </td>
                                            <td className="px-6 py-4">
                                                <div>
                                                    <div className="text-sm font-medium text-slate-900">{permission.name}</div>
                                                    {permission.description && (
                                                        <div className="text-sm text-slate-500 mt-1">{permission.description}</div>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className={`inline-flex px-2 py-1 text-xs font-medium rounded-full ${getCategoryBadgeColor(permission.category)}`}>
                                                    {categoryLabels[permission.category] || permission.category}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className={`inline-flex px-3 py-1 text-xs font-medium rounded-full border ${getPermissionBadgeColor(permission)}`}>
                                                    {permission.is_core ? 'Core' : 'Dynamic'}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex items-center">
                                                    <span className="text-sm font-medium text-slate-900">{permission.roles_count}</span>
                                                    <span className="ml-2 text-xs text-slate-500">roles</span>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="text-sm text-slate-900">
                                                    {new Date(permission.created_at).toLocaleDateString()}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex space-x-2">
                                                    <button
                                                        onClick={() => openViewModal(permission)}
                                                        className="inline-flex items-center px-3 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white text-sm font-medium rounded-lg hover:from-blue-600 hover:to-blue-700 transform hover:scale-105 transition-all duration-200"
                                                    >
                                                        <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                        </svg>
                                                        View
                                                    </button>
                                                    {!permission.is_core && (
                                                        <>
                                                            <button
                                                                onClick={() => openEditModal(permission)}
                                                                className="inline-flex items-center px-3 py-2 bg-gradient-to-r from-amber-500 to-amber-600 text-white text-sm font-medium rounded-lg hover:from-amber-600 hover:to-amber-700 transform hover:scale-105 transition-all duration-200"
                                                            >
                                                                <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                                </svg>
                                                                Edit
                                                            </button>
                                                            <button
                                                                onClick={() => openDeleteModal(permission)}
                                                                className="inline-flex items-center px-3 py-2 bg-gradient-to-r from-red-500 to-red-600 text-white text-sm font-medium rounded-lg hover:from-red-600 hover:to-red-700 transform hover:scale-105 transition-all duration-200"
                                                            >
                                                                <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                </svg>
                                                                Delete
                                                            </button>
                                                        </>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={7} className="px-6 py-12 text-center">
                                            <div className="flex flex-col items-center">
                                                <svg className="w-16 h-16 text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                                </svg>
                                                <h3 className="text-lg font-medium text-slate-900 mb-1">No permissions found</h3>
                                                <p className="text-slate-500">Try adjusting your search or create a new permission</p>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {permissions.data.length > 0 && (
                        <div className="px-6 py-4 bg-slate-50 border-t flex items-center justify-between">
                            <p className="text-sm text-slate-600">
                                Showing {permissions.data.length} of {permissions.total} permissions
                            </p>
                            <div className="flex space-x-2">
                                {permissions.links.map((link, index) => (
                                    <button
                                        key={index}
                                        onClick={() => handlePageChange(link.url)}
                                        disabled={!link.url}
                                        className={`px-3 py-2 text-sm rounded-lg transition-all ${
                                            link.active
                                                ? 'bg-blue-500 text-white'
                                                : link.url
                                                ? 'bg-white text-slate-700 hover:bg-slate-100 border'
                                                : 'bg-slate-100 text-slate-400 cursor-not-allowed'
                                        }`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Create Permission Modal */}
            {isCreateModalOpen && (
                <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50 p-4">
                    <div className="bg-white rounded-2xl shadow-2xl max-w-2xl w-full">
                        <div className="p-6">
                            <div className="flex items-center justify-between mb-6">
                                <div>
                                    <h3 className="text-2xl font-bold text-slate-800">Create New Permission</h3>
                                    <p className="text-slate-600 mt-1">Define a new system permission</p>
                                </div>
                                <button
                                    onClick={closeAllModals}
                                    className="p-2 hover:bg-slate-100 rounded-full transition-colors"
                                >
                                    <svg className="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <div className="space-y-4">
                                <div>
                                    <label className="block text-sm font-semibold text-slate-700 mb-2">Permission Name</label>
                                    <input
                                        type="text"
                                        name="name"
                                        value={formData.name}
                                        onChange={handleInputChange}
                                        placeholder="e.g., custom-advanced-reporting"
                                        className="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    />
                                    {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-semibold text-slate-700 mb-2">Description</label>
                                    <textarea
                                        name="description"
                                        value={formData.description}
                                        onChange={handleInputChange}
                                        placeholder="Enter permission description (optional)"
                                        rows={3}
                                        className="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                                    />
                                    {errors.description && <p className="mt-1 text-sm text-red-600">{errors.description}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-semibold text-slate-700 mb-2">Category</label>
                                    <select
                                        name="category"
                                        value={formData.category}
                                        onChange={handleInputChange}
                                        className="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    >
                                        {categories.map(cat => (
                                            <option key={cat} value={cat}>{categoryLabels[cat] || cat}</option>
                                        ))}
                                    </select>
                                    {errors.category && <p className="mt-1 text-sm text-red-600">{errors.category}</p>}
                                </div>
                            </div>

                            <div className="flex justify-end space-x-4 pt-6 border-t">
                                <button
                                    onClick={closeAllModals}
                                    disabled={isLoading}
                                    className="px-6 py-3 text-slate-700 bg-slate-100 hover:bg-slate-200 font-semibold rounded-xl transition-all duration-200 disabled:opacity-50"
                                >
                                    Cancel
                                </button>
                                <button
                                    onClick={handleCreate}
                                    disabled={isLoading || !formData.name.trim()}
                                    className="px-6 py-3 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white font-semibold rounded-xl hover:from-emerald-600 hover:to-emerald-700 transform hover:scale-105 transition-all duration-200 disabled:opacity-50 disabled:transform-none"
                                >
                                    {isLoading ? (
                                        <div className="flex items-center">
                                            <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-white mr-2"></div>
                                            Creating...
                                        </div>
                                    ) : (
                                        'Create Permission'
                                    )}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Edit Permission Modal */}
            {isEditModalOpen && currentPermission && (
                <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50 p-4">
                    <div className="bg-white rounded-2xl shadow-2xl max-w-2xl w-full">
                        <div className="p-6">
                            <div className="flex items-center justify-between mb-6">
                                <div>
                                    <h3 className="text-2xl font-bold text-slate-800">Edit Permission: {currentPermission.name}</h3>
                                    <p className="text-slate-600 mt-1">Modify permission details</p>
                                </div>
                                <button
                                    onClick={closeAllModals}
                                    className="p-2 hover:bg-slate-100 rounded-full transition-colors"
                                >
                                    <svg className="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <div className="space-y-4">
                                <div>
                                    <label className="block text-sm font-semibold text-slate-700 mb-2">Permission Name</label>
                                    <input
                                        type="text"
                                        name="name"
                                        value={formData.name}
                                        onChange={handleInputChange}
                                        placeholder="e.g., custom-advanced-reporting"
                                        className="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    />
                                    {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-semibold text-slate-700 mb-2">Description</label>
                                    <textarea
                                        name="description"
                                        value={formData.description}
                                        onChange={handleInputChange}
                                        placeholder="Enter permission description (optional)"
                                        rows={3}
                                        className="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                                    />
                                    {errors.description && <p className="mt-1 text-sm text-red-600">{errors.description}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-semibold text-slate-700 mb-2">Category</label>
                                    <select
                                        name="category"
                                        value={formData.category}
                                        onChange={handleInputChange}
                                        className="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    >
                                        {categories.map(cat => (
                                            <option key={cat} value={cat}>{categoryLabels[cat] || cat}</option>
                                        ))}
                                    </select>
                                    {errors.category && <p className="mt-1 text-sm text-red-600">{errors.category}</p>}
                                </div>
                            </div>

                            <div className="flex justify-end space-x-4 pt-6 border-t">
                                <button
                                    onClick={closeAllModals}
                                    disabled={isLoading}
                                    className="px-6 py-3 text-slate-700 bg-slate-100 hover:bg-slate-200 font-semibold rounded-xl transition-all duration-200 disabled:opacity-50"
                                >
                                    Cancel
                                </button>
                                <button
                                    onClick={handleUpdate}
                                    disabled={isLoading || !formData.name.trim()}
                                    className="px-6 py-3 bg-gradient-to-r from-amber-500 to-amber-600 text-white font-semibold rounded-xl hover:from-amber-600 hover:to-amber-700 transform hover:scale-105 transition-all duration-200 disabled:opacity-50 disabled:transform-none"
                                >
                                    {isLoading ? (
                                        <div className="flex items-center">
                                            <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-white mr-2"></div>
                                            Updating...
                                        </div>
                                    ) : (
                                        'Update Permission'
                                    )}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* View Permission Modal */}
            {isViewModalOpen && currentPermission && (
                <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50 p-4">
                    <div className="bg-white rounded-2xl shadow-2xl max-w-2xl w-full">
                        <div className="p-6">
                            <div className="flex items-center justify-between mb-6">
                                <div>
                                    <h3 className="text-2xl font-bold text-slate-800">Permission Details: {currentPermission.name}</h3>
                                    <div className="flex items-center mt-2 space-x-3">
                                        <span className={`inline-flex px-3 py-1 text-sm font-medium rounded-full border ${getPermissionBadgeColor(currentPermission)}`}>
                                            {currentPermission.is_core ? 'Core Permission' : 'Dynamic Permission'}
                                        </span>
                                        <span className={`inline-flex px-2 py-1 text-xs font-medium rounded-full ${getCategoryBadgeColor(currentPermission.category)}`}>
                                            {categoryLabels[currentPermission.category] || currentPermission.category}
                                        </span>
                                        <span className="text-slate-500">
                                            {currentPermission.roles_count} roles
                                        </span>
                                    </div>
                                </div>
                                <button
                                    onClick={closeAllModals}
                                    className="p-2 hover:bg-slate-100 rounded-full transition-colors"
                                >
                                    <svg className="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <div className="space-y-6">
                                <div className="bg-slate-50 rounded-lg p-4">
                                    <h4 className="text-lg font-semibold text-slate-800 mb-3">Permission Information</h4>
                                    <div className="space-y-3">
                                        <div>
                                            <label className="block text-sm font-medium text-slate-600">Name</label>
                                            <p className="text-slate-900 font-medium">{currentPermission.name}</p>
                                        </div>
                                        {currentPermission.description && (
                                            <div>
                                                <label className="block text-sm font-medium text-slate-600">Description</label>
                                                <p className="text-slate-900">{currentPermission.description}</p>
                                            </div>
                                        )}
                                        <div>
                                            <label className="block text-sm font-medium text-slate-600">Category</label>
                                            <p className="text-slate-900">{categoryLabels[currentPermission.category] || currentPermission.category}</p>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-slate-600">Created</label>
                                            <p className="text-slate-900">{new Date(currentPermission.created_at).toLocaleDateString()}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div className="flex justify-end space-x-4 pt-6 border-t">
                                <button
                                    onClick={closeAllModals}
                                    className="px-6 py-3 text-slate-700 bg-slate-100 hover:bg-slate-200 font-semibold rounded-xl transition-all duration-200"
                                >
                                    Close
                                </button>
                                {!currentPermission.is_core && (
                                    <button
                                        onClick={() => {
                                            closeAllModals();
                                            openEditModal(currentPermission);
                                        }}
                                        className="px-6 py-3 bg-gradient-to-r from-amber-500 to-amber-600 text-white font-semibold rounded-xl hover:from-amber-600 hover:to-amber-700 transform hover:scale-105 transition-all duration-200"
                                    >
                                        Edit Permission
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Delete Confirmation Modal */}
            {isDeleteModalOpen && currentPermission && (
                <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50 p-4">
                    <div className="bg-white rounded-2xl shadow-2xl max-w-md w-full">
                        <div className="p-6">
                            <div className="flex items-center mb-4">
                                <div className="flex-shrink-0">
                                    <svg className="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div className="ml-4">
                                    <h3 className="text-lg font-semibold text-slate-900">Delete Permission</h3>
                                    <p className="text-sm text-slate-600 mt-1">This action cannot be undone</p>
                                </div>
                            </div>

                            <div className="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                                <p className="text-red-800">
                                    Are you sure you want to delete the permission <strong>"{currentPermission.name}"</strong>? 
                                    {currentPermission.roles_count > 0 && (
                                        <span className="block mt-2 text-sm">
                                            This permission is currently assigned to {currentPermission.roles_count} role(s).
                                        </span>
                                    )}
                                </p>
                            </div>

                            <div className="flex justify-end space-x-3">
                                <button
                                    onClick={closeAllModals}
                                    disabled={isLoading}
                                    className="px-4 py-2 text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200 transition-colors disabled:opacity-50"
                                >
                                    Cancel
                                </button>
                                <button
                                    onClick={handleDelete}
                                    disabled={isLoading}
                                    className="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors disabled:opacity-50"
                                >
                                    {isLoading ? 'Deleting...' : 'Delete Permission'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Bulk Create Modal */}
            {isBulkCreateModalOpen && (
                <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50 p-4">
                    <div className="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                        <div className="p-6">
                            <div className="flex items-center justify-between mb-6">
                                <div>
                                    <h3 className="text-2xl font-bold text-slate-800">Bulk Create Permissions</h3>
                                    <p className="text-slate-600 mt-1">Create multiple permissions at once</p>
                                </div>
                                <button
                                    onClick={closeAllModals}
                                    className="p-2 hover:bg-slate-100 rounded-full transition-colors"
                                >
                                    <svg className="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <div className="space-y-4 max-h-96 overflow-y-auto">
                                {bulkPermissions.map((permission, index) => (
                                    <div key={index} className="grid grid-cols-12 gap-4 p-4 bg-slate-50 rounded-lg">
                                        <div className="col-span-4">
                                            <input
                                                type="text"
                                                value={permission.name}
                                                onChange={(e) => updateBulkPermission(index, 'name', e.target.value)}
                                                placeholder="Permission name"
                                                className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                            />
                                        </div>
                                        <div className="col-span-4">
                                            <input
                                                type="text"
                                                value={permission.description}
                                                onChange={(e) => updateBulkPermission(index, 'description', e.target.value)}
                                                placeholder="Description (optional)"
                                                className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                            />
                                        </div>
                                        <div className="col-span-3">
                                            <select
                                                value={permission.category}
                                                onChange={(e) => updateBulkPermission(index, 'category', e.target.value)}
                                                className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                            >
                                                {categories.map(cat => (
                                                    <option key={cat} value={cat}>{categoryLabels[cat] || cat}</option>
                                                ))}
                                            </select>
                                        </div>
                                        <div className="col-span-1 flex justify-center">
                                            {bulkPermissions.length > 1 && (
                                                <button
                                                    onClick={() => removeBulkPermission(index)}
                                                    className="p-2 text-red-500 hover:bg-red-100 rounded-full transition-colors"
                                                >
                                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>

                            <div className="flex justify-between items-center pt-4">
                                <button
                                    onClick={addBulkPermission}
                                    className="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white font-medium rounded-lg hover:from-blue-600 hover:to-blue-700 transition-all duration-200"
                                >
                                    <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg>
                                    Add Another
                                </button>
                                
                                <div className="flex space-x-4">
                                    <button
                                        onClick={closeAllModals}
                                        disabled={isLoading}
                                        className="px-6 py-3 text-slate-700 bg-slate-100 hover:bg-slate-200 font-semibold rounded-xl transition-all duration-200 disabled:opacity-50"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        onClick={handleBulkCreate}
                                        disabled={isLoading}
                                        className="px-6 py-3 bg-gradient-to-r from-purple-500 to-purple-600 text-white font-semibold rounded-xl hover:from-purple-600 hover:to-purple-700 transform hover:scale-105 transition-all duration-200 disabled:opacity-50 disabled:transform-none"
                                    >
                                        {isLoading ? (
                                            <div className="flex items-center">
                                                <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-white mr-2"></div>
                                                Creating...
                                            </div>
                                        ) : (
                                            `Create ${bulkPermissions.filter(p => p.name.trim()).length} Permissions`
                                        )}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
};

export default DynamicPermissions;