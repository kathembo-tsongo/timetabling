import React, { useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { toast } from 'react-hot-toast';

interface Classroom {
    building_name: string;
    id: number;
    name: string;
    code: string;
    building: string;
    floor: string | null;
    capacity: number;
    type: string;
    facilities: string[];
    is_active: boolean;
    location: string | null;
    description: string | null;
    created_at: string;
    updated_at: string;
    usage_stats: {
        total_bookings: number;
        weekly_hours: number;
        utilization_rate: number;
        recent_bookings: any[];
    };
}

interface PageProps {
    classrooms: Classroom[];
    stats: {
        total: number;
        active: number;
        inactive: number;
        by_type: Record<string, number>;
        by_building: Record<string, number>;
    };
    buildings: Array<{id: number; name: string; code: string}>;
    types: string[];
    filters: {
        search?: string;
        is_active?: boolean;
        building?: string;
        type?: string;
        sort_field?: string;
        sort_direction?: string;
    };
    can: {
        create: boolean;
        update: boolean;
        delete: boolean;
    };
    error?: string;
}

interface ClassroomFormData {
    name: string;
    code: string;
    building: string;
    floor: string;
    capacity: number;
    type: string;
    facilities: string[];
    location: string;
    description: string;
    is_active: boolean;
}

const ClassroomsIndex: React.FC = () => {
    const { classrooms, stats, buildings, types, filters, can, error } = usePage<PageProps>().props;

    // State management
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [isEditModalOpen, setIsEditModalOpen] = useState(false);
    const [isViewModalOpen, setIsViewModalOpen] = useState(false);
    const [selectedClassroom, setSelectedClassroom] = useState<Classroom | null>(null);
    const [loading, setLoading] = useState(false);

    // Form state
    const [formData, setFormData] = useState<ClassroomFormData>({
        name: '',
        code: '',
        building: '',
        floor: '',
        capacity: 30,
        type: 'lecture_hall',
        facilities: [],
        location: '',
        description: '',
        is_active: true
    });

    // Filter state
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState<string>(
        filters.is_active !== undefined ? (filters.is_active ? 'active' : 'inactive') : 'all'
    );
    const [buildingFilter, setBuildingFilter] = useState(filters.building || 'all');
    const [typeFilter, setTypeFilter] = useState(filters.type || 'all');

    // Available facilities
    const availableFacilities = [
        'Projector', 'Whiteboard', 'Smart Board', 'Audio System', 'Air Conditioning',
        'WiFi', 'Microphone', 'Chairs', 'Tables', 'Computer', 'Printer'
    ];

    // Type labels
    const typeLabels: Record<string, string> = {
        lecture_hall: 'Lecture Hall',
        laboratory: 'Laboratory',
        seminar_room: 'Seminar Room',
        computer_lab: 'Computer Lab',
        auditorium: 'Auditorium',
        meeting_room: 'Meeting Room',
        other: 'Other'
    };

    // Handle form submission
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);

        const url = selectedClassroom 
            ? `/admin/classrooms/${selectedClassroom.id}`
            : '/admin/classrooms';
        
        const method = selectedClassroom ? 'put' : 'post';

        router[method](url, formData, {
            onSuccess: () => {
                toast.success(`Classroom ${selectedClassroom ? 'updated' : 'created'} successfully!`);
                setIsCreateModalOpen(false);
                setIsEditModalOpen(false);
                setSelectedClassroom(null);
                resetForm();
            },
            onError: (errors) => {
                toast.error(errors.error || `Failed to ${selectedClassroom ? 'update' : 'create'} classroom`);
            },
            onFinish: () => setLoading(false)
        });
    };

    // Handle delete
    const handleDelete = (classroom: Classroom) => {
        if (confirm(`Are you sure you want to delete "${classroom.name}"?`)) {
            setLoading(true);
            router.delete(`/admin/classrooms/${classroom.id}`, {
                onSuccess: () => {
                    toast.success('Classroom deleted successfully!');
                },
                onError: (errors) => {
                    toast.error(errors.error || 'Failed to delete classroom');
                },
                onFinish: () => setLoading(false)
            });
        }
    };

    // Form helpers
    const resetForm = () => {
        setFormData({
            name: '',
            code: '',
            building: '',
            floor: '',
            capacity: 30,
            type: 'lecture_hall',
            facilities: [],
            location: '',
            description: '',
            is_active: true
        });
    };

    const openCreateModal = () => {
        resetForm();
        setIsCreateModalOpen(true);
    };

    const openEditModal = (classroom: Classroom) => {
        setSelectedClassroom(classroom);
        setFormData({
            name: classroom.name,
            code: classroom.code,
            building: classroom.building || '',
            floor: classroom.floor || '',
            capacity: classroom.capacity,
            type: classroom.type,
            facilities: classroom.facilities || [],
            location: classroom.location || '',
            description: classroom.description || '',
            is_active: classroom.is_active
        });
        setIsEditModalOpen(true);
    };

    const openViewModal = (classroom: Classroom) => {
        setSelectedClassroom(classroom);
        setIsViewModalOpen(true);
    };

    const closeAllModals = () => {
        setIsCreateModalOpen(false);
        setIsEditModalOpen(false);
        setIsViewModalOpen(false);
        setSelectedClassroom(null);
    };

    // Handle facility toggle
    const toggleFacility = (facility: string) => {
        setFormData(prev => ({
            ...prev,
            facilities: prev.facilities.includes(facility)
                ? prev.facilities.filter(f => f !== facility)
                : [...prev.facilities, facility]
        }));
    };

    // Filter classrooms
    const filteredClassrooms = classrooms.filter(classroom => {
        const matchesSearch = classroom.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
            classroom.code.toLowerCase().includes(searchTerm.toLowerCase()) ||
            (classroom.building_name && classroom.building_name.toLowerCase().includes(searchTerm.toLowerCase()));
        
        const matchesStatus = statusFilter === 'all' || 
            (statusFilter === 'active' && classroom.is_active) ||
            (statusFilter === 'inactive' && !classroom.is_active);
            
        const matchesBuilding = buildingFilter === 'all' || classroom.building === buildingFilter;
        const matchesType = typeFilter === 'all' || classroom.type === typeFilter;
        
        return matchesSearch && matchesStatus && matchesBuilding && matchesType;
    });

    // Handle filter changes
    const applyFilters = () => {
        const params = new URLSearchParams();
        
        if (searchTerm) params.set('search', searchTerm);
        if (statusFilter !== 'all') params.set('is_active', statusFilter === 'active' ? '1' : '0');
        if (buildingFilter !== 'all') params.set('building', buildingFilter);
        if (typeFilter !== 'all') params.set('type', typeFilter);
        
        router.get(`/admin/classrooms?${params.toString()}`);
    };

    const getStatusBadge = (isActive: boolean) => {
        return (
            <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                isActive 
                    ? 'bg-green-100 text-green-800' 
                    : 'bg-red-100 text-red-800'
            }`}>
                {isActive ? 'Active' : 'Inactive'}
            </span>
        );
    };

    const getTypeBadge = (type: string) => {
        const colors = {
            lecture_hall: 'bg-blue-100 text-blue-800',
            laboratory: 'bg-purple-100 text-purple-800',
            seminar_room: 'bg-yellow-100 text-yellow-800',
            computer_lab: 'bg-indigo-100 text-indigo-800',
            auditorium: 'bg-red-100 text-red-800',
            meeting_room: 'bg-gray-100 text-gray-800',
            other: 'bg-gray-100 text-gray-800'
        };
        
        return (
            <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                colors[type as keyof typeof colors] || colors.other
            }`}>
                {typeLabels[type] || 'Other'}
            </span>
        );
    };

    return (
        <AuthenticatedLayout>
            <Head title="Classroom Management" />
            
            <div className="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50 py-8">
                {error && (
                    <div className="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
                        <p className="text-red-700">{error}</p>
                    </div>
                )}

                {/* Header */}
                <div className="bg-white rounded-2xl shadow-xl border p-8 mb-6">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h1 className="text-4xl font-bold text-slate-800 mb-2">Classroom Management</h1>
                            <p className="text-slate-600 text-lg">Manage classrooms, facilities, and room assignments</p>
                            <div className="flex items-center gap-4 mt-4">
                                <div className="text-sm text-slate-600">
                                    Total: <span className="font-semibold">{stats.total}</span>
                                </div>
                                <div className="text-sm text-slate-600">
                                    Active: <span className="font-semibold">{stats.active}</span>
                                </div>
                            </div>
                        </div>
                        {can.create && (
                            <button
                                onClick={openCreateModal}
                                className="inline-flex items-center px-6 py-3 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white font-semibold rounded-xl shadow-lg hover:from-emerald-600 hover:to-emerald-700 transform hover:scale-105 transition-all duration-200"
                            >
                                <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                Create Classroom
                            </button>
                        )}
                    </div>
                </div>

                {/* Filters */}
                <div className="bg-white rounded-2xl shadow-lg border p-6 mb-6">
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                        <div>
                            <input
                                type="text"
                                placeholder="Search classrooms..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            />
                        </div>
                        
                        <div>
                            <select
                                value={statusFilter}
                                onChange={(e) => setStatusFilter(e.target.value)}
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                                <option value="all">All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        
                        <div>
                            <select
                                value={buildingFilter}
                                onChange={(e) => setBuildingFilter(e.target.value)}
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                                <option value="all">All Buildings</option>
                                {buildings.map(building => (
                                    <option key={building.name} value={building.name}>{building.name}</option>
                                ))}
                            </select>
                        </div>
                        
                        <div>
                            <select
                                value={typeFilter}
                                onChange={(e) => setTypeFilter(e.target.value)}
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                                <option value="all">All Types</option>
                                {types.map(type => (
                                    <option key={type} value={type}>{typeLabels[type] || type}</option>
                                ))}
                            </select>
                        </div>
                        
                        <div>
                            <button
                                onClick={applyFilters}
                                className="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                            >
                                Apply Filters
                            </button>
                        </div>
                    </div>
                </div>

                {/* Classrooms Table */}
                <div className="bg-white rounded-2xl shadow-lg border overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Classroom
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Location
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Type
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Capacity
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    {/* <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Utilization
                                    </th> */}
                                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {filteredClassrooms.map((classroom) => (
                                    <tr key={classroom.id} className="hover:bg-gray-50">
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div>
                                                <div className="text-sm font-medium text-gray-900">{classroom.name}</div>
                                                <div className="text-sm text-gray-500">{classroom.code}</div>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="text-sm text-gray-900">{classroom.building_name || 'Unknown Building'}</div>
                                            {classroom.floor && (
                                                <div className="text-sm text-gray-500">{classroom.floor} Floor</div>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            {getTypeBadge(classroom.type)}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {classroom.capacity}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            {getStatusBadge(classroom.is_active)}
                                        </td>
                                        {/* <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {classroom.usage_stats.utilization_rate.toFixed(1)}%
                                        </td> */}
                                        <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div className="flex items-center justify-end gap-2">
                                                <button
                                                    onClick={() => openViewModal(classroom)}
                                                    className="text-blue-600 hover:text-blue-800"
                                                >
                                                    View
                                                </button>
                                                {can.update && (
                                                    <button
                                                        onClick={() => openEditModal(classroom)}
                                                        className="text-indigo-600 hover:text-indigo-800"
                                                    >
                                                        Edit
                                                    </button>
                                                )}
                                                {can.delete && (
                                                    <button
                                                        onClick={() => handleDelete(classroom)}
                                                        className="text-red-600 hover:text-red-800"
                                                    >
                                                        Delete
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    
                    {filteredClassrooms.length === 0 && (
                        <div className="p-8 text-center text-gray-500">
                            No classrooms found matching your criteria.
                        </div>
                    )}
                </div>

                {/* Create/Edit Modal */}
                {(isCreateModalOpen || isEditModalOpen) && (
                    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
                        <div className="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                            <div className="p-6">
                                <div className="flex justify-between items-center mb-6">
                                    <h2 className="text-2xl font-bold text-gray-900">
                                        {selectedClassroom ? 'Edit Classroom' : 'Create Classroom'}
                                    </h2>
                                    <button
                                        onClick={closeAllModals}
                                        className="text-gray-400 hover:text-gray-600"
                                    >
                                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>

                                <form onSubmit={handleSubmit} className="space-y-6">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                                Classroom Name *
                                            </label>
                                            <input
                                                type="text"
                                                required
                                                value={formData.name}
                                                onChange={(e) => setFormData(prev => ({ ...prev, name: e.target.value }))}
                                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                            />
                                        </div>

                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                                Classroom Code *
                                            </label>
                                            <input
                                                type="text"
                                                required
                                                value={formData.code}
                                                onChange={(e) => setFormData(prev => ({ ...prev, code: e.target.value }))}
                                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                            />
                                        </div>

                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                                Building *
                                            </label>
                                            <select
                                                required
                                                value={formData.building}
                                                onChange={(e) => setFormData(prev => ({ ...prev, building: e.target.value }))}
                                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                            >
                                                <option value="">Select Building</option>
                                                {buildings.map(building => (
                                                    <option key={building.name} value={building.name}>{building.name}</option>
                                                ))}
                                            </select>
                                        </div>

                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                                Floor
                                            </label>
                                            <input
                                                type="text"
                                                value={formData.floor}
                                                onChange={(e) => setFormData(prev => ({ ...prev, floor: e.target.value }))}
                                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                            />
                                        </div>

                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                                Capacity *
                                            </label>
                                            <input
                                                type="number"
                                                required
                                                min="1"
                                                value={formData.capacity}
                                                onChange={(e) => setFormData(prev => ({ ...prev, capacity: parseInt(e.target.value) }))}
                                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                            />
                                        </div>

                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                                Type *
                                            </label>
                                            <select
                                                required
                                                value={formData.type}
                                                onChange={(e) => setFormData(prev => ({ ...prev, type: e.target.value }))}
                                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                            >
                                                {Object.entries(typeLabels).map(([value, label]) => (
                                                    <option key={value} value={value}>{label}</option>
                                                ))}
                                            </select>
                                        </div>
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Location
                                        </label>
                                        <input
                                            type="text"
                                            value={formData.location}
                                            onChange={(e) => setFormData(prev => ({ ...prev, location: e.target.value }))}
                                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Description
                                        </label>
                                        <textarea
                                            rows={3}
                                            value={formData.description}
                                            onChange={(e) => setFormData(prev => ({ ...prev, description: e.target.value }))}
                                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-3">
                                            Facilities
                                        </label>
                                        <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
                                            {availableFacilities.map(facility => (
                                                <label key={facility} className="flex items-center">
                                                    <input
                                                        type="checkbox"
                                                        checked={formData.facilities.includes(facility)}
                                                        onChange={() => toggleFacility(facility)}
                                                        className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                    />
                                                    <span className="ml-2 text-sm text-gray-700">{facility}</span>
                                                </label>
                                            ))}
                                        </div>
                                    </div>

                                    <div>
                                        <label className="flex items-center">
                                            <input
                                                type="checkbox"
                                                checked={formData.is_active}
                                                onChange={(e) => setFormData(prev => ({ ...prev, is_active: e.target.checked }))}
                                                className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            />
                                            <span className="ml-2 text-sm font-medium text-gray-700">Active</span>
                                        </label>
                                    </div>

                                    <div className="flex justify-end gap-4">
                                        <button
                                            type="button"
                                            onClick={closeAllModals}
                                            className="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            type="submit"
                                            disabled={loading}
                                            className="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                                        >
                                            {loading ? 'Saving...' : (selectedClassroom ? 'Update' : 'Create')}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                )}

                {/* View Modal */}
                {isViewModalOpen && selectedClassroom && (
                    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
                        <div className="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                            <div className="p-6">
                                <div className="flex justify-between items-center mb-6">
                                    <h2 className="text-2xl font-bold text-gray-900">Classroom Details</h2>
                                    <button
                                        onClick={closeAllModals}
                                        className="text-gray-400 hover:text-gray-600"
                                    >
                                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>

                                <div className="space-y-6">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <h3 className="text-sm font-medium text-gray-500">Name</h3>
                                            <p className="mt-1 text-sm text-gray-900">{selectedClassroom.name}</p>
                                        </div>
                                        <div>
                                            <h3 className="text-sm font-medium text-gray-500">Code</h3>
                                            <p className="mt-1 text-sm text-gray-900">{selectedClassroom.code}</p>
                                        </div>
                                        <div>
                                            <h3 className="text-sm font-medium text-gray-500">Building</h3>
                                            <p className="mt-1 text-sm text-gray-900">{selectedClassroom.building_name || 'Unknown Building'}</p>
                                        </div>
                                        {selectedClassroom.floor && (
                                            <div>
                                                <h3 className="text-sm font-medium text-gray-500">Floor</h3>
                                                <p className="mt-1 text-sm text-gray-900">{selectedClassroom.floor}</p>
                                            </div>
                                        )}
                                        <div>
                                            <h3 className="text-sm font-medium text-gray-500">Capacity</h3>
                                            <p className="mt-1 text-sm text-gray-900">{selectedClassroom.capacity} people</p>
                                        </div>
                                        <div>
                                            <h3 className="text-sm font-medium text-gray-500">Type</h3>
                                            <p className="mt-1">{getTypeBadge(selectedClassroom.type)}</p>
                                        </div>
                                        <div>
                                            <h3 className="text-sm font-medium text-gray-500">Status</h3>
                                            <p className="mt-1">{getStatusBadge(selectedClassroom.is_active)}</p>
                                        </div>
                                    </div>

                                    {selectedClassroom.location && (
                                        <div>
                                            <h3 className="text-sm font-medium text-gray-500">Location</h3>
                                            <p className="mt-1 text-sm text-gray-900">{selectedClassroom.location}</p>
                                        </div>
                                    )}

                                    {selectedClassroom.description && (
                                        <div>
                                            <h3 className="text-sm font-medium text-gray-500">Description</h3>
                                            <p className="mt-1 text-sm text-gray-900">{selectedClassroom.description}</p>
                                        </div>
                                    )}

                                    <div>
                                        <h3 className="text-sm font-medium text-gray-500 mb-3">Facilities</h3>
                                        <div className="flex flex-wrap gap-2">
                                            {selectedClassroom.facilities && selectedClassroom.facilities.length > 0 ? (
                                                selectedClassroom.facilities.map(facility => (
                                                    <span
                                                        key={facility}
                                                        className="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800"
                                                    >
                                                        {facility}
                                                    </span>
                                                ))
                                            ) : (
                                                <p className="text-sm text-gray-500">No facilities listed</p>
                                            )}
                                        </div>
                                    </div>

                                    <div>
                                        <h3 className="text-sm font-medium text-gray-500 mb-3">Usage Statistics</h3>
                                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <div className="bg-blue-50 p-4 rounded-lg">
                                                <div className="text-2xl font-bold text-blue-600">
                                                    {selectedClassroom.usage_stats.total_bookings}
                                                </div>
                                                <div className="text-sm text-blue-800">Total Bookings</div>
                                            </div>
                                            <div className="bg-green-50 p-4 rounded-lg">
                                                <div className="text-2xl font-bold text-green-600">
                                                    {selectedClassroom.usage_stats.weekly_hours}
                                                </div>
                                                <div className="text-sm text-green-800">Weekly Hours</div>
                                            </div>
                                            <div className="bg-purple-50 p-4 rounded-lg">
                                                <div className="text-2xl font-bold text-purple-600">
                                                    {selectedClassroom.usage_stats.utilization_rate.toFixed(1)}%
                                                </div>
                                                <div className="text-sm text-purple-800">Utilization Rate</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="border-t pt-4">
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-500">
                                            <div>
                                                <strong>Created:</strong> {new Date(selectedClassroom.created_at).toLocaleDateString()}
                                            </div>
                                            <div>
                                                <strong>Last Updated:</strong> {new Date(selectedClassroom.updated_at).toLocaleDateString()}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div className="flex justify-end mt-6">
                                    <button
                                        onClick={closeAllModals}
                                        className="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700"
                                    >
                                        Close
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
};

export default ClassroomsIndex;