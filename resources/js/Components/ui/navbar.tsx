import React from 'react';
import { Link, useForm } from '@inertiajs/react';
import { LogOut, User } from 'lucide-react';

interface NavbarProps {
    user: {
        name: string;
        code: string;
    };
}

const Navbar: React.FC<NavbarProps> = ({ user }) => {
    const { post } = useForm();

    const handleLogout = () => {
        post(route('logout')); // Calls Laravel's logout route
    };

    return (
        <nav className="bg-blue-800 text-white p-6 shadow-md flex justify-between items-center">
            <div className="flex items-center space-x-2">
            <img src="/images/strathmore.png" alt="Logo" className="h-10 w-10 rounded-full" />
            
            </div>

            <div className="flex items-center space-x-6">
            <div className="flex flex-col text-right">
                <span className="text-xs text-gray-300 font-bold">{user.code}</span>
            </div>

            <Link href="/profile" className="flex items-center space-x-2 hover:text-gray-300">
                <User className="h-5 w-5" />
                <span className="font-bold">Profile</span>
            </Link>

            {/* Logout Button */}
            <button
                onClick={handleLogout}
                className="bg-red-600 hover:bg-red-700 px-4 py-2 rounded text-white flex items-center space-x-2"
            >
                <LogOut className="h-5 w-5" />
                <span className="font-bold">Logout</span>
            </button>
            </div>
        </nav>
    );
};

export default Navbar;
