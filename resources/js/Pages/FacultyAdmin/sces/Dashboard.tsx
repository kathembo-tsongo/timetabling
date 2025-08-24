import React from 'react';
import { PageProps } from '@inertiajs/inertia-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import RoleAwareComponent from '@/Components/RoleAwareComponent';

type Statistic = {
  count: number;
  growthRate: number;
  period: string;
};

type Program = {
  name: string;
  units: number;
  code: string;
};

type FacultyInfo = {
  name: string;
  code: string;
  totalPrograms: number;
  totalClasses: number;
};

type PendingApprovals = {
  enrollments: number;
  lecturerRequests: number;
  unitChanges: number;
};

type RecentActivity = {
  created_at: string;
  unit_name?: string;
  unit_code?: string;
  first_name?: string;
  last_name?: string;
};

type DashboardProps = PageProps & {
  schoolCode: string;
  schoolName: string;
  currentSemester: { name?: string } | null;
  statistics: {
    totalStudents: Statistic;
    totalLecturers: Statistic;
    totalUnits: Statistic;
    activeEnrollments: Statistic;
  };
  programs: Program[];
  facultyInfo: FacultyInfo;
  pendingApprovals: PendingApprovals;
  recentActivities: RecentActivity[];
  userPermissions: string[];
  userRoles: string[];
  error?: string;
};

const Dashboard: React.FC<DashboardProps> = ({
  schoolCode,
  schoolName,
  currentSemester,
  statistics,
  programs,
  facultyInfo,
  pendingApprovals,
  recentActivities,
  userPermissions,
  userRoles,
  error,
}) => {
  // Helper function to format statistic keys
  const formatStatKey = (key: string) => {
    return key.replace(/([A-Z])/g, ' $1').replace(/^./, str => str.toUpperCase());
  };

  // Helper function to get growth color
  const getGrowthColor = (rate: number) => {
    if (rate > 0) return 'text-green-600';
    if (rate < 0) return 'text-red-600';
    return 'text-gray-600';
  };

  // Helper function to get growth icon
  const getGrowthIcon = (rate: number) => {
    if (rate > 0) return '↗';
    if (rate < 0) return '↘';
    return '→';
  };

  return (
    <AuthenticatedLayout>
      <Head title={`${schoolName} Dashboard`} />
      
      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          
          {/* Header Section */}
          <div className="mb-8">
            <div className="bg-gradient-to-r from-blue-600 to-blue-800 rounded-lg shadow-lg p-6 text-white">
              <h1 className="text-3xl font-bold mb-2">{schoolName}</h1>
              <p className="text-blue-100 text-lg">Faculty Code: {schoolCode}</p>
              
              {/* Current Semester Banner */}
              <div className="mt-4 bg-white/10 backdrop-blur-sm rounded-lg p-4">
                <h2 className="text-lg font-semibold mb-1">Current Semester</h2>
                <div className="text-xl font-bold">
                  {currentSemester?.name ? (
                    <span className="text-yellow-300">{currentSemester.name}</span>
                  ) : (
                    <span className="text-red-300">No active semester</span>
                  )}
                </div>
              </div>
            </div>
          </div>

          {/* Error Alert */}
          {error && (
            <div className="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg shadow">
              <div className="flex">
                <div className="flex-shrink-0">
                  <span className="text-red-500 text-xl">⚠</span>
                </div>
                <div className="ml-3">
                  <p className="text-sm font-medium">{error}</p>
                </div>
              </div>
            </div>
          )}

          {/* Statistics Cards Grid */}
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            {Object.entries(statistics).map(([key, stat]) => (
              <div key={key} className="bg-white overflow-hidden shadow-lg rounded-lg hover:shadow-xl transition-shadow duration-300">
                <div className="p-6">
                  <div className="flex items-center">
                    <div className="flex-shrink-0">
                      <div className="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                        <span className="text-white text-sm font-bold">
                          {key === 'totalStudents' ? '👥' : 
                           key === 'totalLecturers' ? '👨‍🏫' : 
                           key === 'totalUnits' ? '📚' : '📋'}
                        </span>
                      </div>
                    </div>
                    <div className="ml-5 w-0 flex-1">
                      <dl>
                        <dt className="text-sm font-medium text-gray-500 truncate">
                          {formatStatKey(key)}
                        </dt>
                        <dd className="flex items-baseline">
                          <div className="text-2xl font-semibold text-gray-900">
                            {stat.count.toLocaleString()}
                          </div>
                          <div className={`ml-2 flex items-baseline text-sm font-semibold ${getGrowthColor(stat.growthRate)}`}>
                            <span>{getGrowthIcon(stat.growthRate)}</span>
                            <span className="ml-1">{Math.abs(stat.growthRate)}%</span>
                          </div>
                        </dd>
                        <dd className="text-xs text-gray-500 mt-1">
                          {stat.period}
                        </dd>
                      </dl>
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>

          {/* Main Content Grid */}
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            {/* Left Column */}
            <div className="lg:col-span-2 space-y-8">
              
              {/* Programs Section */}
              <div className="bg-white overflow-hidden shadow-lg rounded-lg">
                <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
                  <h3 className="text-lg font-semibold text-gray-900 flex items-center">
                    <span className="mr-2">🎓</span>
                    Academic Programs
                  </h3>
                </div>
                <div className="p-6">
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {programs.map((program) => (
                      <div key={program.code} className="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg p-4 border border-blue-100 hover:shadow-md transition-shadow">
                        <div className="flex items-center justify-between">
                          <div>
                            <h4 className="font-semibold text-gray-900 text-lg">{program.name}</h4>
                            <p className="text-sm text-gray-600">Code: {program.code}</p>
                          </div>
                          <div className="text-right">
                            <div className="text-2xl font-bold text-blue-600">{program.units}</div>
                            <div className="text-xs text-gray-500">Units</div>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              </div>

              {/* Recent Activities */}
              <div className="bg-white overflow-hidden shadow-lg rounded-lg">
                <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
                  <h3 className="text-lg font-semibold text-gray-900 flex items-center">
                    <span className="mr-2">📊</span>
                    Recent Activities
                  </h3>
                </div>
                <div className="p-6">
                  {recentActivities.length > 0 ? (
                    <div className="space-y-4 max-h-64 overflow-y-auto">
                      {recentActivities.map((activity, idx) => (
                        <div key={idx} className="flex items-center space-x-4 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                          <div className="flex-shrink-0 w-2 h-2 bg-blue-500 rounded-full"></div>
                          <div className="flex-1 min-w-0">
                            <div className="flex items-center space-x-2">
                              {activity.unit_name && (
                                <span className="font-medium text-gray-900">{activity.unit_name}</span>
                              )}
                              {activity.unit_code && (
                                <span className="text-sm text-gray-500 bg-gray-200 px-2 py-1 rounded">
                                  {activity.unit_code}
                                </span>
                              )}
                            </div>
                            <div className="flex items-center justify-between">
                              <span className="text-sm text-gray-600">
                                {activity.first_name} {activity.last_name}
                              </span>
                              <span className="text-xs text-gray-400">
                                {new Date(activity.created_at).toLocaleDateString()}
                              </span>
                            </div>
                          </div>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <div className="text-center py-8 text-gray-500">
                      <div className="text-4xl mb-2">📋</div>
                      <p>No recent activities to display</p>
                    </div>
                  )}
                </div>
              </div>
            </div>

            {/* Right Column */}
            <div className="space-y-8">
              
              {/* Faculty Information */}
              <div className="bg-white overflow-hidden shadow-lg rounded-lg">
                <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
                  <h3 className="text-lg font-semibold text-gray-900 flex items-center">
                    <span className="mr-2">🏫</span>
                    Faculty Information
                  </h3>
                </div>
                <div className="p-6 space-y-4">
                  <div className="flex justify-between items-center py-2 border-b border-gray-100">
                    <span className="text-sm font-medium text-gray-600">Name</span>
                    <span className="text-sm text-gray-900">{facultyInfo.name}</span>
                  </div>
                  <div className="flex justify-between items-center py-2 border-b border-gray-100">
                    <span className="text-sm font-medium text-gray-600">Code</span>
                    <span className="text-sm text-gray-900 font-mono bg-gray-100 px-2 py-1 rounded">
                      {facultyInfo.code}
                    </span>
                  </div>
                  <div className="flex justify-between items-center py-2 border-b border-gray-100">
                    <span className="text-sm font-medium text-gray-600">Total Programs</span>
                    <span className="text-sm font-semibold text-blue-600">{facultyInfo.totalPrograms}</span>
                  </div>
                  <div className="flex justify-between items-center py-2">
                    <span className="text-sm font-medium text-gray-600">Total Classes</span>
                    <span className="text-sm font-semibold text-green-600">{facultyInfo.totalClasses}</span>
                  </div>
                </div>
              </div>

              {/* Pending Approvals */}
              <div className="bg-white overflow-hidden shadow-lg rounded-lg">
                <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
                  <h3 className="text-lg font-semibold text-gray-900 flex items-center">
                    <span className="mr-2">⏳</span>
                    Pending Approvals
                  </h3>
                </div>
                <div className="p-6 space-y-4">
                  <div className="flex items-center justify-between p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                    <div className="flex items-center">
                      <span className="text-yellow-600 mr-2">📝</span>
                      <span className="text-sm font-medium text-gray-700">Enrollments</span>
                    </div>
                    <span className="text-lg font-bold text-yellow-600">{pendingApprovals.enrollments}</span>
                  </div>
                  <div className="flex items-center justify-between p-3 bg-blue-50 rounded-lg border border-blue-200">
                    <div className="flex items-center">
                      <span className="text-blue-600 mr-2">👨‍🏫</span>
                      <span className="text-sm font-medium text-gray-700">Lecturer Requests</span>
                    </div>
                    <span className="text-lg font-bold text-blue-600">{pendingApprovals.lecturerRequests}</span>
                  </div>
                  <div className="flex items-center justify-between p-3 bg-green-50 rounded-lg border border-green-200">
                    <div className="flex items-center">
                      <span className="text-green-600 mr-2">📚</span>
                      <span className="text-sm font-medium text-gray-700">Unit Changes</span>
                    </div>
                    <span className="text-lg font-bold text-green-600">{pendingApprovals.unitChanges}</span>
                  </div>
                </div>
              </div>

              {/* User Roles & Permissions */}
              <div className="bg-white overflow-hidden shadow-lg rounded-lg">
                <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
                  <h3 className="text-lg font-semibold text-gray-900 flex items-center">
                    <span className="mr-2">🔐</span>
                    Access Control
                  </h3>
                </div>
                <div className="p-6 space-y-4">
                  <div>
                    <h4 className="text-sm font-medium text-gray-600 mb-2">Your Roles</h4>
                    <div className="flex flex-wrap gap-2">
                      {userRoles.map((role) => (
                        <span key={role} className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                          {role}
                        </span>
                      ))}
                    </div>
                  </div>
                  <div>
                    <h4 className="text-sm font-medium text-gray-600 mb-2">Permissions</h4>
                    <div className="max-h-32 overflow-y-auto">
                      <div className="flex flex-wrap gap-1">
                        {userPermissions.map((perm) => (
                          <span key={perm} className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                            {perm}
                          </span>
                        ))}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Quick Actions Section */}
          <div className="mt-8">
            <RoleAwareComponent requiredRoles={['Faculty Admin - SCES']}>
              <div className="bg-white overflow-hidden shadow-lg rounded-lg">
                <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
                  <h3 className="text-lg font-semibold text-gray-900 flex items-center">
                    <span className="mr-2">⚡</span>
                    Quick Actions
                  </h3>
                </div>
                <div className="p-6">
                  <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <button className="bg-blue-50 hover:bg-blue-100 p-4 rounded-lg text-left transition-colors group">
                      <div className="flex items-center justify-between">
                        <div>
                          <h4 className="font-medium text-gray-900 group-hover:text-blue-600">Manage Units</h4>
                          <p className="text-sm text-gray-500 mt-1">Add or edit course units</p>
                        </div>
                        <span className="text-blue-500 text-xl">📚</span>
                      </div>
                    </button>
                    
                    <button className="bg-green-50 hover:bg-green-100 p-4 rounded-lg text-left transition-colors group">
                      <div className="flex items-center justify-between">
                        <div>
                          <h4 className="font-medium text-gray-900 group-hover:text-green-600">Enrollments</h4>
                          <p className="text-sm text-gray-500 mt-1">Manage student enrollments</p>
                        </div>
                        <span className="text-green-500 text-xl">📝</span>
                      </div>
                    </button>
                    
                    <button className="bg-purple-50 hover:bg-purple-100 p-4 rounded-lg text-left transition-colors group">
                      <div className="flex items-center justify-between">
                        <div>
                          <h4 className="font-medium text-gray-900 group-hover:text-purple-600">Timetables</h4>
                          <p className="text-sm text-gray-500 mt-1">View and manage schedules</p>
                        </div>
                        <span className="text-purple-500 text-xl">📅</span>
                      </div>
                    </button>
                    
                    <button className="bg-orange-50 hover:bg-orange-100 p-4 rounded-lg text-left transition-colors group">
                      <div className="flex items-center justify-between">
                        <div>
                          <h4 className="font-medium text-gray-900 group-hover:text-orange-600">Reports</h4>
                          <p className="text-sm text-gray-500 mt-1">Generate faculty reports</p>
                        </div>
                        <span className="text-orange-500 text-xl">📊</span>
                      </div>
                    </button>
                  </div>
                </div>
              </div>
            </RoleAwareComponent>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
};

export default Dashboard;