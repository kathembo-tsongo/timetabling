import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="min-h-screen flex flex-col justify-center items-center bg-blue-500 px-4">            
             {/* Welcome Message */}
            <h2 className="text-2xl font-bold text-white mt-2">Welcome Back</h2>
            <p className="text-gray-200 text-sm">Sign in to continue</p>

            {/* Login Form Container */}
            <div className="w-full sm:max-w-md bg-white shadow-lg rounded-lg px-8 py-6 mt-4">
                {children}
            </div>
        </div>
    );
}
