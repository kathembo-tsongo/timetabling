import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import RoleAwareComponent from '@/Components/RoleAwareComponent';

export default function Dashboard() {
  return (
    <AuthenticatedLayout>
      <Head title="Dashboard" />
      
      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div className="p-6 text-gray-900">
              <h1 className="text-2xl font-semibold mb-6">Dashboard</h1>
              
              {/* Common section visible to all users */}
              <div className="mb-8">
                <h2 className="text-xl font-medium mb-4">Quick Actions</h2>
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                  
                  {/* View Timetable - Available to all authenticated users */}
                  <div className="bg-blue-50 p-4 rounded-lg shadow">
                    <h3 className="font-medium">My Timetable</h3>
                    <p className="text-sm text-gray-600 mt-1">View your personal timetable</p>
                  </div>
                  
                  {/* Download Timetable - Available to users with specific permission */}
                  <RoleAwareComponent requiredPermissions={['download-own-timetable']}>
                    <div className="bg-green-50 p-4 rounded-lg shadow">
                      <h3 className="font-medium">Download Timetable</h3>
                      <p className="text-sm text-gray-600 mt-1">Download your timetable as PDF</p>
                    </div>
                  </RoleAwareComponent>
                </div>
              </div>
              
              {/* Admin Section */}
              <RoleAwareComponent requiredRoles={['Admin']}>
                <div className="mb-8">
                  <h2 className="text-xl font-medium mb-4">Administration</h2>
                  <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div className="bg-purple-50 p-4 rounded-lg shadow">
                      <h3 className="font-medium">User Management</h3>
                      <p className="text-sm text-gray-600 mt-1">Manage system users</p>
                    </div>
                    <div className="bg-purple-50 p-4 rounded-lg shadow">
                      <h3 className="font-medium">Role Management</h3>
                      <p className="text-sm text-gray-600 mt-1">Manage roles and permissions</p>
                    </div>
                    <div className="bg-purple-50 p-4 rounded-lg shadow">
                      <h3 className="font-medium">System Settings</h3>
                      <p className="text-sm text-gray-600 mt-1">Configure system settings</p>
                    </div>
                  </div>
                </div>
              </RoleAwareComponent>
              
              {/* Exam Office Section */}
              <RoleAwareComponent requiredRoles={['Exam office']}>
                <div className="mb-8">
                  <h2 className="text-xl font-medium mb-4">Exam Office</h2>
                  <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div className="bg-amber-50 p-4 rounded-lg shadow">
                      <h3 className="font-medium">Manage Timetable</h3>
                      <p className="text-sm text-gray-600 mt-1">Create and edit exam timetables</p>
                    </div>
                    <div className="bg-amber-50 p-4 rounded-lg shadow">
                      <h3 className="font-medium">Manage Classrooms</h3>
                      <p className="text-sm text-gray-600 mt-1">Assign and manage classrooms</p>
                    </div>
                    <div className="bg-amber-50 p-4 rounded-lg shadow">
                      <h3 className="font-medium">Resolve Conflicts</h3>
                      <p className="text-sm text-gray-600 mt-1">Identify and resolve scheduling conflicts</p>
                    </div>
                  </div>
                </div>
              </RoleAwareComponent>
              
              {/* School Admin Section */}
              <RoleAwareComponent requiredRoles={['School Admin']}>
                <div className="mb-8">
                  <h2 className="text-xl font-medium mb-4">School Administration</h2>
                  <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div className="bg-teal-50 p-4 rounded-lg shadow">
                      <h3 className="font-medium">Manage Units</h3>
                      <p className="text-sm text-gray-600 mt-1">Create and edit course units</p>
                    </div>
                    <div className="bg-teal-50 p-4 rounded-lg shadow">
                      <h3 className="font-medium">Manage Enrollments</h3>
                      <p className="text-sm text-gray-600 mt-1">Manage student enrollments</p>
                    </div>
                    <div className="bg-teal-50 p-4 rounded-lg shadow">
                      <h3 className="font-medium">School Reports</h3>
                      <p className="text-sm text-gray-600 mt-1">View school-specific reports</p>
                    </div>
                  </div>
                </div>
              </RoleAwareComponent>
              
              {/* Lecturer Section */}
              <RoleAwareComponent requiredRoles={['Lecturer']}>
                <div className="mb-8">
                  <h2 className="text-xl font-medium mb-4">Teaching</h2>
                  <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div className="bg-blue-50 p-4 rounded-lg shadow">
                      <h3 className="font-medium">My Classes</h3>
                      <p className="text-sm text-gray-600 mt-1">View your assigned classes</p>
                    </div>
                    <div className="bg-blue-50 p-4 rounded-lg shadow">
                      <h3 className="font-medium">Exam Schedule</h3>
                      <p className="text-sm text-gray-600 mt-1">View your exam supervision schedule</p>
                    </div>
                  </div>
                </div>
              </RoleAwareComponent>
              
              {/* Student Section */}
              <RoleAwareComponent requiredRoles={['Student']}>
                <div className="mb-8">
                  <h2 className="text-xl font-medium mb-4">My Studies</h2>
                  <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div className="bg-indigo-50 p-4 rounded-lg shadow">
                      <h3 className="font-medium">My Enrollments</h3>
                      <p className="text-sm text-gray-600 mt-1">View your course enrollments</p>
                    </div>
                    <div className="bg-indigo-50 p-4 rounded-lg shadow">
                      <h3 className="font-medium">Exam Schedule</h3>
                      <p className="text-sm text-gray-600 mt-1">View your upcoming exams</p>
                    </div>
                  </div>
                </div>
              </RoleAwareComponent>
              
              {/* Permission-based section */}
              <RoleAwareComponent requiredPermissions={['generate-reports']}>
                <div className="mb-8">
                  <h2 className="text-xl font-medium mb-4">Reports</h2>
                  <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div className="bg-rose-50 p-4 rounded-lg shadow">
                      <h3 className="font-medium">Generate Reports</h3>
                      <p className="text-sm text-gray-600 mt-1">Create custom reports</p>
                    </div>
                  </div>
                </div>
              </RoleAwareComponent>
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}