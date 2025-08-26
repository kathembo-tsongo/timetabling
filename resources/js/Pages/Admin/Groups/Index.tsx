import React, { useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import axios from "axios";

interface Class {
    id: number;
    name: string;
}

interface Group {
    id: number;
    name: string;
    class_id: number | null;
    capacity: number | null;
}

interface PaginationLinks {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedGroups {
    data: Group[];
    links: PaginationLinks[];
    total: number;
    per_page: number;
    current_page: number;
}

const Groups = () => {
    const { groups, classes, perPage, search } = usePage().props as {
        groups: PaginatedGroups;
        classes: Class[];
        perPage: number;
        search: string;
    };

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [modalType, setModalType] = useState<'create' | 'edit' | 'delete' | ''>('');
    const [currentGroup, setCurrentGroup] = useState<Group | null>(null);
    const [itemsPerPage, setItemsPerPage] = useState(perPage);
    const [searchQuery, setSearchQuery] = useState(search);

    const handleOpenModal = (type: 'create' | 'edit' | 'delete', group: Group | null = null) => {
        setModalType(type);
        setCurrentGroup(
            type === 'create'
                ? { id: 0, name: '', class_id: null, capacity: null }
                : group
        );
        setIsModalOpen(true);
    };

    const handleCloseModal = () => {
        setIsModalOpen(false);
        setModalType('');
        setCurrentGroup(null);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (modalType === 'create') {
            if (currentGroup) {
                router.post('/groups', currentGroup, {
                    onSuccess: () => {
                        alert('Group created successfully!');
                        handleCloseModal();
                    },
                    onError: (errors) => {
                        console.error('Error creating group:', errors);
                    },
                });
            }
        } else if (modalType === 'edit' && currentGroup) {
            router.put(`/groups/${currentGroup.id}`, currentGroup, {
                onSuccess: () => {
                    alert('Group updated successfully!');
                    handleCloseModal();
                },
                onError: (errors) => {
                    console.error('Error updating group:', errors);
                },
            });
        } else if (modalType === 'delete' && currentGroup) {
            deleteGroup(currentGroup.id);
        }
    };

    const deleteGroup = async (groupId: number) => {
        try {
            // Close the modal before refreshing the page
            handleCloseModal();

            // Send DELETE request
            await axios.delete(`/groups/${groupId}`);

            alert("Group deleted successfully!");

            // Reload the page to reflect changes
            router.reload();
        } catch (error) {
            console.error("Error deleting group:", error);
            alert("Failed to delete group. Please try again.");
        }
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/groups', { search: searchQuery, per_page: itemsPerPage }, { preserveState: true });
    };

    const handlePerPageChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        const newPerPage = parseInt(e.target.value, 10);
        setItemsPerPage(newPerPage);
        router.get('/groups', { per_page: newPerPage, search: searchQuery }, { preserveState: true });
    };

    const handlePageChange = (url: string | null) => {
        if (url) {
            router.get(url, { per_page: itemsPerPage, search: searchQuery }, { preserveState: true });
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="Groups" />
            <div className="p-6 bg-white rounded-lg shadow-md">
                <h1 className="text-2xl font-semibold mb-4">Groups</h1>
                <div className="flex justify-between items-center mb-4">
                    <button
                        onClick={() => handleOpenModal('create')}
                        className="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600"
                    >
                        + Add Group
                    </button>
                    <form onSubmit={handleSearch} className="flex items-center space-x-2">
                        <input
                            type="text"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder="Search groups..."
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
                            Items per page:
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
                            <th className="px-4 py-2 border">ID</th>
                            <th className="px-4 py-2 border">Name</th>
                            <th className="px-4 py-2 border">Class</th>
                            <th className="px-4 py-2 border">Capacity</th>
                            <th className="px-4 py-2 border">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {groups.data.map((group) => (
                            <tr key={group.id} className="hover:bg-gray-50">
                                <td className="px-4 py-2 border">{group.id}</td>
                                <td className="px-4 py-2 border">{group.name}</td>
                                <td className="px-4 py-2 border">
                                    {classes.find((classItem) => classItem.id === group.class_id)?.name || 'N/A'}
                                </td>
                                <td className="px-4 py-2 border">{group.capacity || 'N/A'}</td>
                                <td className="px-4 py-2 border">
                                    <button
                                        onClick={() => handleOpenModal('edit', group)}
                                        className="bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-600 mr-2"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        onClick={() => handleOpenModal('delete', group)}
                                        className="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <div className="mt-4 flex justify-between items-center">
                    <p className="text-sm text-gray-600">
                        Showing {groups.data.length} of {groups.total} groups
                    </p>
                    <div className="flex space-x-2">
                        {groups.links.map((link, index) => (
                            <button
                                key={index}
                                onClick={() => handlePageChange(link.url)}
                                className={`px-3 py-1 rounded ${
                                    link.active
                                        ? 'bg-blue-500 text-white'
                                        : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                                }`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                </div>
            </div>

            {/* Modal */}
            {isModalOpen && (
                <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50">
                    <div className="bg-white p-6 rounded shadow-md" style={{ width: 'auto', maxWidth: '90%', minWidth: '300px' }}>
                        <h2 className="text-xl font-bold mb-4">
                            {modalType === 'create' && 'Add Group'}
                            {modalType === 'edit' && 'Edit Group'}
                            {modalType === 'delete' && 'Delete Group'}
                        </h2>
                        {modalType !== 'delete' ? (
                            <form onSubmit={handleSubmit}>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">Name</label>
                                    <input
                                        type="text"
                                        value={currentGroup?.name || ''}
                                        onChange={(e) =>
                                            setCurrentGroup((prev) => ({ ...prev!, name: e.target.value }))
                                        }
                                        className="w-full border rounded p-2"
                                        required
                                    />
                                </div>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">Class</label>
                                    <select
                                        value={currentGroup?.class_id || ''}
                                        onChange={(e) =>
                                            setCurrentGroup((prev) => ({
                                                ...prev!,
                                                class_id: parseInt(e.target.value, 10),
                                            }))
                                        }
                                        className="w-full border rounded p-2"
                                        required
                                    >
                                        <option value="" disabled>Select a class</option>
                                        {classes.map((classItem) => (
                                            <option key={classItem.id} value={classItem.id}>
                                                {classItem.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">Capacity</label>
                                    <input
                                        type="number"
                                        value={currentGroup?.capacity || ''}
                                        onChange={(e) =>
                                            setCurrentGroup((prev) => ({
                                                ...prev!,
                                                capacity: parseInt(e.target.value, 10),
                                            }))
                                        }
                                        className="w-full border rounded p-2"
                                        required
                                    />
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
                                <p>Are you sure you want to delete this group?</p>
                                <div className="mt-4 flex justify-end">
                                    <button
                                        onClick={handleSubmit}
                                        className="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700"
                                    >
                                        Delete
                                    </button>
                                    <button
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

export default Groups;
