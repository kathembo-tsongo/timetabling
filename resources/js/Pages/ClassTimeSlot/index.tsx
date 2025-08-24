import React, { useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Pagination from '@/components/ui/Pagination'; // Import the Pagination component

interface ClassTimeSlot {
    id: number;
    day: string;    
    start_time: string;
    end_time: string;
    status: string; // Add status field
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

    // Debugging: Log the props to verify data
    console.log('classTimeSlots props:', { classtimeSlot, perPage, search });

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [modalType, setModalType] = useState<'create' | 'edit' | 'delete' | ''>('');
    const [currentClassTimeSlot, setCurrentClassTimeSlot] = useState<ClassTimeSlot | null>(null);
    const [itemsPerPage, setItemsPerPage] = useState(perPage);
    const [searchQuery, setSearchQuery] = useState(search);

    const handleOpenModal = (type: 'create' | 'edit' | 'delete', classtimeSlot: ClassTimeSlot | null = null) => {
        setModalType(type);
        setCurrentClassTimeSlot(
            type === 'create'
                ? { id: 0, day: '', start_time: '', end_time: '', status: '' }
                : classtimeSlot
        );
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
                },
            });
        }
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/classtimeslot', { search: searchQuery, per_page: itemsPerPage }, { preserveState: true });
    };

    const handleDateChange = (date: string) => {
        const selectedDate = new Date(date);
        const day = selectedDate.toLocaleDateString('en-US', { weekday: 'long' }); // Get the day of the week
        setCurrentClassTimeSlot((prev) => ({ ...prev!, date, day })); // Update both date and day
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

    return (
        <AuthenticatedLayout>
            <Head title="Time Slots" />
            <div className="p-6 bg-white rounded-lg shadow-md">
                {/* <h1 className="text-2xl font-semibold mb-4">Class Time Slots</h1> */}
                <div className="flex justify-between items-center mb-4">
                    <button
                        onClick={() => handleOpenModal('create')}
                        className="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600"
                    >
                        + Add Class Time Slot
                    </button>
                    <form onSubmit={handleSearch} className="flex items-center space-x-2">
                        <input
                            type="text"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder="Search classrooms..."
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
                            <th className="px-4 py-2 border">Day</th><th className="px-4 py-2 border">Start Time</th><th className="px-4 py-2 border">End Time</th><th className="px-4 py-2 border">Mode of Learning</th><th className="px-4 py-2 border">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {classtimeSlot.data.length > 0 ? (
                            classtimeSlot.data.map((class_time_slots) => (
                                <tr key={class_time_slots.id} className="border-b hover:bg-gray-50">
                                    <td className="px-4 py-2 border">{class_time_slots.day}</td><td className="px-4 py-2 border">{class_time_slots.start_time}</td><td className="px-4 py-2 border">{class_time_slots.end_time}</td><td className="px-4 py-2 border">{class_time_slots.status}</td><td className="px-4 py-2 border text-center">
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
                                <td colSpan={5} className="px-4 py-2 text-center text-gray-500">No class time slots found.</td>
                            </tr>
                        )}
                    </tbody>
                </table>
                <Pagination links={classtimeSlot.links} onPageChange={handlePageChange} />
            </div>

            {/* Modal */}
            {isModalOpen && (
                <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50">
                    <div className="bg-white p-6 rounded shadow-md">
                        <h2 className="text-xl font-bold mb-4">
                            {modalType === 'create' && 'Add Class Time Slot'}
                            {modalType === 'edit' && 'Edit Class Time Slot'}
                            {modalType === 'delete' && 'Delete Class Time Slot'}
                        </h2>
                        {modalType !== 'delete' ? (
                            <form onSubmit={handleSubmit}>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">Day</label>
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
                                    <label className="block text-sm font-medium text-gray-700">Start Time</label>
                                    <input
                                        type="time"
                                        value={currentClassTimeSlot?.start_time || ''} // Pre-populate start_time
                                        onChange={(e) =>
                                            setCurrentClassTimeSlot((prev) => ({ ...prev!, start_time: e.target.value }))
                                        }
                                        className="w-full border rounded p-2"
                                        required
                                    />
                                </div>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">End Time</label>
                                    <input
                                        type="time"
                                        value={currentClassTimeSlot?.end_time || ''} // Pre-populate end_time
                                        onChange={(e) =>
                                            setCurrentClassTimeSlot((prev) => ({ ...prev!, end_time: e.target.value }))
                                        }
                                        className="w-full border rounded p-2"
                                        required
                                    />
                                </div>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">Mode of Learning</label>
                                    <select
                                        value={currentClassTimeSlot?.status || ''}
                                        onChange={(e) =>
                                            setCurrentClassTimeSlot((prev) => ({ ...prev!, status: e.target.value }))
                                        }
                                        className="w-full border rounded p-2"
                                        required
                                    >
                                        <option value="Default">Select Mode of Study</option>
                                        <option value="Physical">Physical</option>
                                        <option value="Online">Online</option>
                                    </select>
                                </div>
                                <button
                                    type="submit"
                                    className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
                                >
                                    {modalType === 'create' ? 'Create' : 'Update'}
                                </button>
                                <button
                                    type="button"
                                    onClick={handleCloseModal}
                                    className="bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500 ml-2"
                                >
                                    Cancel
                                </button>
                            </form>
                        ) : (
                            <div>
                                <p>Are you sure you want to delete this time slot?</p>
                                <div className="mt-4 flex justify-end">
                                    <button
                                        onClick={handleSubmit}
                                        className="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700"
                                    >
                                        Delete
                                    </button>
                                    <button
                                        type="button"
                                        onClick={handleCloseModal}
                                        className="bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500 ml-2"
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
