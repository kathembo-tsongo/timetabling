import React, { useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface Semester {
    id: number;
    name: string;
}

interface Program {
    id: number;
    name: string;
}

interface Class {
    id: number;
    name: string;
    semester_id: number | null;
    program_id: number | null;
}

interface PaginationLinks {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedClasses {
    data: Class[];
    links: PaginationLinks[];
    total: number;
    per_page: number;
    current_page: number;
}

interface PageProps {
    classes: PaginatedClasses;
    semesters: Semester[];
    programs: Program[];
    perPage: number;
    search: string;
    auth?: {
        user?: {
            permissions?: string[];
        };
    };
    userPermissions?: string[];
    flash?: {
        success?: string;
        error?: string;
    };
}

const Classes = () => {
    const { 
        classes, 
        semesters, 
        programs, 
        perPage, 
        search, 
        userPermissions = [],
        auth,
        flash
    } = usePage().props as PageProps;

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [modalType, setModalType] = useState<'create' | 'edit' | 'delete' | ''>('');
    const [currentClass, setCurrentClass] = useState<Class | null>(null);
    const [itemsPerPage, setItemsPerPage] = useState(perPage);
    const [searchQuery, setSearchQuery] = useState(search || '');
    const [isSubmitting, setIsSubmitting] = useState(false);

    // Permission checks - handle multiple possible permission sources
    const permissions = userPermissions.length > 0 
        ? userPermissions 
        : (auth?.user?.permissions || []);

    const canCreate = permissions.some(permission => 
        permission.includes('create') || 
        permission.includes('manage-faculty-classes-sces') ||
        permission.includes('manage-classes')
    );

    const canEdit = permissions.some(permission => 
        permission.includes('edit') || 
        permission.includes('update') ||
        permission.includes('manage-faculty-classes-sces') ||
        permission.includes('manage-classes')
    );

    const canDelete = permissions.some(permission => 
        permission.includes('delete') || 
        permission.includes('manage-faculty-classes-sces') ||
        permission.includes('manage-classes')
    );

    const handleOpenModal = (type: 'create' | 'edit' | 'delete', classItem: Class | null = null) => {
        // Check permissions before opening modal
        if (type === 'create' && !canCreate) {
            alert('You do not have permission to create classes.');
            return;
        }
        if (type === 'edit' && !canEdit) {
            alert('You do not have permission to edit classes.');
            return;
        }
        if (type === 'delete' && !canDelete) {
            alert('You do not have permission to delete classes.');
            return;
        }

        setModalType(type);
        setCurrentClass(
            type === 'create'
                ? { id: 0, name: '', semester_id: null, program_id: null }
                : classItem
        );
        setIsModalOpen(true);
    };

    const handleCloseModal = () => {
        setIsModalOpen(false);
        setModalType('');
        setCurrentClass(null);
        setIsSubmitting(false);
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        
        if (isSubmitting) return;
        setIsSubmitting(true);

        try {
            if (modalType === 'create') {
                if (currentClass) {
                    await router.post(route('faculty.classes.sces.bbit.store'), currentClass, {
                        onSuccess: () => {
                            handleCloseModal();
                        },
                        onError: (errors) => {
                            console.error('Error creating class:', errors);
                            setIsSubmitting(false);
                        },
                    });
                }
            } else if (modalType === 'edit' && currentClass) {
                await router.put(route('faculty.classes.sces.bbit.update', currentClass.id), currentClass, {
                    onSuccess: () => {
                        handleCloseModal();
                    },
                    onError: (errors) => {
                        console.error('Error updating class:', errors);
                        setIsSubmitting(false);
                    },
                });
            } else if (modalType === 'delete' && currentClass) {
                await router.delete(route('faculty.classes.sces.bbit.destroy', currentClass.id), {
                    onSuccess: () => {
                        handleCloseModal();
                    },
                    onError: (errors) => {
                        console.error('Error deleting class:', errors);
                        setIsSubmitting(false);
                    },
                });
            }
        } catch (error) {
            console.error('Unexpected error:', error);
            setIsSubmitting(false);
        }
    };

    const handlePaginationClick = (url: string | null) => {
        if (!url) return;
        
        const params: Record<string, any> = {};
        if (searchQuery) params.search = searchQuery;
        if (itemsPerPage !== perPage) params.per_page = itemsPerPage;
        
        router.get(url, params, { 
            preserveState: true,
            preserveScroll: true 
        });
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(route('faculty.classes.sces.bbit'), { 
            search: searchQuery, 
            per_page: itemsPerPage 
        }, { 
            preserveState: true 
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="BBIT Classes - SCES" />
            <div className="p-6 bg-white rounded-lg shadow-md">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-2xl font-semibold text-gray-900">BBIT Classes</h1>
                        <p className="text-sm text-gray-600">School of Computing and Engineering Sciences (SCES)</p>
                    </div>
                    <div className="flex items-center space-x-4">
                        <span className="text-sm text-gray-500">Total: {classes.total} classes</span>
                    </div>
                </div>
                
                {/* Flash Messages */}
                {flash?.success && (
                    <div className="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                        {flash.error}
                    </div>
                )}

                <div className="flex flex-col md:flex-row md:justify-between md:items-center mb-6 space-y-4 md:space-y-0">
                    {canCreate && (
                        <button
                            onClick={() => handleOpenModal('create')}
                            className="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50"
                            disabled={isSubmitting}
                        >
                            <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                            </svg>
                            Add Class
                        </button>
                    )}
                    
                    <form onSubmit={handleSearch} className="flex items-center space-x-2">
                        <input
                            type="text"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder="Search classes..."
                            className="block w-64 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                        />
                        <select
                            value={itemsPerPage}
                            onChange={(e) => setItemsPerPage(parseInt(e.target.value))}
                            className="block px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                        >
                            <option value={10}>10 per page</option>
                            <option value={25}>25 per page</option>
                            <option value={50}>50 per page</option>
                        </select>
                        <button
                            type="submit"
                            className="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                        >
                            <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            Search
                        </button>
                    </form>
                </div>

                {classes.data.length === 0 ? (
                    <div className="text-center py-12">
                        <svg className="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                        <h3 className="mt-2 text-sm font-medium text-gray-900">No classes</h3>
                        <p className="mt-1 text-sm text-gray-500">
                            {searchQuery ? `No classes found matching "${searchQuery}"` : 'Get started by creating your first class.'}
                        </p>
                        {canCreate && !searchQuery && (
                            <div className="mt-6">
                                <button
                                    onClick={() => handleOpenModal('create')}
                                    className="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700"
                                >
                                    <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                    </svg>
                                    Add Class
                                </button>
                            </div>
                        )}
                    </div>
                ) : (
                    <div className="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                        <table className="min-w-full divide-y divide-gray-300">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Class Name
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Semester
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Program
                                    </th>
                                    {(canEdit || canDelete) && (
                                        <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    )}
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {classes.data.map((classItem) => (
                                    <tr key={classItem.id} className="hover:bg-gray-50">
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="text-sm font-medium text-gray-900">{classItem.name}</div>
                                            <div className="text-sm text-gray-500">ID: {classItem.id}</div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                {semesters.find((semester) => semester.id === classItem.semester_id)?.name || 'N/A'}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                {programs.find((program) => program.id === classItem.program_id)?.name || 'N/A'}
                                            </span>
                                        </td>
                                        {(canEdit || canDelete) && (
                                            <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <div className="flex justify-end space-x-2">
                                                    {canEdit && (
                                                        <button
                                                            onClick={() => handleOpenModal('edit', classItem)}
                                                            className="inline-flex items-center px-3 py-1 border border-transparent text-sm leading-4 font-medium rounded-md text-yellow-700 bg-yellow-100 hover:bg-yellow-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 disabled:opacity-50"
                                                            disabled={isSubmitting}
                                                        >
                                                            Edit
                                                        </button>
                                                    )}
                                                    {canDelete && (
                                                        <button
                                                            onClick={() => handleOpenModal('delete', classItem)}
                                                            className="inline-flex items-center px-3 py-1 border border-transparent text-sm leading-4 font-medium rounded-md text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50"
                                                            disabled={isSubmitting}
                                                        >
                                                            Delete
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {/* Pagination */}
                {classes.total > 0 && (
                    <div className="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                        <div className="flex-1 flex justify-between sm:hidden">
                            {classes.links[0]?.url && (
                                <button
                                    onClick={() => handlePaginationClick(classes.links[0].url)}
                                    className="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                >
                                    Previous
                                </button>
                            )}
                            {classes.links[classes.links.length - 1]?.url && (
                                <button
                                    onClick={() => handlePaginationClick(classes.links[classes.links.length - 1].url)}
                                    className="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                >
                                    Next
                                </button>
                            )}
                        </div>
                        <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p className="text-sm text-gray-700">
                                    Showing <span className="font-medium">{((classes.current_page - 1) * classes.per_page) + 1}</span> to{' '}
                                    <span className="font-medium">
                                        {Math.min(classes.current_page * classes.per_page, classes.total)}
                                    </span>{' '}
                                    of <span className="font-medium">{classes.total}</span> results
                                </p>
                            </div>
                            <div>
                                <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                    {classes.links.map((link, index) => (
                                        <button
                                            key={index}
                                            onClick={() => handlePaginationClick(link.url)}
                                            disabled={!link.url}
                                            className={`relative inline-flex items-center px-4 py-2 border text-sm font-medium ${
                                                link.active
                                                    ? 'z-10 bg-blue-50 border-blue-500 text-blue-600'
                                                    : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                                            } ${!link.url ? 'opacity-50 cursor-not-allowed' : ''} ${
                                                index === 0 ? 'rounded-l-md' : ''
                                            } ${index === classes.links.length - 1 ? 'rounded-r-md' : ''}`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </nav>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            {/* Modal */}
            {isModalOpen && (
                <div className="fixed inset-0 z-50 overflow-y-auto">
                    <div className="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div className="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onClick={handleCloseModal}></div>
                        
                        <span className="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                        
                        <div className="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                            <div className="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <h3 className="text-lg leading-6 font-medium text-gray-900 mb-4">
                                    {modalType === 'create' && 'Add New Class'}
                                    {modalType === 'edit' && 'Edit Class'}
                                    {modalType === 'delete' && 'Delete Class'}
                                </h3>
                                
                                {modalType !== 'delete' ? (
                                    <form onSubmit={handleSubmit} className="space-y-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                Class Name <span className="text-red-500">*</span>
                                            </label>
                                            <input
                                                type="text"
                                                value={currentClass?.name || ''}
                                                onChange={(e) =>
                                                    setCurrentClass((prev) => ({ ...prev!, name: e.target.value }))
                                                }
                                                className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                                required
                                                disabled={isSubmitting}
                                                placeholder="Enter class name"
                                            />
                                        </div>
                                        
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                Semester <span className="text-red-500">*</span>
                                            </label>
                                            <select
                                                value={currentClass?.semester_id || ''}
                                                onChange={(e) =>
                                                    setCurrentClass((prev) => ({
                                                        ...prev!,
                                                        semester_id: e.target.value ? parseInt(e.target.value, 10) : null,
                                                    }))
                                                }
                                                className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                                required
                                                disabled={isSubmitting}
                                            >
                                                <option value="">Select a semester</option>
                                                {semesters.map((semester) => (
                                                    <option key={semester.id} value={semester.id}>
                                                        {semester.name}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                        
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                Program <span className="text-red-500">*</span>
                                            </label>
                                            <select
                                                value={currentClass?.program_id || ''}
                                                onChange={(e) =>
                                                    setCurrentClass((prev) => ({
                                                        ...prev!,
                                                        program_id: e.target.value ? parseInt(e.target.value, 10) : null,
                                                    }))
                                                }
                                                className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                                required
                                                disabled={isSubmitting}
                                            >
                                                <option value="">Select a program</option>
                                                {programs.map((program) => (
                                                    <option key={program.id} value={program.id}>
                                                        {program.name}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                    </form>
                                ) : (
                                    <div>
                                        <p className="text-sm text-gray-500 mb-4">
                                            Are you sure you want to delete the class "{currentClass?.name}"? This action cannot be undone.
                                        </p>
                                    </div>
                                )}
                            </div>
                            
                            <div className="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                {modalType !== 'delete' ? (
                                    <>
                                        <button
                                            type="submit"
                                            onClick={handleSubmit}
                                            className="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
                                            disabled={isSubmitting}
                                        >
                                            {isSubmitting ? 'Processing...' : (modalType === 'create' ? 'Create Class' : 'Update Class')}
                                        </button>
                                        <button
                                            type="button"
                                            onClick={handleCloseModal}
                                            className="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
                                            disabled={isSubmitting}
                                        >
                                            Cancel
                                        </button>
                                    </>
                                ) : (
                                    <>
                                        <button
                                            onClick={handleSubmit}
                                            className="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
                                            disabled={isSubmitting}
                                        >
                                            {isSubmitting ? 'Deleting...' : 'Delete Class'}
                                        </button>
                                        <button
                                            onClick={handleCloseModal}
                                            className="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
                                            disabled={isSubmitting}
                                        >
                                            Cancel
                                        </button>
                                    </>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
};

export default Classes;