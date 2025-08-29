import React, { useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { toast } from 'react-hot-toast';

interface Role {
    id: number;
    name: string;
    description: string | null;
    is_core: boolean;
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

const UserRoles = () => {
    const { users, roles, selectedRole, roleCounts, perPage, search } = usePage().props as {
        users: PaginatedUsers;
        roles: Role[];
        selectedRole: string;
        roleCounts: Record<string, number>;
        perPage: number;
        search: string;
    };

    const [searchQuery, setSearchQuery] = useState(search);
    const [itemsPerPage, setItemsPerPage] = useState(perPage);
    const [selectedUsers, setSelectedUsers] = useState<number[]>([]);
    
    // Modal states
    const [isRoleModalOpen, setIsRoleModalOpen] = useState(false);
    const [isBulkModalOpen, setIsBulkModalOpen] = useState(false);
    const [isRemoveModalOpen, setIsRemoveModalOpen] = useState(false);
    
    // Current user/role being operated on
    const [currentUser, setCurrentUser] = useState<User | null>(null);
    const [selectedRoleForUser, setSelectedRoleForUser] = useState('');
    const [bulkRole, setBulkRole] = useState('');
    const [bulkAction, setBulkAction] = useState<'replace' | 'add'>('replace');
    
    const [isLoading, setIsLoading] = useState(false);

    const handleRoleFilter = (roleName: string) => {
        router.get('/admin/users/roles', {
            role: roleName,
            search: searchQuery,
            per_page: itemsPerPage
        }, { preserveState: true });
    };

    const handleSearch = () => {
        router.get('/admin/users/roles', {
            role: selectedRole,
            search: searchQuery,
            per_page: itemsPerPage
        }, { preserveState: true });
    };

    const handlePerPageChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        const newPerPage = parseInt(e.target.value);
        setItemsPerPage(newPerPage);
        router.get('/admin/users/roles', {
            role: selectedRole,
            search: searchQuery,
            per_page: newPerPage
        }, { preserveState: true });
    };

    const handlePageChange = (url: string | null) => {
        if (url) {
            router.get(url, {}, { preserveState: true });
        }
    };

    const handleUserSelection = (userId: number) => {
        setSelectedUsers(prev => 
            prev.includes(userId) 
                ? prev.filter(id => id !== userId)
                : [...prev, userId]
        );
    };

    const handleSelectAll = () => {
        if (selectedUsers.length === users.data.length) {
            setSelectedUsers([]);
        } else {
            setSelectedUsers(users.data.map(user => user.id));
        }
    };

    const closeAllModals = () => {
        setIsRoleModalOpen(false);
        setIsBulkModalOpen(false);
        setIsRemoveModalOpen(false);
        setCurrentUser(null);
        setSelectedRoleForUser('');
        setBulkRole('');
    };

    // Individual role assignment
    const openRoleModal = (user: User) => {
        setCurrentUser(user);
        setSelectedRoleForUser(user.roles[0]?.name || '');
        setIsRoleModalOpen(true);
    };

    const updateUserRole = () => {
        if (!currentUser || !selectedRoleForUser) return;

        setIsLoading(true);
        router.put(`/admin/users/${currentUser.id}/role`, {
            role: selectedRoleForUser
        }, {
            onSuccess: () => {
                toast.success('User role updated successfully!');
                closeAllModals();
            },
            onError: (errors) => {
                const errorMessage = errors.error || 'Failed to update user role.';
                toast.error(errorMessage);
            },
            onFinish: () => setIsLoading(false)
        });
    };

    // Remove all roles from user
    const openRemoveModal = (user: User) => {
        setCurrentUser(user);
        setIsRemoveModalOpen(true);
    };

    const removeUserRole = () => {
        if (!currentUser) return;
        
        setIsLoading(true);
        router.delete(`/admin/users/${currentUser.id}/role`, {
            onSuccess: () => {
                toast.success('User roles removed successfully!');
                closeAllModals();
            },
            onError: (errors) => {
                const errorMessage = errors.error || 'Failed to remove user roles.';
                toast.error(errorMessage);
            },
            onFinish: () => setIsLoading(false)
        });
    };

    // Bulk operations
    const openBulkModal = () => {
        if (selectedUsers.length === 0) {
            toast.error('Please select users first.');
            return;
        }
        setIsBulkModalOpen(true);
    };

    const bulkAssignRole = () => {
        if (!bulkRole || selectedUsers.length === 0) return;

        setIsLoading(true);
        router.post('/admin/users/bulk-assign-role', {
            user_ids: selectedUsers,
            role: bulkRole,
            action: bulkAction
        }, {
            onSuccess: () => {
                const actionText = bulkAction === 'replace' ? 'assigned to' : 'added to';
                toast.success(`Role ${actionText} ${selectedUsers.length} users successfully!`);
                setSelectedUsers([]);
                closeAllModals();
            },
            onError: (errors) => {
                const errorMessage = errors.error || 'Failed to assign roles to users.';
                toast.error(errorMessage);
            },
            onFinish: () => setIsLoading(false)
        });
    };

    const getRoleBadgeColor = (role: Role) => {
        if (role.is_core) {
            return 'bg-blue-100 text-blue-800 border-blue-200';
        }
        return 'bg-green-100 text-green-800 border-green-200';
    };

    const getRoleCountBadgeColor = (roleKey: string) => {
        const colors: Record<string, string> = {
            'total': 'bg-slate-500',
            'no_role': 'bg-red-500',
            'Admin': 'bg-purple-500',
            'Student': 'bg-emerald-500',
            'Lecturer': 'bg-blue-500',
            'Faculty Admin': 'bg-orange-500',
            'Exam Office': 'bg-indigo-500'
        };
        return colors[roleKey] || 'bg-gray-500';
    };

    return (
        <AuthenticatedLayout>
            <Head title="User Roles Management" />
            <div className="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50 py-8">
                {/* Header */}
                <div className="bg-white rounded-2xl shadow-xl border p-8 mb-6">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h1 className="text-4xl font-bold text-slate-800 mb-2">User Roles Management</h1>
                            <p className="text-slate-600 text-lg">Assign and manage user roles across the system</p>
                        </div>
                        {selectedUsers.length > 0 && (
                            <button
                                onClick={openBulkModal}
                                className="inline-flex items-center px-6 py-3 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white font-semibold rounded-xl shadow-lg hover:from-emerald-600 hover:to-emerald-700 transform hover:scale-105 transition-all duration-200 mt-6 sm:mt-0"
                            >
                                <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                Manage Roles ({selectedUsers.length})
                            </button>
                        )}
                    </div>
                </div>

                {/* Role Statistics */}
                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
                    <div 
                        className={`bg-white rounded-xl p-4 shadow-lg border cursor-pointer hover:scale-105 transition-transform ${selectedRole === 'all' ? 'ring-2 ring-blue-500' : ''}`}
                        onClick={() => handleRoleFilter('all')}
                    >
                        <div className="flex items-center">
                            <div className={`w-3 h-3 rounded-full ${getRoleCountBadgeColor('total')} mr-3`}></div>
                            <div>
                                <h3 className="font-semibold text-slate-700 text-sm">All Users</h3>
                                <p className="text-2xl font-bold text-blue-600">{roleCounts.total}</p>
                            </div>
                        </div>
                    </div>
                    
                    {roles.map(role => (
                        <div 
                            key={role.id}
                            className={`bg-white rounded-xl p-4 shadow-lg border cursor-pointer hover:scale-105 transition-transform ${selectedRole === role.name ? 'ring-2 ring-blue-500' : ''}`}
                            onClick={() => handleRoleFilter(role.name)}
                        >
                            <div className="flex items-center">
                                <div className={`w-3 h-3 rounded-full ${getRoleCountBadgeColor(role.name)} mr-3`}></div>
                                <div>
                                    <h3 className="font-semibold text-slate-700 text-sm">{role.name}</h3>
                                    <p className="text-2xl font-bold text-emerald-600">{roleCounts[role.name] || 0}</p>
                                </div>
                            </div>
                        </div>
                    ))}
                    
                    <div 
                        className={`bg-white rounded-xl p-4 shadow-lg border cursor-pointer hover:scale-105 transition-transform ${selectedRole === 'no_role' ? 'ring-2 ring-blue-500' : ''}`}
                        onClick={() => handleRoleFilter('no_role')}
                    >
                        <div className="flex items-center">
                            <div className={`w-3 h-3 rounded-full ${getRoleCountBadgeColor('no_role')} mr-3`}></div>
                            <div>
                                <h3 className="font-semibold text-slate-700 text-sm">No Role</h3>
                                <p className="text-2xl font-bold text-red-600">{roleCounts.no_role || 0}</p>
                            </div>
                        </div>
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
                                    placeholder="Search users by code, name, email..."
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
                            {selectedUsers.length > 0 && (
                                <div className="text-sm text-slate-600 bg-blue-50 px-3 py-2 rounded-lg">
                                    {selectedUsers.length} users selected
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
                                    <option value={15}>15</option>
                                    <option value={25}>25</option>
                                    <option value={50}>50</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Users Table */}
                <div className="bg-white rounded-2xl shadow-xl border">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="bg-slate-50">
                                <tr>
                                    <th className="px-6 py-4 text-left">
                                        <input
                                            type="checkbox"
                                            checked={selectedUsers.length === users.data.length && users.data.length > 0}
                                            onChange={handleSelectAll}
                                            className="rounded focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300"
                                        />
                                    </th>
                                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">User</th>
                                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Contact</th>
                                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Academic Info</th>
                                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Current Roles</th>
                                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200">
                                {users.data.length ? (
                                    users.data.map((user, index) => (
                                        <tr key={user.id} className={`hover:bg-slate-50 transition-colors ${index % 2 === 0 ? 'bg-white' : 'bg-slate-25'}`}>
                                            <td className="px-6 py-4">
                                                <input
                                                    type="checkbox"
                                                    checked={selectedUsers.includes(user.id)}
                                                    onChange={() => handleUserSelection(user.id)}
                                                    className="rounded focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300"
                                                />
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex items-center">
                                                    <div className="w-10 h-10 bg-gradient-to-br from-blue-400 to-purple-500 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                                                        {user.first_name[0]}{user.last_name[0]}
                                                    </div>
                                                    <div className="ml-4">
                                                        <div className="text-sm font-medium text-slate-900">
                                                            {user.first_name} {user.last_name}
                                                        </div>
                                                        <div className="text-sm text-slate-500">Code: {user.code}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="text-sm text-slate-900">{user.email}</div>
                                                <div className="text-sm text-slate-500">{user.phone}</div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="text-sm text-slate-900">{user.schools || 'N/A'}</div>
                                                <div className="text-sm text-slate-500">{user.programs || 'N/A'}</div>
                                            </td>
                                            <td className="px-6 py-4">
                                                {user.roles.length > 0 ? (
                                                    <div className="flex flex-wrap gap-1">
                                                        {user.roles.map(role => (
                                                            <span key={role.id} className={`inline-flex px-2 py-1 text-xs font-medium rounded-full border ${getRoleBadgeColor(role)}`}>
                                                                {role.name}
                                                            </span>
                                                        ))}
                                                    </div>
                                                ) : (
                                                    <span className="inline-flex px-3 py-1 text-xs font-medium bg-gray-100 text-gray-600 rounded-full">
                                                        No Role Assigned
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex space-x-2">
                                                    <button
                                                        onClick={() => openRoleModal(user)}
                                                        className="inline-flex items-center px-3 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white text-sm font-medium rounded-lg hover:from-blue-600 hover:to-blue-700 transform hover:scale-105 transition-all duration-200"
                                                    >
                                                        <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                        Assign Role
                                                    </button>
                                                    {user.roles.length > 0 && (
                                                        <button
                                                            onClick={() => openRemoveModal(user)}
                                                            className="inline-flex items-center px-3 py-2 bg-gradient-to-r from-red-500 to-red-600 text-white text-sm font-medium rounded-lg hover:from-red-600 hover:to-red-700 transform hover:scale-105 transition-all duration-200"
                                                        >
                                                            <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                            Remove Roles
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={6} className="px-6 py-12 text-center">
                                            <div className="flex flex-col items-center">
                                                <svg className="w-16 h-16 text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                                </svg>
                                                <h3 className="text-lg font-medium text-slate-900 mb-1">No users found</h3>
                                                <p className="text-slate-500">Try adjusting your search or filter criteria</p>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {users.data.length > 0 && (
                        <div className="px-6 py-4 bg-slate-50 border-t flex items-center justify-between">
                            <p className="text-sm text-slate-600">
                                Showing {users.data.length} of {users.total} users
                            </p>
                            <div className="flex space-x-2">
                                {users.links.map((link, index) => (
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

            {/* Individual Role Assignment Modal */}
            {isRoleModalOpen && currentUser && (
                <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50 p-4">
                    <div className="bg-white rounded-2xl shadow-2xl max-w-md w-full">
                        <div className="p-6">
                            <h3 className="text-lg font-semibold mb-4">
                                Assign Role to {currentUser.first_name} {currentUser.last_name}
                            </h3>
                            <select
                                value={selectedRoleForUser}
                                onChange={(e) => setSelectedRoleForUser(e.target.value)}
                                className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 mb-4"
                            >
                                <option value="">No Role</option>
                                {roles.map(role => (
                                    <option key={role.id} value={role.name}>{role.name}</option>
                                ))}
                            </select>
                            <div className="flex justify-end space-x-3">
                                <button
                                    onClick={closeAllModals}
                                    disabled={isLoading}
                                    className="px-4 py-2 text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200"
                                >
                                    Cancel
                                </button>
                                <button
                                    onClick={updateUserRole}
                                    disabled={isLoading}
                                    className="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600"
                                >
                                    {isLoading ? 'Updating...' : 'Update Role'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Bulk Assignment Modal */}
            {isBulkModalOpen && (
                <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50 p-4">
                    <div className="bg-white rounded-2xl shadow-2xl max-w-md w-full">
                        <div className="p-6">
                            <h3 className="text-lg font-semibold mb-4">
                                Manage Roles for {selectedUsers.length} Users
                            </h3>
                            
                            <div className="space-y-4">
                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-2">Select Role</label>
                                    <select
                                        value={bulkRole}
                                        onChange={(e) => setBulkRole(e.target.value)}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                    >
                                        <option value="">Select a role</option>
                                        {roles.map(role => (
                                            <option key={role.id} value={role.name}>{role.name}</option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-2">Action</label>
                                    <select
                                        value={bulkAction}
                                        onChange={(e) => setBulkAction(e.target.value as 'replace' | 'add')}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                    >
                                        <option value="replace">Replace all roles with selected role</option>
                                        <option value="add">Add role to existing roles</option>
                                    </select>
                                </div>
                            </div>

                            <div className="flex justify-end space-x-3 mt-6">
                                <button
                                    onClick={closeAllModals}
                                    disabled={isLoading}
                                    className="px-4 py-2 text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200"
                                >
                                    Cancel
                                </button>
                                <button
                                    onClick={bulkAssignRole}
                                    disabled={isLoading || !bulkRole}
                                    className="px-4 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 disabled:opacity-50"
                                >
                                    {isLoading ? 'Processing...' : 'Assign Role'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Remove Roles Confirmation Modal */}
            {isRemoveModalOpen && currentUser && (
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
                                    <h3 className="text-lg font-semibold text-slate-900">Remove All Roles</h3>
                                    <p className="text-sm text-slate-600 mt-1">This action cannot be undone</p>
                                </div>
                            </div>

                            <div className="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                                <p className="text-red-800">
                                    Are you sure you want to remove all roles from <strong>{currentUser.first_name} {currentUser.last_name}</strong>?
                                </p>
                                {currentUser.roles.length > 0 && (
                                    <div className="mt-2">
                                        <p className="text-sm text-red-700">Current roles:</p>
                                        <div className="flex flex-wrap gap-1 mt-1">
                                            {currentUser.roles.map(role => (
                                                <span key={role.id} className="inline-flex px-2 py-1 text-xs font-medium bg-red-100 text-red-700 rounded-full">
                                                    {role.name}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                )}
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
                                    onClick={removeUserRole}
                                    disabled={isLoading}
                                    className="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors disabled:opacity-50"
                                >
                                    {isLoading ? 'Removing...' : 'Remove All Roles'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
};

export default UserRoles;