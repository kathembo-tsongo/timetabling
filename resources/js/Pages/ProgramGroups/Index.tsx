import React, { useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface Program {
    id: number;
    name: string;
}

interface ProgramGroup {
    id: number;
    name: string;
    description: string;
    program_id: number | null; // Added program_id field
    group: string; // Added group field
    capacity: number | null; // Added capacity field
}

interface PaginationLinks {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedProgramGroups {
    data: ProgramGroup[];
    links: PaginationLinks[];
    total: number;
    per_page: number;
    current_page: number;
}

const ProgramGroups = () => {
    const { programGroups, programs, perPage, search } = usePage().props as { 
        programGroups: PaginatedProgramGroups; 
        programs: Program[]; // List of programs passed from the backend
        perPage: number; 
        search: string; 
    };

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [modalType, setModalType] = useState<'create' | 'edit' | 'delete' | ''>('');
    const [currentProgramGroup, setCurrentProgramGroup] = useState<ProgramGroup | null>(null);
    const [itemsPerPage, setItemsPerPage] = useState(perPage);
    const [searchQuery, setSearchQuery] = useState(search);

    const handleOpenModal = (type: 'create' | 'edit' | 'delete', programGroup: ProgramGroup | null = null) => {
        setModalType(type);
        setCurrentProgramGroup(
            type === 'create'
                ? { id: 0, name: '', description: '', program_id: null, group: '', capacity: null }
                : programGroup
        );
        setIsModalOpen(true);
    };

    const handleCloseModal = () => {
        setIsModalOpen(false);
        setModalType('');
        setCurrentProgramGroup(null);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (modalType === 'create') {
            if (currentProgramGroup) {
                router.post('/program-groups', currentProgramGroup, {
                    onSuccess: () => {
                        alert('Program group created successfully!');
                        handleCloseModal();
                    },
                    onError: (errors) => {
                        console.error('Error creating program group:', errors);
                    },
                });
            }
        } else if (modalType === 'edit' && currentProgramGroup) {
            router.put(`/program-groups/${currentProgramGroup.id}`, currentProgramGroup, {
                onSuccess: () => {
                    alert('Program group updated successfully!');
                    handleCloseModal();
                },
                onError: (errors) => {
                    console.error('Error updating program group:', errors);
                },
            });
        } else if (modalType === 'delete' && currentProgramGroup) {
            router.delete(`/program-groups/${currentProgramGroup.id}`, {
                onSuccess: () => {
                    alert('Program group deleted successfully!');
                    handleCloseModal();
                },
                onError: (errors) => {
                    console.error('Error deleting program group:', errors);
                },
            });
        }
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/program-groups', { search: searchQuery, per_page: itemsPerPage }, { preserveState: true });
    };

    const handlePerPageChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        const newPerPage = parseInt(e.target.value, 10);
        setItemsPerPage(newPerPage);
        router.get('/program-groups', { per_page: newPerPage, search: searchQuery }, { preserveState: true });
    };

    const handlePageChange = (url: string | null) => {
        if (url) {
            router.get(url, { per_page: itemsPerPage, search: searchQuery }, { preserveState: true });
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="Program Groups" />
            <div className="p-6 bg-white rounded-lg shadow-md">
                <h1 className="text-2xl font-semibold mb-4">Program Groups</h1>
                <div className="flex justify-between items-center mb-4">
                    <button
                        onClick={() => handleOpenModal('create')}
                        className="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600"
                    >
                        + Add Program Group
                    </button>
                    <form onSubmit={handleSearch} className="flex items-center space-x-2">
                        <input
                            type="text"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder="Search program groups..."
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
                            <th className="px-4 py-2 border">Description</th>
                            <th className="px-4 py-2 border">Program</th>
                            <th className="px-4 py-2 border">Group</th>
                            <th className="px-4 py-2 border">Capacity</th>
                            <th className="px-4 py-2 border">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {programGroups.data.map((programGroup) => (
                            <tr key={programGroup.id} className="hover:bg-gray-50">
                                <td className="px-4 py-2 border">{programGroup.id}</td>
                                <td className="px-4 py-2 border">{programGroup.name}</td>
                                <td className="px-4 py-2 border">{programGroup.description}</td>
                                <td className="px-4 py-2 border">
                                    {programs.find((program) => program.id === programGroup.program_id)?.name || 'N/A'}
                                </td>
                                <td className="px-4 py-2 border">{programGroup.group}</td>
                                <td className="px-4 py-2 border">{programGroup.capacity || 'N/A'}</td>
                                <td className="px-4 py-2 border">
                                    <button
                                        onClick={() => handleOpenModal('edit', programGroup)}
                                        className="bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-600 mr-2"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        onClick={() => handleOpenModal('delete', programGroup)}
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
                        Showing {programGroups.data.length} of {programGroups.total} program groups
                    </p>
                    <div className="flex space-x-2">
                        {programGroups.links.map((link, index) => (
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
                            {modalType === 'create' && 'Add Program Group'}
                            {modalType === 'edit' && 'Edit Program Group'}
                            {modalType === 'delete' && 'Delete Program Group'}
                        </h2>
                        {modalType !== 'delete' ? (
                            <form onSubmit={handleSubmit}>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">Name</label>
                                    <input
                                        type="text"
                                        value={currentProgramGroup?.name || ''}
                                        onChange={(e) =>
                                            setCurrentProgramGroup((prev) => ({ ...prev!, name: e.target.value }))
                                        }
                                        className="w-full border rounded p-2"
                                        required
                                    />
                                </div>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">Description</label>
                                    <textarea
                                        value={currentProgramGroup?.description || ''}
                                        onChange={(e) =>
                                            setCurrentProgramGroup((prev) => ({ ...prev!, description: e.target.value }))
                                        }
                                        className="w-full border rounded p-2"
                                        required
                                    />
                                </div>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">Program</label>
                                    <select
                                        value={currentProgramGroup?.program_id || ''}
                                        onChange={(e) =>
                                            setCurrentProgramGroup((prev) => ({
                                                ...prev!,
                                                program_id: parseInt(e.target.value, 10),
                                            }))
                                        }
                                        className="w-full border rounded p-2"
                                        required
                                    >
                                        <option value="" disabled>Select a program</option>
                                        {programs.map((program) => (
                                            <option key={program.id} value={program.id}>
                                                {program.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">Group</label>
                                    <input
                                        type="text"
                                        value={currentProgramGroup?.group || ''}
                                        onChange={(e) =>
                                            setCurrentProgramGroup((prev) => ({ ...prev!, group: e.target.value }))
                                        }
                                        className="w-full border rounded p-2"
                                        required
                                    />
                                </div>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700">Capacity</label>
                                    <input
                                        type="number"
                                        value={currentProgramGroup?.capacity || ''}
                                        onChange={(e) =>
                                            setCurrentProgramGroup((prev) => ({
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
                                <p>Are you sure you want to delete this program group?</p>
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

export default ProgramGroups;
