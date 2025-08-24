"use client"

import type React from "react"
import { useState, useEffect } from "react"
import { Head, usePage, router } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import axios from "axios"
import { toast } from "react-hot-toast"
import Pagination from "@/Components/Pagination"

// Define interfaces
interface Semester {
  id: number
  name: string
}

interface Group {
  id: number
  name: string
  class: { id: number; name: string }
  capacity: number
}

interface Class {
  id: number
  name: string
  semester_id: number
}

interface Unit {
  id: number
  name: string
  code?: string
  program?: { id: number; name: string }
  school?: { id: number; name: string }
}

interface Student {
  id: number
  code: string
  first_name: string
  last_name: string
  name?: string
}

interface Enrollment {
  id: number
  student_code: string | null
  group_id: string | null
  unit_id: number
  semester_id: number
  student: Student | null
  unit: Unit | null
  group: Group | null
}

interface PaginationLinks {
  url: string | null
  label: string
  active: boolean
}

interface PaginatedEnrollments {
  data: Enrollment[]
  links: PaginationLinks[]
  total: number
  per_page: number
  current_page: number
}

interface PaginatedLecturerAssignments {
  data: LecturerAssignment[]
  links: PaginationLinks[]
  total: number
  per_page: number
  current_page: number
  last_page: number
}

interface LecturerAssignment {
  unit_id: number
  unit_name: string
  unit_code: string
  lecturer_code: string
  lecturer_name: string
}

interface PageProps {
  enrollments: PaginatedEnrollments | null
  semesters: Semester[] | null
  groups: Group[] | null
  classes: Class[] | null
  units: Unit[] | null
  lecturerAssignments: PaginatedLecturerAssignments | null
  errors: Record<string, string>
}

const Enrollments: React.FC = () => {
  const pageProps = usePage<PageProps>().props

  const {
    enrollments,
    semesters = [],
    groups = [],
    classes = [],
    units = [],
    lecturerAssignments,
    errors: pageErrors,
  } = pageProps

  const [isModalOpen, setIsModalOpen] = useState(false)
  const [currentEnrollment, setCurrentEnrollment] = useState<{
    student_code: string
    semester_id: number
    class_id: number
    group_id: string
    unit_ids: number[]
  } | null>(null)
  const [filteredClasses, setFilteredClasses] = useState<Class[]>([])
  const [filteredGroups, setFilteredGroups] = useState<Group[]>([])
  const [filteredUnits, setFilteredUnits] = useState<Unit[]>([])
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const [isAssignModalOpen, setIsAssignModalOpen] = useState(false)
  const [assignData, setAssignData] = useState<{ unit_id: number; lecturer_code: string } | null>(null)
  const [assignSemesterId, setAssignSemesterId] = useState<number | null>(null)
  const [assignClassId, setAssignClassId] = useState<number | null>(null)
  const [assignUnits, setAssignUnits] = useState<Unit[]>([])

  const [enrollmentsPage, setEnrollmentsPage] = useState(1)
  const [lecturerAssignmentsPage, setLecturerAssignmentsPage] = useState(1)
  const [selectedEnrollments, setSelectedEnrollments] = useState<number[]>([])
  const [isSelectAllChecked, setIsSelectAllChecked] = useState(false)
  const [lecturerAssignmentsPerPage, setLecturerAssignmentsPerPage] = useState(15)

  // Pagination handlers
  const handleLecturerAssignmentsPageChange = (url: string | null) => {
    if (url) {
      const urlObj = new URL(url, window.location.origin)
      urlObj.searchParams.set("lecturer_per_page", lecturerAssignmentsPerPage.toString())
      router.get(
        urlObj.toString(),
        {},
        {
          preserveState: true,
          preserveScroll: true,
          only: ["lecturerAssignments"],
        },
      )
      const pageParam = urlObj.searchParams.get("lecturer_page")
      setLecturerAssignmentsPage(Number(pageParam) || 1)
    }
  }

  const handleLecturerAssignmentsPerPageChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const perPage = Number(e.target.value)
    setLecturerAssignmentsPerPage(perPage)
    router.get(
      window.location.pathname,
      { lecturer_page: 1, lecturer_per_page: perPage },
      {
        preserveState: true,
        preserveScroll: true,
        only: ["lecturerAssignments"],
      },
    )
    setLecturerAssignmentsPage(1)
  }

  const handleEnrollmentsPageChange = (url: string | null) => {
    if (url) {
      router.get(url, {}, { preserveState: true, preserveScroll: true })
      setEnrollmentsPage(Number(new URL(url).searchParams.get("page")) || 1)
    }
  }

  // Modal handlers
  const handleOpenModal = () => {
    setCurrentEnrollment({
      student_code: "",
      semester_id: 0,
      class_id: 0,
      group_id: "",
      unit_ids: [],
    })
    setFilteredClasses([])
    setFilteredGroups([])
    setFilteredUnits([])
    setIsModalOpen(true)
    setError(null)
  }

  const handleCloseModal = () => {
    setIsModalOpen(false)
    setCurrentEnrollment(null)
    setError(null)
  }

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

  // Form handlers
  const handleSemesterChange = (semesterId: number) => {
    setCurrentEnrollment((prev) => ({
      ...prev!,
      semester_id: semesterId,
      class_id: 0,
      group_id: "",
      unit_ids: [],
    }))

    const filtered = (classes || []).filter((cls) => cls.semester_id === semesterId)
    setFilteredClasses(filtered)
    setFilteredGroups([])
    setFilteredUnits([])
  }

  const handleClassChange = async (classId: number) => {
    setCurrentEnrollment((prev) => ({
      ...prev!,
      class_id: classId,
      group_id: "",
      unit_ids: [],
    }))

    const filtered = (groups || []).filter((group) => group.class?.id === classId)
    setFilteredGroups(filtered)
    setFilteredUnits([])
    setIsLoading(true)
    setError(null)

    if (currentEnrollment?.semester_id) {
      try {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || ""

        const response = await axios.get("/units/by-class-and-semester", {
          params: {
            semester_id: currentEnrollment.semester_id,
            class_id: classId,
          },
          headers: {
            "X-CSRF-TOKEN": token,
            "Content-Type": "application/json",
            Accept: "application/json",
          },
        })

        if (response.data.units && Array.isArray(response.data.units)) {
          setFilteredUnits(response.data.units)
        } else if (Array.isArray(response.data)) {
          setFilteredUnits(response.data)
        } else {
          setFilteredUnits([])
          setError("No units found for this class and semester.")
        }
      } catch (error: any) {
        console.error("Error fetching units:", error)
        setError("Failed to fetch units. Please try again.")
        setFilteredUnits([])
      } finally {
        setIsLoading(false)
      }
    } else {
      setError("Please select a semester before selecting a class.")
      setIsLoading(false)
    }
  }

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

      if (!currentEnrollment.class_id) {
        setError("Please select a class")
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

      router.post("/enrollments", formattedEnrollment, {
        onSuccess: () => {
          toast.success("Student enrolled successfully!")
          handleCloseModal()
        },
        onError: (errors) => {
          console.error("Enrollment errors:", errors)
          if (errors.student_code) {
            setError(errors.student_code)
          } else if (errors.error) {
            setError(errors.error)
          } else {
            setError("An error occurred during enrollment. Please try again.")
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
        const allEnrollmentIds = enrollments?.data?.map((e) => e.id) || []
        setIsSelectAllChecked(newSelection.length === allEnrollmentIds.length)
        return newSelection
      }
    })
  }

  const handleSelectAll = () => {
    if (isSelectAllChecked) {
      setSelectedEnrollments([])
      setIsSelectAllChecked(false)
    } else {
      const allEnrollmentIds = enrollments?.data?.map((e) => e.id) || []
      setSelectedEnrollments(allEnrollmentIds)
      setIsSelectAllChecked(true)
    }
  }

  const handleBulkDelete = () => {
    if (selectedEnrollments.length === 0) {
      toast.error("Please select enrollments to delete")
      return
    }

    if (
      confirm(
        `Are you sure you want to delete ${selectedEnrollments.length} enrollment(s)? This action cannot be undone.`,
      )
    ) {
      router.post(
        "/enrollments/bulk-delete",
        { enrollment_ids: selectedEnrollments },
        {
          onSuccess: () => {
            toast.success(`${selectedEnrollments.length} enrollment(s) deleted successfully!`)
            setSelectedEnrollments([])
            setIsSelectAllChecked(false)
          },
          onError: (errors) => {
            console.error("Bulk delete errors:", errors)
            toast.error("Failed to delete some enrollments. Please try again.")
          },
        },
      )
    }
  }

  // Lecturer assignment handlers Delete, edit, show actions

const [selectedAssignment, setSelectedAssignment] = useState<LecturerAssignment | null>(null)
const [modalType, setModalType] = useState<"" | "show" | "edit" | "delete">("")

// Handler functions
const handleShowAssignment = (assignment: LecturerAssignment) => {
  setSelectedAssignment(assignment)
  setModalType("show")
}

const handleEditAssignment = (assignment: LecturerAssignment) => {
  setSelectedAssignment(assignment)
  setModalType("edit")
  setAssignData({
    unit_id: assignment.unit_id,
    lecturer_code: assignment.lecturer_code,
  })
  setIsAssignModalOpen(true)
}

const handleDeleteAssignment = (assignment: LecturerAssignment) => {
  if (confirm("Are you sure you want to delete this lecturer assignment?")) {
    router.delete(`/lecturer-assignments/${assignment.unit_id}`, {
      onSuccess: () => {
        toast.success("Lecturer assignment deleted successfully!")
        setModalType("")
        setSelectedAssignment(null)
      },
      onError: () => {
        toast.error("Failed to delete lecturer assignment.")
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

return (
    <AuthenticatedLayout>
      <Head title="Enrollments" />
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
                      Student Enrollments
                    </h1>
                    <p className="text-slate-600 text-lg">
                      Manage student course registrations and lecturer assignments
                    </p>
                  </div>
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
                      Enroll Student
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
                      Assign Lecturer
                    </button>
                  </div>
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
                  <h2 className="text-2xl font-bold text-slate-800">Current Enrollments</h2>
                  <p className="text-slate-600 mt-1">View and manage all student course enrollments</p>
                </div>
                {selectedEnrollments.length > 0 && (
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
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      ID
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Student
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Group
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Class
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Unit
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200">
                  {enrollments?.data?.length ? (
                    enrollments.data.map((enrollment, index) => (
                      <tr
                        key={enrollment.id}
                        className={`hover:bg-slate-50 transition-colors duration-150 ${index % 2 === 0 ? "bg-white" : "bg-slate-25"} ${selectedEnrollments.includes(enrollment.id) ? "bg-blue-50 border-l-4 border-blue-500" : ""}`}
                      >
                        <td className="px-6 py-4 whitespace-nowrap">
                          <input
                            type="checkbox"
                            checked={selectedEnrollments.includes(enrollment.id)}
                            onChange={() => handleSelectEnrollment(enrollment.id)}
                            className="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2"
                          />
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <div className="flex items-center">
                            <div className="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                              <span className="text-blue-600 font-semibold text-sm">{enrollment.id}</span>
                            </div>
                          </div>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <div className="text-sm font-medium text-slate-900">{enrollment.student_code || "N/A"}</div>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <span className="inline-flex px-3 py-1 text-xs font-medium bg-purple-100 text-purple-800 rounded-full">
                            {enrollment.group ? enrollment.group.name : enrollment.group_id || "N/A"}
                          </span>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <span className="inline-flex px-3 py-1 text-xs font-medium bg-indigo-100 text-indigo-800 rounded-full">
                            {enrollment.group && enrollment.group.class ? enrollment.group.class.name : "N/A"}
                          </span>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <div className="text-sm text-slate-900">{enrollment.unit ? enrollment.unit.name : "N/A"}</div>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <button
                            onClick={() => router.delete(`/enrollments/${enrollment.id}`)}
                            className="inline-flex items-center px-4 py-2 bg-gradient-to-r from-red-500 to-red-600 text-white text-sm font-medium rounded-lg hover:from-red-600 hover:to-red-700 transform hover:scale-105 transition-all duration-200 shadow-md"
                          >
                            <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth={2}
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                              />
                            </svg>
                            Remove
                          </button>
                        </td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan={7} className="px-6 py-12 text-center">
                        <div className="flex flex-col items-center">
                          <svg
                            className="w-16 h-16 text-slate-300 mb-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                          >
                            <path
                              strokeLinecap="round"
                              strokeLinejoin="round"
                              strokeWidth={1}
                              d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"
                            />
                          </svg>
                          <h3 className="text-lg font-medium text-slate-900 mb-1">No enrollments found</h3>
                          <p className="text-slate-500">Start by enrolling your first student</p>
                        </div>
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>

            {enrollments?.links && (
              <div className="px-6 py-4 bg-gradient-to-r from-slate-50 to-blue-50 border-t border-slate-200/50">
                <Pagination
                  links={enrollments.links}
                  onPageChange={handleEnrollmentsPageChange}
                  currentPage={enrollmentsPage}
                />
              </div>
            )}
          </div>

          {/* Lecturer Assignments Table */}
          <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-2xl border border-slate-200/50 overflow-hidden">
            <div className="px-8 py-6 bg-gradient-to-r from-slate-50 via-blue-50 to-indigo-50 border-b border-slate-200/50">
              <div className="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                  <h2 className="text-2xl font-bold text-slate-800">Lecturer Assignments</h2>
                  <p className="text-slate-600 mt-1">
                    View current unit-lecturer assignments
                    {lecturerAssignments && (
                      <span className="font-medium">
                        ({lecturerAssignments.data?.length || 0} of {lecturerAssignments.total || 0} total)
                      </span>
                    )}
                  </p>
                </div>
                <div className="mt-4 md:mt-0 flex items-center gap-2">
                  <label htmlFor="lecturerAssignmentsPerPage" className="text-sm text-slate-600">
                    Show
                  </label>
                  <select
                    id="lecturerAssignmentsPerPage"
                    value={lecturerAssignmentsPerPage}
                    onChange={handleLecturerAssignmentsPerPageChange}
                    className="border rounded px-2 py-1 text-sm"
                  >
                    <option value={10}>10</option>
                    <option value={15}>15</option>
                    <option value={25}>25</option>
                    <option value={50}>50</option>
                  </select>
                  <span className="text-sm text-slate-600">per page</span>
                </div>
              </div>
            </div>

            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-slate-50">
                  <tr>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Unit
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Unit Code
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Lecturer Code
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Lecturer Name
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200">
                  {lecturerAssignments?.data?.length ? (
                    lecturerAssignments.data.map((assignment, index) => (
                      <tr
                        key={`${assignment.unit_id}-${assignment.lecturer_code}-${index}`}
                        className={`hover:bg-slate-50 transition-colors duration-150 ${
                          index % 2 === 0 ? "bg-white" : "bg-slate-25"
                        }`}
                      >
                        <td className="px-6 py-4 whitespace-nowrap">
                          <div className="text-sm font-medium text-slate-900">{assignment.unit_name}</div>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <span className="inline-flex px-3 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">
                            {assignment.unit_code || "N/A"}
                          </span>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <span className="inline-flex px-3 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                            {assignment.lecturer_code}
                          </span>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <div className="text-sm text-slate-900">{assignment.lecturer_name || "Unknown"}</div>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
          <div className="flex gap-2">
            <button
              onClick={() => handleShowAssignment(assignment)}
              className="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs"
            >
              Show
            </button>
            <button
              onClick={() => handleEditAssignment(assignment)}
              className="px-2 py-1 bg-yellow-100 text-yellow-700 rounded text-xs"
            >
              Edit
            </button>
             <button
              onClick={() => handleDeleteAssignment(assignment)}
              className="px-2 py-1 bg-red-100 text-red-700 rounded text-xs"
            >
              Delete
            </button> 
          </div>
        </td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan={4} className="px-6 py-12 text-center">
                        <div className="flex flex-col items-center">
                          <svg
                            className="w-16 h-16 text-slate-300 mb-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                          >
                            <path
                              strokeLinecap="round"
                              strokeLinejoin="round"
                              strokeWidth={1}
                              d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-6.5L12 18l-3-3"
                            />
                          </svg>
                          <h3 className="text-lg font-medium text-slate-900 mb-1">No lecturer assignments found</h3>
                          <p className="text-slate-500">Start by assigning units to lecturers</p>
                        </div>
                      </td>
                    </tr>
                  )}
                </tbody>
                {/* Modal for showing assignment details */}
{modalType === "show" && selectedAssignment && (
  <div className="fixed inset-0 flex items-center justify-center bg-black/60 z-50">
    <div className="bg-white p-6 rounded-xl shadow-xl max-w-md w-full">
      <h2 className="text-lg font-bold mb-4">Lecturer Assignment Details</h2>
      <div className="mb-2"><b>Unit:</b> {selectedAssignment.unit_name}</div>
      <div className="mb-2"><b>Unit Code:</b> {selectedAssignment.unit_code}</div>
      <div className="mb-2"><b>Lecturer Code:</b> {selectedAssignment.lecturer_code}</div>
      <div className="mb-2"><b>Lecturer Name:</b> {selectedAssignment.lecturer_name}</div>
      <button
        onClick={() => { setModalType(""); setSelectedAssignment(null); }}
        className="mt-4 px-4 py-2 bg-gray-200 rounded"
      >
        Close
      </button>
    </div>
  </div>
)}
              </table>
            </div>

            {lecturerAssignments?.links && lecturerAssignments.links.length > 3 && (
              <div className="px-6 py-4 bg-gradient-to-r from-slate-50 to-blue-50 border-t border-slate-200/50">
                <div className="flex items-center justify-between">
                  <div className="text-sm text-slate-600">
                    Showing {(lecturerAssignments.current_page - 1) * lecturerAssignments.per_page + 1} to{" "}
                    {Math.min(
                      lecturerAssignments.current_page * lecturerAssignments.per_page,
                      lecturerAssignments.total,
                    )}{" "}
                    of {lecturerAssignments.total} lecturer assignments
                  </div>
                  <Pagination
                    links={lecturerAssignments.links}
                    onPageChange={handleLecturerAssignmentsPageChange}
                    currentPage={lecturerAssignments.current_page}
                  />
                </div>
              </div>
            )}
          </div>
        </div>
      </div>
      

      {/* Enrollment Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 flex items-center justify-center bg-black/60 backdrop-blur-sm z-50 animate-in fade-in duration-300">
          <div className="bg-white/95 backdrop-blur-sm p-8 rounded-2xl shadow-2xl max-w-lg w-full mx-4 border border-slate-200/50 animate-in slide-in-from-bottom-4 duration-300">
            <h2 className="text-xl font-bold mb-4">Enroll Student</h2>
            {error && <div className="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">{error}</div>}
            <form onSubmit={handleSubmit}>
              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700 mb-2">Student Code</label>
                <input
                  type="text"
                  value={currentEnrollment?.student_code || ""}
                  onChange={(e) =>
                    setCurrentEnrollment((prev) => ({
                      ...prev!,
                      student_code: e.target.value,
                    }))
                  }
                  className="w-full border border-slate-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-slate-50 focus:bg-white hover:border-slate-400"
                  required
                  placeholder="Enter student code (e.g., BBIT0001)"
                />
              </div>

              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700 mb-2">Semester</label>
                <select
                  value={currentEnrollment?.semester_id || ""}
                  onChange={(e) => handleSemesterChange(Number(e.target.value))}
                  className="w-full border border-slate-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-slate-50 focus:bg-white hover:border-slate-400"
                  required
                >
                  <option value="">Select a semester</option>
                  {semesters && semesters.length > 0 ? (
                    semesters.map((semester) => (
                      <option key={semester.id} value={semester.id}>
                        {semester.name}
                      </option>
                    ))
                  ) : (
                    <option value="" disabled>
                      No semesters available
                    </option>
                  )}
                </select>
              </div>

              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700 mb-2">Class</label>
                <select
                  value={currentEnrollment?.class_id || ""}
                  onChange={(e) => handleClassChange(Number(e.target.value))}
                  className="w-full border border-slate-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-slate-50 focus:bg-white hover:border-slate-400"
                  required
                  disabled={!currentEnrollment?.semester_id}
                >
                  <option value="">Select a class</option>
                  {filteredClasses.map((classItem) => (
                    <option key={classItem.id} value={classItem.id}>
                      {classItem.name}
                    </option>
                  ))}
                </select>
              </div>

              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700 mb-2">Group</label>
                <select
                  value={currentEnrollment?.group_id || ""}
                  onChange={(e) =>
                    setCurrentEnrollment((prev) => ({
                      ...prev!,
                      group_id: e.target.value,
                    }))
                  }
                  className="w-full border border-slate-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-slate-50 focus:bg-white hover:border-slate-400"
                  required
                  disabled={!currentEnrollment?.class_id}
                >
                  <option value="">Select a group</option>
                  {filteredGroups.map((group) => (
                    <option key={group.id} value={group.id}>
                      {group.name} (Capacity: {group.capacity})
                    </option>
                  ))}
                </select>
              </div>
                  <div className="mb-6">
  <label className="block text-sm font-medium text-gray-700 mb-2">Units</label>
  {/* Select All Units Checkbox */}
  {filteredUnits.length > 0 && (
    <div className="flex items-center mb-2">
      <input
        type="checkbox"
        id="select-all-units"
        checked={
          currentEnrollment?.unit_ids.length === filteredUnits.length &&
          filteredUnits.length > 0
        }
        onChange={() => {
          if (
            currentEnrollment?.unit_ids.length === filteredUnits.length &&
            filteredUnits.length > 0
          ) {
            setCurrentEnrollment((prev) => ({
              ...prev!,
              unit_ids: [],
            }))
          } else {
            setCurrentEnrollment((prev) => ({
              ...prev!,
              unit_ids: filteredUnits.map((unit) => unit.id),
            }))
          }
        }}
        className="mr-2 accent-blue-600"
      />
      <label htmlFor="select-all-units" className="text-sm font-medium text-blue-700">
        Select All Units
      </label>
    </div>
  )}
  <div className="max-h-32 overflow-y-auto border rounded-lg p-2">
    {isLoading ? (
      <div className="text-center py-4">Loading units...</div>
    ) : filteredUnits.length > 0 ? (
      filteredUnits.map((unit) => (
        <div key={unit.id} className="flex items-center mb-2">
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
            className="mr-2"
          />
          <label htmlFor={`unit-${unit.id}`} className="text-sm">
            {unit.code ? `${unit.code} - ${unit.name}` : unit.name}
          </label>
        </div>
      ))
    ) : (
      <div className="text-center py-4 text-gray-500">
        {currentEnrollment?.class_id ? "No units available for this class" : "Select a class first"}
      </div>
    )}
  </div>
</div>

              <div className="flex justify-end space-x-3">
                <button
                  type="button"
                  onClick={handleCloseModal}
                  className="px-6 py-3 bg-gradient-to-r from-gray-200 to-gray-300 text-gray-700 rounded-lg hover:from-gray-300 hover:to-gray-400 transition-all duration-200 font-medium shadow-sm hover:shadow-md"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-lg hover:from-blue-700 hover:to-indigo-700 transition-all duration-200 font-medium shadow-lg hover:shadow-xl transform hover:scale-105"
                  disabled={isLoading}
                >
                  {isLoading ? "Loading..." : "Enroll"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Assignment Modal */}
      {isAssignModalOpen && (
        <div className="fixed inset-0 flex items-center justify-center bg-black/60 backdrop-blur-sm z-50 animate-in fade-in duration-300">
          <div className="bg-white/95 backdrop-blur-sm p-8 rounded-2xl shadow-2xl max-w-lg w-full mx-4 border border-slate-200/50 animate-in slide-in-from-bottom-4 duration-300">
            <h2 className="text-xl font-bold mb-4">Assign Unit to Lecturer</h2>
            {error && <div className="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">{error}</div>}
            <form
              onSubmit={(e) => {
                e.preventDefault()
                if (assignData) {
                  router.post("/assign-unit", assignData, {
                    onSuccess: () => {
                      toast.success("Unit assigned to lecturer successfully!")
                      handleCloseAssignModal()
                    },
                    onError: (errors) => {
                      console.error("Error assigning unit:", errors)
                      setError("Failed to assign unit to lecturer. Please try again.")
                    },
                  })
                }
              }}
            >
              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700 mb-2">Unit</label>
                <select
                  value={assignData?.unit_id || ""}
                  onChange={(e) =>
                    setAssignData((prev) => ({
                      ...prev!,
                      unit_id: Number(e.target.value),
                    }))
                  }
                  className="w-full border border-slate-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-slate-50 focus:bg-white hover:border-slate-400"
                  required
                >
                  <option value="">Select a unit</option>
                  {units &&
                    units.map((unit) => (
                      <option key={unit.id} value={unit.id}>
                        {unit.code ? `${unit.code} - ${unit.name}` : unit.name}
                      </option>
                    ))}
                </select>
              </div>

              <div className="mb-6">
                <label className="block text-sm font-medium text-gray-700 mb-2">Lecturer Code</label>
                <input
                  type="text"
                  value={assignData?.lecturer_code || ""}
                  onChange={(e) =>
                    setAssignData((prev) => ({
                      ...prev!,
                      lecturer_code: e.target.value,
                    }))
                  }
                  className="w-full border border-slate-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-slate-50 focus:bg-white hover:border-slate-400"
                  required
                  placeholder="Enter lecturer code (e.g., BBITLEC001)"
                />
              </div>

              <div className="flex justify-end space-x-3">
                <button
                  type="button"
                  onClick={handleCloseAssignModal}
                  className="px-6 py-3 bg-gradient-to-r from-gray-200 to-gray-300 text-gray-700 rounded-lg hover:from-gray-300 hover:to-gray-400 transition-all duration-200 font-medium shadow-sm hover:shadow-md"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-lg hover:from-blue-700 hover:to-indigo-700 transition-all duration-200 font-medium shadow-lg hover:shadow-xl transform hover:scale-105"
                >
                  Assign
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </AuthenticatedLayout>
  )
}

export default Enrollments

