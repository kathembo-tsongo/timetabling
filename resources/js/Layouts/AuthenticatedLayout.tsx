import React from 'react';
import Sidebar from '@/components/ui/sidebar';
import Navbar from '@/components/ui/navbar';
import { usePage } from '@inertiajs/react';

interface User {
  id: number;
  name: string;
  email: string;
  // Add other user properties as needed
}

interface Auth {
  user: User;
  roles: string[];
  permissions: string[];
}

interface PageProps {
  auth: Auth;
}

interface AuthenticatedLayoutProps {
  children: React.ReactNode;
}

const AuthenticatedLayout: React.FC<AuthenticatedLayoutProps> = ({ children }) => {
  const { auth } = usePage<PageProps>().props;

  return (
    <div className="flex h-screen bg-gray-100">
      {/* Sidebar */}
      <Sidebar />

      {/* Main Content */}
      <div className="flex-1 flex flex-col">
        {/* Navbar */}
        <Navbar user={auth.user} />

        {/* Page Content */}
        <main className="flex-1 p-6 overflow-y-auto">
          {children}
        </main>
      </div>
    </div>
  );
};

export default AuthenticatedLayout;