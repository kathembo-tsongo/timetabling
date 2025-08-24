import React, { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface Enrollment {
  id: number;
  unit: {
    code: string;
    name: string;
  };
  semester: {
    name: string;
  };
  lecturer?: {
    first_name: string;
    last_name: string;
  };
  student?: {
    first_name: string;
    last_name: string;
    code: string;
  };
  group: {
    name: string;
  };
}

interface Props {
  enrollments?: {
    data?: Enrollment[];
  };
  userRoles?: {
    isAdmin: boolean;
    isLecturer: boolean;
    isStudent: boolean;
  };
}

export default function Enrollments({ enrollments = { data: [] }, userRoles }: Props) {
  const { auth } = usePage().props as any;
  const isAdmin = userRoles?.isAdmin || false;
  const isLecturer = userRoles?.isLecturer || false;
  const isStudent = userRoles?.isStudent || false;
  
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedSemester, setSelectedSemester] = useState('all');
  const [viewMode, setViewMode] = useState<'grid' | 'table'>('table');

  // Extract unique semesters for filter
  const semesters = [...new Set(enrollments.data?.map(e => e.semester.name) || [])];

  // Filter enrollments based on search and semester
  const filteredEnrollments = enrollments.data?.filter(enrollment => {
    const matchesSearch = searchTerm === '' || 
      enrollment.unit.code.toLowerCase().includes(searchTerm.toLowerCase()) ||
      enrollment.unit.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      (enrollment.student && 
        `${enrollment.student.first_name} ${enrollment.student.last_name}`.toLowerCase().includes(searchTerm.toLowerCase())) ||
      (enrollment.lecturer && 
        `${enrollment.lecturer.first_name} ${enrollment.lecturer.last_name}`.toLowerCase().includes(searchTerm.toLowerCase()));
    
    const matchesSemester = selectedSemester === 'all' || enrollment.semester.name === selectedSemester;
    
    return matchesSearch && matchesSemester;
  }) || [];

  // Get page title and icon based on user role
  const getPageInfo = () => {
    if (isAdmin) return { title: "All Enrollments", subtitle: "Manage student enrollments across all units", icon: "👥" };
    if (isLecturer) return { title: "My Teaching Units", subtitle: "Units you're currently teaching", icon: "👨‍🏫" };
    return { title: "My Enrollments", subtitle: "Units you're currently enrolled in", icon: "📚" };
  };

  const pageInfo = getPageInfo();

  // Stats calculation
  const stats = {
    total: filteredEnrollments.length,
    uniqueUnits: [...new Set(filteredEnrollments.map(e => e.unit.code))].length,
    uniqueSemesters: [...new Set(filteredEnrollments.map(e => e.semester.name))].length,
    uniqueStudents: isAdmin ? [...new Set(filteredEnrollments.map(e => e.student?.code))].filter(Boolean).length : null
  };

  return (
    <AuthenticatedLayout>
      <Head title={pageInfo.title} />

      <div className="min-h-screen bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          {/* Header Section */}
          <div className="mb-8">
            <div className="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
              <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                <div className="flex items-center space-x-4">
                  <div className="w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-600 rounded-2xl flex items-center justify-center text-3xl">
                    {pageInfo.icon}
                  </div>
                  <div>
                    <h1 className="text-3xl font-bold text-gray-900 mb-2">
                      {pageInfo.title}
                    </h1>
                    <p className="text-gray-600 text-lg">
                      {pageInfo.subtitle}
                    </p>
                  </div>
                </div>
                
                {/* Quick Stats */}
                <div className="mt-6 lg:mt-0 grid grid-cols-2 lg:grid-cols-4 gap-4">
                  <div className="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl p-4 text-white text-center">
                    <div className="text-2xl font-bold">{stats.total}</div>
                    <div className="text-xs opacity-90">Total Records</div>
                  </div>
                  <div className="bg-gradient-to-r from-green-500 to-green-600 rounded-xl p-4 text-white text-center">
                    <div className="text-2xl font-bold">{stats.uniqueUnits}</div>
                    <div className="text-xs opacity-90">Unique Units</div>
                  </div>
                  <div className="bg-gradient-to-r from-purple-500 to-purple-600 rounded-xl p-4 text-white text-center">
                    <div className="text-2xl font-bold">{stats.uniqueSemesters}</div>
                    <div className="text-xs opacity-90">Semesters</div>
                  </div>
                  {stats.uniqueStudents && (
                    <div className="bg-gradient-to-r from-orange-500 to-orange-600 rounded-xl p-4 text-white text-center">
                      <div className="text-2xl font-bold">{stats.uniqueStudents}</div>
                      <div className="text-xs opacity-90">Students</div>
                    </div>
                  )}
                </div>
              </div>
            </div>
          </div>

          {/* Controls Section */}
          <div className="mb-8">
            <div className="bg-white rounded-2xl shadow-lg p-6 border border-gray-100">
              <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                {/* Search and Filters */}
                <div className="flex flex-col sm:flex-row gap-4 flex-1">
                  {/* Search */}
                  <div className="relative flex-1 max-w-md">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <svg className="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                      </svg>
                    </div>
                    <input
                      type="text"
                      value={searchTerm}
                      onChange={(e) => setSearchTerm(e.target.value)}
                      placeholder="Search units, students, or lecturers..."
                      className="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-150"
                    />
                  </div>

                  {/* Semester Filter */}
                  <div className="relative">
                    <select
                      value={selectedSemester}
                      onChange={(e) => setSelectedSemester(e.target.value)}
                      className="appearance-none bg-white border border-gray-300 rounded-xl px-4 py-3 pr-8 text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-150"
                    >
                      <option value="all">All Semesters</option>
                      {semesters.map(semester => (
                        <option key={semester} value={semester}>{semester}</option>
                      ))}
                    </select>
                    <div className="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                      <svg className="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                      </svg>
                    </div>
                  </div>
                </div>

                {/* View Toggle */}
                <div className="flex items-center bg-gray-100 rounded-xl p-1">
                  <button
                    onClick={() => setViewMode('table')}
                    className={`flex items-center px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 ${
                      viewMode === 'table' 
                        ? 'bg-white text-gray-900 shadow-sm' 
                        : 'text-gray-500 hover:text-gray-700'
                    }`}
                  >
                    <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 10h18M3 6h18m-9 8h9" />
                    </svg>
                    Table
                  </button>
                  <button
                    onClick={() => setViewMode('grid')}
                    className={`flex items-center px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 ${
                      viewMode === 'grid' 
                        ? 'bg-white text-gray-900 shadow-sm' 
                        : 'text-gray-500 hover:text-gray-700'
                    }`}
                  >
                    <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                    </svg>
                    Grid
                  </button>
                </div>
              </div>
            </div>
          </div>

          {/* Content Section */}
          {filteredEnrollments.length > 0 ? (
            <div className="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
              {viewMode === 'table' ? (
                /* Table View */
                <div className="overflow-x-auto">
                  <table className="min-w-full">
                    <thead>
                      <tr className="bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
                        {isAdmin && (
                          <th className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            <div className="flex items-center space-x-1">
                              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                              </svg>
                              <span>Student</span>
                            </div>
                          </th>
                        )}
                        <th className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                          <div className="flex items-center space-x-1">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 4V2a1 1 0 011-1h8a1 1 0 011 1v2h4a1 1 0 110 2h-1v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6H3a1 1 0 010-2h4z" />
                            </svg>
                            <span>Unit Code</span>
                          </div>
                        </th>
                        <th className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                          <div className="flex items-center space-x-1">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C20.832 18.477 19.246 18 17.5 18c-1.746 0-3.332.477-4.5 1.253" />
                            </svg>
                            <span>Unit Name</span>
                          </div>
                        </th>
                        <th className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                          <div className="flex items-center space-x-1">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <span>Group</span>
                          </div>
                        </th>
                        <th className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                          <div className="flex items-center space-x-1">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3a4 4 0 118 0v4m-4 16l-4-4m0 0l-4-4m4 4V8a3 3 0 00-3-3H3" />
                            </svg>
                            <span>Semester</span>
                          </div>
                        </th>                        
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                      {filteredEnrollments.map((enrollment, index) => (
                        <tr 
                          key={enrollment.id} 
                          className={`hover:bg-blue-50 transition-colors duration-200 ${
                            index % 2 === 0 ? 'bg-white' : 'bg-gray-50'
                          }`}
                        >
                          {isAdmin && (
                            <td className="px-6 py-4 whitespace-nowrap">
                              <div className="flex items-center">
                                <div className="w-10 h-10 bg-gradient-to-br from-blue-400 to-purple-500 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                                  {enrollment.student 
                                    ? `${enrollment.student.first_name[0]}${enrollment.student.last_name[0]}`
                                    : '?'}
                                </div>
                                <div className="ml-4">
                                  <div className="text-sm font-medium text-gray-900">
                                    {enrollment.student 
                                      ? `${enrollment.student.first_name} ${enrollment.student.last_name}`
                                      : 'N/A'}
                                  </div>
                                  <div className="text-sm text-gray-500">
                                    {enrollment.student?.code || 'N/A'}
                                  </div>
                                </div>
                              </div>
                            </td>
                          )}
                          <td className="px-6 py-4 whitespace-nowrap">
                            <span className="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 border border-blue-200">
                              {enrollment.unit.code}
                            </span>
                          </td>
                          <td className="px-6 py-4">
                            <div className="text-sm font-medium text-gray-900">
                              {enrollment.unit.name}
                            </div>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <span className="inline-flex items-center px-2 py-1 rounded-lg text-xs font-medium bg-green-100 text-green-800 border border-green-200">
                              {enrollment.group?.name || 'N/A'}
                            </span>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <span className="inline-flex items-center px-2 py-1 rounded-lg text-xs font-medium bg-purple-100 text-purple-800 border border-purple-200">
                              {enrollment.semester.name}
                            </span>
                          </td>                          
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (
                /* Grid View */
                <div className="p-6">
                  <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {filteredEnrollments.map((enrollment) => (
                      <div 
                        key={enrollment.id}
                        className="bg-gradient-to-br from-white to-gray-50 rounded-xl p-6 border border-gray-200 hover:shadow-lg hover:border-blue-300 transition-all duration-300 transform hover:-translate-y-1"
                      >
                        {/* Card Header */}
                        <div className="flex items-center justify-between mb-4">
                          <span className="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-blue-100 text-blue-800 border border-blue-200">
                            {enrollment.unit.code}
                          </span>
                          <span className="inline-flex items-center px-2 py-1 rounded-lg text-xs font-medium bg-purple-100 text-purple-800 border border-purple-200">
                            {enrollment.semester.name}
                          </span>
                        </div>

                        {/* Unit Name */}
                        <h3 className="text-lg font-semibold text-gray-900 mb-3 line-clamp-2">
                          {enrollment.unit.name}
                        </h3>

                        {/* Details */}
                        <div className="space-y-3">
                          {isAdmin && enrollment.student && (
                            <div className="flex items-center space-x-3">
                              <div className="w-8 h-8 bg-gradient-to-br from-blue-400 to-purple-500 rounded-full flex items-center justify-center text-white font-semibold text-xs">
                                {`${enrollment.student.first_name[0]}${enrollment.student.last_name[0]}`}
                              </div>
                              <div>
                                <div className="text-sm font-medium text-gray-900">
                                  {`${enrollment.student.first_name} ${enrollment.student.last_name}`}
                                </div>
                                <div className="text-xs text-gray-500">{enrollment.student.code}</div>
                              </div>
                            </div>
                          )}

                          <div className="flex items-center justify-between">
                            <span className="text-sm text-gray-600">Group:</span>
                            <span className="inline-flex items-center px-2 py-1 rounded-lg text-xs font-medium bg-green-100 text-green-800 border border-green-200">
                              {enrollment.group?.name || 'N/A'}
                            </span>
                          </div>                          
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>
          ) : (
            /* Empty State */
            <div className="bg-white rounded-2xl shadow-xl border border-gray-100 p-12 text-center">
              <div className="w-24 h-24 bg-gradient-to-br from-gray-100 to-gray-200 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg className="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                </svg>
              </div>
              <h3 className="text-xl font-semibold text-gray-900 mb-2">No Enrollments Found</h3>
              <p className="text-gray-500 mb-6 max-w-md mx-auto">
                {searchTerm || selectedSemester !== 'all' 
                  ? "No enrollments match your current search criteria. Try adjusting your filters."
                  : "No enrollment records are available at this time."
                }
              </p>
              {(searchTerm || selectedSemester !== 'all') && (
                <button
                  onClick={() => {
                    setSearchTerm('');
                    setSelectedSemester('all');
                  }}
                  className="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-200"
                >
                  Clear Filters
                </button>
              )}
            </div>
          )}

          {/* Results Summary */}
          {filteredEnrollments.length > 0 && (
            <div className="mt-6 text-center">
              <p className="text-sm text-gray-600">
                Showing <span className="font-semibold text-gray-900">{filteredEnrollments.length}</span> of{' '}
                <span className="font-semibold text-gray-900">{enrollments.data?.length || 0}</span> enrollment records
                {searchTerm && ` matching "${searchTerm}"`}
                {selectedSemester !== 'all' && ` in ${selectedSemester}`}
              </p>
            </div>
          )}
        </div>
      </div>
    </AuthenticatedLayout>
  );
}