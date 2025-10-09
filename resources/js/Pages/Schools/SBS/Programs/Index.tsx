import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { 
  BookOpen, 
  Users, 
  Calendar, 
  ClipboardList, 
  GraduationCap,
  ChevronDown,
  ChevronRight,
  Eye,
  Edit,
  Trash2,
  Plus,
  Search,
  Filter,
  TrendingUp,
  Clock,
  Mail,
  Phone
} from 'lucide-react';

interface Program {
  id: number;
  code: string;
  name: string;
  full_name: string;
  degree_type: string;
  duration_years: number;
  is_active: boolean;
  description: string;
  contact_email: string;
  contact_phone: string;
  sort_order: number;
  school_name: string;
  units_count: number;
  enrollments_count: number;
  created_at: string;
  updated_at: string;
  routes?: {
    units: string;
    classes: string;
    enrollments: string;
    class_timetables: string;
    exam_timetables: string;
  };
}

interface Props {
  programs: Program[];
  school: {
    id: number;
    name: string;
    code: string;
  };
  schoolCode: string;
  filters: {
    search?: string;
    is_active?: boolean;
    sort_field?: string;
    sort_direction?: string;
  };
  can: {
    create: boolean;
    update: boolean;
    delete: boolean;
  };
  error?: string;
}

export default function Index({ programs, school, schoolCode, filters, can, error }: Props) {
  const [expandedProgram, setExpandedProgram] = useState<number | null>(null);
  const [searchTerm, setSearchTerm] = useState(filters.search || '');
  const [statusFilter, setStatusFilter] = useState(filters.is_active?.toString() || 'all');
  const [degreeFilter, setDegreeFilter] = useState('all');

  const totalUnits = programs.reduce((sum, p) => sum + (p.units_count || 0), 0);
  const totalEnrollments = programs.reduce((sum, p) => sum + (p.enrollments_count || 0), 0);
  const activePrograms = programs.filter(p => p.is_active).length;

  const handleSearch = () => {
    router.get(route(`schools.${schoolCode.toLowerCase()}.programs.index`), {
      search: searchTerm,
      is_active: statusFilter === 'all' ? undefined : statusFilter === 'active',
    }, {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const toggleProgram = (programId: number) => {
    setExpandedProgram(expandedProgram === programId ? null : programId);
  };

  const handleDelete = (programId: number, programName: string) => {
    if (confirm(`Are you sure you want to delete "${programName}"? This action cannot be undone.`)) {
      router.delete(route(`schools.${schoolCode.toLowerCase()}.programs.destroy`, programId), {
        onSuccess: () => {
          alert('Program deleted successfully');
        },
        onError: (errors) => {
          alert('Failed to delete program: ' + (errors.error || 'Unknown error'));
        }
      });
    }
  };

  return (
    <AuthenticatedLayout
      header={
        <div className="flex justify-between items-center">
          <h2 className="font-semibold text-xl text-gray-800 leading-tight">
            {school.name} - Programs
          </h2>
        </div>
      }
    >
      <Head title={`${schoolCode} Programs`} />

      <div className="py-8">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          {/* Header Card */}
          <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6 p-6">
            <div className="flex items-start justify-between">
              <div className="flex-1">
                <div className="flex items-center gap-3 mb-2">
                  <GraduationCap className="h-8 w-8 text-orange-600" />
                  <h1 className="text-2xl font-bold text-gray-900">
                    {school.name === 'School of Business Studies' ? 'Strathmore Business School' : school.name}
                  </h1>
                </div>
                <h2 className="text-lg font-semibold text-gray-700 mb-1">
                  Business Programs Management
                </h2>
                <p className="text-gray-600 mb-4">
                  Manage business programs, degrees, and professional qualifications
                </p>
                <div className="flex gap-6 text-sm text-gray-600">
                  <span>Total: <strong>{programs.length}</strong></span>
                  <span>Active: <strong>{activePrograms}</strong></span>
                  <span>Total Units: <strong>{totalUnits}</strong></span>
                  <span>Total Enrollments: <strong>{totalEnrollments}</strong></span>
                </div>
              </div>
              {can.create && (
                <Link
                  href={route(`schools.${schoolCode.toLowerCase()}.programs.create`)}
                  className="inline-flex items-center px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white font-semibold rounded-lg transition-colors"
                >
                  <Plus className="h-5 w-5 mr-2" />
                  Create Program
                </Link>
              )}
            </div>
          </div>

          {/* Error Message */}
          {error && (
            <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
              {error}
            </div>
          )}

          {/* Search and Filters */}
          <div className="bg-white rounded-lg shadow-sm p-4 mb-6">
            <div className="flex flex-wrap gap-4">
              <div className="flex-1 min-w-[300px]">
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-gray-400" />
                  <input
                    type="text"
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
                    placeholder="Search business programs..."
                    className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                  />
                </div>
              </div>
              <select
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value)}
                className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500"
              >
                <option value="all">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
              <select
                value={degreeFilter}
                onChange={(e) => setDegreeFilter(e.target.value)}
                className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500"
              >
                <option value="all">All Degrees</option>
                <option value="Bachelor">Bachelor</option>
                <option value="Master">Master</option>
                <option value="MBA">MBA</option>
                <option value="Diploma">Diploma</option>
              </select>
              <button
                onClick={handleSearch}
                className="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors flex items-center gap-2"
              >
                <Filter className="h-4 w-4" />
                Filter
              </button>
            </div>
          </div>

          {/* Programs Table */}
          <div className="bg-white rounded-lg shadow-sm overflow-hidden">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Program
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Degree & Duration
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Contact
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Status
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Statistics
                  </th>
                  <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {programs.map((program) => (
                  <>
                    <tr key={program.id} className="hover:bg-gray-50">
                      <td className="px-6 py-4">
                        <div className="flex items-center">
                          <button
                            onClick={() => toggleProgram(program.id)}
                            className="mr-3 text-gray-400 hover:text-gray-600"
                          >
                            {expandedProgram === program.id ? (
                              <ChevronDown className="h-5 w-5" />
                            ) : (
                              <ChevronRight className="h-5 w-5" />
                            )}
                          </button>
                          <div className="flex items-center">
                            <GraduationCap className="h-5 w-5 text-orange-600 mr-3" />
                            <div>
                              <div className="text-sm font-medium text-gray-900">{program.name}</div>
                              <div className="text-sm text-gray-500">{program.code}</div>
                              <div className="text-xs text-gray-400 mt-1">{program.description?.substring(0, 100)}...</div>
                            </div>
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="flex items-center">
                          <span className="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                            {program.degree_type}
                          </span>
                        </div>
                        <div className="flex items-center mt-2 text-sm text-gray-600">
                          <Clock className="h-4 w-4 mr-1" />
                          {program.duration_years} years
                        </div>
                      </td>
                      <td className="px-6 py-4 text-sm text-gray-600">
                        <div className="flex items-center mb-1">
                          <Mail className="h-4 w-4 mr-2 text-gray-400" />
                          {program.contact_email || 'sbs@strathmore.edu'}
                        </div>
                        <div className="flex items-center">
                          <Phone className="h-4 w-4 mr-2 text-gray-400" />
                          {program.contact_phone || 'N/A'}
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                          program.is_active
                            ? 'bg-green-100 text-green-800'
                            : 'bg-red-100 text-red-800'
                        }`}>
                          {program.is_active ? 'Active' : 'Inactive'}
                        </span>
                      </td>
                      <td className="px-6 py-4 text-sm text-gray-600">
                        <div className="flex items-center mb-1">
                          <BookOpen className="h-4 w-4 mr-2 text-blue-600" />
                          <strong>{program.units_count || 0}</strong> units
                        </div>
                        <div className="flex items-center">
                          <Users className="h-4 w-4 mr-2 text-green-600" />
                          <strong>{program.enrollments_count || 0}</strong> enrolled
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <div className="flex justify-end gap-2">
                          <button
                            onClick={() => toggleProgram(program.id)}
                            className="text-blue-600 hover:text-blue-900"
                            title="View Details"
                          >
                            <Eye className="h-5 w-5" />
                          </button>
                          {can.update && (
                            <Link
                              href={route(`schools.${schoolCode.toLowerCase()}.programs.edit`, program.id)}
                              className="text-orange-600 hover:text-orange-900"
                              title="Edit"
                            >
                              <Edit className="h-5 w-5" />
                            </Link>
                          )}
                          {can.delete && (
                            <button
                              onClick={() => handleDelete(program.id, program.name)}
                              className="text-red-600 hover:text-red-900"
                              title="Delete"
                            >
                              <Trash2 className="h-5 w-5" />
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                    {expandedProgram === program.id && (
                      <tr>
                        <td colSpan={6} className="px-6 py-4 bg-gray-50">
                          <div className="space-y-6">
                            {/* Program Management Section - THIS WAS MISSING IN SBS! */}
                            <div>
                              <h3 className="text-sm font-semibold text-gray-700 mb-3 flex items-center">
                                <BookOpen className="h-4 w-4 mr-2" />
                                Program Management
                              </h3>
                              <div className="grid grid-cols-5 gap-4">
                                {program.routes && (
                                  <>
                                    <Link
                                      href={program.routes.units}
                                      className="p-4 bg-blue-50 hover:bg-blue-100 rounded-lg text-center transition-colors group"
                                    >
                                      <BookOpen className="h-6 w-6 text-blue-600 mx-auto mb-2 group-hover:scale-110 transition-transform" />
                                      <div className="text-sm font-medium text-gray-900">Units</div>
                                      <div className="text-xs text-gray-600">{program.units_count || 0}</div>
                                    </Link>
                                    <Link
                                      href={program.routes.classes}
                                      className="p-4 bg-green-50 hover:bg-green-100 rounded-lg text-center transition-colors group"
                                    >
                                      <Users className="h-6 w-6 text-green-600 mx-auto mb-2 group-hover:scale-110 transition-transform" />
                                      <div className="text-sm font-medium text-gray-900">Classes</div>
                                      <div className="text-xs text-gray-600">Manage</div>
                                    </Link>
                                    <Link
                                      href={program.routes.enrollments}
                                      className="p-4 bg-orange-50 hover:bg-orange-100 rounded-lg text-center transition-colors group"
                                    >
                                      <GraduationCap className="h-6 w-6 text-orange-600 mx-auto mb-2 group-hover:scale-110 transition-transform" />
                                      <div className="text-sm font-medium text-gray-900">Enrollment</div>
                                      <div className="text-xs text-gray-600">{program.enrollments_count || 0}</div>
                                    </Link>
                                    <Link
                                      href={program.routes.class_timetables}
                                      className="p-4 bg-cyan-50 hover:bg-cyan-100 rounded-lg text-center transition-colors group"
                                    >
                                      <Calendar className="h-6 w-6 text-cyan-600 mx-auto mb-2 group-hover:scale-110 transition-transform" />
                                      <div className="text-sm font-medium text-gray-900">Class Timetable</div>
                                      <div className="text-xs text-gray-600">Schedule</div>
                                    </Link>
                                    <Link
                                      href={program.routes.exam_timetables}
                                      className="p-4 bg-red-50 hover:bg-red-100 rounded-lg text-center transition-colors group"
                                    >
                                      <ClipboardList className="h-6 w-6 text-red-600 mx-auto mb-2 group-hover:scale-110 transition-transform" />
                                      <div className="text-sm font-medium text-gray-900">Exam Timetable</div>
                                      <div className="text-xs text-gray-600">Schedule</div>
                                    </Link>
                                  </>
                                )}
                              </div>
                            </div>

                            {/* Program Details, Contact Info, and Statistics */}
                            <div className="grid grid-cols-3 gap-6">
                              <div>
                                <h4 className="text-sm font-semibold text-gray-700 mb-2">Program Details</h4>
                                <dl className="space-y-1 text-sm">
                                  <div>
                                    <dt className="text-gray-600">Full Name:</dt>
                                    <dd className="font-medium text-gray-900">{program.full_name}</dd>
                                  </div>
                                  <div>
                                    <dt className="text-gray-600">Sort Order:</dt>
                                    <dd className="font-medium text-gray-900">{program.sort_order}</dd>
                                  </div>
                                  <div>
                                    <dt className="text-gray-600">Created:</dt>
                                    <dd className="font-medium text-gray-900">
                                      {new Date(program.created_at).toLocaleDateString()}
                                    </dd>
                                  </div>
                                </dl>
                              </div>
                              <div>
                                <h4 className="text-sm font-semibold text-gray-700 mb-2">Contact Information</h4>
                                <dl className="space-y-1 text-sm">
                                  <div>
                                    <dt className="text-gray-600">Email:</dt>
                                    <dd className="font-medium text-gray-900">{program.contact_email || 'sbs@strathmore.edu'}</dd>
                                  </div>
                                  <div>
                                    <dt className="text-gray-600">Phone:</dt>
                                    <dd className="font-medium text-gray-900">{program.contact_phone || 'N/A'}</dd>
                                  </div>
                                </dl>
                              </div>
                              <div>
                                <h4 className="text-sm font-semibold text-gray-700 mb-2">Statistics</h4>
                                <dl className="space-y-1 text-sm">
                                  <div>
                                    <dt className="text-gray-600">Units:</dt>
                                    <dd className="font-medium text-gray-900">{program.units_count || 0}</dd>
                                  </div>
                                  <div>
                                    <dt className="text-gray-600">Enrollments:</dt>
                                    <dd className="font-medium text-gray-900">{program.enrollments_count || 0}</dd>
                                  </div>
                                  <div>
                                    <dt className="text-gray-600">Description:</dt>
                                    <dd className="font-medium text-gray-900">{program.description}</dd>
                                  </div>
                                </dl>
                              </div>
                            </div>
                          </div>
                        </td>
                      </tr>
                    )}
                  </>
                ))}
              </tbody>
            </table>

            {programs.length === 0 && (
              <div className="text-center py-12">
                <GraduationCap className="h-16 w-16 text-gray-400 mx-auto mb-4" />
                <p className="text-gray-600 text-lg">No programs found</p>
                {can.create && (
                  <Link
                    href={route(`schools.${schoolCode.toLowerCase()}.programs.create`)}
                    className="inline-flex items-center mt-4 px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white font-semibold rounded-lg"
                  >
                    <Plus className="h-5 w-5 mr-2" />
                    Create First Program
                  </Link>
                )}
              </div>
            )}
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}