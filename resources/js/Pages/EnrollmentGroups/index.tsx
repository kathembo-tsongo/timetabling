import React, { useState } from 'react';
import { Inertia } from '@inertiajs/inertia';
import { usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface EnrollmentGroup {
    id: number;
    name: string;
    semester_id: number;
}

const EnrollmentGroups = () => {
    const { enrollmentGroups, semesters, auth } = usePage().props as { enrollmentGroups: EnrollmentGroup[]; semesters: any[]; auth: { user: any } };
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [modalType, setModalType] = useState('');
    const [currentEnrollmentGroup, setCurrentEnrollmentGroup] = useState<EnrollmentGroup>({ id: 0, name: '', semester_id: 0 });

    const handleCreate = () => {
        setModalType('create');
        setCurrentEnrollmentGroup({ id: 0, name: '', semester_id: 0 });
        setIsModalOpen(true);
    };

    const handleEdit = (enrollmentGroup: EnrollmentGroup) => {
        setModalType('edit');
        setCurrentEnrollmentGroup(enrollmentGroup);
        setIsModalOpen(true);
    };

    const handleDelete = (enrollmentGroup: EnrollmentGroup) => {
        setModalType('delete');
        setCurrentEnrollmentGroup(enrollmentGroup);
        setIsModalOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (modalType === 'create') {
            Inertia.post('/enrollment-groups', currentEnrollmentGroup);
        } else if (modalType === 'edit') {
            Inertia.patch(`/enrollment-groups/${currentEnrollmentGroup.id}`, currentEnrollmentGroup);
        } else if (modalType === 'delete') {
            Inertia.delete(`/enrollment-groups/${currentEnrollmentGroup.id}`);
        }
        setIsModalOpen(false);
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="p-6 max-w-6xl mx-auto">
                <h1 className="text-2xl font-semibold mb-4">Manage Enrollment Groups</h1>

                {/* Create Button */}
                <div className="mb-4">
                    <button 
                        onClick={handleCreate} 
                        className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                        + Add Enrollment Group
                    </button>
                </div>

                {/* Enrollment Group Table */}
                <div className="bg-white shadow-md rounded-lg overflow-hidden">
                    <table className="min-w-full border-collapse border border-gray-200">
                        <thead className="bg-gray-100">
                            <tr className="border-b">
                                <th className="px-4 py-2 border">ID</th>
                                <th className="px-4 py-2 border">Name</th>
                                <th className="px-4 py-2 border">Semester</th>
                                <th className="px-4 py-2 border">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {enrollmentGroups.length > 0 ? (
                                enrollmentGroups.map((enrollmentGroup) => (
                                    <tr key={enrollmentGroup.id} className="border-b hover:bg-gray-50">
                                        <td className="px-4 py-2 border text-center">{enrollmentGroup.id}</td>
                                        <td className="px-4 py-2 border">{enrollmentGroup.name}</td>
                                        <td className="px-4 py-2 border">{semesters.find(semester => semester.id === enrollmentGroup.semester_id)?.name}</td>
                                        <td className="px-4 py-2 border flex space-x-2">
                                            <button 
                                                onClick={() => handleEdit(enrollmentGroup)} 
                                                className="bg-blue-600 text-white px-3 py-1 rounded-md hover:bg-green-700 transition">
                                                Edit
                                            </button>
                                            <button 
                                                onClick={() => handleDelete(enrollmentGroup)} 
                                                className="bg-red-600 text-white px-3 py-1 rounded-md hover:bg-red-700 transition">
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={4} className="px-4 py-3 text-center text-gray-500">No enrollment groups found.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Modal for Create/Edit/Delete */}
                {isModalOpen && (
                    <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50">
                        <div className="bg-white p-6 rounded shadow-md w-96">
                            <h2 className="text-xl font-bold mb-4">
                                {modalType === 'create' ? 'Add Enrollment Group' : modalType === 'edit' ? 'Edit Enrollment Group' : 'Confirm Delete'}
                            </h2>
                            <form onSubmit={handleSubmit}>
                                {modalType !== 'delete' ? (
                                    <>
                                        <label className="block text-sm font-medium text-gray-700">Name</label>
                                        <input
                                            type="text"
                                            value={currentEnrollmentGroup.name}
                                            onChange={(e) => setCurrentEnrollmentGroup({ ...currentEnrollmentGroup, name: e.target.value })}
                                            className="w-full border rounded p-2 mt-1"
                                            required
                                        />
                                        <label className="block text-sm font-medium text-gray-700 mt-4">Semester</label>
                                        <select
                                            value={currentEnrollmentGroup.semester_id}
                                            onChange={(e) => setCurrentEnrollmentGroup({ ...currentEnrollmentGroup, semester_id: parseInt(e.target.value) })}
                                            className="w-full border rounded p-2 mt-1"
                                            required
                                        >
                                            <option value="">Select Semester</option>
                                            {semesters.map((semester) => (
                                                <option key={semester.id} value={semester.id}>{semester.name}</option>
                                            ))}
                                        </select>
                                    </>
                                ) : (
                                    <p>Are you sure you want to delete <strong>{currentEnrollmentGroup.name}</strong>?</p>
                                )}
                                <div className="mt-4 flex justify-end space-x-2">
                                    <button type="submit" className="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 transition">
                                        {modalType === 'delete' ? 'Confirm' : 'Save'}
                                    </button>
                                    <button 
                                        type="button" 
                                        onClick={() => setIsModalOpen(false)} 
                                        className="bg-gray-400 text-white px-3 py-1 rounded hover:bg-gray-500 transition">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
};

export default EnrollmentGroups;
