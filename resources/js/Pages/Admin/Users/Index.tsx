import React, { useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { toast } from 'react-hot-toast';

interface Role {
    name: string;
}

interface User {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    code: string;
    schools: string | null;
    programs: string | null;
    roles: Role[];
    password?: string;
}

interface PaginationLinks {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedUsers {
    data: User[];
    links: PaginationLinks[];
    total: number;
    per_page: number;
    current_page: number;
}

const Users = () => {
    const { users, perPage, search } = usePage().props as { users: PaginatedUsers; perPage: number; search: string };

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [modalType, setModalType] = useState<'create' | 'edit' | 'delete' | ''>('');
    const [currentUser, setCurrentUser] = useState<User | null>(null);
    const [itemsPerPage, setItemsPerPage] = useState(perPage);
    const [searchQuery, setSearchQuery] = useState(search);
    const [isLoading, setIsLoading] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const handleOpenModal = (type: 'create' | 'edit' | 'delete', user: User | null = null) => {
        setModalType(type);
        
        if (type === 'create') {
            setCurrentUser({ 
                id: 0, 
                first_name: '', 
                last_name: '', 
                email: '', 
                phone: '', 
                code: '', 
                schools: '', 
                programs: '', 
                roles: [{ name: 'Student' }], // Default to Student role
                password: '' 
            });
        } else if (user) {
            // Ensure user has roles array with proper structure
            const userWithRoles = {
                ...user,
                roles: user.roles && user.roles.length > 0 ? user.roles : [{ name: 'Student' }]
            };
            console.log('Opening modal with user:', userWithRoles);
            setCurrentUser(userWithRoles);
        }
        
        setErrors({});
        setIsModalOpen(true);
    };

    const handleCloseModal = () => {
        setIsModalOpen(false);
        setModalType('');
        setCurrentUser(null);
        setErrors({});
        setIsLoading(false);
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        // FIXED: Changed from /users to /admin/users
        router.get('/admin/users', { search: searchQuery, per_page: itemsPerPage }, { preserveState: true });
    };

    const handlePerPageChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        const newPerPage = parseInt(e.target.value);
        setItemsPerPage(newPerPage);
        // FIXED: Changed from /users to /admin/users
        router.get('/admin/users', { search: searchQuery, per_page: newPerPage }, { preserveState: true });
    };

    const handlePageChange = (url: string | null) => {
        if (url) {
            router.get(url, {}, { preserveState: true });
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setIsLoading(true);
        setErrors({});

        if (!currentUser) {
            setIsLoading(false);
            return;
        }

        // Prepare payload with Spatie roles
        const payload = {
            first_name: currentUser.first_name,
            last_name: currentUser.last_name,
            email: currentUser.email,
            phone: currentUser.phone,
            code: currentUser.code,
            schools: currentUser.schools || '',
            programs: currentUser.programs || '',
            roles: currentUser.roles.map((role) => role.name).filter(name => name !== ''), // Array of role names
            ...(modalType === 'create' && { password: currentUser.password })
        };

        // Debug logging
        console.log('Submitting payload:', payload);
        console.log('Current user roles:', currentUser.roles);

        if (modalType === 'create') {
            // FIXED: Changed from /users to /admin/users
            router.post('/admin/users', payload, {
                onSuccess: () => {
                    toast.success('User created successfully!');
                    handleCloseModal();
                },
                onError: (errors) => {
                    console.error('Error creating user:', errors);
                    setErrors(errors);
                    toast.error('Failed to create user. Please check the form.');
                },
                onFinish: () => setIsLoading(false)
            });
        } else if (modalType === 'edit') {
            // FIXED: Changed from /users to /admin/users
            router.put(`/admin/users/${currentUser.id}`, payload, {
                onSuccess: () => {
                    toast.success('User updated successfully!');
                    handleCloseModal();
                },
                onError: (errors) => {
                    console.error('Error updating user:', errors);
                    setErrors(errors);
                    toast.error('Failed to update user. Please check the form.');
                },
                onFinish: () => setIsLoading(false)
            });
        } else if (modalType === 'delete') {
            // FIXED: Changed from /users to /admin/users
            router.delete(`/admin/users/${currentUser.id}`, {
                onSuccess: () => {
                    toast.success('User deleted successfully!');
                    handleCloseModal();
                },
                onError: (errors) => {
                    console.error('Error deleting user:', errors);
                    toast.error('Failed to delete user.');
                },
                onFinish: () => setIsLoading(false)
            });
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="Users" />
                <div className="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50 py-8">
                    {/* Header Section */}
                    <div className="">
                        <div className="bg-white rounded-2xl shadow-xl border p-8">
                            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h1 className="text-4xl font-bold text-slate-800 mb-2">User Management</h1>
                                    <p className="text-slate-600 text-lg">Manage system users and their permissions</p>
                                </div>
                                <button
                                    onClick={() => handleOpenModal('create')}
                                    className="inline-flex items-center px-6 py-3 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white font-semibold rounded-xl shadow-lg hover:from-emerald-600 hover:to-emerald-700 transform hover:scale-105 transition-all duration-200 mt-6 sm:mt-0"
                                >
                                    <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg>
                                    Add User
                                </button>
                            </div>
                        </div>
                    </div>

                    {/* Controls Section */}
                    <div className="bg-white rounded-2xl shadow-xl border border-slate-200 p-6">
                        <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
                            {/* Search */}
                            <form onSubmit={handleSearch} className="flex items-center space-x-3">
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
                                        placeholder="Search users..."
                                        className="block w-full pl-10 pr-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    />
                                </div>
                                <button
                                    type="submit"
                                    className="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white font-medium rounded-lg hover:from-blue-600 hover:to-blue-700 transition-all duration-200"
                                >
                                    Search
                                </button>
                            </form>

                            {/* Items per page */}
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
                                    <option value={5}>5</option>
                                    <option value={10}>10</option>
                                    <option value={15}>15</option>
                                    <option value={20}>20</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {/* Users Table */}
                    <div className="bg-white rounded-2xl shadow-xl border border-slate-200">
                        <div className="">
                            <table className="w-full">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">ID</th>
                                        <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Code</th>
                                        <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Name</th>
                                        <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Email</th>
                                        <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Phone</th>
                                        <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Schools</th>
                                        <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Programs</th>
                                        <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Role</th>
                                        <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-200">
                                    {users.data.length ? (
                                        users.data.map((user, index) => (
                                            <tr key={user.id} className={`hover:bg-slate-50 transition-colors duration-150 ${index % 2 === 0 ? 'bg-white' : 'bg-slate-25'}`}>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="flex items-center">
                                                        <div className="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                                            <span className="text-blue-600 font-semibold text-sm">{user.id}</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className="inline-flex px-3 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">
                                                        {user.code}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="text-sm font-medium text-slate-900">{user.first_name} {user.last_name}</div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="text-sm text-slate-900">{user.email}</div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="text-sm text-slate-900">{user.phone}</div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="text-sm text-slate-900">{user.schools || 'N/A'}</div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="text-sm text-slate-900">{user.programs || 'N/A'}</div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className="inline-flex px-3 py-1 text-xs font-medium bg-purple-100 text-purple-800 rounded-full">
                                                        {user.roles.map((role) => role.name).join(', ')}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="flex space-x-2">
                                                        <button
                                                            onClick={() => handleOpenModal('edit', user)}
                                                            className="inline-flex items-center px-3 py-2 bg-gradient-to-r from-amber-500 to-amber-600 text-white text-sm font-medium rounded-lg hover:from-amber-600 hover:to-amber-700 transform hover:scale-105 transition-all duration-200"
                                                        >
                                                            <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                            </svg>
                                                            Edit
                                                        </button>
                                                        <button
                                                            onClick={() => handleOpenModal('delete', user)}
                                                            className="inline-flex items-center px-3 py-2 bg-gradient-to-r from-red-500 to-red-600 text-white text-sm font-medium rounded-lg hover:from-red-600 hover:to-red-700 transform hover:scale-105 transition-all duration-200"
                                                        >
                                                            <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                            Delete
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={9} className="px-6 py-12 text-center">
                                                <div className="flex flex-col items-center">
                                                    <svg className="w-16 h-16 text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                                    </svg>
                                                    <h3 className="text-lg font-medium text-slate-900 mb-1">No users found</h3>
                                                    <p className="text-slate-500">Start by adding your first user</p>
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        {users.data.length > 0 && (
                            <div className="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                                <p className="text-sm text-slate-600">
                                    Showing {users.data.length} of {users.total} users
                                </p>
                                <div className="flex space-x-2">
                                    {users.links.map((link, index) => (
                                        <button
                                            key={index}
                                            onClick={() => handlePageChange(link.url)}
                                            className={`px-3 py-2 text-sm rounded-lg transition-all duration-200 ${
                                                link.active
                                                    ? 'bg-blue-500 text-white shadow-md'
                                                    : link.url
                                                    ? 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-300'
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

            {/* Modal */}
            {isModalOpen && (
                <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50 p-4">
                    <div className="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                        <div className="p-8">
                            <div className="flex items-center justify-between mb-6">
                                <div>
                                    <h2 className="text-3xl font-bold text-slate-800">
                                        {modalType === 'create' && 'Add New User'}
                                        {modalType === 'edit' && 'Edit User'}
                                        {modalType === 'delete' && 'Delete User'}
                                    </h2>
                                    <p className="text-slate-600 mt-1">
                                        {modalType === 'create' && 'Create a new user account'}
                                        {modalType === 'edit' && 'Update user information'}
                                        {modalType === 'delete' && 'This action cannot be undone'}
                                    </p>
                                </div>
                                <button
                                    onClick={handleCloseModal}
                                    className="p-2 hover:bg-slate-100 rounded-full transition-colors duration-200"
                                >
                                    <svg className="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            {modalType !== 'delete' ? (
                                <form onSubmit={handleSubmit} className="space-y-6">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label className="block text-sm font-semibold text-slate-700 mb-2">First Name</label>
                                            <input
                                                type="text"
                                                value={currentUser?.first_name || ''}
                                                onChange={(e) =>
                                                    setCurrentUser((prev) => ({ ...prev!, first_name: e.target.value }))
                                                }
                                                className="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                                required
                                            />
                                            {errors.first_name && <p className="mt-1 text-sm text-red-600">{errors.first_name}</p>}
                                        </div>

                                        <div>
                                            <label className="block text-sm font-semibold text-slate-700 mb-2">Last Name</label>
                                            <input
                                                type="text"
                                                value={currentUser?.last_name || ''}
                                                onChange={(e) =>
                                                    setCurrentUser((prev) => ({ ...prev!, last_name: e.target.value }))
                                                }
                                                className="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                                required
                                            />
                                            {errors.last_name && <p className="mt-1 text-sm text-red-600">{errors.last_name}</p>}
                                        </div>
                                    </div>

                                    <div>
                                        <label className="block text-sm font-semibold text-slate-700 mb-2">Email</label>
                                        <input
                                            type="email"
                                            value={currentUser?.email || ''}
                                            onChange={(e) =>
                                                setCurrentUser({ ...currentUser!, email: e.target.value })
                                            }
                                            className="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                            required
                                        />
                                        {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email}</p>}
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label className="block text-sm font-semibold text-slate-700 mb-2">Phone</label>
                                            <input
                                                type="text"
                                                value={currentUser?.phone || ''}
                                                onChange={(e) =>
                                                    setCurrentUser({ ...currentUser!, phone: e.target.value })
                                                }
                                                className="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                                required
                                            />
                                            {errors.phone && <p className="mt-1 text-sm text-red-600">{errors.phone}</p>}
                                        </div>

                                        <div>
                                            <label className="block text-sm font-semibold text-slate-700 mb-2">Code</label>
                                            <input
                                                type="text"
                                                value={currentUser?.code || ''}
                                                onChange={(e) =>
                                                    setCurrentUser({ ...currentUser!, code: e.target.value })
                                                }
                                                className="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                                required
                                            />
                                            {errors.code && <p className="mt-1 text-sm text-red-600">{errors.code}</p>}
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label className="block text-sm font-semibold text-slate-700 mb-2">Schools</label>
                                            <input
                                                type="text"
                                                value={currentUser?.schools || ''}
                                                onChange={(e) =>
                                                    setCurrentUser({ ...currentUser!, schools: e.target.value })
                                                }
                                                className="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                                placeholder="Optional"
                                            />
                                            {errors.schools && <p className="mt-1 text-sm text-red-600">{errors.schools}</p>}
                                        </div>

                                        <div>
                                            <label className="block text-sm font-semibold text-slate-700 mb-2">Programs</label>
                                            <input
                                                type="text"
                                                value={currentUser?.programs || ''}
                                                onChange={(e) =>
                                                    setCurrentUser({ ...currentUser!, programs: e.target.value })
                                                }
                                                className="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                                placeholder="Optional"
                                            />
                                            {errors.programs && <p className="mt-1 text-sm text-red-600">{errors.programs}</p>}
                                        </div>
                                    </div>

                                    <div>
                                        <label className="block text-sm font-semibold text-slate-700 mb-2">Role</label>
                                        <select
                                            value={currentUser?.roles && currentUser.roles.length > 0 ? currentUser.roles[0].name : ''}
                                            onChange={(e) => {
                                                console.log('Role changed to:', e.target.value);
                                                setCurrentUser((prev) => ({
                                                    ...prev!,
                                                    roles: [{ name: e.target.value }],
                                                }))
                                            }}
                                            className="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                            required
                                        >
                                            <option value="">Select a role</option>
                                            <option value="Student">Student</option>
                                            <option value="Admin">Admin</option>
                                            <option value="Exam office">Exam Officer</option>
                                            <option value="Lecturer">Lecturer</option>
                                            <option value="Faculty Admin">Faculty Admin</option>
                                        </select>
                                        {errors.roles && <p className="mt-1 text-sm text-red-600">{errors.roles}</p>}
                                        
                                        {/* Debug info - remove in production */}
                                        {process.env.NODE_ENV === 'development' && (
                                            <div className="mt-2 text-xs text-gray-500">
                                                Debug: Current roles: {JSON.stringify(currentUser?.roles)}
                                            </div>
                                        )}
                                    </div>

                                    {modalType === 'create' && (
                                        <div>
                                            <label className="block text-sm font-semibold text-slate-700 mb-2">Password</label>
                                            <input
                                                type="password"
                                                value={currentUser?.password || ''}
                                                onChange={(e) =>
                                                    setCurrentUser((prev) => ({ ...prev!, password: e.target.value }))
                                                }
                                                className="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                                required
                                                placeholder="Enter a secure password"
                                            />
                                            {errors.password && <p className="mt-1 text-sm text-red-600">{errors.password}</p>}
                                        </div>
                                    )}

                                    <div className="flex justify-end space-x-4 pt-6">
                                        <button
                                            type="button"
                                            onClick={handleCloseModal}
                                            className="px-6 py-3 text-slate-700 bg-slate-100 hover:bg-slate-200 font-semibold rounded-xl transition-all duration-200"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            type="submit"
                                            disabled={isLoading}
                                            className="px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white font-semibold rounded-xl hover:from-blue-600 hover:to-blue-700 transform hover:scale-105 transition-all duration-200 shadow-lg disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
                                        >
                                            {isLoading ? (
                                                <div className="flex items-center">
                                                    <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-white mr-2"></div>
                                                    Processing...
                                                </div>
                                            ) : (
                                                modalType === 'create' ? 'Create User' : 'Update User'
                                            )}
                                        </button>
                                    </div>
                                </form>
                            ) : (
                                <div>
                                    <div className="bg-gradient-to-r from-red-50 to-red-100 border-l-4 border-red-400 p-6 rounded-lg mb-6">
                                        <div className="flex items-center">
                                            <svg className="w-6 h-6 text-red-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <div>
                                                <h3 className="text-red-800 font-semibold">Confirm Deletion</h3>
                                                <p className="text-red-700 mt-1">
                                                    Are you sure you want to delete user <strong>{currentUser?.first_name} {currentUser?.last_name}</strong>?
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div className="flex justify-end space-x-4">
                                        <button
                                            type="button"
                                            onClick={handleCloseModal}
                                            className="px-6 py-3 text-slate-700 bg-slate-100 hover:bg-slate-200 font-semibold rounded-xl transition-all duration-200"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            onClick={handleSubmit}
                                            disabled={isLoading}
                                            className="px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white font-semibold rounded-xl hover:from-red-600 hover:to-red-700 transform hover:scale-105 transition-all duration-200 shadow-lg disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
                                        >
                                            {isLoading ? (
                                                <div className="flex items-center">
                                                    <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-white mr-2"></div>
                                                    Deleting...
                                                </div>
                                            ) : (
                                                'Delete User'
                                            )}
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
};

export default Users;