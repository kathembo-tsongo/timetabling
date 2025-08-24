import React, { useState } from "react";
import { Head, router } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import axios from "axios";
import { toast } from "react-hot-toast";

// Icons components
const LoadingSpinner = () => (
  <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
);

const CheckIcon = ({ className = "w-5 h-5" }) => (
  <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
  </svg>
);

const BookIcon = ({ className = "w-5 h-5" }) => (
  <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} 
          d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C20.832 18.477 19.246 18 17.5 18c-1.746 0-3.332.477-4.5 1.253" />
  </svg>
);

const UserIcon = ({ className = "w-5 h-5" }) => (
  <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} 
          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
  </svg>
);

const CalendarIcon = ({ className = "w-5 h-5" }) => (
  <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} 
          d="M8 7V3a4 4 0 118 0v4m-4 16l-4-4m0 0l-4-4m4 4V8a3 3 0 00-3-3H3" />
  </svg>
);

interface Props {
  semesters: Array<{ id: number; name: string }>;
  groups: Array<{ id: number; name: string; class: { id: number; name: string }; capacity: number }>;
  classes: Array<{ id: number; name: string; semester_id: number }>;
  units: Array<{ id: number; name: string; code?: string }>;
  student: { id: number; code: string; first_name: string; last_name: string };
  enrollments: Array<{
    id: number;
    student_code: string | null;
    group_id: string | null;
    unit_id: number;
    semester_id: number;
    student: any;
    unit: { name: string } | null;
    group: { name: string } | null;
  }>;
}

export default function StudentEnroll({ semesters, groups, classes, units, student, enrollments }: Props) {
  const [currentEnrollment, setCurrentEnrollment] = useState({
    code: student.code,
    semester_id: 0,
    class_id: 0,
    group_id: "",
    unit_ids: [] as number[],
  });

  const [filteredClasses, setFilteredClasses] = useState<typeof classes>([]);
  const [filteredGroups, setFilteredGroups] = useState<typeof groups>([]);
  const [filteredUnits, setFilteredUnits] = useState<typeof units>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // When semester changes, filter classes
  const handleSemesterChange = (semesterId: number) => {
    setCurrentEnrollment((prev) => ({
      ...prev,
      semester_id: semesterId,
      class_id: 0,
      group_id: "",
      unit_ids: [],
    }));
    setFilteredClasses(classes.filter((cls) => cls.semester_id === semesterId));
    setFilteredGroups([]);
    setFilteredUnits([]);
  };

  // When class changes, filter groups and fetch units
  const handleClassChange = async (classId: number) => {
    setCurrentEnrollment((prev) => ({
      ...prev,
      class_id: classId,
      group_id: "",
      unit_ids: [],
    }));
    setFilteredGroups(groups.filter((group) => group.class?.id === classId));
    setFilteredUnits([]);
    setIsLoading(true);
    setError(null);

    if (currentEnrollment.semester_id && classId) {
      try {
        const response = await axios.get("/units/by-class-and-semester", {
          params: {
            semester_id: currentEnrollment.semester_id,
            class_id: classId,
          },
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
          },
        });

        if (response.data.success && Array.isArray(response.data.units)) {
          setFilteredUnits(response.data.units);
        } else if (response.data.units && Array.isArray(response.data.units)) {
          setFilteredUnits(response.data.units);
        } else {
          setFilteredUnits([]);
          setError("No units found for this class and semester.");
        }
      } catch (error: any) {
        console.error("Error fetching units:", error);
        setFilteredUnits([]);
        setError("Failed to fetch units. Please try again.");
      } finally {
        setIsLoading(false);
      }
    } else {
      setIsLoading(false);
    }
  };

  // When group changes, just update state
  const handleGroupChange = (groupId: string) => {
    setCurrentEnrollment((prev) => ({
      ...prev,
      group_id: groupId,
      unit_ids: [],
    }));
  };

  // Handle form submit
  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (
      !currentEnrollment.semester_id ||
      !currentEnrollment.class_id ||
      !currentEnrollment.group_id ||
      !currentEnrollment.unit_ids.length
    ) {
      setError("Please fill all fields and select at least one unit.");
      return;
    }

    setError(null);

    router.post(
      "/enroll",
      {
        semester_id: currentEnrollment.semester_id,
        group_id: currentEnrollment.group_id,
        unit_ids: currentEnrollment.unit_ids,
        code: student.code,
      },
      {
        onSuccess: () => {
          toast.success("Enrolled successfully!");
          setCurrentEnrollment({
            code: student.code,
            semester_id: 0,
            class_id: 0,
            group_id: "",
            unit_ids: [],
          });
          setFilteredClasses([]);
          setFilteredGroups([]);
          setFilteredUnits([]);
        },
        onError: (errors: any) => {
          if (typeof errors.error === "string") setError(errors.error);
          else if (errors.group_id) setError(errors.group_id);
          else if (errors.unit_ids) setError(errors.unit_ids);
          else if (errors.code) setError(errors.code);
          else setError("An error occurred during enrollment. Please try again.");
        },
      }
    );
  };

  return (
    <AuthenticatedLayout>
      <Head title="Self Enroll in Units" />
      
      <div className="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-purple-50 py-8">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
          
          {/* Header */}
          <div className="text-center mb-8">
            <div className="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full mb-4">
              <BookIcon className="w-10 h-10 text-white" />
            </div>
            <h1 className="text-4xl font-bold text-gray-900 mb-2">Unit Enrollment</h1>
            <p className="text-lg text-gray-600">Select your units for the upcoming semester</p>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            {/* Main Form */}
            <div className="lg:col-span-2">
              <div className="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
                <div className="bg-gradient-to-r from-blue-600 to-purple-600 px-8 py-6">
                  <h2 className="text-2xl font-bold text-white">Enrollment Form</h2>
                  <p className="text-blue-100 mt-1">Complete all fields to enroll in your units</p>
                </div>

                <form onSubmit={handleSubmit} className="p-8 space-y-6">
                  
                  {/* Student Info */}
                  <div className="bg-gradient-to-r from-gray-50 to-gray-100 rounded-xl p-6 border border-gray-200">
                    <div className="flex items-center space-x-3 mb-3">
                      <UserIcon className="w-6 h-6 text-blue-600" />
                      <label className="text-lg font-semibold text-gray-900">Student Information</label>
                    </div>
                    <div className="bg-white rounded-lg px-4 py-3 border border-gray-200">
                      <div className="text-sm text-gray-600">Enrolled as:</div>
                      <div className="text-lg font-semibold text-gray-900">
                        {student.first_name} {student.last_name}
                      </div>
                      <div className="text-sm text-blue-600 font-medium">ID: {student.code}</div>
                    </div>
                  </div>

                  {/* Semester Selection */}
                  <div className="space-y-2">
                    <div className="flex items-center space-x-2">
                      <CalendarIcon className="w-5 h-5 text-blue-600" />
                      <label className="text-sm font-semibold text-gray-900">Semester *</label>
                    </div>
                    <select
                      value={currentEnrollment.semester_id || ""}
                      onChange={(e) => handleSemesterChange(Number(e.target.value))}
                      className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                      required
                    >
                      <option value="" disabled>Choose your semester</option>
                      {semesters.map((semester) => (
                        <option key={semester.id} value={semester.id}>
                          {semester.name}
                        </option>
                      ))}
                    </select>
                  </div>

                  {/* Class Selection */}
                  <div className="space-y-2">
                    <label className="text-sm font-semibold text-gray-900">Class *</label>
                    <select
                      value={currentEnrollment.class_id || ""}
                      onChange={(e) => handleClassChange(Number(e.target.value))}
                      className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 disabled:bg-gray-100 disabled:cursor-not-allowed"
                      required
                      disabled={!currentEnrollment.semester_id}
                    >
                      <option value="" disabled>Select your class</option>
                      {filteredClasses.map((classItem) => (
                        <option key={classItem.id} value={classItem.id}>
                          {classItem.name}
                        </option>
                      ))}
                    </select>
                  </div>

                  {/* Group Selection */}
                  <div className="space-y-2">
                    <label className="text-sm font-semibold text-gray-900">Group *</label>
                    <select
                      value={currentEnrollment.group_id || ""}
                      onChange={(e) => handleGroupChange(e.target.value)}
                      className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 disabled:bg-gray-100 disabled:cursor-not-allowed"
                      required
                      disabled={!currentEnrollment.class_id}
                    >
                      <option value="" disabled>Select your group</option>
                      {filteredGroups.map((group) => (
                        <option key={group.id} value={group.id.toString()}>
                          {group.name} (Capacity: {group.capacity})
                        </option>
                      ))}
                    </select>
                  </div>

                  {/* Units Selection */}
                  <div className="space-y-3">
                    <label className="text-sm font-semibold text-gray-900">Available Units *</label>
                    
                    {isLoading ? (
                      <div className="flex items-center justify-center py-12 bg-gray-50 rounded-xl border-2 border-dashed border-gray-300">
                        <div className="text-center">
                          <LoadingSpinner />
                          <p className="mt-2 text-sm text-gray-600">Loading available units...</p>
                        </div>
                      </div>
                    ) : (
                      <div className="border border-gray-300 rounded-xl bg-gray-50">
                        {filteredUnits.length > 0 ? (
                          <>
                            {/* Select All Option */}
                            <div className="px-4 py-3 bg-white border-b border-gray-200 rounded-t-xl">
                              <label className="flex items-center space-x-3 cursor-pointer">
                                <input
                                  type="checkbox"
                                  onChange={(e) => {
                                    if (e.target.checked) {
                                      setCurrentEnrollment((prev) => ({
                                        ...prev,
                                        unit_ids: filteredUnits.map((unit) => unit.id),
                                      }));
                                    } else {
                                      setCurrentEnrollment((prev) => ({
                                        ...prev,
                                        unit_ids: [],
                                      }));
                                    }
                                  }}
                                  checked={
                                    filteredUnits.length > 0 &&
                                    currentEnrollment.unit_ids.length === filteredUnits.length
                                  }
                                  disabled={!filteredUnits.length || isLoading}
                                  className="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                />
                                <span className="text-sm font-medium text-gray-900">Select All Units</span>
                              </label>
                            </div>
                            
                            {/* Units List */}
                            <div className="max-h-64 overflow-y-auto">
                              {filteredUnits.map((unit, index) => (
                                <div
                                  key={unit.id}
                                  className={`px-4 py-3 ${index !== filteredUnits.length - 1 ? 'border-b border-gray-200' : ''} hover:bg-blue-50 transition-colors duration-150`}
                                >
                                  <label className="flex items-start space-x-3 cursor-pointer">
                                    <input
                                      type="checkbox"
                                      id={`unit-${unit.id}`}
                                      value={unit.id}
                                      onChange={(e) => {
                                        const unitId = Number(e.target.value);
                                        setCurrentEnrollment((prev) => ({
                                          ...prev,
                                          unit_ids: e.target.checked
                                            ? [...prev.unit_ids, unitId]
                                            : prev.unit_ids.filter((id) => id !== unitId),
                                        }));
                                      }}
                                      checked={currentEnrollment.unit_ids.includes(unit.id)}
                                      disabled={isLoading}
                                      className="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 mt-1"
                                    />
                                    <div className="flex-1 min-w-0">
                                      <div className="text-sm font-medium text-gray-900">
                                        {unit.code ? `${unit.code} - ${unit.name}` : unit.name}
                                      </div>
                                      {unit.code && (
                                        <div className="text-xs text-gray-500 mt-1">
                                          Course Code: {unit.code}
                                        </div>
                                      )}
                                    </div>
                                  </label>
                                </div>
                              ))}
                            </div>
                          </>
                        ) : (
                          <div className="px-4 py-8 text-center">
                            <BookIcon className="w-12 h-12 text-gray-400 mx-auto mb-3" />
                            <p className="text-sm text-gray-500">
                              {currentEnrollment.class_id
                                ? "No units found for this class and semester."
                                : "Please select a class to see available units."}
                            </p>
                          </div>
                        )}
                      </div>
                    )}
                  </div>

                  {/* Error Message */}
                  {error && (
                    <div className="bg-red-50 border border-red-200 rounded-xl p-4">
                      <div className="flex">
                        <div className="flex-shrink-0">
                          <svg className="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                          </svg>
                        </div>
                        <div className="ml-3">
                          <p className="text-sm text-red-800">{error}</p>
                        </div>
                      </div>
                    </div>
                  )}

                  {/* Action Buttons */}
                  <div className="flex justify-end space-x-4 pt-6">
                    <button
                      type="button"
                      onClick={() => {
                        setCurrentEnrollment({
                          code: student.code,
                          semester_id: 0,
                          class_id: 0,
                          group_id: "",
                          unit_ids: [],
                        });
                        setFilteredClasses([]);
                        setFilteredGroups([]);
                        setFilteredUnits([]);
                        setError(null);
                      }}
                      className="px-6 py-3 text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-all duration-200"
                    >
                      Reset Form
                    </button>
                    <button
                      type="submit"
                      className="px-8 py-3 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-xl hover:from-blue-700 hover:to-purple-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 font-semibold"
                      disabled={isLoading || !currentEnrollment.unit_ids.length}
                    >
                      {isLoading ? (
                        <div className="flex items-center space-x-2">
                          <LoadingSpinner />
                          <span>Processing...</span>
                        </div>
                      ) : (
                        `Enroll in ${currentEnrollment.unit_ids.length} Unit${currentEnrollment.unit_ids.length !== 1 ? 's' : ''}`
                      )}
                    </button>
                  </div>
                </form>
              </div>
            </div>

            {/* Current Enrollments Sidebar */}
            <div className="lg:col-span-1">
              <div className="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden sticky top-6">
                <div className="bg-gradient-to-r from-green-600 to-teal-600 px-6 py-4">
                  <h3 className="text-xl font-bold text-white">Current Enrollments</h3>
                  <p className="text-green-100 text-sm mt-1">Your registered units</p>
                </div>
                
                <div className="p-6">
                  {enrollments && enrollments.length > 0 ? (
                    <div className="space-y-3">
                      {enrollments.map((enrollment) => (
                        <div key={enrollment.id} className="bg-gradient-to-r from-green-50 to-teal-50 border border-green-200 rounded-lg p-4">
                          <div className="flex items-start space-x-3">
                            <div className="flex-shrink-0">
                              <div className="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                <CheckIcon className="w-4 h-4 text-green-600" />
                              </div>
                            </div>
                            <div className="flex-1 min-w-0">
                              <p className="text-sm font-semibold text-gray-900">
                                {enrollment.unit?.name || 'Unknown Unit'}
                              </p>
                              {enrollment.group && (
                                <p className="text-xs text-gray-600 mt-1">
                                  Group: {enrollment.group.name}
                                </p>
                              )}
                            </div>
                          </div>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <div className="text-center py-8">
                      <BookIcon className="w-12 h-12 text-gray-400 mx-auto mb-3" />
                      <p className="text-sm text-gray-500">No current enrollments</p>
                      <p className="text-xs text-gray-400 mt-1">Complete the form to enroll in units</p>
                    </div>
                  )}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}