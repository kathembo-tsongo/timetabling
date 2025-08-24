import React from 'react';
import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

const FacultyAdminDashboard = () => {
    return (
        <AuthenticatedLayout>
            <Head title="Faculty Admin Dashboard" />
            <div className="p-6 bg-white rounded-lg shadow-md">
                <h1 className="text-2xl font-semibold mb-4">Faculty Admin Dashboard</h1>
                <p className="text-gray-700">Welcome to the Faculty Admin Dashboard. Use the sidebar to navigate through the available options.</p>
            </div>
        </AuthenticatedLayout>
    );
};

export default FacultyAdminDashboard;
