import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { 
  User, 
  Search, 
  Plus, 
  Edit2, 
  Trash2, 
  Shield, 
  Mail,
  Phone,
  Calendar,
  UserCheck,
  UserX,
  X,
  Eye,
  EyeOff
} from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { toast } from 'react-hot-toast';

interface Role {
  id: number;
  name: string;
}

interface UserData {
  id: number;
  code: string;
  first_name: string;
  last_name: string;
  email: string;
  phone?: string;
  email_verified_at?: string;
  created_at: string;
  updated_at: string;
  roles: Role[];
  schools?: string[];
  programs?: string[];
}

interface PaginationLinks {
  url: string | null;
  label: string;
  active: boolean;
}

interface PaginatedUsers {
  data: UserData[];
  links: PaginationLinks[];
  total: number;
  per_page: number;
  current_page: number;
}

interface Props {
  users: PaginatedUsers;
  filters: {
    search: string;
    role: string;
    status: string;
    per_page: number;
  };
  roles: Role[];
  stats: {
    total: number;
    active: number;
    inactive: number;
    with_roles: number;
  };
}

export default function UsersIndex() {
  const { users, filters, roles, stats } = usePage().props as Props;

  const [searchQuery, setSearchQuery] = useState(filters.search);
  const [itemsPerPage, setItemsPerPage] = useState(filters.per_page);
  const [selectedFilter, setSelectedFilter] = useState('all');
  const [selectedRole, setSelectedRole] = useState(filters.role);
  const [selectedStatus, setSelectedStatus] = useState(filters.status);

  // Modal states
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
  const [isViewModalOpen, setIsViewModalOpen] = useState(false);
  
  // Current user being operated on
  const [currentUser, setCurrentUser] = useState<UserData | null>(null);
  
  // Form data
  const [formData, setFormData] = useState({
    code: '',
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
    roles: [] as number[]
  });
  
  const [selectedUsers, setSelectedUsers] = useState<number[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [showPassword, setShowPassword] = useState(false);

  const handleSearch = () => {
    router.get('/admin/users', {
      search: searchQuery,
      role: selectedRole,
      status: selectedStatus,
      per_page: itemsPerPage
    }, { preserveState: true });
  };

  const handleFilterChange = (newFilter: string) => {
    setSelectedFilter(newFilter);
    let status = '';
    if (newFilter === 'active') status = 'verified';
    if (newFilter === 'inactive') status = 'unverified';
    
    setSelectedStatus(status);
    router.get('/admin/users', {
      search: searchQuery,
      role: selectedRole,
      status: status,
      per_page: itemsPerPage
    }, { preserveState: true });
  };

  const handleRoleChange = (newRole: string) => {
    setSelectedRole(newRole);
    router.get('/admin/users', {
      search: searchQuery,
      role: newRole,
      status: selectedStatus,
      per_page: itemsPerPage
    }, { preserveState: true });
  };

  const handleStatusChange = (newStatus: string) => {
    setSelectedStatus(newStatus);
    router.get('/admin/users', {
      search: searchQuery,
      role: selectedRole,
      status: newStatus,
      per_page: itemsPerPage
    }, { preserveState: true });
  };

  const handlePerPageChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const newPerPage = parseInt(e.target.value);
    setItemsPerPage(newPerPage);
    router.get('/admin/users', {
      search: searchQuery,
      role: selectedRole,
      status: selectedStatus,
      per_page: newPerPage
    }, { preserveState: true });
  };

  const handlePageChange = (url: string | null) => {
    if (url) {
      router.get(url, {}, { preserveState: true });
    }
  };

  const resetForm = () => {
    setFormData({ 
      code: '',
      first_name: '',
      last_name: '',
      email: '',
      phone: '',
      password: '',
      password_confirmation: '',
      roles: []
    });
    setErrors({});
  };

  const closeAllModals = () => {
    setIsCreateModalOpen(false);
    setIsEditModalOpen(false);
    setIsDeleteModalOpen(false);
    setIsViewModalOpen(false);
    setCurrentUser(null);
    resetForm();
  };

  // Create Modal
  const openCreateModal = () => {
    resetForm();
    setIsCreateModalOpen(true);
  };

  const handleCreate = () => {
    setIsLoading(true);
    router.post('/admin/users', formData, {
      onSuccess: () => {
        toast.success('User created successfully!');
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
  const openEditModal = (user: UserData) => {
    setCurrentUser(user);
    setFormData({
      code: user.code,
      first_name: user.first_name,
      last_name: user.last_name,
      email: user.email,
      phone: user.phone || '',
      password: '',
      password_confirmation: '',
      roles: user.roles.map(role => role.id)
    });
    setIsEditModalOpen(true);
  };

  const handleUpdate = () => {
    if (!currentUser) return;
    
    setIsLoading(true);
    router.put(`/admin/users/${currentUser.id}`, formData, {
      onSuccess: () => {
        toast.success('User updated successfully!');
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
  const openDeleteModal = (user: UserData) => {
    setCurrentUser(user);
    setIsDeleteModalOpen(true);
  };

  const handleDelete = () => {
    if (!currentUser) return;
    
    setIsLoading(true);
    router.delete(`/admin/users/${currentUser.id}`, {
      onSuccess: () => {
        toast.success('User deleted successfully!');
        closeAllModals();
      },
      onError: (errors) => {
        const errorMessage = errors.error || 'Failed to delete user.';
        toast.error(errorMessage);
      },
      onFinish: () => setIsLoading(false)
    });
  };

  // View Modal
  const openViewModal = (user: UserData) => {
    setCurrentUser(user);
    setIsViewModalOpen(true);
  };

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
    if (errors[name]) {
      setErrors(prev => ({ ...prev, [name]: '' }));
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
      setSelectedUsers(users.data.map(u => u.id));
    }
  };

  const handleBulkDelete = () => {
    if (selectedUsers.length === 0) return;
    
    if (confirm(`Are you sure you want to delete ${selectedUsers.length} users?`)) {
      setIsLoading(true);
      router.post('/admin/users/bulk-delete', {
        user_ids: selectedUsers
      }, {
        onSuccess: () => {
          toast.success(`${selectedUsers.length} users deleted successfully!`);
          setSelectedUsers([]);
        },
        onError: (errors) => {
          const errorMessage = errors.error || 'Failed to delete users.';
          toast.error(errorMessage);
        },
        onFinish: () => setIsLoading(false)
      });
    }
  };

  const getUserBadgeColor = (user: UserData) => {
    if (user.email_verified_at) {
      return 'bg-green-100 text-green-800 border-green-200';
    }
    return 'bg-red-100 text-red-800 border-red-200';
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
  };

  return (
    <AuthenticatedLayout>
      <Head title="Users Management" />
      <div className="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50 py-8">
        {/* Header */}
        <div className="bg-white rounded-2xl shadow-xl border p-8 mb-6">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h1 className="text-4xl font-bold text-slate-800 mb-2">Users Management</h1>
              <p className="text-slate-600 text-lg">Manage system users and their access permissions</p>
            </div>
            <div className="flex space-x-3 mt-6 sm:mt-0">
              {selectedUsers.length > 0 && (
                <button
                  onClick={handleBulkDelete}
                  className="inline-flex items-center px-4 py-2 bg-gradient-to-r from-red-500 to-red-600 text-white font-medium rounded-xl shadow-lg hover:from-red-600 hover:to-red-700 transform hover:scale-105 transition-all duration-200"
                >
                  <Trash2 className="w-5 h-5 mr-2" />
                  Delete Selected ({selectedUsers.length})
                </button>
              )}
              <button
                onClick={openCreateModal}
                className="inline-flex items-center px-6 py-3 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white font-semibold rounded-xl shadow-lg hover:from-emerald-600 hover:to-emerald-700 transform hover:scale-105 transition-all duration-200"
              >
                <Plus className="w-5 h-5 mr-2" />
                Create User
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
            <h3 className="text-sm font-semibold text-slate-600 uppercase tracking-wider">Total Users</h3>
            <p className="text-3xl font-bold text-slate-800">{stats.total}</p>
          </div>

          <div 
            className={`bg-white rounded-xl p-6 shadow-lg border cursor-pointer hover:scale-105 transition-transform ${selectedFilter === 'active' ? 'ring-2 ring-blue-500' : ''}`}
            onClick={() => handleFilterChange('active')}
          >
            <h3 className="text-sm font-semibold text-slate-600 uppercase tracking-wider">Active Users</h3>
            <p className="text-3xl font-bold text-green-600">{stats.active}</p>
          </div>

          <div 
            className={`bg-white rounded-xl p-6 shadow-lg border cursor-pointer hover:scale-105 transition-transform ${selectedFilter === 'inactive' ? 'ring-2 ring-blue-500' : ''}`}
            onClick={() => handleFilterChange('inactive')}
          >
            <h3 className="text-sm font-semibold text-slate-600 uppercase tracking-wider">Inactive Users</h3>
            <p className="text-3xl font-bold text-red-600">{stats.inactive}</p>
          </div>

          <div className="bg-white rounded-xl p-6 shadow-lg border">
            <h3 className="text-sm font-semibold text-slate-600 uppercase tracking-wider mb-2">Role Filter</h3>
            <select
              value={selectedRole}
              onChange={(e) => handleRoleChange(e.target.value)}
              className="w-full border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
            >
              <option value="">All Roles</option>
              {roles.map(role => (
                <option key={role.id} value={role.name}>{role.name}</option>
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
                  <Search className="h-5 w-5 text-slate-400" />
                </div>
                <input
                  type="text"
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  onKeyPress={(e) => e.key === 'Enter' && handleSearch()}
                  placeholder="Search users..."
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
                <div className="text-sm text-slate-600">
                  {selectedUsers.length} selected
                </div>
              )}
              
              <div className="flex items-center space-x-2">
                <label htmlFor="status" className="text-sm font-medium text-slate-700">
                  Status:
                </label>
                <select
                  id="status"
                  value={selectedStatus}
                  onChange={(e) => handleStatusChange(e.target.value)}
                  className="border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
                  <option value="">All Status</option>
                  <option value="verified">Verified</option>
                  <option value="unverified">Unverified</option>
                </select>
              </div>
              
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
                  <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Roles</th>
                  <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Status</th>
                  <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Created</th>
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
                          <div className="flex-shrink-0 h-10 w-10">
                            <div className="h-10 w-10 rounded-full bg-gradient-to-r from-blue-400 to-blue-600 flex items-center justify-center">
                              <User className="h-6 w-6 text-white" />
                            </div>
                          </div>
                          <div className="ml-4">
                            <div className="text-sm font-medium text-slate-900">{user.first_name} {user.last_name}</div>
                            <div className="text-sm text-slate-500">Code: {user.code}</div>
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="text-sm text-slate-900 flex items-center">
                          <Mail className="h-4 w-4 mr-1 text-slate-400" />
                          {user.email}
                        </div>
                        {user.phone && (
                          <div className="text-sm text-slate-500 flex items-center mt-1">
                            <Phone className="h-4 w-4 mr-1 text-slate-400" />
                            {user.phone}
                          </div>
                        )}
                      </td>
                      <td className="px-6 py-4">
                        <div className="flex flex-wrap gap-1">
                          {user.roles.length > 0 ? (
                            user.roles.map((role) => (
                              <span
                                key={role.id}
                                className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"
                              >
                                <Shield className="w-3 h-3 mr-1" />
                                {role.name}
                              </span>
                            ))
                          ) : (
                            <span className="text-sm text-slate-500 italic">No roles assigned</span>
                          )}
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <span className={`inline-flex px-3 py-1 text-xs font-medium rounded-full border ${getUserBadgeColor(user)}`}>
                          {user.email_verified_at ? 'Verified' : 'Unverified'}
                        </span>
                      </td>
                      <td className="px-6 py-4">
                        <div className="text-sm text-slate-900">
                          {formatDate(user.created_at)}
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="flex space-x-2">
                          <button
                            onClick={() => openViewModal(user)}
                            className="inline-flex items-center px-3 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white text-sm font-medium rounded-lg hover:from-blue-600 hover:to-blue-700 transform hover:scale-105 transition-all duration-200"
                          >
                            <Eye className="w-4 h-4 mr-1" />
                            View
                          </button>
                          <button
                            onClick={() => openEditModal(user)}
                            className="inline-flex items-center px-3 py-2 bg-gradient-to-r from-amber-500 to-amber-600 text-white text-sm font-medium rounded-lg hover:from-amber-600 hover:to-amber-700 transform hover:scale-105 transition-all duration-200"
                          >
                            <Edit2 className="w-4 h-4 mr-1" />
                            Edit
                          </button>
                          <button
                            onClick={() => openDeleteModal(user)}
                            className="inline-flex items-center px-3 py-2 bg-gradient-to-r from-red-500 to-red-600 text-white text-sm font-medium rounded-lg hover:from-red-600 hover:to-red-700 transform hover:scale-105 transition-all duration-200"
                          >
                            <Trash2 className="w-4 h-4 mr-1" />
                            Delete
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan={7} className="px-6 py-12 text-center">
                      <div className="flex flex-col items-center">
                        <User className="w-16 h-16 text-slate-300 mb-4" />
                        <h3 className="text-lg font-medium text-slate-900 mb-1">No users found</h3>
                        <p className="text-slate-500">Try adjusting your search or create a new user</p>
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

      {/* Create User Modal */}
      {isCreateModalOpen && (
        <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50 p-4">
          <div className="bg-white rounded-2xl shadow-2xl max-w-2xl w-full">
        <div className="p-6">
          <div className="flex items-center justify-between mb-6">
            <div>
          <h3 className="text-2xl font-bold text-slate-800">Create New User</h3>
          <p className="text-slate-600 mt-1">Add a new user to the system</p>
            </div>
            <button
          onClick={closeAllModals}
          className="p-2 hover:bg-slate-100 rounded-full transition-colors"
            >
          <X className="w-6 h-6 text-slate-400" />
            </button>
          </div>

          <div className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-semibold text-slate-700 mb-2">User Code</label>
            <input
              type="text"
              name="code"
              value={formData.code}
              onChange={handleInputChange}
              placeholder="e.g., USR001"
              className="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
            {errors.code && <p className="mt-1 text-sm text-red-600">{errors.code}</p>}
          </div>

          <div>
            <label className="block text-sm font-semibold text-slate-700 mb-2">Email</label>
            <input
              type="email"
              name="email"
              value={formData.email}
              onChange={handleInputChange}
              placeholder="user@example.com"
              className="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
            {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email}</p>}
          </div>

          <div>
            <label className="block text-sm font-semibold text-slate-700 mb-2">First Name</label>
            <input
              type="text"
              name="first_name"
              value={formData.first_name}
              onChange={handleInputChange}
              placeholder="John"
              className="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
            {errors.first_name && <p className="mt-1 text-sm text-red-600">{errors.first_name}</p>}
          </div>

          <div>
            <label className="block text-sm font-semibold text-slate-700 mb-2">Last Name</label>
            <input
              type="text"
              name="last_name"
              value={formData.last_name}
              onChange={handleInputChange}
              placeholder="Doe"
              className="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
            {errors.last_name && <p className="mt-1 text-sm text-red-600">{errors.last_name}</p>}
          </div>

          <div>
            <label className="block text-sm font-semibold text-slate-700 mb-2">Phone (Optional)</label>
            <input
              type="text"
              name="phone"
              value={formData.phone}
              onChange={handleInputChange}
              placeholder="e.g., +1234567890"
              className="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
            {errors.phone && <p className="mt-1 text-sm text-red-600">{errors.phone}</p>}
          </div>

          <div>
            <label className="block text-sm font-semibold text-slate-700 mb-2">Password</label>
            <div className="relative">
              <input
            type={showPassword ? 'text' : 'password'}
            name="password"
            value={formData.password}
            onChange={handleInputChange}
            placeholder="Enter password"
            className="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent pr-12"
              />
              <button
            type="button"
            onClick={() => setShowPassword(!showPassword)}
            className="absolute inset-y-0 right-0 pr-3 flex items-center"
              >
            {showPassword ? (
              <EyeOff className="h-5 w-5 text-slate-400" />
            ) : (
              <Eye className="h-5 w-5 text-slate-400" />
            )}
              </button>
            </div>
            {errors.password && <p className="mt-1 text-sm text-red-600">{errors.password}</p>}
          </div>

          <div>
            <label className="block text-sm font-semibold text-slate-700 mb-2">Confirm Password</label>
            <input
              type={showPassword ? 'text' : 'password'}
              name="password_confirmation"
              value={formData.password_confirmation}
              onChange={handleInputChange}
              placeholder="Confirm password"
              className="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
            {errors.password_confirmation && <p className="mt-1 text-sm text-red-600">{errors.password_confirmation}</p>}
          </div>
            </div>

            <div>
          <label className="block text-sm font-semibold text-slate-700 mb-2">Assign Roles</label>
          <div className="grid grid-cols-2 md:grid-cols-3 gap-3 max-h-40 overflow-y-auto border border-slate-200 rounded-xl p-4">
            {roles.map((role) => (
              <label key={role.id} className="flex items-center">
            <input
              type="checkbox"
              checked={formData.roles.includes(role.id)}
              onChange={(e) => {
                if (e.target.checked) {
              setFormData(prev => ({ ...prev, roles: [...prev.roles, role.id] }));
                } else {
              setFormData(prev => ({ ...prev, roles: prev.roles.filter(id => id !== role.id) }));
                }
              }}
              className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
            />
            <span className="ml-2 text-sm text-slate-700">
              {role.name}
            </span>
              </label>
            ))}
          </div>
          {errors.roles && <p className="mt-1 text-sm text-red-600">{errors.roles}</p>}
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
          disabled={isLoading || !formData.code.trim() || !formData.email.trim()}
          className="px-6 py-3 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white font-semibold rounded-xl hover:from-emerald-600 hover:to-emerald-700 transform hover:scale-105 transition-all duration-200 disabled:opacity-50 disabled:transform-none"
            >
          {isLoading ? (
            <div className="flex items-center">
              <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-white mr-2"></div>
              Creating...
            </div>
          ) : (
            'Create User'
          )}
            </button>
          </div>
        </div>
          </div>
        </div>
      )}

      {/* Edit User Modal */}
      {isEditModalOpen && currentUser && (
        <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50 p-4">
          <div className="bg-white rounded-2xl shadow-2xl max-w-2xl w-full">
        <div className="p-6">
          <div className="flex items-center justify-between mb-6">
            <div>
          <h3 className="text-2xl font-bold text-slate-800">Edit User: {currentUser.first_name} {currentUser.last_name}</h3>
          <p className="text-slate-600 mt-1">Modify user details</p>
            </div>
            <button
          onClick={closeAllModals}
          className="p-2 hover:bg-slate-100 rounded-full transition-colors"
            >
          <X className="w-6 h-6 text-slate-400" />
            </button>
          </div>

          <div className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-semibold text-slate-700 mb-2">User Code</label>
            <input
              type="text"
              name="code"
              value={formData.code}
              onChange={handleInputChange}
              className="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
            {errors.code && <p className="mt-1 text-sm text-red-600">{errors.code}</p>}
          </div>

          <div>
            <label className="block text-sm font-semibold text-slate-700 mb-2">Email</label>
            <input
              type="email"
              name="email"
              value={formData.email}
              onChange={handleInputChange}
              className="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
            {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email}</p>}
          </div>

          <div>
            <label className="block text-sm font-semibold text-slate-700 mb-2">First Name</label>
            <input
              type="text"
              name="first_name"
              value={formData.first_name}
              onChange={handleInputChange}
              className="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
            {errors.first_name && <p className="mt-1 text-sm text-red-600">{errors.first_name}</p>}
          </div>

          <div>
            <label className="block text-sm font-semibold text-slate-700 mb-2">Last Name</label>
            <input
              type="text"
              name="last_name"
              value={formData.last_name}
              onChange={handleInputChange}
              className="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
            {errors.last_name && <p className="mt-1 text-sm text-red-600">{errors.last_name}</p>}
          </div>

          <div>
            <label className="block text-sm font-semibold text-slate-700 mb-2">Phone (Optional)</label>
            <input
              type="text"
              name="phone"
              value={formData.phone}
              onChange={handleInputChange}
              className="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
            {errors.phone && <p className="mt-1 text-sm text-red-600">{errors.phone}</p>}
          </div>

          <div>
            <label className="block text-sm font-semibold text-slate-700 mb-2">New Password (Leave blank to keep current)</label>
            <div className="relative">
              <input
            type={showPassword ? 'text' : 'password'}
            name="password"
            value={formData.password}
            onChange={handleInputChange}
            placeholder="Enter new password"
            className="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent pr-12"
              />
              <button
            type="button"
            onClick={() => setShowPassword(!showPassword)}
            className="absolute inset-y-0 right-0 pr-3 flex items-center"
              >
            {showPassword ? (
              <EyeOff className="h-5 w-5 text-slate-400" />
            ) : (
              <Eye className="h-5 w-5 text-slate-400" />
            )}
              </button>
            </div>
            {errors.password && <p className="mt-1 text-sm text-red-600">{errors.password}</p>}
          </div>

          <div>
            <label className="block text-sm font-semibold text-slate-700 mb-2">Confirm New Password</label>
            <input
              type={showPassword ? 'text' : 'password'}
              name="password_confirmation"
              value={formData.password_confirmation}
              onChange={handleInputChange}
              placeholder="Confirm new password"
              className="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
            {errors.password_confirmation && <p className="mt-1 text-sm text-red-600">{errors.password_confirmation}</p>}
          </div>
            </div>

            <div>
          <label className="block text-sm font-semibold text-slate-700 mb-2">Assign Roles</label>
          <div className="grid grid-cols-2 md:grid-cols-3 gap-3 max-h-40 overflow-y-auto border border-slate-200 rounded-xl p-4">
            {roles.map((role) => (
              <label key={role.id} className="flex items-center">
            <input
              type="checkbox"
              checked={formData.roles.includes(role.id)}
              onChange={(e) => {
                if (e.target.checked) {
              setFormData(prev => ({ ...prev, roles: [...prev.roles, role.id] }));
                } else {
              setFormData(prev => ({ ...prev, roles: prev.roles.filter(id => id !== role.id) }));
                }
              }}
              className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
            />
            <span className="ml-2 text-sm text-slate-700">
              {role.name}
            </span>
              </label>
            ))}
          </div>
          {errors.roles && <p className="mt-1 text-sm text-red-600">{errors.roles}</p>}
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
          disabled={isLoading || !formData.code.trim() || !formData.email.trim()}
          className="px-6 py-3 bg-gradient-to-r from-amber-500 to-amber-600 text-white font-semibold rounded-xl hover:from-amber-600 hover:to-amber-700 transform hover:scale-105 transition-all duration-200 disabled:opacity-50 disabled:transform-none"
            >
          {isLoading ? (
            <div className="flex items-center">
              <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-white mr-2"></div>
              Updating...
            </div>
          ) : (
            'Update User'
          )}
            </button>
          </div>
        </div>
          </div>
        </div>
      )}

      {/* View User Modal */}
      {isViewModalOpen && currentUser && (
        <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50 p-4">
          <div className="bg-white rounded-2xl shadow-2xl max-w-2xl w-full">
        <div className="p-6">
          <div className="flex items-center justify-between mb-6">
            <div>
          <h3 className="text-2xl font-bold text-slate-800">User Details: {currentUser.first_name} {currentUser.last_name}</h3>
          <div className="flex items-center mt-2 space-x-3">
            <span className={`inline-flex px-3 py-1 text-sm font-medium rounded-full border ${getUserBadgeColor(currentUser)}`}>
              {currentUser.email_verified_at ? 'Verified User' : 'Unverified User'}
            </span>
            <span className="text-slate-500">
              {currentUser.roles.length} roles assigned
            </span>
          </div>
            </div>
            <button
          onClick={closeAllModals}
          className="p-2 hover:bg-slate-100 rounded-full transition-colors"
            >
          <X className="w-6 h-6 text-slate-400" />
            </button>
          </div>

          <div className="space-y-6">
            <div className="bg-slate-50 rounded-lg p-4">
          <h4 className="text-lg font-semibold text-slate-800 mb-3">User Information</h4>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-slate-600">Code</label>
              <p className="text-slate-900 font-medium">{currentUser.code}</p>
            </div>
            <div>
              <label className="block text-sm font-medium text-slate-600">Email</label>
              <p className="text-slate-900">{currentUser.email}</p>
            </div>
            <div>
              <label className="block text-sm font-medium text-slate-600">First Name</label>
              <p className="text-slate-900">{currentUser.first_name}</p>
            </div>
            <div>
              <label className="block text-sm font-medium text-slate-600">Last Name</label>
              <p className="text-slate-900">{currentUser.last_name}</p>
            </div>
            {currentUser.phone && (
              <div>
            <label className="block text-sm font-medium text-slate-600">Phone</label>
            <p className="text-slate-900">{currentUser.phone}</p>
              </div>
            )}
            <div>
              <label className="block text-sm font-medium text-slate-600">Created</label>
              <p className="text-slate-900">{formatDate(currentUser.created_at)}</p>
            </div>
          </div>
            </div>

            <div className="bg-slate-50 rounded-lg p-4">
          <h4 className="text-lg font-semibold text-slate-800 mb-3">Assigned Roles</h4>
          <div className="flex flex-wrap gap-2">
            {currentUser.roles.length > 0 ? (
              currentUser.roles.map((role) => (
            <span
              key={role.id}
              className="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800"
            >
              <Shield className="w-4 h-4 mr-1" />
              {role.name}
            </span>
              ))
            ) : (
              <p className="text-slate-500 italic">No roles assigned</p>
            )}
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
            <button
          onClick={() => {
            closeAllModals();
            openEditModal(currentUser);
          }}
          className="px-6 py-3 bg-gradient-to-r from-amber-500 to-amber-600 text-white font-semibold rounded-xl hover:from-amber-600 hover:to-amber-700 transform hover:scale-105 transition-all duration-200"
            >
          Edit User
            </button>
          </div>
        </div>
          </div>
        </div>
      )}

      {/* Delete Confirmation Modal */}}
      {isDeleteModalOpen && currentUser && (
        <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50 p-4">
          <div className="bg-white rounded-2xl shadow-2xl max-w-md w-full">
            <div className="p-6">
              <div className="flex items-center mb-4">
                <div className="flex-shrink-0">
                  <Trash2 className="w-8 h-8 text-red-500" />
                </div>
                <div className="ml-4">
                  <h3 className="text-lg font-semibold text-slate-900">Delete User</h3>
                  <p className="text-sm text-slate-600 mt-1">This action cannot be undone</p>
                </div>
              </div>

              <div className="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                <p className="text-red-800">
                  Are you sure you want to delete <strong>"{currentUser.first_name} {currentUser.last_name}"</strong>? 
                  <span className="block mt-2 text-sm">
                    This will permanently remove the user and all associated data.
                  </span>
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
                  {isLoading ? 'Deleting...' : 'Delete User'}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </AuthenticatedLayout>
  );
}