"use client"

import { useState } from "react"
import { Head, router } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"

interface ClassTimetable {
  id: number
  day: string
  unit_code: string
  unit_name: string
  semester_name: string
  venue: string
  start_time: string
  end_time: string
  location: string
  lecturer: string
  teaching_mode?: string
  group_name?: string
}

interface Student {
  id: number
  first_name: string
  last_name: string
  code: string
  groups?: number[]
}

interface Semester {
  id: number
  name: string
}

interface Props {
  classTimetables?: any
  currentSemester?: Semester
  availableSemesters?: Semester[] // Add available semesters
  student?: Student
  downloadUrl?: string
  filters?: {
    per_page?: number
    search?: string
    semester_id?: number // Add semester filter
  }
  error?: string
}

export default function StudentTimetable({
  classTimetables,
  currentSemester,
  availableSemesters = [],
  student,
  downloadUrl,
  filters = {},
  error,
}: Props) {
  const [perPage, setPerPage] = useState(filters.per_page || 10)
  const [searchTerm, setSearchTerm] = useState(filters.search || "")
  const [selectedSemesterId, setSelectedSemesterId] = useState(filters.semester_id || currentSemester?.id || "")

  // Safely extract timetable data
  let timetableData: ClassTimetable[] = []
  let paginationInfo: any = null

  try {
    if (Array.isArray(classTimetables)) {
      timetableData = classTimetables
    } else if (classTimetables && typeof classTimetables === "object") {
      if ("data" in classTimetables && Array.isArray(classTimetables.data)) {
        timetableData = classTimetables.data
        paginationInfo = classTimetables
      } else if ("classTimetables" in classTimetables) {
        timetableData = Array.isArray(classTimetables.classTimetables) ? classTimetables.classTimetables : []
      }
    }
  } catch (e) {
    console.error("Error processing timetable data:", e)
    timetableData = []
  }

  // Handle semester change
  const handleSemesterChange = (semesterId: string) => {
    setSelectedSemesterId(semesterId)

    if (semesterId) {
      router.get(
        window.location.pathname,
        {
          semester_id: semesterId,
          per_page: perPage,
          search: searchTerm,
          page: 1,
        },
        {
          preserveState: true,
          replace: true,
        },
      )
    }
  }

  // Handle per page change
  const handlePerPageChange = (newPerPage: number) => {
    setPerPage(newPerPage)
    router.get(
      window.location.pathname,
      {
        semester_id: selectedSemesterId,
        per_page: newPerPage,
        search: searchTerm,
      },
      {
        preserveState: true,
        replace: true,
      },
    )
  }

  // Handle search
  const handleSearch = () => {
    router.get(
      window.location.pathname,
      {
        semester_id: selectedSemesterId,
        per_page: perPage,
        search: searchTerm,
        page: 1,
      },
      {
        preserveState: true,
        replace: true,
      },
    )
  }

  // Handle pagination link click
  const handlePageClick = (url: string | null) => {
    if (url) {
      router.get(
        url,
        {},
        {
          preserveState: true,
          replace: true,
        },
      )
    }
  }

  // Group timetables by day
  const groupedTimetables = timetableData.reduce(
    (acc, timetable) => {
      if (!acc[timetable.day]) {
        acc[timetable.day] = []
      }
      acc[timetable.day].push(timetable)
      return acc
    },
    {} as Record<string, ClassTimetable[]>,
  )

  const daysOrder = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"]

  return (
    <AuthenticatedLayout>
      <Head title="My Timetable" />

      <div className="max-w-7xl mx-auto my-8 bg-white rounded-lg shadow-md p-6">
        {/* Header */}
        <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-6 gap-4">
          <div>
            <h1 className="text-3xl font-bold text-gray-900">My Timetable</h1>
            <div className="mt-2 flex items-center gap-2">
              <select
                value={selectedSemesterId}
                onChange={(e) => handleSemesterChange(e.target.value)}
                className="bg-white border border-blue-200 text-blue-900 text-sm rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 min-w-[200px]"
              >
                <option value="">Select Semester</option>
                {availableSemesters.map((semester) => (
                  <option key={semester.id} value={semester.id}>
                    {semester.name}
                  </option>
                ))}
              </select>
            </div>
          </div>

          {downloadUrl && selectedSemesterId && (
            <a
              href={`${downloadUrl}?semester_id=${selectedSemesterId}`}
              className="inline-flex items-center justify-center bg-green-600 hover:bg-green-700 text-white text-sm px-4 py-2 rounded-lg shadow-md transition-colors duration-200 font-medium"
            >
              <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                />
              </svg>
              Download PDF
            </a>
          )}
        </div>

        {/* Error Message */}
        {error && (
          <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
            <div className="flex items-center">
              <svg className="w-5 h-5 text-red-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path
                  fillRule="evenodd"
                  d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                  clipRule="evenodd"
                />
              </svg>
              <p className="text-red-700">{error}</p>
            </div>
          </div>
        )}

        {/* Student Info */}
        <div className="mb-6 p-4 border border-yellow-200 bg-gradient-to-r from-yellow-50 to-orange-50 rounded-lg">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <p className="text-sm text-gray-600">Student Name</p>
              <p className="font-semibold text-gray-900">
                {student ? `${student.first_name} ${student.last_name}` : "N/A"}
              </p>
            </div>
            <div>
              <p className="text-sm text-gray-600">Student ID</p>
              <p className="font-semibold text-gray-900">{student ? student.code : "N/A"}</p>
            </div>
            <div>
              <p className="text-sm text-gray-600">Total Classes</p>
              <p className="font-semibold text-gray-900">
                {paginationInfo ? paginationInfo.total : timetableData.length}
              </p>
            </div>
          </div>
        </div>

        {/* Show message if no semester selected */}
        {!selectedSemesterId && (
          <div className="text-center py-12">
            <div className="text-blue-400 mb-4">
              <svg className="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={1}
                  d="M8 7V3a2 2 0 012-2h4a2 2 0 012 2v4m-6 4v10m4-10v10m-8-4h16"
                />
              </svg>
            </div>
            <h3 className="text-lg font-medium text-gray-900 mb-2">Select a Semester</h3>
            <p className="text-gray-500">
              Please select a semester from the dropdown above to view your class timetable.
            </p>
          </div>
        )}

        {/* Search and Filters - Only show if semester is selected */}
        {selectedSemesterId && (
          <div className="mb-6 p-4 bg-gray-50 rounded-lg">
            <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
              {/* Search Box */}
              <div className="flex-1 max-w-md">
                <div className="flex">
                  <input
                    type="text"
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    placeholder="Search by unit, lecturer, venue..."
                    className="flex-1 px-3 py-2 border border-gray-300 rounded-l-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    onKeyDown={(e) => {
                      if (e.key === "Enter") {
                        handleSearch()
                      }
                    }}
                  />
                  <button
                    onClick={handleSearch}
                    className="px-4 py-2 bg-blue-600 text-white rounded-r-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors duration-200"
                  >
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                      />
                    </svg>
                  </button>
                </div>
              </div>

              {/* Per Page Selector */}
              <div className="flex items-center gap-2">
                <label htmlFor="perPage" className="text-sm font-medium text-gray-700">
                  Show:
                </label>
                <select
                  id="perPage"
                  value={perPage}
                  onChange={(e) => handlePerPageChange(Number.parseInt(e.target.value))}
                  className="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
                  <option value={5}>5</option>
                  <option value={10}>10</option>
                  <option value={15}>15</option>
                  <option value={20}>20</option>
                  <option value={25}>25</option>
                  <option value={50}>50</option>
                  <option value={100}>100</option>
                </select>
                <span className="text-sm text-gray-700">entries</span>
              </div>
            </div>
          </div>
        )}

        {/* Timetable Display - Only show if semester is selected */}
        {selectedSemesterId && (
          <>
            {timetableData.length === 0 ? (
              <div className="text-center py-12">
                <div className="text-gray-400 mb-4">
                  <svg className="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={1}
                      d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                    />
                  </svg>
                </div>
                <h3 className="text-lg font-medium text-gray-900 mb-2">No Classes Found</h3>
                <p className="text-gray-500">
                  {searchTerm
                    ? `No classes match your search "${searchTerm}"`
                    : "No class timetables are available for this semester yet."}
                </p>
                {searchTerm && (
                  <button
                    onClick={() => {
                      setSearchTerm("")
                      router.get(
                        window.location.pathname,
                        {
                          semester_id: selectedSemesterId,
                          per_page: perPage,
                          search: "",
                          page: 1,
                        },
                        {
                          preserveState: true,
                          replace: true,
                        },
                      )
                    }}
                    className="mt-4 px-4 py-2 text-blue-600 hover:text-blue-800 underline"
                  >
                    Clear search
                  </button>
                )}
              </div>
            ) : (
              <div className="space-y-6">
                {daysOrder.map((day) => {
                  const dayClasses = groupedTimetables[day]
                  if (!dayClasses || dayClasses.length === 0) return null

                  return (
                    <div key={day} className="border border-gray-200 rounded-lg overflow-hidden">
                      <div className="bg-gradient-to-r from-blue-600 to-blue-700 text-white px-4 py-3">
                        <h3 className="text-lg font-semibold flex items-center">
                          <span className="w-2 h-2 bg-white rounded-full mr-3"></span>
                          {day}
                          <span className="ml-auto text-sm bg-blue-500 px-2 py-1 rounded-full">
                            {dayClasses.length} {dayClasses.length === 1 ? "class" : "classes"}
                          </span>
                        </h3>
                      </div>
                      <div className="overflow-x-auto">
                        <table className="min-w-full">
                          <thead className="bg-gray-50">
                            <tr>
                              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Time
                              </th>
                              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Unit
                              </th>
                              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Venue
                              </th>
                              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Mode
                              </th>
                              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Lecturer
                              </th>
                              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Group
                              </th>
                            </tr>
                          </thead>
                          <tbody className="bg-white divide-y divide-gray-200">
                            {dayClasses.map((cls) => (
                              <tr key={cls.id} className="hover:bg-gray-50 transition-colors duration-150">
                                <td className="px-4 py-4 whitespace-nowrap">
                                  <div className="flex items-center">
                                    <div className="text-sm font-medium text-gray-900">{cls.start_time}</div>
                                    <div className="text-sm text-gray-500 ml-1">- {cls.end_time}</div>
                                  </div>
                                </td>
                                <td className="px-4 py-4">
                                  <div className="text-sm font-medium text-gray-900">{cls.unit_code}</div>
                                  <div className="text-sm text-gray-500">{cls.unit_name}</div>
                                </td>
                                <td className="px-4 py-4">
                                  <div className="text-sm text-gray-900">{cls.venue || "TBA"}</div>
                                  {cls.location && <div className="text-sm text-gray-500">{cls.location}</div>}
                                </td>
                                <td className="px-4 py-4 whitespace-nowrap">
                                  <span
                                    className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                      cls.teaching_mode === "online"
                                        ? "bg-blue-100 text-blue-800"
                                        : "bg-green-100 text-green-800"
                                    }`}
                                  >
                                    {cls.teaching_mode === "online" ? "🌐 Online" : "🏢 Physical"}
                                  </span>
                                </td>
                                <td className="px-4 py-4">
                                  <div className="text-sm text-gray-900">{cls.lecturer || "TBA"}</div>
                                </td>
                                <td className="px-4 py-4 whitespace-nowrap">
                                  <div className="text-sm text-gray-900">{cls.group_name || "N/A"}</div>
                                </td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </div>
                    </div>
                  )
                })}
              </div>
            )}

            {/* Pagination */}
            {paginationInfo && paginationInfo.links && paginationInfo.links.length > 3 && (
              <div className="mt-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div className="text-sm text-gray-600">
                  Showing <span className="font-semibold text-gray-900">{paginationInfo.from || 0}</span> to{" "}
                  <span className="font-semibold text-gray-900">{paginationInfo.to || 0}</span> of{" "}
                  <span className="font-semibold text-gray-900">{paginationInfo.total || 0}</span> classes
                </div>
                <div className="flex flex-wrap gap-1">
                  {paginationInfo.links.map((link: any, idx: number) => {
                    const isDisabled = !link.url
                    const isCurrent = link.active
                    const isEllipsis = link.label === "..."

                    if (isEllipsis) {
                      return (
                        <span key={idx} className="px-3 py-2 text-gray-500">
                          ...
                        </span>
                      )
                    }

                    return (
                      <button
                        key={idx}
                        onClick={() => handlePageClick(link.url)}
                        disabled={isDisabled}
                        className={`px-3 py-2 rounded-lg border text-sm font-medium transition-colors duration-200 ${
                          isCurrent
                            ? "bg-blue-600 text-white border-blue-600 shadow-sm"
                            : isDisabled
                              ? "bg-gray-100 text-gray-400 border-gray-300 cursor-not-allowed"
                              : "bg-white text-gray-700 border-gray-300 hover:bg-gray-50 hover:border-gray-400"
                        }`}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                      />
                    )
                  })}
                </div>
              </div>
            )}
          </>
        )}

        {/* Footer */}
        <div className="mt-8 text-center text-xs text-white bg-gradient-to-r from-blue-800 to-blue-900 rounded-lg py-4 shadow-inner">
          <p className="font-medium">📋 Official Timetable Document</p>
          <p className="mt-1 opacity-90">
            Please keep this for your records and contact administration for any discrepancies.
          </p>
        </div>
      </div>
    </AuthenticatedLayout>
  )
}
