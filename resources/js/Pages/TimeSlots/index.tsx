import React, { useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Pagination from '@/components/ui/Pagination'; // Import the Pagination component

interface TimeSlot {
    id: number;
    day: string;
    date: string;
    start_time: string;
    end_time: string;
}

interface PaginationLinks {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedTimeSlots {
    data: TimeSlot[];
    links: PaginationLinks[];
    total: number;
    per_page: number;
    current_page: number;
}

const TimeSlots = () => {
    const { timeSlots = { data: [], links: [], total: 0, per_page: 10, current_page: 1 }, perPage = 10, search = '' } = usePage().props as {
        timeSlots?: PaginatedTimeSlots;
        perPage?: number;
        search?: string;
    };

    // Debugging: Log the props to verify data
    console.log('TimeSlots props:', { timeSlots, perPage, search });

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [modalType, setModalType] = useState<'create' | 'edit' | 'delete' | ''>('');
    const [currentTimeSlot, setCurrentTimeSlot] = useState<TimeSlot | null>(null);
    const [itemsPerPage, setItemsPerPage] = useState(perPage);
    const [searchQuery, setSearchQuery] = useState(search);

    const handleOpenModal = (type: 'create' | 'edit' | 'delete', timeSlot: TimeSlot | null = null) => {
        setModalType(type);
        setCurrentTimeSlot(
            type === 'create'
                ? { id: 0, day: '', date: '', start_time: '', end_time: '' }
                : timeSlot
        );
        setIsModalOpen(true);
    };

    const handleCloseModal = () => {
        setIsModalOpen(false);
        setModalType('');
        setCurrentTimeSlot(null);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (modalType === 'create') {
            router.post('/timeslots', currentTimeSlot, {
                onSuccess: () => {
                    alert('Time slot created successfully!');
                    handleCloseModal();
                },
                onError: (errors) => {
                    console.error('Error creating time slot:', errors);
                },
            });
        } else if (modalType === 'edit' && currentTimeSlot) {
            router.put(`/timeslots/${currentTimeSlot.id}`, currentTimeSlot, {
                onSuccess: () => {
                    alert('Time slot updated successfully!');
                    handleCloseModal();
                },
                onError: (errors) => {
                    console.error('Error updating time slot:', errors);
                },
            });
        } else if (modalType === 'delete' && currentTimeSlot) {
            router.delete(`/timeslots/${currentTimeSlot.id}`, {
                onSuccess: () => {
                    alert('Time slot deleted successfully!');
                    handleCloseModal();
                },
                onError: (errors) => {
                    console.error('Error deleting time slot:', errors);
                },
            });
        }
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/timeslots', { search: searchQuery, per_page: itemsPerPage }, { preserveState: true });
    };

    const handleDateChange = (date: string) => {
        const selectedDate = new Date(date);
        const day = selectedDate.toLocaleDateString('en-US', { weekday: 'long' }); // Get the day of the week
        setCurrentTimeSlot((prev) => ({ ...prev!, date, day })); // Update both date and day
    };

    const handlePageChange = (url: string | null) => {
        if (url) {
            router.get(url, { per_page: itemsPerPage, search: searchQuery }, { preserveState: true });
        }
    };

    const handlePerPageChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        const newPerPage = parseInt(e.target.value, 10);
        setItemsPerPage(newPerPage);
        router.get('/timeslots', { per_page: newPerPage, search: searchQuery }, { preserveState: true });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Time Slots" />
            
            {/* Main Container with gradient background */}
            <div className="min-h-screen bg-gradient-to-br from-slate-50 via-purple-50 to-pink-50">
                <div className="max-w-7xl mx-auto p-6 space-y-6">
                    
                    {/* Header Section */}
                    <div className="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl border border-white/20 p-8">
                        <div className="flex items-center justify-between">
                            <div>
                                <h1 className="text-4xl font-bold bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent">
                                    Time Slots
                                </h1>
                                <p className="text-gray-600 mt-2 text-lg">Manage examination time schedules and periods</p>
                            </div>
                            <div className="flex items-center space-x-4">
                                <div className="bg-gradient-to-r from-purple-500 to-pink-600 text-white px-6 py-3 rounded-full text-sm font-semibold shadow-lg">
                                    Total: {timeSlots.total} Time Slots
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Controls Section */}
                    <div className="bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg border border-white/20 p-6">
                        <div className="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6">
                            
                            {/* Add Button */}
                            <button
                                onClick={() => handleOpenModal('create')}
                                className="group relative bg-gradient-to-r from-emerald-500 to-teal-600 text-white px-8 py-4 rounded-xl font-semibold text-lg shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-300 flex items-center space-x-3"
                            >
                                <svg className="w-6 h-6 group-hover:rotate-90 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                <span>Add Time Slot</span>
                            </button>
                            
                            {/* Search Section */}
                            <form onSubmit={handleSearch} className="flex items-center space-x-4">
                                <div className="relative">
                                    <input
                                        type="text"
                                        value={searchQuery}
                                        onChange={(e) => setSearchQuery(e.target.value)}
                                        placeholder="Search time slots..."
                                        className="w-80 pl-12 pr-6 py-4 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-purple-500 focus:ring-4 focus:ring-purple-100 transition-all duration-300 text-lg bg-white/70 backdrop-blur-sm"
                                    />
                                    <svg className="absolute left-4 top-1/2 transform -translate-y-1/2 w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                <button
                                    type="submit"
                                    className="bg-gradient-to-r from-purple-500 to-pink-600 text-white px-8 py-4 rounded-xl font-semibold hover:shadow-lg transform hover:scale-105 transition-all duration-300"
                                >
                                    Search
                                </button>
                            </form>
                            
                            {/* Items per page */}
                            <div className="flex items-center space-x-3">
                                <label htmlFor="perPage" className="text-lg font-medium text-gray-700">
                                    Rows per page:
                                </label>
                                <select
                                    id="perPage"
                                    value={itemsPerPage}
                                    onChange={handlePerPageChange}
                                    className="border-2 border-gray-200 rounded-xl px-4 py-3 text-lg focus:outline-none focus:border-purple-500 focus:ring-4 focus:ring-purple-100 transition-all duration-300 bg-white/70 backdrop-blur-sm"
                                >
                                    <option value={5}>5</option>
                                    <option value={10}>10</option>
                                    <option value={15}>15</option>
                                    <option value={20}>20</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {/* Table Section */}
                    <div className="bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg border border-white/20 overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="min-w-full">
                                <thead>
                                    <tr className="bg-gradient-to-r from-purple-600 to-pink-600 text-white">
                                        <th className="px-8 py-6 text-left text-lg font-bold uppercase tracking-wider">
                                            <div className="flex items-center space-x-2">
                                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                                <span>Day</span>
                                            </div>
                                        </th>
                                        <th className="px-8 py-6 text-left text-lg font-bold uppercase tracking-wider">
                                            <div className="flex items-center space-x-2">
                                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                                <span>Date</span>
                                            </div>
                                        </th>
                                        <th className="px-8 py-6 text-left text-lg font-bold uppercase tracking-wider">
                                            <div className="flex items-center space-x-2">
                                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span>Start Time</span>
                                            </div>
                                        </th>
                                        <th className="px-8 py-6 text-left text-lg font-bold uppercase tracking-wider">
                                            <div className="flex items-center space-x-2">
                                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span>End Time</span>
                                            </div>
                                        </th>
                                        <th className="px-8 py-6 text-center text-lg font-bold uppercase tracking-wider">
                                            <div className="flex items-center justify-center space-x-2">
                                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                                                </svg>
                                                <span>Actions</span>
                                            </div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200">
                                    {timeSlots.data.length > 0 ? (
                                        timeSlots.data.map((time_slots, index) => (
                                            <tr key={time_slots.id} className={`hover:bg-gradient-to-r hover:from-purple-50 hover:to-pink-50 transition-all duration-300 ${index % 2 === 0 ? 'bg-white/50' : 'bg-gray-50/50'}`}>
                                                <td className="px-8 py-6">
                                                    <div className="flex items-center">
                                                        <div className="flex-shrink-0 h-12 w-12 bg-gradient-to-br from-purple-400 to-pink-500 rounded-xl flex items-center justify-center text-white font-bold text-sm">
                                                            {time_slots.day.substring(0, 3).toUpperCase()}
                                                        </div>
                                                        <div className="ml-4">
                                                            <div className="text-lg font-semibold text-gray-900">{time_slots.day}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-8 py-6">
                                                    <div className="flex items-center">
                                                        <div className="bg-gradient-to-r from-blue-100 to-indigo-100 text-blue-800 px-4 py-2 rounded-full text-lg font-semibold">
                                                            {new Date(time_slots.date).toLocaleDateString('en-US', { 
                                                                month: 'short', 
                                                                day: 'numeric', 
                                                                year: 'numeric' 
                                                            })}
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-8 py-6">
                                                    <div className="flex items-center text-lg text-gray-700">
                                                        <div className="bg-gradient-to-r from-emerald-100 to-teal-100 text-emerald-800 px-4 py-2 rounded-full font-semibold">
                                                            {new Date(`2000-01-01T${time_slots.start_time}`).toLocaleTimeString('en-US', { 
                                                                hour: 'numeric', 
                                                                minute: '2-digit', 
                                                                hour12: true 
                                                            })}
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-8 py-6">
                                                    <div className="flex items-center text-lg text-gray-700">
                                                        <div className="bg-gradient-to-r from-orange-100 to-red-100 text-orange-800 px-4 py-2 rounded-full font-semibold">
                                                            {new Date(`2000-01-01T${time_slots.end_time}`).toLocaleTimeString('en-US', { 
                                                                hour: 'numeric', 
                                                                minute: '2-digit', 
                                                                hour12: true 
                                                            })}
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-8 py-6 text-center">
                                                    <div className="flex justify-center space-x-3">
                                                        <button
                                                            onClick={() => handleOpenModal('edit', time_slots)}
                                                            className="group bg-gradient-to-r from-blue-500 to-indigo-600 text-white px-6 py-3 rounded-lg font-semibold hover:shadow-lg transform hover:scale-105 transition-all duration-300 flex items-center space-x-2"
                                                        >
                                                            <svg className="w-4 h-4 group-hover:rotate-12 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                            </svg>
                                                            <span>Edit</span>
                                                        </button>
                                                        <button
                                                            onClick={() => handleOpenModal('delete', time_slots)}
                                                            className="group bg-gradient-to-r from-red-500 to-rose-600 text-white px-6 py-3 rounded-lg font-semibold hover:shadow-lg transform hover:scale-105 transition-all duration-300 flex items-center space-x-2"
                                                        >
                                                            <svg className="w-4 h-4 group-hover:scale-110 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                            <span>Delete</span>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={5} className="px-8 py-12 text-center">
                                                <div className="flex flex-col items-center justify-center space-y-4">
                                                    <div className="h-16 w-16 bg-gradient-to-br from-gray-200 to-gray-300 rounded-full flex items-center justify-center">
                                                        <svg className="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                    </div>
                                                    <p className="text-xl text-gray-500 font-medium">No time slots found</p>
                                                    <p className="text-gray-400">Create your first time slot to get started</p>
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {/* Pagination Section */}
                    <div className="bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg border border-white/20 p-6">
                        <Pagination links={timeSlots.links} onPageChange={handlePageChange} />
                    </div>
                </div>
            </div>

            {/* Enhanced Modal */}
            {isModalOpen && (
                <div className="fixed inset-0 flex items-center justify-center bg-black/50 backdrop-blur-sm z-50 p-4">
                    <div className="bg-white rounded-3xl shadow-2xl border border-white/20 w-full max-w-md transform transition-all duration-300 scale-100">
                        <div className="p-8">
                            <div className="flex items-center justify-between mb-8">
                                <h2 className="text-3xl font-bold bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent">
                                    {modalType === 'create' && 'Add New Time Slot'}
                                    {modalType === 'edit' && 'Edit Time Slot'}
                                    {modalType === 'delete' && 'Delete Time Slot'}
                                </h2>
                                <button
                                    onClick={handleCloseModal}
                                    className="text-gray-400 hover:text-gray-600 transition-colors duration-200"
                                >
                                    <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                            
                            {modalType !== 'delete' ? (
                                <form onSubmit={handleSubmit} className="space-y-6">
                                    <div>
                                        <label className="block text-lg font-semibold text-gray-700 mb-3">Date</label>
                                        <input
                                            type="date"
                                            value={currentTimeSlot?.date || ''}
                                            onChange={(e) => handleDateChange(e.target.value)}
                                            className="w-full border-2 border-gray-200 rounded-xl px-6 py-4 text-lg focus:outline-none focus:border-purple-500 focus:ring-4 focus:ring-purple-100 transition-all duration-300"
                                            required
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-lg font-semibold text-gray-700 mb-3">Day</label>
                                        <input
                                            type="text"
                                            value={currentTimeSlot?.day || ''}
                                            readOnly
                                            className="w-full border-2 border-gray-200 rounded-xl px-6 py-4 text-lg bg-gray-100/70 text-gray-600"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-lg font-semibold text-gray-700 mb-3">Start Time</label>
                                        <input
                                            type="time"
                                            value={currentTimeSlot?.start_time || ''}
                                            onChange={(e) =>
                                                setCurrentTimeSlot((prev) => ({ ...prev!, start_time: e.target.value }))
                                            }
                                            className="w-full border-2 border-gray-200 rounded-xl px-6 py-4 text-lg focus:outline-none focus:border-purple-500 focus:ring-4 focus:ring-purple-100 transition-all duration-300"
                                            required
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-lg font-semibold text-gray-700 mb-3">End Time</label>
                                        <input
                                            type="time"
                                            value={currentTimeSlot?.end_time || ''}
                                            onChange={(e) =>
                                                setCurrentTimeSlot((prev) => ({ ...prev!, end_time: e.target.value }))
                                            }
                                            className="w-full border-2 border-gray-200 rounded-xl px-6 py-4 text-lg focus:outline-none focus:border-purple-500 focus:ring-4 focus:ring-purple-100 transition-all duration-300"
                                            required
                                        />
                                    </div>
                                    <div className="flex space-x-4 pt-6">
                                        <button
                                            type="submit"
                                            className="flex-1 bg-gradient-to-r from-purple-500 to-pink-600 text-white px-8 py-4 rounded-xl font-bold text-lg hover:shadow-lg transform hover:scale-105 transition-all duration-300"
                                        >
                                            {modalType === 'create' ? 'Create Time Slot' : 'Update Time Slot'}
                                        </button>
                                        <button
                                            type="button"
                                            onClick={handleCloseModal}
                                            className="flex-1 bg-gray-100 text-gray-700 px-8 py-4 rounded-xl font-bold text-lg hover:bg-gray-200 transition-all duration-300"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            ) : (
                                <div className="text-center">
                                    <div className="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-red-100 mb-6">
                                        <svg className="h-10 w-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                        </svg>
                                    </div>
                                    <h3 className="text-2xl font-bold text-gray-900 mb-4">Delete Time Slot</h3>
                                    <p className="text-lg text-gray-600 mb-8">
                                        Are you sure you want to delete the time slot for "<span className="font-semibold text-gray-900">{currentTimeSlot?.day}</span>"? This action cannot be undone.
                                    </p>
                                    <div className="flex space-x-4">
                                        <button
                                            onClick={handleSubmit}
                                            className="flex-1 bg-gradient-to-r from-red-500 to-rose-600 text-white px-8 py-4 rounded-xl font-bold text-lg hover:shadow-lg transform hover:scale-105 transition-all duration-300"
                                        >
                                            Yes, Delete
                                        </button>
                                        <button
                                            type="button"
                                            onClick={handleCloseModal}
                                            className="flex-1 bg-gray-100 text-gray-700 px-8 py-4 rounded-xl font-bold text-lg hover:bg-gray-200 transition-all duration-300"
                                        >
                                            Cancel
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

export default TimeSlots;