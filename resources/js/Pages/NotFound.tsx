import React from 'react';
import { Link } from '@inertiajs/react';

const NotFound = () => {
    return (
        <div className="flex flex-col items-center justify-center h-screen bg-gray-100">
            <h1 className="text-4xl font-bold text-gray-800 mb-4">404 - Page Not Found</h1>
            <p className="text-gray-600 mb-6">The page you are looking for does not exist.</p>
            <Link
                href="/"
                className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition"
            >
                Go Back to the Login
            </Link>
        </div>
    );
};

export default NotFound;
