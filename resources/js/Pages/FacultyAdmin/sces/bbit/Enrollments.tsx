"use client"

import type React from "react"
import { useState, useEffect } from "react"
import { Head, usePage, router } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import axios from "axios"
import { toast } from "react-hot-toast"

// Define interfaces for BBIT specific data structure
interface Semester {
  id: number
  name: string
  is_active: boolean
}

interface Group {
  id: number
  name: string
  class: { id: number; name: string }
  capacity: number
  class_id: number
}

interface Class {
  id: number
  name: string
  semester_id: number
}

interface BbitUnit {
  id: number
  name: string
  code: string
  credit_hours: number
  is_active: number
}

interface Student {
  id: number
  code: string
  first_name: string
  last_name: string
  name?: string
}

interface BbitEnrollment {
  id: number
  student_code: string
  unit_id: number
  semester_id: number
  group_id: number | null
  lecturer_code: string | null
  created_at: string
  updated_at: string
  // Joined data from controller
  unit_name: string
  unit_code: string
  credit_hours: number
  student_first_name: string | null
  student_last_name: string | null
  lecturer_first_name: string | null
  lecturer_last_name: string | null
  group_name: string | null
  group_capacity: number | null
  class_name: string | null
}

interface LecturerAssignment {
  id?: number
  unit_id: number
  unit_name: string
  unit_code: string
  lecturer_code: string
  lecturer_name: string
  first_name: string
  last_name: string
}

interface PageProps {
  enrollments: BbitEnrollment[]
  lecturerAssignments: LecturerAssignment[]
  bbitUnits: BbitUnit[]
  students: Student[]
  semesters: Semester[]
  groups: Group[]
  schoolCode: string
  programCode: string
  programName: string
  currentSemester: Semester | null
  userPermissions: string[]
  userRoles: string[]
  errors: Record<string, string>
}

const Enrollments: React.FC = () => {
  const pageProps = usePage<PageProps>().props

  const {
    enrollments = [],
    lecturerAssignments = [],
    bbitUnits = [],
    students = [],
    semesters = [],
    groups = [],
    schoolCode,
    programCode,
    programName,
    currentSemester,
    userPermissions = [],
    userRoles = [],
    errors: pageErrors,
  } = pageProps

  // State for enrollments
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [currentEnrollment, setCurrentEnrollment] = useState<{
    student_code: string
    semester_id: number
    group_id: string
    unit_ids: number[]
  } | null>(null)
  const [filteredGroups, setFilteredGroups] = useState<Group[]>([])
  const [semesterUnits, setSemesterUnits] = useState<BbitUnit[]>([])
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  // State for lecturer assignments
  const [isAssignModalOpen, setIsAssignModalOpen] = useState(false)
  const [isEditAssignModalOpen, setIsEditAssignModalOpen] = useState(false)
  const [isViewAssignModalOpen, setIsViewAssignModalOpen] = useState(false)
  const [assignData, setAssignData] = useState<{ unit_id: number; lecturer_code: string } | null>(null)
  const [selectedAssignment, setSelectedAssignment] = useState<LecturerAssignment | null>(null)

  // Pagination state for enrollments
  const [enrollmentPage, setEnrollmentPage] = useState(1)
  const [enrollmentPerPage, setEnrollmentPerPage] = useState(10)

  // Pagination state for lecturer assignments
  const [assignmentPage, setAssignmentPage] = useState(1)
  const [assignmentPerPage, setAssignmentPerPage] = useState(10)

  const [selectedEnrollments, setSelectedEnrollments] = useState<number[]>([])
  const [isSelectAllChecked, setIsSelectAllChecked] = useState(false)

  // Pagination calculations for enrollments
  const totalEnrollments = enrollments.length
  const totalEnrollmentPages = Math.ceil(totalEnrollments / enrollmentPerPage)
  const startEnrollmentIndex = (enrollmentPage - 1) * enrollmentPerPage
  const endEnrollmentIndex = startEnrollmentIndex + enrollmentPerPage
  const currentEnrollments = enrollments.slice(startEnrollmentIndex, endEnrollmentIndex)

  // Pagination calculations for lecturer assignments
  const totalAssignments = lecturerAssignments.length
  const totalAssignmentPages = Math.ceil(totalAssignments / assignmentPerPage)
  const startAssignmentIndex = (assignmentPage - 1) * assignmentPerPage
  const endAssignmentIndex = startAssignmentIndex + assignmentPerPage
  const currentAssignments = lecturerAssignments.slice(startAssignmentIndex, endAssignmentIndex)

  // Pagination component
  const Pagination: React.FC<{
    currentPage: number
    totalPages: number
    onPageChange: (page: number) => void
    perPage: number
    onPerPageChange: (perPage: number) => void
    totalItems: number
    startIndex: number
    endIndex: number
  }> = ({ currentPage, totalPages, onPageChange, perPage, onPerPageChange, totalItems, startIndex, endIndex }) => {
    const pageNumbers = []
    const maxVisiblePages = 5
    let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2))
    let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1)
    
    if (endPage - startPage + 1 < maxVisiblePages) {
      startPage = Math.max(1, endPage - maxVisiblePages + 1)
    }
    
    for (let i = startPage; i <= endPage; i++) {
      pageNumbers.push(i)
    }

    return (
      <div className="flex items-center justify-between px-6 py-4 bg-white border-t border-gray-200">
        <div className="flex items-center space-x-2">
          <span className="text-sm text-gray-700">Show</span>
          <select
            value={perPage}
            onChange={(e) => onPerPageChange(Number(e.target.value))}
            className="border border-gray-300 rounded-md px-3 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          >
            <option value={5}>5</option>
            <option value={10}>10</option>
            <option value={25}>25</option>
            <option value={50}>50</option>
          </select>
          <span className="text-sm text-gray-700">entries</span>
        </div>
        <div className="flex items-center space-x-2">
          <span className="text-sm text-gray-700">
            Showing {startIndex + 1} to {Math.min(endIndex, totalItems)} of {totalItems} entries
          </span>
        </div>
        <div className="flex items-center space-x-1">
          <button
            onClick={() => onPageChange(currentPage - 1)}
            disabled={currentPage === 1}
            className={`px-3 py-2 text-sm font-medium rounded-md transition-colors ${
              currentPage === 1
                ? 'text-gray-400 cursor-not-allowed'
                : 'text-gray-700 hover:bg-gray-100'
            }`}
          >
            Previous
          </button>
          {startPage > 1 && (
            <>
              <button
                onClick={() => onPageChange(1)}
                className="px-3 py-2 text-sm font-medium text-gray-700 rounded-md hover:bg-gray-100 transition-colors"
              >
                1
              </button>
              {startPage > 2 && (
                <span className="px-2 py-2 text-gray-400">...</span>
              )}
            </>
          )}
          {pageNumbers.map((page) => (
            <button
              key={page}
              onClick={() => onPageChange(page)}
              className={`px-3 py-2 text-sm font-medium rounded-md transition-colors ${
                currentPage === page
                  ? 'bg-blue-600 text-white'
                  : 'text-gray-700 hover:bg-gray-100'
              }`}
            >
              {page}
            </button>
          ))}
          {endPage < totalPages && (
            <>
              {endPage < totalPages - 1 && (
                <span className="px-2 py-2 text-gray-400">...</span>
              )}
              <button
                onClick={() => onPageChange(totalPages)}
                className="px-3 py-2 text-sm font-medium text-gray-700 rounded-md hover:bg-gray-100 transition-colors"
              >
                {totalPages}
              </button>
            </>
          )}
          <button
            onClick={() => onPageChange(currentPage + 1)}
            disabled={currentPage === totalPages}
            className={`px-3 py-2 text-sm font-medium rounded-md transition-colors ${
              currentPage === totalPages
                ? 'text-gray-400 cursor-not-allowed'
                : 'text-gray-700 hover:bg-gray-100'
            }`}
          >
            Next
          </button>
        </div>
      </div>
    )
  }

  // Modal handlers for enrollments
  const handleOpenModal = () => {
    setCurrentEnrollment({
      student_code: "",
      semester_id: currentSemester?.id || 0,
      group_id: "",
      unit_ids: [],
    })
    setFilteredGroups(groups)
    setIsModalOpen(true)
    setError(null)
  }

  const handleCloseModal = () => {
    setIsModalOpen(false)
    setCurrentEnrollment(null)
    setError(null)
  }

  // Modal handlers for lecturer assignments
  const handleOpenAssignModal = () => {
    setAssignData({ unit_id: 0, lecturer_code: "" })
    setIsAssignModalOpen(true)
    setError(null)
  }

  const handleCloseAssignModal = () => {
    setIsAssignModalOpen(false)
    setAssignData(null)
    setError(null)
  }

  const handleViewAssignment = (assignment: LecturerAssignment) => {
    setSelectedAssignment(assignment)
    setIsViewAssignModalOpen(true)
  }

  const handleEditAssignment = (assignment: LecturerAssignment) => {
    setSelectedAssignment(assignment)
    setAssignData({
      unit_id: assignment.unit_id,
      lecturer_code: assignment.lecturer_code
    })
    setIsEditAssignModalOpen(true)
    setError(null)
  }

  const handleDeleteAssignment = (assignment: LecturerAssignment) => {
    if (confirm(`Are you sure you want to remove lecturer ${assignment.lecturer_name} from ${assignment.unit_name}?`)) {
      router.delete(`/facultyadmin/sces/bbit/lecturer-assignments/${assignment.unit_id}/${assignment.lecturer_code}`, {
        onSuccess: () => {
          toast.success("Lecturer assignment removed successfully!")
        },
        onError: (errors) => {
          console.error("Delete error:", errors)
          toast.error("Failed to remove lecturer assignment.")
        },
      })
    }
  }

  // Form handlers
  const handleSemesterChange = async (semesterId: number) => {
    setCurrentEnrollment((prev) => ({
      ...prev!,
      semester_id: semesterId,
      group_id: "",
      unit_ids: [],
    }))
    
    setSemesterUnits([])
    setError(null)
    
    if (semesterId && semesterId > 0) {
      setIsLoading(true)
      
      try {
        console.log(`Fetching units for semester ${semesterId}`)
        
        const response = await axios.get(`/facultyadmin/sces/bbit/units/by-semester/${semesterId}`, {
          headers: {
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "",
            "Content-Type": "application/json",
            Accept: "application/json",
          },
        })

        console.log('API Response:', response.data)

        if (response.data.success && response.data.units && response.data.units.length > 0) {
          setSemesterUnits(response.data.units)
          console.log(`Loaded ${response.data.units.length} units for semester ${semesterId}`)
        } else {
          setSemesterUnits([])
          setError(`No units are assigned to the selected semester (ID: ${semesterId})`)
          console.log('No units found for semester', semesterId)
        }
      } catch (error: any) {
        console.error("Error fetching semester units:", error)
        setSemesterUnits([])
        
        if (error.response?.status === 404) {
          setError("API endpoint not found. Please check if the route is properly configured.")
        } else if (error.response?.data?.error) {
          setError(error.response.data.error)
        } else {
          setError("Failed to fetch units for the selected semester. Please try again.")
        }
      } finally {
        setIsLoading(false)
      }
    }
  }

  // BBIT specific submission handler
  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()

    if (currentEnrollment) {
      if (!currentEnrollment.student_code.trim()) {
        setError("Student code is required")
        return
      }

      if (!currentEnrollment.semester_id) {
        setError("Please select a semester")
        return
      }

      if (!currentEnrollment.group_id) {
        setError("Please select a group")
        return
      }

      if (!currentEnrollment.unit_ids.length) {
        setError("Please select at least one unit")
        return
      }

      const formattedEnrollment = {
        student_code: currentEnrollment.student_code.trim(),
        semester_id: Number(currentEnrollment.semester_id),
        group_id: Number(currentEnrollment.group_id),
        unit_ids: currentEnrollment.unit_ids.map((id) => Number(id)),
      }

      console.log('Submitting BBIT enrollment:', formattedEnrollment)

      router.post("/facultyadmin/sces/bbit/enrollments", formattedEnrollment, {
        onSuccess: () => {
          toast.success("Student enrolled in BBIT program successfully!")
          handleCloseModal()
        },
        onError: (errors) => {
          console.error("BBIT enrollment errors:", errors)
          if (errors.student_code) {
            setError(errors.student_code)
          } else if (errors.error) {
            setError(errors.error)
          } else {
            setError("An error occurred during BBIT enrollment. Please try again.")
          }
        },
      })
    }
  }

  // Selection handlers
  const handleSelectEnrollment = (enrollmentId: number) => {
    setSelectedEnrollments((prev) => {
      if (prev.includes(enrollmentId)) {
        const newSelection = prev.filter((id) => id !== enrollmentId)
        setIsSelectAllChecked(false)
        return newSelection
      } else {
        const newSelection = [...prev, enrollmentId]
        setIsSelectAllChecked(newSelection.length === currentEnrollments.length)
        return newSelection
      }
    })
  }

  const handleSelectAll = () => {
    if (isSelectAllChecked) {
      setSelectedEnrollments([])
      setIsSelectAllChecked(false)
    } else {
      const allEnrollmentIds = currentEnrollments.map((e) => e.id)
      setSelectedEnrollments(allEnrollmentIds)
      setIsSelectAllChecked(true)
    }
  }

  // BBIT specific bulk delete handler
  const handleBulkDelete = () => {
    if (selectedEnrollments.length === 0) {
      toast.error("Please select enrollments to delete")
      return
    }

    if (
      confirm(
        `Are you sure you want to delete ${selectedEnrollments.length} BBIT enrollment(s)? This action cannot be undone.`,
      )
    ) {
      router.delete("/facultyadmin/sces/bbit/enrollments/bulk", {
        data: { enrollment_ids: selectedEnrollments },
        onSuccess: () => {
          toast.success(`${selectedEnrollments.length} BBIT enrollment(s) deleted successfully!`)
          setSelectedEnrollments([])
          setIsSelectAllChecked(false)
        },
        onError: (errors) => {
          console.error("Bulk delete errors:", errors)
          toast.error("Failed to delete some BBIT enrollments. Please try again.")
        },
      })
    }
  }

  // BBIT specific delete handler for individual enrollments
  const handleDeleteEnrollment = (enrollmentId: number) => {
    if (confirm("Are you sure you want to delete this BBIT enrollment?")) {
      router.delete(`/facultyadmin/sces/bbit/enrollments/${enrollmentId}`, {
        onSuccess: () => {
          toast.success("BBIT enrollment deleted successfully!")
        },
        onError: (errors) => {
          console.error("Delete error:", errors)
          toast.error("Failed to delete BBIT enrollment.")
        },
      })
    }
  }

  // BBIT specific lecturer assignment handler
  const handleAssignLecturer = (e: React.FormEvent) => {
    e.preventDefault()
    if (assignData) {
      router.post("/facultyadmin/sces/bbit/assign-lecturer", assignData, {
        onSuccess: () => {
          toast.success("BBIT unit assigned to lecturer successfully!")
          handleCloseAssignModal()
        },
        onError: (errors) => {
          console.error("Error assigning BBIT unit:", errors)
          setError("Failed to assign BBIT unit to lecturer. Please try again.")
        },
      })
    }
  }

  // Update lecturer assignment
  const handleUpdateAssignment = (e: React.FormEvent) => {
    e.preventDefault()
    if (assignData && selectedAssignment) {
      router.put(`/facultyadmin/sces/bbit/lecturer-assignments/${selectedAssignment.unit_id}`, assignData, {
        onSuccess: () => {
          toast.success("Lecturer assignment updated successfully!")
          setIsEditAssignModalOpen(false)
          setSelectedAssignment(null)
          setAssignData(null)
        },
        onError: (errors) => {
          console.error("Error updating assignment:", errors)
          setError("Failed to update lecturer assignment. Please try again.")
        },
      })
    }
  }

  // Effects
  useEffect(() => {
    if (pageErrors && Object.keys(pageErrors).length > 0) {
      const errorMessage = Object.values(pageErrors).join(", ")
      setError(errorMessage)
    }
  }, [pageErrors])

  // Reset pagination when data changes
  useEffect(() => {
    setEnrollmentPage(1)
  }, [enrollments.length])

  useEffect(() => {
    setAssignmentPage(1)
  }, [lecturerAssignments.length])

  const canManage = userPermissions.includes('manage-faculty-enrollments-sces') || userRoles.includes('Faculty Admin - SCES')

  return (
    <AuthenticatedLayout>
      <Head title={`${programCode} Enrollments`} />
      <div className="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-cyan-50 py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          {/* Header Section */}
          <div className="mb-8">
            <div className="bg-white/80 backdrop-blur-sm rounded-3xl shadow-2xl border border-slate-200/50 p-8 relative overflow-hidden">
              <div className="absolute inset-0 bg-gradient-to-r from-blue-600/5 to-purple-600/5"></div>
              <div className="relative">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                  <div>
                    <h1 className="text-4xl font-bold bg-gradient-to-r from-slate-800 via-blue-800 to-indigo-800 bg-clip-text text-transparent mb-2 animate-in slide-in-from-left duration-500">
                      {programCode} Student Enrollments
                    </h1>
                    <p className="text-slate-600 text-lg">
                      Manage {programName} student course registrations and lecturer assignments
                    </p>
                    {currentSemester && (
                      <p className="text-sm text-blue-600 font-medium mt-1">
                        Current Semester: {currentSemester.name}
                      </p>
                    )}
                  </div>
                  {canManage && (
                    <div className="flex flex-col sm:flex-row gap-3 mt-6 sm:mt-0">
                      <button
                        onClick={handleOpenModal}
                        className="inline-flex items-center px-6 py-3 bg-gradient-to-r from-emerald-500 via-emerald-600 to-teal-600 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl hover:from-emerald-600 hover:via-emerald-700 hover:to-teal-700 transform hover:scale-105 hover:-translate-y-0.5 transition-all duration-300 group"
                      >
                        <svg
                          className="w-5 h-5 mr-2 group-hover:rotate-12 transition-transform duration-300"
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
                        >
                          <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M12 6v6m0 0v6m0-6h6m-6 0H6"
                          />
                        </svg>
                        Enroll {programCode} Student
                      </button>
                      <button
                        onClick={handleOpenAssignModal}
                        className="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-500 via-blue-600 to-indigo-600 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl hover:from-blue-600 hover:via-blue-700 hover:to-indigo-700 transform hover:scale-105 hover:-translate-y-0.5 transition-all duration-300 group"
                      >
                        <svg
                          className="w-5 h-5 mr-2 group-hover:rotate-12 transition-transform duration-300"
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
                        >
                          <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-6.5L12 18l-3-3"
                          />
                        </svg>
                        Assign {programCode} Lecturer
                      </button>
                    </div>
                  )}
                </div>
              </div>
            </div>
          </div>

          {/* Warning Section */}
          {(!semesters || semesters.length === 0) && (
            <div className="mb-6 bg-gradient-to-r from-amber-50 via-yellow-50 to-orange-50 border-l-4 border-amber-400 p-6 rounded-xl shadow-lg backdrop-blur-sm border border-amber-200/50">
              <div className="flex items-center">
                <svg className="w-6 h-6 text-amber-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"
                  />
                </svg>
                <div>
                  <h3 className="text-amber-800 font-semibold">No Semesters Available</h3>
                  <p className="text-amber-700 mt-1">Please check your database or contact the administrator.</p>
                </div>
              </div>
            </div>
          )}

          {/* Enrollments Table */}
          <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-2xl border border-slate-200/50 overflow-hidden mb-8 hover:shadow-3xl transition-shadow duration-300">
            <div className="px-8 py-6 bg-gradient-to-r from-slate-50 via-blue-50 to-indigo-50 border-b border-slate-200/50">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <h2 className="text-2xl font-bold text-slate-800">{programCode} Enrollments</h2>
                  <p className="text-slate-600 mt-1">
                    View and manage {programName} student course enrollments ({totalEnrollments} total)
                  </p>
                </div>
                {selectedEnrollments.length > 0 && canManage && (
                  <div className="flex items-center gap-3 mt-4 sm:mt-0">
                    <span className="text-sm font-medium text-slate-600">{selectedEnrollments.length} selected</span>
                    <button
                      onClick={handleBulkDelete}
                      className="inline-flex items-center px-4 py-2 bg-gradient-to-r from-red-500 to-red-600 text-white text-sm font-medium rounded-lg hover:from-red-600 hover:to-red-700 transform hover:scale-105 transition-all duration-200 shadow-md"
                    >
                      <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          strokeWidth={2}
                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                        />
                      </svg>
                      Delete Selected
                    </button>
                  </div>
                )}
              </div>
            </div>

            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-slate-50">
                  <tr>
                    {canManage && (
                      <th className="px-6 py-4 text-left">
                        <div className="flex items-center">
                          <input
                            type="checkbox"
                            checked={isSelectAllChecked}
                            onChange={handleSelectAll}
                            className="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2"
                          />
                          <label className="ml-2 text-xs font-semibold text-slate-600 uppercase tracking-wider">
                            Select All
                          </label>
                        </div>
                      </th>
                    )}
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Student Code
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Student Name
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Unit
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Group
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Class
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Lecturer
                    </th>
                    {canManage && (
                      <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                        Actions
                      </th>
                    )}
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200">
                  {currentEnrollments.length ? (
                    currentEnrollments.map((enrollment, index) => (
                      <tr
                        key={enrollment.id}
                        className={`hover:bg-slate-50 transition-colors duration-150 ${
                          index % 2 === 0 ? "bg-white" : "bg-slate-50/50"
                        }`}
                      >
                        {canManage && (
                          <td className="px-6 py-4">
                            <input
                              type="checkbox"
                              checked={selectedEnrollments.includes(enrollment.id)}
                              onChange={() => handleSelectEnrollment(enrollment.id)}
                              className="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2"
                            />
                          </td>
                        )}
                        <td className="px-6 py-4 text-sm font-medium text-slate-900">
                          {enrollment.student_code}
                        </td>
                        <td className="px-6 py-4 text-sm text-slate-700">
                          {enrollment.student_first_name && enrollment.student_last_name
                            ? `${enrollment.student_first_name} ${enrollment.student_last_name}`
                            : "N/A"}
                        </td>
                        <td className="px-6 py-4 text-sm text-slate-700">
                          <div>
                            <div className="font-medium">{enrollment.unit_name}</div>
                            <div className="text-xs text-slate-500">{enrollment.unit_code}</div>
                          </div>
                        </td>
                        <td className="px-6 py-4 text-sm text-slate-700">
                          {enrollment.group_name || "N/A"}
                          {enrollment.group_capacity && (
                            <div className="text-xs text-slate-500">
                              Capacity: {enrollment.group_capacity}
                            </div>
                          )}
                        </td>
                        <td className="px-6 py-4 text-sm text-slate-700">
                          {enrollment.class_name || "N/A"}
                        </td>
                        <td className="px-6 py-4 text-sm text-slate-700">
                          {enrollment.lecturer_first_name && enrollment.lecturer_last_name
                            ? `${enrollment.lecturer_first_name} ${enrollment.lecturer_last_name}`
                            : "Not Assigned"}
                        </td>
                        {canManage && (
                          <td className="px-6 py-4 text-sm font-medium">
                            <button
                              onClick={() => handleDeleteEnrollment(enrollment.id)}
                              className="text-red-600 hover:text-red-900 transition-colors duration-150"
                            >
                              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                  strokeLinecap="round"
                                  strokeLinejoin="round"
                                  strokeWidth={2}
                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                />
                              </svg>
                            </button>
                          </td>
                        )}
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td
                        colSpan={canManage ? 8 : 7}
                        className="px-6 py-12 text-center text-slate-500"
                      >
                        <div className="flex flex-col items-center">
                          <svg
                            className="w-12 h-12 text-slate-300 mb-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                          >
                            <path
                              strokeLinecap="round"
                              strokeLinejoin="round"
                              strokeWidth={2}
                              d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"
                            />
                          </svg>
                          <p className="text-lg font-medium">No enrollments found</p>
                          <p className="text-sm mt-1">Start by enrolling students in {programCode} courses</p>
                        </div>
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>

            {/* Enrollments Pagination */}
            {totalEnrollmentPages > 1 && (
              <Pagination
                currentPage={enrollmentPage}
                totalPages={totalEnrollmentPages}
                onPageChange={setEnrollmentPage}
                perPage={enrollmentPerPage}
                onPerPageChange={setEnrollmentPerPage}
                totalItems={totalEnrollments}
                startIndex={startEnrollmentIndex}
                endIndex={endEnrollmentIndex}
              />
            )}
          </div>

          {/* Lecturer Assignments Table */}
          <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-2xl border border-slate-200/50 overflow-hidden mb-8 hover:shadow-3xl transition-shadow duration-300">
            <div className="px-8 py-6 bg-gradient-to-r from-slate-50 via-purple-50 to-pink-50 border-b border-slate-200/50">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <h2 className="text-2xl font-bold text-slate-800">{programCode} Lecturer Assignments</h2>
                  <p className="text-slate-600 mt-1">
                    View and manage {programName} unit-lecturer assignments ({totalAssignments} total)
                  </p>
                </div>
              </div>
            </div>

            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-slate-50">
                  <tr>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Unit Code
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Unit Name
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Lecturer Code
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Lecturer Name
                    </th>
                    {canManage && (
                      <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                        Actions
                      </th>
                    )}
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200">
                  {currentAssignments.length ? (
                    currentAssignments.map((assignment, index) => (
                      <tr
                        key={`${assignment.unit_id}-${assignment.lecturer_code}`}
                        className={`hover:bg-slate-50 transition-colors duration-150 ${
                          index % 2 === 0 ? "bg-white" : "bg-slate-50/50"
                        }`}
                      >
                        <td className="px-6 py-4 text-sm font-medium text-slate-900">
                          {assignment.unit_code}
                        </td>
                        <td className="px-6 py-4 text-sm text-slate-700">
                          {assignment.unit_name}
                        </td>
                        <td className="px-6 py-4 text-sm text-slate-700">
                          {assignment.lecturer_code}
                        </td>
                        <td className="px-6 py-4 text-sm text-slate-700">
                          {assignment.lecturer_name}
                        </td>
                        {canManage && (
                          <td className="px-6 py-4 text-sm font-medium">
                            <div className="flex items-center space-x-2">
                              <button
                                onClick={() => handleViewAssignment(assignment)}
                                className="text-blue-600 hover:text-blue-900 transition-colors duration-150"
                                title="View assignment"
                              >
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                  <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                  />
                                  <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                  />
                                </svg>
                              </button>
                              <button
                                onClick={() => handleEditAssignment(assignment)}
                                className="text-indigo-600 hover:text-indigo-900 transition-colors duration-150"
                                title="Edit assignment"
                              >
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                  <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                                  />
                                </svg>
                              </button>
                              <button
                                onClick={() => handleDeleteAssignment(assignment)}
                                className="text-red-600 hover:text-red-900 transition-colors duration-150"
                                title="Remove assignment"
                              >
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                  <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                  />
                                </svg>
                              </button>
                            </div>
                          </td>
                        )}
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td
                        colSpan={canManage ? 5 : 4}
                        className="px-6 py-12 text-center text-slate-500"
                      >
                        <div className="flex flex-col items-center">
                          <svg
                            className="w-12 h-12 text-slate-300 mb-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                          >
                            <path
                              strokeLinecap="round"
                              strokeLinejoin="round"
                              strokeWidth={2}
                              d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-6.5L12 18l-3-3"
                            />
                          </svg>
                          <p className="text-lg font-medium">No lecturer assignments found</p>
                          <p className="text-sm mt-1">Start by assigning lecturers to {programCode} units</p>
                        </div>
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>

            {/* Assignments Pagination */}
            {totalAssignmentPages > 1 && (
              <Pagination
                currentPage={assignmentPage}
                totalPages={totalAssignmentPages}
                onPageChange={setAssignmentPage}
                perPage={assignmentPerPage}
                onPerPageChange={setAssignmentPerPage}
                totalItems={totalAssignments}
                startIndex={startAssignmentIndex}
                endIndex={endAssignmentIndex}
              />
            )}
          </div>

          {/* Enrollment Modal */}
          {isModalOpen && (
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
              <div className="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div className="bg-gradient-to-r from-emerald-500 via-emerald-600 to-teal-600 p-6 rounded-t-2xl">
                  <div className="flex items-center justify-between">
                    <h3 className="text-xl font-semibold text-white">
                      Enroll {programCode} Student
                    </h3>
                    <button
                      onClick={handleCloseModal}
                      className="text-white hover:text-emerald-100 transition-colors duration-200"
                    >
                      <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          strokeWidth={2}
                          d="M6 18L18 6M6 6l12 12"
                        />
                      </svg>
                    </button>
                  </div>
                </div>

                <form onSubmit={handleSubmit} className="p-6 space-y-6">
                  {error && (
                    <div className="bg-red-50 border border-red-200 rounded-lg p-4">
                      <div className="flex items-center">
                        <svg className="w-5 h-5 text-red-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                          />
                        </svg>
                        <p className="text-red-700 text-sm">{error}</p>
                      </div>
                    </div>
                  )}

                  <div>
                    <label className="block text-sm font-medium text-slate-700 mb-2">
                      Student Code <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="text"
                      value={currentEnrollment?.student_code || ""}
                      onChange={(e) =>
                        setCurrentEnrollment((prev) => ({
                          ...prev!,
                          student_code: e.target.value,
                        }))
                      }
                      className="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-colors duration-200"
                      placeholder="Enter student code"
                      required
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-slate-700 mb-2">
                      Semester <span className="text-red-500">*</span>
                    </label>
                    <select
                      value={currentEnrollment?.semester_id || ""}
                      onChange={(e) => handleSemesterChange(Number(e.target.value))}
                      className="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-colors duration-200"
                      required
                    >
                      <option value="">Select Semester</option>
                      {semesters.map((semester) => (
                        <option key={semester.id} value={semester.id}>
                          {semester.name}
                        </option>
                      ))}
                    </select>
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-slate-700 mb-2">
                      Group <span className="text-red-500">*</span>
                    </label>
                    <select
                      value={currentEnrollment?.group_id || ""}
                      onChange={(e) =>
                        setCurrentEnrollment((prev) => ({
                          ...prev!,
                          group_id: e.target.value,
                        }))
                      }
                      className="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-colors duration-200"
                      required
                    >
                      <option value="">Select Group</option>
                      {groups.map((group) => (
                        <option key={group.id} value={group.id}>
                          {group.name} - {group.class.name} (Capacity: {group.capacity})
                        </option>
                      ))}
                    </select>
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-slate-700 mb-2">
                      Units <span className="text-red-500">*</span>
                    </label>
                    {isLoading ? (
                      <div className="flex items-center justify-center py-8">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-500"></div>
                        <span className="ml-3 text-slate-600">Loading units...</span>
                      </div>
                    ) : semesterUnits.length > 0 ? (
                      <div className="border border-slate-300 rounded-lg p-4 max-h-64 overflow-y-auto">
                        {semesterUnits.map((unit) => (
                          <div key={unit.id} className="flex items-center space-x-3 py-2">
                            <input
                              type="checkbox"
                              id={`unit-${unit.id}`}
                              checked={currentEnrollment?.unit_ids.includes(unit.id) || false}
                              onChange={(e) => {
                                const isChecked = e.target.checked
                                setCurrentEnrollment((prev) => ({
                                  ...prev!,
                                  unit_ids: isChecked
                                    ? [...prev!.unit_ids, unit.id]
                                    : prev!.unit_ids.filter((id) => id !== unit.id),
                                }))
                              }}
                              className="w-4 h-4 text-emerald-600 bg-gray-100 border-gray-300 rounded focus:ring-emerald-500 focus:ring-2"
                            />
                            <label htmlFor={`unit-${unit.id}`} className="flex-1 text-sm text-slate-700">
                              <span className="font-medium">{unit.code}</span> - {unit.name}
                              <span className="text-slate-500 ml-2">({unit.credit_hours} credits)</span>
                            </label>
                          </div>
                        ))}
                      </div>
                    ) : (
                      <div className="border border-slate-300 rounded-lg p-8 text-center text-slate-500">
                        {currentEnrollment?.semester_id
                          ? "No units available for the selected semester"
                          : "Please select a semester to view available units"}
                      </div>
                    )}
                  </div>

                  <div className="flex items-center justify-end space-x-4 pt-6 border-t border-slate-200">
                    <button
                      type="button"
                      onClick={handleCloseModal}
                      className="px-6 py-3 text-slate-700 bg-slate-100 rounded-lg hover:bg-slate-200 transition-colors duration-200 font-medium"
                    >
                      Cancel
                    </button>
                    <button
                      type="submit"
                      disabled={isLoading}
                      className="px-6 py-3 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white rounded-lg hover:from-emerald-600 hover:to-emerald-700 transition-all duration-200 font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      {isLoading ? "Processing..." : "Enroll Student"}
                    </button>
                  </div>
                </form>
              </div>
            </div>
          )}

          {/* Assign Lecturer Modal */}
          {isAssignModalOpen && (
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
              <div className="bg-white rounded-2xl shadow-2xl max-w-lg w-full">
                <div className="bg-gradient-to-r from-blue-500 via-blue-600 to-indigo-600 p-6 rounded-t-2xl">
                  <div className="flex items-center justify-between">
                    <h3 className="text-xl font-semibold text-white">
                      Assign {programCode} Lecturer
                    </h3>
                    <button
                      onClick={handleCloseAssignModal}
                      className="text-white hover:text-blue-100 transition-colors duration-200"
                    >
                      <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          strokeWidth={2}
                          d="M6 18L18 6M6 6l12 12"
                        />
                      </svg>
                    </button>
                  </div>
                </div>

                <form onSubmit={handleAssignLecturer} className="p-6 space-y-6">
                  {error && (
                    <div className="bg-red-50 border border-red-200 rounded-lg p-4">
                      <div className="flex items-center">
                        <svg className="w-5 h-5 text-red-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                          />
                        </svg>
                        <p className="text-red-700 text-sm">{error}</p>
                      </div>
                    </div>
                  )}

                  <div>
                    <label className="block text-sm font-medium text-slate-700 mb-2">
                      Unit <span className="text-red-500">*</span>
                    </label>
                    <select
                      value={assignData?.unit_id || ""}
                      onChange={(e) =>
                        setAssignData((prev) => ({
                          ...prev!,
                          unit_id: Number(e.target.value),
                        }))
                      }
                      className="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200"
                      required
                    >
                      <option value="">Select Unit</option>
                      {bbitUnits.map((unit) => (
                        <option key={unit.id} value={unit.id}>
                          {unit.code} - {unit.name}
                        </option>
                      ))}
                    </select>
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-slate-700 mb-2">
                      Lecturer Code <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="text"
                      value={assignData?.lecturer_code || ""}
                      onChange={(e) =>
                        setAssignData((prev) => ({
                          ...prev!,
                          lecturer_code: e.target.value,
                        }))
                      }
                      className="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200"
                      placeholder="Enter lecturer code"
                      required
                    />
                  </div>

                  <div className="flex items-center justify-end space-x-4 pt-6 border-t border-slate-200">
                    <button
                      type="button"
                      onClick={handleCloseAssignModal}
                      className="px-6 py-3 text-slate-700 bg-slate-100 rounded-lg hover:bg-slate-200 transition-colors duration-200 font-medium"
                    >
                      Cancel
                    </button>
                    <button
                      type="submit"
                      className="px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-lg hover:from-blue-600 hover:to-blue-700 transition-all duration-200 font-medium"
                    >
                      Assign Lecturer
                    </button>
                  </div>
                </form>
              </div>
            </div>
          )}

          {/* Edit Assignment Modal */}
          {isEditAssignModalOpen && selectedAssignment && (
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
              <div className="bg-white rounded-2xl shadow-2xl max-w-lg w-full">
                <div className="bg-gradient-to-r from-indigo-500 via-indigo-600 to-purple-600 p-6 rounded-t-2xl">
                  <div className="flex items-center justify-between">
                    <h3 className="text-xl font-semibold text-white">
                      Edit Lecturer Assignment
                    </h3>
                    <button
                      onClick={() => setIsEditAssignModalOpen(false)}
                      className="text-white hover:text-indigo-100 transition-colors duration-200"
                    >
                      <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          strokeWidth={2}
                          d="M6 18L18 6M6 6l12 12"
                        />
                      </svg>
                    </button>
                  </div>
                </div>

                <form onSubmit={handleUpdateAssignment} className="p-6 space-y-6">
                  {error && (
                    <div className="bg-red-50 border border-red-200 rounded-lg p-4">
                      <div className="flex items-center">
                        <svg className="w-5 h-5 text-red-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                          />
                        </svg>
                        <p className="text-red-700 text-sm">{error}</p>
                      </div>
                    </div>
                  )}

                  <div>
                    <label className="block text-sm font-medium text-slate-700 mb-2">Unit</label>
                    <div className="px-4 py-3 bg-slate-50 border border-slate-200 rounded-lg text-slate-700">
                      {selectedAssignment.unit_code} - {selectedAssignment.unit_name}
                    </div>
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-slate-700 mb-2">
                      Lecturer Code <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="text"
                      value={assignData?.lecturer_code || ""}
                      onChange={(e) =>
                        setAssignData((prev) => ({
                          ...prev!,
                          lecturer_code: e.target.value,
                        }))
                      }
                      className="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors duration-200"
                      placeholder="Enter lecturer code"
                      required
                    />
                  </div>

                  <div className="flex items-center justify-end space-x-4 pt-6 border-t border-slate-200">
                    <button
                      type="button"
                      onClick={() => setIsEditAssignModalOpen(false)}
                      className="px-6 py-3 text-slate-700 bg-slate-100 rounded-lg hover:bg-slate-200 transition-colors duration-200 font-medium"
                    >
                      Cancel
                    </button>
                    <button
                      type="submit"
                      className="px-6 py-3 bg-gradient-to-r from-indigo-500 to-indigo-600 text-white rounded-lg hover:from-indigo-600 hover:to-indigo-700 transition-all duration-200 font-medium"
                    >
                      Update Assignment
                    </button>
                  </div>
                </form>
              </div>
            </div>
          )}

          {/* View Assignment Modal */}
          {isViewAssignModalOpen && selectedAssignment && (
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
              <div className="bg-white rounded-2xl shadow-2xl max-w-lg w-full">
                <div className="bg-gradient-to-r from-slate-500 via-slate-600 to-gray-600 p-6 rounded-t-2xl">
                  <div className="flex items-center justify-between">
                    <h3 className="text-xl font-semibold text-white">
                      Lecturer Assignment Details
                    </h3>
                    <button
                      onClick={() => setIsViewAssignModalOpen(false)}
                      className="text-white hover:text-slate-100 transition-colors duration-200"
                    >
                      <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          strokeWidth={2}
                          d="M6 18L18 6M6 6l12 12"
                        />
                      </svg>
                    </button>
                  </div>
                </div>

                <div className="p-6 space-y-6">
                  <div className="grid grid-cols-1 gap-6">
                    <div>
                      <label className="block text-sm font-medium text-slate-700 mb-2">Unit Code</label>
                      <div className="px-4 py-3 bg-slate-50 border border-slate-200 rounded-lg text-slate-700 font-medium">
                        {selectedAssignment.unit_code}
                      </div>
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-slate-700 mb-2">Unit Name</label>
                      <div className="px-4 py-3 bg-slate-50 border border-slate-200 rounded-lg text-slate-700">
                        {selectedAssignment.unit_name}
                      </div>
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-slate-700 mb-2">Lecturer Code</label>
                      <div className="px-4 py-3 bg-slate-50 border border-slate-200 rounded-lg text-slate-700 font-medium">
                        {selectedAssignment.lecturer_code}
                      </div>
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-slate-700 mb-2">Lecturer Name</label>
                      <div className="px-4 py-3 bg-slate-50 border border-slate-200 rounded-lg text-slate-700">
                        {selectedAssignment.lecturer_name}
                      </div>
                    </div>
                  </div>

                  <div className="flex items-center justify-end space-x-4 pt-6 border-t border-slate-200">
                    <button
                      onClick={() => setIsViewAssignModalOpen(false)}
                      className="px-6 py-3 text-slate-700 bg-slate-100 rounded-lg hover:bg-slate-200 transition-colors duration-200 font-medium"
                    >
                      Close
                    </button>
                    {canManage && (
                      <button
                        onClick={() => {
                          setIsViewAssignModalOpen(false)
                          handleEditAssignment(selectedAssignment)
                        }}
                        className="px-6 py-3 bg-gradient-to-r from-indigo-500 to-indigo-600 text-white rounded-lg hover:from-indigo-600 hover:to-indigo-700 transition-all duration-200 font-medium"
                      >
                        Edit Assignment
                      </button>
                    )}
                  </div>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
    </AuthenticatedLayout>
  )
}

export default Enrollments