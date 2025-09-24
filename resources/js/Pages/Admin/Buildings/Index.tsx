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
  MapPin,
  Users,
  Home,
  Building,
  Calendar,
  RotateCcw,
  Archive,
  AlertTriangle,
  RefreshCw
} from "lucide-react";

// Interfaces
interface Building {
  id: number;
  name: string;
  code: string;
  description: string | null;
  address: string | null;
  is_active: boolean;
  classroom?: number;
  created_at: string;
  updated_at: string;
  deleted_at?: string;
}

interface PaginationLinks {
  url: string | null;
  label: string;
  active: boolean;
}

interface PaginatedBuildings {
  data: Building[];
  links: PaginationLinks[];
  total: number;
  per_page: number;
  current_page: number;
}

interface PageProps {
  [key: string]: unknown;
  buildings: PaginatedBuildings;
  filters: {
    search?: string;
    status?: string;
    per_page?: number;
  };
  can: {
    create_buildings: boolean;
    update_buildings: boolean;
    delete_buildings: boolean;
    view_buildings: boolean;
  };
  flash?: {
    success?: string;
  };
  errors?: {
    error?: string;
  };
}

const BuildingsIndex: React.FC = () => {
  const { buildings, filters, can, flash, errors } = usePage<PageProps>().props;

  // State management
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [modalType, setModalType] = useState<'create' | 'edit' | 'delete' | 'view' | 'restore' | 'forceDelete' | ''>('');
  const [currentBuilding, setCurrentBuilding] = useState<Building | null>(null);
  const [loading, setLoading] = useState(false);
  const [activeTab, setActiveTab] = useState<'active' | 'trash'>('active');
  const [trashedBuildings, setTrashedBuildings] = useState<PaginatedBuildings>({
    data: [],
    links: [],
    total: 0,
    per_page: 10,
    current_page: 1
  });
  const [loadingTrash, setLoadingTrash] = useState(false);

  // Form state - UPDATED to include classroom_count
  const [buildingForm, setBuildingForm] = useState({
    name: '',
    code: '',
    description: '',
    address: '',
    is_active: true,
    classroom: 0 // NEW FIELD
  });

  // Filter state
  const [searchQuery, setSearchQuery] = useState(filters.search || '');
  const [statusFilter, setStatusFilter] = useState(filters.status || 'all');
  const [itemsPerPage, setItemsPerPage] = useState(filters.per_page || 10);

  // Error handling
  useEffect(() => {
    if (errors?.error) {
      toast.error(errors.error);
    }
    if (flash?.success) {
      toast.success(flash.success);
    }
  }, [errors, flash]);

  // Load trashed buildings when trash tab is selected
  useEffect(() => {
    if (activeTab === 'trash') {
      loadTrashedBuildings();
    }
  }, [activeTab]);

  const loadTrashedBuildings = async (search = '', perPage = 10, page = 1) => {
    setLoadingTrash(true);
    try {
      const response = await fetch(`/admin/buildings/trashed?search=${search}&per_page=${perPage}&page=${page}`);
      const data = await response.json();
      setTrashedBuildings(data);
    } catch (error) {
      toast.error('Failed to load deleted buildings');
    } finally {
      setLoadingTrash(false);
    }
  };

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

  // Modal handlers - UPDATED to handle classroom_count
  const handleOpenModal = (type: 'create' | 'edit' | 'delete' | 'view' | 'restore' | 'forceDelete', building: Building | null = null) => {
    setModalType(type);
    if (type === 'create') {
      setBuildingForm({
        name: '',
        code: '',
        description: '',
        address: '',
        is_active: true,
        classroom: 0 // Initialize with 0
      });
    } else if (type === 'edit' && building) {
      setCurrentBuilding(building);
      setBuildingForm({
        name: building.name,
        code: building.code,
        description: building.description || '',
        address: building.address || '',
        is_active: building.is_active,
        classroom: building.classroom || 0 // Use existing count or 0
      });
    } else if (['delete', 'view', 'restore', 'forceDelete'].includes(type)) {
      setCurrentBuilding(building);
    }
    setIsModalOpen(true);
  };

  const handleCloseModal = () => {
    setIsModalOpen(false);
    setModalType('');
    setCurrentBuilding(null);
  };

  // Auto-generate code from name
  const generateCode = (name: string) => {
    return name
      .toUpperCase()
      .replace(/[^A-Z0-9\s]/g, '')
      .split(' ')
      .map(word => word.substring(0, 3))
      .join('')
      .substring(0, 10);
  };

  // Form handlers
  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);

    if (modalType === 'create') {
      router.post(route('admin.buildings.store'), buildingForm, {
        onSuccess: () => {
          toast.success('Building created successfully!');
          handleCloseModal();
        },
        onError: (errors) => {
          toast.error(errors.error || 'Failed to create building');
        },
        onFinish: () => setLoading(false)
      });
    } else if (modalType === 'edit' && currentBuilding) {
      router.put(route('admin.buildings.update', currentBuilding.id), buildingForm, {
        onSuccess: () => {
          toast.success('Building updated successfully!');
          handleCloseModal();
        },
        onError: (errors) => {
          toast.error(errors.error || 'Failed to update building');
        },
        onFinish: () => setLoading(false)
      });
    } else if (modalType === 'delete' && currentBuilding) {
      if (currentBuilding.classroom && currentBuilding.classroom > 0) {
        toast.error(`Cannot delete building with ${currentBuilding.classroom} classrooms`);
        setLoading(false);
        return;
      }

      router.delete(route('admin.buildings.destroy', currentBuilding.id), {
        onSuccess: () => {
          toast.success('Building deleted successfully!');
          handleCloseModal();
        },
        onError: (errors) => {
          toast.error(errors.error || 'Failed to delete building');
        },
        onFinish: () => setLoading(false)
      });
    } else if (modalType === 'restore' && currentBuilding) {
      router.put(route('admin.buildings.restore', currentBuilding.id), {}, {
        onSuccess: () => {
          toast.success('Building restored successfully!');
          handleCloseModal();
          loadTrashedBuildings(); // Refresh trash list
        },
        onError: (errors) => {
          toast.error(errors.error || 'Failed to restore building');
        },
        onFinish: () => setLoading(false)
      });
    } else if (modalType === 'forceDelete' && currentBuilding) {
      router.delete(route('admin.buildings.force-delete', currentBuilding.id), {
        onSuccess: () => {
          toast.success('Building permanently deleted!');
          handleCloseModal();
          loadTrashedBuildings(); // Refresh trash list
        },
        onError: (errors) => {
          toast.error(errors.error || 'Failed to permanently delete building');
        },
        onFinish: () => setLoading(false)
      });
    }
  };

  // Filter handlers
  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    const params = {
      search: searchQuery,
      status: statusFilter !== 'all' ? statusFilter : undefined,
      per_page: itemsPerPage
    };
    
    router.get(route('admin.buildings.index'), params, { preserveState: true });
  };

  const handlePerPageChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const newPerPage = parseInt(e.target.value, 10);
    setItemsPerPage(newPerPage);
    
    const params = {
      search: searchQuery,
      status: statusFilter !== 'all' ? statusFilter : undefined,
      per_page: newPerPage
    };
    
    router.get(route('admin.buildings.index'), params, { preserveState: true });
  };

  const handlePageChange = (url: string | null) => {
    if (url) {
      router.get(url, {}, { preserveState: true });
    }
  };

  const toggleStatus = (building: Building) => {
    router.put(route('admin.buildings.toggle-status', building.id), {}, {
      onSuccess: () => {
        const status = building.is_active ? 'deactivated' : 'activated';
        toast.success(`Building ${status} successfully!`);
      },
      onError: (errors) => {
        toast.error('Failed to update building status');
      }
    });
  };

  return (
    <AuthenticatedLayout>
      <Head title="Buildings Management" />
      
      <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-indigo-50 py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          
          {/* Header */}
          <div className="mb-8">
            <div className="bg-white/80 backdrop-blur-sm rounded-3xl shadow-2xl border border-slate-200/50 p-8">
              <div className="flex items-center mb-6">
                <Building2 className="w-8 h-8 text-blue-600 mr-3" />
                <div>
                  <h1 className="text-4xl font-bold bg-gradient-to-r from-blue-800 via-blue-600 to-indigo-800 bg-clip-text text-transparent">
                    Buildings Management
                  </h1>
                  <p className="text-slate-600 text-lg mt-1">
                    Manage campus buildings and facilities
                  </p>
                </div>
              </div>
              
              <p className="text-slate-600 text-base mb-4">
                Create and manage buildings, their locations, and associated classrooms.
              </p>
              
              <div className="flex items-center gap-4">
                <div className="text-sm text-slate-600">
                  Total Buildings: <span className="font-semibold">{buildings.total}</span>
                </div>
                <div className="text-sm text-slate-600">
                  Active Buildings: <span className="font-semibold">{buildings.data.filter(b => b.is_active).length}</span>
                </div>
                <div className="text-sm text-slate-600">
                  Total Classrooms: <span className="font-semibold">{buildings.data.reduce((sum, b) => sum + (b.classroom || 0), 0)}</span>
                </div>
              </div>
            </div>
          </div>

          {/* Tab Navigation and Controls */}
          <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-lg border border-slate-200/50 p-6 mb-6">
            <div className="flex space-x-1 bg-gray-100 rounded-lg p-1 mb-6">
              <button
                onClick={() => setActiveTab('active')}
                className={`flex items-center px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 ${
                  activeTab === 'active'
                    ? 'bg-white text-blue-600 shadow-sm'
                    : 'text-gray-600 hover:text-blue-600'
                }`}
              >
                <Building2 className="w-4 h-4 mr-2" />
                Active Buildings ({buildings.total})
              </button>
              <button
                onClick={() => setActiveTab('trash')}
                className={`flex items-center px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 ${
                  activeTab === 'trash'
                    ? 'bg-white text-red-600 shadow-sm'
                    : 'text-gray-600 hover:text-red-600'
                }`}
              >
                <Archive className="w-4 h-4 mr-2" />
                Deleted Buildings ({trashedBuildings.total})
              </button>
            </div>

            {/* Action Buttons and Filters */}
            <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
              <div className="flex gap-3">
                {activeTab === 'active' && can.create_buildings && (
                  <button
                    onClick={() => handleOpenModal('create')}
                    className="inline-flex items-center px-4 py-2 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white font-semibold rounded-lg hover:from-emerald-600 hover:to-emerald-700 transition-all duration-200"
                  >
                    <Plus className="w-4 h-4 mr-2" />
                    Add Building
                  </button>
                )}
                {activeTab === 'trash' && (
                  <button
                    onClick={() => loadTrashedBuildings(searchQuery, itemsPerPage)}
                    disabled={loadingTrash}
                    className="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white font-semibold rounded-lg hover:from-blue-600 hover:to-blue-700 transition-all duration-200 disabled:opacity-50"
                  >
                    <RefreshCw className={`w-4 h-4 mr-2 ${loadingTrash ? 'animate-spin' : ''}`} />
                    Refresh
                  </button>
                )}
              </div>
              
              <div className="flex items-center gap-3">
                <label className="text-sm font-medium text-gray-700">Items per page:</label>
                <select
                  value={itemsPerPage}
                  onChange={(e) => {
                    const newPerPage = parseInt(e.target.value);
                    setItemsPerPage(newPerPage);
                    if (activeTab === 'trash') {
                      loadTrashedBuildings(searchQuery, newPerPage);
                    } else {
                      handlePerPageChange(e);
                    }
                  }}
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
            <form onSubmit={(e) => {
              e.preventDefault();
              if (activeTab === 'trash') {
                loadTrashedBuildings(searchQuery, itemsPerPage);
              } else {
                handleSearch(e);
              }
            }} className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              <div>
                <input
                  type="text"
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  placeholder={`Search ${activeTab === 'trash' ? 'deleted' : ''} buildings...`}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
              </div>
              {activeTab === 'active' && (
                <div>
                  <select
                    value={statusFilter}
                    onChange={(e) => setStatusFilter(e.target.value)}
                    className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  >
                    <option value="all">All Status</option>
                    <option value="active">Active Only</option>
                    <option value="inactive">Inactive Only</option>
                  </select>
                </div>
              )}
              <div>
                <button
                  type="submit"
                  disabled={activeTab === 'trash' && loadingTrash}
                  className="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors flex items-center justify-center disabled:opacity-50"
                >
                  <Search className="w-4 h-4 mr-2" />
                  {activeTab === 'trash' && loadingTrash ? 'Searching...' : 'Search'}
                </button>
              </div>
            </form>
          </div>

          {/* Buildings/Trash Table */}
          <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-2xl border border-slate-200/50 overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-slate-50">
                  <tr>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">ID</th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Building Info</th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Description</th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Address</th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Classrooms</th>
                    {activeTab === 'active' && (
                      <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Status</th>
                    )}
                    {activeTab === 'trash' && (
                      <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Deleted At</th>
                    )}
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200">
                  {/* Active Buildings */}
                  {activeTab === 'active' && buildings.data.map((building, index) => (
                    <tr key={building.id} className={`hover:bg-slate-50 transition-colors duration-150 ${
                      index % 2 === 0 ? "bg-white" : "bg-slate-50/50"
                    }`}>
                      <td className="px-6 py-4 text-sm font-medium text-slate-900">{building.id}</td>
                      <td className="px-6 py-4">
                        <div className="flex items-center">
                          <Building2 className="w-5 h-5 text-blue-500 mr-3" />
                          <div>
                            <div className="text-sm font-medium text-slate-900">{building.name}</div>
                            <div className="text-xs text-slate-500 font-mono bg-slate-100 px-2 py-1 rounded">
                              {building.code}
                            </div>
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4 text-sm text-slate-700 max-w-xs">
                        {building.description ? (
                          <span className="truncate block" title={building.description}>
                            {building.description.length > 50 
                              ? `${building.description.substring(0, 50)}...` 
                              : building.description
                            }
                          </span>
                        ) : (
                          <em className="text-slate-400">No description</em>
                        )}
                      </td>
                      <td className="px-6 py-4 text-sm text-slate-700 max-w-xs">
                        {building.address ? (
                          <div className="flex items-center">
                            <MapPin className="w-4 h-4 text-slate-400 mr-1" />
                            <span className="truncate" title={building.address}>
                              {building.address.length > 40 
                                ? `${building.address.substring(0, 40)}...` 
                                : building.address
                              }
                            </span>
                          </div>
                        ) : (
                          <em className="text-slate-400">No address</em>
                        )}
                      </td>
                      <td className="px-6 py-4 text-sm text-slate-700">
                        <span className="inline-flex items-center px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">
                          <Home className="w-3 h-3 mr-1" />
                          {building.classroom || 0}
                        </span>
                      </td>
                      <td className="px-6 py-4">
                        <button
                          onClick={() => toggleStatus(building)}
                          className="cursor-pointer"
                          disabled={!can.update_buildings}
                        >
                          <StatusBadge isActive={building.is_active} />
                        </button>
                      </td>
                      <td className="px-6 py-4 text-sm font-medium">
                        <div className="flex items-center space-x-2">
                          {can.view_buildings && (
                            <button
                              onClick={() => handleOpenModal('view', building)}
                              className="text-blue-600 hover:text-blue-900 transition-colors p-1 rounded hover:bg-blue-50"
                              title="View building details"
                            >
                              <Eye className="w-4 h-4" />
                            </button>
                          )}
                          {can.update_buildings && (
                            <button
                              onClick={() => handleOpenModal('edit', building)}
                              className="text-indigo-600 hover:text-indigo-900 transition-colors p-1 rounded hover:bg-indigo-50"
                              title="Edit building"
                            >
                              <Edit className="w-4 h-4" />
                            </button>
                          )}
                          {can.delete_buildings && (
                            <button
                              onClick={() => handleOpenModal('delete', building)}
                              className="text-red-600 hover:text-red-900 transition-colors p-1 rounded hover:bg-red-50"
                              title="Delete building"
                              disabled={building.classroom && building.classroom > 0}
                            >
                              <Trash2 className="w-4 h-4" />
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}

                  {/* Deleted Buildings (Trash) */}
                  {activeTab === 'trash' && trashedBuildings.data.map((building, index) => (
                    <tr key={`trash-${building.id}`} className={`hover:bg-red-50 transition-colors duration-150 ${
                      index % 2 === 0 ? "bg-red-50/30" : "bg-red-50/50"
                    }`}>
                      <td className="px-6 py-4 text-sm font-medium text-slate-900">{building.id}</td>
                      <td className="px-6 py-4">
                        <div className="flex items-center">
                          <Archive className="w-5 h-5 text-red-500 mr-3" />
                          <div>
                            <div className="text-sm font-medium text-slate-900">{building.name}</div>
                            <div className="text-xs text-slate-500 font-mono bg-red-100 px-2 py-1 rounded">
                              {building.code}
                            </div>
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4 text-sm text-slate-700 max-w-xs">
                        {building.description ? (
                          <span className="truncate block" title={building.description}>
                            {building.description.length > 50 
                              ? `${building.description.substring(0, 50)}...` 
                              : building.description
                            }
                          </span>
                        ) : (
                          <em className="text-slate-400">No description</em>
                        )}
                      </td>
                      <td className="px-6 py-4 text-sm text-slate-700 max-w-xs">
                        {building.address ? (
                          <div className="flex items-center">
                            <MapPin className="w-4 h-4 text-slate-400 mr-1" />
                            <span className="truncate" title={building.address}>
                              {building.address.length > 40 
                                ? `${building.address.substring(0, 40)}...` 
                                : building.address
                              }
                            </span>
                          </div>
                        ) : (
                          <em className="text-slate-400">No address</em>
                        )}
                      </td>
                      <td className="px-6 py-4 text-sm text-slate-700">
                        <span className="inline-flex items-center px-2 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-medium">
                          <Home className="w-3 h-3 mr-1" />
                          {building.classroom || 0}
                        </span>
                      </td>
                      <td className="px-6 py-4 text-sm text-red-600">
                        {building.deleted_at && new Date(building.deleted_at).toLocaleDateString('en-US', {
                          month: 'short',
                          day: 'numeric',
                          hour: '2-digit',
                          minute: '2-digit'
                        })}
                      </td>
                      <td className="px-6 py-4 text-sm font-medium">
                        <div className="flex items-center space-x-2">
                          {can.update_buildings && (
                            <button
                              onClick={() => handleOpenModal('restore', building)}
                              className="text-green-600 hover:text-green-900 transition-colors p-1 rounded hover:bg-green-50"
                              title="Restore building"
                            >
                              <RotateCcw className="w-4 h-4" />
                            </button>
                          )}
                          {can.delete_buildings && (
                            <button
                              onClick={() => handleOpenModal('forceDelete', building)}
                              className="text-red-600 hover:text-red-900 transition-colors p-1 rounded hover:bg-red-50"
                              title="Permanently delete"
                              disabled={building.classroom && building.classroom > 0}
                            >
                              <AlertTriangle className="w-4 h-4" />
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>

              {/* Empty States */}
              {activeTab === 'active' && buildings.data.length === 0 && (
                <div className="text-center py-12">
                  <Building2 className="mx-auto h-12 w-12 text-gray-400" />
                  <h3 className="mt-2 text-sm font-medium text-gray-900">No buildings found</h3>
                  <p className="mt-1 text-sm text-gray-500">
                    {searchQuery || statusFilter !== 'all'
                      ? 'Try adjusting your filters'
                      : 'Get started by creating your first building'
                    }
                  </p>
                  {can.create_buildings && !searchQuery && statusFilter === 'all' && (
                    <button
                      onClick={() => handleOpenModal('create')}
                      className="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                    >
                      <Plus className="w-4 h-4 mr-2" />
                      Create Building
                    </button>
                  )}
                </div>
              )}

              {activeTab === 'trash' && trashedBuildings.data.length === 0 && (
                <div className="text-center py-12">
                  <Archive className="mx-auto h-12 w-12 text-gray-400" />
                  <h3 className="mt-2 text-sm font-medium text-gray-900">No deleted buildings</h3>
                  <p className="mt-1 text-sm text-gray-500">
                    {searchQuery ? 'No deleted buildings match your search' : 'All buildings are currently active'}
                  </p>
                </div>
              )}

              {/* Loading state for trash */}
              {activeTab === 'trash' && loadingTrash && (
                <div className="text-center py-12">
                  <RefreshCw className="mx-auto h-8 w-8 text-blue-500 animate-spin" />
                  <p className="mt-2 text-sm text-gray-500">Loading deleted buildings...</p>
                </div>
              )}
            </div>

            {/* Pagination */}
            {((activeTab === 'active' && buildings.data.length > 0) || 
              (activeTab === 'trash' && trashedBuildings.data.length > 0)) && (
              <div className="bg-gray-50 px-6 py-4 flex items-center justify-between">
                <div className="text-sm text-gray-600">
                  {activeTab === 'active' 
                    ? `Showing ${buildings.data.length} of ${buildings.total} buildings`
                    : `Showing ${trashedBuildings.data.length} of ${trashedBuildings.total} deleted buildings`
                  }
                </div>
                <div className="flex space-x-2">
                  {(activeTab === 'active' ? buildings.links : trashedBuildings.links).map((link, index) => (
                    <button
                      key={index}
                      onClick={() => {
                        if (activeTab === 'active') {
                          handlePageChange(link.url);
                        } else {
                          // Handle trash pagination differently
                          if (link.url) {
                            const url = new URL(link.url);
                            const page = url.searchParams.get('page') || '1';
                            loadTrashedBuildings(searchQuery, itemsPerPage, parseInt(page));
                          }
                        }
                      }}
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
                : modalType === 'view'
                ? 'bg-gradient-to-r from-blue-500 to-blue-600'
                : modalType === 'edit'
                ? 'bg-gradient-to-r from-amber-500 to-amber-600'
                : modalType === 'restore'
                ? 'bg-gradient-to-r from-green-500 to-green-600'
                : modalType === 'forceDelete'
                ? 'bg-gradient-to-r from-red-600 to-red-700'
                : 'bg-gradient-to-r from-emerald-500 to-emerald-600'
            }`}>
              <h3 className="text-xl font-semibold text-white flex items-center">
                {modalType === 'create' && <><Plus className="w-5 h-5 mr-2" />Add Building</>}
                {modalType === 'edit' && <><Edit className="w-5 h-5 mr-2" />Edit Building</>}
                {modalType === 'delete' && <><Trash2 className="w-5 h-5 mr-2" />Delete Building</>}
                {modalType === 'view' && <><Eye className="w-5 h-5 mr-2" />Building Details</>}
                {modalType === 'restore' && <><RotateCcw className="w-5 h-5 mr-2" />Restore Building</>}
                {modalType === 'forceDelete' && <><AlertTriangle className="w-5 h-5 mr-2" />Permanently Delete</>}
              </h3>
            </div>

            <div className="p-6">
              {modalType === 'restore' && currentBuilding ? (
                <div>
                  <div className="flex items-center mb-4">
                    <RotateCcw className="w-8 h-8 text-green-500 mr-3" />
                    <div>
                      <h3 className="text-lg font-medium text-gray-900">Restore Building</h3>
                      <p className="text-sm text-gray-500">This will restore the building and make it active again</p>
                    </div>
                  </div>
                  
                  <div className="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                    <div className="text-sm text-green-800">
                      <strong>Building:</strong> {currentBuilding.name} ({currentBuilding.code})
                    </div>
                    <div className="text-sm text-green-800">
                      <strong>Classrooms:</strong> {currentBuilding.classroom || 0}
                    </div>
                    {currentBuilding.deleted_at && (
                      <div className="text-sm text-green-800">
                        <strong>Deleted:</strong> {new Date(currentBuilding.deleted_at).toLocaleDateString('en-US', {
                          year: 'numeric',
                          month: 'long',
                          day: 'numeric',
                          hour: '2-digit',
                          minute: '2-digit'
                        })}
                      </div>
                    )}
                  </div>

                  <p className="text-gray-700 mb-4">
                    Are you sure you want to restore "{currentBuilding.name}"? It will be available for use immediately.
                  </p>

                  <div className="flex items-center justify-end space-x-4">
                    <button
                      onClick={handleCloseModal}
                      className="px-6 py-3 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors font-medium"
                    >
                      Cancel
                    </button>
                    <button
                      onClick={handleSubmit}
                      disabled={loading}
                      className="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors font-medium disabled:opacity-50"
                    >
                      {loading ? 'Restoring...' : 'Restore Building'}
                    </button>
                  </div>
                </div>
              ) : modalType === 'forceDelete' && currentBuilding ? (
                <div>
                  <div className="flex items-center mb-4">
                    <AlertTriangle className="w-8 h-8 text-red-500 mr-3" />
                    <div>
                      <h3 className="text-lg font-medium text-gray-900">Permanently Delete Building</h3>
                      <p className="text-sm text-gray-500">This action cannot be undone!</p>
                    </div>
                  </div>

                  <div className="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                    <div className="text-sm text-red-800">
                      <strong>⚠️ Warning:</strong> This will permanently delete the building from the database.
                    </div>
                    <div className="text-sm text-red-800 mt-1">
                      <strong>Building:</strong> {currentBuilding.name} ({currentBuilding.code})
                    </div>
                    <div className="text-sm text-red-800 mt-1">
                      <strong>Classrooms:</strong> {currentBuilding.classroom || 0}
                    </div>
                  </div>

                  {currentBuilding.classroom && currentBuilding.classroom > 0 ? (
                    <div className="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                      <div className="flex items-center">
                        <AlertTriangle className="w-5 h-5 text-red-500 mr-2" />
                        <p className="text-sm text-red-700">
                          Cannot permanently delete building with {currentBuilding.classroom} classroom(s). 
                          Remove classrooms first.
                        </p>
                      </div>
                    </div>
                  ) : (
                    <p className="text-gray-700 mb-4">
                      Are you absolutely sure you want to permanently delete "{currentBuilding.name}"? 
                      This action cannot be undone and all data will be lost forever.
                    </p>
                  )}

                  <div className="flex items-center justify-end space-x-4">
                    <button
                      onClick={handleCloseModal}
                      className="px-6 py-3 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors font-medium"
                    >
                      Cancel
                    </button>
                    {(!currentBuilding.classroom || currentBuilding.classroom === 0) && (
                      <button
                        onClick={handleSubmit}
                        disabled={loading}
                        className="px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors font-medium disabled:opacity-50"
                      >
                        {loading ? 'Deleting Forever...' : 'Delete Permanently'}
                      </button>
                    )}
                  </div>
                </div>
              ) : modalType === 'view' && currentBuilding ? (
                <div className="space-y-6">
                  {/* Building Header */}
                  <div className="flex items-center justify-between">
                    <div className="flex items-center">
                      <Building2 className="w-8 h-8 text-blue-500 mr-3" />
                      <div>
                        <h2 className="text-2xl font-bold text-gray-900">{currentBuilding.name}</h2>
                        <p className="text-sm text-gray-500 font-mono bg-gray-100 px-2 py-1 rounded inline-block">
                          {currentBuilding.code}
                        </p>
                      </div>
                    </div>
                    <StatusBadge isActive={currentBuilding.is_active} />
                  </div>

                  {/* Building Details */}
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">Description</label>
                      <div className="p-3 bg-gray-50 rounded-lg border">
                        {currentBuilding.description || <em className="text-gray-500">No description provided</em>}
                      </div>
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">Address</label>
                      <div className="p-3 bg-gray-50 rounded-lg border">
                        {currentBuilding.address ? (
                          <div className="flex items-center">
                            <MapPin className="w-4 h-4 text-gray-400 mr-2" />
                            {currentBuilding.address}
                          </div>
                        ) : (
                          <em className="text-gray-500">No address provided</em>
                        )}
                      </div>
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">Classrooms</label>
                      <div className="p-3 bg-blue-50 rounded-lg border border-blue-200">
                        <div className="flex items-center">
                          <Home className="w-4 h-4 text-blue-500 mr-2" />
                          <span className="font-medium">{currentBuilding.classroom || 0} Classrooms</span>
                        </div>
                      </div>
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">Created</label>
                      <div className="p-3 bg-gray-50 rounded-lg border">
                        <div className="flex items-center">
                          <Calendar className="w-4 h-4 text-gray-400 mr-2" />
                          {new Date(currentBuilding.created_at).toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                          })}
                        </div>
                      </div>
                    </div>
                  </div>

                  <div className="flex items-center justify-end space-x-4 pt-4 border-t">
                    <button
                      onClick={handleCloseModal}
                      className="px-6 py-3 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors font-medium"
                    >
                      Close
                    </button>
                    {can.update_buildings && (
                      <button
                        onClick={() => {
                          handleCloseModal();
                          setTimeout(() => handleOpenModal('edit', currentBuilding), 100);
                        }}
                        className="px-6 py-3 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition-colors font-medium"
                      >
                        Edit Building
                      </button>
                    )}
                  </div>
                </div>
              ) : modalType === 'delete' ? (
                <div>
                  <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                    <div className="text-sm text-yellow-800">
                      <strong>Building:</strong> {currentBuilding?.name} ({currentBuilding?.code})
                    </div>
                    <div className="text-sm text-yellow-800">
                      <strong>Classrooms:</strong> {currentBuilding?.classroom || 0}
                    </div>
                  </div>

                  <p className="text-gray-700 mb-4">
                    Are you sure you want to delete the building "{currentBuilding?.name}"?
                  </p>
                  {currentBuilding?.classroom && currentBuilding.classroom > 0 && (
                    <div className="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                      <div className="flex items-center">
                        <AlertTriangle className="w-5 h-5 text-red-500 mr-2" />
                        <p className="text-sm text-red-700">
                          This building has {currentBuilding.classroom} classrooms and cannot be deleted.
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
                    {(!currentBuilding?.classroom || currentBuilding.classroom === 0) && (
                      <button
                        onClick={handleSubmit}
                        disabled={loading}
                        className="px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors font-medium disabled:opacity-50"
                      >
                        {loading ? 'Deleting...' : 'Delete Building'}
                      </button>
                    )}
                  </div>
                </div>
              ) : (
                <form onSubmit={handleSubmit} className="space-y-4">
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Building Name *
                      </label>
                      <input
                        type="text"
                        value={buildingForm.name}
                        onChange={(e) => {
                          setBuildingForm(prev => ({ ...prev, name: e.target.value }));
                          // Auto-generate code if creating new building
                          if (modalType === 'create' && !buildingForm.code) {
                            setBuildingForm(prev => ({ ...prev, code: generateCode(e.target.value) }));
                          }
                        }}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        placeholder="e.g., Main Building, Library Building"
                        required
                      />
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Building Code *
                      </label>
                      <input
                        type="text"
                        value={buildingForm.code}
                        onChange={(e) => setBuildingForm(prev => ({ ...prev, code: e.target.value.toUpperCase() }))}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 font-mono"
                        placeholder="e.g., MAIN, LIB, ENG"
                        maxLength={10}
                        required
                      />
                      <small className="text-gray-500 text-xs">
                        Max 10 characters, auto-generated from name
                      </small>
                    </div>
                  </div>

                  {/* NEW CLASSROOM COUNT FIELD */}
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Number of Classrooms *
                      </label>
                      <input
                        type="number"
                        min="0"
                        max="999"
                        value={buildingForm.classroom}
                        onChange={(e) => setBuildingForm(prev => ({ 
                          ...prev, 
                          classroom: Math.max(0, parseInt(e.target.value) || 0)
                        }))}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        placeholder="0"
                        required
                      />
                      <small className="text-gray-500 text-xs">
                        Total number of classrooms in this building
                      </small>
                    </div>

                    <div className="flex items-center pt-6">
                      <input
                        type="checkbox"
                        id="is_active"
                        checked={buildingForm.is_active}
                        onChange={(e) => setBuildingForm(prev => ({ ...prev, is_active: e.target.checked }))}
                        className="mr-3 rounded focus:ring-emerald-500 h-4 w-4 text-emerald-600 border-gray-300"
                      />
                      <div>
                        <label htmlFor="is_active" className="text-sm font-medium text-gray-700">
                          Active Building
                        </label>
                        <p className="text-gray-500 text-xs">
                          Active buildings are available for classroom assignments
                        </p>
                      </div>
                    </div>
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Description
                    </label>
                    <textarea
                      value={buildingForm.description}
                      onChange={(e) => setBuildingForm(prev => ({ ...prev, description: e.target.value }))}
                      rows={3}
                      maxLength={1000}
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                      placeholder="Brief description of the building and its purpose"
                    />
                    <small className="text-gray-500 text-xs">
                      {1000 - buildingForm.description.length} characters remaining
                    </small>
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Address/Location
                    </label>
                    <textarea
                      value={buildingForm.address}
                      onChange={(e) => setBuildingForm(prev => ({ ...prev, address: e.target.value }))}
                      rows={2}
                      maxLength={500}
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                      placeholder="Physical address or location description"
                    />
                    <small className="text-gray-500 text-xs">
                      {500 - buildingForm.address.length} characters remaining
                    </small>
                  </div>

                  {/* Preview for creation */}
                  {modalType === 'create' && buildingForm.name && buildingForm.code && (
                    <div className="bg-emerald-50 p-4 rounded-lg border border-emerald-200">
                      <h4 className="font-medium text-emerald-900 mb-2">Preview:</h4>
                      <div className="space-y-1">
                        <div className="text-sm text-emerald-800">
                          <strong>Name:</strong> {buildingForm.name}
                        </div>
                        <div className="text-sm text-emerald-800">
                          <strong>Code:</strong> <span className="font-mono bg-emerald-100 px-2 py-1 rounded">{buildingForm.code}</span>
                        </div>
                        <div className="text-sm text-emerald-800">
                          <strong>Classrooms:</strong> {buildingForm.classroom}
                        </div>
                        {buildingForm.description && (
                          <div className="text-sm text-emerald-800">
                            <strong>Description:</strong> {buildingForm.description.substring(0, 100)}{buildingForm.description.length > 100 ? '...' : ''}
                          </div>
                        )}
                        <div className="text-sm text-emerald-800">
                          <strong>Status:</strong> {buildingForm.is_active ? 'Active' : 'Inactive'}
                        </div>
                      </div>
                    </div>
                  )}

                  {/* Last updated info for edit */}
                  {modalType === 'edit' && currentBuilding && (
                    <div className="bg-amber-50 border border-amber-200 rounded-lg p-3">
                      <div className="text-sm text-amber-800">
                        <strong>Last Updated:</strong> {new Date(currentBuilding.updated_at).toLocaleDateString('en-US', {
                          year: 'numeric',
                          month: 'long',
                          day: 'numeric',
                          hour: '2-digit',
                          minute: '2-digit'
                        })}
                      </div>
                      <div className="text-sm text-amber-800">
                        <strong>Created:</strong> {new Date(currentBuilding.created_at).toLocaleDateString('en-US', {
                          year: 'numeric',
                          month: 'long',
                          day: 'numeric'
                        })}
                      </div>
                      <div className="text-sm text-amber-800">
                        <strong>Current Classrooms:</strong> {currentBuilding.classroom || 0}
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
                      disabled={loading || !buildingForm.name.trim() || !buildingForm.code.trim()}
                      className={`px-6 py-3 text-white rounded-lg transition-colors font-medium disabled:opacity-50 ${
                        modalType === 'create' 
                          ? 'bg-emerald-600 hover:bg-emerald-700' 
                          : 'bg-amber-600 hover:bg-amber-700'
                      }`}
                    >
                      {loading 
                        ? 'Processing...' 
                        : modalType === 'create' 
                          ? 'Create Building' 
                          : 'Update Building'
                      }
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

export default BuildingsIndex;