import React, { useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Pagination from '@/components/ui/Pagination';

interface ClassTimeSlot {
    id: number;
    day: string;    
    start_time: string;
    end_time: string;
    status: string;
}

interface PaginationLinks {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedTimeSlots {
    data: ClassTimeSlot[];
    links: PaginationLinks[];
    total: number;
    per_page: number;
    current_page: number;
}

const ClassTimeSlot = () => {
    const { classtimeSlot = { data: [], links: [], total: 0, per_page: 10, current_page: 1 }, perPage = 10, search = '' } = usePage().props as {
        classtimeSlot?: PaginatedTimeSlots;
        perPage?: number;
        search?: string;
    };

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [modalType, setModalType] = useState<'create' | 'edit' | 'delete' | ''>('');
    const [currentClassTimeSlot, setCurrentClassTimeSlot] = useState<ClassTimeSlot | null>(null);
    const [itemsPerPage, setItemsPerPage] = useState(perPage);
    const [searchQuery, setSearchQuery] = useState(search);

    // Helper function to format time to HH:mm (24-hour format)
    const formatTimeTo24Hour = (time: string): string => {
        if (!time) return '';
        // If already in HH:mm or HH:mm:ss format, extract HH:mm
        if (time.includes(':')) {
            const parts = time.split(':');
            return `${parts[0].padStart(2, '0')}:${parts[1].padStart(2, '0')}`;
        }
        return time;
    };

    const handleOpenModal = (type: 'create' | 'edit' | 'delete', classtimeSlot: ClassTimeSlot | null = null) => {
        setModalType(type);
        if (type === 'create') {
            setCurrentClassTimeSlot({ id: 0, day: '', start_time: '', end_time: '', status: '' });
        } else if (classtimeSlot) {
            // Format times to HH:mm when opening edit modal
            setCurrentClassTimeSlot({
                ...classtimeSlot,
                start_time: formatTimeTo24Hour(classtimeSlot.start_time),
                end_time: formatTimeTo24Hour(classtimeSlot.end_time)
            });
        }
        setIsModalOpen(true);
    };

    const handleCloseModal = () => {
        setIsModalOpen(false);
        setModalType('');
        setCurrentClassTimeSlot(null);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (modalType === 'create') {
            router.post('/classtimeslot', currentClassTimeSlot, {
                onSuccess: () => {
                    alert('Class Time slot created successfully!');
                    handleCloseModal();
                },
                onError: (errors) => {
                    console.error('Error creating class time slot:', errors);
                    const errorMessages = Object.entries(errors)
                        .map(([key, value]) => `${key}: ${value}`)
                        .join('\n');
                    alert('Validation errors:\n' + errorMessages);
                },
            });
        } else if (modalType === 'edit' && currentClassTimeSlot) {
            router.put(`/classtimeslot/${currentClassTimeSlot.id}`, currentClassTimeSlot, {
                onSuccess: () => {
                    alert('Class Time slot updated successfully!');
                    handleCloseModal();
                },
                onError: (errors) => {
                    console.error('Error updating class time slot:', errors);
                    const errorMessages = Object.entries(errors)
                        .map(([key, value]) => `${key}: ${value}`)
                        .join('\n');
                    alert('Validation errors:\n' + errorMessages);
                },
            });
        } else if (modalType === 'delete' && currentClassTimeSlot) {
            router.delete(`/classtimeslot/${currentClassTimeSlot.id}`, {
                onSuccess: () => {
                    alert('Class Time slot deleted successfully!');
                    handleCloseModal();
                },
                onError: (errors) => {
                    console.error('Error deleting time slot:', errors);
                    alert('Error deleting time slot. Please try again.');
                },
            });
        }
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/classtimeslot', { search: searchQuery, per_page: itemsPerPage }, { preserveState: true });
    };

    const handlePageChange = (url: string | null) => {
        if (url) {
            router.get(url, { per_page: itemsPerPage, search: searchQuery }, { preserveState: true });
        }
    };

    const handlePerPageChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        const newPerPage = parseInt(e.target.value, 10);
        setItemsPerPage(newPerPage);
        router.get('/classtimeslot', { per_page: newPerPage, search: searchQuery }, { preserveState: true });
    };

    // Helper to display time in readable format
    const displayTime = (time: string): string => {
        if (!time) return '';
        try {
            const [hours, minutes] = time.split(':');
            const date = new Date();
            date.setHours(parseInt(hours), parseInt(minutes));
            return date.toLocaleTimeString('en-US', { 
                hour: 'numeric', 
                minute: '2-digit', 
                hour12: true 
            });
        } catch {
            return time;
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="Time Slots" />
            <div className="p-6 bg-white rounded-lg shadow-md">
                <div className="flex justify-between items-center mb-4">
                    <button
                        onClick={() => handleOpenModal('create', null)}
                        className="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600"
                    >
                        + Add Class Time Slot
                    </button>
                    <form onSubmit={handleSearch} className="flex items-center space-x-2">
                        <input
                            type="text"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder="Search time slots..."
                            className="border rounded p-2 w-64 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <button
                            type="submit"
                            className="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600"
                        >
                            Search
                        </button>
                    </form>
                    <div>
                        <label htmlFor="perPage" className="mr-2 text-sm font-medium text-gray-700">
                            Rows per page:
                        </label>
                        <select
                            id="perPage"
                            value={itemsPerPage}
                            onChange={handlePerPageChange}
                            className="border rounded p-2"
                        >
                            <option value={5}>5</option>
                            <option value={10}>10</option>
                            <option value={15}>15</option>
                            <option value={20}>20</option>
                        </select>
                    </div>
                </div>

                <table className="min-w-full border-collapse border border-gray-200">
                    <thead className="bg-gray-100">
                        <tr>
                            <th className="px-4 py-2 border">Day</th>
                            <th className="px-4 py-2 border">Start Time</th>
                            <th className="px-4 py-2 border">End Time</th>
                            <th className="px-4 py-2 border">Mode of Learning</th>
                            <th className="px-4 py-2 border">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {classtimeSlot.data.length > 0 ? (
                            classtimeSlot.data.map((class_time_slots) => (
                                <tr key={class_time_slots.id} className="border-b hover:bg-gray-50">
                                    <td className="px-4 py-2 border">{class_time_slots.day}</td>
                                    <td className="px-4 py-2 border">{displayTime(class_time_slots.start_time)}</td>
                                    <td className="px-4 py-2 border">{displayTime(class_time_slots.end_time)}</td>
                                    <td className="px-4 py-2 border">{class_time_slots.status}</td>
                                    <td className="px-4 py-2 border text-center">
                                        <button
                                            onClick={() => handleOpenModal('edit', class_time_slots)}
                                            className="bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-600 mr-2"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            onClick={() => handleOpenModal('delete', class_time_slots)}
                                            className="bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600"
                                        >
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            ))
                        ) : (
                            <tr>
                                <td colSpan={5} className="px-4 py-2 text-center text-gray-500">
                                    No class time slots found.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>

                <Pagination links={classtimeSlot.links} onPageChange={handlePageChange} />
            </div>

            {/* Modal */}
            {isModalOpen && (
                <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
                    <div className="bg-white p-6 rounded shadow-md w-96">
                        <h2 className="text-xl font-bold mb-4">
                            {modalType === 'create' && 'Add Class Time Slot'}
                            {modalType === 'edit' && 'Edit Class Time Slot'}
                            {modalType === 'delete' && 'Delete Class Time Slot'}
                        </h2>
                        {modalType !== 'delete' ? (
                            <form onSubmit={handleSubmit}>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700 mb-2">Day</label>
                                    <select
                                        value={currentClassTimeSlot?.day || ''}
                                        onChange={(e) =>
                                            setCurrentClassTimeSlot((prev) => ({ ...prev!, day: e.target.value }))
                                        }
                                        className="w-full border rounded p-2"
                                        required
                                    >
                                        <option value="">Select Day</option>
                                        {["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"].map((day) => (
                                            <option key={day} value={day}>
                                                {day}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700 mb-2">Start Time</label>
                                    <input
                                        type="time"
                                        value={currentClassTimeSlot?.start_time || ''}
                                        onChange={(e) =>
                                            setCurrentClassTimeSlot((prev) => ({ ...prev!, start_time: e.target.value }))
                                        }
                                        className="w-full border rounded p-2"
                                        required
                                    />
                                </div>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700 mb-2">End Time</label>
                                    <input
                                        type="time"
                                        value={currentClassTimeSlot?.end_time || ''}
                                        onChange={(e) =>
                                            setCurrentClassTimeSlot((prev) => ({ ...prev!, end_time: e.target.value }))
                                        }
                                        className="w-full border rounded p-2"
                                        required
                                    />
                                </div>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700 mb-2">Mode of Learning</label>
                                    <select
                                        value={currentClassTimeSlot?.status || ''}
                                        onChange={(e) =>
                                            setCurrentClassTimeSlot((prev) => ({ ...prev!, status: e.target.value }))
                                        }
                                        className="w-full border rounded p-2"
                                        required
                                    >
                                        <option value="">Select Mode of Study</option>
                                        <option value="Physical">Physical</option>
                                        <option value="Online">Online</option>
                                    </select>
                                </div>
                                <div className="flex space-x-2">
                                    <button
                                        type="submit"
                                        className="flex-1 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
                                    >
                                        {modalType === 'create' ? 'Create' : 'Update'}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={handleCloseModal}
                                        className="flex-1 bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        ) : (
                            <div>
                                <p className="mb-4">Are you sure you want to delete this time slot for {currentClassTimeSlot?.day}?</p>
                                <div className="flex space-x-2">
                                    <button
                                        onClick={handleSubmit}
                                        className="flex-1 bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700"
                                    >
                                        Delete
                                    </button>
                                    <button
                                        type="button"
                                        onClick={handleCloseModal}
                                        className="flex-1 bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
};

export default ClassTimeSlot;